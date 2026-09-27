<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression test for the single most impactful bug found during a real,
 * authenticated production walkthrough: creating a KPI, or submitting a
 * value against one, failed for EVERY non-Super-Admin caller — Company
 * Admins and Employees alike — which in practice meant every real user of
 * the Platform except the Center itself.
 *
 * Root cause: `kpis_select` and `kpi_submissions_select` both route through
 * `auth_can_view_kpi()`, which queries back into `kpis` for the row being
 * checked. PostgREST's default `Prefer: return=representation` makes an
 * INSERT implicitly re-SELECT the new row to return it, which evaluates
 * that SELECT policy — and doing so failed for a non-Super-Admin caller even
 * though the INSERT's own WITH CHECK (`auth_can_administer_company`)
 * independently and correctly evaluated true, confirmed directly against
 * production via RPC calls, direct SQL, and repeated live inserts. The fix
 * is `Prefer: return=minimal` on these specific inserts — none of the
 * callers use the returned row anyway.
 *
 * This can't be verified against Http::fake (which doesn't simulate real
 * Postgres RLS), so what's tested here is the mechanism: the fixed call
 * sites must send `return=minimal`, not the default. A regression back to
 * the default would silently reintroduce the exact production outage this
 * fixes.
 */
class KpiReturningPolicyFixTest extends TestCase
{
    private function fakeCompanyAdminToken(): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => 'company-admin-auth-id', 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    private function fakeAuthenticatedCompanyAdminSession(): void
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

    public function test_kpi_controller_store_requests_return_minimal(): void
    {
        $this->fakeAuthenticatedCompanyAdminSession();

        $this->withSession(['platform_access_token' => $this->fakeCompanyAdminToken()])
            ->post('/platform/companies/company-1/kpis', [
                'name' => 'QA KPI',
                'frequency' => 'quarterly',
            ]);

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/rest/v1/kpis') || $request->method() !== 'POST') {
                return false;
            }

            return $request->header('Prefer') === ['return=minimal'];
        });
    }

    public function test_kpi_submission_controller_store_requests_return_minimal(): void
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
            '*/rest/v1/rpc/auth_department_ids*' => Http::response(['department-1'], 200),
        ]);

        $this->withSession(['platform_access_token' => $this->fakeCompanyAdminToken()])
            ->post('/platform/companies/company-1/departments/department-1/submissions', [
                'kpi_id' => '11111111-1111-1111-1111-111111111111',
                'value' => 50,
                'submission_date' => '2026-08-17',
            ]);

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/rest/v1/kpi_submissions') || $request->method() !== 'POST') {
                return false;
            }

            return $request->header('Prefer') === ['return=minimal'];
        });
    }
}
