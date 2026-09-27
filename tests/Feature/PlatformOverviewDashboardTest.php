<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Requirement #9: "Since Richworks is effectively the platform operator,
 * Performix should have a separate platform view." Before this,
 * `/platform/dashboard` rendered the SAME component for a Super Admin and
 * for an ordinary company user, with a couple of blocks conditionally shown
 * to the former — a company user was never actually shown another company's
 * data (RLS already prevented that), but the page itself was operator-
 * flavored regardless of who loaded it. Now the two are genuinely different
 * Inertia components: a Super Admin gets `Platform/PlatformOverview`, and
 * everyone else still gets `Platform/Dashboard`, unchanged in substance.
 *
 * What matters here: the Super Admin path computes real stats (company
 * status counts, total users, onboarding breakdown) rather than fabricated
 * numbers, degrades gracefully instead of 500ing if a downstream query
 * fails, and a non-Super-Admin never sees the operator component at all.
 */
class PlatformOverviewDashboardTest extends TestCase
{
    private function fakeToken(string $sub): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $sub, 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    public function test_a_super_admin_sees_the_platform_overview_with_real_status_counts(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'super-admin-id', 'name' => 'Center Admin', 'email' => 'center@example.com',
                'role' => 'richworks_super_admin', 'status' => 'active',
            ]], 200),
            '*/rest/v1/companies*' => Http::response([
                ['id' => 'company-1', 'name' => 'Andalusia', 'code' => 'AND', 'status' => 'active', 'onboarding_status' => 'completed', 'created_at' => '2026-01-01'],
                ['id' => 'company-2', 'name' => 'VFive', 'code' => 'VF', 'status' => 'suspended', 'onboarding_status' => 'completed', 'created_at' => '2026-01-02'],
                ['id' => 'company-3', 'name' => 'Newco', 'code' => 'NEW', 'status' => 'draft', 'onboarding_status' => 'not_started', 'created_at' => '2026-01-03'],
            ], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('super-admin-auth-id')])
            ->get('/platform/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/PlatformOverview')
            ->where('stats.total_companies', 3)
            ->where('stats.active_companies', 1)
            ->where('stats.suspended_companies', 1)
            ->where('onboardingProgress.draft', 1)
            ->where('systemHealth.database', 'reachable')
        );
    }

    public function test_a_company_admin_never_sees_the_operator_dashboard(): void
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
            '*/rest/v1/companies*' => Http::response([['id' => 'company-1', 'name' => 'QA Co', 'code' => 'QA', 'status' => 'active']], 200),
            '*/rest/v1/company_kpi_summary*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('company-admin-auth-id')])
            ->get('/platform/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Platform/Dashboard'));
    }

    public function test_the_operator_dashboard_degrades_gracefully_if_companies_cannot_be_fetched(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'super-admin-id', 'name' => 'Center Admin', 'email' => 'center@example.com',
                'role' => 'richworks_super_admin', 'status' => 'active',
            ]], 200),
            '*/rest/v1/companies*' => Http::response(['message' => 'db down'], 500),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('super-admin-auth-id')])
            ->get('/platform/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/PlatformOverview')
            ->where('systemHealth.database', 'unreachable')
            ->where('stats.total_companies', 0)
        );
    }
}
