<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\ApprovalRequestService;
use App\Services\ApprovalWorkflowService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Performix Company Platform, Phase 1 hardening (Part 7): target revision
 * governance. `kpis.target` is never written here — it changes only via
 * `apply_approved_target_revision()` once the request this creates is fully
 * approved (see the `kpi_target_revisions` migration's docblock). Until
 * then, every calculation (`KpiCalculationService`, dashboards, ANIRA)
 * keeps reading the KPI's current, unchanged `target`.
 */
class TargetRevisionController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function store(Request $request, string $company, string $kpi)
    {
        $this->ensureCompanyMember($request, $company);

        $request->validate([
            'new_target' => 'required|numeric',
            'reason' => 'required|string|min:5',
            'effective_financial_year' => 'required|integer|min:2000|max:2200',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $platformUser = $request->attributes->get('platformUser');

        $kpiRow = $supabase->first('kpis', [
            'id' => 'eq.' . $kpi,
            'company_id' => 'eq.' . $company,
            'select' => 'id,name,target,owner_user_id',
        ]);

        if (!$kpiRow) {
            abort(404, 'That KPI does not belong to this company.');
        }

        $isOwner = $kpiRow['owner_user_id'] === $platformUser['id'];

        abort_unless(
            $this->canAdministerCompany($request, $company) || $isOwner,
            403,
            'Only the KPI owner or a company admin may request a target revision.'
        );

        if ((float) $request->new_target === (float) ($kpiRow['target'] ?? 0)) {
            return back()->withInput()->with('error', 'The proposed target is the same as the current one.');
        }

        $revisionId = (string) Str::uuid();

        try {
            $workflows = new ApprovalWorkflowService($supabase);
            $approvals = new ApprovalRequestService($supabase, $workflows);

            $approvalRequest = $approvals->createRequest(
                $company,
                'target_revision',
                'kpi_target_revision',
                $revisionId,
                $platformUser['id'],
                null,
            );

            $supabase->insert('kpi_target_revisions', [
                'id' => $revisionId,
                'company_id' => $company,
                'kpi_id' => $kpi,
                'old_target' => $kpiRow['target'],
                'new_target' => $request->new_target,
                'reason' => $request->reason,
                'requested_by' => $platformUser['id'],
                'effective_financial_year' => $request->effective_financial_year,
                'status' => 'pending',
                'approval_request_id' => $approvalRequest['id'],
            ], false);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not submit target revision: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'request_target_revision', $company, null, [], 'kpi', $kpi, [
                'target' => $kpiRow['target'],
            ], [
                'proposed_target' => $request->new_target,
                'reason' => $request->reason,
            ]);
        } catch (\Throwable) {
            return back()->with('error', 'Target revision was submitted, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Target revision requested for "' . $kpiRow['name'] . '" — the current target stays in effect until this is approved.');
    }
}
