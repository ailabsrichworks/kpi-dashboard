<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Platform\ApprovalController — the unified Approval Center. See
 * database/migrations/2026_09_29_080000_add_kpi_target_change_and_delete_requests.php
 * for why target-change/delete-request are their own tables rather than
 * reusing legacy's `[[WC]]`-prefix-tagged single-table hack.
 */
class ApprovalControllerTest extends TestCase
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

    public function test_a_plain_member_cannot_open_the_approval_center(): void
    {
        Http::fake($this->fakeMemberSession());

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->get('/platform/companies/company-a/approvals');

        $response->assertStatus(403);
    }

    public function test_the_index_aggregates_every_pending_request_type_with_a_tag(): void
    {
        Http::fake($this->fakeAdminSession() + [
            '*/rest/v1/companies*' => Http::response([['id' => 'company-a', 'name' => 'Company A', 'code' => 'COA']], 200),
            '*/rest/v1/kpi_target_change_requests*' => Http::response([
                ['id' => 'tcr-1', 'kpi_id' => 'kpi-1', 'old_target' => 10, 'new_target' => 20, 'reason' => 'growth', 'created_at' => now()->toIso8601String(), 'kpis' => ['name' => 'Sales'], 'users' => ['name' => 'Alice', 'email' => 'alice@example.com']],
            ], 200),
            '*/rest/v1/kpi_delete_requests*' => Http::response([], 200),
            '*/rest/v1/kpi_weight_change_requests*' => Http::response([], 200),
            '*/rest/v1/kpi_quarter_update_requests*' => Http::response([], 200),
            '*/rest/v1/kpi_quarters*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('admin-auth-id')])
            ->get('/platform/companies/company-a/approvals');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Platform/Approvals/Index')
            ->where('counts.target_change', 1)
            ->where('pending.0.type', 'target_change'));
    }

    public function test_approving_a_target_change_applies_it_to_the_kpi(): void
    {
        Http::fake($this->fakeAdminSession() + [
            '*/rest/v1/kpi_target_change_requests*' => Http::response([[
                'id' => 'tcr-1', 'kpi_id' => 'kpi-1', 'new_target' => 50, 'requested_by' => 'member-id',
                'kpis' => ['name' => 'Sales'],
            ]], 200),
            '*/rest/v1/kpis*' => Http::response([['id' => 'kpi-1']], 200),
            '*/rest/v1/notifications*' => Http::response([['id' => 'n-1']], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('admin-auth-id')])
            ->post('/platform/companies/company-a/target-change-requests/tcr-1/approve');

        $response->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && str_contains($request->url(), '/rest/v1/kpis')
            && ($request['target'] ?? null) === 50);
    }

    public function test_approving_a_delete_request_deletes_the_kpi(): void
    {
        Http::fake($this->fakeAdminSession() + [
            '*/rest/v1/kpi_delete_requests*' => Http::response([[
                'id' => 'dr-1', 'kpi_id' => 'kpi-1', 'requested_by' => 'member-id', 'reason' => 'no longer tracked',
                'kpis' => ['name' => 'Old KPI'],
            ]], 200),
            '*/rest/v1/kpis*' => Http::response([], 200),
            '*/rest/v1/notifications*' => Http::response([['id' => 'n-1']], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('admin-auth-id')])
            ->post('/platform/companies/company-a/delete-requests/dr-1/approve');

        $response->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/rest/v1/kpis'));
    }

    public function test_rejecting_a_delete_request_never_deletes_the_kpi(): void
    {
        Http::fake($this->fakeAdminSession() + [
            '*/rest/v1/kpi_delete_requests*' => Http::response([[
                'id' => 'dr-1', 'requested_by' => 'member-id', 'kpis' => ['name' => 'Old KPI'],
            ]], 200),
            '*/rest/v1/notifications*' => Http::response([['id' => 'n-1']], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('admin-auth-id')])
            ->post('/platform/companies/company-a/delete-requests/dr-1/reject', ['decision_note' => 'Still needed']);

        $response->assertSessionHas('success');

        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/rest/v1/kpis'));
    }
}
