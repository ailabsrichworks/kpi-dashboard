<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    private string $currentFinancialYear = 'FY2026';

    public function index(SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login')->with('error', 'Sila login terlebih dahulu.');
        }

        $user = $this->getCurrentUser($supabase);

        $company = $supabase->get('companies', [
            'code' => 'eq.' . session('company_code'),
            'select' => '*'
        ])[0] ?? null;

        if ($company) {
            session([
                'company_display_name' => $company['display_name'] ?: $company['name'],
            ]);
        }

        if (!$user) {
            session()->flush();
            return redirect()->route('login')->with('error', 'Session tidak sah. Sila login semula.');
        }

        $companyCode = session('company_code');

        $department = $this->getUserDepartment($supabase, $user);
        $canSwitchDepartment = $this->canSwitchDepartment($user);

        $departments = $canSwitchDepartment
            ? $this->getAllDepartments($supabase, $companyCode)
            : [];

        $selectedDepartmentCode = $this->getSelectedDepartmentCode($user, $canSwitchDepartment);

        $visibleEmployeeIds = $this->getVisibleEmployeeIds(
            $supabase,
            $user,
            $selectedDepartmentCode,
            $companyCode
        );

        $kpis = $this->getKpis($supabase, $visibleEmployeeIds, $companyCode);

        $kpis = $this->attachQuartersToKpis($supabase, $kpis);
        $kpis = $this->attachEmployeeDataToKpis($supabase, $kpis, $user, $companyCode);
        $kpis = $this->attachHistoryToKpis($supabase, $kpis);

        $rankingResult       = $this->getCompanyDeptPerformance($supabase, $companyCode);
        $companyDeptRanking  = $rankingResult['depts'] ?? [];
        $companyTotalStaff   = $rankingResult['total_staff'] ?? 0;
        $companyTotalDepts   = $rankingResult['total_depts'] ?? 0;
        $allCompanyEmployees = $rankingResult['employees'] ?? [];

        // ── KPI LINKAGES (cascading targets) ────────────────────────────────
        // These are all independent of one another (different filters, no
        // shared data dependency), so they're fetched concurrently instead of
        // one after another — same requests, same results, just sent at once.
        $fy       = $this->currentFinancialYear;
        $userId   = $user['id'];
        $userRole = strtoupper(trim($user['role'] ?? ''));

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

        if ($userRole === 'SLT') {
            $batch['directReports'] = ['table' => 'employees', 'query' => [
                'company_code' => 'eq.' . $companyCode,
                'role'         => 'eq.VP',
                'is_active'    => 'eq.true',
                'select'       => 'id,short_name,role',
                'order'        => 'short_name.asc',
            ]];
        } elseif ($userRole === 'VP') {
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
        } elseif ($userRole === 'MANAGER') {
            $batch['directReports'] = ['table' => 'employees', 'query' => [
                'company_code' => 'eq.' . $companyCode,
                'manager_id'   => 'eq.' . $userId,
                'is_active'    => 'eq.true',
                'select'       => 'id,short_name,role',
                'order'        => 'short_name.asc',
            ]];
        }

        $batchResults     = $supabase->getMany($batch);
        $incomingLinkages = $batchResults['incoming'] ?? [];
        $outgoingLinkages = $batchResults['outgoing'] ?? [];

        $directReports = [];
        if ($userRole === 'SLT' || $userRole === 'MANAGER') {
            $directReports = $batchResults['directReports'] ?? [];
        } elseif ($userRole === 'VP') {
            $byVpId      = $batchResults['byVpId'] ?? [];
            $byReportsTo = $batchResults['byReportsTo'] ?? [];
            $drIds       = collect($byVpId)->pluck('id')->toArray();
            foreach ($byReportsTo as $r) {
                if (!in_array($r['id'], $drIds)) { $byVpId[] = $r; $drIds[] = $r['id']; }
            }
            $directReports = $byVpId;
        }

        $allEmployees = $allCompanyEmployees;
        $currentFinancialYear = $this->currentFinancialYear;

        // ─────────────────────────────────────────────────────────────────────
        // Ported verbatim from dashboard.blade.php's former @php block (Phase 4
        // Step 7a) — this is a pure relocation, not a rewrite. Every variable
        // below is passed to the view unchanged via compact() so the rendered
        // page's output stays byte-for-byte identical to before the move.
        // ─────────────────────────────────────────────────────────────────────

        // ── CORE ────────────────────────────────────────────────────────────────
        $role              = strtoupper(trim($user['role'] ?? ''));
        $currentUserId     = (string)($user['id'] ?? $user['employee_id'] ?? '');
        $currentUserName   = ($user['salutation'] ? $user['salutation'] . ' ' : '') . ($user['short_name'] ?? $user['full_name'] ?? $user['name'] ?? 'User');
        $currentDepartment = $user['department_code'] ?? '-';
        $currentFinancialYear = $currentFinancialYear ?? ('FY'.now()->year);
        $userPosition      = $user['position'] ?? $user['role'] ?? '-';

        $greetHour = now()->timezone('Asia/Kuala_Lumpur')->hour;
        $greeting  = $greetHour < 12 ? 'Good Morning' : ($greetHour < 18 ? 'Good Afternoon' : 'Good Evening');

        $isManager = in_array($role, ['SLT','VP','MANAGER']);
        $isSltOffice = in_array(strtoupper(trim($currentDepartment)), ['SLT OFFICE', 'BTS']);

        $kpiCollection = collect($kpis ?? []);

        // ── KPI SCORE ───────────────────────────────────────────────────────────
        $calculateKpiScore = function($kpi) {
            $quarters = collect($kpi['quarters'] ?? []);
            $qBase = 0; $qActual = 0;
            foreach (['Q1','Q2','Q3','Q4'] as $q) {
                $row = $quarters->firstWhere('quarter',$q) ?? [];
                $qBase   += max(0,(float)($row['quarter_target'] ?? 0));
                $qActual += max(0,(float)($row['quarter_actual'] ?? 0));
            }
            if ($qBase > 0) return round(($qActual/$qBase)*100,2);
            $base   = max(0,(float)($kpi['base_target'] ?? 0));
            $actual = max(0,(float)($kpi['actual_value'] ?? 0));
            return $base > 0 ? round(($actual/$base)*100,2) : 0;
        };

        $calculateWeightedScore = function($kpi) use($calculateKpiScore) {
            return round(((float)$calculateKpiScore($kpi) * max(0,(float)($kpi['weightage']??0)))/100,2);
        };

        // ── KPI ROWS ────────────────────────────────────────────────────────────
        $riskStatuses = ['at_risk','risk','in_trouble','critical'];
        $kpiRows = $kpiCollection->map(function($kpi) use($calculateKpiScore,$calculateWeightedScore,$riskStatuses) {
            $score     = $calculateKpiScore($kpi);
            $weightage = max(0,(float)($kpi['weightage'] ?? 0));
            $status    = strtolower($kpi['status'] ?? 'not_started');
            return array_merge($kpi,[
                '_score'           => $score,
                '_weightage'       => $weightage,
                '_weighted_score'  => $calculateWeightedScore($kpi),
                '_is_risk'         => in_array($status,$riskStatuses),
                '_employee_key'    => (string)($kpi['employee_id'] ?? 'unassigned'),
                '_employee_name'   => $kpi['owner_display_name'] ?? $kpi['employee_name'] ?? $kpi['owner_name'] ?? 'Unassigned',
                '_department_code' => $kpi['owner_department_code'] ?? $kpi['department_code'] ?? '-',
            ]);
        });

        // ── MY KPIs ─────────────────────────────────────────────────────────────
        $individualKpis = $kpiRows->filter(fn($k) => (string)($k['employee_id']??'') === $currentUserId);
        $individualPerformance = round($individualKpis->sum('_weighted_score'),2);
        $individualWeightage   = round($individualKpis->sum('_weightage'),2);
        $individualKpiCount    = $individualKpis->count();
        $myOnTrack    = $individualKpis->whereIn('status',['on_track','monitoring'])->count();
        $myAtRisk     = $individualKpis->whereIn('status',['at_risk','risk','in_trouble','critical'])->count();
        $myCompletedByQ = ['Q1'=>0,'Q2'=>0,'Q3'=>0,'Q4'=>0];
        $myTotalByQ     = ['Q1'=>0,'Q2'=>0,'Q3'=>0,'Q4'=>0];
        // Weighted progress (actual/target, scaled by each KPI's weightage) — distinct
        // from completion above, which only tracks whether a quarter was formally
        // signed off. A KPI can show real progress here while still 0% "completed".
        $myProgressByQ  = ['Q1'=>0,'Q2'=>0,'Q3'=>0,'Q4'=>0];
        foreach ($individualKpis as $kpi) {
            $weight = (float)($kpi['_weightage'] ?? 0);
            foreach (['Q1','Q2','Q3','Q4'] as $q) {
                $qr = collect($kpi['quarters'] ?? [])->firstWhere('quarter', $q);
                if ($qr) {
                    $myTotalByQ[$q]++;
                    if (($qr['status'] ?? '') === 'completed' && !empty($qr['completion_submitted_at'])) $myCompletedByQ[$q]++;

                    $qTarget = max(0,(float)($qr['quarter_target'] ?? 0));
                    $qActual = max(0,(float)($qr['quarter_actual'] ?? 0));
                    $qPct    = $qTarget > 0 ? ($qActual/$qTarget)*100 : 0;
                    $myProgressByQ[$q] += $qPct * $weight / 100;
                }
            }
        }
        $myProgressByQ = array_map(fn($v) => round(min($v,100),1), $myProgressByQ);

        // Up to 3 at-risk KPI titles for the "Needs Attention" callout.
        $myAtRiskKpis = $individualKpis
            ->whereIn('status', ['at_risk','risk','in_trouble','critical'])
            ->take(3)
            ->map(fn($k) => $k['kpi_title'] ?? $k['title'] ?? 'Untitled KPI')
            ->values();
        $myCompletedAnnual = $individualKpis->filter(function($kpi) {
            $qs = collect($kpi['quarters'] ?? []);
            return collect(['Q1','Q2','Q3','Q4'])->every(fn($q) => ($qs->firstWhere('quarter',$q)['status'] ?? '') === 'completed' && !empty($qs->firstWhere('quarter',$q)['completion_submitted_at'] ?? ''));
        })->count();
        $myCompleted  = array_sum($myCompletedByQ);
        $myTotalQuarters = array_sum($myTotalByQ);

        // ── CATEGORY GROUPS ─────────────────────────────────────────────────────
        $categoryOrder  = ['Financial','Growth & Customer','Initiatives','People'];
        $myKpisByCategory = $individualKpis->groupBy('category');
        $orderedCategoryGroups = collect();
        foreach ($categoryOrder as $cat) { if ($myKpisByCategory->has($cat)) $orderedCategoryGroups[$cat] = $myKpisByCategory->get($cat); }
        foreach ($myKpisByCategory as $cat => $items) { if (!in_array($cat,$categoryOrder)) $orderedCategoryGroups[$cat] = $items; }
        $myCategoryCounts = $orderedCategoryGroups->map(fn($items, $cat) => ['category' => $cat, 'count' => $items->count()])->values()->all();

        // ── STAFF BASE ROWS ──────────────────────────────────────────────────────
        // Use name as fallback key so people with null employee_id don't merge
        $kpiRowsKeyed = $kpiRows->map(function($kpi) {
            $empId   = (string)($kpi['employee_id'] ?? '');
            $empName = $kpi['_employee_name'] ?? '';
            $safeKey = $empId ?: ($empName ?: 'unassigned');
            return array_merge($kpi, ['_safe_key' => $safeKey]);
        });

        $staffBaseRows = $kpiRowsKeyed->groupBy('_safe_key')->map(function($items) {
            $first = $items->first();
            return [
                'employee_id'     => $first['employee_id'] ?? '',
                'name'            => $first['_employee_name'] ?? 'Unknown',
                'department_code' => $first['_department_code'] ?? '-',
                'role'            => $first['owner_role'] ?? $first['employee_role'] ?? $first['position'] ?? '-',
                'kpi_count'       => $items->count(),
                'weightage_total' => round($items->sum('_weightage'),2),
                'performance'     => round($items->sum('_weighted_score'),2),
                'risk_count'      => $items->where('_is_risk',true)->count(),
                'completed_count' => $items->sum(fn($k) => collect($k['quarters'] ?? [])->filter(fn($q) => ($q['status'] ?? '') === 'completed' && !empty($q['completion_submitted_at']))->count()),
                'on_track_count'  => $items->whereIn('status',['on_track','monitoring'])->count(),
            ];
        })->values();

        // ── PER-EMPLOYEE QUARTERLY SCORES ────────────────────────────────────────
        $empQuarterMap = [];
        foreach ($kpiRows as $kpi) {
            $empKey = $kpi['_employee_key'];
            $quarters = collect($kpi['quarters'] ?? []);
            $weight   = (float)($kpi['_weightage'] ?? 0);
            foreach (['Q1','Q2','Q3','Q4'] as $q) {
                $qRow    = $quarters->firstWhere('quarter',$q);
                if (!$qRow) continue;
                $qTarget = max(0,(float)($qRow['quarter_target'] ?? 0));
                $qActual = max(0,(float)($qRow['quarter_actual'] ?? 0));
                $qPct    = $qTarget > 0 ? ($qActual/$qTarget)*100 : 0;
                $empQuarterMap[$empKey][$q] = ($empQuarterMap[$empKey][$q] ?? 0) + round($qPct * $weight / 100, 3);
            }
        }

        // ── STAFF WITH QUARTERLY DATA ────────────────────────────────────────────
        $staffPerformanceRows = $staffBaseRows->map(function($staff) use($empQuarterMap) {
            $key = (string)($staff['employee_id'] ?? '');
            $q = $empQuarterMap[$key] ?? [];
            return array_merge($staff,[
                'q1' => round($q['Q1'] ?? 0,2),
                'q2' => round($q['Q2'] ?? 0,2),
                'q3' => round($q['Q3'] ?? 0,2),
                'q4' => round($q['Q4'] ?? 0,2),
            ]);
        })->values()->sortByDesc('performance');

        // ── ROLE HIERARCHY SORT ──────────────────────────────────────────────────
        $rolePriority = function($role) {
            return match(strtoupper(trim($role ?? ''))) {
                'SLT'       => 1,
                'VP'        => 2,
                'MANAGER'   => 3,
                'EXECUTIVE' => 4,
                default     => 5,
            };
        };

        // ── DEPARTMENT ROWS — all employees, KPI data merged in where available ──
        $kpiByEmpId = $kpiRows->groupBy(fn($k) => (string)($k['employee_id'] ?? ''));

        $deptRows = collect($allEmployees ?? [])->map(function($emp) use($kpiByEmpId, $empQuarterMap) {
            $empId   = (string)($emp['id'] ?? '');
            $empKpis = $kpiByEmpId->get($empId, collect());
            $q       = $empQuarterMap[$empId] ?? [];
            return [
                'employee_id'     => $empId,
                'name'            => $emp['short_name'] ?? $emp['full_name'] ?? 'Unknown',
                'department_code' => $emp['department_code'] ?? '-',
                'role'            => $emp['role'] ?? '-',
                'kpi_count'       => $empKpis->count(),
                'performance'     => round($empKpis->sum('_weighted_score'), 2),
                'risk_count'      => $empKpis->where('_is_risk', true)->count(),
                'q1'              => round($q['Q1'] ?? 0, 2),
                'q2'              => round($q['Q2'] ?? 0, 2),
                'q3'              => round($q['Q3'] ?? 0, 2),
                'q4'              => round($q['Q4'] ?? 0, 2),
            ];
        })->groupBy('department_code')->map(function($staff, $deptCode) use($rolePriority) {
            $cnt   = $staff->count();
            $bands = [0,0,0,0];
            foreach ($staff as $s) {
                $p = (float)$s['performance'];
                if ($p >= 90) $bands[0]++;
                elseif ($p >= 75) $bands[1]++;
                elseif ($p >= 50) $bands[2]++;
                else $bands[3]++;
            }
            $sortedStaff = $staff->sortBy(
                fn($s) => sprintf('%d_%s', $rolePriority($s['role']), strtolower($s['name'] ?? ''))
            )->values();
            return [
                'department_code' => $deptCode ?: '-',
                'staff_count'     => $cnt,
                'kpi_count'       => $staff->sum('kpi_count'),
                'performance'     => round($cnt > 0 ? $staff->avg('performance') : 0, 2),
                'risk_count'      => $staff->sum('risk_count'),
                'q1'              => round($cnt > 0 ? $staff->avg('q1') : 0, 2),
                'q2'              => round($cnt > 0 ? $staff->avg('q2') : 0, 2),
                'q3'              => round($cnt > 0 ? $staff->avg('q3') : 0, 2),
                'q4'              => round($cnt > 0 ? $staff->avg('q4') : 0, 2),
                'band_counts'     => $bands,
                'staff_list'      => $sortedStaff->toArray(),
            ];
        })->values()->sortByDesc('performance');

        // ── COMPANY TOTALS ───────────────────────────────────────────────────────
        $totalStaffCount    = $staffPerformanceRows->count();
        $totalKpisVisible   = $kpiCollection->count();
        $totalCompletedByQ = ['Q1'=>0,'Q2'=>0,'Q3'=>0,'Q4'=>0];
        $totalByQ          = ['Q1'=>0,'Q2'=>0,'Q3'=>0,'Q4'=>0];
        foreach ($kpiRows as $kpi) {
            foreach (['Q1','Q2','Q3','Q4'] as $q) {
                $qr = collect($kpi['quarters'] ?? [])->firstWhere('quarter', $q);
                if ($qr) {
                    $totalByQ[$q]++;
                    if (($qr['status'] ?? '') === 'completed' && !empty($qr['completion_submitted_at'])) $totalCompletedByQ[$q]++;
                }
            }
        }
        $totalCompletedAnnual = $kpiRows->filter(function($kpi) {
            $qs = collect($kpi['quarters'] ?? []);
            return collect(['Q1','Q2','Q3','Q4'])->every(fn($q) => ($qs->firstWhere('quarter',$q)['status'] ?? '') === 'completed' && !empty($qs->firstWhere('quarter',$q)['completion_submitted_at'] ?? ''));
        })->count();
        $companyDeptCount = $companyTotalDepts ?? count($companyDeptRanking ?? []);

        // ── MY DEPARTMENT SCORE (for the donut panel) ───────────────────────────
        $myDeptRow        = $deptRows->firstWhere('department_code', $currentDepartment);
        $myDeptPerformance = $myDeptRow ? (float)$myDeptRow['performance'] : 0;
        $myDeptBands      = $myDeptRow ? ($myDeptRow['band_counts'] ?? [0,0,0,0]) : [0,0,0,0];

        // ── DATA FOR JS CHARTS ───────────────────────────────────────────────────
        $deptChartData = $deptRows->map(fn($d) => [
            'code'    => $d['department_code'],
            'annual'  => $d['performance'],
            'q1'      => $d['q1'],
            'q2'      => $d['q2'],
            'q3'      => $d['q3'],
            'q4'      => $d['q4'],
            'bands'   => $d['band_counts'],
            'staff'   => $d['staff_count'],
            'at_risk' => $d['risk_count'],
        ])->values()->all();

        // ── LINKAGE DATA ─────────────────────────────────────────────────────────
        $incomingLinkages = collect($incomingLinkages ?? []);
        $outgoingLinkages = collect($outgoingLinkages ?? []);
        $directReports    = collect($directReports ?? []);

        // Key: "sub_category|unit" so RM totals never mix with % or number totals
        $mySubCatSums = $individualKpis
            ->groupBy(fn($k) => ($k['sub_category'] ?? '') . '|' . ($k['unit'] ?? 'number'))
            ->map(fn($g) => $g->sum(fn($k) => (float)($k['base_target'] ?? 0)));

        $myLinkageMap = $incomingLinkages->map(function($lnk) use($mySubCatSums) {
            $target  = (float)($lnk['assigned_target'] ?? 0);
            $key     = ($lnk['sub_category'] ?? '') . '|' . ($lnk['unit'] ?? 'number');
            $covered = (float)($mySubCatSums->get($key, 0));
            $gap     = max(0, $target - $covered);
            $pct     = $target > 0 ? min(100, round($covered / $target * 100)) : 100;
            return array_merge($lnk, ['covered'=>$covered,'gap'=>$gap,'pct'=>$pct,'met'=>$covered>=$target]);
        });

        $allKpisByEmployee = $kpiRows->groupBy('employee_id');
        $outgoingWithCoverage = $outgoingLinkages->map(function($lnk) use($allKpisByEmployee) {
            $assigneeKpis = $allKpisByEmployee->get($lnk['assignee_id'], collect());
            $lnkUnit = $lnk['unit'] ?? 'number';
            $target  = (float)($lnk['assigned_target'] ?? 0);
            $covered = $assigneeKpis
                ->where('sub_category', $lnk['sub_category'])
                ->filter(fn($k) => ($k['unit'] ?? 'number') === $lnkUnit)
                ->sum(fn($k) => (float)($k['base_target'] ?? 0));
            $gap  = max(0, $target - $covered);
            $pct  = $target > 0 ? min(100, round($covered / $target * 100)) : 100;
            return array_merge($lnk, ['covered'=>$covered,'gap'=>$gap,'pct'=>$pct,'met'=>$covered>=$target]);
        });

        $hasAnyLinkage   = $myLinkageMap->isNotEmpty() || $outgoingWithCoverage->isNotEmpty();
        $canAssignTarget = $role !== 'EXECUTIVE' && $directReports->isNotEmpty();
        $linkageTotalCount = $myLinkageMap->count() + $outgoingWithCoverage->count();
        $linkageGapCount   = $myLinkageMap->where('met', false)->count() + $outgoingWithCoverage->where('met', false)->count();

        return Inertia::render('Dashboard', [
            'user' => $user,
            'greeting' => $greeting,
            'currentFinancialYear' => $currentFinancialYear,
            'currentUserName' => $currentUserName,
            'userPosition' => $userPosition,
            'currentDepartment' => $currentDepartment,
            'isManager' => $isManager,
            'isSltOffice' => $isSltOffice,

            'individualKpiCount' => $individualKpiCount,
            'individualWeightage' => $individualWeightage,
            'individualPerformance' => $individualPerformance,
            'myOnTrack' => $myOnTrack,
            'myAtRisk' => $myAtRisk,
            'myAtRiskKpis' => $myAtRiskKpis->values()->all(),
            'myCompletedByQ' => $myCompletedByQ,
            'myTotalByQ' => $myTotalByQ,
            'myProgressByQ' => $myProgressByQ,
            'myCategoryCounts' => $myCategoryCounts,

            'companyDeptRanking' => $companyDeptRanking,
            'companyTotalStaff' => $companyTotalStaff,
            'companyDeptCount' => $companyDeptCount,
            'totalStaffCount' => $totalStaffCount,
            'totalCompletedByQ' => $totalCompletedByQ,
            'totalByQ' => $totalByQ,
            'totalCompletedAnnual' => $totalCompletedAnnual,
            'totalKpisVisible' => $totalKpisVisible,

            'deptRows' => $deptRows->values()->all(),
            'myDeptPerformance' => $myDeptPerformance,
            'myDeptBands' => $myDeptBands,
            'deptChartData' => $deptChartData,

            'hasAnyLinkage' => $hasAnyLinkage,
            'canAssignTarget' => $canAssignTarget,
            'linkageTotalCount' => $linkageTotalCount,
            'linkageGapCount' => $linkageGapCount,
        ]);
    }

    public function switchDepartment(Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login')->with('error', 'Sila login terlebih dahulu.');
        }

        $user = $this->getCurrentUser($supabase);

        if (!$user || !$this->canSwitchDepartment($user)) {
            abort(403, 'Anda tiada akses untuk tukar department.');
        }

        $request->validate([
            'department_code' => 'required|string',
        ]);

        session([
            'selected_department_code' => $request->department_code,
        ]);

        return back();
    }

    private function getCurrentUser(SupabaseService $supabase): ?array
    {
        $employees = $supabase->get('employees', [
            'id' => 'eq.' . session('employee_uuid'),
            'is_active' => 'eq.true',
            'select' => '*',
        ]);

        return $employees[0] ?? null;
    }

    private function getUserDepartment(SupabaseService $supabase, array $user): ?array
    {
        $departments = $supabase->get('departments', [
            'code' => 'eq.' . $user['department_code'],
            'select' => '*',
        ]);

        return $departments[0] ?? null;
    }

    private function canSwitchDepartment(array $user): bool
    {
        // BTS has cross-department admin/support access, same level as SLT
        // — including while impersonating someone via View As.
        return ($user['role'] ?? '') === 'SLT' || $this->isBtsSession();
    }

    private function getAllDepartments(SupabaseService $supabase, string $companyCode): array
    {
        return $supabase->get('departments', [
            'company_code' => 'eq.' . $companyCode,
            'select' => '*',
            'order' => 'name.asc',
        ]);
    }

    private function getSelectedDepartmentCode(array $user, bool $canSwitchDepartment): string
    {
        if ($canSwitchDepartment) {
            // Dept switcher UI removed — always show all departments, clear any stale session value
            session()->forget('selected_department_code');
            return 'ALL';
        }

        session()->forget('selected_department_code');

        return $user['department_code'];
    }

    private function getVisibleEmployeeIds(
        SupabaseService $supabase,
        array $user,
        string $selectedDepartmentCode,
        string $companyCode
    ): array {
        $role = $user['role'] ?? '';

        // BTS has cross-department admin/support access, same level as SLT
        // — including while impersonating someone via View As.
        if ($role === 'SLT' || $this->isBtsSession()) {
            $filters = [
                'company_code' => 'eq.' . $companyCode,
                'is_active' => 'eq.true',
                'select' => 'id',
            ];

            if ($selectedDepartmentCode !== 'ALL') {
                $filters['department_code'] = 'eq.' . $selectedDepartmentCode;
            }

            $employees = $supabase->get('employees', $filters);

            return collect($employees)->pluck('id')->toArray();
        }

        if (in_array($role, ['VP', 'MANAGER'])) {
            $employees = $supabase->get('employees', [
                'company_code' => 'eq.' . $companyCode,
                'is_active'    => 'eq.true',
                'select'       => 'id',
            ]);

            return collect($employees)->pluck('id')->toArray();
        }

        return [$user['id']];
    }

    private function getKpis(
        SupabaseService $supabase,
        array $visibleEmployeeIds,
        string $companyCode
    ): array {
        if (empty($visibleEmployeeIds)) {
            return [];
        }

        return $supabase->get('kpis', [
            'company_code' => 'eq.' . $companyCode,
            'employee_id' => 'in.(' . implode(',', $visibleEmployeeIds) . ')',
            'financial_year' => 'eq.' . $this->currentFinancialYear,
            'select' => '*',
            'order' => 'created_at.desc',
        ]);
    }

    private function attachQuartersToKpis(SupabaseService $supabase, array $kpis): array
    {
        if (empty($kpis)) {
            return [];
        }

        $kpiIds = collect($kpis)->pluck('id')->filter()->values()->toArray();

        if (empty($kpiIds)) {
            return $kpis;
        }

        $quarters = $supabase->get('kpi_quarters', [
            'kpi_id' => 'in.(' . implode(',', $kpiIds) . ')',
            'select' => '*',
            'order' => 'quarter.asc',
        ]);

        $quarterMap = collect($quarters)->groupBy('kpi_id');

        return collect($kpis)->map(function ($kpi) use ($quarterMap) {
            $kpiQuarters = $quarterMap
                ->get($kpi['id'], collect())
                ->sortBy('quarter')
                ->values();

            $kpi['quarters'] = $kpiQuarters->toArray();

            $kpi['quarter_total_target'] = $kpiQuarters->sum(function ($quarter) {
                return (float) ($quarter['quarter_target'] ?? 0);
            });

            $kpi['quarter_total_actual'] = $kpiQuarters->sum(function ($quarter) {
                return (float) ($quarter['quarter_actual'] ?? 0);
            });

            return $kpi;
        })->values()->toArray();
    }

    private function attachEmployeeDataToKpis(
        SupabaseService $supabase,
        array $kpis,
        array $user,
        string $companyCode
    ): array {
        if (empty($kpis)) {
            return [];
        }

        $employees = $supabase->get('employees', [
            'company_code' => 'eq.' . $companyCode,
            'is_active' => 'eq.true',
            'select' => 'id,employee_id,short_name,full_name,email,role,department_code',
        ]);

        $employeeMap = collect($employees)->keyBy('id');

        $creatorIds = collect($kpis)
            ->pluck('created_by')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $creators = [];

        if (!empty($creatorIds)) {
            $creators = $supabase->get('employees', [
                'id' => 'in.(' . implode(',', $creatorIds) . ')',
                'select' => 'id,employee_id,short_name,role,department_code'
            ]);
        }

        $creatorMap = collect($creators)->keyBy('id');

        return collect($kpis)->map(function ($kpi) use ($employeeMap, $user) {
            $owner = $employeeMap->get($kpi['employee_id']);

            $base = (float) ($kpi['base_target'] ?? 0);
            $stretch = (float) ($kpi['stretch_target'] ?? 0);

            $actual = isset($kpi['quarter_total_actual'])
                ? (float) $kpi['quarter_total_actual']
                : (float) ($kpi['actual_value'] ?? 0);

            $targetForAchievement = isset($kpi['quarter_total_target']) && (float) $kpi['quarter_total_target'] > 0
                ? (float) $kpi['quarter_total_target']
                : $base;

            if ($targetForAchievement <= 0) {
                $achievement = 0;
            } else {
                $achievement = round(($actual / $targetForAchievement) * 100, 2);
            }

            $achievement = min(max($achievement, 0), 200);

            $isSelf = ($kpi['employee_id'] ?? null) === ($user['id'] ?? null);

            $ownerName = $owner['short_name']
                ?? $owner['full_name']
                ?? 'Unknown';

            $kpi['is_self'] = $isSelf;
            $kpi['owner_name'] = $isSelf ? null : $ownerName;
            $kpi['owner_role'] = $owner['role'] ?? '-';

            $kpi['owner_display_name'] = $ownerName;
            $kpi['owner_department_code'] = $owner['department_code'] ?? $kpi['department_code'] ?? null;

            $kpi['base_target'] = $base;
            $kpi['stretch_target'] = $stretch;
            $kpi['actual_value'] = $actual;
            $kpi['achievement_percentage'] = $achievement;

            $kpi['status'] = $this->normalizeStatus($kpi['status'] ?? null);
            $kpi['unit'] = $kpi['unit'] ?? 'number';
            $kpi['remark'] = $kpi['remark'] ?? '-';

            return $kpi;
        })->values()->toArray();
    }

    private function attachHistoryToKpis(SupabaseService $supabase, array $kpis): array
    {
        if (empty($kpis)) {
            return [];
        }

        $kpiIds = collect($kpis)->pluck('id')->toArray();

        $histories = $supabase->get('kpi_histories', [
            'kpi_id' => 'in.(' . implode(',', $kpiIds) . ')',
            'select' => '*',
            'order' => 'created_at.desc',
        ]);

        $historyMap = collect($histories)->groupBy('kpi_id');

        return collect($kpis)->map(function ($kpi) use ($historyMap) {
            $histories = $historyMap->get($kpi['id'], collect())->values()->toArray();

            $kpi['histories'] = $histories;

            $latestHistory = $histories[0] ?? null;

            $kpi['last_edited_at'] = $latestHistory['created_at'] ?? $kpi['updated_at'] ?? null;
            $kpi['last_edited_by'] = $latestHistory['edited_by_name'] ?? null;

            return $kpi;
        })->values()->toArray();
    }

    private function normalizeStatus(?string $status): string
    {
        return match ($status) {
            'not_started' => 'not_started',
            'on_track', 'monitoring' => 'on_track',
            'at_risk', 'risk' => 'at_risk',
            'in_trouble', 'critical', 'off_track', 'overdue' => 'in_trouble',
            'completed' => 'completed',
            default => 'not_started',
        };
    }

    private function shouldShowOwnerColumn(array $kpis): bool
    {
        return collect($kpis)->where('is_self', false)->count() > 0;
    }

    private function calculateWeightageSummary(array $kpis): array
    {
        $totalWeightage = collect($kpis)->sum(function ($kpi) {
            return (float) ($kpi['weightage'] ?? 0);
        });

        return [
            'totalWeightage' => round($totalWeightage, 2),
            'isWeightageExceeded' => $totalWeightage > 100,
            'isWeightageComplete' => round($totalWeightage, 2) == 100,
        ];
    }

    private function getCompanyDeptPerformance(SupabaseService $supabase, string $companyCode): array
    {
        $employees = $supabase->get('employees', [
            'company_code' => 'eq.' . $companyCode,
            'is_active'    => 'eq.true',
            'select'       => 'id,short_name,full_name,role,department_code',
        ]);

        if (empty($employees)) return ['depts' => [], 'total_staff' => 0, 'total_depts' => 0];

        $empIds     = collect($employees)->pluck('id')->filter()->values()->toArray();
        $empDeptMap = collect($employees)->pluck('department_code', 'id');

        // All depts with actual employee counts (includes depts with no KPIs)
        $allDeptStaff = [];
        foreach ($employees as $emp) {
            $d = $emp['department_code'] ?? '-';
            $allDeptStaff[$d] = ($allDeptStaff[$d] ?? 0) + 1;
        }

        $kpis = $supabase->get('kpis', [
            'company_code'   => 'eq.' . $companyCode,
            'employee_id'    => 'in.(' . implode(',', $empIds) . ')',
            'financial_year' => 'eq.' . $this->currentFinancialYear,
            'select'         => 'id,employee_id,weightage,base_target,actual_value',
        ]);

        // Score accumulator per dept (employees with KPIs only)
        $deptScores = [];
        if (!empty($kpis)) {
            $kpiIds     = collect($kpis)->pluck('id')->filter()->values()->toArray();
            $quarters   = $supabase->get('kpi_quarters', [
                'kpi_id' => 'in.(' . implode(',', $kpiIds) . ')',
                'select' => 'kpi_id,quarter_target,quarter_actual',
            ]);
            $quarterMap = collect($quarters ?? [])->groupBy('kpi_id');

            $empScores = [];
            foreach ($kpis as $kpi) {
                $empId  = $kpi['employee_id'];
                $weight = (float)($kpi['weightage'] ?? 0);
                if ($weight <= 0) continue;

                $kpiQuarters = $quarterMap->get($kpi['id'], collect());
                $qTarget = $kpiQuarters->sum(fn($q) => max(0, (float)($q['quarter_target'] ?? 0)));
                $qActual = $kpiQuarters->sum(fn($q) => max(0, (float)($q['quarter_actual'] ?? 0)));

                if ($qTarget > 0) {
                    $pct = ($qActual / $qTarget) * 100;
                } else {
                    $base   = max(0, (float)($kpi['base_target']   ?? 0));
                    $actual = max(0, (float)($kpi['actual_value']   ?? 0));
                    $pct    = $base > 0 ? ($actual / $base) * 100 : 0;
                }
                $empScores[$empId] = ($empScores[$empId] ?? 0) + ($pct * $weight / 100);
            }

            foreach ($empScores as $empId => $score) {
                $d = $empDeptMap->get($empId, '-');
                $deptScores[$d]['total'] = ($deptScores[$d]['total'] ?? 0) + $score;
                $deptScores[$d]['count'] = ($deptScores[$d]['count'] ?? 0) + 1;
            }
        }

        // Build result for ALL departments — divide by total staff (including those with 0 KPIs)
        $result = [];
        foreach ($allDeptStaff as $deptCode => $staffCount) {
            $s = $deptScores[$deptCode] ?? null;
            $result[] = [
                'code'  => $deptCode,
                'score' => $s && $staffCount > 0 ? round($s['total'] / $staffCount, 2) : 0,
                'staff' => $staffCount,
            ];
        }

        usort($result, fn($a, $b) => $b['score'] <=> $a['score']);

        return [
            'depts'       => $result,
            'total_staff' => count($employees),
            'total_depts' => count($allDeptStaff),
            'employees'   => $employees,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | STAFF KPI DRILL-DOWN — SLT Office only
    |--------------------------------------------------------------------------
    */

    private function requireSltOffice(array $user): void
    {
        $dept = strtoupper(trim($user['department_code'] ?? ''));
        if (!in_array($dept, ['SLT OFFICE', 'BTS'])) {
            abort(403, 'This page is only accessible to SLT Office.');
        }
    }

    public function staffKpis(string $employeeId, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login')->with('error', 'Sila login terlebih dahulu.');
        }

        $user = $this->getCurrentUser($supabase);
        if (!$user) {
            session()->flush();
            return redirect()->route('login')->with('error', 'Session tidak sah. Sila login semula.');
        }

        $this->requireSltOffice($user);

        $companyCode = session('company_code');

        $staff = $supabase->get('employees', [
            'id'            => 'eq.' . $employeeId,
            'company_code'  => 'eq.' . $companyCode,
            'is_active'     => 'eq.true',
            'select'        => '*',
        ])[0] ?? null;

        if (!$staff) {
            abort(404, 'Staff not found.');
        }

        $kpis = $supabase->get('kpis', [
            'employee_id'    => 'eq.' . $employeeId,
            'company_code'   => 'eq.' . $companyCode,
            'financial_year' => 'eq.' . $this->currentFinancialYear,
            'select'         => '*',
            'order'          => 'created_at.desc',
        ]) ?? [];

        $kpiIds = collect($kpis)->pluck('id')->filter()->values()->toArray();

        $quarterMap = collect();
        if (!empty($kpiIds)) {
            $quarters = $supabase->get('kpi_quarters', [
                'kpi_id' => 'in.(' . implode(',', $kpiIds) . ')',
                'select' => '*',
            ]) ?? [];
            $quarterMap = collect($quarters)->groupBy('kpi_id');
        }

        $kpis = collect($kpis)->map(function ($kpi) use ($quarterMap) {
            $qs      = $quarterMap->get($kpi['id'], collect());
            $qTarget = $qs->sum(fn ($q) => max(0, (float) ($q['quarter_target'] ?? 0)));
            $qActual = $qs->sum(fn ($q) => max(0, (float) ($q['quarter_actual'] ?? 0)));

            $base   = max(0, (float) ($kpi['base_target']   ?? 0));
            $actual = max(0, (float) ($kpi['actual_value']  ?? 0));

            $target = $qTarget > 0 ? $qTarget : $base;
            $act    = $qTarget > 0 ? $qActual : $actual;

            $kpi['display_target'] = $target;
            $kpi['display_actual'] = $act;
            $kpi['progress_pct']   = $target > 0 ? round(($act / $target) * 100, 1) : 0;
            $kpi['quarters_filled'] = $qs->filter(fn ($q) => (float) ($q['quarter_actual'] ?? 0) > 0)->count();

            // Per-quarter detail (target, actual, progress, quarter title) for the compact quarter strip
            $kpi['quarters'] = collect(['Q1', 'Q2', 'Q3', 'Q4'])->map(function ($label) use ($qs) {
                $row    = $qs->firstWhere('quarter', $label);
                $target = max(0, (float) ($row['quarter_target'] ?? 0));
                $actual = max(0, (float) ($row['quarter_actual'] ?? 0));

                return [
                    'label'        => $label,
                    'quarter_title' => $row['quarter_title'] ?? null,
                    'target'       => $target,
                    'actual'       => $actual,
                    'progress_pct' => $target > 0 ? round(($actual / $target) * 100, 1) : 0,
                    'has_data'     => $row !== null,
                ];
            })->values()->all();

            return $kpi;
        })->values()->all();

        $staffDepartment = $supabase->get('departments', [
            'code'   => 'eq.' . ($staff['department_code'] ?? ''),
            'select' => '*',
        ])[0] ?? null;

        $totalWeight   = collect($kpis)->sum(fn ($k) => (float) ($k['weightage'] ?? 0));
        $weightedScore = collect($kpis)->sum(fn ($k) => (float) ($k['weightage'] ?? 0) > 0
            ? ($k['progress_pct'] * (float) ($k['weightage'] ?? 0) / 100)
            : 0);

        return Inertia::render('Dashboard/StaffKpis', [
            'user'                 => $user,
            'staff'                => $staff,
            'kpis'                 => $kpis,
            'departmentName'       => $staffDepartment['name'] ?? $staff['department_code'] ?? '-',
            'currentFinancialYear' => $this->currentFinancialYear,
            'totalWeight'          => round($totalWeight, 2),
            'weightedScore'        => round($weightedScore, 2),
        ]);
    }

    public function staffKpiDetail(string $employeeId, string $kpiId, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login')->with('error', 'Sila login terlebih dahulu.');
        }

        $user = $this->getCurrentUser($supabase);
        if (!$user) {
            session()->flush();
            return redirect()->route('login')->with('error', 'Session tidak sah. Sila login semula.');
        }

        $this->requireSltOffice($user);

        $companyCode = session('company_code');

        $staff = $supabase->get('employees', [
            'id'           => 'eq.' . $employeeId,
            'company_code' => 'eq.' . $companyCode,
            'is_active'    => 'eq.true',
            'select'       => '*',
        ])[0] ?? null;

        if (!$staff) {
            abort(404, 'Staff not found.');
        }

        $kpi = $supabase->get('kpis', [
            'id'           => 'eq.' . $kpiId,
            'employee_id'  => 'eq.' . $employeeId,
            'company_code' => 'eq.' . $companyCode,
            'select'       => '*',
        ])[0] ?? null;

        if (!$kpi) {
            abort(404, 'KPI not found.');
        }

        $quarters = $supabase->get('kpi_quarters', [
            'kpi_id' => 'eq.' . $kpiId,
            'select' => '*',
        ]) ?? [];

        $quarters = collect($quarters)->map(function ($q) {
            $target = max(0, (float) ($q['quarter_target'] ?? 0));
            $actual = max(0, (float) ($q['quarter_actual'] ?? 0));
            $q['progress_pct'] = $target > 0 ? round(($actual / $target) * 100, 1) : 0;
            return $q;
        })->sortBy('quarter')->values()->all();

        $filledQuarters = collect($quarters)->filter(
            fn ($q) => (float) ($q['quarter_actual'] ?? 0) > 0
        );

        $average = $filledQuarters->count() > 0
            ? round($filledQuarters->avg('progress_pct'), 1)
            : 0;

        return view('dashboard.staff-kpi-detail', [
            'user'                 => $user,
            'department'           => $this->getUserDepartment($supabase, $user),
            'staff'                => $staff,
            'kpi'                  => $kpi,
            'quarters'             => $quarters,
            'average'              => $average,
            'currentFinancialYear' => $this->currentFinancialYear,
        ]);
    }

    private function calculateSummary(array $kpis): array
    {
        $collection = collect($kpis);

        $totalWeighted = 0;
        $totalWeight = 0;

        foreach ($collection as $kpi) {
            $weight = (float) ($kpi['weightage'] ?? 0);
            $achievement = (float) ($kpi['achievement_percentage'] ?? 0);

            if ($weight <= 0) {
                continue;
            }

            $totalWeighted += $achievement * $weight;
            $totalWeight += $weight;
        }

        $overallScore = $totalWeight > 0
            ? round($totalWeighted / $totalWeight, 2)
            : 0;

        return [
            'overallScore' => $overallScore,
            'totalKpis' => $collection->count(),
            'completed' => $collection->where('status', 'completed')->count(),
            'onTrack' => $collection->where('status', 'on_track')->count(),
            'atRisk' => $collection->where('status', 'at_risk')->count(),
            'inTrouble' => $collection->where('status', 'in_trouble')->count(),
        ];
    }
}
