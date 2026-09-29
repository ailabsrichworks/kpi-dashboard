<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Platform\PerformanceController — the real port of legacy's Performance/
 * Appraisal subsystem. See the migration's own docblock
 * (2026_09_29_050000_create_performance_reviews.php) for the scoring formula
 * and the deliberate scope departures (one appraiser via manager_user_id,
 * no canvas signature) from legacy's 3-tier Manager/VP/SLT chain.
 */
class PerformanceControllerTest extends TestCase
{
    private function fakeToken(string $sub): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $sub, 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    private function fakeMemberSession(string $role = 'employee', string $id = 'member-id'): array
    {
        return [
            '*/rest/v1/users*' => Http::response([[
                'id' => $id, 'name' => 'Member', 'email' => 'member@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-a', 'role' => $role, 'status' => 'active',
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
        ];
    }

    public function test_a_non_member_is_rejected(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'outsider-id', 'name' => 'Outsider', 'email' => 'outsider@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('outsider-auth-id')])
            ->get('/platform/companies/company-a/performance/q2');

        $response->assertStatus(403);
    }

    public function test_an_invalid_quarter_404s(): void
    {
        Http::fake($this->fakeMemberSession());

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->get('/platform/companies/company-a/performance/q9');

        $response->assertStatus(404);
    }

    public function test_index_shows_no_review_yet_for_a_fresh_quarter(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'member-id', 'name' => 'Member', 'email' => 'member@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::sequence()
                ->push([['company_id' => 'company-a', 'role' => 'employee', 'status' => 'active']], 200) // PlatformAuth's own membership lookup
                ->push([], 200), // "who reports to me" -- nobody
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/companies*' => Http::response([['id' => 'company-a', 'name' => 'Company A', 'code' => 'COA']], 200),
            '*/rest/v1/performance_reviews*' => Http::response([], 200),
            '*/rest/v1/appraiser_delegations*' => Http::response([], 200), // nobody has delegated to me
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->get('/platform/companies/company-a/performance/q2');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Platform/Performance/Index')
            ->where('quarter', 'Q2')
            ->where('mine', null)
            ->where('isQ4', false));
    }

    public function test_saving_a_draft_computes_the_self_kpi_score_server_side(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*/rest/v1/performance_reviews*' => Http::sequence()
                ->push([], 200) // "does a review already exist" lookup
                ->push([['id' => 'rev-1']], 201),
            '*/rest/v1/kpis*' => Http::response([
                ['id' => 'kpi-1', 'name' => 'Sales Target', 'weight' => 100],
            ], 200),
            '*/rest/v1/kpi_quarters*' => Http::response([
                ['kpi_id' => 'kpi-1', 'target' => 100, 'actual' => 50],
            ], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/performance/q2/save', [
                'attitude_ratings' => array_fill(0, 12, 4),
                'action' => 'save',
            ]);

        $response->assertSessionHas('success', 'Draft saved.');

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || !str_contains($request->url(), '/rest/v1/performance_reviews')) {
                return false;
            }
            $kpi = $request['self_scores']['kpi'] ?? null;
            $attitude = $request['self_scores']['attitude'] ?? null;

            // actual/target = 0.5 -> 0.5*5 = 2.5 self score; single KPI at
            // 100% weight -> section total = 2.5/5*70 = 35.
            return $kpi['total'] === 35.0
                && ($attitude['total'] ?? null) === 20.0 // (4*12)/60*25
                && ($request['status'] ?? null) === 'draft';
        });
    }

    public function test_a_non_manager_cannot_appraise_someone_elses_review(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'member-id', 'name' => 'Member', 'email' => 'member@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::sequence()
                ->push([['company_id' => 'company-a', 'role' => 'employee', 'status' => 'active']], 200) // PlatformAuth's own membership lookup
                ->push([], 200), // "is this caller the employee's manager" check -- no
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/performance/q2/appraise/some-employee', [
                'attitude_ratings' => array_fill(0, 12, ['rating' => 3, 'comment' => '']),
                'attendance_counts' => ['lateness' => 0, 'absent' => 0, 'insufficient' => 0, 'leave' => 0, 'disciplinary' => 0],
                'action' => 'save',
            ]);

        $response->assertStatus(403);
    }

    public function test_the_manager_can_appraise_and_submit_computes_final_score_and_band(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'manager-id', 'name' => 'Manager', 'email' => 'manager@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::sequence()
                ->push([['company_id' => 'company-a', 'role' => 'executive', 'status' => 'active']], 200) // PlatformAuth's own membership lookup
                ->push([['manager_user_id' => 'manager-id']], 200), // "is this caller the employee's manager" check
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/performance_reviews*' => Http::sequence()
                ->push([['id' => 'rev-1', 'status' => 'submitted']], 200) // existing review lookup
                ->push([['id' => 'rev-1']], 200), // the update itself
            '*/rest/v1/kpis*' => Http::response([
                ['id' => 'kpi-1', 'name' => 'Sales Target', 'weight' => 100],
            ], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([['id' => 'log-1']], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('manager-auth-id')])
            ->post('/platform/companies/company-a/performance/q2/appraise/employee-id', [
                'kpi_scores' => ['kpi-1' => 5],
                // 12 areas x rating 5 -> 60/60*25 = 25
                'attitude_ratings' => array_fill(0, 12, ['rating' => 5, 'comment' => 'Great work']),
                // all zero counts -> 1.00 x 5 categories = 5
                'attendance_counts' => ['lateness' => 0, 'absent' => 0, 'insufficient' => 0, 'leave' => 0, 'disciplinary' => 0],
                'action' => 'submit',
            ]);

        $response->assertSessionHas('success', 'Appraisal submitted.');

        Http::assertSent(function ($request) {
            if ($request->method() !== 'PATCH' || !str_contains($request->url(), '/rest/v1/performance_reviews')) {
                return false;
            }

            // kpi 5/5 weighted 100% -> 5/5*70 = 70; attitude 25; attendance 5 -> 100 total -> Outstanding.
            return ($request['final_score'] ?? null) === 100.0
                && ($request['band'] ?? null) === 'Outstanding'
                && ($request['status'] ?? null) === 'appraised';
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/admin_action_logs')
                && ($request['action'] ?? null) === 'appraise_performance_review';
        });
    }

    public function test_acknowledge_is_refused_before_the_review_is_appraised(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*/rest/v1/performance_reviews*' => Http::response([['id' => 'rev-1', 'status' => 'draft']], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/performance/q2/acknowledge', ['acknowledgment' => 'Agreed.']);

        $response->assertSessionHas('error', 'This review is not ready to be signed off yet.');
    }

    public function test_acknowledge_completes_an_appraised_review(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*/rest/v1/performance_reviews*' => Http::response([['id' => 'rev-1', 'status' => 'appraised']], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([['id' => 'log-1']], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/performance/q2/acknowledge', ['acknowledgment' => 'Agreed, thank you.']);

        $response->assertSessionHas('success');

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), '/rest/v1/performance_reviews')
                && ($request['status'] ?? null) === 'completed';
        });
    }
}
