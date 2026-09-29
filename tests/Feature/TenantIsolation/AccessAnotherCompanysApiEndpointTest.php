<?php

namespace Tests\Feature\TenantIsolation;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Attack #6: "Access another company's API endpoint." A broad sweep across
 * every company-scoped Platform route this suite knows about, all hit with
 * a foreign company id in the URL. Every controller in this list calls
 * `ensureCompanyAdmin()`/`ensureCompanyMember()` (or, for `KpiSubmissionController`,
 * its own equivalent `ensureDepartmentAccess()`) BEFORE reading `$request`'s
 * validation rules, so an empty/minimal body is enough to reach the
 * authorization check on every one of these — this list isn't testing
 * request validation, only the tenant boundary.
 *
 * NOT an exhaustive, automatically-derived list — new company-scoped routes
 * must be added here by hand as they're built. Silently limited coverage
 * that isn't stated as such is worse than none; consider this list itself
 * to need the same maintenance as `routes/web.php`.
 */
class AccessAnotherCompanysApiEndpointTest extends TenantIsolationTestCase
{
    public static function companyScopedRoutes(): array
    {
        return [
            'GET departments index' => ['GET', '/platform/companies/company-b/departments'],
            'POST departments store' => ['POST', '/platform/companies/company-b/departments'],
            'GET kpis index' => ['GET', '/platform/companies/company-b/kpis'],
            'POST kpis store' => ['POST', '/platform/companies/company-b/kpis'],
            'PATCH kpi update' => ['PATCH', '/platform/companies/company-b/kpis/kpi-b'],
            'POST kpi category store' => ['POST', '/platform/companies/company-b/kpi-categories'],
            'POST apply kpi template' => ['POST', '/platform/companies/company-b/kpis/apply-template'],
            'POST kpi grant store' => ['POST', '/platform/companies/company-b/kpis/kpi-b/grants'],
            'DELETE kpi grant destroy' => ['DELETE', '/platform/companies/company-b/kpis/kpi-b/grants/grant-b'],
            'POST department user store' => ['POST', '/platform/companies/company-b/departments/dept-b/users'],
            'PATCH department user role update' => ['PATCH', '/platform/companies/company-b/departments/dept-b/users/user-b/role'],
            'POST role store' => ['POST', '/platform/companies/company-b/departments/dept-b/roles'],
            'DELETE role destroy' => ['DELETE', '/platform/companies/company-b/departments/dept-b/roles/role-b'],
            'GET submissions index' => ['GET', '/platform/companies/company-b/departments/dept-b/submissions'],
            'POST submissions store' => ['POST', '/platform/companies/company-b/departments/dept-b/submissions'],
            'POST suspend user' => ['POST', '/platform/companies/company-b/users/user-b/suspend'],
            'POST reactivate user' => ['POST', '/platform/companies/company-b/users/user-b/reactivate'],
            'GET company audit log' => ['GET', '/platform/companies/company-b/audit-log'],
            'GET company audit log export' => ['GET', '/platform/companies/company-b/audit-log/export'],
            'GET onboarding' => ['GET', '/platform/companies/company-b/onboarding'],
            'GET tasks index' => ['GET', '/platform/companies/company-b/tasks'],
            'POST tasks store' => ['POST', '/platform/companies/company-b/tasks'],
            'PATCH task update' => ['PATCH', '/platform/companies/company-b/tasks/task-b'],
            'DELETE task destroy' => ['DELETE', '/platform/companies/company-b/tasks/task-b'],
            'PUT task kpi links update' => ['PUT', '/platform/companies/company-b/tasks/task-b/kpi-links'],
            'POST task ai summary' => ['POST', '/platform/companies/company-b/tasks/ai-summary'],
            'GET kpis create' => ['GET', '/platform/companies/company-b/kpis/create'],
            'GET job description index' => ['GET', '/platform/companies/company-b/job-description'],
            'POST job description save' => ['POST', '/platform/companies/company-b/job-description'],
            'POST job description decision' => ['POST', '/platform/companies/company-b/job-descriptions/jd-b/decision'],
            'GET attendance index' => ['GET', '/platform/companies/company-b/attendance'],
            'POST attendance import' => ['POST', '/platform/companies/company-b/attendance/import'],
            'POST attendance save' => ['POST', '/platform/companies/company-b/attendance/save'],
            'POST attendance holiday store' => ['POST', '/platform/companies/company-b/attendance/holidays'],
            'DELETE attendance holiday destroy' => ['DELETE', '/platform/companies/company-b/attendance/holidays/holiday-b'],
            'GET performance index' => ['GET', '/platform/companies/company-b/performance/q2'],
            'POST performance save' => ['POST', '/platform/companies/company-b/performance/q2/save'],
            'POST performance appraise' => ['POST', '/platform/companies/company-b/performance/q2/appraise/user-b'],
            'POST performance acknowledge' => ['POST', '/platform/companies/company-b/performance/q2/acknowledge'],
            'PATCH user manager update' => ['PATCH', '/platform/companies/company-b/users/user-b/manager'],
            'GET target linkages index' => ['GET', '/platform/companies/company-b/target-linkages'],
            'POST target linkages store' => ['POST', '/platform/companies/company-b/target-linkages'],
            'DELETE target linkages destroy' => ['DELETE', '/platform/companies/company-b/target-linkages/link-b'],
        ];
    }

    #[DataProvider('companyScopedRoutes')]
    public function test_every_known_company_scoped_route_denies_a_foreign_company_admin(string $method, string $url): void
    {
        Http::fake($this->fakeCompanyAdminSessionFakes('company-a-admin', 'company-a'));

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('company-a-admin-auth-id')])
            ->json($method, $url);

        $response->assertStatus(403);
    }
}
