<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\ComputesFinancialYear;
use App\Services\SupabaseUserService;
use App\Services\WeightedScoreService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    use ComputesFinancialYear;

    /**
     * Two genuinely different pages behind one URL, split by platform tier —
     * requirement #9: "Since Richworks is effectively the platform operator,
     * Performix should have a separate platform view. A company user should
     * never even know another company exists." A Richworks Super Admin gets
     * `platformOverview()` — the Center-wide operator dashboard (total/active/
     * suspended companies, total users, onboarding progress, system health,
     * recent admin activity, security alerts). Everyone else — a Company
     * Admin, SLT, Executive, Employee, or a Platform Admin scoped to their
     * assigned companies — gets `companyLanding()`, which only ever shows
     * what RLS already scoped `companies` to for that specific caller. These
     * were previously one shared component with an `is_super_admin`-gated
     * block bolted on; a company user landing here saw operator-flavored
     * copy ("Companies visible to you") even though the isolation itself was
     * never actually broken (RLS, not this controller, already narrowed the
     * result to their own company). Splitting them into two components makes
     * that separation structural, not just a conditional render.
     */
    public function index(Request $request)
    {
        $platformUser = $request->attributes->get('platformUser');

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        if ($platformUser['is_super_admin'] ?? false) {
            return $this->platformOverview($request, $platformUser, $supabase);
        }

        return $this->companyLanding($platformUser, $supabase);
    }

    /**
     * Deliberately queries `companies` with no filter at all — RLS is what
     * decides whether one row comes back or all of them. A Company Admin
     * (or a Platform Admin, scoped to their assigned companies) seeing only
     * their own here — not because this controller filtered it, but because
     * Postgres did — is the actual proof that isolation works.
     */
    private function companyLanding(array $platformUser, SupabaseUserService $supabase)
    {
        $companies = $supabase->get('companies', ['select' => '*']);

        // `company_kpi_summary` is a Postgres view with `security_invoker`,
        // so this returns exactly the same set of companies RLS already
        // scoped `companies` to above — the aggregation happens in Postgres,
        // never by summing kpi_submissions rows here.
        try {
            $summaries = $supabase->get('company_kpi_summary', ['select' => '*']);
        } catch (\Throwable) {
            $summaries = [];
        }
        $summariesByCompany = collect($summaries)->keyBy('company_id');

        // Same defensive shape as `company_kpi_summary` above: these two are
        // "nice to have" dashboard widgets, not core to the page — a hiccup
        // fetching either must never turn the whole company list into a 500.
        try {
            $myScoreByCompany = $this->myScoreByCompany($supabase, $platformUser['id']);
        } catch (\Throwable) {
            $myScoreByCompany = [];
        }

        $companiesWithStats = collect($companies)->map(function ($company) use ($summariesByCompany, $myScoreByCompany, $supabase) {
            $summary = $summariesByCompany->get($company['id']);

            try {
                $departmentOverview = $this->departmentOverview($supabase, $company['id']);
            } catch (\Throwable) {
                $departmentOverview = [];
            }

            return $company + [
                'department_count' => $summary['department_count'] ?? 0,
                'user_count' => $summary['user_count'] ?? 0,
                'kpi_count' => $summary['kpi_count'] ?? 0,
                'submission_count' => $summary['submission_count'] ?? 0,
                'avg_achievement_pct' => $summary['avg_achievement_pct'] ?? null,
                'my_performance' => $myScoreByCompany[$company['id']] ?? null,
                'department_overview' => $departmentOverview,
            ];
        })->values();

        return Inertia::render('Platform/Dashboard', [
            'me' => $platformUser,
            'visibleCompanies' => $companiesWithStats,
            'greeting' => $this->timeOfDayGreeting(),
        ]);
    }

    /**
     * Same "Good Morning/Afternoon/Evening" split dashboard.blade.php uses,
     * on the same timezone (Richworks/Performix's own working hours) rather
     * than the visitor's browser clock.
     */
    private function timeOfDayGreeting(): string
    {
        $hour = now()->timezone('Asia/Kuala_Lumpur')->hour;

        return $hour < 12 ? 'Good Morning' : ($hour < 18 ? 'Good Afternoon' : 'Good Evening');
    }

    /**
     * "My Performance" (the legacy DashboardController::index()'s
     * $individualPerformance/$myOnTrack/$myAtRisk/"Needs Attention" widgets,
     * see WeightedScoreService's own docblock for the formula) — keyed by
     * company_id since a caller can belong to more than one company and
     * Platform/Dashboard.tsx renders one card per company. Only companies
     * with at least one KPI actually assigned to this caller get an entry;
     * Dashboard.tsx renders the existing plain company card for the rest —
     * an admin/member with nothing assigned sees no regression from before
     * this widget existed.
     *
     * @return array<string, array{overall_score: float|null, kpi_count: int, on_track: int, at_risk: int, needs_attention: array, total_weight: float, category_counts: array}>
     */
    private function myScoreByCompany(SupabaseUserService $supabase, string $userId): array
    {
        $myKpis = $supabase->get('kpis', [
            'assigned_user_id' => 'eq.' . $userId,
            'select' => 'id,company_id,name,target,weight,kpi_categories(name)',
        ]);

        if (empty($myKpis)) {
            return [];
        }

        $kpiIds = array_column($myKpis, 'id');

        // Latest-per-KPI, computed in PHP: PostgREST has no "distinct on"
        // without a view, and this is the same "pick the first per group"
        // pattern KpiController::index() already uses for grants/etc.
        $submissions = $supabase->get('kpi_submissions', [
            'kpi_id' => 'in.(' . implode(',', $kpiIds) . ')',
            'select' => 'kpi_id,value,submission_date',
            'order' => 'submission_date.desc',
        ]);

        $latestByKpiId = [];
        foreach ($submissions as $submission) {
            $latestByKpiId[$submission['kpi_id']] ??= $submission;
        }

        // Only the current financial year — a quarter row from a prior year
        // (once this feature has been live long enough to have any) must
        // never silently feed into this year's score.
        $quarters = $supabase->get('kpi_quarters', [
            'kpi_id' => 'in.(' . implode(',', $kpiIds) . ')',
            'financial_year' => 'eq.' . $this->currentFinancialYear(),
            'select' => 'kpi_id,quarter,target,actual,status',
        ]);

        $quartersByKpiId = collect($quarters)->groupBy('kpi_id')->map(fn ($group) => $group->all())->all();

        $byCompany = collect($myKpis)->groupBy('company_id');

        $scoreService = app(WeightedScoreService::class);

        return $byCompany->map(function ($kpis) use ($scoreService, $latestByKpiId, $quartersByKpiId) {
            $summary = $scoreService->summarize($kpis->all(), $latestByKpiId, $quartersByKpiId);

            // "My KPIs" preview (dashboard.blade.php's category-badge strip)
            // — grouped from the same fetched rows, not a second query.
            $summary['category_counts'] = $kpis
                ->groupBy(fn ($kpi) => $kpi['kpi_categories']['name'] ?? 'General')
                ->map(fn ($group, $category) => ['category' => $category, 'count' => $group->count()])
                ->values()
                ->all();

            return $summary;
        })->all();
    }

    /**
     * "Company Overview" lite (legacy CompanyOverview.tsx's department
     * ranking bar, minus the manager-only doughnut/quarterly-trend cards
     * that need per-period tracking the Platform doesn't have yet — see this
     * feature's own plan doc). Average achievement per department, computed
     * from whatever `kpi_submissions` rows RLS actually returns for this
     * caller (a plain employee only ever sees their own department's, same
     * as everywhere else in the Platform — this never widens visibility, it
     * only summarizes what was already visible).
     *
     * @return array<int, array{department: string, avg_achievement_pct: float|null, submission_count: int}>
     */
    private function departmentOverview(SupabaseUserService $supabase, string $companyId): array
    {
        $departments = $supabase->get('departments', [
            'company_id' => 'eq.' . $companyId,
            'select' => 'id,name',
        ]);

        if (empty($departments)) {
            return [];
        }

        $submissions = $supabase->get('kpi_submissions', [
            'company_id' => 'eq.' . $companyId,
            'select' => 'department_id,value,kpis(target)',
        ]);

        $byDepartment = collect($submissions)->groupBy('department_id');

        return collect($departments)->map(function ($department) use ($byDepartment) {
            $rows = $byDepartment->get($department['id'], collect())->filter(
                fn ($row) => ($row['kpis']['target'] ?? null) !== null && $row['kpis']['target'] != 0
            );

            $avg = $rows->isNotEmpty()
                ? round($rows->avg(fn ($row) => ($row['value'] / $row['kpis']['target']) * 100), 2)
                : null;

            return [
                'department' => $department['name'],
                'avg_achievement_pct' => $avg,
                'submission_count' => $rows->count(),
            ];
        })->values()->all();
    }

    /**
     * The Center-wide operator view. Every widget here is real, derived data
     * — nothing fabricated to fill a tile:
     *   - Total/Active/Suspended Companies, Total Users — one query each
     *     (`companies`, `users`), same 200-row defensive cap already used by
     *     `CompanyController::index()` (spec requirement #37).
     *   - Onboarding Progress — a breakdown by `companies.status`, the real
     *     lifecycle column (`draft → onboarding → configuring → active →
     *     suspended → archived`, see CompanyLifecycleService), not a
     *     separately tracked progress percentage that could drift from it.
     *   - System Health — deliberately narrow: DB reachability (this
     *     method's own queries either succeed or this degrades gracefully
     *     below) and a link to Horizon's own dashboard for queue/job health,
     *     which already exists and would be a redundant custom page to
     *     rebuild (see the Phase 11 audit log note). No fabricated CPU/memory
     *     metrics this app has no way to actually observe.
     *   - Recent Admin Activity / Security Alerts — both read straight from
     *     `admin_action_logs` (the comprehensive audit system, requirement
     *     #8) rather than a second, separate tracking mechanism. "Security
     *     Alerts" specifically surfaces `login_failed`/`access_denied`/
     *     `telegram_link_failed` — the three action types that already exist
     *     for exactly this purpose — rather than inventing a new anomaly-
     *     detection engine.
     */
    private function platformOverview(Request $request, array $platformUser, SupabaseUserService $supabase)
    {
        $health = ['database' => 'reachable'];

        try {
            $companies = $supabase->get('companies', [
                'select' => 'id,name,code,status,onboarding_status,created_at',
                'order' => 'created_at.desc',
                'limit' => 200,
            ]);
        } catch (\Throwable) {
            $companies = [];
            $health['database'] = 'unreachable';
        }

        $statusCounts = collect($companies)->countBy('status');

        $totalUsers = 0;
        if ($health['database'] === 'reachable') {
            try {
                $totalUsers = count($supabase->get('users', ['select' => 'id', 'limit' => 5000]));
            } catch (\Throwable) {
                $health['database'] = 'degraded';
            }
        }

        $securityActionTypes = ['login_failed', 'access_denied', 'telegram_link_failed'];
        $recentActivity = [];
        $securityEvents = [];
        $securityCounts24h = ['login_failed' => 0, 'access_denied' => 0];

        try {
            $recentActivity = $supabase->get('admin_action_logs', [
                'select' => 'id,action,actor_email,target_company_id,target_type,occurred_at',
                'order' => 'occurred_at.desc',
                'limit' => 10,
            ]);

            $securityEvents = $supabase->get('admin_action_logs', [
                'action' => 'in.(' . implode(',', $securityActionTypes) . ')',
                'select' => 'id,action,actor_email,metadata,occurred_at',
                'order' => 'occurred_at.desc',
                'limit' => 10,
            ]);

            $since24h = now()->subDay()->toIso8601String();

            $securityCounts24h['login_failed'] = count($supabase->get('admin_action_logs', [
                'action' => 'eq.login_failed', 'occurred_at' => 'gte.' . $since24h, 'select' => 'id',
            ]));
            $securityCounts24h['access_denied'] = count($supabase->get('admin_action_logs', [
                'action' => 'eq.access_denied', 'occurred_at' => 'gte.' . $since24h, 'select' => 'id',
            ]));
        } catch (\Throwable) {
            // The audit log itself being unreachable shouldn't take the
            // whole operator dashboard down with it — the stats above are
            // still worth showing even if the activity feed can't load.
        }

        $companiesById = collect($companies)->keyBy('id');

        $recentActivity = collect($recentActivity)->map(fn ($log) => [
            ...$log,
            'target_company_name' => $log['target_company_id']
                ? ($companiesById->get($log['target_company_id'])['name'] ?? null)
                : null,
        ])->values()->all();

        return Inertia::render('Platform/PlatformOverview', [
            'me' => $platformUser,
            'stats' => [
                'total_companies' => count($companies),
                'active_companies' => $statusCounts->get('active', 0),
                'suspended_companies' => $statusCounts->get('suspended', 0),
                'total_users' => $totalUsers,
            ],
            'onboardingProgress' => [
                'draft' => $statusCounts->get('draft', 0),
                'onboarding' => $statusCounts->get('onboarding', 0),
                'configuring' => $statusCounts->get('configuring', 0),
                'active' => $statusCounts->get('active', 0),
                'suspended' => $statusCounts->get('suspended', 0),
                'archived' => $statusCounts->get('archived', 0),
            ],
            'systemHealth' => [
                'database' => $health['database'],
                'horizonUrl' => '/horizon',
            ],
            'recentActivity' => $recentActivity,
            'securityAlerts' => [
                'loginFailed24h' => $securityCounts24h['login_failed'],
                'accessDenied24h' => $securityCounts24h['access_denied'],
                'recent' => $securityEvents,
            ],
            'companies' => $companies,
        ]);
    }
}