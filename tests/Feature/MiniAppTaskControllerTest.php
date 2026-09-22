<?php

namespace Tests\Feature;

use App\Mail\TaskNotifyMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * `MiniAppTaskController` — the web "Performix" To-Do/Kanban board (the
 * page users actually reach via the sidebar's "Performix" link, not the
 * separate Platform Tasks feature under `Platform/Tasks/Index.tsx`).
 * Covers the two things added alongside the Kanban redesign: an optional
 * `meeting_time` (distinct from the date-only `due_date`) and reassigning a
 * task's `assignee_employee_id` from Edit, both gated by the same
 * `TaskAccessPolicy::canAssign()` the create form already used; the
 * `/tasks/assignable` endpoint that both Assign To and Notify by email
 * draw their options from (same `TaskAccessPolicy::visibleEmployeeIds()`
 * scope — an SLT sees the whole company, an EXECUTIVE with no reports sees
 * only themselves); and `due_time`/`notify_email`, where notify_email is
 * only ever accepted if it matches an employee already on file in
 * Supabase — there is no other way to satisfy it.
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

    public function test_store_saves_due_time_and_sends_notify_email_to_a_known_employee(): void
    {
        Mail::fake();

        Http::fake([
            '*/rest/v1/telegram_projects*' => Http::response([['id' => 'proj-1']], 200),
            '*/rest/v1/employees*' => Http::response([['id' => 'colleague-1', 'email' => 'colleague@richworks.com']], 200),
            '*/rest/v1/telegram_project_tasks*' => Http::response([[
                'id' => 'task-1', 'title' => 'Send the client proposal',
                'due_date' => '2026-10-01', 'due_time' => '17:00', 'priority' => 'high',
            ]], 201),
        ]);

        $this->withSession($this->employeeSession())
            ->post('/mini-app/api/tasks', [
                'title' => 'Send the client proposal',
                'unit' => 'number',
                'target' => 1,
                'due_date' => '2026-10-01',
                'due_time' => '17:00',
                'notify_email' => 'colleague@richworks.com',
            ])
            ->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/telegram_project_tasks')
                && $request->method() === 'POST'
                && $request['due_time'] === '17:00'
                && $request['notify_email'] === 'colleague@richworks.com';
        });

        // Subject is the task title itself (not a "TTD:" prefix); recipient
        // is exactly the chosen notify_email; the description and the due
        // date/time/priority/creator detail render in the body.
        Mail::assertSent(TaskNotifyMail::class, function ($mail) {
            $mail->assertHasSubject('Send the client proposal');
            $mail->assertTo('colleague@richworks.com');

            return true;
        });
    }

    public function test_store_rejects_a_notify_email_that_does_not_match_any_employee(): void
    {
        Mail::fake();

        Http::fake([
            '*/rest/v1/employees*' => Http::response([], 200), // no matching employee
        ]);

        $response = $this->withSession($this->employeeSession())
            ->post('/mini-app/api/tasks', [
                'title' => 'Send the client proposal',
                'unit' => 'number',
                'target' => 1,
                'notify_email' => 'someone-outside@gmail.com',
            ]);

        $response->assertStatus(422);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rest/v1/telegram_project_tasks'));
        Mail::assertNothingSent();
    }

    public function test_update_does_not_resend_notify_email_when_it_is_unchanged(): void
    {
        Mail::fake();

        Http::fake([
            '*/rest/v1/employees*' => Http::response([['id' => 'colleague-1', 'email' => 'colleague@richworks.com']], 200),
            '*/rest/v1/telegram_project_tasks*' => function ($request) {
                if ($request->method() === 'GET') {
                    return Http::response([[
                        'id' => 'task-1', 'employee_id' => 'emp-1', 'assignee_employee_id' => 'emp-1',
                        'title' => 'Old title', 'unit' => 'number', 'target' => 10, 'actual' => 0,
                        'status' => 'not_started', 'priority' => 'medium', 'notify_email' => 'colleague@richworks.com',
                    ]], 200);
                }

                return Http::response([['id' => 'task-1']], 200);
            },
        ]);

        $this->withSession($this->employeeSession())
            ->patch('/mini-app/api/tasks/task-1', [
                'title' => 'Weekly ops sync',
                'unit' => 'number',
                'target' => 10,
                'notify_email' => 'colleague@richworks.com',
            ])
            ->assertOk();

        // The ordinary "task updated" activity notification (which may email
        // the actor themself via AppNotificationMail) still fires as normal
        // -- only the dedicated TaskNotifyMail resend should be suppressed.
        Mail::assertNotSent(TaskNotifyMail::class);
    }

    public function test_update_sends_notify_email_when_it_changes_to_another_known_employee(): void
    {
        Mail::fake();

        Http::fake([
            '*/rest/v1/employees*' => Http::response([['id' => 'colleague-2', 'email' => 'newcolleague@richworks.com']], 200),
            '*/rest/v1/telegram_project_tasks*' => function ($request) {
                if ($request->method() === 'GET') {
                    return Http::response([[
                        'id' => 'task-1', 'employee_id' => 'emp-1', 'assignee_employee_id' => 'emp-1',
                        'title' => 'Old title', 'unit' => 'number', 'target' => 10, 'actual' => 0,
                        'status' => 'not_started', 'priority' => 'medium', 'notify_email' => 'colleague@richworks.com',
                    ]], 200);
                }

                return Http::response([['id' => 'task-1']], 200);
            },
        ]);

        $this->withSession($this->employeeSession())
            ->patch('/mini-app/api/tasks/task-1', [
                'title' => 'Weekly ops sync',
                'unit' => 'number',
                'target' => 10,
                'notify_email' => 'newcolleague@richworks.com',
            ])
            ->assertOk();

        Mail::assertSent(TaskNotifyMail::class, fn ($mail) => $mail->hasTo('newcolleague@richworks.com'));
    }

    public function test_assignable_employees_returns_the_whole_company_for_slt(): void
    {
        Http::fake([
            '*/rest/v1/employees*' => Http::response([
                ['id' => 'emp-1', 'short_name' => 'Test User', 'email' => 'test@richworks.com'],
                ['id' => 'colleague-1', 'short_name' => 'Colleague One', 'email' => 'colleague1@richworks.com'],
                ['id' => 'colleague-2', 'short_name' => 'Colleague Two', 'email' => 'colleague2@richworks.com'],
            ], 200),
        ]);

        $response = $this->withSession($this->employeeSession('SLT'))->get('/mini-app/api/tasks/assignable');

        $response->assertOk();
        $ids = collect($response->json('employees'))->pluck('id')->all();
        $this->assertContains('emp-1', $ids);
        $this->assertContains('colleague-1', $ids);
        $this->assertContains('colleague-2', $ids);
    }

    public function test_assignable_employees_are_ordered_alphabetically_by_supabase_itself(): void
    {
        // The sort is a query-level rule (Supabase's own ORDER BY), not a
        // PHP-side re-sort of whatever order happened to come back -- same
        // 'short_name.asc' convention DashboardController already uses.
        // Http::fake() doesn't simulate PostgREST ordering, so this asserts
        // the outgoing request actually asks for it, rather than asserting
        // an order the fake can't produce on its own.
        Http::fake([
            '*/rest/v1/employees*' => Http::response([
                ['id' => 'emp-1', 'short_name' => 'Test User', 'email' => 'test@richworks.com'],
            ], 200),
        ]);

        $this->withSession($this->employeeSession('SLT'))->get('/mini-app/api/tasks/assignable')->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/employees')
                && str_contains($request->url(), 'order=short_name.asc');
        });
    }

    public function test_assignable_employees_defaults_to_self_only_for_an_executive_with_no_reports(): void
    {
        // EXECUTIVE never even queries `employees` -- visibleEmployeeIds()
        // returns just the actor's own id directly (no subordinates to see).
        Http::fake([
            '*/rest/v1/employees*' => Http::response([
                ['id' => 'emp-1', 'short_name' => 'Test User', 'email' => 'test@richworks.com'],
            ], 200),
        ]);

        $response = $this->withSession($this->employeeSession('EXECUTIVE'))->get('/mini-app/api/tasks/assignable');

        $response->assertOk();
        $ids = collect($response->json('employees'))->pluck('id')->all();
        $this->assertSame(['emp-1'], $ids);
    }
}
