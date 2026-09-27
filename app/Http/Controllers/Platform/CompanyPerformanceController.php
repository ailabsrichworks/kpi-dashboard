<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\PerformancePeriodService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * CEO / Top Management command centre — spec §26-34. Answers "is the
 * company performing, where are the gaps, what requires action" using only
 * data that already exists: `company_kpi_summary` (overall %),
 * `company_goals` (strategic direction), `company_department_kpi_summary`
 * (department comparison), and the new `company_kpi_achievement` view
 * (per-KPI, for Needs Attention). Nothing here computes an achievement
 * percentage itself — every number comes from a view that already owns that
 * calculation, the same rule `DashboardWidgetService` follows.
 */
class CompanyPerformanceController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    private const AT_RISK_CEILING = 85;
    private const CRITICAL_CEILING = 60;

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyWideViewer($request, $company);
        $this->logAdminAccessIfCrossCompany($request, 'view_performance', $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', [
            'id' => 'eq.' . $company,
            'select' => 'id,name,code,financial_year_start_month',
        ]);

        $periods = new PerformancePeriodService((int) ($companyRow['financial_year_start_month'] ?? 1));
        $now = Carbon::now();
        $fy = (int) $request->query('fy', $periods->financialYearFor($now));
        $periodType = $request->query('period_type', 'quarter') === 'month' ? 'month' : 'quarter';
        $periodNumber = (int) $request->query('period_number', $periodType === 'quarter' ? $periods->quarterFor($now) : $periods->monthOfFinancialYearFor($now));

        $summary = null;
        try {
            $summary = $supabase->first('company_kpi_summary', ['company_id' => 'eq.' . $company, 'select' => '*']);
        } catch (\Throwable) {
        }

        $goals = $supabase->get('company_goals', [
            'company_id' => 'eq.' . $company,
            'select' => '*,users(name)',
            'order' => 'created_at.desc',
        ]);

        $goalStatusCounts = [
            'on_track' => collect($goals)->whereIn('status', ['active', 'completed'])->count(),
            'at_risk' => collect($goals)->where('status', 'at_risk')->count(),
            'critical' => 0,
        ];

        $kpiAchievement = [];
        try {
            $kpiAchievement = $supabase->get('company_kpi_achievement', [
                'company_id' => 'eq.' . $company,
                'select' => '*',
            ]);
        } catch (\Throwable) {
        }

        // Category breakdown (spec §28: Financial / Growth / Initiatives /
        // People) — averaged over each category's goals' own contributing
        // KPIs, computed here in PHP since it's a join across two already-
        // fetched, small result sets rather than a new database view.
        $kpisByGoal = collect($kpiAchievement)->whereNotNull('company_goal_id')->groupBy('company_goal_id');
        $categoryTotals = [];
        foreach ($goals as $goal) {
            if (!$goal['category']) {
                continue;
            }
            $goalKpis = $kpisByGoal->get($goal['id'], collect())->pluck('achievement_pct')->filter(fn ($v) => $v !== null);
            if ($goalKpis->isEmpty()) {
                continue;
            }
            $categoryTotals[$goal['category']][] = $goalKpis->avg();
        }
        $categoryBreakdown = collect($categoryTotals)->map(fn ($vals) => round(collect($vals)->avg(), 1))->all();

        $needsAttention = collect($kpiAchievement)
            ->filter(fn ($k) => $k['achievement_pct'] !== null && $k['achievement_pct'] < self::AT_RISK_CEILING)
            ->sortBy('achievement_pct')
            ->take(8)
            ->values()
            ->all();

        $departments = [];
        try {
            $departments = $supabase->get('company_department_kpi_summary', [
                'company_id' => 'eq.' . $company,
                'select' => 'department_id,department_name,kpi_count,avg_achievement_pct',
                'order' => 'avg_achievement_pct.asc.nullslast',
            ]);
        } catch (\Throwable) {
        }

        $trend = [];
        try {
            $trend = array_reverse($supabase->get('company_period_kpi_summary', [
                'company_id' => 'eq.' . $company,
                'select' => 'financial_year,period_number,avg_achievement_pct',
                'order' => 'financial_year.desc,period_number.desc',
                'limit' => 4,
            ]));
        } catch (\Throwable) {
        }

        // A computed, honest stand-in for a live AI summary rather than
        // pretending this page calls ANIRA on every load (that's what
        // /platform/anira is for) — see the controller docblock.
        $worstDept = collect($departments)->whereNotNull('avg_achievement_pct')->sortBy('avg_achievement_pct')->first();
        $worstKpi = collect($needsAttention)->first();
        $insight = null;
        if ($worstDept && count($trend) >= 2) {
            $insight = sprintf(
                '%s has the lowest average achievement at %s%%.%s',
                $worstDept['department_name'],
                number_format($worstDept['avg_achievement_pct'], 0),
                $worstKpi ? sprintf(' The largest single gap is "%s" at %s%%.', $worstKpi['name'], number_format($worstKpi['achievement_pct'], 0)) : '',
            );
        }

        return Inertia::render('Platform/Performance/Index', [
            'company' => $companyRow,
            'period' => ['financial_year' => $fy, 'period_type' => $periodType, 'period_number' => $periodNumber],
            'summary' => $summary,
            'goals' => $goals,
            'goalStatusCounts' => $goalStatusCounts,
            'categoryBreakdown' => $categoryBreakdown,
            'needsAttention' => $needsAttention,
            'departments' => $departments,
            'trend' => $trend,
            'insight' => $insight,
        ]);
    }

    /**
     * Root-cause drilldown for one goal (spec §32): the goal, its directly
     * contributing KPIs, and each of those KPIs' own children by
     * `parent_kpi_id` — built in PHP from one flat, already-RLS-scoped
     * fetch of every KPI achievement row for the company, since a typical
     * company's KPI count is small enough that this beats N follow-up
     * queries per cascade level.
     */
    public function goal(Request $request, string $company, string $goal)
    {
        $this->ensureCompanyWideViewer($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name,code']);

        $goalRow = $supabase->first('company_goals', [
            'id' => 'eq.' . $goal,
            'company_id' => 'eq.' . $company,
            'select' => '*,users(name)',
        ]);

        if (!$goalRow) {
            abort(404, 'That goal does not belong to this company.');
        }

        $allKpis = [];
        try {
            $allKpis = $supabase->get('company_kpi_achievement', ['company_id' => 'eq.' . $company, 'select' => '*']);
        } catch (\Throwable) {
        }

        $byParent = collect($allKpis)->groupBy('parent_kpi_id');
        $roots = collect($allKpis)->where('company_goal_id', $goal)->whereNull('parent_kpi_id');

        $tree = [];
        $walk = function ($node, int $depth) use (&$walk, &$tree, $byParent) {
            $tree[] = $node + ['depth' => $depth];
            foreach ($byParent->get($node['kpi_id'], collect()) as $child) {
                $walk($child, $depth + 1);
            }
        };
        foreach ($roots as $root) {
            $walk($root, 0);
        }

        return Inertia::render('Platform/Performance/Goal', [
            'company' => $companyRow,
            'goal' => $goalRow,
            'cascade' => $tree,
        ]);
    }
}
