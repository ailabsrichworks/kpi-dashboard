<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * BTS-only mechanism (Quarter Control page) letting a Manager's own VP stand
 * in as appraiser for that Manager's Executives while the Manager is on long
 * leave -- e.g. dept Program: Pn Izzati (Manager) on long leave, VP Program
 * takes over appraising her Executives. One active delegate per manager_id
 * (database/sql/create_appraiser_delegations.sql); BTS deletes the row when
 * the manager is back, reverting to the normal chain automatically.
 *
 * Deliberately one hop only, Manager -> VP: `nextParentId()` below is the
 * single place that decides "who's next up the chain", shared by both
 * PerformanceController::resolveAppraiserLevel() (access control -- can this
 * viewer open/score the appraisal) and NotificationService::appraiserChainFor()
 * (who gets notified), so the two can never resolve a different person for
 * the same employee. A VP's own appraiser (SLT) is never looked up here at
 * all -- the role-priority match() below has no case that reads past 'VP',
 * so there's structurally no way to delegate a VP's own duty onward if the
 * VP is also away; that's an intentional limit of this feature, not a gap.
 */
class AppraiserDelegationService
{
    public function __construct(private SupabaseService $supabase)
    {
    }

    /**
     * The next id up $employee's appraiser chain -- manager_id/vp_id per
     * role with reports_to_id as fallback (matches
     * PerformanceController::resolveAppraiserLevel()'s own priority) -- with
     * any active delegation substituted in. Null once the chain has nowhere
     * left to go (SLT, or a role/data gap).
     */
    public function nextParentId(array $employee): ?string
    {
        $role = strtoupper(trim($employee['role'] ?? ''));

        $parentId = match ($role) {
            'EXECUTIVE' => $employee['manager_id'] ?? $employee['vp_id'] ?? null,
            'MANAGER'   => $employee['vp_id'] ?? $employee['reports_to_id'] ?? null,
            'VP'        => $employee['reports_to_id'] ?? null,
            default     => null,
        };

        if (empty($parentId)) {
            return null;
        }

        return $this->activeDelegate($parentId) ?? $parentId;
    }

    /**
     * The employee id currently standing in for $managerId, or null if no
     * delegation is active for them.
     *
     * Called on every hop of every appraiser resolution (resolveAppraiserLevel,
     * appraiserChainFor) for every employee, delegated or not — so this must
     * never take down the ordinary appraisal flow. Fails open (treats it as
     * "no delegation") on any Supabase error, including the table not
     * existing yet (database/sql/create_appraiser_delegations.sql not run
     * against production yet) rather than throwing and breaking every
     * appraisal page load for everyone.
     */
    public function activeDelegate(string $managerId): ?string
    {
        try {
            $row = $this->supabase->first('appraiser_delegations', [
                'manager_id' => 'eq.' . $managerId,
                'select'     => 'delegate_to_id',
            ]);
        } catch (\Throwable $e) {
            Log::error('AppraiserDelegationService::activeDelegate failed', ['error' => $e->getMessage()]);
            return null;
        }

        return $row['delegate_to_id'] ?? null;
    }

    /**
     * Every active delegation row -- used by the BTS Quarter Control page to
     * list who's currently standing in for whom. Same fail-open reasoning as
     * activeDelegate() -- the page should still render (just showing nothing
     * delegated yet) if the table hasn't been created in Supabase yet.
     */
    public function all(): array
    {
        try {
            return $this->supabase->get('appraiser_delegations', ['select' => '*']) ?? [];
        } catch (\Throwable $e) {
            Log::error('AppraiserDelegationService::all failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function setDelegate(string $managerId, string $delegateToId, ?string $reason, ?string $createdBy, ?string $createdByName): array
    {
        $rows = $this->supabase->upsert('appraiser_delegations', [
            'manager_id'      => $managerId,
            'delegate_to_id'  => $delegateToId,
            'reason'          => $reason,
            'created_by'      => $createdBy,
            'created_by_name' => $createdByName,
            'created_at'      => now()->toIso8601String(),
        ], 'manager_id');

        return $rows[0] ?? [];
    }

    public function clearDelegate(string $managerId): void
    {
        $this->supabase->delete('appraiser_delegations', ['manager_id' => 'eq.' . $managerId]);
    }

    /**
     * Section 7's ordered, role-aware occupant list for one appraisee — the
     * single source of truth for "who fills Part A/B/C", used by both
     * PerformanceController::resolveAppraiserLevel() (access control) and its
     * notification-escalation logic, so the two can never disagree.
     *
     * Part A ("Appraiser") is always whoever nextParentId() resolves first,
     * whatever their real title — a Manager appraisee's own approver is
     * routinely a VP, and that's normal, not a gap to fill.
     *
     * Part B ("Remarks by VP") only exists if THAT person's own approver is a
     * genuine VP. When it isn't — e.g. a Manager or Executive reporting
     * straight to a VP with no separate Manager in between, so the VP already
     * gave their input as Part A — a second "VP remarks" step would just be
     * asking the same person twice. In that case Part B is skipped entirely
     * and this hop goes straight to Part C (SLT).
     *
     * Returns an ordered list of ['level' => 'manager'|'vp'|'slt', 'id' => string],
     * containing only the parts that actually apply to this employee.
     */
    public function resolveSection7Chain(array $employee, callable $getParent): array
    {
        $chain = [];

        $hop1Id = $this->nextParentId($employee);
        if (empty($hop1Id)) {
            return $chain;
        }
        $chain[] = ['level' => 'manager', 'id' => $hop1Id];

        $hop1 = $getParent($hop1Id);
        if (empty($hop1)) {
            return $chain;
        }

        $hop2Id = $this->nextParentId($hop1);
        if (empty($hop2Id)) {
            return $chain;
        }

        $hop2 = $getParent($hop2Id);
        if (empty($hop2)) {
            // Can't resolve this hop's own record (inactive/missing employee)
            // — stop here rather than guessing they're SLT.
            return $chain;
        }
        $hop2Role = strtoupper(trim($hop2['role'] ?? ''));

        if ($hop2Role === 'VP') {
            $chain[] = ['level' => 'vp', 'id' => $hop2Id];

            $hop3Id = $this->nextParentId($hop2);
            if (!empty($hop3Id)) {
                $chain[] = ['level' => 'slt', 'id' => $hop3Id];
            }
        } else {
            // Chain skipped a genuine VP tier — hop2 is effectively SLT
            // relative to this employee, so it fills Part C, not Part B.
            $chain[] = ['level' => 'slt', 'id' => $hop2Id];
        }

        return $chain;
    }
}
