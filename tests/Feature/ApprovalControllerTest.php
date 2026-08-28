<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 1 hardening, Part 6: "My Approvals" / decide(). The real approval
 * resolution and cross-tenant enforcement is proven for real against Postgres
 * by `database/rls-tests/tenant_isolation.sql` scenarios 21-22 (self-approval
 * blocked, non-current-step approver blocked, cross-company target-revision
 * application blocked) — this test covers what an HTTP-mocked controller
 * test is actually good at: the comment-required validation gate, which is
 * pure application logic that runs before any Supabase write is attempted.
 */
class ApprovalControllerTest extends TestCase
{
    private function fakeToken(string $authUserId): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $authUserId, 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    private function fakeCompanyMemberFakes(string $userId, string $companyId): array
    {
        return [
            '*/rest/v1/users*' => Http::response([[
                'id' => $userId, 'name' => 'Approver', 'email' => $userId . '@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => $companyId, 'role' => 'manager', 'status' => 'active',
                'companies' => ['name' => 'Company', 'code' => 'CO'],
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([], 201),
        ];
    }

    public function test_rejecting_without_a_comment_is_refused_before_any_decision_is_recorded(): void
    {
        $company = 'company-a';

        Http::fake($this->fakeCompanyMemberFakes('user-manager', $company) + [
            '*/rest/v1/approval_requests*' => Http::response([[
                'id' => 'request-1', 'company_id' => $company, 'workflow_type' => 'actual_submission',
                'object_type' => 'kpi_submission', 'object_id' => 'submission-1', 'status' => 'pending',
                'current_step_order' => 1,
            ]], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('user-manager-auth')])
            ->post("/platform/companies/{$company}/approvals/request-1/decide", [
                'decision' => 'rejected',
            ]);

        $response->assertSessionHas('error');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rest/v1/approval_request_steps') && $request->method() === 'PATCH');
    }

    public function test_returning_for_revision_without_a_comment_is_also_refused(): void
    {
        $company = 'company-a';

        Http::fake($this->fakeCompanyMemberFakes('user-manager', $company) + [
            '*/rest/v1/approval_requests*' => Http::response([[
                'id' => 'request-1', 'company_id' => $company, 'workflow_type' => 'actual_submission',
                'object_type' => 'kpi_submission', 'object_id' => 'submission-1', 'status' => 'pending',
                'current_step_order' => 1,
            ]], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('user-manager-auth')])
            ->post("/platform/companies/{$company}/approvals/request-1/decide", [
                'decision' => 'returned',
            ]);

        $response->assertSessionHas('error');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rest/v1/approval_request_steps') && $request->method() === 'PATCH');
    }

    public function test_approving_does_not_require_a_comment(): void
    {
        $company = 'company-a';

        Http::fake($this->fakeCompanyMemberFakes('user-manager', $company) + [
            '*/rest/v1/approval_requests*' => Http::response([[
                'id' => 'request-1', 'company_id' => $company, 'workflow_type' => 'actual_submission',
                'object_type' => 'kpi_submission', 'object_id' => 'submission-1', 'status' => 'pending',
                'current_step_order' => 1,
            ]], 200),
            '*/rest/v1/approval_request_steps*' => Http::response([[
                'id' => 'step-1', 'request_id' => 'request-1', 'step_order' => 1, 'status' => 'pending',
            ]], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('user-manager-auth')])
            ->post("/platform/companies/{$company}/approvals/request-1/decide", [
                'decision' => 'approved',
            ]);

        $response->assertSessionHas('success');
    }
}
