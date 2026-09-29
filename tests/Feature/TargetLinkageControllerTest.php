<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Platform\TargetLinkageController — the real port of legacy's Target
 * Linkages. See the migration's own docblock
 * (2026_09_29_060000_create_kpi_target_linkages.php) for the category_id/
 * unit adaptation and the real hierarchy-enforcement improvement over legacy
 * (server-side verifies the assignee is an actual direct report, not just
 * displayed as one).
 */
class TargetLinkageControllerTest extends TestCase
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
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
        ];
    }

    public function test_a_non_member_is_rejected(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'outsider-id', 'name' => 'Outsider', 'email' => 'outsider@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('outsider-auth-id')])
            ->get('/platform/companies/company-a/target-linkages');

        $response->assertStatus(403);
    }

    public function test_index_with_no_direct_reports_cannot_assign(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*/rest/v1/company_users*' => Http::sequence()
                ->push([['company_id' => 'company-a', 'role' => 'employee', 'status' => 'active']], 200) // PlatformAuth's own membership lookup
                ->push([], 200), // "who reports to me" -- nobody
            '*/rest/v1/companies*' => Http::response([['id' => 'company-a', 'name' => 'Company A', 'code' => 'COA']], 200),
            '*/rest/v1/kpi_target_linkages*' => Http::response([], 200),
            '*/rest/v1/kpis*' => Http::response([], 200),
            '*/rest/v1/kpi_categories*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->get('/platform/companies/company-a/target-linkages');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Platform/TargetLinkages/Index')
            ->where('canAssignTarget', false));
    }

    public function test_store_refuses_a_non_direct_report(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*/rest/v1/company_users*' => Http::sequence()
                ->push([['company_id' => 'company-a', 'role' => 'employee', 'status' => 'active']], 200) // PlatformAuth's own membership lookup
                ->push([], 200), // not a direct report of mine
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/target-linkages', [
                'assignee_user_id' => '11111111-1111-1111-1111-111111111111',
                'category_id' => '22222222-2222-2222-2222-222222222222',
                'assigned_target' => 100,
            ]);

        $response->assertSessionHas('error', 'You can only assign a target linkage to one of your own direct reports.');
    }

    public function test_store_creates_a_new_linkage_for_a_real_direct_report(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*/rest/v1/company_users*' => Http::sequence()
                ->push([['company_id' => 'company-a', 'role' => 'employee', 'status' => 'active']], 200) // PlatformAuth's own membership lookup
                ->push([['user_id' => '11111111-1111-1111-1111-111111111111', 'users' => ['name' => 'Report Person']]], 200),
            '*/rest/v1/kpi_target_linkages*' => Http::sequence()
                ->push([], 200) // "does one already exist" lookup
                ->push([['id' => 'link-1']], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/target-linkages', [
                'assignee_user_id' => '11111111-1111-1111-1111-111111111111',
                'category_id' => '22222222-2222-2222-2222-222222222222',
                'unit' => 'RM',
                'assigned_target' => 5000,
            ]);

        $response->assertSessionHas('success', 'Target linkage saved for Report Person.');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/rest/v1/kpi_target_linkages')
                && ($request['assigner_user_id'] ?? null) === 'member-id'
                && ($request['assignee_user_id'] ?? null) === '11111111-1111-1111-1111-111111111111'
                && ($request['assigned_target'] ?? null) === 5000;
        });
    }

    public function test_destroy_refuses_a_non_assigner(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*/rest/v1/company_users*' => Http::response([['company_id' => 'company-a', 'role' => 'employee', 'status' => 'active']], 200),
            '*/rest/v1/kpi_target_linkages*' => Http::response([[
                'id' => 'link-1', 'assigner_user_id' => 'someone-else',
            ]], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->delete('/platform/companies/company-a/target-linkages/link-1');

        $response->assertSessionHas('error', 'Not authorized.');
    }

    public function test_destroy_allows_the_original_assigner(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*/rest/v1/company_users*' => Http::response([['company_id' => 'company-a', 'role' => 'employee', 'status' => 'active']], 200),
            '*/rest/v1/kpi_target_linkages*' => Http::response([[
                'id' => 'link-1', 'assigner_user_id' => 'member-id',
            ]], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->delete('/platform/companies/company-a/target-linkages/link-1');

        $response->assertSessionHas('success', 'Linkage removed.');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/rest/v1/kpi_target_linkages'));
    }
}
