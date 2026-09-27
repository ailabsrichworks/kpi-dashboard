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
        $parentId = $this->naturalParentId($employee);

        if (empty($parentId)) {
            return null;
        }

        return $this->activeDelegate($parentId) ?? $parentId;
    }

    /**
     * $employee's next-up id per the org chart alone -- manager_id/vp_id per
     * role with reports_to_id as fallback -- with NO delegation substitution
     * applied. Exists so resolveSection7Chain() below can tell "did this hop
     * land here through the real org chart, or only because they're standing
     * in for someone else" — see its own docblock for why that distinction
     * matters.
     */
    private function naturalParentId(array $employee): ?string
    {
        $role = strtoupper(trim($employee['role'] ?? ''));

        return match ($role) {
            'EXECUTIVE' => $employee['manager_id'] ?? $employee['vp_id'] ?? null,
            'MANAGER'   => $employee['vp_id'] ?? $employee['reports_to_id'] ?? null,
            'VP'        => $employee['reports_to_id'] ?? null,
            default     => null,
        };
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
     * That skip is only trustworthy when Part A landed on hop1 through the
     * real org chart, though. When hop1 is only there via an active
     * delegation (a VP standing in for this employee's own absent Manager),
     * hop1 is wearing the Part A hat purely as a temporary stand-in — it
     * says nothing about whether hop1's OWN, permanent VP-tier duty for this
     * employee should be skipped too. So for a delegated hop1, the skip
     * decision isn't made from hop2's role at all: it checks whether hop2
     * actually has someone further up to escalate to. If they do, hop2 is
     * kept as a genuine, distinct Part B and that further person becomes
     * Part C — the appraisee's real chain doesn't lose a checkpoint (and
     * SLT doesn't lose the gate that waits on it) just because the stand-in
     * happens to already hold the VP title elsewhere. If hop2 genuinely has
     * nobody further up, they're the last rung either way, so they're
     * labelled 'slt' same as the non-delegated case.
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
        $naturalHop1Id = $this->naturalParentId($employee);
        $hop1Delegated = $naturalHop1Id !== null && $naturalHop1Id !== $hop1Id;

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
        // Only worth looking up when it could actually change the outcome
        // below — a non-delegated, non-VP hop2 is always terminal, no need
        // to spend a lookup confirming that.
        $hop3Id = ($hop2Role === 'VP' || $hop1Delegated) ? $this->nextParentId($hop2) : null;

        if ($hop2Role === 'VP' || ($hop1Delegated && !empty($hop3Id))) {
            // Either hop2 is a genuine VP, or hop1 only got here via
            // delegation and hop2 genuinely has someone further up — in
            // both cases hop2 is a real, distinct Part B, not folded into
            // Part C just because a role-based shortcut said to.
            $chain[] = ['level' => 'vp', 'id' => $hop2Id];

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
