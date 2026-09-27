<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LinkageController extends Controller
{
    protected SupabaseService $supabase;

    public function __construct(SupabaseService $supabase)
    {
        $this->supabase = $supabase;
    }

    private function currentUser(): array
    {
        $employeeUuid = session('employee_uuid');
        if (!$employeeUuid) abort(403, 'Not logged in.');
        $rows = $this->supabase->get('employees', [
            'id'        => 'eq.' . $employeeUuid,
            'is_active' => 'eq.true',
            'select'    => '*',
        ]);
        if (empty($rows)) abort(403, 'Employee not found.');
        return $rows[0];
    }

    private function nowMy(): string
    {
        return now()->timezone('Asia/Kuala_Lumpur')->toDateTimeString();
    }

    private function currentFY(): string
    {
        return 'FY' . now()->year;
    }

    public function index()
    {
        $user        = $this->currentUser();
        $companyCode = $user['company_code'];
        $role        = strtoupper(trim($user['role'] ?? ''));
        $userId      = $user['id'];
        $fy          = $this->currentFY();

        // Same batch pattern as DashboardController: incoming/outgoing
        // linkages and direct reports are independent of one another, so
        // they're fetched concurrently rather than one after another.
        $batch = [
            'incoming' => ['table' => 'kpi_linkages', 'query' => [
                'assignee_id'    => 'eq.' . $userId,
                'financial_year' => 'eq.' . $fy,
                'company_code'   => 'eq.' . $companyCode,
                'select'         => '*',
            ]],
            'outgoing' => ['table' => 'kpi_linkages', 'query' => [
                'assigner_id'    => 'eq.' . $userId,
                'financial_year' => 'eq.' . $fy,
                'company_code'   => 'eq.' . $companyCode,
                'select'         => '*',
            ]],
        ];

        if ($role === 'SLT') {
            $batch['directReports'] = ['table' => 'employees', 'query' => [
                'company_code' => 'eq.' . $companyCode,
                'role'         => 'eq.VP',
                'is_active'    => 'eq.true',
                'select'       => 'id,short_name,role',
                'order'        => 'short_name.asc',
            ]];
        } elseif ($role === 'VP') {
            $batch['byVpId'] = ['table' => 'employees', 'query' => [
                'company_code' => 'eq.' . $companyCode,
                'vp_id'        => 'eq.' . $userId,
                'is_active'    => 'eq.true',
                'select'       => 'id,short_name,role',
            ]];
            $batch['byReportsTo'] = ['table' => 'employees', 'query' => [
                'company_code'  => 'eq.' . $companyCode,
                'reports_to_id' => 'eq.' . $userId,
                'is_active'     => 'eq.true',
                'select'        => 'id,short_name,role',
            ]];
        } elseif ($role === 'MANAGER') {
            $batch['directReports'] = ['table' => 'employees', 'query' => [
                'company_code' => 'eq.' . $companyCode,
                'manager_id'   => 'eq.' . $userId,
                'is_active'    => 'eq.true',
                'select'       => 'id,short_name,role',
                'order'        => 'short_name.asc',
            ]];
        }

        $batchResults     = $this->supabase->getMany($batch);
        $incomingLinkages = $batchResults['incoming'] ?? [];
        $outgoingLinkages = $batchResults['outgoing'] ?? [];

        $directReports = [];
        if ($role === 'SLT' || $role === 'MANAGER') {
            $directReports = $batchResults['directReports'] ?? [];
        } elseif ($role === 'VP') {
            $byVpId      = $batchResults['byVpId'] ?? [];
            $byReportsTo = $batchResults['byReportsTo'] ?? [];
            $drIds       = collect($byVpId)->pluck('id')->toArray();
            foreach ($byReportsTo as $r) {
                if (!in_array($r['id'], $drIds)) { $byVpId[] = $r; $drIds[] = $r['id']; }
            }
            $directReports = $byVpId;
        }

        // Coverage only needs MY OWN KPIs (for targets assigned to me) and
        // each DIRECT REPORT's own KPIs (for targets I assigned to them) —
        // a much narrower scope than the dashboard's full department/company
        // visibility, so this stays a small, independent query.
        $employeeIds = array_values(array_unique(array_merge(
            [$userId],
            collect($directReports)->pluck('id')->toArray()
        )));

        $kpis = $this->supabase->get('kpis', [
            'company_code'   => 'eq.' . $companyCode,
            'employee_id'    => 'in.(' . implode(',', $employeeIds) . ')',
            'financial_year' => 'eq.' . $fy,
            'select'         => 'id,employee_id,sub_category,unit,base_target',
        ]) ?? [];

        $kpiRows        = collect($kpis);
        $individualKpis = $kpiRows->filter(fn($k) => (string)($k['employee_id'] ?? '') === (string)$userId);

        // Key: "sub_category|unit" so RM totals never mix with % or number totals
        $mySubCatSums = $individualKpis
            ->groupBy(fn($k) => ($k['sub_category'] ?? '') . '|' . ($k['unit'] ?? 'number'))
            ->map(fn($g) => $g->sum(fn($k) => (float)($k['base_target'] ?? 0)));

        $myLinkageMap = collect($incomingLinkages)->map(function ($lnk) use ($mySubCatSums) {
            $target  = (float)($lnk['assigned_target'] ?? 0);
            $key     = ($lnk['sub_category'] ?? '') . '|' . ($lnk['unit'] ?? 'number');
            $covered = (float)($mySubCatSums->get($key, 0));
            $gap     = max(0, $target - $covered);
            $pct     = $target > 0 ? min(100, round($covered / $target * 100)) : 100;
            return array_merge($lnk, ['covered' => $covered, 'gap' => $gap, 'pct' => $pct, 'met' => $covered >= $target]);
        });

        $allKpisByEmployee = $kpiRows->groupBy('employee_id');
        $outgoingWithCoverage = collect($outgoingLinkages)->map(function ($lnk) use ($allKpisByEmployee) {
            $assigneeKpis = $allKpisByEmployee->get($lnk['assignee_id'], collect());
            $lnkUnit = $lnk['unit'] ?? 'number';
            $target  = (float)($lnk['assigned_target'] ?? 0);
            $covered = $assigneeKpis
                ->where('sub_category', $lnk['sub_category'])
                ->filter(fn($k) => ($k['unit'] ?? 'number') === $lnkUnit)
                ->sum(fn($k) => (float)($k['base_target'] ?? 0));
            $gap = max(0, $target - $covered);
            $pct = $target > 0 ? min(100, round($covered / $target * 100)) : 100;
            return array_merge($lnk, ['covered' => $covered, 'gap' => $gap, 'pct' => $pct, 'met' => $covered >= $target]);
        });

        $hasAnyLinkage   = $myLinkageMap->isNotEmpty() || $outgoingWithCoverage->isNotEmpty();
        $canAssignTarget = $role !== 'EXECUTIVE' && !empty($directReports);

        return Inertia::render('Linkages', [
            'user'                 => $user,
            'fy'                   => $fy,
            'directReports'        => $directReports,
            'myLinkageMap'         => $myLinkageMap->values()->all(),
            'outgoingWithCoverage' => $outgoingWithCoverage->values()->all(),
            'hasAnyLinkage'        => $hasAnyLinkage,
            'canAssignTarget'      => $canAssignTarget,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'assignee_id'     => 'required|string',
            'category'        => 'required|string',
            'sub_category'    => 'required|string',
            'assigned_target' => 'required|numeric|min:0',
            'unit'            => 'required|in:number,currency,percentage',
        ]);

        $user = $this->currentUser();
        $fy   = $this->currentFY();

        // Fetch assignee name
        $assignee = $this->supabase->first('employees', [
            'id'     => 'eq.' . $validated['assignee_id'],
            'select' => 'id,short_name,role',
        ]);
        if (!$assignee) return back()->with('error', 'Assignee not found.');

        $now = $this->nowMy();

        // Check if linkage already exists
        $existing = $this->supabase->first('kpi_linkages', [
            'company_code'   => 'eq.' . $user['company_code'],
            'financial_year' => 'eq.' . $fy,
            'assigner_id'    => 'eq.' . $user['id'],
            'assignee_id'    => 'eq.' . $validated['assignee_id'],
            'sub_category'   => 'eq.' . $validated['sub_category'],
        ]);

        if ($existing) {
            $this->supabase->safePatch('kpi_linkages', ['id' => 'eq.' . $existing['id']], [
                'assigned_target' => $validated['assigned_target'],
                'unit'            => $validated['unit'],
                'category'        => $validated['category'],
                'updated_at'      => $now,
            ]);
        } else {
            $this->supabase->safeInsert('kpi_linkages', [
                'company_code'    => $user['company_code'],
                'financial_year'  => $fy,
                'assigner_id'     => $user['id'],
                'assigner_name'   => $user['short_name'] ?? $user['full_name'] ?? 'Unknown',
                'assignee_id'     => $validated['assignee_id'],
                'assignee_name'   => $assignee['short_name'],
                'category'        => $validated['category'],
                'sub_category'    => $validated['sub_category'],
                'assigned_target' => $validated['assigned_target'],
                'unit'            => $validated['unit'],
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }

        return back()->with('success', 'Target linkage saved for ' . $assignee['short_name'] . '.');
    }

    public function destroy(string $id)
    {
        $user    = $this->currentUser();
        $linkage = $this->supabase->first('kpi_linkages', ['id' => 'eq.' . $id]);

        if (!$linkage || $linkage['assigner_id'] !== $user['id']) {
            return back()->with('error', 'Not authorized.');
        }

        $this->supabase->delete('kpi_linkages', ['id' => 'eq.' . $id]);
        return back()->with('success', 'Linkage removed.');
    }
}
