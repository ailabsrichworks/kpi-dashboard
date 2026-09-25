<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Mail\PlatformInviteMail;
use App\Services\CompanyLifecycleService;
use App\Services\SupabaseAuthService;
use App\Services\SupabaseService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

/**
 * Richworks Super Admin only. Every read/write here goes through the
 * caller's own `SupabaseUserService` (their real Supabase Auth token) — never
 * service_role — so it is RLS, not this controller, that actually enforces
 * "only a Super Admin may do this." `store()`/`storeAdmin()` will simply fail
 * with a 403 from Postgres if the caller somehow isn't one. `ensureSuperAdmin()`
 * (from PlatformAuthorization) is defense-in-depth, not a substitute for the
 * database-level checks.
 */
class CompanyController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    /**
     * The lifecycle: draft -> onboarding -> configuring -> active ->
     * suspended -> archived. Keyed by the action that performs the
     * transition, valued by the statuses it's allowed to run from — a
     * company outside that list gets a clean 422 instead of the Postgres
     * check constraint or a silent no-op.
     *
     * `draft`/`onboarding`/`configuring` are never targets here — those are
     * auto-advanced by `CompanyLifecycleService` as real setup work happens,
     * not something an admin clicks a button to set directly.
     */
    private const ALLOWED_TRANSITIONS = [
        'activate' => ['draft', 'onboarding', 'configuring'],
        'suspend' => ['draft', 'onboarding', 'configuring', 'active'],
        'reactivate' => ['suspended'],
        'archive' => ['active', 'suspended'],
        'unarchive' => ['archived'],
    ];

    private function currentStatus(SupabaseUserService $supabase, string $company): ?string
    {
        return $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'status'])['status'] ?? null;
    }

    private function ensureValidTransition(string $action, ?string $currentStatus): void
    {
        abort_unless(
            in_array($currentStatus, self::ALLOWED_TRANSITIONS[$action], true),
            422,
            "Cannot {$action} a company that is currently '" . ($currentStatus ?? 'unknown') . "'."
        );
    }

    public function index(Request $request)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        // Spec requirement #37: avoid loading all companies' data when a
        // reasonable cap covers every realistic near-term case. Not full
        // pagination (no UI need for it yet, and building one now would be
        // speculative) — just a ceiling so this can never grow unbounded,
        // matching the same defensive limit already used on
        // ImportController::show() (20) and AuditLogController (200).
        $companies = $supabase->get('companies', [
            'select' => '*',
            'order' => 'created_at.desc',
            'limit' => 200,
        ]);

        $companyIds = array_column($companies, 'id');

        $admins = empty($companyIds)
            ? []
            : $supabase->get('company_users', [
                'company_id' => 'in.(' . implode(',', $companyIds) . ')',
                'role' => 'eq.company_admin',
                'select' => 'company_id,users(name,email)',
            ]);

        return Inertia::render('Platform/Companies/Index', [
            'companies' => $companies,
            'admins' => $admins,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureSuperAdmin($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            // Explicit, not left to the column default ('active') — a brand
            // new company has no admin/departments/KPIs yet, and the whole
            // onboarding wizard (OnboardingController) assumes a company
            // starts non-active. Leaving this to the default silently marked
            // every new company "already live" and hid the Activate button
            // behind an admin-existence check nothing could ever reach.
            // 'draft' — not 'onboarding' — because nothing has actually
            // started yet; storeAdmin() below is what advances it.
            $newCompany = $supabase->insert('companies', [
                'name' => $request->name,
                'code' => strtoupper($request->code),
                'status' => 'draft',
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not create company: ' . $e->getMessage());
        }

        try {
            $this->logAdminAction($request, 'create_company', $newCompany[0]['id'], null, [], 'company', $newCompany[0]['id'], null, [
                'name' => $request->name,
                'code' => $newCompany[0]['code'],
                'status' => 'draft',
            ]);
        } catch (\Throwable) {
            return back()->with('error', 'Company was created, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Company "' . $request->name . '" created.');
    }

    /**
     * Invites the first Company Admin for a company. Creating the Supabase
     * Auth user requires service_role (a genuinely privileged "invite"
     * operation — see SupabaseAuthService), but linking them to the company
     * via `company_users` still goes through the caller's own RLS-scoped
     * token, so a non-Super-Admin calling this endpoint gets rejected by
     * Postgres on that second step even if the first step succeeded.
     *
     * Phase 11: no password is generated here at all — generateInviteLink()
     * mints a token we email a link around, and the invitee chooses their
     * own password when they accept it (InviteController::setPassword()).
     */
    public function storeAdmin(Request $request, string $company, SupabaseAuthService $auth, SupabaseService $privileged)
    {
        $this->ensureSuperAdmin($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            $invite = $auth->generateInviteLink($request->email, [
                'name' => $request->name,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not create the admin account: ' . $e->getMessage());
        }

        // Supabase's generate_link response has `id` at the top level, not
        // nested under a `user` key — confirmed by calling the real API
        // directly. The old `$invite['user']['id']` path was always null,
        // which meant every admin invite through this endpoint silently
        // failed after already creating the Supabase Auth user, leaving an
        // orphaned account with no company_users link.
        $newUserId = $invite['id'] ?? null;

        if (!$newUserId) {
            return back()->with('error', 'Invite was created but the new account id was missing — try again.');
        }

        // Looked up via the privileged client, not the caller's own RLS-scoped
        // one: `users_select` only lets a caller see people already linked to
        // one of their companies — which this brand-new user isn't, yet. The
        // ensureSuperAdmin() check above is what actually authorizes this.
        // generateInviteLink() already gives us the id synchronously; this
        // wait is only for the `on_auth_user_created` trigger to finish
        // writing the matching `public.users` row `company_users` needs.
        //
        // Filtered on `auth_user_id`, NOT `id` — `$newUserId` is the
        // Supabase Auth user's id (auth.users.id), which `public.users`
        // stores as `auth_user_id`; `public.users.id` is a separate,
        // independently-generated primary key. Filtering on `id` here always
        // found nothing, so this poll ran out its retries and failed on
        // every single invite, real API response confirmed.
        $newUser = $privileged->firstEventually('users', [
            'auth_user_id' => 'eq.' . $newUserId,
            'select' => 'id',
        ]);

        if (!$newUser) {
            return back()->with('error', 'Admin account was created but its profile row never appeared — try linking them again in a moment.');
        }

        try {
            $supabase->insert('company_users', [
                'company_id' => $company,
                'user_id' => $newUser['id'],
                'role' => 'company_admin',
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Admin account was created but could not be linked to the company: ' . $e->getMessage());
        }

        // A real admin now exists — the first sign this company is actually
        // being set up rather than sitting as an empty shell. No-ops if the
        // company already moved past 'draft' (e.g. a second admin invite).
        CompanyLifecycleService::advanceTo($supabase, $company, 'onboarding');

        try {
            $this->logAdminAction($request, 'invite_company_admin', $company, $newUser['id'], [
                'email' => $request->email,
            ], 'company_user', $newUser['id']);
        } catch (\Throwable) {
            return back()->with('error', 'Admin was created and linked, but the action could not be logged — contact support before continuing.');
        }

        $companyRow = $supabase->first('companies', [
            'id' => 'eq.' . $company,
            'select' => 'name',
        ]);

        try {
            Mail::to($request->email)->send(new PlatformInviteMail(
                route('platform.invite.accept', ['token' => $invite['hashed_token']]),
                $request->name,
                $companyRow['name'] ?? 'Performix',
                'a Company Admin',
            ));
        } catch (\Throwable $e) {
            return back()->with('error', 'Admin was created and linked, but the invite email could not be sent: ' . $e->getMessage());
        }

        return back()->with('success', "Invite sent to {$request->email}.");
    }

    /**
     * First activation: `companies.status` -> `active`, `onboarding_status`
     * -> `completed`, `activated_at` -> now. Gated on at least one active
     * Company Admin existing — a company created but never given an admin
     * has no one who could ever manage it once live. This is a lighter
     * check than the full Review-step checklist the onboarding wizard
     * (Phase 6) will eventually gate activation behind; it only covers the
     * one precondition that would otherwise be irreversible to discover
     * after the fact.
     */
    public function activate(Request $request, string $company)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $beforeStatus = $this->currentStatus($supabase, $company);
        $this->ensureValidTransition('activate', $beforeStatus);

        $hasAdmin = $supabase->first('company_users', [
            'company_id' => 'eq.' . $company,
            'role' => 'eq.company_admin',
            'status' => 'eq.active',
            'select' => 'id',
        ]);

        if (!$hasAdmin) {
            return back()->with('error', 'Cannot activate — this company has no active Company Admin yet. Invite one first.');
        }

        try {
            $supabase->update('companies', ['id' => 'eq.' . $company], [
                'status' => 'active',
                'onboarding_status' => 'completed',
                'activated_at' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not activate company: ' . $e->getMessage());
        }

        try {
            $this->logAdminAction($request, 'activate_company', $company, null, [], 'company', $company, ['status' => $beforeStatus], ['status' => 'active']);
        } catch (\Throwable) {
            return back()->with('error', 'Company was activated, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Company activated — now LIVE.');
    }

    /**
     * Suspension is enforced at the RLS layer, not just this status label —
     * see 2026_08_14_060000_enforce_company_suspension_in_rls.php. Once this
     * runs, the suspended company's own users lose read/write access to
     * their own data immediately, on their very next request.
     */
    public function suspend(Request $request, string $company)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $beforeStatus = $this->currentStatus($supabase, $company);
        $this->ensureValidTransition('suspend', $beforeStatus);

        try {
            $supabase->update('companies', ['id' => 'eq.' . $company], ['status' => 'suspended']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not suspend company: ' . $e->getMessage());
        }

        try {
            $this->logAdminAction($request, 'suspend_company', $company, null, [], 'company', $company, ['status' => $beforeStatus], ['status' => 'suspended']);
        } catch (\Throwable) {
            return back()->with('error', 'Company was suspended, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Company suspended — its users have lost access.');
    }

    public function reactivate(Request $request, string $company)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $beforeStatus = $this->currentStatus($supabase, $company);
        $this->ensureValidTransition('reactivate', $beforeStatus);

        try {
            $supabase->update('companies', ['id' => 'eq.' . $company], ['status' => 'active']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not reactivate company: ' . $e->getMessage());
        }

        try {
            $this->logAdminAction($request, 'reactivate_company', $company, null, [], 'company', $company, ['status' => $beforeStatus], ['status' => 'active']);
        } catch (\Throwable) {
            return back()->with('error', 'Company was reactivated, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Company reactivated.');
    }

    /**
     * Terminal-ish, not a soft delete: an archived company's own users lose
     * access exactly like a suspended one (see the RLS update in
     * 2026_08_17_130000), but `unarchive()` deliberately lands back on
     * `suspended` rather than `active` — bringing a retired company back
     * goes through the same reactivate() checkpoint a normal suspension
     * does, rather than skipping straight to live.
     */
    public function archive(Request $request, string $company)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $beforeStatus = $this->currentStatus($supabase, $company);
        $this->ensureValidTransition('archive', $beforeStatus);

        try {
            $supabase->update('companies', ['id' => 'eq.' . $company], ['status' => 'archived']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not archive company: ' . $e->getMessage());
        }

        try {
            $this->logAdminAction($request, 'archive_company', $company, null, [], 'company', $company, ['status' => $beforeStatus], ['status' => 'archived']);
        } catch (\Throwable) {
            return back()->with('error', 'Company was archived, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Company archived — its users have lost access.');
    }

    public function unarchive(Request $request, string $company)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $beforeStatus = $this->currentStatus($supabase, $company);
        $this->ensureValidTransition('unarchive', $beforeStatus);

        try {
            $supabase->update('companies', ['id' => 'eq.' . $company], ['status' => 'suspended']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not unarchive company: ' . $e->getMessage());
        }

        try {
            $this->logAdminAction($request, 'unarchive_company', $company, null, [], 'company', $company, ['status' => $beforeStatus], ['status' => 'suspended']);
        } catch (\Throwable) {
            return back()->with('error', 'Company was unarchived, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Company unarchived — reactivate it to bring it fully live again.');
    }

    /**
     * Branding-only company config (Blueprint §17 decision: no separate
     * organization_settings table for v1). display_name is what the
     * Platform's own chrome would show in place of the legal `name` once
     * branded pages exist — not yet consumed anywhere, written ahead of that
     * feature the same way `logo_url` already was.
     */
    public function updateBranding(Request $request, string $company)
    {
        $this->ensureSuperAdmin($request);

        $request->validate([
            'display_name' => 'nullable|string|max:255',
            'primary_color' => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            $supabase->update('companies', ['id' => 'eq.' . $company], [
                'display_name' => $request->display_name,
                'primary_color' => $request->primary_color,
                'secondary_color' => $request->secondary_color,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not update branding: ' . $e->getMessage());
        }

        return back()->with('success', 'Branding updated.');
    }
}