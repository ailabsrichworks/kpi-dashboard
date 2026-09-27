<?php

namespace App\Services;

/**
 * Performix Company Platform, Phase 1 hardening (Part 5/6): creates and
 * advances `approval_requests` for any object type, and applies the final
 * decision back onto the underlying object (`kpi_submissions.status`,
 * `kpi_target_revisions.status` + `apply_approved_target_revision()`).
 *
 * A step with a null `resolved_approver_user_id` (couldn't be resolved to
 * anyone, not even the company_admin fallback — see
 * `ApprovalWorkflowService::resolveApproverForStep()`) is created as already
 * 'skipped' rather than 'pending', so `decide()`'s "advance past pending
 * steps" logic treats it exactly like an already-decided step instead of
 * leaving the whole request stuck waiting on a step nobody can ever act on.
 */
class ApprovalRequestService
{
    public function __construct(
        private readonly SupabaseUserService $supabase,
        private readonly ApprovalWorkflowService $workflows,
    ) {
    }

    public function createRequest(
        string $companyId,
        string $workflowType,
        string $objectType,
        string $objectId,
        string $submittedBy,
        ?string $departmentId = null,
    ): array {
        ['workflow' => $workflow, 'steps' => $stepDefs] = $this->workflows->resolveWorkflow($companyId, $workflowType);

        $allowSelfApproval = (bool) ($workflow['allow_self_approval'] ?? false);

        $request = $this->supabase->insert('approval_requests', [
            'company_id' => $companyId,
            'workflow_id' => $workflow['id'] ?? null,
            'workflow_type' => $workflowType,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'department_id' => $departmentId,
            'submitted_by' => $submittedBy,
            'current_step_order' => 1,
            'status' => 'pending',
        ])[0];

        foreach ($stepDefs as $stepDef) {
            $approver = $this->workflows->resolveApproverForStep($stepDef, $companyId, $departmentId, $submittedBy, $allowSelfApproval);

            $this->supabase->insert('approval_request_steps', [
                'request_id' => $request['id'],
                'step_order' => $stepDef['step_order'],
                'approver_type' => $stepDef['approver_type'],
                'resolved_approver_user_id' => $approver,
                'status' => $approver ? 'pending' : 'skipped',
            ], false);
        }

        // If step 1 itself couldn't be resolved to anyone, advance past it
        // immediately rather than leaving current_step_order pointed at a
        // step that will never receive a decision.
        $this->advancePastUnresolvedSteps($request['id'], (int) $request['current_step_order']);

        return $request;
    }

    /**
     * Records one decision on the request's CURRENT step and either advances
     * to the next step (on 'approved' with more steps remaining) or
     * completes the request and applies the outcome to the underlying object
     * (on 'approved' with no more steps, or on 'rejected'/'returned').
     */
    public function decide(string $requestId, string $decision, string $actingUserId, ?string $comments = null): array
    {
        if (!in_array($decision, ['approved', 'rejected', 'returned'], true)) {
            throw new \InvalidArgumentException("Invalid decision: {$decision}");
        }

        $request = $this->supabase->first('approval_requests', ['id' => 'eq.' . $requestId, 'select' => '*']);

        if (!$request) {
            throw new \RuntimeException('Approval request not found.');
        }

        if ($request['status'] !== 'pending') {
            throw new \RuntimeException('This request has already been decided.');
        }

        $step = $this->supabase->first('approval_request_steps', [
            'request_id' => 'eq.' . $requestId,
            'step_order' => 'eq.' . $request['current_step_order'],
            'select' => '*',
        ]);

        if (!$step) {
            throw new \RuntimeException('Current approval step not found.');
        }

        $this->supabase->update('approval_request_steps', ['id' => 'eq.' . $step['id']], [
            'status' => $decision,
            'acted_by' => $actingUserId,
            'acted_at' => now()->toIso8601String(),
            'comments' => $comments,
        ], false);

        if ($decision !== 'approved') {
            $this->completeRequest($request, $decision, $actingUserId);

            return $request;
        }

        $nextStep = $this->supabase->first('approval_request_steps', [
            'request_id' => 'eq.' . $requestId,
            'step_order' => 'eq.' . ($request['current_step_order'] + 1),
            'select' => '*',
        ]);

        if ($nextStep) {
            $this->supabase->update('approval_requests', ['id' => 'eq.' . $requestId], [
                'current_step_order' => $nextStep['step_order'],
            ], false);

            $this->advancePastUnresolvedSteps($requestId, (int) $nextStep['step_order']);
        } else {
            $this->completeRequest($request, 'approved', $actingUserId);
        }

        return $request;
    }

    /**
     * Skips forward over any consecutive 'skipped' steps starting at
     * `$fromStepOrder`, so an unresolvable step (see class docblock) never
     * leaves `current_step_order` stuck on it. If skipping reaches the end
     * with no further pending step, completes the request as approved.
     */
    private function advancePastUnresolvedSteps(string $requestId, int $fromStepOrder): void
    {
        $step = $this->supabase->first('approval_request_steps', [
            'request_id' => 'eq.' . $requestId,
            'step_order' => 'eq.' . $fromStepOrder,
            'select' => '*',
        ]);

        if (!$step || $step['status'] !== 'skipped') {
            return;
        }

        $nextStep = $this->supabase->first('approval_request_steps', [
            'request_id' => 'eq.' . $requestId,
            'step_order' => 'eq.' . ($fromStepOrder + 1),
            'select' => '*',
        ]);

        if ($nextStep) {
            $this->supabase->update('approval_requests', ['id' => 'eq.' . $requestId], [
                'current_step_order' => $nextStep['step_order'],
            ], false);

            $this->advancePastUnresolvedSteps($requestId, (int) $nextStep['step_order']);

            return;
        }

        $request = $this->supabase->first('approval_requests', ['id' => 'eq.' . $requestId, 'select' => '*']);
        $this->completeRequest($request, 'approved', null);
    }

    private function completeRequest(array $request, string $finalStatus, ?string $actingUserId): void
    {
        $this->supabase->update('approval_requests', ['id' => 'eq.' . $request['id']], [
            'status' => $finalStatus,
            'completed_at' => now()->toIso8601String(),
        ], false);

        $this->applyToObject($request, $finalStatus, $actingUserId);
    }

    private function applyToObject(array $request, string $finalStatus, ?string $actingUserId): void
    {
        if ($request['object_type'] === 'kpi_submission') {
            $this->supabase->update('kpi_submissions', ['id' => 'eq.' . $request['object_id']], [
                'status' => $finalStatus,
                'decided_by' => $actingUserId,
                'decided_at' => now()->toIso8601String(),
            ], false);

            return;
        }

        if ($request['object_type'] === 'kpi_target_revision') {
            $this->supabase->update('kpi_target_revisions', ['id' => 'eq.' . $request['object_id']], [
                'status' => $finalStatus,
                'decided_by' => $actingUserId,
                'decided_at' => now()->toIso8601String(),
            ], false);

            // Only after the revision row itself is marked 'approved' can
            // apply_approved_target_revision()'s own authorization check
            // (which re-reads this row and its approval_request_steps) pass.
            if ($finalStatus === 'approved') {
                $this->supabase->rpc('apply_approved_target_revision', ['revision_id' => $request['object_id']]);
            }
        }
    }
}
