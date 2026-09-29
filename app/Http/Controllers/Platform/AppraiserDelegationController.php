<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\PlatformNotificationService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;

/**
 * Ports legacy's Appraiser Delegation (AppraiserDelegationController/
 * Service) — see the migration's own docblock
 * (2026_09_29_090000_create_appraiser_delegations.php) for the full
 * design-adaptation rationale (Company-Admin-only instead of BTS-only, the
 * delegate is the manager's own `manager_user_id` instead of a `vp_id`).
 *
 * No standalone page — surfaced as an admin-only panel on
 * Platform/Performance/Index.tsx, mirroring legacy's own choice to embed it
 * on an existing admin page (Quarter Control) rather than give it a
 * dedicated screen.
 */
class AppraiserDelegationController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function store(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'manager_user_id' => 'required|uuid',
            'reason' => 'nullable|string|max:500',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $platformUser = $request->attributes->get('platformUser');

        $manager = $supabase->first('company_users', [
            'company_id' => 'eq.' . $company,
            'user_id' => 'eq.' . $request->manager_user_id,
            'status' => 'eq.active',
            'select' => 'user_id,manager_user_id,users!company_users_user_id_foreign(name)',
        ]);

        if (!$manager) {
            return back()->with('error', 'That person is not an active member of this company.');
        }

        // The delegate is always this manager's OWN manager — never
        // client-chosen — mirroring legacy's server-computed vp_id/
        // reports_to_id exactly. No one above them in the chain means
        // nobody to stand in, same as legacy's "no VP found" rejection.
        $delegateId = $manager['manager_user_id'] ?? null;

        if (!$delegateId) {
            return back()->with('error', 'This person has no one above them in the reporting chain to delegate to.');
        }

        $existing = $supabase->first('appraiser_delegations', [
            'company_id' => 'eq.' . $company,
            'manager_user_id' => 'eq.' . $request->manager_user_id,
            'select' => 'id',
        ]);

        try {
            if ($existing) {
                $supabase->update('appraiser_delegations', ['id' => 'eq.' . $existing['id']], [
                    'delegate_user_id' => $delegateId,
                    'reason' => $request->reason,
                    'created_by' => $platformUser['id'],
                ], false);
            } else {
                $supabase->insert('appraiser_delegations', [
                    'company_id' => $company,
                    'manager_user_id' => $request->manager_user_id,
                    'delegate_user_id' => $delegateId,
                    'reason' => $request->reason,
                    'created_by' => $platformUser['id'],
                ], false);
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not create delegation: ' . $e->getMessage());
        }

        app(PlatformNotificationService::class)->notify(
            $supabase,
            $company,
            $delegateId,
            'Appraisal delegation assigned',
            'You are now standing in as appraiser for ' . ($manager['users']['name'] ?? 'a manager') . "'s reports.",
        );

        try {
            $this->logCompanyAction($request, 'delegate_appraiser', $company, $delegateId, [
                'manager_user_id' => $request->manager_user_id, 'reason' => $request->reason,
            ], 'appraiser_delegation');
        } catch (\Throwable) {
            return back()->with('error', 'Delegation was created, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Appraiser delegation created.');
    }

    public function destroy(Request $request, string $company, string $manager)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $existing = $supabase->first('appraiser_delegations', [
            'company_id' => 'eq.' . $company,
            'manager_user_id' => 'eq.' . $manager,
            'select' => 'id,delegate_user_id',
        ]);

        if (!$existing) {
            return back()->with('error', 'No active delegation found for that manager.');
        }

        try {
            $supabase->delete('appraiser_delegations', ['id' => 'eq.' . $existing['id']]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not end delegation: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'end_appraiser_delegation', $company, $existing['delegate_user_id'], [
                'manager_user_id' => $manager,
            ], 'appraiser_delegation', $existing['id']);
        } catch (\Throwable) {
            return back()->with('error', 'Delegation was ended, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Delegation ended.');
    }
}
