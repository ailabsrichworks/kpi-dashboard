<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Services\ApprovalRequestService;
use App\Services\ApprovalWorkflowService;
use App\Services\KpiCalculationService;
use App\Services\PeriodLifecycleService;
use App\Services\PerformancePeriodService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * KPI submissions, scoped to one department. Viewing is open to
 * Company/Department Admins (company-wide) and to anyone assigned to this
 * specific department; submitting is narrower still — `kpi_submissions_insert`
 * requires the department itself be in the caller's own `auth_department_ids()`,
 * so even a Company Admin can't submit on a department's behalf unless they
 * are personally a member of it too. `ensureDepartmentAccess()` below calls
 * that same RPC to decide what the UI should offer, not to enforce anything —
 * the enforcement is the policy, this just avoids showing a submit form that
 * would fail anyway.
 */
class KpiSubmissionController extends Controller
{
    use LogsAdminActions;

    private function ensureDepartmentAccess(Request $request, string $company, string $department): array
    {
        $platformUser = $request->attributes->get('platformUser');

        if ($platformUser['is_super_admin'] ?? false) {
            return ['can_submit' => false];
        }

        $isCompanyOrDeptAdmin = collect($platformUser['company_memberships'] ?? [])
            ->contains(fn ($m) => $m['company_id'] === $company && in_array($m['role'], ['company_admin', 'slt', 'executive'], true));

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $myDepartmentIds = $supabase->rpc('auth_department_ids');
        $isDepartmentMember = in_array($department, $myDepartmentIds, true);

        abort_unless($isCompanyOrDeptAdmin || $isDepartmentMember, 403, 'You do not have access to this department.');

        return ['can_submit' => $isDepartmentMember];
    }

    public function index(Request $request, string $company, string $department, KpiCalculationService $calc)
    {
        $access = $this->ensureDepartmentAccess($request, $company, $department);

        $this->logAdminAccessIfCrossCompany($request, 'view_submissions', $company, ['department_id' => $department]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $departmentRow = $supabase->first('departments', [
            'id' => 'eq.' . $department,
            'select' => 'id,name,code,company_id',
        ]);

        $companyRow = $supabase->first('companies', [
            'id' => 'eq.' . $company,
            'select' => 'id,financial_year_start_month',
        ]);

        $kpis = $supabase->get('kpis', [
            'company_id' => 'eq.' . $company,
            'status' => 'eq.active',
            'select' => 'id,name,target,unit,frequency',
        ]);

        // Full, unfiltered history — every revision of every submission ever
        // made in this department (spec Part 2: never overwrite, always
        // preserve). `is_current_approved` marks which single row per
        // KPI+period is the one dashboards/reports/ANIRA actually use, so
        // the page can show "Approved: RM820,000 (V2)" alongside a pending
        // V3 without the two being confused for each other.
        $submissions = $supabase->get('kpi_submissions', [
            'department_id' => 'eq.' . $department,
            'select' => '*,kpis(name,unit,target,stretch_target,measurement_direction),users(name)',
            'order' => 'submission_date.desc,revision_number.desc',
        ]);

        $latestApprovedRevisionByPeriod = [];
        foreach ($submissions as $submission) {
            if ($submission['status'] !== 'approved') {
                continue;
            }
            $key = $submission['kpi_id'] . '|' . $submission['financial_year'] . '|' . $submission['period_type'] . '|' . $submission['period_number'];
            $latestApprovedRevisionByPeriod[$key] = max($latestApprovedRevisionByPeriod[$key] ?? 0, (int) $submission['revision_number']);
        }

        // Status engine (spec §16), server-computed via KpiCalculationService
        // so it can't drift from what any other page's calculation says —
        // no expected-progress context here (a single submission's own
        // period, not a year-to-date figure), so this falls back to flat
        // achievement thresholds.
        $submissions = array_map(function ($submission) use ($calc, $latestApprovedRevisionByPeriod) {
            $target = $submission['kpis']['target'] ?? null;
            $stretch = $submission['kpis']['stretch_target'] ?? null;
            $direction = $submission['kpis']['measurement_direction'] ?? 'higher_is_better';

            $achievement = $calc->achievement((float) $submission['value'], $target !== null ? (float) $target : null, $stretch !== null ? (float) $stretch : null, $direction);

            $key = $submission['kpi_id'] . '|' . $submission['financial_year'] . '|' . $submission['period_type'] . '|' . $submission['period_number'];
            $isCurrentApproved = $submission['status'] === 'approved'
                && (int) $submission['revision_number'] === ($latestApprovedRevisionByPeriod[$key] ?? null);

            return $submission + ['computed_status' => $calc->status($achievement), 'is_current_approved' => $isCurrentApproved];
        }, $submissions);

        // Period gating info for the submit form — "can I submit right now"
        // for a submission dated today, computed the same way store() checks it.
        $periods = new PerformancePeriodService((int) ($companyRow['financial_year_start_month'] ?? 1));
        $lifecycle = new PeriodLifecycleService($supabase);
        $periodState = $lifecycle->canSubmitFor($company, $periods, Carbon::now());

        return Inertia::render('Platform/Submissions/Index', [
            'department' => $departmentRow,
            'kpis' => $kpis,
            'submissions' => $submissions,
            'canSubmit' => $access['can_submit'],
            'periodState' => $periodState,
        ]);
    }

    /**
     * Every submission is a NEW, immutable, versioned row — never an edit of
     * a prior one (spec Part 2). Gated by two independent checks before
     * anything is written: the period this date falls in must currently
     * accept submissions (spec Part 3), and there must not already be a
     * decision-pending revision for this exact KPI+period (spec Part 4 —
     * only one proposed value should be "in flight" at a time; a rejected or
     * returned revision clears the way for a new one).
     *
     * The approval request is created BEFORE the submission row (with the
     * submission's id generated client-side via `Str::uuid()`) rather than
     * after, so the submission can be inserted already carrying its
     * `approval_request_id` — avoiding a second UPDATE that the submitter
     * themselves has no RLS right to make (`kpi_submissions_update` is
     * scoped to admins/resolved approvers only, deliberately not the
     * submitter — see the migration that tightens it).
     */
    public function store(Request $request, string $company, string $department)
    {
        $access = $this->ensureDepartmentAccess($request, $company, $department);

        abort_unless($access['can_submit'], 403, 'Only members of this department can submit KPI values.');

        $request->validate([
            'kpi_id' => 'required|uuid',
            'value' => 'required|numeric',
            'submission_date' => 'required|date',
            'notes' => 'nullable|string',
            'evidence_note' => 'nullable|string',
        ]);

        $platformUser = $request->attributes->get('platformUser');

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', [
            'id' => 'eq.' . $company,
            'select' => 'id,financial_year_start_month',
        ]);

        $periods = new PerformancePeriodService((int) ($companyRow['financial_year_start_month'] ?? 1));
        $submissionDate = Carbon::parse($request->submission_date);

        $lifecycle = new PeriodLifecycleService($supabase);
        $periodState = $lifecycle->canSubmitFor($company, $periods, $submissionDate);

        if (!$periodState['allowed']) {
            return back()->withInput()->with('error', sprintf(
                'This period is not open for submissions (quarter status: %s, month status: %s). Ask your Company Admin to reopen it if this is expected.',
                $periodState['quarter_status'],
                $periodState['month_status'],
            ));
        }

        $existingForPeriod = $supabase->get('kpi_submissions', [
            'kpi_id' => 'eq.' . $request->kpi_id,
            'financial_year' => 'eq.' . $periodState['financial_year'],
            'period_type' => 'eq.month',
            'period_number' => 'eq.' . $periodState['month_of_fy'],
            'select' => 'id,revision_number,status',
        ]);

        if (collect($existingForPeriod)->contains(fn ($s) => $s['status'] === 'pending_review')) {
            return back()->withInput()->with('error', 'A submission for this KPI and period is already awaiting approval — wait for it to be decided before submitting a new revision.');
        }

        $nextRevision = (int) (collect($existingForPeriod)->max('revision_number') ?? 0) + 1;
        $submissionId = (string) Str::uuid();

        try {
            $workflows = new ApprovalWorkflowService($supabase);
            $approvals = new ApprovalRequestService($supabase, $workflows);

            $approvalRequest = $approvals->createRequest(
                $company,
                'actual_submission',
                'kpi_submission',
                $submissionId,
                $platformUser['id'],
                $department,
            );

            // return=minimal (3rd arg false): `kpi_submissions_select` routes
            // through `auth_can_view_kpi()` on the parent KPI, which queries
            // back into `kpis` — requesting the row back via the default
            // `return=representation` makes PostgREST evaluate that SELECT
            // policy as part of this INSERT's RETURNING clause, which fails
            // for non-Super-Admin callers even though the INSERT's own WITH
            // CHECK independently evaluates true (same confirmed bug as
            // KpiController::store()). Nothing here uses the returned row.
            $supabase->insert('kpi_submissions', [
                'id' => $submissionId,
                'company_id' => $company,
                'department_id' => $department,
                'kpi_id' => $request->kpi_id,
                'value' => $request->value,
                'submission_date' => $request->submission_date,
                'submitted_by' => $platformUser['id'],
                'notes' => $request->notes,
                'evidence_note' => $request->evidence_note,
                'status' => 'pending_review',
                'revision_number' => $nextRevision,
                'financial_year' => $periodState['financial_year'],
                'period_type' => 'month',
                'period_number' => $periodState['month_of_fy'],
                'approval_request_id' => $approvalRequest['id'],
            ], false);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not save submission: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'submit_kpi_actual', $company, null, [
                'department_id' => $department,
                'revision_number' => $nextRevision,
                'financial_year' => $periodState['financial_year'],
                'month_of_fy' => $periodState['month_of_fy'],
            ], 'kpi_submission', $submissionId, null, ['value' => $request->value]);
        } catch (\Throwable) {
            return back()->with('error', 'Submission was saved, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Submission saved (revision ' . $nextRevision . ') and sent for approval.');
    }
}
