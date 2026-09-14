<?php

namespace Tests\Unit;

use App\Services\AppraiserDelegationService;
use App\Services\SupabaseService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Manager -> VP substitution feature (see AppraiserDelegationService's
 * own docblock): when BTS activates a delegation for a Manager on long
 * leave, nextParentId() must hand back the delegate instead of the normal
 * manager_id/vp_id/reports_to_id chain -- for every role, and unaffected
 * when no delegation exists for that particular parent id.
 */
class AppraiserDelegationServiceTest extends TestCase
{
    private function service(): AppraiserDelegationService
    {
        return new AppraiserDelegationService(app(SupabaseService::class));
    }

    public function test_next_parent_id_for_executive_uses_manager_id_when_no_delegation_exists(): void
    {
        Http::fake([
            '*/rest/v1/appraiser_delegations*' => Http::response([], 200),
        ]);

        $executive = ['role' => 'EXECUTIVE', 'manager_id' => 'manager-1', 'vp_id' => 'vp-1', 'reports_to_id' => null];

        $this->assertSame('manager-1', $this->service()->nextParentId($executive));
    }

    public function test_next_parent_id_for_executive_substitutes_the_delegate_when_their_manager_is_delegated(): void
    {
        Http::fake([
            '*/rest/v1/appraiser_delegations*' => Http::response([
                ['manager_id' => 'manager-1', 'delegate_to_id' => 'vp-2'],
            ], 200),
        ]);

        $executive = ['role' => 'EXECUTIVE', 'manager_id' => 'manager-1', 'vp_id' => 'vp-1', 'reports_to_id' => null];

        $this->assertSame('vp-2', $this->service()->nextParentId($executive));
    }

    public function test_next_parent_id_for_manager_falls_back_to_vp_id_and_is_unaffected_by_an_unrelated_delegation(): void
    {
        // Http::fake matches by URL pattern, not by the actual querystring
        // filter -- a bare wildcard would return this row regardless of
        // which manager_id was really being asked about, which would prove
        // nothing. Scoping the fake to the exact filtered URL forces the
        // lookup to genuinely ask for "vp-1" and get an empty result, the
        // same as a real "some-other-manager"-only row would produce.
        Http::fake([
            '*manager_id=eq.some-other-manager*' => Http::response([
                ['manager_id' => 'some-other-manager', 'delegate_to_id' => 'vp-2'],
            ], 200),
            '*/rest/v1/appraiser_delegations*' => Http::response([], 200),
        ]);

        $manager = ['role' => 'MANAGER', 'manager_id' => null, 'vp_id' => 'vp-1', 'reports_to_id' => 'slt-1'];

        $this->assertSame('vp-1', $this->service()->nextParentId($manager));
    }

    public function test_next_parent_id_for_vp_uses_reports_to_id_when_no_delegation_matches_it(): void
    {
        // A VP's own appraiser (their reports_to_id, typically SLT) is still
        // run through the same delegation lookup as every other hop -- it's
        // just that no row can ever match it in practice, because
        // AppraiserDelegationController::store() only ever writes rows keyed
        // by a MANAGER's id. That guarantee lives on the write side, not by
        // skipping the read here.
        Http::fake(['*/rest/v1/appraiser_delegations*' => Http::response([], 200)]);

        $vp = ['role' => 'VP', 'manager_id' => null, 'vp_id' => null, 'reports_to_id' => 'slt-1'];

        $this->assertSame('slt-1', $this->service()->nextParentId($vp));
    }

    public function test_next_parent_id_returns_null_when_role_has_no_parent(): void
    {
        Http::fake(['*/rest/v1/appraiser_delegations*' => Http::response([], 200)]);

        $slt = ['role' => 'SLT', 'manager_id' => null, 'vp_id' => null, 'reports_to_id' => null];

        $this->assertNull($this->service()->nextParentId($slt));
    }

    public function test_active_delegate_fails_open_to_null_when_the_table_does_not_exist_yet(): void
    {
        Http::fake([
            '*/rest/v1/appraiser_delegations*' => Http::response([
                'code' => 'PGRST205', 'message' => "Could not find the table 'public.appraiser_delegations'",
            ], 404),
        ]);

        $this->assertNull($this->service()->activeDelegate('manager-1'));
    }

    public function test_all_fails_open_to_empty_array_when_the_table_does_not_exist_yet(): void
    {
        Http::fake([
            '*/rest/v1/appraiser_delegations*' => Http::response([
                'code' => 'PGRST205', 'message' => "Could not find the table 'public.appraiser_delegations'",
            ], 404),
        ]);

        $this->assertSame([], $this->service()->all());
    }

    private function getParentFor(array $employees): \Closure
    {
        return fn (string $id) => $employees[$id] ?? null;
    }

    public function test_resolve_section7_chain_skips_part_b_when_manager_reports_straight_to_vp(): void
    {
        Http::fake(['*/rest/v1/appraiser_delegations*' => Http::response([], 200)]);

        // Manager's own approver is a VP with no separate Manager in
        // between -- Part B would just be asking the same person twice, so
        // it's skipped and the VP fills Part C directly.
        $manager = ['id' => 'manager-1', 'role' => 'MANAGER', 'vp_id' => 'vp-1', 'reports_to_id' => null];
        $vp      = ['id' => 'vp-1', 'role' => 'VP', 'reports_to_id' => 'slt-1'];
        $slt     = ['id' => 'slt-1', 'role' => 'SLT', 'reports_to_id' => null];

        $chain = $this->service()->resolveSection7Chain($manager, $this->getParentFor([
            'vp-1'  => $vp,
            'slt-1' => $slt,
        ]));

        $this->assertSame([
            ['level' => 'manager', 'id' => 'vp-1'],
            ['level' => 'slt', 'id' => 'slt-1'],
        ], $chain);
    }

    public function test_resolve_section7_chain_keeps_part_b_for_a_genuine_three_tier_chain(): void
    {
        Http::fake(['*/rest/v1/appraiser_delegations*' => Http::response([], 200)]);

        $executive = ['id' => 'exec-1', 'role' => 'EXECUTIVE', 'manager_id' => 'manager-1'];
        $manager   = ['id' => 'manager-1', 'role' => 'MANAGER', 'vp_id' => 'vp-1'];
        $vp        = ['id' => 'vp-1', 'role' => 'VP', 'reports_to_id' => 'slt-1'];

        $chain = $this->service()->resolveSection7Chain($executive, $this->getParentFor([
            'manager-1' => $manager,
            'vp-1'      => $vp,
        ]));

        $this->assertSame([
            ['level' => 'manager', 'id' => 'manager-1'],
            ['level' => 'vp', 'id' => 'vp-1'],
            ['level' => 'slt', 'id' => 'slt-1'],
        ], $chain);
    }

    public function test_resolve_section7_chain_does_not_collapse_part_b_when_part_a_is_a_delegate_with_someone_further_up(): void
    {
        // Manager-1 is on leave, delegated to VP-2, so hop1 lands there only
        // via delegation, not the executive's own manager_id. VP-2's own
        // approver (hop2) has a role field the role-based shortcut wouldn't
        // recognise as 'VP', but genuinely has someone further up -- the
        // non-delegated shortcut would fold them straight into Part C and
        // never reach that further approver at all.
        Http::fake([
            '*manager_id=eq.manager-1*' => Http::response([
                ['manager_id' => 'manager-1', 'delegate_to_id' => 'vp-2'],
            ], 200),
            '*/rest/v1/appraiser_delegations*' => Http::response([], 200),
        ]);

        $executive = ['id' => 'exec-1', 'role' => 'EXECUTIVE', 'manager_id' => 'manager-1'];
        $delegate  = ['id' => 'vp-2', 'role' => 'VP', 'reports_to_id' => 'hop2-1'];
        $hop2      = ['id' => 'hop2-1', 'role' => 'MANAGER', 'vp_id' => 'slt-1'];

        $chain = $this->service()->resolveSection7Chain($executive, $this->getParentFor([
            'vp-2'   => $delegate,
            'hop2-1' => $hop2,
        ]));

        $this->assertSame([
            ['level' => 'manager', 'id' => 'vp-2'],
            ['level' => 'vp', 'id' => 'hop2-1'],
            ['level' => 'slt', 'id' => 'slt-1'],
        ], $chain);
    }

    public function test_resolve_section7_chain_still_ends_at_slt_when_a_delegates_own_approver_is_genuinely_terminal(): void
    {
        // Same delegation, but this time the delegate's own approver really
        // is the end of the line (role 'SLT' has no further match() case) --
        // the delegate-aware branch must not invent a Part B/C split where
        // there's nothing further to escalate to; behaviour matches the
        // non-delegated case exactly.
        Http::fake([
            '*manager_id=eq.manager-1*' => Http::response([
                ['manager_id' => 'manager-1', 'delegate_to_id' => 'vp-2'],
            ], 200),
            '*/rest/v1/appraiser_delegations*' => Http::response([], 200),
        ]);

        $executive = ['id' => 'exec-1', 'role' => 'EXECUTIVE', 'manager_id' => 'manager-1'];
        $delegate  = ['id' => 'vp-2', 'role' => 'VP', 'reports_to_id' => 'slt-1'];
        $slt       = ['id' => 'slt-1', 'role' => 'SLT', 'reports_to_id' => null];

        $chain = $this->service()->resolveSection7Chain($executive, $this->getParentFor([
            'vp-2'  => $delegate,
            'slt-1' => $slt,
        ]));

        $this->assertSame([
            ['level' => 'manager', 'id' => 'vp-2'],
            ['level' => 'slt', 'id' => 'slt-1'],
        ], $chain);
    }
}
