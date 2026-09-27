<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The subscription plan catalog the Richworks "Control Centre" manages —
 * Super-Admin-only, mirroring KpiTemplateController's own shape (a shared,
 * no-company_id catalog). What matters here: only a Super Admin can reach
 * any of these actions, and destroy() refuses to delete a plan a company is
 * currently assigned to (RLS/DB-layer enforcement of that specific rule is
 * covered separately via a disposable Postgres container, not here — this
 * suite proves the application-layer guard, which is the first line of
 * defense).
 */
class SubscriptionPlanControllerTest extends TestCase
{
    private function fakeToken(string $sub): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $sub, 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    private function fakeSuperAdminSession(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'super-admin-id', 'name' => 'Center Admin', 'email' => 'center@example.com',
                'role' => 'richworks_super_admin', 'status' => 'active',
            ]], 200),
        ]);
    }

    private function fakeCompanyAdminSession(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'company-admin-id', 'name' => 'Admin', 'email' => 'admin@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-1', 'role' => 'company_admin', 'status' => 'active',
                'companies' => ['name' => 'QA Co', 'code' => 'QA'],
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
        ]);
    }

    public function test_super_admin_can_list_plans(): void
    {
        $this->fakeSuperAdminSession();
        Http::fake([
            '*/rest/v1/subscription_plans*' => Http::response([
                ['id' => 'plan-1', 'name' => 'Pro', 'price_cents' => 9900, 'billing_period' => 'monthly', 'max_users' => 50, 'max_departments' => null, 'is_active' => true],
            ], 200),
            '*/rest/v1/companies*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('super-admin-auth-id')])
            ->get('/platform/subscription-plans');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/SubscriptionPlans/Index')
            ->where('plans.0.name', 'Pro'));
    }

    public function test_company_admin_cannot_reach_the_plan_catalog(): void
    {
        $this->fakeCompanyAdminSession();

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('company-admin-auth-id')])
            ->get('/platform/subscription-plans');

        $response->assertForbidden();
    }

    public function test_super_admin_can_create_a_plan(): void
    {
        $this->fakeSuperAdminSession();
        Http::fake([
            '*/rest/v1/subscription_plans*' => Http::response([['id' => 'plan-new', 'name' => 'Enterprise']], 201),
            '*/rest/v1/admin_action_logs*' => Http::response([], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('super-admin-auth-id')])
            ->post('/platform/subscription-plans', [
                'name' => 'Enterprise',
                'price_cents' => 49900,
                'billing_period' => 'monthly',
                'max_users' => 500,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/subscription_plans') && $request->method() === 'POST');
    }

    public function test_company_admin_cannot_create_a_plan(): void
    {
        $this->fakeCompanyAdminSession();

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('company-admin-auth-id')])
            ->post('/platform/subscription-plans', ['name' => 'Sneaky', 'price_cents' => 0, 'billing_period' => 'monthly']);

        $response->assertForbidden();
    }

    public function test_destroy_refuses_when_a_company_is_assigned_to_the_plan(): void
    {
        $this->fakeSuperAdminSession();
        Http::fake([
            '*/rest/v1/companies*' => Http::response([['id' => 'company-1']], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('super-admin-auth-id')])
            ->delete('/platform/subscription-plans/plan-1');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rest/v1/subscription_plans') && $request->method() === 'DELETE');
    }

    public function test_destroy_succeeds_when_no_company_is_assigned(): void
    {
        $this->fakeSuperAdminSession();
        Http::fake([
            '*/rest/v1/companies*' => Http::response([], 200),
            '*/rest/v1/subscription_plans*' => Http::response([], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('super-admin-auth-id')])
            ->delete('/platform/subscription-plans/plan-1');

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}
