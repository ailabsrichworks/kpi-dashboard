<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\AuditLogService;
use App\Services\SupabaseAuthService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The Platform's real "View As" — ports legacy `AdminController`'s shape
 * (search a user, start/stop, snapshot-and-restore the admin's own session,
 * log every use) onto real Supabase Auth sessions instead of a plain
 * session-variable swap. Legacy's BTS-department restriction is legacy's
 * *only* cross-employee access concept; the Platform's equivalent is
 * `richworks_super_admin` — the one tier with unconditional reach across
 * every company (see CLAUDE.md's role table) — not Platform Admin, whose
 * reach is deliberately narrowed to assigned companies only.
 *
 * A plain session-variable swap (what legacy does) can't work here: every
 * Platform request re-derives identity and RLS access from the JWT inside
 * `platform_access_token` (see PlatformAuth), never from arbitrary session
 * values. To genuinely view the Platform as someone else — with their real,
 * RLS-narrowed access, not a Super Admin's own bypass — this mints a real,
 * short-lived Supabase Auth session for the target via
 * SupabaseAuthService::mintSessionAccessToken() (the same magiclink
 * generate-then-verify round trip TelegramAuthorizedScope already uses) and
 * swaps it into `platform_access_token`, the exact session key every other
 * request already trusts. `platformUser` on every subsequent request
 * therefore correctly resolves to the TARGET's own real identity/role —
 * which is also why the sidebar's Super-Admin-only section correctly
 * disappears while impersonating (the caller's own tier isn't consulted
 * anymore, the target's is) and why a persistent banner, not the nav, is
 * what has to carry "Return to my account".
 */
class ViewAsController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function index(Request $request)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $search = trim((string) $request->query('q', ''));

        $filters = [
            'select' => 'id,name,email,role,status',
            'order' => 'name.asc',
            'limit' => 200,
        ];

        if ($search !== '') {
            $escaped = str_replace(['%', '*'], ['\%', '\*'], $search);
            $filters['or'] = "(name.ilike.*{$escaped}*,email.ilike.*{$escaped}*)";
        }

        $users = $supabase->get('users', $filters) ?? [];

        return Inertia::render('Platform/ViewAs/Index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function start(Request $request, string $user)
    {
        $this->ensureSuperAdmin($request);

        if (session('admin_impersonating')) {
            return back()->with('error', 'Return to your own account before viewing as someone else.');
        }

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $target = $supabase->first('users', [
            'id' => 'eq.' . $user,
            'status' => 'eq.active',
            'select' => 'id,name,email',
        ]);

        if (!$target) {
            return back()->with('error', 'User not found or inactive.');
        }

        $mintedToken = app(SupabaseAuthService::class)->mintSessionAccessToken($target['email']);

        if (!$mintedToken) {
            return back()->with('error', 'Could not start a session for this user.');
        }

        $admin = $request->attributes->get('platformUser');

        // Logged BEFORE the session is swapped — if this throws, impersonation
        // never starts, so there's no gap where a "view as" happened without
        // a row proving it (not best-effort: an infrequent, deliberate,
        // sensitive admin action, matching CompanyController::store()'s own
        // "a silent gap is worse than a visible error" reasoning).
        try {
            $this->logAdminAction($request, 'start_view_as', null, $target['id'], [
                'target_name' => $target['name'],
                'target_email' => $target['email'],
            ], 'user', $target['id']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not start View As — the action could not be logged: ' . $e->getMessage());
        }

        // Snapshot the admin's own identity + session, not just the token —
        // stop() needs the admin's own actor fields to log against, since by
        // the time it runs `platformUser` will have already resolved to the
        // impersonated target instead (the whole point of the swap below).
        session(['admin_original_session' => [
            'platform_access_token' => session('platform_access_token'),
            'platform_refresh_token' => session('platform_refresh_token'),
            'admin_user_id' => $admin['id'] ?? null,
            'admin_email' => $admin['email'] ?? null,
            'target_user_id' => $target['id'],
        ]]);

        session([
            'admin_impersonating' => true,
            'platform_access_token' => $mintedToken,
            'platform_refresh_token' => null,
        ]);

        return redirect()->route('platform.dashboard');
    }

    public function stop(Request $request, AuditLogService $auditLog)
    {
        $original = session('admin_original_session');

        if (!$original) {
            return redirect()->route('platform.dashboard');
        }

        // Direct AuditLogService call, not logBestEffort() — at this point in
        // the request `platformUser` has already resolved to the IMPERSONATED
        // target (PlatformAuth ran before this controller, off the still-live
        // target token), so the trait's auto-derived actor would misattribute
        // this to the target instead of the real admin. The snapshot above
        // carries the admin's own identity for exactly this reason.
        $auditLog->recordBestEffort([
            'actor_user_id' => $original['admin_user_id'] ?? null,
            'actor_email' => $original['admin_email'] ?? null,
            'action' => 'stop_view_as',
            'target_user_id' => $original['target_user_id'] ?? null,
            'target_type' => 'user',
            'target_id' => $original['target_user_id'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        session([
            'platform_access_token' => $original['platform_access_token'] ?? null,
            'platform_refresh_token' => $original['platform_refresh_token'] ?? null,
        ]);
        session()->forget(['admin_impersonating', 'admin_original_session']);

        return redirect()->route('platform.dashboard');
    }
}
