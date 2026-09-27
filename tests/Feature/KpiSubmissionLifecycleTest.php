<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 1 hardening, Part 2/3: KPI actual submissions are period-specific
 * and versioned, gated by the period lifecycle, before anything ever reaches
 * `kpi_submissions` or the approval engine. Every case here is a rejection
 * that must happen BEFORE any write — asserted the same way the tenant
 * isolation suite does (`Http::assertNotSent`), since these two gates
 * (period lifecycle, one-pending-revision-at-a-time) are meant to be the
 * FIRST checks `KpiSubmissionController::store()` makes, not an afterthought.
 */
class KpiSubmissionLifecycleTest extends TestCase
{
    private function fakeToken(string $authUserId): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $authUserId, 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    /** A department member (not admin) — `can_submit` should be true for them. */
    private function fakeDepartmentMemberFakes(string $userId, string $companyId, string $departmentId, int $financialYearStartMonth = 1): array
    {
        return [
            '*/rest/v1/users*' => Http::response([[
                'id' => $userId, 'name' => 'Employee', 'email' => $userId . '@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => $companyId, 'role' => 'employee', 'status' => 'active',
                'companies' => ['name' => 'Company', 'code' => 'CO'],
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([], 201),
            '*/rest/v1/rpc/auth_department_ids*' => Http::response([$departmentId], 200),
            '*/rest/v1/companies*' => Http::response([[
                'id' => $companyId, 'financial_year_start_month' => $financialYearStartMonth,
            ]], 200),
        ];
    }

    public function test_a_submission_dated_in_a_future_upcoming_period_is_rejected(): void
    {
        $company = 'company-a';
        $department = 'dept-a';

        Http::fake($this->fakeDepartmentMemberFakes('user-a', $company, $department, 1) + [
            // No override row for this period -> defaults to computed status,
            // and a date years in the future is unambiguously 'upcoming'.
            '*/rest/v1/company_performance_periods*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('user-a-auth')])
            ->post("/platform/companies/{$company}/departments/{$department}/submissions", [
                'kpi_id' => '11111111-1111-1111-1111-111111111111',
                'value' => 100,
                'submission_date' => now()->addYears(2)->toDateString(),
            ]);

        $response->assertSessionHas('error');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rest/v1/kpi_submissions') && $request->method() === 'POST');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rest/v1/approval_requests') && $request->method() === 'POST');
    }

    public function test_a_second_submission_for_a_period_already_awaiting_approval_is_rejected(): void
    {
        $company = 'company-a';
        $department = 'dept-a';
        $today = now();

        Http::fake($this->fakeDepartmentMemberFakes('user-a', $company, $department, 1) + [
            '*/rest/v1/company_performance_periods*' => Http::response([], 200),
            // An existing, still-pending revision for this exact KPI+period.
            '*/rest/v1/kpi_submissions*' => Http::response([[
                'id' => 'existing-submission', 'revision_number' => 1, 'status' => 'pending_review',
            ]], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('user-a-auth')])
            ->post("/platform/companies/{$company}/departments/{$department}/submissions", [
                'kpi_id' => '11111111-1111-1111-1111-111111111111',
                'value' => 100,
                'submission_date' => $today->toDateString(),
            ]);

        $response->assertSessionHas('error');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rest/v1/kpi_submissions') && $request->method() === 'POST');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rest/v1/approval_requests') && $request->method() === 'POST');
    }

    public function test_a_new_revision_is_accepted_once_the_prior_one_was_rejected(): void
    {
        $company = 'company-a';
        $department = 'dept-a';
        $today = now();

        Http::fake($this->fakeDepartmentMemberFakes('user-a', $company, $department, 1) + [
            '*/rest/v1/company_performance_periods*' => Http::response([], 200),
            // The only prior revision was rejected -- a new one should be
            // allowed (this is what "returned/rejected clears the way for a
            // new revision" actually means, exercised end to end).
            '*/rest/v1/kpi_submissions*' => Http::response([[
                'id' => 'prior-submission', 'revision_number' => 1, 'status' => 'rejected',
            ]], 200),
            // No configured workflow -> ApprovalWorkflowService falls back to
            // its built-in default (a single submitter_manager step).
            '*/rest/v1/approval_workflows*' => Http::response([], 200),
            // Resolves as the submitter's direct manager, so the approval
            // step resolves cleanly without falling through to the
            // company_admin fallback (kept out of scope for this test).
            '*/rest/v1/department_users*' => Http::response([['manager_user_id' => 'user-manager']], 200),
            '*/rest/v1/approval_requests*' => Http::response([[
                'id' => 'new-request', 'company_id' => $company, 'workflow_type' => 'actual_submission',
                'object_type' => 'kpi_submission', 'object_id' => 'whatever', 'submitted_by' => 'user-a',
                'current_step_order' => 1, 'status' => 'pending',
            ]], 201),
            '*/rest/v1/approval_request_steps*' => Http::response([], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('user-a-auth')])
            ->post("/platform/companies/{$company}/departments/{$department}/submissions", [
                'kpi_id' => '11111111-1111-1111-1111-111111111111',
                'value' => 100,
                'submission_date' => $today->toDateString(),
            ]);

        $response->assertSessionHas('success');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/kpi_submissions') && $request->method() === 'POST'
            && ($request->data()['revision_number'] ?? null) === 2
            && ($request->data()['status'] ?? null) === 'pending_review');
    }
}
