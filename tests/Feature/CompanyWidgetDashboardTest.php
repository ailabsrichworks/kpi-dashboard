<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A caller scoped to exactly one company (every ordinary Company Admin/SLT/
 * Executive/Employee) gets that company's own customizable widget dashboard
 * (Platform/CompanyDashboard) instead of the generic company-list page
 * (Platform/Dashboard) — see DashboardController::companyLanding()'s own
 * docblock. A Platform Admin scoped to several companies still gets the
 * list, since one customizable layout doesn't make sense across several
 * companies at once.
 */
class CompanyWidgetDashboardTest extends TestCase
{
    private function fakeToken(string $sub): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $sub, 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    private function fakeSingleCompanyMember(): void
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
            '*/rest/v1/companies*' => Http::response([[
                'id' => 'company-1', 'name' => 'QA Co', 'code' => 'QA', 'status' => 'active', 'financial_year_start_month' => 1,
            ]], 200),
            '*/rest/v1/company_kpi_summary*' => Http::response([[
                'company_id' => 'company-1', 'department_count' => 2, 'user_count' => 10, 'kpi_count' => 5,
                'submission_count' => 3, 'avg_achievement_pct' => 87.5,
            ]], 200),
            '*/rest/v1/company_performance_periods*' => Http::response([], 200),
            '*/rest/v1/approval_request_steps*' => Http::response([], 200),
            '*/rest/v1/kpi_submissions*' => Http::response([], 200),
        ]);
    }

    public function test_a_single_company_member_sees_the_widget_dashboard_with_the_default_layout(): void
    {
        $this->fakeSingleCompanyMember();
        Http::fake([
            '*/rest/v1/company_dashboard_widgets*' => Http::response([], 200),
        ] + $this->existingFakes());

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('company-admin-auth-id')])
            ->get('/platform/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/CompanyDashboard')
            ->where('layout', ['company_overview', 'period_status', 'pending_approvals', 'recent_submissions'])
            ->where('canEditLayout', true)
            ->where('widgetData.company_overview.avg_achievement_pct', 87.5));
    }

    public function test_a_saved_layout_is_used_instead_of_the_default(): void
    {
        $this->fakeSingleCompanyMember();
        Http::fake([
            '*/rest/v1/company_dashboard_widgets*' => Http::response([
                ['widget_type' => 'period_status'],
                ['widget_type' => 'recent_submissions'],
            ], 200),
        ] + $this->existingFakes());

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('company-admin-auth-id')])
            ->get('/platform/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/CompanyDashboard')
            ->where('layout', ['period_status', 'recent_submissions']));
    }

    public function test_a_non_admin_company_member_cannot_edit_the_layout(): void
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
            '*/rest/v1/companies*' => Http::response([[
                'id' => 'company-1', 'name' => 'QA Co', 'code' => 'QA', 'status' => 'active', 'financial_year_start_month' => 1,
            ]], 200),
            '*/rest/v1/company_kpi_summary*' => Http::response([], 200),
            '*/rest/v1/company_performance_periods*' => Http::response([], 200),
            '*/rest/v1/approval_request_steps*' => Http::response([], 200),
            '*/rest/v1/kpi_submissions*' => Http::response([], 200),
            '*/rest/v1/company_dashboard_widgets*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('employee-auth-id')])
            ->get('/platform/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/CompanyDashboard')
            ->where('canEditLayout', false));
    }

    public function test_a_platform_admin_with_multiple_companies_still_sees_the_company_list(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'platform-admin-id', 'name' => 'PA', 'email' => 'pa@example.com',
                'role' => 'platform_admin', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([
                ['company_id' => 'company-1'],
                ['company_id' => 'company-2'],
            ], 200),
            '*/rest/v1/companies*' => Http::response([
                ['id' => 'company-1', 'name' => 'A', 'code' => 'A', 'status' => 'active'],
                ['id' => 'company-2', 'name' => 'B', 'code' => 'B', 'status' => 'active'],
            ], 200),
            '*/rest/v1/company_kpi_summary*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('platform-admin-auth-id')])
            ->get('/platform/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Platform/Dashboard'));
    }

    private function existingFakes(): array
    {
        return [
            '*/rest/v1/users*' => Http::response([[
                'id' => 'company-admin-id', 'name' => 'Admin', 'email' => 'admin@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-1', 'role' => 'company_admin', 'status' => 'active',
                'companies' => ['name' => 'QA Co', 'code' => 'QA'],
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/companies*' => Http::response([[
                'id' => 'company-1', 'name' => 'QA Co', 'code' => 'QA', 'status' => 'active', 'financial_year_start_month' => 1,
            ]], 200),
            '*/rest/v1/company_kpi_summary*' => Http::response([[
                'company_id' => 'company-1', 'department_count' => 2, 'user_count' => 10, 'kpi_count' => 5,
                'submission_count' => 3, 'avg_achievement_pct' => 87.5,
            ]], 200),
            '*/rest/v1/company_performance_periods*' => Http::response([], 200),
            '*/rest/v1/approval_request_steps*' => Http::response([], 200),
            '*/rest/v1/kpi_submissions*' => Http::response([], 200),
        ];
    }
}
