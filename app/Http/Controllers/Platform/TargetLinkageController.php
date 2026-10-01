<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\ComputesFinancialYear;
use App\Http\Controllers\Platform\Concerns\ComputesLinkageCoverage;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Ports legacy's Target Linkages (see the migration's own docblock,
 * 2026_09_29_060000_create_kpi_target_linkages.php, for the category_id/unit
 * adaptation and the one real hierarchy-enforcement improvement over legacy).
 * Anyone with at least one direct report (`company_users.manager_user_id`
 * pointing at them) may cascade a target to that report — not gated to
 * Company Admin, matching legacy where this is a personal/manager feature.
 */
class TargetLinkageController extends Controller
{
    use ComputesFinancialYear;
    use ComputesLinkageCoverage;
    use PlatformAuthorization;

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name,code']);
        abort_if(!$companyRow, 404);

        $meId = $request->attributes->get('platformUser')['id'];
        $financialYear = $this->currentFinancialYear();

        $directReports = $supabase->get('company_users', [
            'company_id' => 'eq.' . $company,
            'manager_user_id' => 'eq.' . $meId,
            'status' => 'eq.active',
            'select' => 'user_id,users!company_users_user_id_foreign(name,email)',
        ]);

        $incoming = $supabase->get('kpi_target_linkages', [
            'company_id' => 'eq.' . $company,
            'financial_year' => 'eq.' . $financialYear,
            'assignee_user_id' => 'eq.' . $meId,
            'select' => '*,kpi_categories(name),assigner:users!kpi_target_linkages_assigner_user_id_foreign(name)',
        ]);

        $outgoing = $supabase->get('kpi_target_linkages', [
            'company_id' => 'eq.' . $company,
            'financial_year' => 'eq.' . $financialYear,
            'assigner_user_id' => 'eq.' . $meId,
            'select' => '*,kpi_categories(name),assignee:users!kpi_target_linkages_assignee_user_id_foreign(name)',
        ]);

        // Coverage for incoming: MY OWN KPIs against what was assigned to me.
        $myKpis = $supabase->get('kpis', [
            'company_id' => 'eq.' . $company,
            'assigned_user_id' => 'eq.' . $meId,
            'select' => 'category_id,unit,target',
        ]);

        // Coverage for outgoing: each report's own KPIs, scoped to just the
        // reports I actually assigned something to (a much narrower query
        // than every KPI in the company).
        $reportIds = array_unique(array_column($outgoing, 'assignee_user_id'));
        $reportKpis = empty($reportIds) ? [] : $supabase->get('kpis', [
            'company_id' => 'eq.' . $company,
            'assigned_user_id' => 'in.(' . implode(',', $reportIds) . ')',
            'select' => 'assigned_user_id,category_id,unit,target',
        ]);
        $reportKpisByUser = [];
        foreach ($reportKpis as $kpi) {
            $reportKpisByUser[$kpi['assigned_user_id']][] = $kpi;
        }

        $incomingWithCoverage = $this->withLinkageCoverage($incoming, $this->linkageCoverageMap($myKpis));

        $outgoingWithCoverage = array_map(function ($lnk) use ($reportKpisByUser) {
            $coverage = $this->linkageCoverageMap($reportKpisByUser[$lnk['assignee_user_id']] ?? []);

            return $this->withLinkageCoverage([$lnk], $coverage)[0];
        }, $outgoing);

        return Inertia::render('Platform/TargetLinkages/Index', [
            'company' => $companyRow,
            'financialYear' => $financialYear,
            'directReports' => $directReports,
            'categories' => $supabase->get('kpi_categories', [
                'company_id' => 'eq.' . $company,
                'select' => 'id,name',
                'order' => 'name.asc',
            ]),
            'incoming' => array_values($incomingWithCoverage),
            'outgoing' => array_values($outgoingWithCoverage),
            'canAssignTarget' => count($directReports) > 0,
        ]);
    }

    public function store(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        $request->validate([
            'assignee_user_id' => 'required|uuid',
            'category_id' => 'required|uuid',
            'unit' => 'nullable|string|max:50',
            'assigned_target' => 'required|numeric|min:0',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $meId = $request->attributes->get('platformUser')['id'];
        $financialYear = $this->currentFinancialYear();

        $report = $supabase->first('company_users', [
            'company_id' => 'eq.' . $company,
            'user_id' => 'eq.' . $request->assignee_user_id,
            'manager_user_id' => 'eq.' . $meId,
            'status' => 'eq.active',
            'select' => 'user_id,users!company_users_user_id_foreign(name)',
        ]);

        if (!$report) {
            return back()->with('error', 'You can only assign a target linkage to one of your own direct reports.');
        }

        $existing = $supabase->first('kpi_target_linkages', [
            'company_id' => 'eq.' . $company,
            'financial_year' => 'eq.' . $financialYear,
            'assigner_user_id' => 'eq.' . $meId,
            'assignee_user_id' => 'eq.' . $request->assignee_user_id,
            'category_id' => 'eq.' . $request->category_id,
            'unit' => $request->unit ? 'eq.' . $request->unit : 'is.null',
            'select' => 'id',
        ]);

        $payload = ['assigned_target' => $request->assigned_target];

        try {
            if ($existing) {
                $supabase->update('kpi_target_linkages', ['id' => 'eq.' . $existing['id']], $payload, false);
            } else {
                $supabase->insert('kpi_target_linkages', $payload + [
                    'company_id' => $company,
                    'financial_year' => $financialYear,
                    'assigner_user_id' => $meId,
                    'assignee_user_id' => $request->assignee_user_id,
                    'category_id' => $request->category_id,
                    'unit' => $request->unit,
                ], false);
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not save this target linkage: ' . $e->getMessage());
        }

        return back()->with('success', 'Target linkage saved for ' . ($report['users']['name'] ?? 'this employee') . '.');
    }

    public function destroy(Request $request, string $company, string $linkage)
    {
        $this->ensureCompanyMember($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $meId = $request->attributes->get('platformUser')['id'];

        $existing = $supabase->first('kpi_target_linkages', [
            'id' => 'eq.' . $linkage,
            'company_id' => 'eq.' . $company,
            'select' => 'id,assigner_user_id',
        ]);

        if (!$existing || ($existing['assigner_user_id'] !== $meId && !$this->canAdministerCompany($request, $company))) {
            return back()->with('error', 'Not authorized.');
        }

        $supabase->delete('kpi_target_linkages', ['id' => 'eq.' . $linkage]);

        return back()->with('success', 'Linkage removed.');
    }
}
