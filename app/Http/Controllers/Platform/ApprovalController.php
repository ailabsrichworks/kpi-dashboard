<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\PlatformNotificationService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Ports legacy's Unified Approval Center (`ApprovalController`,
 * resources/views/kpi/approval.blade.php) — a single inbox listing every
 * pending approval a Company Admin needs to act on, instead of the
 * Platform's previous state of four separate review queues scattered across
 * Weightage/Quarterly's own pages plus two brand-new request types
 * (target-change, delete) that had no home at all.
 *
 * Deliberately NOT a rewrite of Weightage/Quarterly's own approve/reject
 * logic — this controller only owns decisions for the two NEW request types
 * (target-change, delete-request) this migration pass adds. Weightage and
 * Quarterly change-requests/completions keep their own existing routes
 * (`platform.weightage.approve/reject`, `platform.quarterly.approve-change`/
 * `reject-change`/`approve-completion`/`reject-completion`) as the single
 * source of truth for applying those decisions — this page's cards for those
 * two types simply submit to those same existing endpoints, so there is
 * exactly one place each kind of approval is actually executed, matching
 * this codebase's established "one write path per concept" rule.
 *
 * Legacy also gates KPI edit behind an approval-request table
 * (`kpi_target_change_requests`, its "TARGET CHANGE" branch) even for the
 * base/stretch target fields a Manager might own — the Platform only has one
 * `target` field (Blueprint's own decision, `kpis.target` is single-value,
 * not base/stretch), so there is exactly one thing to request a change to,
 * not two.
 *
 * Unlike legacy (role-hierarchy approver — whoever is a requester's
 * Manager/VP/SLT), every decision here is Company-Admin-only
 * (`ensureCompanyAdmin`), the same approver concept Weightage/Quarterly
 * already established as the Platform's one consistent "who decides"
 * answer, since the Platform has no equivalent of legacy's multi-tier
 * approval chain to route through instead.
 */
class ApprovalController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name,code']);
        abort_if(!$companyRow, 404);

        // Same PGRST201 ambiguous-embed trap documented throughout this
        // codebase: every one of these tables has 2 foreign keys into
        // `users` (requested_by, decided_by), so the qualified
        // `users!<constraint>(...)` form is required, not `users(...)`.
        $targetChanges = $supabase->get('kpi_target_change_requests', [
            'company_id' => 'eq.' . $company,
            'status' => 'eq.pending',
            'select' => 'id,kpi_id,old_target,new_target,reason,created_at,kpis(name),users!kpi_target_change_requests_requested_by_foreign(name,email)',
            'order' => 'created_at.asc',
        ]);

        $deleteRequests = $supabase->get('kpi_delete_requests', [
            'company_id' => 'eq.' . $company,
            'status' => 'eq.pending',
            'select' => 'id,kpi_id,reason,created_at,kpis(name),users!kpi_delete_requests_requested_by_foreign(name,email)',
            'order' => 'created_at.asc',
        ]);

        $weightChanges = $supabase->get('kpi_weight_change_requests', [
            'company_id' => 'eq.' . $company,
            'status' => 'eq.pending',
            'select' => 'id,kpi_id,old_weight,new_weight,reason,created_at,kpis(name),users!kpi_weight_change_requests_requested_by_foreign(name,email)',
            'order' => 'created_at.asc',
        ]);

        $quarterActualChanges = $supabase->get('kpi_quarter_update_requests', [
            'company_id' => 'eq.' . $company,
            'status' => 'eq.pending',
            'select' => 'id,kpi_id,old_actual,requested_actual,reason,created_at,kpis(name),kpi_quarters(quarter),users!kpi_quarter_update_requests_requested_by_foreign(name,email)',
            'order' => 'created_at.asc',
        ]);

        $pendingCompletions = $supabase->get('kpi_quarters', [
            'company_id' => 'eq.' . $company,
            'status' => 'eq.pending_completion',
            'select' => 'id,kpi_id,quarter,financial_year,target,actual,completion_note,completion_submitted_at,kpis(name),users!kpi_quarters_completion_submitted_by_foreign(name,email)',
            'order' => 'completion_submitted_at.asc',
        ]);

        $pending = [
            ...collect($targetChanges)->map(fn ($r) => $this->tag($r, 'target_change'))->all(),
            ...collect($deleteRequests)->map(fn ($r) => $this->tag($r, 'delete_request'))->all(),
            ...collect($weightChanges)->map(fn ($r) => $this->tag($r, 'weightage_change'))->all(),
            ...collect($quarterActualChanges)->map(fn ($r) => $this->tag($r, 'quarter_update'))->all(),
            ...collect($pendingCompletions)->map(fn ($r) => $this->tag($r, 'completion'))->all(),
        ];

        return Inertia::render('Platform/Approvals/Index', [
            'company' => $companyRow,
            'pending' => $pending,
            'counts' => [
                'target_change' => count($targetChanges),
                'delete_request' => count($deleteRequests),
                'weightage_change' => count($weightChanges),
                'quarter_update' => count($quarterActualChanges),
                'completion' => count($pendingCompletions),
            ],
        ]);
    }

    private function tag(array $row, string $type): array
    {
        $row['type'] = $type;

        return $row;
    }

    public function approveTarget(Request $request, string $company, string $targetChangeRequest)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $platformUser = $request->attributes->get('platformUser');

        $pending = $supabase->first('kpi_target_change_requests', [
            'id' => 'eq.' . $targetChangeRequest,
            'company_id' => 'eq.' . $company,
            'status' => 'eq.pending',
            'select' => 'id,kpi_id,new_target,requested_by,kpis(name)',
        ]);

        if (!$pending) {
            return back()->with('error', 'That request is no longer pending.');
        }

        try {
            $supabase->update('kpis', ['id' => 'eq.' . $pending['kpi_id']], ['target' => $pending['new_target']], false);

            $supabase->update('kpi_target_change_requests', ['id' => 'eq.' . $targetChangeRequest], [
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
            'Target change approved',
            'Your request to change "' . ($pending['kpis']['name'] ?? 'a KPI') . '" to ' . $pending['new_target'] . ' was approved.',
        );

        try {
            $this->logCompanyAction($request, 'approve_kpi_target_change', $company, null, [
                'new_target' => $pending['new_target'],
            ], 'kpi_target_change_request', $targetChangeRequest);
        } catch (\Throwable) {
            return back()->with('error', 'Request was approved, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Target change approved and applied.');
    }

    public function rejectTarget(Request $request, string $company, string $targetChangeRequest)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate(['decision_note' => 'nullable|string|max:1000']);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $platformUser = $request->attributes->get('platformUser');

        $pending = $supabase->first('kpi_target_change_requests', [
            'id' => 'eq.' . $targetChangeRequest,
            'company_id' => 'eq.' . $company,
            'status' => 'eq.pending',
            'select' => 'id,requested_by,kpis(name)',
        ]);

        if (!$pending) {
            return back()->with('error', 'That request is no longer pending.');
        }

        try {
            $supabase->update('kpi_target_change_requests', ['id' => 'eq.' . $targetChangeRequest], [
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
            'Target change rejected',
            'Your request to change "' . ($pending['kpis']['name'] ?? 'a KPI') . '" was rejected.' . ($request->decision_note ? ' Note: ' . $request->decision_note : ''),
        );

        try {
            $this->logCompanyAction($request, 'reject_kpi_target_change', $company, null, [], 'kpi_target_change_request', $targetChangeRequest);
        } catch (\Throwable) {
            return back()->with('error', 'Request was rejected, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Target change rejected.');
    }

    /**
     * Deletes the KPI itself (cascading to its submissions/quarters/access
     * grants/requests via the existing `onDelete('cascade')` foreign keys —
     * including this very request row) — matches legacy's `approveDelete()`,
     * which hard-deletes the KPI and every one of its request/history rows
     * as one cleanup operation rather than preserving an "approved" record
     * of the now-gone KPI. The admin_action_logs entry is the durable trail
     * of what was deleted and why, not the request table.
     */
    public function approveDelete(Request $request, string $company, string $deleteRequest)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $pending = $supabase->first('kpi_delete_requests', [
            'id' => 'eq.' . $deleteRequest,
            'company_id' => 'eq.' . $company,
            'status' => 'eq.pending',
            'select' => 'id,kpi_id,requested_by,reason,kpis(name)',
        ]);

        if (!$pending) {
            return back()->with('error', 'That request is no longer pending.');
        }

        $kpiName = $pending['kpis']['name'] ?? 'a KPI';

        try {
            $supabase->delete('kpis', ['id' => 'eq.' . $pending['kpi_id']]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not delete KPI: ' . $e->getMessage());
        }

        app(PlatformNotificationService::class)->notify(
            $supabase,
            $company,
            $pending['requested_by'],
            'Deletion approved',
            'Your request to delete "' . $kpiName . '" was approved — the KPI has been removed.',
        );

        try {
            $this->logCompanyAction($request, 'approve_kpi_delete', $company, null, [
                'kpi_name' => $kpiName, 'reason' => $pending['reason'],
            ], 'kpi', $pending['kpi_id']);
        } catch (\Throwable) {
            return back()->with('error', 'KPI was deleted, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Deletion approved — "' . $kpiName . '" has been removed.');
    }

    public function rejectDelete(Request $request, string $company, string $deleteRequest)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate(['decision_note' => 'nullable|string|max:1000']);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $platformUser = $request->attributes->get('platformUser');

        $pending = $supabase->first('kpi_delete_requests', [
            'id' => 'eq.' . $deleteRequest,
            'company_id' => 'eq.' . $company,
            'status' => 'eq.pending',
            'select' => 'id,requested_by,kpis(name)',
        ]);

        if (!$pending) {
            return back()->with('error', 'That request is no longer pending.');
        }

        try {
            $supabase->update('kpi_delete_requests', ['id' => 'eq.' . $deleteRequest], [
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
            'Deletion rejected',
            'Your request to delete "' . ($pending['kpis']['name'] ?? 'a KPI') . '" was rejected.' . ($request->decision_note ? ' Note: ' . $request->decision_note : ''),
        );

        try {
            $this->logCompanyAction($request, 'reject_kpi_delete', $company, null, [], 'kpi_delete_request', $deleteRequest);
        } catch (\Throwable) {
            return back()->with('error', 'Request was rejected, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Deletion request rejected.');
    }
}
