<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\ComputesFinancialYear;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Platform-native "My Department KPI" — legacy's version
 * (KpiController::myDepartmentKpi()) lists every KPI whose owner shares the
 * caller's `department_code`, on the legacy single-tenant `employees`/`kpis`
 * schema. The Platform has no equivalent single-user "my department" concept
 * built directly into `kpis` (KPIs aren't columned by department — only
 * `assigned_user_id` and, for restricted sharing, `kpi_access_grants`), so
 * this reconstructs the same idea from data the Platform actually has:
 * whichever department(s) the caller belongs to (`department_users`), then
 * every KPI assigned to a member of that department.
 *
 * A caller with no department membership (a Company Admin/SLT who was never
 * added to a department row) gets an honest empty state rather than a 403 —
 * matching legacy's own behaviour of never gating this page by role.
 */
class DepartmentKpiController extends Controller
{
    use ComputesFinancialYear;
    use PlatformAuthorization;

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $platformUser = $request->attributes->get('platformUser');
        $meId = $platformUser['id'];

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name,code']);

        $myMemberships = $supabase->get('department_users', [
            'company_id' => 'eq.' . $company,
            'user_id' => 'eq.' . $meId,
            'select' => 'department_id,departments(id,name)',
        ]);

        if (empty($myMemberships)) {
            return Inertia::render('Platform/DepartmentKpi/Index', [
                'company' => $companyRow,
                'department' => null,
                'members' => [],
                'kpis' => [],
                'avgAchievement' => null,
            ]);
        }

        $department = $myMemberships[0]['departments'];

        $deptMembers = $supabase->get('department_users', [
            'department_id' => 'eq.' . $department['id'],
            'select' => 'user_id,users(name,email)',
        ]);
        $memberIds = array_column($deptMembers, 'user_id');
        $memberMap = collect($deptMembers)->keyBy('user_id');

        $kpis = empty($memberIds) ? [] : $supabase->get('kpis', [
            'company_id' => 'eq.' . $company,
            'assigned_user_id' => 'in.(' . implode(',', $memberIds) . ')',
            'select' => 'id,name,target,weight,assigned_user_id',
            'order' => 'name.asc',
        ]);

        $kpiIds = array_column($kpis, 'id');

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
        $quartersByKpiId = collect($quarters)->groupBy('kpi_id');

        $rows = collect($kpis)->map(function ($kpi) use ($memberMap, $latestByKpiId, $quartersByKpiId) {
            $owner = $memberMap->get($kpi['assigned_user_id']);
            $kpiQuarters = $quartersByKpiId->get($kpi['id'], collect());

            return [
                'id' => $kpi['id'],
                'name' => $kpi['name'],
                'target' => $kpi['target'],
                'weight' => $kpi['weight'],
                'owner_name' => $owner['users']['name'] ?? 'Unknown',
                'owner_email' => $owner['users']['email'] ?? '',
                'achievement' => $this->achievementFor($kpi, $latestByKpiId[$kpi['id']] ?? null, $kpiQuarters),
            ];
        })->values();

        $scored = $rows->filter(fn ($r) => $r['achievement'] !== null);

        return Inertia::render('Platform/DepartmentKpi/Index', [
            'company' => $companyRow,
            'department' => $department,
            'members' => array_values($memberMap->map(fn ($m) => ['name' => $m['users']['name'] ?? 'Unknown'])->all()),
            'kpis' => $rows,
            'avgAchievement' => $scored->isNotEmpty() ? round($scored->avg('achievement'), 1) : null,
        ]);
    }

    /**
     * A display-only achievement % — the same two-branch precedence
     * (quarter rollup beats a single latest submission) WeightedScoreService
     * uses, but computed per KPI regardless of `weight`, since this is a
     * plain roster listing, not a weight-gated portfolio score.
     */
    private function achievementFor(array $kpi, ?array $latestSubmission, \Illuminate\Support\Collection $quarters): ?float
    {
        if ($quarters->isNotEmpty()) {
            $sumTarget = (float) $quarters->sum(fn ($q) => (float) ($q['target'] ?? 0));
            $sumActual = (float) $quarters->sum(fn ($q) => (float) ($q['actual'] ?? 0));

            return $sumTarget > 0 ? round(min(100, ($sumActual / $sumTarget) * 100), 1) : null;
        }

        $target = $kpi['target'] ?? null;
        $value = $latestSubmission['value'] ?? null;

        if (!$target || $value === null) {
            return null;
        }

        return round(min(100, ($value / $target) * 100), 1);
    }
}
