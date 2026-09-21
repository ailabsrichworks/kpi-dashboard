<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `MiniAppTaskController` — the web "Performix" To-Do/Kanban board (the
 * page users actually reach via the sidebar's "Performix" link, not the
 * separate Platform Tasks feature under `Platform/Tasks/Index.tsx`).
 * Covers the two things added alongside the Kanban redesign: an optional
 * `meeting_time` (distinct from the date-only `due_date`) and reassigning a
 * task's `assignee_employee_id` from Edit, both gated by the same
 * `TaskAccessPolicy::canAssign()` the create form already used.
 */
class MiniAppTaskControllerTest extends TestCase
{
    private function employeeSession(string $role = 'EXECUTIVE', string $id = 'emp-1'): array
    {
        return [
            'employee_uuid' => $id,
            'employee' => [
                'id' => $id,
                'role' => $role,
                'company_code' => 'RGHB',
                'department_code' => 'OPS',
                'short_name' => 'Test User',
            ],
        ];
    }

    public function test_store_saves_meeting_time(): void
    {
        Http::fake([
            '*/rest/v1/telegram_projects*' => Http::response([['id' => 'proj-1']], 200),
            '*/rest/v1/telegram_project_tasks*' => Http::response([['id' => 'task-1']], 201),
        ]);

        $this->withSession($this->employeeSession())
            ->post('/mini-app/api/tasks', [
                'title' => 'Weekly ops sync',
                'unit' => 'number',
                'target' => 1,
                'meeting_time' => '14:30',
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/telegram_project_tasks')
                && $request->method() === 'POST'
                && $request['title'] === 'Weekly ops sync'
                && $request['meeting_time'] === '14:30';
        });
    }

    public function test_update_saves_meeting_time_and_reassigns_when_allowed(): void
    {
        Http::fake([
            '*/rest/v1/employees*' => Http::response([
                ['id' => 'emp-1'], ['id' => 'colleague-1'],
            ], 200),
            '*/rest/v1/telegram_project_tasks*' => function ($request) {
                if ($request->method() === 'GET') {
                    return Http::response([[
                        'id' => 'task-1', 'employee_id' => 'emp-1', 'assignee_employee_id' => 'emp-1',
                        'title' => 'Old title', 'unit' => 'number', 'target' => 10, 'actual' => 0,
                        'status' => 'not_started', 'priority' => 'medium',
                    ]], 200);
                }

                return Http::response([['id' => 'task-1']], 200);
            },
        ]);

        // SLT sees the whole company, so it may reassign to any active
        // employee in it (TaskAccessPolicy::canAssign() -> canView()).
        $this->withSession($this->employeeSession('SLT'))
            ->patch('/mini-app/api/tasks/task-1', [
                'title' => 'Weekly ops sync',
                'unit' => 'number',
                'target' => 10,
                'meeting_time' => '09:00',
                'assignee_employee_id' => 'colleague-1',
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/telegram_project_tasks')
                && $request->method() === 'PATCH'
                && $request['meeting_time'] === '09:00'
                && $request['assignee_employee_id'] === 'colleague-1';
        });
    }

    public function test_update_rejects_a_reassignment_the_caller_is_not_allowed_to_make(): void
    {
        Http::fake([
            '*/rest/v1/telegram_project_tasks*' => function ($request) {
                if ($request->method() === 'GET') {
                    return Http::response([[
                        'id' => 'task-1', 'employee_id' => 'emp-1', 'assignee_employee_id' => 'emp-1',
                        'title' => 'Old title', 'unit' => 'number', 'target' => 10, 'actual' => 0,
                        'status' => 'not_started', 'priority' => 'medium',
                    ]], 200);
                }

                return Http::response([], 200);
            },
        ]);

        // A plain EXECUTIVE may only ever assign to themselves.
        $response = $this->withSession($this->employeeSession('EXECUTIVE'))
            ->patch('/mini-app/api/tasks/task-1', [
                'title' => 'Weekly ops sync',
                'unit' => 'number',
                'target' => 10,
                'assignee_employee_id' => 'someone-else',
            ]);

        $response->assertStatus(403);

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/telegram_project_tasks') && $request->method() === 'PATCH';
        });
    }

    public function test_index_returns_meeting_time_and_the_assignees_name(): void
    {
        Http::fake([
            '*/rest/v1/telegram_project_tasks*' => Http::response([[
                'id' => 'task-1', 'employee_id' => 'emp-1', 'assignee_employee_id' => 'colleague-1',
                'title' => 'Weekly ops sync', 'unit' => 'number', 'target' => 10, 'actual' => 0,
                'status' => 'not_started', 'priority' => 'medium', 'meeting_time' => '14:30:00',
                'project_id' => null,
            ]], 200),
            '*/rest/v1/telegram_project_task_kpi_links*' => Http::response([], 200),
            '*/rest/v1/employees*' => Http::response([[
                'id' => 'colleague-1', 'short_name' => 'Colleague',
            ]], 200),
        ]);

        $response = $this->withSession($this->employeeSession())->get('/mini-app/api/tasks');

        $response->assertOk();
        $task = $response->json('tasks.0');
        $this->assertSame('14:30:00', $task['meeting_time']);
        $this->assertSame('Colleague', $task['assignee_name']);
    }
}
