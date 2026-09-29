<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * KpiController::requestTargetChange()/requestDelete() — the self-service
 * half of the two new request types Platform\ApprovalController decides on.
 * See 2026_09_29_080000_add_kpi_target_change_and_delete_requests.php for
 * why a non-admin has no direct-write path to either `target` or a KPI
 * delete at all.
 */
class KpiTargetChangeAndDeleteRequestTest extends TestCase
{
    private function fakeToken(string $sub): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $sub, 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    private function fakeMemberSession(): array
    {
        return [
            '*/rest/v1/users*' => Http::response([[
                'id' => 'member-id', 'name' => 'Member', 'email' => 'member@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-a', 'role' => 'employee', 'status' => 'active',
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
        ];
    }

    public function test_the_owner_can_request_a_target_change(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*/rest/v1/kpis*' => Http::response([[
                'id' => 'kpi-1', 'name' => 'Sales', 'target' => 10, 'assigned_user_id' => 'member-id',
            ]], 200),
            '*/rest/v1/kpi_target_change_requests*' => Http::sequence()
                ->push([], 200) // no existing pending request
                ->push([['id' => 'tcr-1']], 201), // the insert itself
            '*/rest/v1/admin_action_logs*' => Http::response([['id' => 'log-1']], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/kpis/kpi-1/target-change-requests', [
                'new_target' => 20,
                'reason' => 'Market conditions changed significantly this quarter',
            ]);

        $response->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/rest/v1/kpi_target_change_requests')
            && ($request['new_target'] ?? null) === 20
            && ($request['old_target'] ?? null) === 10);
    }

    public function test_a_non_owner_cannot_request_a_target_change_on_someone_elses_kpi(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*/rest/v1/kpis*' => Http::response([[
                'id' => 'kpi-1', 'name' => 'Sales', 'target' => 10, 'assigned_user_id' => 'someone-else-id',
            ]], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/kpis/kpi-1/target-change-requests', [
                'new_target' => 20,
                'reason' => 'Market conditions changed significantly this quarter',
            ]);

        $response->assertSessionHas('error');

        Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/rest/v1/kpi_target_change_requests'));
    }

    public function test_the_owner_can_request_deletion(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*/rest/v1/kpis*' => Http::response([[
                'id' => 'kpi-1', 'name' => 'Old KPI', 'assigned_user_id' => 'member-id',
            ]], 200),
            '*/rest/v1/kpi_delete_requests*' => Http::sequence()
                ->push([], 200)
                ->push([['id' => 'dr-1']], 201),
            '*/rest/v1/admin_action_logs*' => Http::response([['id' => 'log-1']], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/kpis/kpi-1/delete-requests', [
                'reason' => 'No longer tracked by the team',
            ]);

        $response->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/rest/v1/kpi_delete_requests'));
    }

    public function test_a_duplicate_pending_target_change_request_is_refused(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*/rest/v1/kpis*' => Http::response([[
                'id' => 'kpi-1', 'name' => 'Sales', 'target' => 10, 'assigned_user_id' => 'member-id',
            ]], 200),
            '*/rest/v1/kpi_target_change_requests*' => Http::response([['id' => 'existing']], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/kpis/kpi-1/target-change-requests', [
                'new_target' => 20,
                'reason' => 'Market conditions changed significantly this quarter',
            ]);

        $response->assertSessionHas('error');
    }
}
