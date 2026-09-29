<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\ComputesFinancialYear;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use App\Services\WeightedScoreService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * A Platform-native "SLT Dashboard" — the legacy page of the same name is
 * actually an appraisal-workflow status dashboard (self-assessment -> manager
 * scores -> sign-off, built on a `performance_reports` table the Platform has
 * no equivalent of at all), so this is deliberately NOT a port of that page.
 * Same idea instead — a company-wide view for the company's SLT-tier members
 * — built on data the Platform actually has: KPI achievement across every
 * department and every employee, reusing `WeightedScoreService` exactly as
 * `DashboardController::myScoreByCompany()` already does for a single caller,
 * just for every assigned KPI in the company at once.
 *
 * Access mirrors `ensureCompanyWideViewer()` exactly (Super Admin, an
 * assigned Platform Admin, the company's own Company Admin, or a plain `slt`
 * company-tier member) — the same predicate `auth_can_view_company_wide()`
 * already grants at the RLS layer, so this page never shows more than RLS
 * would already let the caller read one KPI at a time.
 */
class SltDashboardController extends Controller
{
    use ComputesFinancialYear;
    use PlatformAuthorization;

    private const ROLE_PRIORITY = ['company_admin' => 1, 'slt' => 2, 'executive' => 3, 'employee' => 4];

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyWideViewer($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', [
            'id' => 'eq.' . $company,
            'select' => 'id,name,code',
        ]);

        $members = $supabase->get('company_users', [
            'company_id' => 'eq.' . $company,
            'status' => 'eq.active',
            'select' => 'user_id,role,users(name,email)',
        ]);

        // One row per (department_users) membership -- a user with more than
        // one department membership just shows their first one here; this
        // page is a company-wide roster, not a department-membership editor.
        $departmentMemberships = $supabase->get('department_users', [
            'company_id' => 'eq.' . $company,
            'select' => 'user_id,departments(name)',
        ]);
        $departmentByUser = collect($departmentMemberships)->keyBy('user_id');

        $kpis = $supabase->get('kpis', [
            'company_id' => 'eq.' . $company,
            'assigned_user_id' => 'not.is.null',
            'select' => 'id,name,target,weight,assigned_user_id',
        ]);

        $kpiIds = array_column($kpis, 'id');

        // Same three fetches DashboardController::myScoreByCompany() already
        // does for the caller's own KPIs -- here for every assigned KPI in
        // the company at once.
        $submissions = empty($kpiIds) ? [] : $supabase->get('kpi_submissions', [
            'kpi_id' => 'in.(' . implode(',', $kpiIds) . ')',
            'select' => 'kpi_id,value,submission_date',
            'order' => 'submission_date.desc',
        ]);
        $latestByKpiId = [];
        foreach ($submissions as $submission) {
            $latestByKpiId[$submission['kpi_id']] ??= $submission;
        }

        $quarters = empty($kpiIds) ? [] : $supabase->get('kpi_quarters', [
            'kpi_id' => 'in.(' . implode(',', $kpiIds) . ')',
            'financial_year' => 'eq.' . $this->currentFinancialYear(),
            'select' => 'kpi_id,quarter,target,actual,status',
        ]);
        $quartersByKpiId = collect($quarters)->groupBy('kpi_id')->map(fn ($group) => $group->all())->all();

        $scoreService = app(WeightedScoreService::class);
        $scoreByUser = collect($kpis)->groupBy('assigned_user_id')->map(
            fn ($userKpis) => $scoreService->summarize($userKpis->all(), $latestByKpiId, $quartersByKpiId)
        );

        $staffRows = collect($members)
            ->map(function ($member) use ($departmentByUser, $scoreByUser) {
                $summary = $scoreByUser->get($member['user_id']);
                $department = $departmentByUser->get($member['user_id']);

                return [
                    'user_id' => $member['user_id'],
                    'name' => $member['users']['name'] ?? 'Unknown',
                    'email' => $member['users']['email'] ?? '',
                    'department' => $department['departments']['name'] ?? null,
                    'role' => $member['role'],
                    'kpi_count' => $summary['kpi_count'] ?? 0,
                    'score' => $summary['overall_score'] ?? null,
                ];
            })
            ->sort(function ($a, $b) {
                $priority = (self::ROLE_PRIORITY[$a['role']] ?? 99) <=> (self::ROLE_PRIORITY[$b['role']] ?? 99);

                return $priority !== 0 ? $priority : strcasecmp($a['name'], $b['name']);
            })
            ->values();

        $scoredRows = $staffRows->filter(fn ($row) => $row['score'] !== null);

        $bandCounts = [];
        foreach ($scoredRows as $row) {
            $band = $this->bandLabel($row['score']);
            $bandCounts[$band] = ($bandCounts[$band] ?? 0) + 1;
        }

        return Inertia::render('Platform/SltDashboard', [
            'company' => $companyRow,
            'totalStaff' => count($members),
            'departmentCount' => collect($departmentMemberships)->pluck('departments.name')->filter()->unique()->count(),
            'avgScore' => $scoredRows->isNotEmpty() ? round($scoredRows->avg('score'), 2) : null,
            'bandCounts' => $bandCounts,
            'staffRows' => $staffRows,
        ]);
    }

    /**
     * Mirrors `scoreStyle()` (resources/js/lib/scoreStyle.ts) band-for-band —
     * the Platform's one existing KPI-achievement band scheme, reused here
     * rather than inventing a second one just for this page's distribution
     * count.
     */
    private function bandLabel(float $score): string
    {
        return match (true) {
            $score <= 25 => 'Critical',
            $score <= 50 => 'Risk',
            $score <= 75 => 'Watch',
            $score <= 100 => 'Good',
            default => 'Exceeded',
        };
    }
}
