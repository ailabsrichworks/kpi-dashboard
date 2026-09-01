<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Computes the data behind each widget on a company's customizable
 * dashboard (see 2026_09_01_010000_create_company_dashboard_widgets.php).
 * This class only ever reads through existing, already-proven paths —
 * `company_kpi_summary` (the same view DashboardController::companyLanding()
 * already uses), the same pending-approvals shape ApprovalController's own
 * inbox query uses, and PeriodLifecycleService/PerformancePeriodService for
 * period status. It never computes an achievement percentage or approval
 * rule itself — this project's own hard rule is one calculation source of
 * truth, and widgets are a presentation layer on top of it, not a second one.
 */
class DashboardWidgetService
{
    public const AVAILABLE_WIDGETS = ['company_overview', 'pending_approvals', 'recent_submissions', 'period_status'];

    public const DEFAULT_LAYOUT = ['company_overview', 'period_status', 'pending_approvals', 'recent_submissions'];

    public function __construct(private readonly SupabaseUserService $supabase)
    {
    }

    /**
     * @param string[] $widgetTypes
     */
    public function computeData(string $companyId, string $myUserId, array $widgetTypes): array
    {
        $data = [];

        foreach (array_unique($widgetTypes) as $type) {
            $data[$type] = match ($type) {
                'company_overview' => $this->companyOverview($companyId),
                'pending_approvals' => $this->pendingApprovals($companyId, $myUserId),
                'recent_submissions' => $this->recentSubmissions($companyId),
                'period_status' => $this->periodStatus($companyId),
                default => null,
            };
        }

        return $data;
    }

    private function companyOverview(string $companyId): ?array
    {
        try {
            return $this->supabase->first('company_kpi_summary', [
                'company_id' => 'eq.' . $companyId,
                'select' => '*',
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Same shape as ApprovalController::index()'s own pending-steps query —
     * approval_request_steps_select's RLS already scopes
     * resolved_approver_user_id = auth_current_user_id() as the real
     * boundary, this just counts/previews rather than building the full
     * tabbed inbox.
     */
    private function pendingApprovals(string $companyId, string $myUserId): array
    {
        try {
            $steps = $this->supabase->get('approval_request_steps', [
                'resolved_approver_user_id' => 'eq.' . $myUserId,
                'status' => 'eq.pending',
                'company_id' => 'eq.' . $companyId,
                'select' => 'id,step_order,created_at,approval_requests(workflow_type,object_type,submitted_at)',
                'order' => 'created_at.asc',
                'limit' => 5,
            ]);
        } catch (\Throwable) {
            return ['count' => 0, 'items' => []];
        }

        return ['count' => count($steps), 'items' => $steps];
    }

    private function recentSubmissions(string $companyId): array
    {
        try {
            return $this->supabase->get('kpi_submissions', [
                'company_id' => 'eq.' . $companyId,
                'status' => 'eq.approved',
                'select' => 'id,value,submission_date,kpis(name,unit,target)',
                'order' => 'submission_date.desc',
                'limit' => 5,
            ]);
        } catch (\Throwable) {
            return [];
        }
    }

    private function periodStatus(string $companyId): ?array
    {
        try {
            $company = $this->supabase->first('companies', [
                'id' => 'eq.' . $companyId,
                'select' => 'financial_year_start_month',
            ]);

            $periods = new PerformancePeriodService((int) ($company['financial_year_start_month'] ?? 1));
            $now = Carbon::now();
            $fy = $periods->financialYearFor($now);
            $quarter = $periods->quarterFor($now);
            [$start, $end] = $periods->quarterBounds($fy, $quarter);

            $lifecycle = new PeriodLifecycleService($this->supabase);
            $state = $lifecycle->effectiveStatus($companyId, $fy, 'quarter', $quarter, $start, $end, $now);

            return [
                'financial_year' => $fy,
                'quarter' => $quarter,
                'status' => $state['status'],
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
