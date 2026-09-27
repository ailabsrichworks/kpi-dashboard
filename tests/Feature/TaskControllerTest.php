<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `TaskController` — the Performix Platform's Tasks feature, optionally
 * linked to one or more KPIs for visibility only (a link never touches a
 * KPI's actual value). Covers the authorized path: any active company
 * member (not just a Company Admin) may create/list tasks, creating a task
 * with `kpi_ids` inserts one `task_kpi_links` row per id, and
 * `updateKpiLinks` is delete-then-reinsert (replace-all) semantics, matching
 * the legacy Telegram Mini App's `linkKpis` behavior this feature mirrors.
 */
class TaskControllerTest extends TestCase
{
    private function fakeToken(): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => 'employee-auth-id', 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    /**
     * A plain `employee` (not `company_admin`) — proves task creation is
     * open to any active company member, unlike KPI definitions.
     */
    private function fakeEmployeeSessionFakes(): array
    {
        return [
            '*/rest/v1/users*' => Http::response([[
                'id' => 'employee-id', 'name' => 'Employee', 'email' => 'employee@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-1', 'role' => 'employee', 'status' => 'active',
                'companies' => ['name' => 'QA Co', 'code' => 'QA'],
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([], 201),
        ];
    }

    public function test_store_creates_a_task_links_it_to_a_kpi_and_logs_it(): void
    {
        Http::fake(array_merge($this->fakeEmployeeSessionFakes(), [
            '*/rest/v1/tasks*' => Http::response([['id' => 'task-1']], 201),
            '*/rest/v1/task_kpi_links*' => Http::response([], 201),
        ]));

        $this->withSession(['platform_access_token' => $this->fakeToken()])
            ->post('/platform/companies/company-1/tasks', [
                'title' => 'Follow up with client',
                'kpi_ids' => ['11111111-1111-1111-1111-111111111111'],
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/tasks')
                && $request->method() === 'POST'
                && $request['title'] === 'Follow up with client'
                && $request['company_id'] === 'company-1'
                && $request['created_by'] === 'employee-id';
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/task_kpi_links')
                && $request->method() === 'POST'
                && $request['task_id'] === 'task-1'
                && $request['kpi_id'] === '11111111-1111-1111-1111-111111111111'
                && $request['linked_by'] === 'employee-id'
                && $request->header('Prefer') === ['return=minimal'];
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/admin_action_logs')
                && $request->method() === 'POST'
                && $request['action'] === 'create_task';
        });
    }

    public function test_store_saves_meeting_time_when_the_task_is_a_scheduled_meeting(): void
    {
        Http::fake(array_merge($this->fakeEmployeeSessionFakes(), [
            '*/rest/v1/tasks*' => Http::response([['id' => 'task-1']], 201),
        ]));

        $this->withSession(['platform_access_token' => $this->fakeToken()])
            ->post('/platform/companies/company-1/tasks', [
                'title' => 'Weekly ops sync',
                'meeting_time' => '09:00',
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/tasks')
                && $request->method() === 'POST'
                && $request['title'] === 'Weekly ops sync'
                && $request['meeting_time'] === '09:00';
        });
    }

    public function test_update_saves_and_clears_meeting_time(): void
    {
        Http::fake(array_merge($this->fakeEmployeeSessionFakes(), [
            '*/rest/v1/tasks*' => Http::response([[
                'id' => 'task-1', 'title' => 'Weekly ops sync', 'status' => 'open', 'priority' => 'medium',
                'due_date' => null, 'meeting_time' => '09:00', 'assignee_user_id' => null,
            ]], 200),
        ]));

        $this->withSession(['platform_access_token' => $this->fakeToken()])
            ->patch('/platform/companies/company-1/tasks/task-1', [
                'title' => 'Weekly ops sync',
                'status' => 'in_progress',
                'priority' => 'medium',
                'meeting_time' => '10:30',
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/tasks')
                && $request->method() === 'PATCH'
                && $request['meeting_time'] === '10:30';
        });
    }

    public function test_update_kpi_links_deletes_existing_then_reinserts_the_given_set(): void
    {
        Http::fake(array_merge($this->fakeEmployeeSessionFakes(), [
            '*/rest/v1/tasks*' => Http::response([['id' => 'task-1']], 200),
            '*/rest/v1/task_kpi_links*' => Http::response([], 200),
        ]));

        $this->withSession(['platform_access_token' => $this->fakeToken()])
            ->put('/platform/companies/company-1/tasks/task-1/kpi-links', [
                'kpi_ids' => ['22222222-2222-2222-2222-222222222222'],
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/task_kpi_links')
                && $request->method() === 'DELETE'
                && str_contains($request->url(), 'task_id=eq.task-1');
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/task_kpi_links')
                && $request->method() === 'POST'
                && $request['kpi_id'] === '22222222-2222-2222-2222-222222222222';
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/admin_action_logs')
                && $request->method() === 'POST'
                && $request['action'] === 'link_task_kpis';
        });
    }
}
