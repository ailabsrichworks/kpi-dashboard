<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Assigning a subscription plan/status to a company is "Control Centre"
 * (Super Admin) authority specifically — narrower than general company
 * administration, since a Platform Admin can otherwise update a company's
 * row (see companies_update's own RLS policy). This suite proves the
 * application-layer guard; the database-layer one
 * (trg_prevent_non_super_admin_subscription_change, which also blocks a
 * Platform Admin who *does* pass companies_update's RLS) was verified
 * separately against a disposable Postgres container.
 */
class CompanyControllerUpdateSubscriptionTest extends TestCase
{
    private function fakeToken(string $sub): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $sub, 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    public function test_super_admin_can_assign_a_subscription(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'super-admin-id', 'name' => 'Center Admin', 'email' => 'center@example.com',
                'role' => 'richworks_super_admin', 'status' => 'active',
            ]], 200),
            '*/rest/v1/companies*' => Http::response([[
                'subscription_plan_id' => null, 'subscription_status' => null, 'subscription_current_period_end' => null,
            ]], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('super-admin-auth-id')])
            ->post('/platform/companies/company-1/subscription', [
                'subscription_plan_id' => '11111111-1111-1111-1111-111111111111',
                'subscription_status' => 'active',
                'subscription_current_period_end' => '2027-01-01',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/companies')
                && $request->method() === 'PATCH'
                && $request['subscription_status'] === 'active';
        });
    }

    public function test_company_admin_cannot_reach_the_subscription_endpoint_at_all(): void
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

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('company-admin-auth-id')])
            ->post('/platform/companies/company-1/subscription', [
                'subscription_plan_id' => '11111111-1111-1111-1111-111111111111',
                'subscription_status' => 'active',
            ]);

        $response->assertForbidden();
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rest/v1/companies') && $request->method() === 'PATCH');
    }

    public function test_an_invalid_subscription_status_is_rejected(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'super-admin-id', 'name' => 'Center Admin', 'email' => 'center@example.com',
                'role' => 'richworks_super_admin', 'status' => 'active',
            ]], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('super-admin-auth-id')])
            ->post('/platform/companies/company-1/subscription', [
                'subscription_status' => 'not-a-real-status',
            ]);

        $response->assertSessionHasErrors('subscription_status');
    }
}
