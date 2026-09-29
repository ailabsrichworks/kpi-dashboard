<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `updateUserManager()` — the one UI control that actually populates
 * `company_users.manager_user_id`, the shared schema piece Performance/
 * Appraisal's appraiser chain and Target Linkages both depend on. Real DB
 * tenant/self-reference safety lives in `validate_manager_same_company()`
 * (2026_09_29_040000); this only needs to prove the endpoint is admin-gated
 * and logs a real before/after.
 */
class DepartmentControllerUpdateUserManagerTest extends TestCase
{
    private function fakeToken(string $sub): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $sub, 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    public function test_a_non_admin_cannot_set_a_manager(): void
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
            ->patch('/platform/companies/company-a/users/report-id/manager', [
                'manager_user_id' => '11111111-1111-1111-1111-111111111111',
            ]);

        $response->assertStatus(403);
    }

    public function test_an_admin_setting_a_manager_logs_before_and_after(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'admin-id', 'name' => 'Admin', 'email' => 'admin@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::sequence()
                ->push([['company_id' => 'company-a', 'role' => 'company_admin', 'status' => 'active']], 200) // PlatformAuth's own membership lookup
                ->push([['manager_user_id' => null]], 200) // "before" lookup
                ->push([], 200), // the PATCH itself
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([['id' => 'log-1']], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('admin-auth-id')])
            ->patch('/platform/companies/company-a/users/report-id/manager', [
                'manager_user_id' => '11111111-1111-1111-1111-111111111111',
            ]);

        $response->assertSessionHas('success', 'Manager updated.');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/admin_action_logs')
                && $request->method() === 'POST'
                && ($request['action'] ?? null) === 'update_user_manager'
                && array_key_exists('manager_user_id', $request['before'] ?? [])
                && $request['before']['manager_user_id'] === null
                && ($request['after']['manager_user_id'] ?? null) === '11111111-1111-1111-1111-111111111111';
        });
    }
}
