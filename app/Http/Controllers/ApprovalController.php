<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SupabaseService;
use App\Services\ApprovalActionService;
use App\Services\ApprovalHierarchyService;
use App\Services\NotificationService;

class ApprovalController extends Controller
{
    protected ApprovalActionService $approvalActionService;
    protected ApprovalHierarchyService $hierarchyService;
    protected NotificationService $notifications;

    public function __construct(
        ApprovalActionService $approvalActionService,
        ApprovalHierarchyService $hierarchyService,
        NotificationService $notifications
    ){
        $this->approvalActionService = $approvalActionService;
        $this->hierarchyService      = $hierarchyService;
        $this->notifications         = $notifications;
    }



    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(
        SupabaseService $supabase
    ){

        $userId = session('employee_uuid');

        if(!$userId){

            return redirect()
                ->route('login');
        }

        /*
        |--------------------------------------------------------------------------
        | QUARTER APPROVALS
        |--------------------------------------------------------------------------
        */

        $quarterApprovals = $supabase->get(

            'kpi_update_approvals',

            [

                'approver_id' =>
                    'eq.' . $userId,

                'requested_by' =>
                    'neq.' . $userId,

                'order' =>
                    'created_at.desc',

            ]

        ) ?? [];

        /*
        |--------------------------------------------------------------------------
        | TARGET CHANGE REQUESTS
        |--------------------------------------------------------------------------
        */

        $allTargetRows = $supabase->get(

            'kpi_target_change_requests',

            [

                'approver_id' =>
                    'eq.' . $userId,

                'requested_by' =>
                    'neq.' . $userId,

                'order' =>
                    'created_at.desc',

            ]

        ) ?? [];

        $targetRequests   = array_values(array_filter($allTargetRows,
            fn($r) => !str_starts_with($r['reason'] ?? '', '[[WC]]')));

        $weightageRequests = array_values(array_filter($allTargetRows,
            fn($r) => str_starts_with($r['reason'] ?? '', '[[WC]]')));

        /*
        |--------------------------------------------------------------------------
        | DELETE REQUESTS
        |--------------------------------------------------------------------------
        */

        $deleteRequests = $supabase->get(

            'kpi_delete_requests',

            [

                'approver_id' =>
                    'eq.' . $userId,

                'requested_by' =>
                    'neq.' . $userId,

                'order' =>
                    'created_at.desc',

            ]

        ) ?? [];

        /*
        |--------------------------------------------------------------------------
        | ENRICH
        |--------------------------------------------------------------------------
        */

        // Split completion approvals from quarter_update approvals
        $completionApprovals = array_values(array_filter($quarterApprovals,
            fn($r) => str_starts_with($r['reason'] ?? '', '[[COMPLETION]]')));
        $quarterApprovals = array_values(array_filter($quarterApprovals,
            fn($r) => !str_starts_with($r['reason'] ?? '', '[[COMPLETION]]')));

        $quarterApprovals = $this->enrichApprovals(
            $quarterApprovals,
            'quarter_update',
            $supabase
        );

        $completionApprovals = $this->enrichApprovals(
            $completionApprovals,
            'completion',
            $supabase
        );

        $targetRequests = $this->enrichApprovals(
            $targetRequests,
            'target_change',
            $supabase
        );

        $deleteRequests = $this->enrichApprovals(
            $deleteRequests,
            'delete_request',
            $supabase
        );

        $weightageRequests = $this->enrichApprovals(
            $weightageRequests,
            'weightage_change',
            $supabase
        );

        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        $quarterCount =
            count(
                array_filter(
                    $quarterApprovals,
                    fn($x)
                        => ($x['status'] ?? '')
                        === 'pending'
                )
            );

        $targetCount =
            count(
                array_filter(
                    $targetRequests,
                    fn($x)
                        => ($x['status'] ?? '')
                        === 'pending'
                )
            );

        $deleteCount =
            count(
                array_filter(
                    $deleteRequests,
                    fn($x)
                        => ($x['status'] ?? '')
                        === 'pending'
                )
            );

        $weightageCount =
            count(
                array_filter(
                    $weightageRequests,
                    fn($x)
                        => ($x['status'] ?? '')
                        === 'pending'
                )
            );

        $completionCount =
            count(array_filter($completionApprovals, fn($x) => ($x['status'] ?? '') === 'pending'));

        $totalPending =
            $quarterCount +
            $targetCount +
            $deleteCount +
            $weightageCount +
            $completionCount;

        /*
        |--------------------------------------------------------------------------
        | COMBINE
        |--------------------------------------------------------------------------
        */

        $approvals = array_merge(
            $completionApprovals,
            $quarterApprovals,
            $targetRequests,
            $deleteRequests,
            $weightageRequests
        );

        /*
        |--------------------------------------------------------------------------
        | SORT
        |--------------------------------------------------------------------------
        */

        usort($approvals, function($a, $b){

            return strtotime(
                $b['created_at'] ?? now()
            ) <=> strtotime(
                $a['created_at'] ?? now()
            );
        });

        return view(
            'kpi.approval',
            [
                'approvals'       => $approvals,
                'quarterCount'    => $quarterCount,
                'targetCount'     => $targetCount,
                'deleteCount'     => $deleteCount,
                'weightageCount'  => $weightageCount,
                'completionCount' => $completionCount,
                'totalPending'    => $totalPending,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ENRICH APPROVALS
    |--------------------------------------------------------------------------
    */

    protected function enrichApprovals(
        array $items,
        string $type,
        SupabaseService $supabase
    ){

        if(empty($items)){
            return [];
        }

        $kpiIds = collect($items)
            ->pluck('kpi_id')
            ->filter()
            ->unique()
            ->values();

        if($kpiIds->isEmpty()){
            return $items;
        }

        $kpiIds = $kpiIds->implode(',');

        $kpis = $supabase->get(

            'kpis',

            [

                'id' => 'in.(' . $kpiIds . ')'

            ]

        ) ?? [];

        $kpiMap = collect($kpis)
            ->keyBy('id');

        foreach($items as &$item){

            $kpi = $kpiMap[
                $item['kpi_id']
            ] ?? [];

            $item['kpi_title']
                = $kpi['kpi_title']
                ?? 'Untitled KPI';

            $item['category']
                = $kpi['category']
                ?? '-';

            $item['sub_category']
                = $kpi['sub_category']
                ?? '-';

            $item['unit']
                = $kpi['unit']
                ?? '';

            if(str_starts_with($item['reason'] ?? '', '[[COMPLETION]]')){
                $item['reason'] = substr($item['reason'], 14);
                $item['type']   = 'completion';
            } elseif(str_starts_with($item['reason'] ?? '', '[[WC]]')){
                $item['reason']        = substr($item['reason'], 6);
                $item['type']          = 'weightage_change';
                $item['old_weightage'] = $item['old_base_target'] ?? 0;
                $item['new_weightage'] = $item['new_base_target'] ?? 0;
            } else {
                $item['type'] = $type;
            }

            if(empty($item['priority'])){
                $item['priority'] = match($item['type']){
                    'delete_request'   => 'critical',
                    'target_change'    => 'high',
                    'weightage_change' => 'high',
                    default            => 'normal'
                };
            }
        }

        return $items;
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE
    |--------------------------------------------------------------------------
    */

    public function approve(
        Request $request,
        string $id
    )
    {
        try {

            $approval =
                $this->findApprovalById(
                    $id
                );

            if(!$approval){

                return response()->json([
                    'success' => false,
                    'message' => 'Approval not found'
                ],404);
            }

            // The approver is acting on this request right now, regardless
            // of which screen brought them here — clear its notification so
            // the Approvals badge doesn't keep counting it after it's done.
            $this->notifications->markApprovalResolved(session('employee_uuid'), $id);

            if(
                ($approval['status'] ?? '')
                !== 'pending'
            ){
                return response()->json([
                    'success' => false,
                    'message' => 'Already processed'
                ],422);
            }

            $type =
                $approval['type']
                ?? null;

            if(
                $type === 'delete_request'
            ){

                return $this
                    ->approvalActionService
                    ->approveDelete(

                        $approval,

                        session(
                            'employee_uuid'
                        ),

                        session(
                            'short_name'
                        )

                    );
            }

            if(
                $type === 'target_change'
            ){

                return $this
                    ->approvalActionService
                    ->approveTarget(

                        $approval,

                        session(
                            'employee_uuid'
                        ),

                        session(
                            'short_name'
                        )

                    );
            }

            if(
                $type === 'quarter_update'
            ){

                return $this
                    ->approvalActionService
                    ->approveQuarter(

                        $approval,

                        session(
                            'employee_uuid'
                        ),

                        session(
                            'short_name'
                        )

                    );
            }

            if(
                $type === 'weightage_change'
            ){

                return $this
                    ->approvalActionService
                    ->approveWeightage(

                        $approval,

                        session(
                            'employee_uuid'
                        ),

                        session(
                            'short_name'
                        )

                    );
            }

            if($type === 'completion'){
                return $this->approvalActionService->approveCompletion(
                    $approval,
                    session('employee_uuid'),
                    session('short_name')
                );
            }

            return response()->json([
                'success' => false,
                'message' => 'Unknown approval type'
            ],422);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('approve failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    protected function findApprovalById(
        string $id
    ): ?array
    {
        $supabase = app(
            \App\Services\SupabaseService::class
        );

        /*
        |--------------------------------------------------------------------------
        | QUARTER APPROVAL
        |--------------------------------------------------------------------------
        */

        $approval = $supabase->first(
            'kpi_update_approvals',
            [
                'id' => 'eq.' . $id,
                'approver_id' => 'eq.' . session('employee_uuid'),
            ]
        );

        if($approval){
            if(str_starts_with($approval['reason'] ?? '', '[[COMPLETION]]')){
                $approval['reason'] = substr($approval['reason'], 14);
                $approval['type']   = 'completion';
            } else {
                $approval['type'] = 'quarter_update';
            }
            return $approval;
        }

        /*
        |--------------------------------------------------------------------------
        | TARGET CHANGE
        |--------------------------------------------------------------------------
        */

        $approval = $supabase->first(
            'kpi_target_change_requests',
            [
                'id' => 'eq.' . $id,
                'approver_id' => 'eq.' . session('employee_uuid'),
            ]
        );

        if($approval){
            if(str_starts_with($approval['reason'] ?? '', '[[WC]]')){
                $approval['reason']        = substr($approval['reason'], 6);
                $approval['type']          = 'weightage_change';
                $approval['old_weightage'] = $approval['old_base_target'] ?? 0;
                $approval['new_weightage'] = $approval['new_base_target'] ?? 0;
            } else {
                $approval['type'] = 'target_change';
            }

            return $approval;
        }

        /*
        |--------------------------------------------------------------------------
        | DELETE REQUEST
        |--------------------------------------------------------------------------
        */

        $approval = $supabase->first(
            'kpi_delete_requests',
            [
                'id' => 'eq.' . $id,
                'approver_id' => 'eq.' . session('employee_uuid'),
            ]
        );

        if($approval){

            $approval['type']
                = 'delete_request';

            return $approval;
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT
    |--------------------------------------------------------------------------
    */

    public function reject(
        $id,
        Request $request,
        SupabaseService $supabase
    ){

        $reason = $request->reason ?? 'Rejected';

        /*
        |--------------------------------------------------------------------------
        | QUARTER APPROVAL
        |--------------------------------------------------------------------------
        */

        $approval = $supabase->first(
            'kpi_update_approvals',
            [
                'id' => 'eq.' . $id,
                'approver_id' => 'eq.' . session('employee_uuid'),
            ]
        );

        if($approval){

            $this->notifications->markApprovalResolved(session('employee_uuid'), $id);

            if(
                ($approval['status'] ?? '')
                !== 'pending'
            ){
                return response()->json([
                    'success' => false,
                    'message' => 'Already processed'
                ],422);
            }

            $isCompletion = str_starts_with($approval['reason'] ?? '', '[[COMPLETION]]');

            $this->approvalActionService->reject(
                'kpi_update_approvals',
                $id,
                session('employee_uuid'),
                session('short_name'),
                $reason
            );

            if ($isCompletion) {
                // Revert quarter back to on_track when completion is rejected
                if (!empty($approval['quarter_id'])) {
                    $supabase->safePatch('kpi_quarters', ['id' => 'eq.' . $approval['quarter_id']], [
                        'status'     => 'on_track',
                        'updated_at' => now()->toDateTimeString(),
                    ]);
                }
                $this->approvalActionService->history(
                    $approval['kpi_id'], 'completion_rejected',
                    'pending_completion', 'on_track',
                    session('employee_uuid'), session('short_name')
                );
            } else {
                $this->approvalActionService->history(
                    $approval['kpi_id'], 'quarter_rejected',
                    $approval['requested_actual'], 'REJECTED',
                    session('employee_uuid'), session('short_name')
                );
            }

            return response()->json([
                'success' => true
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | TARGET CHANGE
        |--------------------------------------------------------------------------
        */

        $targetRequest = $supabase->first(
            'kpi_target_change_requests',
            [
                'id' => 'eq.' . $id,
                'approver_id' => 'eq.' . session('employee_uuid'),
            ]
        );

        if($targetRequest){

            $this->notifications->markApprovalResolved(session('employee_uuid'), $id);

            if(
                ($targetRequest['status'] ?? '')
                !== 'pending'
            ){
                return response()->json([
                    'success' => false,
                    'message' => 'Already processed'
                ],422);
            }

            $this->approvalActionService->history(
                $targetRequest['kpi_id'],
                'target_rejected',
                $targetRequest['old_value'],
                $targetRequest['requested_value'],
                session('employee_uuid'),
                session('short_name')
            );

            $this->approvalActionService->reject(
                'kpi_target_change_requests',
                $id,
                session('employee_uuid'),
                session('short_name'),
                $reason
            );

            return response()->json([
                'success' => true
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | DELETE REQUEST
        |--------------------------------------------------------------------------
        */

        $deleteRequest = $supabase->first(
            'kpi_delete_requests',
            [
                'id' => 'eq.' . $id,
                'approver_id' => 'eq.' . session('employee_uuid'),
            ]
        );

        if($deleteRequest){

            $this->notifications->markApprovalResolved(session('employee_uuid'), $id);

            if(
                ($deleteRequest['status'] ?? '')
                !== 'pending'
            ){
                return response()->json([
                    'success' => false,
                    'message' => 'Already processed'
                ],422);
            }

            $this->approvalActionService->reject(
                'kpi_delete_requests',
                $id,
                session('employee_uuid'),
                session('short_name'),
                $reason
            );

            $this->approvalActionService->history(
                $deleteRequest['kpi_id'],
                'delete_rejected',
                'PENDING_DELETE',
                'REJECTED',
                session('employee_uuid'),
                session('short_name')
            );

            return response()->json([
                'success' => true
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | WEIGHTAGE CHANGE REQUEST
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => false,
            'message' => 'Approval not found.'
        ],404);
    }

    /*
    |--------------------------------------------------------------------------
    | REQUEST WEIGHTAGE CHANGE
    |--------------------------------------------------------------------------
    */

    public function requestWeightageChange(
        Request $request,
        string $id,
        SupabaseService $supabase
    ){
        $userId = session('employee_uuid');

        if(!$userId){
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated'
            ], 403);
        }

        $validated = $request->validate([
            'old_weightage' => 'required|numeric|min:0|max:100',
            'new_weightage' => 'required|numeric|min:0|max:100',
            'reason'        => 'required|string|min:20',
        ]);

        $kpi = $supabase->first('kpis', [
            'id'          => 'eq.' . $id,
            'employee_id' => 'eq.' . $userId,
        ]);

        if(!$kpi){
            return response()->json([
                'success' => false,
                'message' => 'KPI not found or does not belong to you.'
            ], 404);
        }

        $pendingForKpi = $supabase->get('kpi_target_change_requests', [
            'kpi_id' => 'eq.' . $id,
            'status' => 'eq.pending',
        ]) ?? [];
        $existing = collect($pendingForKpi)->first(
            fn($r) => str_starts_with($r['reason'] ?? '', '[[WC]]')
        );

        if($existing){
            return response()->json([
                'success' => false,
                'message' => 'A pending approval request already exists for this KPI. Please wait for it to be processed.'
            ], 422);
        }

        $employee = $supabase->first('employees', ['id' => 'eq.' . $userId]);

        $approver = $this->hierarchyService->getApprover($employee ?? []);

        if(!$approver){
            return response()->json([
                'success' => false,
                'message' => 'No approver found for your role. Please contact your administrator.'
            ], 422);
        }

        $oldWt = round((float) $validated['old_weightage'], 2);
        $newWt = round((float) $validated['new_weightage'], 2);

        try {
            $wcInsert = $supabase->insert('kpi_target_change_requests', [
                'kpi_id'             => $id,
                'requested_by'       => $userId,
                'requested_by_name'  => session('short_name') ?? '',
                'requested_role'     => $employee['role'] ?? '',
                'approver_id'        => $approver['id'],
                'approver_name'      => $approver['short_name'] ?? '',
                'approver_role'      => $approver['role'] ?? '',
                'field_name'         => 'target_change',
                'old_value'          => $oldWt,
                'requested_value'    => $newWt,
                'old_base_target'    => $oldWt,
                'new_base_target'    => $newWt,
                'old_stretch_target' => $oldWt,
                'new_stretch_target' => $newWt,
                'reason'             => '[[WC]]' . $validated['reason'],
                'status'             => 'pending',
                'created_at'         => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('requestWeightageChange insert failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit request: ' . $e->getMessage(),
            ], 500);
        }

        $wcId = $wcInsert[0]['id'] ?? null;
        $this->notifications->notify(
            [$approver['id']],
            'kpi_weightage_approval',
            ['name' => session('short_name') ?? 'Someone'],
            (session('short_name') ?? 'Someone') . ' needs your approval',
            'Weightage change needs your approval: ' . ($kpi['kpi_title'] ?? 'a KPI'),
            $wcId ? route('approval.index') . '?highlight=' . $wcId : route('approval.index')
        );

        return response()->json([
            'success'       => true,
            'message'       => 'Approval request submitted to ' . $approver['short_name'] . '. They will review your request shortly.',
            'approver_name' => $approver['short_name'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REJECTED HISTORY
    |--------------------------------------------------------------------------
    */

    public function rejected(
        SupabaseService $supabase
    ){

        $userId = session('employee_uuid');

        $records = array_merge(

            $supabase->get(
                'kpi_update_approvals',
                [
                    'approver_id' =>
                        'eq.' . $userId,

                    'status' =>
                        'eq.rejected',

                    'order' =>
                        'rejected_at.desc',
                ]
            ) ?? [],

            $supabase->get(
                'kpi_target_change_requests',
                [
                    'approver_id' =>
                        'eq.' . $userId,

                    'status' =>
                        'eq.rejected',

                    'order' =>
                        'rejected_at.desc',
                ]
            ) ?? [],

            $supabase->get(
                'kpi_delete_requests',
                [
                    'approver_id' =>
                        'eq.' . $userId,

                    'status' =>
                        'eq.rejected',

                    'order' =>
                        'rejected_at.desc',
                ]
            ) ?? []

        );

        usort($records,function($a,$b){

            return strtotime(
                $b['rejected_at'] ?? now()
            ) <=> strtotime(
                $a['rejected_at'] ?? now()
            );

        });

        return view(
            'approval.rejected',
            compact('records')
        );
    }
}
