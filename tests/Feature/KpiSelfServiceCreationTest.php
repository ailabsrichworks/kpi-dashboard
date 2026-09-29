<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Everyone can create their own KPI" — widened from Company-Admin-only.
 * See the migration's own docblock (2026_09_29_070000_allow_self_service_kpi_creation.php)
 * for why a non-admin is restricted to `assigned_user_id = self` and
 * `visibility = 'company'` (the RLS policy enforces it; this proves the app
 * layer enforces the identical rule rather than trusting the client).
 */
class KpiSelfServiceCreationTest extends TestCase
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

    public function test_a_plain_member_can_open_the_create_page_with_no_member_picker(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*/rest/v1/companies*' => Http::response([['id' => 'company-a', 'name' => 'Company A', 'code' => 'COA']], 200),
            '*/rest/v1/kpi_categories*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->get('/platform/companies/company-a/kpis/create');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Platform/Kpis/Create')
            ->where('isAdmin', false)
            ->where('members', []));
    }

    public function test_a_plain_member_creating_a_kpi_is_forced_to_self_and_company_visibility(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*/rest/v1/kpis*' => Http::response([], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([['id' => 'log-1']], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/kpis', [
                'name' => 'My Own KPI',
                'frequency' => 'monthly',
                // A tampered/naive client could still send these -- the
                // server must ignore both for a non-admin.
                'assigned_user_id' => '33333333-3333-3333-3333-333333333333',
                'visibility' => 'restricted',
            ]);

        $response->assertSessionHas('success');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/rest/v1/kpis')
                && ($request['assigned_user_id'] ?? null) === 'member-id'
                && ($request['visibility'] ?? null) === 'company';
        });
    }

    public function test_a_plain_member_cannot_add_a_category(): void
    {
        Http::fake($this->fakeMemberSession());

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/kpi-categories', ['name' => 'New Category']);

        $response->assertStatus(403);
    }
}
