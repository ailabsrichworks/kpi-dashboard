<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Platform\SltDashboardController -- a real clone of legacy's SLT Dashboard
 * (the live frontend behind legacy's own `Inertia::render('SltDashboard', ...)`,
 * not the stale `resources/views/slt-dashboard.blade.php`). Reads
 * `performance_reviews` directly for the 4-stage funnel and band
 * distribution, rather than re-deriving a score from a form_data blob the
 * way legacy's own `scoreFromFormData()` has to. See the controller's own
 * docblock for why an earlier "Platform-native" KPI-achievement version of
 * this page was replaced.
 */
class SltDashboardControllerTest extends TestCase
{
    private function fakeToken(string $sub): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $sub, 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    private function fakeUserRow(string $id): array
    {
        return ['id' => $id, 'name' => ucfirst($id), 'email' => $id . '@example.com', 'role' => 'member', 'status' => 'active'];
    }

    public function test_a_plain_employee_is_rejected(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([$this->fakeUserRow('emp-id')], 200),
            '*/rest/v1/company_users*' => Http::response([['company_id' => 'company-a', 'role' => 'employee', 'status' => 'active']], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('emp-auth-id')])
            ->get('/platform/companies/company-a/slt-dashboard');

        $response->assertStatus(403);
    }

    public function test_slt_member_sees_the_real_appraisal_funnel_and_band_distribution(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([$this->fakeUserRow('slt-id')], 200),
            '*/rest/v1/company_users*' => Http::sequence()
                ->push([['company_id' => 'company-a', 'role' => 'slt', 'status' => 'active']], 200) // PlatformAuth's own membership lookup
                ->push([
                    ['user_id' => 'u1', 'role' => 'employee', 'manager_user_id' => null, 'users' => ['name' => 'Not Submitted Guy', 'email' => 'u1@x.com']],
                    ['user_id' => 'u2', 'role' => 'employee', 'manager_user_id' => null, 'users' => ['name' => 'Submitted Guy', 'email' => 'u2@x.com']],
                    ['user_id' => 'u3', 'role' => 'employee', 'manager_user_id' => null, 'users' => ['name' => 'Appraised Guy', 'email' => 'u3@x.com']],
                    ['user_id' => 'u4', 'role' => 'employee', 'manager_user_id' => null, 'users' => ['name' => 'Completed Guy', 'email' => 'u4@x.com']],
                ], 200), // the controller's own company-wide roster
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/companies*' => Http::response([['id' => 'company-a', 'name' => 'Company A', 'code' => 'COA']], 200),
            '*/rest/v1/departments*' => Http::response([], 200),
            '*/rest/v1/department_users*' => Http::response([], 200),
            '*/rest/v1/performance_reviews*' => Http::response([
                ['user_id' => 'u2', 'status' => 'submitted', 'final_score' => null, 'band' => null],
                ['user_id' => 'u3', 'status' => 'appraised', 'final_score' => null, 'band' => null],
                ['user_id' => 'u4', 'status' => 'completed', 'final_score' => 92.5, 'band' => 'Outstanding'],
            ], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('slt-auth-id')])
            ->get('/platform/companies/company-a/slt-dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Platform/SltDashboard')
            ->where('totalStaff', 4)
            ->where('notSubmittedCount', 1)
            ->where('pendingCount', 1)
            ->where('awaitingSignoffCount', 1)
            ->where('completedCount', 1)
            ->where('participationRate', 75)
            ->where('averageScore', 92.5)
            ->where('averageBand.label', 'Outstanding')
            ->where('bandCounts.outstanding', 1));
    }

    public function test_department_filter_narrows_the_roster(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([$this->fakeUserRow('slt-id')], 200),
            '*/rest/v1/company_users*' => Http::sequence()
                ->push([['company_id' => 'company-a', 'role' => 'slt', 'status' => 'active']], 200)
                ->push([
                    ['user_id' => 'u1', 'role' => 'employee', 'manager_user_id' => null, 'users' => ['name' => 'In Dept', 'email' => 'u1@x.com']],
                    ['user_id' => 'u2', 'role' => 'employee', 'manager_user_id' => null, 'users' => ['name' => 'Out Of Dept', 'email' => 'u2@x.com']],
                ], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/companies*' => Http::response([['id' => 'company-a', 'name' => 'Company A', 'code' => 'COA']], 200),
            '*/rest/v1/departments*' => Http::response([['id' => 'dept-1', 'name' => 'Ops', 'code' => 'OPS']], 200),
            '*/rest/v1/department_users*' => Http::response([
                ['user_id' => 'u1', 'department_id' => 'dept-1', 'departments' => ['name' => 'Ops']],
            ], 200),
            '*/rest/v1/performance_reviews*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('slt-auth-id')])
            ->get('/platform/companies/company-a/slt-dashboard?department=dept-1');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Platform/SltDashboard')
            ->where('totalStaff', 1)
            ->where('staffRows.0.name', 'In Dept')
            ->where('staffRows.0.department', 'Ops'));
    }
}
