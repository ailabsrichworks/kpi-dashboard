<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Platform\AppraiserDelegationController — see
 * 2026_09_29_090000_create_appraiser_delegations.php for the design
 * adaptation from legacy (Company-Admin-only instead of BTS-only; the
 * delegate is always the manager's own manager_user_id, never client-chosen).
 *
 * Named distinctly from the pre-existing `AppraiserDelegationControllerTest`
 * (legacy's `App\Http\Controllers\AppraiserDelegationController`) — same
 * concept ported to the Platform, but a genuinely different controller/class
 * under the same simple name, so the two test classes must not collide.
 */
class PlatformAppraiserDelegationControllerTest extends TestCase
{
    private function fakeToken(string $sub): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $sub, 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    private function fakeAdminSession(): array
    {
        return [
            '*/rest/v1/users*' => Http::response([[
                'id' => 'admin-id', 'name' => 'Admin', 'email' => 'admin@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-a', 'role' => 'company_admin', 'status' => 'active',
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([['id' => 'log-1']], 201),
        ];
    }

    public function test_delegating_computes_the_delegate_server_side_from_the_managers_own_manager(): void
    {
        $managerId = '11111111-1111-1111-1111-111111111111';
        $vpId = '22222222-2222-2222-2222-222222222222';

        // The manager's own company_users row -- their manager_user_id
        // (the VP) is what the controller must use as the delegate,
        // regardless of what (if anything) the client submits.
        Http::fake(array_merge($this->fakeAdminSession(), [
            '*/rest/v1/company_users*' => Http::sequence()
                ->push([['company_id' => 'company-a', 'role' => 'company_admin', 'status' => 'active']], 200) // PlatformAuth membership
                ->push([['user_id' => $managerId, 'manager_user_id' => $vpId, 'users' => ['name' => 'Manager']]], 200), // the manager lookup
            '*/rest/v1/appraiser_delegations*' => Http::sequence()
                ->push([], 200) // no existing delegation for this manager
                ->push([['id' => 'del-1']], 201), // the insert
            '*/rest/v1/notifications*' => Http::response([['id' => 'n-1']], 201),
        ]));

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('admin-auth-id')])
            ->post('/platform/companies/company-a/appraiser-delegations', [
                'manager_user_id' => $managerId,
                'reason' => 'On leave',
            ]);

        $response->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/rest/v1/appraiser_delegations')
            && ($request['delegate_user_id'] ?? null) === $vpId
            && ($request['manager_user_id'] ?? null) === $managerId);
    }

    public function test_delegating_for_a_manager_with_no_one_above_them_is_refused(): void
    {
        $managerId = '11111111-1111-1111-1111-111111111111';

        Http::fake(array_merge($this->fakeAdminSession(), [
            '*/rest/v1/company_users*' => Http::sequence()
                ->push([['company_id' => 'company-a', 'role' => 'company_admin', 'status' => 'active']], 200)
                ->push([['user_id' => $managerId, 'manager_user_id' => null, 'users' => ['name' => 'Manager']]], 200),
        ]));

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('admin-auth-id')])
            ->post('/platform/companies/company-a/appraiser-delegations', [
                'manager_user_id' => $managerId,
            ]);

        $response->assertSessionHas('error');

        Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/rest/v1/appraiser_delegations'));
    }

    public function test_a_plain_member_cannot_create_a_delegation(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'member-id', 'name' => 'Member', 'email' => 'member@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-a', 'role' => 'employee', 'status' => 'active',
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/appraiser-delegations', [
                'manager_user_id' => 'manager-id',
            ]);

        $response->assertStatus(403);
    }

    public function test_ending_a_delegation_deletes_it(): void
    {
        Http::fake($this->fakeAdminSession() + [
            '*/rest/v1/appraiser_delegations*' => Http::response([['id' => 'del-1', 'delegate_user_id' => 'vp-id']], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('admin-auth-id')])
            ->delete('/platform/companies/company-a/appraiser-delegations/manager-id');

        $response->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/rest/v1/appraiser_delegations'));
    }
}
