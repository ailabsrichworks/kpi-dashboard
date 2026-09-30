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
                'due_date' => null, 'meeting_time' => '09:00', 'assignee_user_id' => null, 'created_by' => 'employee-id',
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

    /**
     * The real bug behind "why can't I drag a card to another column":
     * `tasks_update`'s RLS policy (admin/creator/assignee) was the only
     * gate, and PostgREST returns success with ZERO rows affected when RLS
     * filters every row -- so anyone who could merely SEE a task (any
     * SLT/admin on the company-wide board) but didn't create or wasn't
     * assigned to it could drag its card, get back a "success" flash, and
     * watch it silently revert to its original column on the next reload.
     * This proves the app-level check now catches it BEFORE that silent
     * no-op, with a real error message instead.
     */
    public function test_update_is_refused_for_someone_who_is_neither_creator_assignee_nor_admin(): void
    {
        Http::fake(array_merge($this->fakeEmployeeSessionFakes(), [
            '*/rest/v1/tasks*' => Http::response([[
                'id' => 'task-1', 'title' => 'Someone else\'s task', 'status' => 'open', 'priority' => 'medium',
                'due_date' => null, 'meeting_time' => null, 'assignee_user_id' => 'someone-else-id',
                'created_by' => 'someone-else-id',
            ]], 200),
        ]));

        $response = $this->withSession(['platform_access_token' => $this->fakeToken()])
            ->patch('/platform/companies/company-1/tasks/task-1', [
                'title' => 'Someone else\'s task',
                'status' => 'in_progress',
                'priority' => 'medium',
            ]);

        $response->assertSessionHas('error', 'You can only edit or move a task you created or are assigned to.');

        Http::assertNotSent(fn ($request) => $request->method() === 'PATCH' && str_contains($request->url(), '/rest/v1/tasks'));
    }

    public function test_update_is_allowed_for_the_tasks_assignee_even_when_they_are_not_the_creator(): void
    {
        Http::fake(array_merge($this->fakeEmployeeSessionFakes(), [
            '*/rest/v1/tasks*' => Http::response([[
                'id' => 'task-1', 'title' => 'Assigned to me', 'status' => 'open', 'priority' => 'medium',
                'due_date' => null, 'meeting_time' => null, 'assignee_user_id' => 'employee-id',
                'created_by' => 'someone-else-id',
            ]], 200),
        ]));

        $response = $this->withSession(['platform_access_token' => $this->fakeToken()])
            ->patch('/platform/companies/company-1/tasks/task-1', [
                'title' => 'Assigned to me',
                'status' => 'in_progress',
                'priority' => 'medium',
            ]);

        $response->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && str_contains($request->url(), '/rest/v1/tasks')
            && ($request['status'] ?? null) === 'in_progress');
    }

    public function test_destroy_is_refused_for_the_assignee_who_did_not_create_it(): void
    {
        Http::fake(array_merge($this->fakeEmployeeSessionFakes(), [
            '*/rest/v1/tasks*' => Http::response([[
                'id' => 'task-1', 'title' => 'Assigned to me', 'status' => 'open',
                'assignee_user_id' => 'employee-id', 'created_by' => 'someone-else-id',
            ]], 200),
        ]));

        $response = $this->withSession(['platform_access_token' => $this->fakeToken()])
            ->delete('/platform/companies/company-1/tasks/task-1');

        $response->assertSessionHas('error', 'You can only delete a task you created.');

        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/rest/v1/tasks'));
    }

    /**
     * The real bug behind "why can't I drag a card with a meeting time set
     * to another column" -- confirmed live against production. PostgREST
     * always serializes a Postgres `time` column with seconds ("12:00:00"),
     * and drag-and-drop's moveTask() resends a task's own current
     * meeting_time verbatim (it only changes `status`) -- but the
     * validation rule required exactly `H:i` (no seconds), so every
     * request carrying a task's own round-tripped meeting_time value was
     * silently rejected by Laravel's validator. Nothing on this page
     * renders the generic Inertia `errors` bag (only flash.success/
     * flash.error), so the failure was completely invisible: the request
     * "completed", the page reloaded, and the card was simply still in its
     * old column with no explanation at all.
     */
    public function test_update_accepts_a_meeting_time_with_seconds_as_postgrest_actually_returns_it(): void
    {
        $employeeId = '11111111-1111-1111-1111-111111111111';

        Http::fake(array_merge($this->fakeEmployeeSessionFakes(), [
            '*/rest/v1/tasks*' => Http::response([[
                'id' => 'task-1', 'title' => 'Test TTD', 'status' => 'open', 'priority' => 'medium',
                'due_date' => '2026-09-29', 'meeting_time' => '12:00:00', 'assignee_user_id' => $employeeId,
                'created_by' => 'employee-id',
            ]], 200),
        ]));

        $response = $this->withSession(['platform_access_token' => $this->fakeToken()])
            ->patch('/platform/companies/company-1/tasks/task-1', [
                'title' => 'Test TTD',
                'status' => 'in_progress',
                'priority' => 'medium',
                'due_date' => '2026-09-29',
                'meeting_time' => '12:00:00',
                'assignee_user_id' => $employeeId,
            ]);

        $response->assertSessionHas('success');
        $response->assertSessionDoesntHaveErrors();

        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && str_contains($request->url(), '/rest/v1/tasks')
            && ($request['status'] ?? null) === 'in_progress'
            && ($request['meeting_time'] ?? null) === '12:00:00');
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
