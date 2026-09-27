<?php

namespace App\Services;

/**
 * Performix Company Platform, Phase 1 hardening (Part 5): resolves which
 * approval workflow applies to a (company, workflow_type) pair, and which
 * real user should be asked to decide each step for a specific submitter.
 *
 * A company is never required to configure a workflow before approval-gated
 * features work — `resolveWorkflow()` falls back to a built-in default chain
 * per workflow type (documented in `builtInDefaultSteps()`) so Phase 1's
 * governance loop (submit -> approve -> calculate) works out of the box, the
 * same way the rest of this codebase prefers a computed default over
 * requiring every company to pre-seed configuration (onboarding_status,
 * company lifecycle, etc.).
 *
 * Step resolution happens ONCE, at request-creation time, into
 * `approval_request_steps.resolved_approver_user_id` — a later role or
 * manager change never reassigns an approval already in flight.
 */
class ApprovalWorkflowService
{
    public function __construct(private readonly SupabaseUserService $supabase)
    {
    }

    /**
     * @return array{workflow: array|null, steps: array[]}
     */
    public function resolveWorkflow(string $companyId, string $workflowType): array
    {
        $workflow = $this->supabase->first('approval_workflows', [
            'company_id' => 'eq.' . $companyId,
            'workflow_type' => 'eq.' . $workflowType,
            'is_default' => 'eq.true',
            'is_active' => 'eq.true',
            'select' => '*',
        ]);

        if ($workflow) {
            $steps = $this->supabase->get('approval_workflow_steps', [
                'workflow_id' => 'eq.' . $workflow['id'],
                'select' => '*',
                'order' => 'step_order.asc',
            ]);

            return ['workflow' => $workflow, 'steps' => $steps];
        }

        return ['workflow' => null, 'steps' => $this->builtInDefaultSteps($workflowType)];
    }

    /**
     * The zero-configuration fallback chain, matching the spec's own
     * "Workflow A" (Employee -> Manager -> Approved) for actual submissions,
     * and a single company_admin checkpoint for everything else until a
     * company configures something more specific.
     */
    private function builtInDefaultSteps(string $workflowType): array
    {
        return match ($workflowType) {
            'actual_submission' => [
                ['step_order' => 1, 'approver_type' => 'submitter_manager', 'approver_role' => null, 'approver_scope' => null],
            ],
            default => [
                ['step_order' => 1, 'approver_type' => 'role', 'approver_role' => 'company_admin', 'approver_scope' => 'company'],
            ],
        };
    }

    /**
     * Candidate user id(s) for one step, before self-approval filtering or
     * the company_admin fallback are applied (see `resolveApproverForStep()`).
     */
    public function resolveCandidates(array $step, string $companyId, ?string $departmentId, string $submitterUserId): array
    {
        return match ($step['approver_type']) {
            'specific_user' => $step['approver_user_id'] ? [$step['approver_user_id']] : [],

            // The submitter's own direct manager (department_users.manager_user_id) —
            // resolved once here, relative to the ORIGINAL submitter only. A
            // workflow step for "the manager's manager" is expressed as a
            // separate 'role' step (e.g. hod/department), not a chained
            // submitter_manager lookup — this service does not walk the
            // management chain more than one level.
            'submitter_manager' => collect($this->supabase->get('department_users', [
                'user_id' => 'eq.' . $submitterUserId,
                'select' => 'manager_user_id',
            ]))->pluck('manager_user_id')->filter()->values()->all(),

            'role' => $this->resolveRoleCandidates($step, $companyId, $departmentId),

            default => [],
        };
    }

    private function resolveRoleCandidates(array $step, string $companyId, ?string $departmentId): array
    {
        if (($step['approver_scope'] ?? 'company') === 'department' && $departmentId) {
            $rows = $this->supabase->get('department_users', [
                'department_id' => 'eq.' . $departmentId,
                'role' => 'eq.' . $step['approver_role'],
                'select' => 'user_id',
            ]);
        } else {
            $rows = $this->supabase->get('company_users', [
                'company_id' => 'eq.' . $companyId,
                'role' => 'eq.' . $step['approver_role'],
                'status' => 'eq.active',
                'select' => 'user_id',
            ]);
        }

        return array_column($rows, 'user_id');
    }

    /**
     * The single resolved approver for one step: the first candidate (self
     * excluded unless `$allowSelfApproval`), or — if that leaves nobody, e.g.
     * no HOD assigned in this department yet — any active company_admin, so
     * a request can never get permanently stuck for lack of a resolvable
     * approver. Returns null only if there is truly nobody at all (a company
     * with zero company_admins, which `prevent_zero_company_admins` already
     * makes impossible in practice).
     */
    public function resolveApproverForStep(array $step, string $companyId, ?string $departmentId, string $submitterUserId, bool $allowSelfApproval): ?string
    {
        $candidates = $this->resolveCandidates($step, $companyId, $departmentId, $submitterUserId);

        if (!$allowSelfApproval) {
            $candidates = array_values(array_filter($candidates, fn ($id) => $id !== $submitterUserId));
        }

        if (!empty($candidates)) {
            return $candidates[0];
        }

        $admins = array_column($this->supabase->get('company_users', [
            'company_id' => 'eq.' . $companyId,
            'role' => 'eq.company_admin',
            'status' => 'eq.active',
            'select' => 'user_id',
        ]), 'user_id');

        if (!$allowSelfApproval) {
            $admins = array_values(array_filter($admins, fn ($id) => $id !== $submitterUserId));
        }

        return $admins[0] ?? null;
    }
}
