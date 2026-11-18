<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\Request;

class OrgChartController extends Controller
{
    // BTS / CORPORATE GOVERNANCE & HR DEPT only -- same gate as
    // StaffEvaluationSummaryController, this page is for HR and BTS.
    private function requireHrBtsAccess(): void
    {
        if (!$this->isCrossCompanyMonitorSession()) {
            abort(403, 'This page is only accessible to BTS and Corporate Governance & HR.');
        }
    }

    // Mirrors ApprovalHierarchyService::getApprover()'s exact per-role chain
    // (EXECUTIVE -> manager_id, fallback vp_id; MANAGER -> vp_id, fallback
    // reports_to_id; VP -> reports_to_id) but just returns the parent id
    // instead of fetching a fresh row, since the whole roster is already
    // loaded in memory here. One deliberate difference from that service:
    // SLT also resolves via reports_to_id here. ApprovalHierarchyService
    // treats every SLT as the top of the approval chain (correct -- nothing
    // above SLT approves anything), but org-chart-wise two SLT staff aren't
    // necessarily peers: a company can have its own SLT reporting to a
    // group-level SLT (e.g. RCG's Hafiz Jasmi reporting to Suley). Without
    // this, every SLT row becomes its own disconnected root.
    private function parentIdFor(array $emp): ?string
    {
        $role = strtoupper(trim($emp['role'] ?? ''));

        return match ($role) {
            'EXECUTIVE' => $emp['manager_id'] ?: ($emp['vp_id'] ?: null),
            'MANAGER'   => $emp['vp_id'] ?: ($emp['reports_to_id'] ?: null),
            'VP', 'SLT' => $emp['reports_to_id'] ?: null,
            default     => null, // unrecognised role -- nothing to resolve against
        };
    }

    public function index(Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login')->with('error', 'Sila login terlebih dahulu.');
        }

        $this->requireHrBtsAccess();

        // Every viewer who reaches this page already passed
        // requireHrBtsAccess() above, i.e. is a cross-company monitor by
        // definition (see Controller::isCrossCompanyMonitorSession()'s own
        // docblock: "cross-department/cross-company monitoring access...
        // in every company"). Without this, the chart was silently locked
        // to whichever single company the viewer happened to be logged
        // into right now, with no way to see any other company's
        // structure short of logging out and picking a different
        // dashboard at login -- which made an entire company's
        // departments (e.g. RCG's TITAN, MARKETING, OPERATION... when the
        // viewer's own session company is RGHB) look simply missing.
        $companies = $supabase->get('companies', [
            'select' => 'code,name',
            'order'  => 'name.asc',
        ]) ?? [];

        $requestedCompany = strtoupper(trim($request->query('company', '')));
        $validCompanyCodes = collect($companies)->pluck('code')->map(fn ($c) => strtoupper($c))->all();
        $companyCode = in_array($requestedCompany, $validCompanyCodes, true)
            ? $requestedCompany
            : strtoupper(session('company_code'));

        $deptFilter = strtoupper(trim($request->query('department', 'ALL')));

        $departments = $supabase->get('departments', [
            'company_code' => 'eq.' . $companyCode,
            'select'       => 'code,name',
            'order'        => 'name.asc',
        ]) ?? [];

        $employees = $supabase->get('employees', [
            'company_code' => 'eq.' . $companyCode,
            'is_active'    => 'eq.true',
            'select'       => 'id,employee_id,short_name,full_name,department_code,role,position,manager_id,vp_id,reports_to_id',
            'order'        => 'short_name.asc',
        ]) ?? [];

        // Same cross-company-monitor roster exclusion as the SLT Dashboard /
        // Staff Evaluation Summary: a BTS/HR viewer's own row only counts as
        // THIS company's own staff when it's their default company membership,
        // so viewing this company's chart doesn't put the viewer's own row
        // into its own org chart.
        $empIds = collect($employees)->pluck('id')->filter()->values()->toArray();
        if (!empty($empIds)) {
            $rolesForCompany = $supabase->get('user_company_roles', [
                'company_code' => 'eq.' . $companyCode,
                'employee_id'  => 'in.(' . implode(',', $empIds) . ')',
                'select'       => 'employee_id,is_default',
            ]);
            $isDefaultByEmployeeId = collect($rolesForCompany)->pluck('is_default', 'employee_id');
            $allById = collect($employees)->keyBy('id');
            $keptById = collect($employees)->filter(function ($emp) use ($isDefaultByEmployeeId) {
                return $isDefaultByEmployeeId->get($emp['id'], true) !== false;
            })->keyBy('id');

            // A row dropped above still has to come back if some KEPT staff
            // member's real reporting line points straight at them --
            // otherwise excluding a non-default row (meant only to hide a
            // BTS/HR viewer's own foreign membership from a company that
            // isn't theirs) would also decapitate the real chain of command
            // whenever a company's true top happens to have it as a
            // secondary, not default, membership (e.g. Suley is RCG's top
            // SLT -- every VP and Hafiz Jasmi report straight to her -- but
            // her own default company membership is elsewhere). Resolved to
            // a fixed point since a re-added parent can itself need another.
            do {
                $toAdd = [];
                foreach ($keptById->all() as $emp) {
                    $parentId = $this->parentIdFor($emp);
                    if ($parentId && !$keptById->has($parentId) && $allById->has($parentId)) {
                        $toAdd[$parentId] = $allById->get($parentId);
                    }
                }
                foreach ($toAdd as $id => $empToAdd) {
                    $keptById->put($id, $empToAdd);
                }
            } while (!empty($toAdd));

            $employees = $keptById->values()->all();
        }

        $nodes = [];
        foreach ($employees as $emp) {
            $role = strtoupper(trim($emp['role'] ?? '')) ?: '-';
            $nodes[$emp['id']] = [
                'id'             => $emp['id'],
                'employee_id'    => $emp['employee_id'] ?? '-',
                'name'           => $emp['short_name'] ?? $emp['full_name'] ?? 'Unknown',
                'role'           => $role,
                'department'     => $emp['department_code'] ?? '-',
                'position'       => $emp['position'] ?? null,
                // Not removed from the tree when a department filter is active
                // (that would break the chain above/below it) -- just flagged
                // so the view can dim everything outside the selected department.
                'in_dept_filter' => $deptFilter === 'ALL' || strtoupper(trim($emp['department_code'] ?? '')) === $deptFilter,
                'children'       => [],
            ];
        }

        $roots = [];
        foreach ($employees as $emp) {
            $parentId = $this->parentIdFor($emp);
            if ($parentId && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$nodes[$emp['id']];
            } else {
                // No resolvable parent inside this company's own active roster
                // (true top of the chain, or the chain points outside this
                // filtered set) -- surfaces as its own root rather than being
                // silently dropped.
                $roots[] = &$nodes[$emp['id']];
            }
        }
        unset($emp);

        $rolePriority = ['SLT' => 1, 'VP' => 2, 'MANAGER' => 3, 'EXECUTIVE' => 4];
        $sortNodes = function (array &$list) use (&$sortNodes, $rolePriority) {
            usort($list, function ($a, $b) use ($rolePriority) {
                $pa = $rolePriority[$a['role']] ?? 5;
                $pb = $rolePriority[$b['role']] ?? 5;
                return $pa <=> $pb ?: strcasecmp($a['name'], $b['name']);
            });
            foreach ($list as &$node) {
                $sortNodes($node['children']);
            }
        };
        $sortNodes($roots);

        $roleCounts = array_fill_keys(['SLT', 'VP', 'MANAGER', 'EXECUTIVE'], 0);
        foreach ($employees as $emp) {
            $r = strtoupper(trim($emp['role'] ?? ''));
            if (isset($roleCounts[$r])) {
                $roleCounts[$r]++;
            }
        }

        return view('org-chart.index', [
            'today'       => now()->timezone('Asia/Kuala_Lumpur')->format('d M Y'),
            'companyCode' => $companyCode,
            'companies'   => $companies,
            'departments' => $departments,
            'deptFilter'  => $deptFilter,
            'roots'       => $roots,
            'totalStaff'  => count($employees),
            'roleCounts'  => $roleCounts,
        ]);
    }
}
