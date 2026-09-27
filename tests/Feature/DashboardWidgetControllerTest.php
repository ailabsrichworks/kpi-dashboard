<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardWidgetControllerTest extends TestCase
{
    private function fakeToken(string $sub): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $sub, 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    public function test_company_admin_can_save_a_layout(): void
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
            '*/rest/v1/company_dashboard_widgets*' => Http::response([], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('company-admin-auth-id')])
            ->post('/platform/companies/company-1/dashboard-layout', [
                'widgets' => ['period_status', 'company_overview'],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/company_dashboard_widgets') && $request->method() === 'DELETE');
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/company_dashboard_widgets')
                && $request->method() === 'POST'
                && $request['widget_type'] === 'period_status'
                && $request['position'] === 0;
        });
    }

    public function test_a_non_admin_company_member_cannot_save_a_layout(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'employee-id', 'name' => 'Employee', 'email' => 'employee@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-1', 'role' => 'employee', 'status' => 'active',
                'companies' => ['name' => 'QA Co', 'code' => 'QA'],
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('employee-auth-id')])
            ->post('/platform/companies/company-1/dashboard-layout', [
                'widgets' => ['period_status'],
            ]);

        $response->assertForbidden();
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rest/v1/company_dashboard_widgets'));
    }

    public function test_an_unknown_widget_type_is_rejected(): void
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
            ->post('/platform/companies/company-1/dashboard-layout', [
                'widgets' => ['not_a_real_widget'],
            ]);

        $response->assertSessionHasErrors('widgets.0');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rest/v1/company_dashboard_widgets'));
    }
}
