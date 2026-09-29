<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\ComputesFinancialYear;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\PlatformNotificationService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Per-quarter KPI target/actual tracking + sign-off — see the
 * 2026_09_26_000000_add_kpi_quarterly_tracking migration's docblock for the
 * full design rationale (why this reuses Weightage's Company-Admin-approves
 * shape instead of legacy's manager-hierarchy one, and why completion
 * sign-off is a state machine on the row instead of a second approval
 * table). Structurally this controller mirrors `WeightageController` closely
 * on purpose — same self-service-page-with-an-admin-review-queue shape, same
 * "one direct-write path, everything else needs a request/decision" rule.
 *
 * Three write paths:
 *   - updateActual(): direct save of `actual`, only while the quarter isn't
 *     `pending_completion`/`completed` — enforced for real by
 *     restrict_kpi_quarter_owner_update(), not just this validation.
 *   - submitCompletion() -> approveCompletion()/rejectCompletion(): the
 *     owner submits a quarter for sign-off; a Company Admin decides. This is
 *     a status transition on the SAME `kpi_quarters` row, not a request-table
 *     row — see the migration docblock for why.
 *   - requestActualChange() -> approveActualChange()/rejectActualChange():
 *     the one case that still needs a request row — changing `actual` after
 *     a quarter is already `completed`.
 */
class QuarterlyController extends Controller
{
    use ComputesFinancialYear;
    use LogsAdminActions;
    use PlatformAuthorization;

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $platformUser = $request->attributes->get('platformUser');
        $userId = $platformUser['id'];
        $isAdmin = $this->canAdministerCompany($request, $company);

        $companyRow = $supabase->first('companies', [
            'id' => 'eq.' . $company,
            'select' => 'id,name,code',
        ]);

        $myKpis = $supabase->get('kpis', [
            'company_id' => 'eq.' . $company,
            'assigned_user_id' => 'eq.' . $userId,
            'frequency' => 'eq.quarterly',
            'select' => 'id,name,description,kpi_categories(name)',
            'order' => 'created_at.asc',
        ]);

        $myKpiIds = array_column($myKpis, 'id');
        $financialYear = $this->currentFinancialYear();

        $myQuarters = empty($myKpiIds)
            ? []
            : $supabase->get('kpi_quarters', [
                'kpi_id' => 'in.(' . implode(',', $myKpiIds) . ')',
                'financial_year' => 'eq.' . $financialYear,
                'select' => 'id,kpi_id,quarter,target,actual,status,completion_note,completion_submitted_at,start_date,end_date',
                'order' => 'quarter.asc',
            ]);

        $myQuarterIds = array_column($myQuarters, 'id');

        // Same PGRST201 ambiguous-embed trap Weightage already documents:
        // two foreign keys into `users` (requested_by, decided_by).
        $myPendingChangeRequests = empty($myQuarterIds)
            ? []
            : $supabase->get('kpi_quarter_update_requests', [
                'quarter_id' => 'in.(' . implode(',', $myQuarterIds) . ')',
                'status' => 'eq.pending',
                'select' => 'id,quarter_id,old_actual,requested_actual,reason,created_at',
            ]);

        $pendingChangeByQuarter = collect($myPendingChangeRequests)->keyBy('quarter_id');

        $quartersByKpi = collect($myQuarters)->map(function ($quarter) use ($pendingChangeByQuarter) {
            $quarter['pending_change_request'] = $pendingChangeByQuarter->get($quarter['id']);

            return $quarter;
        })->groupBy('kpi_id');

        $kpis = collect($myKpis)->map(function ($kpi) use ($quartersByKpi) {
            $kpi['quarters'] = $quartersByKpi->get($kpi['id'], collect())->values();

            return $kpi;
        })->values();

        $completionQueue = [];
        $changeRequestQueue = [];

        if ($isAdmin) {
            $completionQueue = $supabase->get('kpi_quarters', [
                'company_id' => 'eq.' . $company,
                'status' => 'eq.pending_completion',
                'select' => 'id,quarter,target,actual,completion_note,completion_submitted_at,kpis(name),users!kpi_quarters_completion_submitted_by_foreign(name,email)',
                'order' => 'completion_submitted_at.asc',
            ]);

            $changeRequestQueue = $supabase->get('kpi_quarter_update_requests', [
                'company_id' => 'eq.' . $company,
                'status' => 'eq.pending',
                'select' => 'id,old_actual,requested_actual,reason,created_at,kpis(name),kpi_quarters(quarter),users!kpi_quarter_update_requests_requested_by_foreign(name,email)',
                'order' => 'created_at.asc',
            ]);
        }

        return Inertia::render('Platform/Quarterly/Index', [
            'company' => $companyRow,
            'financialYear' => $financialYear,
            'kpis' => $kpis,
            'isAdmin' => $isAdmin,
            'completionQueue' => $completionQueue,
            'changeRequestQueue' => $changeRequestQueue,
        ]);
    }

    /**
     * Direct save of `actual` for one of the caller's own quarters, only
     * while it isn't `pending_completion`/`completed` —
     * restrict_kpi_quarter_owner_update() is what actually refuses anything
     * else regardless of this validation.
     */
    public function updateActual(Request $request, string $company, string $quarter)
    {
        $this->ensureCompanyMember($request, $company);

        $request->validate(['actual' => 'required|numeric|min:0']);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            $supabase->update('kpi_quarters', ['id' => 'eq.' . $quarter], ['actual' => $request->actual], false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not save this quarter: ' . $e->getMessage());
        }

        return back()->with('success', 'Quarter actual saved.');
    }

    public function submitCompletion(Request $request, string $company, string $quarter)
    {
        $this->ensureCompanyMember($request, $company);

        $request->validate(['completion_note' => 'nullable|string|max:2000']);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $platformUser = $request->attributes->get('platformUser');

        try {
            $supabase->update('kpi_quarters', ['id' => 'eq.' . $quarter], [
                'status' => 'pending_completion',
                'completion_note' => $request->completion_note,
                'completion_submitted_at' => now()->toIso8601String(),
                'completion_submitted_by' => $platformUser['id'],
            ], false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not submit for sign-off: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'submit_kpi_quarter_completion', $company, null, [], 'kpi_quarter', $quarter);
        } catch (\Throwable) {
            // Best-effort: the submission already succeeded and is visible
            // to the admin review queue immediately, unlike a Company Admin
            // decision where a silent logging gap would hide real history.
        }

        return back()->with('success', 'Submitted for sign-off.');
    }

    public function approveCompletion(Request $request, string $company, string $quarter)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $pending = $supabase->first('kpi_quarters', [
            'id' => 'eq.' . $quarter,
            'company_id' => 'eq.' . $company,
            'status' => 'eq.pending_completion',
            'select' => 'id,quarter,completion_submitted_by,kpis(name)',
        ]);

        if (!$pending) {
            return back()->with('error', 'That quarter is no longer awaiting sign-off.');
        }

        try {
            $supabase->update('kpi_quarters', ['id' => 'eq.' . $quarter], ['status' => 'completed'], false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not approve sign-off: ' . $e->getMessage());
        }

        app(PlatformNotificationService::class)->notify(
            $supabase,
            $company,
            $pending['completion_submitted_by'],
            'Quarter sign-off approved',
            $pending['quarter'] . ' for "' . ($pending['kpis']['name'] ?? 'a KPI') . '" was signed off.',
        );

        try {
            $this->logCompanyAction($request, 'approve_kpi_quarter_completion', $company, null, [], 'kpi_quarter', $quarter);
        } catch (\Throwable) {
            return back()->with('error', 'Sign-off was approved, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Quarter signed off.');
    }

    public function rejectCompletion(Request $request, string $company, string $quarter)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate(['decision_note' => 'nullable|string|max:1000']);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $pending = $supabase->first('kpi_quarters', [
            'id' => 'eq.' . $quarter,
            'company_id' => 'eq.' . $company,
            'status' => 'eq.pending_completion',
            'select' => 'id,quarter,completion_submitted_by,kpis(name)',
        ]);

        if (!$pending) {
            return back()->with('error', 'That quarter is no longer awaiting sign-off.');
        }

        try {
            // Back to on_track, not the exact prior sub-status (not_started/
            // at_risk) — that distinction was never preserved once submitted,
            // and "still in progress" is the accurate meaning either way.
            $supabase->update('kpi_quarters', ['id' => 'eq.' . $quarter], [
                'status' => 'on_track',
                'completion_note' => $request->decision_note,
            ], false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not reject sign-off: ' . $e->getMessage());
        }

        app(PlatformNotificationService::class)->notify(
            $supabase,
            $company,
            $pending['completion_submitted_by'],
            'Quarter sign-off rejected',
            $pending['quarter'] . ' for "' . ($pending['kpis']['name'] ?? 'a KPI') . '" was sent back.' . ($request->decision_note ? ' Note: ' . $request->decision_note : ''),
        );

        try {
            $this->logCompanyAction($request, 'reject_kpi_quarter_completion', $company, null, [], 'kpi_quarter', $quarter);
        } catch (\Throwable) {
            return back()->with('error', 'Sign-off was rejected, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Sent back for more work.');
    }

    /**
     * Requests a change to an already-`completed` quarter's actual. Soft-skips
     * when a pending request already exists for it, mirroring Weightage's
     * `requestChange()` tolerance.
     */
    public function requestActualChange(Request $request, string $company, string $quarter)
    {
        $this->ensureCompanyMember($request, $company);

        $request->validate([
            'requested_actual' => 'required|numeric|min:0',
            'reason' => 'required|string|min:20',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $platformUser = $request->attributes->get('platformUser');

        $quarterRow = $supabase->first('kpi_quarters', [
            'id' => 'eq.' . $quarter,
            'company_id' => 'eq.' . $company,
            'select' => 'id,kpi_id,actual,status',
        ]);

        if (!$quarterRow || $quarterRow['status'] !== 'completed') {
            return back()->with('error', 'Only a completed quarter needs a change request — use the direct save instead.');
        }

        $existing = $supabase->first('kpi_quarter_update_requests', [
            'quarter_id' => 'eq.' . $quarter,
            'status' => 'eq.pending',
            'select' => 'id',
        ]);

        if ($existing) {
            return back()->with('error', 'This quarter already has a pending change request.');
        }

        try {
            $created = $supabase->insert('kpi_quarter_update_requests', [
                'company_id' => $company,
                'kpi_id' => $quarterRow['kpi_id'],
                'quarter_id' => $quarter,
                'requested_by' => $platformUser['id'],
                'old_actual' => $quarterRow['actual'],
                'requested_actual' => $request->requested_actual,
                'reason' => $request->reason,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not submit request: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'request_kpi_quarter_actual_change', $company, null, [
                'requested_actual' => $request->requested_actual,
            ], 'kpi_quarter_update_request', $created[0]['id'] ?? null);
        } catch (\Throwable) {
            return back()->with('error', 'Request was submitted, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Change request submitted for approval.');
    }

    public function approveActualChange(Request $request, string $company, string $changeRequest)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $platformUser = $request->attributes->get('platformUser');

        $pending = $supabase->first('kpi_quarter_update_requests', [
            'id' => 'eq.' . $changeRequest,
            'company_id' => 'eq.' . $company,
            'status' => 'eq.pending',
            'select' => 'id,quarter_id,requested_actual,requested_by,kpis(name),kpi_quarters(quarter)',
        ]);

        if (!$pending) {
            return back()->with('error', 'That request is no longer pending.');
        }

        try {
            $supabase->update('kpi_quarters', ['id' => 'eq.' . $pending['quarter_id']], ['actual' => $pending['requested_actual']], false);

            $supabase->update('kpi_quarter_update_requests', ['id' => 'eq.' . $changeRequest], [
                'status' => 'approved',
                'decided_by' => $platformUser['id'],
                'decided_at' => now()->toIso8601String(),
            ], false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not approve request: ' . $e->getMessage());
        }

        app(PlatformNotificationService::class)->notify(
            $supabase,
            $company,
            $pending['requested_by'],
            'Quarter change approved',
            'Your requested change to ' . ($pending['kpi_quarters']['quarter'] ?? 'a quarter') . ' of "' . ($pending['kpis']['name'] ?? 'a KPI') . '" was approved.',
        );

        try {
            $this->logCompanyAction($request, 'approve_kpi_quarter_actual_change', $company, null, [
                'requested_actual' => $pending['requested_actual'],
            ], 'kpi_quarter_update_request', $changeRequest);
        } catch (\Throwable) {
            return back()->with('error', 'Request was approved, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Change approved and applied.');
    }

    public function rejectActualChange(Request $request, string $company, string $changeRequest)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate(['decision_note' => 'nullable|string|max:1000']);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $platformUser = $request->attributes->get('platformUser');

        $pending = $supabase->first('kpi_quarter_update_requests', [
            'id' => 'eq.' . $changeRequest,
            'company_id' => 'eq.' . $company,
            'status' => 'eq.pending',
            'select' => 'id,requested_by,kpis(name),kpi_quarters(quarter)',
        ]);

        if (!$pending) {
            return back()->with('error', 'That request is no longer pending.');
        }

        try {
            $supabase->update('kpi_quarter_update_requests', ['id' => 'eq.' . $changeRequest], [
                'status' => 'rejected',
                'decided_by' => $platformUser['id'],
                'decided_at' => now()->toIso8601String(),
                'decision_note' => $request->decision_note,
            ], false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not reject request: ' . $e->getMessage());
        }

        app(PlatformNotificationService::class)->notify(
            $supabase,
            $company,
            $pending['requested_by'],
            'Quarter change rejected',
            'Your requested change to ' . ($pending['kpi_quarters']['quarter'] ?? 'a quarter') . ' of "' . ($pending['kpis']['name'] ?? 'a KPI') . '" was rejected.' . ($request->decision_note ? ' Note: ' . $request->decision_note : ''),
        );

        try {
            $this->logCompanyAction($request, 'reject_kpi_quarter_actual_change', $company, null, [], 'kpi_quarter_update_request', $changeRequest);
        } catch (\Throwable) {
            return back()->with('error', 'Request was rejected, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Change request rejected.');
    }
}
