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
 * HR / People Management command centre — spec §35-45. There is no
 * per-employee performance-review data model in this schema yet (no
 * `reviews` table, no review-status column anywhere) — rather than
 * fabricate review-completion numbers, this honestly surfaces that gap
 * (matching this codebase's own established pattern for an unbuilt feature,
 * e.g. the onboarding wizard's "Configure ANIRA" placeholder) instead of
 * inventing data nothing tracks.
 *
 * A per-employee performance figure IS real and derivable, though: `kpis`
 * carries `owner_user_id` (2026_08_28_020000) and `company_kpi_achievement`
 * exposes each KPI's live achievement — averaging a person's owned KPIs'
 * achievement is the same "weighted average of what you own" idea the
 * legacy scoring formula already uses, just computed here rather than
 * duplicated as a second stored score.
 */
class PeopleController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    private const EXCEEDED_FLOOR = 100;
    private const ON_TRACK_FLOOR = 85;
    private const AT_RISK_FLOOR = 60;

    private function ensurePeopleViewer(Request $request, string $company): void
    {
        if ($this->canAdministerCompany($request, $company)) {
            return;
        }

        abort_unless(
            in_array($this->companyRole($request, $company), ['slt', 'hr'], true),
            403,
            'You do not have people-performance access.'
        );
    }

    /** Buckets a list of {owner_user_id, achievement_pct} rows into one avg score per user. */
    private function scoresByOwner(array $kpiAchievement): \Illuminate\Support\Collection
    {
        return collect($kpiAchievement)
            ->whereNotNull('owner_user_id')
            ->groupBy('owner_user_id')
            ->map(fn ($rows) => $rows->pluck('achievement_pct')->filter(fn ($v) => $v !== null))
            ->filter(fn ($vals) => $vals->isNotEmpty())
            ->map(fn ($vals) => round($vals->avg(), 1));
    }

    private function bucketFor(?float $score): string
    {
        if ($score === null) return 'no_data';
        if ($score >= self::EXCEEDED_FLOOR) return 'exceeded';
        if ($score >= self::ON_TRACK_FLOOR) return 'on_track';
        if ($score >= self::AT_RISK_FLOOR) return 'at_risk';
        return 'critical';
    }

    public function index(Request $request, string $company)
    {
        $this->ensurePeopleViewer($request, $company);
        $this->logAdminAccessIfCrossCompany($request, 'view_people_performance', $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name,code,financial_year_start_month']);

        $periods = new PerformancePeriodService((int) ($companyRow['financial_year_start_month'] ?? 1));
        $now = Carbon::now();
        [$periodStart, $periodEnd] = $periods->quarterBounds($periods->financialYearFor($now), $periods->quarterFor($now));

        $departments = $supabase->get('departments', ['company_id' => 'eq.' . $company, 'select' => 'id,name']);
        $departmentIds = array_column($departments, 'id');

        $members = empty($departmentIds) ? [] : $supabase->get('department_users', [
            'department_id' => 'in.(' . implode(',', $departmentIds) . ')',
            'select' => 'user_id,department_id,manager_user_id,employment_status',
        ]);
        $activeMembers = collect($members)->where('employment_status', '!=', 'terminated');

        $kpiAchievement = [];
        try {
            $kpiAchievement = $supabase->get('company_kpi_achievement', ['company_id' => 'eq.' . $company, 'select' => '*']);
        } catch (\Throwable) {
        }

        $summary = null;
        try {
            $summary = $supabase->first('company_kpi_summary', ['company_id' => 'eq.' . $company, 'select' => '*']);
        } catch (\Throwable) {
        }

        $scoresByOwner = $this->scoresByOwner($kpiAchievement);
        $distribution = ['exceeded' => 0, 'on_track' => 0, 'at_risk' => 0, 'critical' => 0];
        foreach ($activeMembers as $m) {
            $bucket = $this->bucketFor($scoresByOwner->get($m['user_id']));
            if ($bucket !== 'no_data') {
                $distribution[$bucket]++;
            }
        }

        $ownedKpiCounts = collect($kpiAchievement)->whereNotNull('owner_user_id')->countBy('owner_user_id');
        $employeesWithKpi = $activeMembers->pluck('user_id')->unique()->filter(fn ($id) => $ownedKpiCounts->get($id, 0) > 0)->count();
        $employeesWithoutKpi = max(0, $activeMembers->pluck('user_id')->unique()->count() - $employeesWithKpi);

        $updatedThisPeriod = collect($kpiAchievement)->filter(function ($k) use ($periodStart, $periodEnd) {
            if (!$k['latest_submission_date']) return false;
            $date = Carbon::parse($k['latest_submission_date']);
            return $date->betweenIncluded($periodStart, $periodEnd);
        })->count();
        $totalKpisWithOwner = collect($kpiAchievement)->whereNotNull('owner_user_id')->count();
        $updateCompletionPct = $totalKpisWithOwner > 0 ? round(($updatedThisPeriod / $totalKpisWithOwner) * 100, 1) : null;

        $pendingApproval = 0;
        try {
            $pendingApproval = count($supabase->get('kpi_submissions', [
                'company_id' => 'eq.' . $company,
                'status' => 'eq.pending_review',
                'select' => 'id',
            ]));
        } catch (\Throwable) {
        }

        $managers = $activeMembers->pluck('manager_user_id')->filter()->unique()->count();

        return Inertia::render('Platform/People/Index', [
            'company' => $companyRow,
            'stats' => [
                'employees' => $activeMembers->pluck('user_id')->unique()->count(),
                'overall_performance' => $summary['avg_achievement_pct'] ?? null,
                'kpi_update_completion' => $updateCompletionPct,
                'managers' => $managers,
                'departments' => count($departments),
            ],
            'distribution' => $distribution,
            'compliance' => [
                'employees_with_kpi' => $employeesWithKpi,
                'employees_without_kpi' => $employeesWithoutKpi,
                'update_completion_pct' => $updateCompletionPct,
                'updates_overdue' => max(0, $totalKpisWithOwner - $updatedThisPeriod),
                'pending_approval' => $pendingApproval,
            ],
            'attention' => [
                'critical_performance' => $distribution['critical'],
                'no_active_kpi' => $employeesWithoutKpi,
                'no_reporting_manager' => $activeMembers->whereNull('manager_user_id')->count(),
            ],
            'reviewsTracked' => false,
        ]);
    }

    public function employees(Request $request, string $company)
    {
        $this->ensurePeopleViewer($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name,code']);

        $departments = $supabase->get('departments', ['company_id' => 'eq.' . $company, 'select' => 'id,name']);
        $departmentIds = array_column($departments, 'id');
        $departmentsById = collect($departments)->keyBy('id');

        $memberQuery = [
            'department_id' => empty($departmentIds) ? 'in.()' : 'in.(' . implode(',', $departmentIds) . ')',
            'select' => 'user_id,department_id,manager_user_id,position_title,employment_status,users(name,email)',
        ];
        if ($request->filled('department_id')) {
            $memberQuery['department_id'] = 'eq.' . $request->query('department_id');
        }

        $members = empty($departmentIds) ? [] : $supabase->get('department_users', $memberQuery);

        $managerIds = collect($members)->pluck('manager_user_id')->filter()->unique()->values()->all();
        $managers = empty($managerIds) ? [] : $supabase->get('users', ['id' => 'in.(' . implode(',', $managerIds) . ')', 'select' => 'id,name']);
        $managersById = collect($managers)->keyBy('id');

        $kpiAchievement = [];
        try {
            $kpiAchievement = $supabase->get('company_kpi_achievement', ['company_id' => 'eq.' . $company, 'select' => '*']);
        } catch (\Throwable) {
        }
        $scoresByOwner = $this->scoresByOwner($kpiAchievement);

        $rows = collect($members)->map(function ($m) use ($departmentsById, $managersById, $scoresByOwner) {
            $score = $scoresByOwner->get($m['user_id']);
            return [
                'user_id' => $m['user_id'],
                'name' => $m['users']['name'] ?? '—',
                'email' => $m['users']['email'] ?? null,
                'position_title' => $m['position_title'],
                'department_name' => $departmentsById->get($m['department_id'])['name'] ?? null,
                'manager_name' => $m['manager_user_id'] ? ($managersById->get($m['manager_user_id'])['name'] ?? null) : null,
                'employment_status' => $m['employment_status'],
                'score' => $score,
                'status' => $this->bucketFor($score),
            ];
        });

        if ($request->filled('status')) {
            $rows = $rows->where('status', $request->query('status'));
        }

        return Inertia::render('Platform/People/Employees', [
            'company' => $companyRow,
            'departments' => $departments,
            'employees' => $rows->values(),
            'filters' => ['department_id' => $request->query('department_id', ''), 'status' => $request->query('status', '')],
        ]);
    }

    public function managers(Request $request, string $company)
    {
        $this->ensurePeopleViewer($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name,code']);

        $departments = $supabase->get('departments', ['company_id' => 'eq.' . $company, 'select' => 'id']);
        $departmentIds = array_column($departments, 'id');

        $members = empty($departmentIds) ? [] : $supabase->get('department_users', [
            'department_id' => 'in.(' . implode(',', $departmentIds) . ')',
            'select' => 'user_id,manager_user_id',
        ]);

        $managerIds = collect($members)->pluck('manager_user_id')->filter()->unique()->values()->all();
        $managerUsers = empty($managerIds) ? [] : $supabase->get('users', ['id' => 'in.(' . implode(',', $managerIds) . ')', 'select' => 'id,name']);
        $managersById = collect($managerUsers)->keyBy('id');

        $kpiAchievement = [];
        try {
            $kpiAchievement = $supabase->get('company_kpi_achievement', ['company_id' => 'eq.' . $company, 'select' => '*']);
        } catch (\Throwable) {
        }
        $scoresByOwner = $this->scoresByOwner($kpiAchievement);

        $byManager = collect($members)->groupBy('manager_user_id');

        $rows = $byManager->filter(fn ($_, $managerId) => $managerId)->map(function ($reports, $managerId) use ($managersById, $scoresByOwner) {
            $reportScores = $reports->pluck('user_id')->map(fn ($id) => $scoresByOwner->get($id))->filter(fn ($v) => $v !== null);
            $withKpi = $reports->pluck('user_id')->filter(fn ($id) => $scoresByOwner->has($id))->count();

            return [
                'manager_id' => $managerId,
                'manager_name' => $managersById->get($managerId)['name'] ?? '—',
                'staff_count' => $reports->count(),
                'team_performance' => $reportScores->isNotEmpty() ? round($reportScores->avg(), 1) : null,
                'kpi_update_completion_pct' => $reports->count() > 0 ? round(($withKpi / $reports->count()) * 100, 1) : null,
            ];
        })->values();

        return Inertia::render('Platform/People/Managers', [
            'company' => $companyRow,
            'managers' => $rows,
        ]);
    }
}
