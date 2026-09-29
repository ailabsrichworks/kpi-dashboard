<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Platform\JobDescriptionController — the real port of legacy's Job
 * Description page. See the migration's own docblock
 * (2026_09_29_020000_create_job_descriptions.php) for why this uses a
 * Company-Admin decision instead of legacy's manager-hierarchy sign-off.
 */
class JobDescriptionControllerTest extends TestCase
{
    private function fakeToken(string $sub): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $sub, 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
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
            ->get('/platform/companies/company-a/job-description');

        $response->assertStatus(403);
    }

    public function test_a_member_with_no_job_description_yet_sees_an_empty_form(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'member-id', 'name' => 'Member', 'email' => 'member@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-a', 'role' => 'employee', 'status' => 'active',
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/companies*' => Http::response([['id' => 'company-a', 'name' => 'Company A', 'code' => 'COA']], 200),
            '*/rest/v1/job_descriptions*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->get('/platform/companies/company-a/job-description');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Platform/JobDescription/Index')
            ->where('mine', null)
            ->where('isAdmin', false));
    }

    public function test_saving_a_draft_inserts_a_new_row_for_the_caller(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'member-id', 'name' => 'Member', 'email' => 'member@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-a', 'role' => 'employee', 'status' => 'active',
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/job_descriptions*' => Http::sequence()
                ->push([], 200) // save()'s own "does one already exist" lookup
                ->push([['id' => 'jd-1']], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/job-description', [
                'summary' => 'Runs the thing.',
                'action' => 'save',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Draft saved.');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/rest/v1/job_descriptions')
                && ($request['user_id'] ?? null) === 'member-id'
                && ($request['company_id'] ?? null) === 'company-a'
                && ($request['status'] ?? null) === 'draft';
        });
    }

    public function test_editing_is_refused_once_submitted(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'member-id', 'name' => 'Member', 'email' => 'member@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-a', 'role' => 'employee', 'status' => 'active',
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/job_descriptions*' => Http::response([[
                'id' => 'jd-1', 'status' => 'submitted',
            ]], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/job-description', [
                'summary' => 'Trying to sneak an edit in.',
                'action' => 'save',
            ]);

        $response->assertSessionHas('error');

        Http::assertNotSent(fn ($request) => $request->method() === 'PATCH' && str_contains($request->url(), '/rest/v1/job_descriptions'));
    }

    public function test_a_non_admin_cannot_decide(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'member-id', 'name' => 'Member', 'email' => 'member@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-a', 'role' => 'employee', 'status' => 'active',
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->post('/platform/companies/company-a/job-descriptions/jd-1/decision', [
                'decision' => 'approved',
            ]);

        $response->assertStatus(403);
    }

    public function test_an_admin_approving_logs_the_decision(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'admin-id', 'name' => 'Admin', 'email' => 'admin@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-a', 'role' => 'company_admin', 'status' => 'active',
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/job_descriptions*' => Http::sequence()
                ->push([['id' => 'jd-1', 'user_id' => 'member-id', 'status' => 'submitted']], 200)
                ->push([['id' => 'jd-1']], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([['id' => 'log-1']], 201),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('admin-auth-id')])
            ->post('/platform/companies/company-a/job-descriptions/jd-1/decision', [
                'decision' => 'approved',
            ]);

        $response->assertSessionHas('success');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/admin_action_logs')
                && $request->method() === 'POST'
                && ($request['action'] ?? null) === 'decide_job_description';
        });
    }
}
