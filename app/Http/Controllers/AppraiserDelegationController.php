<?php

namespace App\Http\Controllers;

use App\Services\AppraiserDelegationService;
use App\Services\NotificationService;
use App\Services\SupabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * BTS-only: stand a Manager's own VP in as appraiser for that Manager's
 * Executives while the Manager is on long leave (see
 * AppraiserDelegationService for the resolution logic this feeds). Rendered
 * as a section on the Quarter Control page (QuarterOverrideController::index()
 * supplies the list data); this controller only ever handles the write side.
 *
 * Gated the same way every other BTS-only feature in this app is --
 * Controller::isBtsSession(), checked per-method (there's no BTS middleware
 * in this codebase) -- not by route middleware.
 */
class AppraiserDelegationController extends Controller
{
    public function __construct(private AppraiserDelegationService $delegations)
    {
    }

    private function ensureBts(): void
    {
        abort_unless($this->isQuarterControlAuthorized(), 403, 'Not authorized for Quarter Control.');
    }

    public function store(Request $request, SupabaseService $supabase, NotificationService $notifications)
    {
        $this->ensureBts();

        $validated = $request->validate([
            'manager_id' => 'required|string',
            'reason'     => 'nullable|string|max:500',
        ]);

        $manager = $supabase->first('employees', [
            'id'        => 'eq.' . $validated['manager_id'],
            'is_active' => 'eq.true',
            'select'    => 'id,short_name,role,vp_id,reports_to_id',
        ]);

        if (!$manager) {
            return back()->with('error', 'Employee not found.');
        }

        if (strtoupper(trim($manager['role'] ?? '')) !== 'MANAGER') {
            return back()->with('error', "{$manager['short_name']} isn't a Manager — this feature only substitutes a VP for an absent Manager's own appraisals of their Executives.");
        }

        // Deliberately the manager's OWN resolved parent, never a value the
        // form could submit directly -- so the substitute is always someone
        // who'd already be next in that manager's real chain (still
        // "melalui role"), and a VP's own duty can never be delegated onward
        // through this same field (there's no case for it — see
        // AppraiserDelegationService's docblock).
        $delegateToId = $manager['vp_id'] ?? $manager['reports_to_id'] ?? null;

        if (!$delegateToId) {
            return back()->with('error', "{$manager['short_name']} has no VP on record (vp_id/reports_to_id both empty) — nothing to delegate to.");
        }

        $delegate = $supabase->first('employees', [
            'id'        => 'eq.' . $delegateToId,
            'is_active' => 'eq.true',
            'select'    => 'id,short_name',
        ]);

        if (!$delegate) {
            return back()->with('error', "{$manager['short_name']}'s recorded VP is missing or inactive — nothing to delegate to.");
        }

        try {
            $this->delegations->setDelegate(
                $manager['id'],
                $delegate['id'],
                $validated['reason'] ?? null,
                session('employee_uuid'),
                session('employee.short_name') ?? session('short_name')
            );
        } catch (\Throwable $e) {
            Log::error('AppraiserDelegationController::store failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Could not save the delegation — the appraiser_delegations table may not exist yet. Run database/sql/create_appraiser_delegations.sql in Supabase first.');
        }

        $transferredCount = $this->notifyDelegateOfAlreadyPendingAppraisals($manager, $delegate, $supabase, $notifications);

        $message = "{$delegate['short_name']} will now appraise {$manager['short_name']}'s Executives while {$manager['short_name']} is away.";
        if ($transferredCount > 0) {
            $message .= " {$transferredCount} appraisal(s) already submitted and waiting for review were just re-notified to {$delegate['short_name']}.";
        }

        return back()->with('success', $message);
    }

    /**
     * Access control (resolveAppraiserLevel) is resolved live on every page
     * load, so the delegate can already open/score any of the absent
     * Manager's Executives' appraisals the moment this delegation is saved
     * -- but there's no "my team's pending appraisals" listing page in this
     * app (grep confirms performance.appraise.report is only ever linked
     * from a notification), so an appraisal an Executive already submitted
     * BEFORE this delegation existed already sent its "ready for your
     * review" notification to the absent Manager, and the delegate has no
     * way to discover it. This re-sends that notification to the delegate
     * for every such still-pending (status = submitted) case, so nothing
     * already waiting is silently stuck. Anything submitted AFTER this
     * point already notifies the delegate correctly via
     * NotificationService::appraiserChainFor()'s own delegation lookup.
     */
    private function notifyDelegateOfAlreadyPendingAppraisals(array $manager, array $delegate, SupabaseService $supabase, NotificationService $notifications): int
    {
        $financialYear = 'FY' . now()->year;

        $executives = $supabase->get('employees', [
            'manager_id' => 'eq.' . $manager['id'],
            'is_active'  => 'eq.true',
            'select'     => 'id,full_name,short_name',
        ]) ?? [];

        if (empty($executives)) {
            return 0;
        }

        $execIds = collect($executives)->pluck('id')->all();
        $execById = collect($executives)->keyBy('id');

        try {
            $pendingReports = $supabase->get('performance_reports', [
                'employee_id'    => 'in.(' . implode(',', $execIds) . ')',
                'financial_year' => 'eq.' . $financialYear,
                'status'         => 'eq.submitted',
                'select'         => 'employee_id,quarter',
            ]) ?? [];
        } catch (\Throwable $e) {
            Log::error('AppraiserDelegationController::notifyDelegateOfAlreadyPendingAppraisals failed', ['error' => $e->getMessage()]);
            return 0;
        }

        foreach ($pendingReports as $report) {
            $employee = $execById->get($report['employee_id']);
            if (!$employee) {
                continue;
            }

            $employeeName = $employee['full_name'] ?? $employee['short_name'] ?? 'An employee';
            $q = $report['quarter'];

            $notifications->notify(
                [$delegate['id']],
                'appraisal_submitted',
                ['id' => $employee['id'], 'name' => $employeeName],
                "{$employeeName} submitted their {$q} appraisal",
                "Ready for your review — reassigned to you while {$manager['short_name']} is away.",
                route('performance.appraise.report', [$employee['id'], strtolower($q)]),
                $q,
                $financialYear
            );
        }

        return count($pendingReports);
    }

    public function destroy(Request $request, string $managerId)
    {
        $this->ensureBts();

        try {
            $this->delegations->clearDelegate($managerId);
        } catch (\Throwable $e) {
            Log::error('AppraiserDelegationController::destroy failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Could not end the delegation.');
        }

        return back()->with('success', 'Appraiser delegation ended — back to the normal reporting chain.');
    }
}
