<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Grants and revokes the Platform Admin tier added in
 * 2026_08_17_110000_separate_platform_and_company_roles — a user with no
 * reach of their own until the Center explicitly assigns them a company via
 * `platform_admin_assignments`. Super-Admin-only in both directions, mirroring
 * `platform_admin_assignments_write`'s RLS policy: nobody below that tier can
 * create a peer or widen their own scope.
 */
class PlatformAdminController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function index(Request $request)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companies = $supabase->get('companies', [
            'select' => 'id,name,code',
            'order' => 'name.asc',
            'limit' => 200,
        ]);

        $assignments = $supabase->get('platform_admin_assignments', [
            'select' => 'id,user_id,company_id,created_at,users(name,email),companies(name,code)',
            'order' => 'created_at.desc',
        ]);

        return Inertia::render('Platform/PlatformAdmins/Index', [
            'companies' => $companies,
            'assignments' => $assignments,
        ]);
    }

    /**
     * Looks the invitee up by email rather than accepting a user id directly
     * — a Super Admin manages people by the email they already know, not by
     * a Supabase-generated uuid. The account must already exist (created via
     * some company's own invite flow); this grants platform reach, it does
     * not create identities.
     */
    public function store(Request $request)
    {
        $this->ensureSuperAdmin($request);

        $request->validate([
            'email' => 'required|email',
            'company_ids' => 'required|array|min:1',
            'company_ids.*' => 'uuid',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $user = $supabase->first('users', [
            'email' => 'eq.' . $request->email,
            'select' => 'id,role',
        ]);

        if (!$user) {
            return back()->withInput()->with('error', 'No Performix account exists for that email yet — they need one (via a company invite) before they can be granted Platform Admin access.');
        }

        if ($user['role'] === 'richworks_super_admin') {
            return back()->withInput()->with('error', 'That account is already a Richworks Super Admin, which already sees every company.');
        }

        if ($user['role'] !== 'platform_admin') {
            try {
                $supabase->update('users', ['id' => 'eq.' . $user['id']], ['role' => 'platform_admin']);
            } catch (\Throwable $e) {
                return back()->withInput()->with('error', 'Could not grant the Platform Admin tier: ' . $e->getMessage());
            }
        }

        $granted = 0;
        $skipped = 0;

        foreach ($request->company_ids as $companyId) {
            try {
                $supabase->insert('platform_admin_assignments', [
                    'user_id' => $user['id'],
                    'company_id' => $companyId,
                    'granted_by' => $request->attributes->get('platformUser')['id'],
                ]);
                $granted++;
            } catch (\Throwable) {
                // Unique violation — already assigned to this company.
                $skipped++;
            }
        }

        try {
            $this->logAdminAction($request, 'grant_platform_admin', null, $user['id'], [
                'company_ids' => $request->company_ids,
                'granted' => $granted,
                'skipped' => $skipped,
            ]);
        } catch (\Throwable) {
            return back()->with('error', 'Access was granted, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', "{$granted} company assignment(s) granted." . ($skipped > 0 ? " {$skipped} already existed." : ''));
    }

    public function destroy(Request $request, string $assignment)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $row = $supabase->first('platform_admin_assignments', [
            'id' => 'eq.' . $assignment,
            'select' => 'id,user_id',
        ]);

        if (!$row) {
            return back()->with('error', 'That assignment no longer exists.');
        }

        try {
            $supabase->delete('platform_admin_assignments', ['id' => 'eq.' . $assignment]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not revoke that assignment: ' . $e->getMessage());
        }

        try {
            $this->logAdminAction($request, 'revoke_platform_admin_assignment', null, $row['user_id']);
        } catch (\Throwable) {
            return back()->with('error', 'Assignment was revoked, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Assignment revoked.');
    }

    /**
     * Revokes every company assignment and drops the user back to `member`.
     * Done together deliberately: a platform_admin with zero assignments is
     * harmless (auth_platform_company_ids() returns nothing for them either
     * way) but leaving the role tier set is a confusing half-state — "why is
     * this person marked Platform Admin with no companies?" should never be
     * a question anyone has to answer later.
     */
    public function demote(Request $request, string $user)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            $supabase->delete('platform_admin_assignments', ['user_id' => 'eq.' . $user]);
            $supabase->update('users', ['id' => 'eq.' . $user], ['role' => 'member']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not demote this user: ' . $e->getMessage());
        }

        try {
            $this->logAdminAction($request, 'demote_platform_admin', null, $user);
        } catch (\Throwable) {
            return back()->with('error', 'User was demoted, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'User demoted to member — all company assignments revoked.');
    }
}
