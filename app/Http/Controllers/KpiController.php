<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\SupabaseService;
use App\Services\ApprovalHierarchyService;
use App\Services\NotificationService;
use App\Services\QuarterOverrideService;

class KpiController extends Controller
{
    protected $supabase;
    protected ApprovalHierarchyService $hierarchyService;
    protected NotificationService $notifications;
    protected QuarterOverrideService $quarterOverrides;

    public function __construct(
        SupabaseService $supabase,
        ApprovalHierarchyService $hierarchyService,
        NotificationService $notifications,
        QuarterOverrideService $quarterOverrides
    ){
        $this->supabase = $supabase;
        $this->hierarchyService = $hierarchyService;
        $this->notifications = $notifications;
        $this->quarterOverrides = $quarterOverrides;
    }

    // Every approval-request insert site notifies the approver the same way —
    // one place to keep the message/link shape consistent. $requestId (when
    // known) deep-links straight to that card on the approval page instead of
    // dropping the approver on the generic list.
    private function notifyApprover(string $approverId, string $type, string $requesterName, string $kpiTitle, string $summary, ?string $requestId = null): void
    {
        $link = $requestId ? route('approval.index') . '?highlight=' . $requestId : route('approval.index');

        $this->notifications->notify(
            [$approverId],
            $type,
            ['name' => $requesterName],
            "{$requesterName} needs your approval",
            "{$summary}: {$kpiTitle}",
            $link
        );
    }

    private function currentFY(): string
    {
        return 'FY' . now()->year;
    }

    private function nowMy(): string
    {
        return now()->timezone('Asia/Kuala_Lumpur')->toDateTimeString();
    }

    /**
     * Q1/Q2 2026 stay updatable without an approval reason through 31 Jul
     * 2026 (mid-year appraisal grace period), even though their normal
     * end_date (31 Mar / 30 Jun) has already passed. From 1 Aug 2026 this
     * reverts to the standard past-quarter reason requirement on its own —
     * no manual toggle needed.
     */
    private function inQ1Q2GracePeriod(array $quarter): bool
    {
        return in_array($quarter['quarter'] ?? null, ['Q1', 'Q2'], true)
            && now('Asia/Kuala_Lumpur')->startOfDay()->lte(Carbon::parse('2026-07-31'));
    }

    private function currentUser(SupabaseService $supabase): array
    {
        $employeeUuid = session('employee_uuid');

        if (!$employeeUuid) {
            abort(403, 'Employee not logged in.');
        }

        $employees = $supabase->get('employees', [
            'id' => 'eq.' . $employeeUuid,
            'is_active' => 'eq.true',
            'select' => '*',
        ]);

        if (empty($employees)) {
            session()->flush();
            abort(403, 'Employee not found.');
        }

        return $employees[0];
    }

    private function permissionForRoleFromDb(SupabaseService $supabase, string $role): array
    {
        $permissions = $supabase->get('kpi_permissions', [
            'role' => 'eq.' . $role,
            'select' => '*',
        ]);

        if (empty($permissions)) {
            abort(403, 'KPI permission not configured for role: ' . $role);
        }

        return $permissions[0];
    }

    private function sidebarData(SupabaseService $supabase, array $user): array
    {
        $departmentFilters = [
            'select' => '*',
            'order' => 'name.asc',
        ];

        if (!empty($user['company_code'])) {
            $departmentFilters['company_code'] = 'eq.' . $user['company_code'];
        }

        $departments = $supabase->get('departments', $departmentFilters) ?? [];

        $role = strtoupper(trim($user['role'] ?? ''));

        // BTS has cross-department admin/support access, same level as SLT.
        $canSwitchDepartment = $role === 'SLT' || ($user['department_code'] ?? '') === 'BTS';

        $selectedDepartmentCode = session('selected_department_code')
            ?? $user['department_code']
            ?? null;

        $department = null;

        if ($selectedDepartmentCode) {
            $departmentResult = $supabase->get('departments', [
                'code' => 'eq.' . $selectedDepartmentCode,
                'select' => '*',
            ]);

            $department = $departmentResult[0] ?? null;
        }

        return [
            'departments' => $departments,
            'department' => $department,
            'canSwitchDepartment' => $canSwitchDepartment,
            'selectedDepartmentCode' => $selectedDepartmentCode,
        ];
    }

    public function index(SupabaseService $supabase)
    {
        try {
            return $this->indexInternal($supabase);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('KPI index: Supabase connection failed', ['error' => $e->getMessage()]);
            return response()->view('errors.service', ['message' => 'Cannot connect to database. Please try again in a moment.'], 503);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            \Log::error('KPI index: Supabase request failed', ['error' => $e->getMessage()]);
            return response()->view('errors.service', ['message' => 'Database error. Please try again.'], 503);
        }
    }

    private function indexInternal(SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);
        $permission = $this->permissionForRoleFromDb($supabase, $user['role']);
        $fy = $this->currentFY();

        $role = strtoupper(trim($user['role'] ?? ''));

        $employees = [];
        $kpis = [];

        if ($role === 'VP') {
            // These three reads don't depend on each other, so they're fetched
            // concurrently instead of one after another (same queries, same
            // results — just sent over the wire at the same time).
            $vpBatch = $supabase->getMany([
                'kpis' => ['table' => 'kpis', 'query' => [
                    'company_code'  => 'eq.' . $user['company_code'],
                    'department_code' => 'eq.' . $user['department_code'],
                    'financial_year' => 'eq.' . $fy,
                    'select' => '*',
                    'order' => 'created_at.desc',
                ]],
                'employees' => ['table' => 'employees', 'query' => [
                    'company_code'   => 'eq.' . $user['company_code'],
                    'department_code' => 'eq.' . $user['department_code'],
                    'is_active' => 'eq.true',
                    'select' => 'id,employee_id,short_name,role,department_code',
                ]],
            ]);

            $kpis      = $vpBatch['kpis'] ?? [];
            $employees = $vpBatch['employees'] ?? [];

        } elseif ($role === 'MANAGER') {
            // Independent reads — fetched concurrently, same as the VP branch above.
            $managerBatch = $supabase->getMany([
                'kpis' => ['table' => 'kpis', 'query' => [
                    'company_code'  => 'eq.' . $user['company_code'],
                    'department_code' => 'eq.' . $user['department_code'],
                    'financial_year' => 'eq.' . $fy,
                    'select' => '*',
                    'order' => 'created_at.desc',
                ]],
                'employees' => ['table' => 'employees', 'query' => [
                    'company_code'   => 'eq.' . $user['company_code'],
                    'department_code' => 'eq.' . $user['department_code'],
                    'is_active' => 'eq.true',
                    'select' => 'id,employee_id,short_name,role,department_code',
                ]],
            ]);

            $kpis      = $managerBatch['kpis'] ?? [];
            $employees = $managerBatch['employees'] ?? [];
        } else {

        /*
        |--------------------------------------------------------------------------
        | OWN KPI
        |--------------------------------------------------------------------------
        */

        $ownKpis = $supabase->get('kpis', [

            'employee_id' => 'eq.' . $user['id'],

            'financial_year' => 'eq.' . $fy,

            'select' => '*',

            'order' => 'created_at.desc',

        ]) ?? [];

        /*
        |--------------------------------------------------------------------------
        | ASSIGNED KPI IDS
        |--------------------------------------------------------------------------
        */

        $assignments = $supabase->get('kpi_assignments', [

            'assigned_employee_id' => 'eq.' . $user['id'],

            'select' => '*',

        ]) ?? [];

        $assignedKpiIds = collect($assignments)

            ->pluck('kpi_id')

            ->filter()

            ->unique()

            ->values()

            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | ASSIGNED KPI
        |--------------------------------------------------------------------------
        */

        $assignedKpis = [];

        if (!empty($assignedKpiIds)) {

            $assignedKpis = $supabase->get('kpis', [

                'id' => 'in.(' . implode(',', $assignedKpiIds) . ')',

                'financial_year' => 'eq.' . $fy,

                'select' => '*',

                'order' => 'created_at.desc',

            ]) ?? [];
        }

        /*
        |--------------------------------------------------------------------------
        | MARK ASSIGNED KPI
        |--------------------------------------------------------------------------
        */

        $assignmentStatusMap = collect($assignments)
            ->keyBy('kpi_id')
            ->map(fn($a) => $a['assignment_status'] ?? 'pending')
            ->toArray();

        $assignedKpis = collect($assignedKpis)

            ->map(function ($kpi) use ($assignmentStatusMap) {

                $kpi['is_assigned_kpi'] = true;
                $kpi['assignment_status_for_me'] = $assignmentStatusMap[$kpi['id']] ?? 'pending';

                return $kpi;

            })

            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | MARK OWN KPI
        |--------------------------------------------------------------------------
        */

        $ownKpis = collect($ownKpis)

            ->map(function ($kpi) {

                $kpi['is_assigned_kpi'] = false;

                return $kpi;

            })

            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | MERGE KPI
        |--------------------------------------------------------------------------
        */

        $kpis = collect([

            ...$ownKpis,

            ...$assignedKpis,

        ])

        /*
        |--------------------------------------------------------------------------
        | REMOVE DUPLICATE KPI
        |--------------------------------------------------------------------------
        */

        ->unique('id')

        ->values()

        ->toArray();

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEES
        |--------------------------------------------------------------------------
        */

        $employeeIds = collect($kpis)

            ->pluck('employee_id')

            ->filter()

            ->unique()

            ->values()

            ->toArray();

        $employees = [];

        if(!empty($employeeIds)){

            $employees = $supabase->get('employees',[

                'id'
                    => 'in.('
                    . implode(',',$employeeIds)
                    . ')',

                'select'
                    => 'id,employee_id,short_name,role,department_code'

            ]) ?? [];
        }
    }

        /*
        |--------------------------------------------------------------------------
        | ASSIGNMENT PROCESSING (ALL ROLES)
        | For ADMIN/MANAGER the role branch only fetches dept KPIs — assignments
        | are never marked. Run this for any role where $assignments wasn't set.
        |--------------------------------------------------------------------------
        */
        if (!isset($assignments)) {
            $assignments = $supabase->get('kpi_assignments', [
                'assigned_employee_id' => 'eq.' . $user['id'],
                'select' => '*',
            ]) ?? [];

            if (!empty($assignments)) {
                $assignedKpiIds = collect($assignments)
                    ->pluck('kpi_id')->filter()->unique()->values()->toArray();

                $assignmentStatusMap = collect($assignments)
                    ->keyBy('kpi_id')
                    ->map(fn($a) => $a['assignment_status'] ?? 'pending')
                    ->toArray();

                // Fetch assigned KPIs not already in $kpis (e.g. from another dept)
                $existingIds = collect($kpis)->pluck('id')->toArray();
                $missingIds  = array_values(array_diff($assignedKpiIds, $existingIds));

                if (!empty($missingIds)) {
                    $extraKpis = $supabase->get('kpis', [
                        'id'             => 'in.(' . implode(',', $missingIds) . ')',
                        'financial_year' => 'eq.' . $fy,
                        'select'         => '*',
                        'order'          => 'created_at.desc',
                    ]) ?? [];
                    $kpis = array_merge($kpis, $extraKpis);
                }

                // Mark is_assigned_kpi on relevant records
                $kpis = collect($kpis)->map(function ($kpi) use ($assignedKpiIds, $assignmentStatusMap, $user) {
                    if (
                        in_array($kpi['id'], $assignedKpiIds)
                        && ($kpi['employee_id'] ?? '') !== ($user['id'] ?? '')
                    ) {
                        $kpi['is_assigned_kpi'] = true;
                        $kpi['assignment_status_for_me'] = $assignmentStatusMap[$kpi['id']] ?? 'pending';
                    } else {
                        $kpi['is_assigned_kpi'] = $kpi['is_assigned_kpi'] ?? false;
                    }
                    return $kpi;
                })->values()->toArray();
            }
        }

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
                'select' => 'id,employee_id,short_name,role,department_code',
            ]) ?? [];
        }

        $creatorMap = collect($creators)->keyBy('id');

        // ── Batch all per-KPI sub-queries to avoid N+1 timeouts ──────────────
        $allKpiIds = collect($kpis)->pluck('id')->filter()->unique()->values()->toArray();

        $batchQuarters = $batchPendingActuals = $batchActualTimeline = [];
        $batchEditRequests = $batchDeleteRequests = [];

        if (!empty($allKpiIds)) {
            $inClause = 'in.(' . implode(',', $allKpiIds) . ')';

            $batchQuarters = $supabase->get('kpi_quarters', [
                'kpi_id' => $inClause, 'select' => '*', 'order' => 'quarter.asc',
            ]) ?? [];

            $batchPendingActuals = $supabase->get('kpi_update_approvals', [
                'kpi_id' => $inClause, 'status' => 'eq.pending', 'select' => '*',
            ]) ?? [];

            $batchActualTimeline = $supabase->get('kpi_update_approvals', [
                'kpi_id' => $inClause, 'select' => '*', 'order' => 'created_at.desc',
            ]) ?? [];

            try {
                $batchEditRequests = $supabase->get('kpi_target_change_requests', [
                    'kpi_id' => $inClause, 'select' => '*', 'order' => 'created_at.desc',
                ]) ?? [];
            } catch (\Throwable $e) {
                \Log::warning('KPI index: edit requests batch failed', ['error' => $e->getMessage()]);
            }

            $batchDeleteRequests = $supabase->get('kpi_delete_requests', [
                'kpi_id' => $inClause, 'select' => '*', 'order' => 'created_at.desc',
            ]) ?? [];
        }

        $quartersByKpi      = collect($batchQuarters)->groupBy('kpi_id');
        $pendingActualByKpi = collect($batchPendingActuals)->groupBy('kpi_id')
            ->map(fn($g) => $g->sortByDesc('created_at')->first());
        $timelineActByKpi   = collect($batchActualTimeline)->groupBy('kpi_id');
        $editReqByKpi       = collect($batchEditRequests)->groupBy('kpi_id');
        $deleteReqByKpi     = collect($batchDeleteRequests)->groupBy('kpi_id');

        // Batch-fetch approvers referenced in pending actuals
        $pendingApproverIds = collect($batchPendingActuals)
            ->pluck('approver_id')->filter()->unique()->values()->toArray();
        $approverMapForKpi = collect([]);
        if (!empty($pendingApproverIds)) {
            $approverRows = $supabase->get('employees', [
                'id' => 'in.(' . implode(',', $pendingApproverIds) . ')',
                'select' => 'id,short_name,role',
            ]) ?? [];
            $approverMapForKpi = collect($approverRows)->keyBy('id');
        }
        // ─────────────────────────────────────────────────────────────────────

        $maps = [
            'employeeMap'       => $employeeMap,
            'creatorMap'        => $creatorMap,
            'quartersByKpi'     => $quartersByKpi,
            'pendingActualByKpi'=> $pendingActualByKpi,
            'timelineActByKpi'  => $timelineActByKpi,
            'editReqByKpi'      => $editReqByKpi,
            'deleteReqByKpi'    => $deleteReqByKpi,
            'approverMapForKpi' => $approverMapForKpi,
        ];

        $kpis = collect($kpis)->map(fn($kpi) => $this->enrichKpiRecord($kpi, $maps))->toArray();

        $indexAssignedKpis = collect($kpis)
            ->filter(fn($k) => ($k['is_assigned_kpi'] ?? false) === true
                && ($k['employee_id'] ?? '') !== ($user['id'] ?? ''))
            ->values()
            ->toArray();

        // ── LINKAGE DATA FOR INDEX PAGE ──────────────────────────────────────
        $idxIncoming = collect($supabase->get('kpi_linkages', [
            'assignee_id'    => 'eq.' . $user['id'],
            'financial_year' => 'eq.' . $fy,
            'company_code'   => 'eq.' . $user['company_code'],
            'select'         => '*',
        ]) ?? []);

        // Key: "sub_category|unit" — never mix RM totals with % or number totals
        $myKpiSubSums = collect($kpis)
            ->where('employee_id', $user['id'])
            ->groupBy(fn($k) => ($k['sub_category'] ?? '') . '|' . ($k['unit'] ?? 'number'))
            ->map(fn($g) => $g->sum(fn($k) => (float)($k['base_target'] ?? 0)));

        $idxLinkageMap = [];
        foreach ($idxIncoming as $lnk) {
            $sub     = $lnk['sub_category'];
            $lnkUnit = $lnk['unit'] ?? 'number';
            $key     = $sub . '|' . $lnkUnit;
            $target  = (float)($lnk['assigned_target'] ?? 0);
            $covered = (float)($myKpiSubSums->get($key, 0));
            $idxLinkageMap[$sub] = [
                'target'        => $target,
                'covered'       => $covered,
                'gap'           => max(0, $target - $covered),
                'pct'           => $target > 0 ? min(100, round($covered / $target * 100)) : 100,
                'unit'          => $lnkUnit,
                'category'      => $lnk['category'] ?? '',
                'assigner_name' => $lnk['assigner_name'] ?? '-',
                'met'           => $covered >= $target,
            ];
        }

        return view('kpi.index', array_merge([

            'user' => $user,

            'permission' => $permission,

            'fy' => $fy,

            'employees' => $employees,

            'kpis' => $kpis,

            'indexAssignedKpis' => $indexAssignedKpis,

            'linkageMap' => $idxLinkageMap,

        ], $this->sidebarData($supabase, $user)));
    }

    public function create(Request $request, SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);

        $fy = $request->get(
            'financial_year',
            $this->currentFY()
        );

        /*
        |--------------------------------------------------------------------------
        | REPORTING STAFF
        |--------------------------------------------------------------------------
        | Ambil semua staff yang reporting kepada user sekarang
        | untuk digunakan dalam Assign KPI dropdown.
        |--------------------------------------------------------------------------
        */

        $reportingStaff = $supabase->get('employees', [
            'reports_to_id' => 'eq.' . $user['id'],
            'company_code' => 'eq.' . $user['company_code'],
            'is_active'    => 'eq.true',

            'select' => implode(',', [
                'id',
                'employee_id',
                'short_name',
                'role'
            ]),

            'order' => 'short_name.asc',
        ]) ?? [];

        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | MY ASSIGNMENTS
        |--------------------------------------------------------------------------
        */

        $myAssignments = collect($supabase->get(
            'kpi_assignments',
            [
                'assigned_employee_id'
                    => 'eq.' . $user['id'],

                'select'
                    => '*',
            ]
        ) ?? [])
            ->filter(fn($a) => ($a['owner_employee_id'] ?? '') !== ($user['id'] ?? ''))
            ->values()
            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | OWNER INFO
        |--------------------------------------------------------------------------
        */

        $ownerIds = collect($myAssignments)
            ->pluck('owner_employee_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        $owners = [];

        if(!empty($ownerIds)){
            $owners = $supabase->get(
                'employees',
                [
                    'id'
                        => 'in.('
                        . implode(',', $ownerIds)
                        . ')',

                    'select'
                        => 'id,short_name,role'
                ]
            ) ?? [];
        }

        $ownerMap = collect($owners) ->keyBy('id');

        /*
        |--------------------------------------------------------------------------
        | ASSIGNED KPI DETAILS
        |--------------------------------------------------------------------------
        */

        $assignedKpiIds = collect($myAssignments)
            ->pluck('kpi_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        $assignedKpis = [];

        if(!empty($assignedKpiIds)){
            $assignedKpis = $supabase->get(
                'kpis',
                [
                    'id'
                        => 'in.('
                        . implode(',',$assignedKpiIds)
                        . ')',

                    'financial_year'
                        => 'eq.' . $fy,

                    'select'
                        => '*'
                ]
            ) ?? [];
        }

        /*
        |--------------------------------------------------------------------------
        | ENRICH ASSIGNMENTS
        |--------------------------------------------------------------------------
        */

        $myAssignments = collect($myAssignments)
            ->map(function($assignment) use ($ownerMap){
                $owner =
                    $ownerMap->get(
                        $assignment['owner_employee_id']
                    );

                $assignment['owner_name']
                    = $owner['short_name']
                    ?? 'Unknown';

                $assignment['owner_role']
                    = $owner['role']
                    ?? '-';

                return $assignment;

            })

            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | ASSIGNMENT CARDS
        |--------------------------------------------------------------------------
        */

        $assignmentCards = [];

        foreach($myAssignments as $assignment){

            $kpi = collect($assignedKpis)
                ->firstWhere(
                    'id',
                    $assignment['kpi_id']
                );

            if(!$kpi){
                continue;
            }

            $owner =
                $ownerMap->get(
                    $assignment['owner_employee_id']
                );

            /*
            |--------------------------------------------------------------------------
            | LOAD QUARTERS
            |--------------------------------------------------------------------------
            */

            $quarters = $supabase->get(
                'kpi_quarters',
                [
                    'kpi_id'
                        => 'eq.' . $kpi['id'],

                    'select'
                        => '*',

                    'order'
                        => 'quarter.asc',
                ]
            ) ?? [];

            $assignmentCards[] = [

                'assignment_id'
                    => $assignment['id'],

                'owner_id'
                    => $assignment['owner_employee_id'],

                'owner_name'
                    => $owner['short_name'] ?? '-',

                'owner_role'
                    => $owner['role'] ?? '-',

                'status'
                    => $assignment['assignment_status']
                    ?? 'pending',

                'kpi_id'
                    => $kpi['id'],

                'category'
                    => $kpi['category']
                    ?? '-',

                'sub_category'
                    => $kpi['sub_category']
                    ?? '-',

                'kpi_title'
                    => $kpi['kpi_title']
                    ?? '-',

                'kpi_description'
                    => $kpi['kpi_description']
                    ?? '-',

                'unit'
                    => $kpi['unit']
                    ?? '-',

                'base_target'
                    => $kpi['base_target']
                    ?? 0,

                'stretch_target'
                    => $kpi['stretch_target']
                    ?? 0,

                'weightage'
                    => $kpi['weightage']
                    ?? 0,

                'actual_value'
                    => $kpi['actual_value']
                    ?? 0,

                'remark'
                    => $kpi['remark']
                    ?? '-',

                'quarters'
                    => $quarters,

                'created_at'
                    => $assignment['created_at']
                    ?? null,

            ];

        }

        $assignmentCards = collect($assignmentCards)
            ->sortByDesc('created_at')
            ->values()
            ->toArray();

        $assignmentGroups =
            collect($assignmentCards)
                ->groupBy('owner_id')
                ->map(function ($items) {
                    return [
                        'owner_id' =>
                            $items->first()['owner_id'],

                        'owner_name' =>
                            $items->first()['owner_name'],

                        'owner_role' =>
                            $items->first()['owner_role'],

                        'kpis' =>
                            $items
                                ->sortByDesc('created_at')
                                ->values()
                                ->toArray()
                    ];
                })
                ->values()
                ->toArray();

        $pendingAssignments =
            collect($myAssignments)
                ->where(
                    'assignment_status',
                    'pending'
                )
                ->count();

        $acceptedAssignments =
            collect($myAssignments)
                ->where(
                    'assignment_status',
                    'accepted'
                )
                ->count();

        $rejectedAssignments =
            collect($myAssignments)
                ->where(
                    'assignment_status',
                    'rejected'
                )
                ->count();

        // ── LINKAGE DATA ─────────────────────────────────────────────────────
        // What targets have been assigned TO me by my superior
        $incomingLinkages = collect($supabase->get('kpi_linkages', [
            'assignee_id'    => 'eq.' . $user['id'],
            'financial_year' => 'eq.' . $fy,
            'company_code'   => 'eq.' . $user['company_code'],
            'select'         => '*',
        ]) ?? []);

        // My existing KPIs this FY — to compute sub-category coverage
        $myExistingKpis = collect($supabase->get('kpis', [
            'employee_id'    => 'eq.' . $user['id'],
            'financial_year' => 'eq.' . $fy,
            'company_code'   => 'eq.' . $user['company_code'],
            'select'         => 'sub_category,base_target',
        ]) ?? []);

        // Key: "sub_category|unit" — never mix RM totals with % or number totals
        $mySubCatSums = $myExistingKpis
            ->groupBy(fn($k) => ($k['sub_category'] ?? '') . '|' . ($k['unit'] ?? 'number'))
            ->map(fn($g) => $g->sum(fn($k) => (float)($k['base_target'] ?? 0)));

        // Build linkage warning map: sub_category => [target, covered, gap, unit, assigner_name]
        $linkageMap = [];
        foreach ($incomingLinkages as $lnk) {
            $sub     = $lnk['sub_category'];
            $lnkUnit = $lnk['unit'] ?? 'number';
            $key     = $sub . '|' . $lnkUnit;
            $covered = (float)($mySubCatSums->get($key, 0));
            $target  = (float)($lnk['assigned_target'] ?? 0);
            $linkageMap[$sub] = [
                'target'        => $target,
                'covered'       => $covered,
                'gap'           => max(0, $target - $covered),
                'pct'           => $target > 0 ? min(100, round($covered / $target * 100)) : 100,
                'unit'          => $lnkUnit,
                'category'      => $lnk['category'] ?? '',
                'assigner_name' => $lnk['assigner_name'] ?? '-',
                'met'           => $covered >= $target,
            ];
        }

        // Targets I've assigned to my direct reports (for dashboard assign panel)
        $outgoingLinkages = collect($supabase->get('kpi_linkages', [
            'assigner_id'    => 'eq.' . $user['id'],
            'financial_year' => 'eq.' . $fy,
            'company_code'   => 'eq.' . $user['company_code'],
            'select'         => '*',
        ]) ?? []);

        return view('kpi.create', array_merge([
            'user'                => $user,
            'fy'                  => $fy,
            'reportingStaff'      => $reportingStaff,
            'myAssignments'       => $myAssignments,
            'assignmentCards'     => $assignmentCards,
            'assignmentGroups'    => $assignmentGroups,
            'pendingAssignments'  => $pendingAssignments,
            'acceptedAssignments' => $acceptedAssignments,
            'rejectedAssignments' => $rejectedAssignments,
            'linkageMap'          => $linkageMap,
            'outgoingLinkages'    => $outgoingLinkages,
            'incomingLinkages'    => $incomingLinkages,
        ], $this->sidebarData($supabase, $user)));
    }

    public function store(Request $request, SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);
        $permission = $this->permissionForRoleFromDb($supabase, $user['role']);

        if (!$permission['can_create']) {
            abort(403, 'You cannot create KPI.');
        }

        $validated = $request->validate([
            'financial_year' => 'nullable|string',
            'category' => 'required|string|max:255',
            'sub_category' => 'required|string|max:255',
            'kpi_title' => 'required|string|max:255',
            'kpi_description' => 'nullable|string',
            'unit' => 'required|string|in:number,currency,percentage',
            'base_target' => 'required|numeric|min:0',
            'stretch_target' => 'required|numeric|min:0',
            'actual_value' => 'nullable|numeric',
            'status' => 'required|string|in:not_started,on_track,at_risk,in_trouble,completed',
            'remark' => 'nullable|string',
            'assigned_employee_id' => 'nullable|string',

            'quarters' => 'nullable|array',
            'quarters.*.quarter' => 'nullable|string|in:Q1,Q2,Q3,Q4',
            'quarters.*.quarter_target' => 'nullable|numeric|min:0',
            'quarters.*.start_date' => 'nullable|date',
            'quarters.*.end_date' => 'nullable|date',
            'quarters.*.remark' => 'nullable|string',
            'quarters.*.quarter_title' => 'nullable|string|max:255',
            'quarters.*.quarter_description' => 'nullable|string',
            'quarters.*.status' => 'nullable|string|in:not_started,on_track,at_risk,in_trouble,completed',
        ]);

        if ((float) $validated['stretch_target'] < (float) $validated['base_target']) {
            return back()
                ->withErrors(['stretch_target' => 'Stretch must be greater than or equal to Base.'])
                ->withInput();
        }

        $quarterDateError = $this->validateQuarterDates($validated['quarters'] ?? []);

        if ($quarterDateError) {
            return back()
                ->withErrors($quarterDateError)
                ->withInput();
        }

        $fy = $validated['financial_year'] ?? $this->currentFY();

        $actualValue =
            $validated['actual_value']
            ?? 0;

        $achievement = $this->calculateAchievement(
            $validated['base_target'],
            $validated['stretch_target'],
            $actualValue
        );

        $payload = [
            'employee_id' => $user['id'],
            'department_code' => $user['department_code'],
            'company_code' => $user['company_code'],
            'created_by' => $user['id'],

            'financial_year' => $fy,
            'category' => $validated['category'],
            'sub_category' => $validated['sub_category'],
            'kpi_title' => $validated['kpi_title'],
            'kpi_description' => $validated['kpi_description'] ?? null,
            'unit' => $validated['unit'],
            'base_target' => $validated['base_target'],
            'stretch_target' => $validated['stretch_target'],
            'actual_value' => $actualValue,
            'achievement_percentage' => $achievement,
            'status' => $this->normalizeStatus($validated['status']),
            'remark' => $validated['remark'] ?? null,

            'created_at' => $this->nowMy(),
            'updated_at' => $this->nowMy(),
        ];

        $createdKpi = $supabase->insert('kpis', $payload);

        $kpiId = $createdKpi[0]['id'] ?? null;

        if (!$kpiId) {
            $latestKpi = $supabase->get('kpis', [
                'employee_id' => 'eq.' . $user['id'],
                'department_code' => 'eq.' . $user['department_code'],
                'financial_year' => 'eq.' . $fy,
                'kpi_title' => 'eq.' . $validated['kpi_title'],
                'select' => '*',
                'order' => 'created_at.desc',
                'limit' => '1',
            ]);

            $kpiId = $latestKpi[0]['id'] ?? null;
        }

        if (!$kpiId) {
            return back()
                ->withErrors(['kpi' => 'KPI created, but system failed to get KPI ID for quarter creation.'])
                ->withInput();
        }

        $this->upsertQuarters($supabase, $kpiId, $validated['quarters'] ?? []);

        if (!empty($validated['assigned_employee_id'])) {

            $existingAssignment = $supabase->get('kpi_assignments', [

                'kpi_id' => 'eq.' . $kpiId,

                'assigned_employee_id' => 'eq.' . $validated['assigned_employee_id'],

                'select' => '*',

            ]) ?? [];

            if (empty($existingAssignment)) {
                $supabase->safeInsert(
                    'kpi_assignments',
                    [
                        'kpi_id' => $kpiId,
                        'owner_employee_id' => $user['id'],
                        'assigned_employee_id' => $validated['assigned_employee_id'],
                        'assignment_status' => 'pending',
                        'created_at' => $this->nowMy(),
                    ]
                );

            }

        }

        return redirect()
            ->route('kpi.index')
            ->with('success', 'KPI created successfully.');
    }

    public function update(Request $request, $id, SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);
        $permission = $this->permissionForRoleFromDb($supabase, $user['role']);

        if (!$permission['can_update']) {
            abort(403, 'You cannot update KPI.');
        }

        $oldKpi = $this->findKpiOrFail($supabase, $id);

        /*
        |--------------------------------------------------------------------------
        | PREVENT DIRECT TARGET EDIT
        |--------------------------------------------------------------------------
        */

        if(
            $request->filled('base_target')
            ||
            $request->filled('stretch_target')
        ){
            abort(403,
                'Target changes require approval request.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | STRICT WEIGHTAGE OWNERSHIP
        |--------------------------------------------------------------------------
        |
        | User can ONLY update own KPI weightage.
        | Prevent manager / VP / forged request manipulation.
        |
        */

        if (
            request()->has('weightage')
            && (($oldKpi['employee_id'] ?? '') !== ($user['id'] ?? ''))
        ) {
            abort(403, 'You can only manage your own KPI weightage.');
        }

        if (!$this->canEditKpi($user, $oldKpi)) {
            abort(403, 'You cannot update this KPI.');
        }

        $validated = $request->validate([
            'category' => 'required|string',
            'sub_category' => 'required|string',
            'kpi_title' => 'required|string|max:255',
            'kpi_description' => 'nullable|string',
            'unit' => 'required|string|in:number,currency,percentage',
            'actual_value' => 'required|numeric|min:0',
            'status' => 'required|string|in:not_started,on_track,at_risk,in_trouble,completed',
            'remark' => 'nullable|string',
            'weightage' => 'nullable|numeric|min:0|max:100',

            'quarters' => 'nullable|array',
            'quarters.*.quarter' => 'nullable|string|in:Q1,Q2,Q3,Q4',
            'quarters.*.start_date' => 'nullable|date',
            'quarters.*.end_date' => 'nullable|date',
            'quarters.*.remark' => 'nullable|string',
            'quarters.*.quarter_title' => 'nullable|string|max:255',
            'quarters.*.quarter_description' => 'nullable|string',
            'quarters.*.status' => 'nullable|string|in:not_started,on_track,at_risk,in_trouble,completed',
        ]);

        $quarterDateError = $this->validateQuarterDates($validated['quarters'] ?? []);

        if ($quarterDateError) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $quarterDateError], 422);
            }
            return back()->withErrors($quarterDateError)->withInput();
        }

        $achievement = $this->calculateAchievement(
            $oldKpi['base_target'] ?? 0,
            $oldKpi['stretch_target'] ?? 0,
            $validated['actual_value']
        );

        $updateData = [
            'category' => $validated['category'],
            'sub_category' => $validated['sub_category'],
            'kpi_title' => $validated['kpi_title'],
            'kpi_description' => $validated['kpi_description'] ?? null,
            'base_target' => $oldKpi['base_target'],
            'stretch_target' => $oldKpi['stretch_target'],
            'unit' => $validated['unit'],
            'actual_value' => $validated['actual_value'],
            'achievement_percentage' => $achievement,
            'status' => $this->normalizeStatus($validated['status']),
            'remark' => $validated['remark'] ?? null,
            'updated_at' => $this->nowMy(),
            'weightage' => $validated['weightage'] ?? ($oldKpi['weightage'] ?? 0),
        ];

        if (!$supabase->safePatch('kpis', ['id' => 'eq.' . $id], $updateData)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to update KPI.'], 500);
            }
            return back()->withErrors(['kpi' => 'Failed to update KPI.'])->withInput();
        }

        $this->upsertQuarters($supabase, $id, $validated['quarters'] ?? []);

        $this->recordKpiHistory($supabase, $oldKpi, $updateData, $id, $user);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'KPI updated successfully.']);
        }

        return redirect()
            ->route('kpi.index')
            ->with('success', 'KPI updated successfully.');
    }

    public function inlineUpdate(Request $request, string $id)
    {
        $user = $this->currentUser($this->supabase);
        $kpi  = $this->findKpiOrFail($this->supabase, $id);

        if (!$this->canEditKpi($user, $kpi)) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $validated = $request->validate([
            'kpi_title'       => 'nullable|string|max:255',
            'kpi_description' => 'nullable|string',
        ]);

        $payload = ['updated_at' => $this->nowMy()];
        if (isset($validated['kpi_title']))       $payload['kpi_title']       = $validated['kpi_title'];
        if (array_key_exists('kpi_description', $validated)) $payload['kpi_description'] = $validated['kpi_description'];

        if (!$this->supabase->safePatch('kpis', ['id' => 'eq.' . $id], $payload)) {
            return response()->json(['success' => false, 'message' => 'Failed to update KPI.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'KPI updated.']);
    }

    public function inlineUpdateQuarter(Request $request, string $id)
    {
        $user    = $this->currentUser($this->supabase);
        $quarter = $this->supabase->first('kpi_quarters', ['id' => 'eq.' . $id, 'select' => '*']);

        if (!$quarter) {
            return response()->json(['success' => false, 'message' => 'Quarter not found.'], 404);
        }

        $kpi = $this->findKpiOrFail($this->supabase, $quarter['kpi_id']);

        if (!$this->canEditKpi($user, $kpi)) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $validated = $request->validate([
            'quarter_title'       => 'nullable|string|max:255',
            'quarter_description' => 'nullable|string',
            'status'              => 'nullable|in:not_started,on_track,at_risk,in_trouble,completed',
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date',
        ]);

        $payload = ['updated_at' => $this->nowMy()];
        if (isset($validated['quarter_title']))       $payload['quarter_title']       = $validated['quarter_title'];
        if (array_key_exists('quarter_description', $validated)) $payload['quarter_description'] = $validated['quarter_description'];
        if (isset($validated['status']))              $payload['status']              = $this->normalizeStatus($validated['status']);
        if (isset($validated['start_date']))          $payload['start_date']          = $validated['start_date'];
        if (isset($validated['end_date']))            $payload['end_date']            = $validated['end_date'];

        if (!$this->supabase->safePatch('kpi_quarters', ['id' => 'eq.' . $id], $payload)) {
            return response()->json(['success' => false, 'message' => 'Failed to update quarter.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Quarter updated.']);
    }

    public function completeQuarter(Request $request, string $id)
    {
        $user    = $this->currentUser($this->supabase);
        $quarter = $this->supabase->first('kpi_quarters', ['id' => 'eq.' . $id, 'select' => '*']);

        if (!$quarter) {
            return response()->json(['success' => false, 'message' => 'Quarter not found.'], 404);
        }

        $kpi = $this->findKpiOrFail($this->supabase, $quarter['kpi_id']);

        if (!$this->canEditKpi($user, $kpi)) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $validated = $request->validate([
            'completion_review' => 'required|string|min:10|max:2000',
            'proof_files'       => 'required|array|min:1|max:5',
            'proof_files.*'     => 'file|mimes:jpeg,png,webp,gif,pdf|max:5120',
        ]);

        $proofFiles = []; // [{url, name, type}, …]
        $proofUrl   = null;

        foreach ($request->file('proof_files', []) as $file) {
            $origName = $file->getClientOriginalName();
            $ext      = $file->getClientOriginalExtension();
            $mime     = $file->getMimeType();
            $path     = 'quarters/' . $id . '/' . uniqid('proof_', true) . '.' . $ext;
            $contents = file_get_contents($file->getRealPath());

            try {
                $url = $this->supabase->uploadToStorage('kpi-proofs', $path, $contents, $mime);
            } catch (\Throwable $e) {
                Log::error('completeQuarter: storage upload failed', ['error' => $e->getMessage()]);
                return response()->json(['success' => false, 'message' => 'Failed to upload ' . $origName . '.'], 500);
            }

            $proofFiles[] = ['url' => $url, 'name' => $origName, 'type' => $mime];
            if (!$proofUrl) $proofUrl = $url; // first file kept for legacy field
        }

        // Store completion data in quarter, set pending_completion status
        $quarterPayload = [
            'status'                   => 'pending_completion',
            'completion_review'        => $validated['completion_review'],
            'completion_submitted_at'  => $this->nowMy(),
            'completion_submitted_by'  => $user['id'],
            'completion_proof_urls'    => json_encode($proofFiles),
            'updated_at'               => $this->nowMy(),
        ];
        if ($proofUrl) $quarterPayload['completion_proof_url'] = $proofUrl;

        if (!$this->supabase->safePatch('kpi_quarters', ['id' => 'eq.' . $id], $quarterPayload)) {
            return response()->json(['success' => false, 'message' => 'Failed to save completion.'], 500);
        }

        // Find approver — if none, auto-complete without approval
        $approverId = $this->hierarchyService->getApproverId($user);

        if (!$approverId) {
            $this->supabase->safePatch('kpi_quarters', ['id' => 'eq.' . $id], [
                'status'     => 'completed',
                'updated_at' => $this->nowMy(),
            ]);
            return response()->json([
                'success'      => true,
                'message'      => 'Quarter marked as completed.',
                'status'       => 'completed',
                'proof_url'    => $proofUrl,
                'proof_files'  => $proofFiles,
            ]);
        }

        // Create completion approval request
        try {
            $completionInsert = $this->supabase->insert('kpi_update_approvals', [
                'kpi_id'            => $quarter['kpi_id'],
                'quarter'           => $quarter['quarter'],
                'quarter_id'        => $id,
                'requested_actual'  => $quarter['quarter_actual'] ?? 0,
                'old_actual'        => $quarter['quarter_actual'] ?? 0,
                'quarter_target'    => $quarter['quarter_target'] ?? 0,
                'reason'            => '[[COMPLETION]]' . $validated['completion_review'],
                'attachment_url'    => $proofUrl,
                'attachment_urls'   => json_encode($proofFiles),
                'requested_by'      => $user['id'],
                'requested_by_name' => $user['short_name'] ?? $user['full_name'] ?? 'Unknown',
                'approver_id'       => $approverId,
                'status'            => 'pending',
                'created_at'        => $this->nowMy(),
            ]);
        } catch (\Throwable $e) {
            Log::error('completeQuarter: approval insert failed', ['error' => $e->getMessage()]);
            $completionInsert = null;
        }

        $this->notifyApprover(
            $approverId,
            'kpi_completion_approval',
            $user['short_name'] ?? $user['full_name'] ?? 'Unknown',
            $kpi['kpi_title'] ?? 'a KPI',
            'Quarter completion needs your approval',
            $completionInsert[0]['id'] ?? null
        );

        return response()->json([
            'success'     => true,
            'message'     => 'Completion submitted for approval.',
            'status'      => 'pending_completion',
            'proof_url'   => $proofUrl,
            'proof_files' => $proofFiles,
        ]);
    }

    /**
     * Delete one completion-proof attachment from a quarter.
     *
     * Only allowed while the completion is still pending_completion: the
     * pending approval already references the exact set of proof files
     * that were submitted, so removing one file out from under it would
     * leave the approver reviewing evidence that no longer matches what's
     * stored. Rather than patch the pending approval's attachment list in
     * place, the whole pending completion request is cancelled — the quarter
     * reverts to on_track and the owner can resubmit with a clean set of
     * files.
     */
    public function deleteProofFile(Request $request, string $id)
    {
        $user    = $this->currentUser($this->supabase);
        $quarter = $this->supabase->first('kpi_quarters', ['id' => 'eq.' . $id, 'select' => '*']);

        if (!$quarter) {
            return response()->json(['success' => false, 'message' => 'Quarter not found.'], 404);
        }

        $kpi = $this->findKpiOrFail($this->supabase, $quarter['kpi_id']);

        if (!$this->canEditKpi($user, $kpi)) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        if (($quarter['status'] ?? '') !== 'pending_completion') {
            return response()->json([
                'success' => false,
                'message' => 'Attachments can only be removed while the completion is pending approval.',
            ], 422);
        }

        $validated = $request->validate([
            'url' => 'required|string',
        ]);

        $proofFiles = [];
        if (!empty($quarter['completion_proof_urls'])) {
            $decoded = json_decode($quarter['completion_proof_urls'], true);
            if (is_array($decoded)) $proofFiles = $decoded;
        }
        if (!$proofFiles && !empty($quarter['completion_proof_url'])) {
            $proofFiles = [['url' => $quarter['completion_proof_url']]];
        }

        $matches = array_filter($proofFiles, fn($f) => ($f['url'] ?? null) === $validated['url']);
        if (!$matches) {
            return response()->json(['success' => false, 'message' => 'Attachment not found.'], 404);
        }

        // Best-effort — the DB record (cleared below regardless) is the
        // source of truth for what the UI shows, so a storage hiccup here
        // isn't fatal to the cancellation itself.
        try {
            $this->deleteProofObjectFromStorage($validated['url']);
        } catch (\Throwable $e) {
            Log::error('deleteProofFile: storage delete failed', ['error' => $e->getMessage()]);
        }

        $approval = $this->supabase->first('kpi_update_approvals', [
            'quarter_id' => 'eq.' . $id,
            'status'     => 'eq.pending',
            'select'     => '*',
        ]);

        if ($approval && str_starts_with($approval['reason'] ?? '', '[[COMPLETION]]')) {
            $this->supabase->safePatch('kpi_update_approvals', ['id' => 'eq.' . $approval['id']], [
                'status'           => 'rejected',
                'rejected_by'      => $user['id'],
                'rejected_by_name' => $user['short_name'] ?? $user['full_name'] ?? 'Unknown',
                'rejected_at'      => $this->nowMy(),
                'rejection_reason' => 'Cancelled by requester — attachment removed.',
                'approver_remark'  => 'Cancelled by requester — attachment removed.',
                'is_viewed'        => true,
                'viewed_at'        => $this->nowMy(),
            ]);
        }

        $quarterPayload = [
            'status'                   => 'on_track',
            'completion_review'        => null,
            'completion_proof_url'     => null,
            'completion_proof_urls'    => null,
            'completion_submitted_at'  => null,
            'completion_submitted_by'  => null,
            'updated_at'               => $this->nowMy(),
        ];

        if (!$this->supabase->safePatch('kpi_quarters', ['id' => 'eq.' . $id], $quarterPayload)) {
            return response()->json(['success' => false, 'message' => 'Failed to update quarter.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted — the pending completion request was cancelled.',
            'status'  => 'on_track',
        ]);
    }

    private function deleteProofObjectFromStorage(string $url): void
    {
        $marker = '/storage/v1/object/public/kpi-proofs/';
        $pos    = strpos($url, $marker);
        if ($pos === false) return;

        $path = substr($url, $pos + strlen($marker));
        if ($path === '') return;

        $this->supabase->deleteFromStorage('kpi-proofs', $path);
    }

    public function destroy(string $id, SupabaseService $supabase)
    {
        abort(403,
            'Direct KPI deletion is disabled. Approval required.'
        );
    }

    public function switchDepartment(Request $request)
    {
        $request->validate([
            'department_code' => 'required|string',
        ]);

        session([
            'selected_department_code' => $request->department_code,
        ]);

        return back();
    }

    private function enrichKpiRecord(array $kpi, array $maps): array
    {
        $employeeMap       = $maps['employeeMap'];
        $creatorMap        = $maps['creatorMap'];
        $quartersByKpi     = $maps['quartersByKpi'];
        $pendingActualByKpi= $maps['pendingActualByKpi'];
        $timelineActByKpi  = $maps['timelineActByKpi'];
        $editReqByKpi      = $maps['editReqByKpi'];
        $deleteReqByKpi    = $maps['deleteReqByKpi'];
        $approverMapForKpi = $maps['approverMapForKpi'];

        $employee = $employeeMap->get($kpi['employee_id']);
        $creator  = $creatorMap->get($kpi['created_by'] ?? null);

        $kpi['employee_name']   = $employee['short_name'] ?? 'Unassigned';
        $kpi['employee_role']   = $employee['role'] ?? '-';
        $kpi['employee_code']   = $employee['employee_id'] ?? '-';
        $kpi['department_code'] = $kpi['department_code'] ?? ($employee['department_code'] ?? '-');
        $kpi['created_by_name'] = $creator['short_name'] ?? '-';
        $kpi['created_by_role'] = $creator['role'] ?? '-';

        $quarters = $quartersByKpi->get($kpi['id'], collect([]))->values()->toArray();

        $kpi['quarters']             = $quarters;
        $kpi['quarter_total_target'] = collect($quarters)->sum(fn($q) => (float)($q['quarter_target'] ?? 0));
        $quarterTotalActual          = collect($quarters)->sum(fn($q) => (float)($q['quarter_actual'] ?? 0));
        $kpi['quarter_total_actual'] = $quarterTotalActual;
        $kpi['actual_value']         = $quarterTotalActual;
        $kpi['quarter_overall_progress'] = $kpi['quarter_total_target'] > 0
            ? round(($kpi['quarter_total_actual'] / $kpi['quarter_total_target']) * 100, 2)
            : 0;

        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $currentQuarter = collect($quarters)->first(
            fn($q) => !empty($q['start_date']) && !empty($q['end_date'])
                && $q['start_date'] <= $today && $q['end_date'] >= $today
        );
        // Falls back to a BTS-issued override (QuarterOverrideController) when
        // no quarter is open by its own dates -- so the UI labels the actually-
        // editable quarter as "current", not whichever one the calendar says.
        if (!$currentQuarter && !empty($kpi['financial_year'])) {
            $currentQuarter = collect($quarters)->first(
                fn($q) => $this->quarterOverrides->isActive($kpi['financial_year'], $q['quarter'] ?? '')
            );
        }
        $kpi['current_quarter']          = $currentQuarter['quarter'] ?? '-';
        $kpi['current_quarter_end_date'] = $currentQuarter['end_date'] ?? null;

        $latestActualRequest = $pendingActualByKpi->get($kpi['id']);
        $kpi['has_pending_actual_request'] = !empty($latestActualRequest);
        $kpi['actual_request_status']      = $latestActualRequest['status'] ?? null;

        $approver = null;
        if (!empty($latestActualRequest['approver_id'])) {
            $approver = $approverMapForKpi->get($latestActualRequest['approver_id']);
        }
        $kpi['approver_name'] = $approver['short_name'] ?? '-';
        $kpi['approver_role'] = $approver['role'] ?? '-';

        $timeline = [];

        foreach ($timelineActByKpi->get($kpi['id'], collect([]))->values() as $row) {
            $status = $row['status'] ?? 'pending';
            $approver = $status === 'approved'
                ? ($row['approved_by_name'] ?? $row['approver_name'] ?? null)
                : ($row['approver_name'] ?? null);
            $timeline[] = [
                'type'     => 'Actual Update',
                'status'   => $status,
                'by'       => $row['requested_by_name'] ?? null,
                'date'     => $row['created_at'] ?? null,
                'approver' => $approver,
            ];
        }

        foreach ($editReqByKpi->get($kpi['id'], collect([]))->values() as $row) {
            $timeline[] = [
                'type'         => 'Target Change Requested',
                'status'       => 'requested',
                'date'         => $row['created_at'] ?? null,
                'by'           => $row['requested_by_name'] ?? null,
                'base_from'    => ($row['field_name'] ?? null) === 'base_target'    ? ($row['old_value'] ?? 0) : ($row['old_base_target'] ?? 0),
                'base_to'      => ($row['field_name'] ?? null) === 'base_target'    ? ($row['requested_value'] ?? 0) : ($row['new_base_target'] ?? 0),
                'stretch_from' => ($row['field_name'] ?? null) === 'stretch_target' ? ($row['old_value'] ?? 0) : ($row['old_stretch_target'] ?? 0),
                'stretch_to'   => ($row['field_name'] ?? null) === 'stretch_target' ? ($row['requested_value'] ?? 0) : ($row['new_stretch_target'] ?? 0),
                'reason'       => $row['reason'] ?? null,
                'approver'     => $row['approver_name'] ?? null,
            ];
            if (($row['status'] ?? '') === 'approved') {
                $timeline[] = [
                    'type'     => 'Approved',
                    'status'   => 'approved',
                    'date'     => $row['approved_at'] ?? $row['updated_at'] ?? null,
                    'approver' => $row['approved_by_name'] ?? $row['approver_name'] ?? null,
                ];
            }
            if (($row['status'] ?? '') === 'rejected') {
                $timeline[] = [
                    'type'     => 'Rejected',
                    'status'   => 'rejected',
                    'date'     => $row['rejected_at'] ?? $row['updated_at'] ?? null,
                    'approver' => $row['rejected_by_name'] ?? $row['approver_name'] ?? null,
                    'reason'   => $row['rejection_reason'] ?? $row['reject_reason'] ?? null,
                ];
            }
        }

        foreach ($deleteReqByKpi->get($kpi['id'], collect([]))->values() as $row) {
            $timeline[] = [
                'type'     => 'Delete Request',
                'status'   => $row['status'] ?? 'pending',
                'by'       => $row['requested_by_name'] ?? null,
                'date'     => $row['created_at'] ?? null,
                'approver' => $row['approver_name'] ?? null,
            ];
        }

        $kpi['approval_timeline'] = collect($timeline)->sortByDesc('date')->values()->toArray();

        $kpi['has_pending_delete_request'] = $deleteReqByKpi->get($kpi['id'], collect([]))
            ->contains(fn($r) => ($r['status'] ?? '') === 'pending');

        $kpi['has_pending_edit_request'] = $editReqByKpi->get($kpi['id'], collect([]))
            ->contains(fn($r) => ($r['status'] ?? '') === 'pending');

        return $kpi;
    }

    private function canEditKpi(array $user, array $kpi): bool
    {
        $role = strtoupper(trim($user['role'] ?? ''));

        /*
        |--------------------------------------------------------------------------
        | BTS (cross-department/company admin — full edit access everywhere,
        | including while impersonating someone via View As)
        |--------------------------------------------------------------------------
        */

        if ($this->isBtsSession()) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | ADMIN / SLT
        |--------------------------------------------------------------------------
        */

        if ($role === 'SLT') {

            return ($user['company_code'] ?? null)
                === ($kpi['company_code'] ?? null);
        }

        /*
        |--------------------------------------------------------------------------
        | VP / MANAGER
        |--------------------------------------------------------------------------
        */

        if (in_array($role, ['VP', 'MANAGER'])) {

            return ($user['department_code'] ?? null)
                === ($kpi['department_code'] ?? null);
        }

        /*
        |--------------------------------------------------------------------------
        | EXECUTIVE
        |--------------------------------------------------------------------------
        */

        if ($role === 'EXECUTIVE') {

            return (
                ($user['id'] ?? null)
                ===
                ($kpi['employee_id'] ?? null)
            );
        }

        return false;
    }

    private function upsertQuarters(SupabaseService $supabase, string $kpiId, array $quarters): void
    {
        $year = (int) now('Asia/Kuala_Lumpur')->year;

        $defaultDates = [
            'Q1' => ['start' => $year . '-01-01', 'end' => $year . '-03-31'],
            'Q2' => ['start' => $year . '-04-01', 'end' => $year . '-06-30'],
            'Q3' => ['start' => $year . '-07-01', 'end' => $year . '-09-30'],
            'Q4' => ['start' => $year . '-10-01', 'end' => $year . '-12-31'],
        ];

        foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $quarterLabel) {
            $quarter = $quarters[$quarterLabel] ?? [];

            unset(
                $quarter['quarter_actual']
            );

            $existingQuarter = $supabase->get('kpi_quarters', [
                'kpi_id' => 'eq.' . $kpiId,
                'quarter' => 'eq.' . $quarterLabel,
                'select' => '*',
                'limit' => 1,
            ]);

            $currentQuarter = $existingQuarter[0] ?? null;

            $payload = [

                'kpi_id' => $kpiId,

                'quarter' => $quarterLabel,

                'quarter_title'
                    => $quarter['quarter_title']
                    ?? ($quarterLabel . ' Plan'),

                'quarter_description'
                    => $quarter['quarter_description']
                    ?? null,

                'quarter_target'
                    => isset($quarter['quarter_target'])
                    && $quarter['quarter_target'] !== ''
                    ? (float)$quarter['quarter_target']
                    : ($currentQuarter['quarter_target'] ?? 0),

                'start_date' => !empty($quarter['start_date'])
                    ? $quarter['start_date']
                    : $defaultDates[$quarterLabel]['start'],

                'end_date' => !empty($quarter['end_date'])
                    ? $quarter['end_date']
                    : $defaultDates[$quarterLabel]['end'],

                'status'
                    => $this->normalizeStatus(
                        $quarter['status']
                        ??
                        $currentQuarter['status']
                        ??
                        'not_started'
                    ),

                'remark'
                    => $quarter['remark']
                    ?? null,

                'updated_at'
                    => $this->nowMy(),

            ];

            $payload['quarter_actual']
                = $currentQuarter['quarter_actual']
                ?? 0;

            if (!empty($existingQuarter)) {
                $supabase->safePatch('kpi_quarters', [
                    'id' => 'eq.' . $existingQuarter[0]['id'],
                ], $payload);
            } else {
                $payload['created_at'] = $this->nowMy();
                $supabase->safeInsert(
                    'kpi_quarters',
                    $payload
                );
            }
        }
    }

    private function validateQuarterDates(array $quarters): ?array
    {
        $year = now('Asia/Kuala_Lumpur')->year;

        $ranges = [

            'Q1' => [
                'start' => "$year-01-01",
                'end'   => "$year-03-31",
            ],

            'Q2' => [
                'start' => "$year-04-01",
                'end'   => "$year-06-30",
            ],

            'Q3' => [
                'start' => "$year-07-01",
                'end'   => "$year-09-30",
            ],

            'Q4' => [
                'start' => "$year-10-01",
                'end'   => "$year-12-31",
            ],

        ];

        foreach (['Q1','Q2','Q3','Q4'] as $quarterLabel) {

            $quarter =
                $quarters[$quarterLabel]
                ?? [];

            $startDate =
                $quarter['start_date']
                ?? null;

            $endDate =
                $quarter['end_date']
                ?? null;

            if(
                empty($startDate)
                ||
                empty($endDate)
            ){
                continue;
            }

            if(
                $endDate < $startDate
            ){
                return [

                    "quarters.$quarterLabel.end_date" =>

                    "$quarterLabel end date must be after start date."

                ];
            }

            $allowedStart =
                $ranges[$quarterLabel]['start'];

            $allowedEnd =
                $ranges[$quarterLabel]['end'];

            if(

                $startDate < $allowedStart
                ||

                $startDate > $allowedEnd
                ||

                $endDate < $allowedStart
                ||

                $endDate > $allowedEnd

            ){

                return [

                    "quarters.$quarterLabel.start_date" =>

                    "$quarterLabel timeline must stay within "
                    .
                    $allowedStart
                    .
                    " until "
                    .
                    $allowedEnd

                ];
            }
        }

        return null;
    }

    private function findKpiOrFail(SupabaseService $supabase, string $id): array
    {
        $kpi = $supabase->get('kpis', [
            'id' => 'eq.' . $id,
            'select' => '*',
        ])[0] ?? null;

        if (!$kpi) {
            abort(404, 'KPI not found.');
        }

        return $kpi;
    }

    private function calculateAchievement($baseTarget, $stretchTarget, $actualValue): float
    {
        $base = max(0, (float) ($baseTarget ?? 0));
        $stretch = max($base, (float) ($stretchTarget ?? 0));
        $actual = max(0, (float) ($actualValue ?? 0));

        if ($base <= 0) {
            return 0;
        }

        if ($actual <= $base) {
            return round(($actual / $base) * 100, 2);
        }

        if ($stretch > $base) {
            return round(min(200, 100 + (($actual - $base) / ($stretch - $base)) * 100), 2);
        }

        return 100;
    }

    public function weightage(SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);
        $permission = $this->permissionForRoleFromDb(
                $supabase,
                $user['role']
        );

        $fy = $this->currentFY();

        $employees = [
                [
                    'id' => $user['id'],
                    'short_name' => $user['short_name']
                ]
        ];

        $kpis = $supabase->get('kpis', [
            'employee_id' => 'eq.' . $user['id'],
            'financial_year' => 'eq.' . $this->currentFY(),
            'select' => '*',
            'order' => 'created_at.desc',
        ]) ?? [];

        /*
            Load quarter data for score calculation
        */

        $kpis = collect($kpis)->map(function ($kpi) use ($supabase) {

            $quarters = $supabase->get('kpi_quarters', [
                'kpi_id' => 'eq.' . $kpi['id'],
                'select' => '*',
                'order' => 'quarter.asc',
            ]) ?? [];

            $kpi['quarters'] = $quarters;

            return $kpi;

        })->toArray();

        $kpiCountByUser = collect($kpis)
            ->groupBy('employee_id')
            ->map(fn($items) => count($items))
            ->toArray();

        $kpiCountByDepartment = collect($kpis)
            ->groupBy('department_code')
            ->map(fn($items) => count($items))
            ->toArray();

        $approver = app(\App\Services\ApprovalHierarchyService::class)
            ->getApprover($user);

        $myKpiIds = collect($kpis)->pluck('id')->filter()->values()->toArray();

        $pendingWeightageRequests = [];

        if(!empty($myKpiIds)){
            try {
                $pendingRows = $supabase->get('kpi_target_change_requests', [
                    'requested_by' => 'eq.' . $user['id'],
                    'status'       => 'eq.pending',
                ]) ?? [];
                $pendingWeightageRequests = collect($pendingRows)
                    ->filter(fn($r) => str_starts_with($r['reason'] ?? '', '[[WC]]'))
                    ->map(function($r){
                        $r['reason']        = substr($r['reason'], 6);
                        $r['old_weightage'] = $r['old_base_target'] ?? 0;
                        $r['new_weightage'] = $r['new_base_target'] ?? 0;
                        return $r;
                    })
                    ->keyBy('kpi_id')
                    ->toArray();
            } catch (\Throwable $e) {
                \Log::warning('weightage: pending requests query failed', ['error' => $e->getMessage()]);
            }
        }

        return view('kpi.weightage', array_merge([

            'user'                     => $user,
            'permission'               => $permission,
            'fy'                       => $fy,
            'employees'                => $employees,
            'kpis'                     => $kpis,
            'kpiCountByUser'           => $kpiCountByUser,
            'kpiCountByDepartment'     => $kpiCountByDepartment,
            'approver'                 => $approver,
            'pendingWeightageRequests' => $pendingWeightageRequests,

        ], $this->sidebarData($supabase, $user)));
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


    private function recordKpiHistory(
        SupabaseService $supabase,
        array $oldKpi,
        array $newData,
        string $kpiId,
        array $user
    ): void {
        $fieldsToTrack = [
            'category',
            'sub_category',
            'kpi_title',
            'kpi_description',
            'base_target',
            'stretch_target',
            'actual_value',
            'unit',
            'status',
            'remark',
            'weightage',
        ];

        foreach ($fieldsToTrack as $field) {
            $oldValue = (string) ($oldKpi[$field] ?? '');
            $newValue = (string) ($newData[$field] ?? '');

            if ($oldValue !== $newValue) {
                $supabase->safeInsert('kpi_histories', [
                    'kpi_id' => $kpiId,
                    'edited_by' => $user['id'] ?? null,
                    'edited_by_name' => $user['short_name'] ?? 'Unknown',
                    'field_name' => $field,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'created_at' => $this->nowMy(),
                ]);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | APPLY KPI TEMPLATE TO ALL EMPLOYEES IN DEPARTMENT
    |--------------------------------------------------------------------------
    */

    public function applyTemplate(Request $request, SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);
        $fy   = $this->currentFY();
        $dept = $user['department_code'];

        // Fetch templates for this department
        $templates = $supabase->get('kpi_templates', [
            'department_code' => 'eq.' . $dept,
            'financial_year'  => 'eq.' . $fy,
            'select'          => '*',
            'order'           => 'sort_order.asc',
        ]) ?? [];

        if (empty($templates)) {
            return response()->json(['error' => 'No templates defined for department ' . $dept . '.'], 404);
        }

        // All active employees in this department
        $employees = $supabase->get('employees', [
            'company_code'    => 'eq.' . $user['company_code'],
            'department_code' => 'eq.' . $dept,
            'is_active'       => 'eq.true',
            'select'          => 'id,employee_id,short_name,role',
        ]) ?? [];

        // Filter to specific employees if the caller supplied a list
        $requestedIds = $request->input('employee_ids', []);
        if (!empty($requestedIds)) {
            $employees = array_filter($employees, fn($e) => in_array($e['id'], $requestedIds));
        }

        $created = 0;
        $skipped = 0;
        $now     = $this->nowMy();

        foreach ($employees as $emp) {
            foreach ($templates as $tpl) {
                // Avoid duplicates — check by title + employee + FY
                $exists = $supabase->get('kpis', [
                    'employee_id'    => 'eq.' . $emp['id'],
                    'financial_year' => 'eq.' . $fy,
                    'kpi_title'      => 'eq.' . $tpl['kpi_title'],
                    'select'         => 'id',
                    'limit'          => '1',
                ]);

                if (!empty($exists)) {
                    $skipped++;
                    continue;
                }

                $kpiPayload = [
                    'employee_id'            => $emp['id'],
                    'department_code'        => $dept,
                    'company_code'           => $user['company_code'],
                    'created_by'             => $user['id'],
                    'financial_year'         => $fy,
                    'category'               => $tpl['category'],
                    'sub_category'           => $tpl['sub_category'],
                    'kpi_title'              => $tpl['kpi_title'],
                    'kpi_description'        => $tpl['kpi_description'],
                    'unit'                   => $tpl['unit'],
                    'base_target'            => 0,
                    'stretch_target'         => 0,
                    'actual_value'           => 0,
                    'achievement_percentage' => 0,
                    'status'                 => 'not_started',
                    'created_at'             => $now,
                    'updated_at'             => $now,
                ];

                $inserted = $supabase->insert('kpis', $kpiPayload);
                $kpiId    = $inserted[0]['id'] ?? null;

                if (!$kpiId) {
                    $fallback = $supabase->get('kpis', [
                        'employee_id'    => 'eq.' . $emp['id'],
                        'financial_year' => 'eq.' . $fy,
                        'kpi_title'      => 'eq.' . $tpl['kpi_title'],
                        'select'         => 'id',
                        'order'          => 'created_at.desc',
                        'limit'          => '1',
                    ]);
                    $kpiId = $fallback[0]['id'] ?? null;
                }

                if ($kpiId) {
                    foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $q) {
                        $supabase->safeInsert('kpi_quarters', [
                            'kpi_id'         => $kpiId,
                            'quarter'        => $q,
                            'quarter_target' => 0,
                            'quarter_actual' => 0,
                            'status'         => 'not_started',
                            'created_at'     => $now,
                            'updated_at'     => $now,
                        ]);
                    }
                    $created++;
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Done! {$created} KPI(s) created, {$skipped} already existed.",
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    public function myDepartmentKpi(SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);

        $fy = $this->currentFY();

        /*
        |--------------------------------------------------------------------------
        | GET DEPARTMENT KPI
        |--------------------------------------------------------------------------
        */

        $kpis = $supabase->get('kpis', [

            'company_code' => 'eq.' . $user['company_code'],

            'department_code' => 'eq.' . $user['department_code'],

            'financial_year' => 'eq.' . $fy,

            'select' => '*',

            'order' => 'created_at.desc',

        ]) ?? [];

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEES
        |--------------------------------------------------------------------------
        */

        $employees = $supabase->get('employees', [

            'company_code' => 'eq.' . $user['company_code'],

            'department_code' => 'eq.' . $user['department_code'],

            'is_active' => 'eq.true',

            'select' => implode(',', [

                'id',
                'employee_id',
                'short_name',
                'role',
                'department_code'

            ]),

        ]) ?? [];

        $employeeMap = collect($employees)->keyBy('id');

        /*
        |--------------------------------------------------------------------------
        | LOAD QUARTERS
        |--------------------------------------------------------------------------
        */

        $kpis = collect($kpis)->map(function ($kpi) use ($supabase, $employeeMap) {

            $employee = $employeeMap->get($kpi['employee_id']);

            $kpi['employee_name']
                = $employee['short_name'] ?? 'Unknown';

            $kpi['employee_role']
                = $employee['role'] ?? '-';

            $quarters = $supabase->get('kpi_quarters', [

                'kpi_id' => 'eq.' . $kpi['id'],

                'select' => '*',

                'order' => 'quarter.asc',

            ]) ?? [];

            $kpi['quarters'] = $quarters;

            return $kpi;

        })->toArray();

        /*
        |--------------------------------------------------------------------------
        | DEPARTMENT PERFORMANCE
        |--------------------------------------------------------------------------
        */

        $departmentPerformance = collect($kpis)->avg(function ($kpi) {

            $quarters = collect($kpi['quarters'] ?? []);

            $target = $quarters->sum(function ($q) {

                return (float) ($q['quarter_target'] ?? 0);

            });

            $actual = $quarters->sum(function ($q) {

                return (float) ($q['quarter_actual'] ?? 0);

            });

            if ($target <= 0) {
                return 0;
            }

            return ($actual / $target) * 100;

        });

        return view('kpi.my-department-kpi', array_merge([

            'user' => $user,

            'fy' => $fy,

            'kpis' => $kpis,

            'employees' => $employees,

            'departmentPerformance'
                => round($departmentPerformance, 2),

        ], $this->sidebarData($supabase, $user)));
    }

    public function requestTargetChange(
        Request $request,
        $id,
        SupabaseService $supabase
        )
        {
        $user = $this->currentUser(
        $supabase
        );

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'new_base_target'
                => 'required|numeric|min:0',

            'new_stretch_target'
                => 'required|numeric|min:0',

            'reason'
                => 'required|string|min:30',

        ]);

        /*
        |--------------------------------------------------------------------------
        | STRETCH >= BASE
        |--------------------------------------------------------------------------
        */

        if(
            (float)$validated['new_stretch_target']
            <
            (float)$validated['new_base_target']
        ){
            return response()->json([

                'success' => false,

                'message'
                    => 'Stretch target must be greater than or equal to Base target.'

            ],422);
        }

        /*
        |--------------------------------------------------------------------------
        | KPI
        |--------------------------------------------------------------------------
        */

        $kpi =
            $this->findKpiOrFail(
                $supabase,
                $id
            );

        /*
        |--------------------------------------------------------------------------
        | DUPLICATE CHECK
        |--------------------------------------------------------------------------
        */

        $existing =
            $supabase->first(

                'kpi_target_change_requests',

                [

                    'kpi_id'
                        => 'eq.' . $id,

                    'status'
                        => 'eq.pending',

                    'select'
                        => '*'

                ]

            );

        if($existing){

            return response()->json([

                'success' => false,

                'message'
                    => 'Pending request already exists.'

            ],409);
        }

        /*
        |--------------------------------------------------------------------------
        | APPROVER
        |--------------------------------------------------------------------------
        */

        $approver =
            $this->hierarchyService
                ->getApprover(
                    $user
                );

        /*
        |--------------------------------------------------------------------------
        | NO APPROVER (TOP OF HIERARCHY) — SELF-APPROVE IMMEDIATELY
        |--------------------------------------------------------------------------
        */

        if(!$approver){
            $approver = $user;
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE REQUEST
        |--------------------------------------------------------------------------
        */

        $insertResult =
            $supabase->insert(

                'kpi_target_change_requests',

                [

                    'kpi_id'
                        => $id,

                    'requested_by'
                        => $user['id'],

                    'requested_by_name'
                        => $user['short_name'],

                    'requested_role'
                        => $user['role'],

                    'approver_id'
                        => $approver['id'],

                    'approver_name'
                        => $approver['short_name'],

                    'approver_role'
                        => $approver['role'],

                    /*
                    |--------------------------------------------------------------------------
                    | LEGACY REQUIRED FIELD
                    |--------------------------------------------------------------------------
                    */

                    'field_name'
                        => 'target_change',

                    'old_value'
                        => $kpi['base_target'],

                    'requested_value'
                        => $validated['new_base_target'],

                    /*
                    |--------------------------------------------------------------------------
                    | NEW FIELD
                    |--------------------------------------------------------------------------
                    */

                    'old_base_target'
                        => $kpi['base_target'],

                    'new_base_target'
                        => $validated['new_base_target'],

                    'old_stretch_target'
                        => $kpi['stretch_target'],

                    'new_stretch_target'
                        => $validated['new_stretch_target'],

                    'reason'
                        => $validated['reason'],

                    'status'
                        => 'pending',

                    'created_at'
                        => $this->nowMy(),

                ]

            );

        if(
            empty($insertResult)
            ||
            empty($insertResult[0]['id'])
        ){

            return response()->json([

                'success' => false,

                'message'
                    => 'Failed to create request.'

            ],500);
        }

        /*
        |--------------------------------------------------------------------------
        | SELF-APPROVE WHEN NO APPROVER EXISTS
        |--------------------------------------------------------------------------
        */

        if($approver['id'] === $user['id']){

            return app(
                \App\Services\ApprovalActionService::class
            )->approveTarget(
                $insertResult[0],
                $user['id'],
                $user['short_name']
            );
        }

        $this->notifyApprover(
            $approver['id'],
            'kpi_target_change_approval',
            $user['short_name'] ?? 'Unknown',
            $kpi['kpi_title'] ?? 'a KPI',
            'Target change needs your approval',
            $insertResult[0]['id'] ?? null
        );

        return response()->json([

            'success' => true,

            'message'
                => 'Target change request submitted.'

        ]);

        }


    /*
    |--------------------------------------------------------------------------
    | REQUEST DELETE
    |--------------------------------------------------------------------------
    */

    public function requestDelete($id)
    {
        $kpi = $this->findKpiOrFail($this->supabase, $id);

        return view('kpi.request-delete', [
            'kpi' => $kpi,
            'user' => $this->currentUser($this->supabase),
        ]);
    }

    public function submitDeleteRequest(
        Request $request,
        $id
    )
    {
        $user = $this->currentUser(
            $this->supabase
        );

        $kpi = $this->findKpiOrFail(
            $this->supabase,
            $id
        );

        /*
        |--------------------------------------------------------------------------
        | PERMISSION
        |--------------------------------------------------------------------------
        */

        if(
            !$this->canEditKpi(
                $user,
                $kpi
            )
        ){
            abort(
                403,
                'You cannot delete this KPI.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'reason' => [
                'required',
                'string',
                'min:30',
                'max:2000',
            ],

        ]);

        /*
        |--------------------------------------------------------------------------
        | BLOCK IF KPI HAS PENDING APPROVALS
        |--------------------------------------------------------------------------
        */

        $pendingQuarterApproval =
            $this->supabase->get(
                'kpi_update_approvals',
                [
                    'kpi_id'
                        => 'eq.' . $id,

                    'status'
                        => 'eq.pending',

                    'select'
                        => 'id',

                    'limit'
                        => 1,
                ]
            ) ?? [];

        $pendingTargetApproval =
            $this->supabase->get(
                'kpi_target_change_requests',
                [
                    'kpi_id'
                        => 'eq.' . $id,

                    'status'
                        => 'eq.pending',

                    'select'
                        => 'id',

                    'limit'
                        => 1,
                ]
            ) ?? [];

        if(
            !empty($pendingQuarterApproval)
            ||
            !empty($pendingTargetApproval)
        ){

            return response()->json([

                'success'
                    => false,

                'message'
                    => 'Resolve pending approvals first.'

            ],409);
        }

        /*
        |--------------------------------------------------------------------------
        | BLOCK DUPLICATE DELETE REQUEST
        |--------------------------------------------------------------------------
        */

        $existingRequest =
            $this->supabase->get(
                'kpi_delete_requests',
                [
                    'kpi_id'
                        => 'eq.' . $id,

                    'status'
                        => 'eq.pending',

                    'select'
                        => '*',
                ]
            ) ?? [];

        if(
            !empty($existingRequest)
        ){

            return response()->json([

                'success'
                    => false,

                'message'
                    => 'Pending delete request already exists.'

            ],409);
        }

        /*
        |--------------------------------------------------------------------------
        | APPROVER
        |--------------------------------------------------------------------------
        */

        $approver =
            $this->hierarchyService
                ->getApprover(
                    $user
                );

        /*
        |--------------------------------------------------------------------------
        | NO APPROVER (TOP OF HIERARCHY) — SELF-APPROVE IMMEDIATELY
        |--------------------------------------------------------------------------
        */

        if(!$approver){
            $approver = $user;
        }

        $approverId =
            $approver['id']
            ?? null;

        if(
            !$approverId
        ){

            return response()->json([

                'success'
                    => false,

                'message'
                    => 'Approver ID missing.'

            ],422);
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT REQUEST
        |--------------------------------------------------------------------------
        */

        $payload = [

            'kpi_id'
                => $id,

            'requested_by'
                => $user['id'],

            'requested_by_name'
                => $user['short_name']
                    ?? $user['full_name']
                    ?? $user['name']
                    ?? 'Unknown',

            'requested_role'
                => $user['role']
                    ?? null,

            'approver_id'
                => $approverId,

            'approver_name'
                => $approver['short_name']
                    ?? $approver['full_name']
                    ?? $approver['name']
                    ?? null,

            'approver_role'
                => $approver['role']
                    ?? null,

            'reason'
                => trim(
                    $validated['reason']
                ),

            'status'
                => 'pending',

            'created_at'
                => $this->nowMy(),

        ];

        try {

            $result =
                $this->supabase->insert(
                    'kpi_delete_requests',
                    $payload
                );

        } catch (\Throwable $e) {

            Log::error(
                'DELETE REQUEST INSERT FAILED',
                [
                    'payload' =>
                        $payload,

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return response()->json([

                'success'
                    => false,

                'message'
                    => $e->getMessage(),

            ],500);
        }

        /*
        |--------------------------------------------------------------------------
        | SELF-APPROVE WHEN NO APPROVER EXISTS
        |--------------------------------------------------------------------------
        */

        if(
            $approver['id'] === $user['id']
            &&
            !empty($result[0]['id'])
        ){

            return app(
                \App\Services\ApprovalActionService::class
            )->approveDelete(
                $result[0],
                $user['id'],
                $user['short_name']
            );
        }

        $this->notifyApprover(
            $approverId,
            'kpi_delete_approval',
            $user['short_name'] ?? $user['full_name'] ?? $user['name'] ?? 'Unknown',
            $kpi['kpi_title'] ?? 'a KPI',
            'Delete request needs your approval',
            $result[0]['id'] ?? null
        );

        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success'
                => true,

            'message'
                => 'Delete request submitted successfully.',

            'data'
                => $result,

        ]);
    }

        /*
        |--------------------------------------------------------------------------
        | UPDATE QUARTER ACTUAL
        |--------------------------------------------------------------------------
        */

    public function updateQuarterActual(
        Request $request
    )
    {
        $validated = $request->validate([

            'quarter_id'
                => 'required|string',

            'quarter_actual'
                => 'required|numeric|min:0',

        ]);

        $quarterResult =
            $this->supabase->get(

                'kpi_quarters',

                [

                    'id'
                        => 'eq.' .
                        $validated['quarter_id'],

                    'select'
                        => '*',

                    'limit'
                        => 1

                ]

            ) ?? [];

        if(empty($quarterResult)){

            return response()->json([

                'success' => false,

                'message' =>
                    'Quarter not found.'

            ],404);
        }

        $quarter =
            $quarterResult[0];

        $today =
            now('Asia/Kuala_Lumpur')
            ->startOfDay();

        $startDate =
            \Carbon\Carbon::parse(
                $quarter['start_date']
            )->startOfDay();

        $endDate =
            \Carbon\Carbon::parse(
                $quarter['end_date']
            )->startOfDay();

        // A BTS-issued override (QuarterOverrideController) force-opens this
        // exact quarter until a chosen deadline, bypassing both the "not
        // started yet" and "already ended" checks below regardless of its
        // own start/end dates.
        $quarterOverrideActive = $this->quarterOverrides->isActive(
            'FY' . $startDate->year,
            $quarter['quarter'] ?? ''
        );

        /*
        |--------------------------------------------------------------------------
        | BEFORE START DATE
        |--------------------------------------------------------------------------
        */

        if(
            $today->lt(
                $startDate
            )
            && !$quarterOverrideActive
        ){

            return response()->json([

                'success' => false,

                'message' =>
                    'Quarter has not started yet.'

            ],422);
        }

        /*
        |--------------------------------------------------------------------------
        | AFTER END DATE
        |--------------------------------------------------------------------------
        */

        if(
            $today->gt(
                $endDate
            )
            && !$this->inQ1Q2GracePeriod($quarter)
            && !$quarterOverrideActive
        ){

            return response()->json([

                'success' => false,

                'message' =>
                    'Quarter already ended. Use approval request.'

            ],422);
        }

        /*
        |--------------------------------------------------------------------------
        | DIRECT SAVE
        |--------------------------------------------------------------------------
        */

        $success =
            $this->supabase->safePatch(

                'kpi_quarters',

                [
                    'id'
                        => 'eq.' .
                        $quarter['id']
                ],

                [

                    'quarter_actual'
                        => (float)
                        $validated['quarter_actual'],

                    'updated_at'
                        => $this->nowMy()

                ]

            );

            /*
            |--------------------------------------------------------------------------
            | RECALCULATE KPI TOTAL ACTUAL
            |--------------------------------------------------------------------------
            */

            $allQuarters =
                $this->supabase->get(
                    'kpi_quarters',
                    [
                        'kpi_id'
                            => 'eq.' .
                            $quarter['kpi_id'],

                        'select'
                            => '*'
                    ]
                ) ?? [];

            $totalActual =
                collect($allQuarters)
                ->sum(
                    fn($q)
                    =>
                    (float)
                    ($q['quarter_actual'] ?? 0)
                );

            $this->supabase->safePatch(

                'kpis',

                [
                    'id'
                        => 'eq.' .
                        $quarter['kpi_id']
                ],

                [

                    'actual_value'
                        => $totalActual,

                    'updated_at'
                        => $this->nowMy()

                ]

            );

        if(!$success){

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to update quarter.'

            ],500);
        }

        return response()->json([

            'success' => true,

            'message' =>
                'Actual updated successfully.'

        ]);
    }

        /*
        |--------------------------------------------------------------------------
        | REQUEST QUARTER APPROVAL
        |--------------------------------------------------------------------------
        */

        public function requestQuarterApproval(Request $request)
        {
            $user = $this->currentUser($this->supabase);

            $validated = $request->validate([

                'kpi_id' => 'required|string',

                'quarter' => 'required|string|in:Q1,Q2,Q3,Q4',

                'actual' => 'nullable|numeric|min:0',

                'remark' => 'nullable|string',

            ]);

            /*
            |--------------------------------------------------------------------------
            | PREVENT DUPLICATE PENDING REQUEST
            |--------------------------------------------------------------------------
            */

            $existingRequest = $this->supabase->get(
                'kpi_update_approvals',
                [
                    'kpi_id' => 'eq.' . $validated['kpi_id'],
                    'quarter' => 'eq.' . $validated['quarter'],
                    'status' => 'eq.pending',
                    'select' => '*',
                ]

            ) ?? [];

            if (!empty($existingRequest)) {

                return response()->json([

                    'success' => false,

                    'message' => 'Pending approval already exists.'

                ], 409);
            }

            $quarterRow = $this->supabase->first(
                'kpi_quarters',
                [
                    'kpi_id' => 'eq.' . $validated['kpi_id'],
                    'quarter' => 'eq.' . $validated['quarter'],
                    'select' => '*',
                ]
            );

            $approverId =
                $this->hierarchyService
                    ->getApproverId($user);

            // NO APPROVER (TOP OF HIERARCHY) — SELF-APPROVE IMMEDIATELY
            $noApprover = !$approverId;
            if($noApprover){
                $approverId = $user['id'] ?? null;
            }

            /*
            |--------------------------------------------------------------------------
            | CREATE REQUEST
            |--------------------------------------------------------------------------
            */

            $insertResult = $this->supabase->insert(
                'kpi_update_approvals',
                [
                    'kpi_id' => $validated['kpi_id'],
                    'quarter' => $validated['quarter'],
                    'quarter_id' => $quarterRow['id'] ?? null,
                    'old_actual' => $quarterRow['quarter_actual'] ?? 0,
                    'quarter_target' => $quarterRow['quarter_target'] ?? 0,
                    'requested_actual' => $validated['actual'] ?? 0,
                    'request_remark' => $validated['remark'] ?? null,
                    'requested_by' => $user['id'] ?? null,
                    'requested_by_name' => $user['short_name'] ?? 'Unknown',
                    'approver_id' => $approverId,
                    'status' => 'pending',
                    'created_at' => $this->nowMy(),
                ]
            );

            if(empty($insertResult) || empty($insertResult[0]['id'])){
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create approval request.'
                ],500);
            }

            if($noApprover){
                return app(
                    \App\Services\ApprovalActionService::class
                )->approveQuarter(
                    $insertResult[0],
                    $user['id'],
                    $user['short_name']
                );
            }

            $kpiForNotify = $this->supabase->first('kpis', ['id' => 'eq.' . $validated['kpi_id'], 'select' => 'kpi_title']);
            $this->notifyApprover(
                $approverId,
                'kpi_actual_approval',
                $user['short_name'] ?? 'Unknown',
                $kpiForNotify['kpi_title'] ?? 'a KPI',
                'Actual value update needs your approval',
                $insertResult[0]['id'] ?? null
            );

        return response()->json([
                'success' => true,
                'message' => 'Approval request submitted successfully.'
        ]);


    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT ACTUAL UPDATE REQUEST
    |--------------------------------------------------------------------------
    |
    | Used when user wants to change actual value
    | after quarter is locked.
    |
    */

    public function submitActualUpdateRequest(
        Request $request,
        string $kpiId,
        string $quarterId
    )
    {
        $user = $this->currentUser(
            $this->supabase
        );

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'requested_actual'
                => 'required|numeric|min:0',

            'reason'
                => 'nullable|string|max:1000',

        ]);

        /*
        |--------------------------------------------------------------------------
        | KPI
        |--------------------------------------------------------------------------
        */

        $kpi = $this->findKpiOrFail(
            $this->supabase,
            $kpiId
        );

        /*
        |--------------------------------------------------------------------------
        | QUARTER
        |--------------------------------------------------------------------------
        */

        $quarter = $this->supabase->first(
            'kpi_quarters',
            [
                'id'
                    => 'eq.' . $quarterId,

                'kpi_id'
                    => 'eq.' . $kpiId,

                'select'
                    => '*',
            ]
        );

        if (!$quarter) {
            return response()->json([
                'success' => false,
                'message' => 'Quarter not found.'
            ],404);
        }

        /*
        |--------------------------------------------------------------------------
        | REASON REQUIREMENT
        |--------------------------------------------------------------------------
        */

        $today =
            now('Asia/Kuala_Lumpur')
            ->startOfDay();

        $endDate =
            Carbon::parse(
                $quarter['end_date']
            )->startOfDay();

        $reasonRequired =
            ($today->gt($endDate) && !$this->inQ1Q2GracePeriod($quarter))

            ||

            (
                ($quarter['status'] ?? '')
                === 'completed'
            );

        if(

            $reasonRequired

            &&

            strlen(
                trim(
                    $validated['reason']
                    ?? ''
                )
            ) < 20

        ){

            return response()->json([

                'success' => false,

                'message' =>
                    'Reason minimum 20 characters.'

            ],422);
        }

        $currentActual =

        (float)
        (
            $quarter['quarter_actual']
            ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | PHASE 7
        | CALCULATE RISK
        |--------------------------------------------------------------------------
        */

        $variance = 0;

        if ($currentActual <= 0) {

            if (
                (float)$validated['requested_actual']
                > 0
            ) {

                $variance = 999;
            }

        } else {

            $variance = (

                (
                    (
                        (float)$validated['requested_actual']
                        -
                        (float)$currentActual
                    )

                    /

                    (float)$currentActual

                )

                * 100
            );
        }

        $riskLevel = 'LOW';

        if (
            abs($variance) >= 100
        ) {

            $riskLevel = 'CRITICAL';

        }
        elseif (
            abs($variance) >= 50
        ) {

            $riskLevel = 'HIGH';

        }
        elseif (
            abs($variance) >= 20
        ) {

            $riskLevel = 'MEDIUM';

        }

        /*
        |--------------------------------------------------------------------------
        | QUARTER LOCK CHECK
        |--------------------------------------------------------------------------
        */

        $today = now('Asia/Kuala_Lumpur')
            ->startOfDay();

        $endDate = Carbon::parse(
            $quarter['end_date']
        )->startOfDay();

        if (!$today->gt($endDate)) {

            return response()->json([

                'success' => false,

                'message'
                    => 'Quarter is still open. Use normal update.'

            ],422);
        }

        /*
        |--------------------------------------------------------------------------
        | ALREADY-PENDING REQUEST -- UPDATE IT IN PLACE, DON'T BLOCK THE EDIT
        |--------------------------------------------------------------------------
        | The requester is still allowed to change their mind before the
        | approver acts. Rather than reject the edit (forcing them to wait
        | for the stale request to be approved/rejected first), overwrite
        | the existing pending row with the newly submitted actual/reason
        | so whatever the approver reviews is always the requester's latest
        | figure, not whatever was typed first.
        */

        $existing = $this->supabase->get(
            'kpi_update_approvals',
            [
                'quarter_id' => 'eq.' . $quarterId,
                'status' => 'eq.pending',
                'select' => '*',
                'limit' => 1,
            ]
        ) ?? [];

        if (!empty($existing)) {

            $existingApproval = $existing[0];

            try {
                $this->supabase->patch(
                    'kpi_update_approvals',
                    ['id' => 'eq.' . $existingApproval['id']],
                    [
                        'old_actual' => $currentActual,
                        'quarter_target' => $quarter['quarter_target'] ?? 0,
                        'requested_actual' => $validated['requested_actual'],
                        'reason' => $validated['reason'],
                        'variance' => round($variance, 2),
                        'risk_level' => $riskLevel,
                        'requested_by' => $user['id'],
                        'requested_by_name' => $user['short_name'] ?? 'Unknown',
                        'requested_by_role' => $user['role'] ?? null,
                        'created_at' => $this->nowMy(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::error('submitActualUpdateRequest: update-existing-pending failed', ['error' => $e->getMessage()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update the pending request.'
                ], 500);
            }

            if (!empty($existingApproval['approver_id'])) {
                $this->notifyApprover(
                    $existingApproval['approver_id'],
                    'kpi_actual_approval',
                    $user['short_name'] ?? 'Unknown',
                    $kpi['kpi_title'] ?? 'a KPI',
                    'Actual value update needs your approval (updated)',
                    $existingApproval['id']
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Pending approval updated with your latest actual value.'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | APPROVER
        |--------------------------------------------------------------------------
        */

        $approver =
            $this->hierarchyService
                ->getApprover($user);

        /*
        |--------------------------------------------------------------------------
        | SLT / ADMIN AUTO APPROVE
        |--------------------------------------------------------------------------
        */

        if($approver === null){

            return $this->autoApproveActual(
                $kpiId,
                $quarterId,
                $quarter,
                $currentActual,
                $validated,
                $user,
                $variance,
                $riskLevel
            );
        }

        $approverId =
            $approver['id'] ?? null;

        if (!$approverId) {

            return response()->json([
                'success' => false,
                'message' => 'Approver ID not found.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE REQUEST
        |--------------------------------------------------------------------------
        */

        try {
            $actualInsert = $this->supabase->insert(
                'kpi_update_approvals',
                [

                    'kpi_id'
                        => $kpiId,

                    'quarter_id'
                        => $quarterId,

                    'quarter'
                        => $quarter['quarter'] ?? null,

                    'old_actual'
                        => $currentActual,

                    'quarter_target'
                        => $quarter['quarter_target'] ?? 0,

                    'requested_actual'
                        => $validated['requested_actual'],

                    'reason'
                        => $validated['reason'],

                    'variance'
                        => round($variance, 2),

                    'risk_level'
                        => $riskLevel,

                    'requested_by'
                        => $user['id'],

                    'requested_by_name'
                        => $user['short_name'] ?? 'Unknown',

                    'requested_by_role'
                        => $user['role']
                        ?? null,

                    'approver_id'
                        => $approverId,

                    'approver_name'
                        => $approver['short_name']
                        ?? null,

                    'approver_role'
                        => $approver['role']
                        ?? null,

                    'status'
                        => 'pending',

                    'created_at'
                        => $this->nowMy(),

                ]
            );
        } catch (\Throwable $e) {
            Log::error('submitActualUpdateRequest: insert failed', ['error' => $e->getMessage()]);
            $actualInsert = null;
        }

        if (!$actualInsert) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit request.'
            ], 500);
        }

        $this->notifyApprover(
            $approverId,
            'kpi_actual_approval',
            $user['short_name'] ?? 'Unknown',
            $kpi['kpi_title'] ?? 'a KPI',
            'Actual value update needs your approval',
            $actualInsert[0]['id'] ?? null
        );

        return response()->json([

            'success' => true,

            'message'
                => 'Actual update request submitted successfully.'

        ]);
    }

    private function autoApproveActual(
        string $kpiId,
        string $quarterId,
        array $quarter,
        float $currentActual,
        array $validated,
        array $user,
        float $variance,
        string $riskLevel
    )
    {
        $approval = [

            'id' => null,

            'kpi_id'
                => $kpiId,

            'quarter_id'
                => $quarterId,

            'quarter'
                => $quarter['quarter'] ?? null,

            'old_actual'
                => $currentActual,

            'quarter_target'
                => $quarter['quarter_target'] ?? 0,

            'requested_actual'
                => $validated['requested_actual'],

            'reason'
                => $validated['reason'],

            'status'
                => 'approved',

            'variance'
                => round($variance, 2),

            'risk_level'
                => $riskLevel,

        ];

        $insertResult =
            $this->supabase->insert(
                'kpi_update_approvals',
                [

                    'kpi_id'
                        => $kpiId,

                    'quarter_id'
                        => $quarterId,

                    'quarter'
                        => $quarter['quarter'],

                    'old_actual'
                        => $currentActual,

                    'quarter_target'
                        => $quarter['quarter_target'] ?? 0,

                    'requested_actual'
                        => $validated['requested_actual'],

                    'reason'
                        => $validated['reason'],

                    'variance'
                        => round($variance, 2),

                    'risk_level'
                        => $riskLevel,

                    'requested_by'
                        => $user['id'],

                    'requested_by_name'
                        => $user['short_name'],

                    'requested_by_role'
                        => $user['role'],

                    'approver_role'
                        => $user['role'],

                    'approver_id'
                        => $user['id'],

                    'approver_name'
                        => $user['short_name'],

                    'status'
                        => 'approved',

                    'approved_at'
                        => $this->nowMy(),

                    'created_at'
                        => $this->nowMy(),

                ]
            );

        if(
            empty($insertResult)
            ||
            empty($insertResult[0]['id'])
        ){
            return response()->json([
                'success' => false,
                'message' => 'Failed creating approval record.'
            ],500);
        }

        $approval['id']
            = $insertResult[0]['id'];

        app(
            \App\Services\ApprovalActionService::class
        )->autoApproveQuarter(
            $approval,
            $user['id'],
            $user['short_name']
        );

        return response()->json([
            'success' => true,
            'message'
                => 'Actual updated successfully.'
        ]);
    }

    public function saveQuarterStatus(
        Request $request,
        string $quarterId
    )
    {
        $validated = $request->validate([

            'status'
                => 'required|in:not_started,on_track,at_risk,in_trouble,completed',

        ]);

        $user = $this->currentUser(
            $this->supabase
        );

        $quarter = $this->supabase->first(
            'kpi_quarters',
            [
                'id' => 'eq.' . $quarterId,
                'select' => '*',
            ]
        );

        if(!$quarter){
            abort(404);
        }

        $kpi = $this->findKpiOrFail(
            $this->supabase,
            $quarter['kpi_id']
        );

        if(
            !$this->canEditKpi(
                $user,
                $kpi
            )
        ){
            abort(403);
        }

        $updated = $this->supabase->safePatch(

            'kpi_quarters',

            [
                'id'
                    => 'eq.' . $quarterId,
            ],

            [
                'status'
                    => $validated['status'],

                'updated_at'
                    => $this->nowMy(),
            ]

        );

        if(!$updated){

            return response()->json([

                'success' => false,

                'message'
                    => 'Failed updating status.'

            ],500);
        }

        return response()->json([

            'success' => true,

            'message'
                => 'Status updated successfully.'

        ]);
    }

    public function bulkUpdateWeightage(Request $request)
    {
        $user = $this->currentUser($this->supabase);

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'weightages' => 'required|array',

            'weightages.*'
                => 'nullable|numeric|min:0|max:100',

        ]);

        $weightages = $validated['weightages'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | NEW-ALLOCATION VALIDATION
        | bulk-update only accepts KPIs that currently have 0% weightage.
        | Changing an existing non-zero weightage must go through the approval flow.
        |--------------------------------------------------------------------------
        */

        $kpiIds = array_keys($weightages);

        if(!empty($kpiIds)){

            $existingKpis = $this->supabase->get('kpis', [
                'id'          => 'in.(' . implode(',', $kpiIds) . ')',
                'employee_id' => 'eq.' . ($user['id'] ?? ''),
                'select'      => 'id,weightage',
            ]) ?? [];

            $existingMap = collect($existingKpis)->keyBy('id');

            foreach($kpiIds as $kpiId){
                $existing = $existingMap->get($kpiId);
                if($existing && (float)($existing['weightage'] ?? 0) > 0){
                    return response()->json([
                        'success' => false,
                        'message' => 'Changing an existing weightage requires approval. Use the approval request flow.'
                    ], 422);
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | GET USER OWN KPI
        |--------------------------------------------------------------------------
        */

        $myKpis = $this->supabase->get('kpis', [

            'employee_id'
                => 'eq.' . ($user['id'] ?? ''),

            'financial_year'
                => 'eq.' . $this->currentFY(),

            'select'
                => 'id,employee_id,weightage',

        ]) ?? [];

        /*
        |--------------------------------------------------------------------------
        | OWN KPI IDS
        |--------------------------------------------------------------------------
        */

        $allowedKpiIds = collect($myKpis)
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();
        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        foreach($weightages as $kpiId => $weightage){

            /*
            |--------------------------------------------------------------------------
            | SECURITY CHECK
            |--------------------------------------------------------------------------
            */

            if(!in_array($kpiId, $allowedKpiIds)){

                continue;
            }

            if(empty($allowedKpiIds)){

                return response()->json([

                    'success'=>false,

                    'message'=>'No KPI found.'

                ],422);
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE KPI
            |--------------------------------------------------------------------------
            */

            $this->supabase->safePatch(

                'kpis',

                [

                    'id'
                        => 'eq.' . $kpiId,

                ],

                [

                    'weightage'
                        => round((float)$weightage, 2),

                    'updated_at'
                        => $this->nowMy(),

                ]

            );
        }

        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Weightage updated successfully.'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCEPT ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    public function acceptAssignment($id, SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);

        $assignment = $supabase->get('kpi_assignments', [
            'id'                    => 'eq.' . $id,
            'assigned_employee_id'  => 'eq.' . $user['id'],
            'assignment_status'     => 'eq.pending',
            'select'                => '*',
        ]) ?? [];

        if (empty($assignment)) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found or already actioned.',
            ], 404);
        }

        $supabase->safePatch(
            'kpi_assignments',
            ['id' => 'eq.' . $id],
            [
                'assignment_status' => 'accepted',
                'accepted_at'       => $this->nowMy(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'KPI accepted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    public function rejectAssignment(Request $request, $id, SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);

        $validated = $request->validate([
            'reason' => 'required|string|min:30',
        ]);

        $assignment = $supabase->get('kpi_assignments', [
            'id'                    => 'eq.' . $id,
            'assigned_employee_id'  => 'eq.' . $user['id'],
            'assignment_status'     => 'eq.pending',
            'select'                => '*',
        ]) ?? [];

        if (empty($assignment)) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found or already actioned.',
            ], 404);
        }

        $supabase->safePatch(
            'kpi_assignments',
            ['id' => 'eq.' . $id],
            [
                'assignment_status'  => 'rejected',
                'rejection_reason'   => $validated['reason'],
                'rejected_at'        => $this->nowMy(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'KPI rejected.',
        ]);
    }
}
