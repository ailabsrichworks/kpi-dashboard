<?php

namespace Tests\Feature;

use App\Mail\TaskNotifyMail;
use App\Services\EmailVerificationService;
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
 * `TaskAccessPolicy::canAssign()` the create form already used; plus
 * `due_time` and the optional `notify_email` (an address, independent of
 * assignee_employee_id, that gets emailed about the task) added alongside
 * this test's own EmailVerificationService, which real tests bind with a
 * fake DNS resolver rather than hitting the network.
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

    public function test_store_saves_due_time_and_sends_notify_email_when_the_domain_checks_out(): void
    {
        $this->app->bind(EmailVerificationService::class, fn () => new EmailVerificationService(fn () => true));
        Mail::fake();

        Http::fake([
            '*/rest/v1/telegram_projects*' => Http::response([['id' => 'proj-1']], 200),
            '*/rest/v1/employees*' => Http::response([], 200), // not a known employee -- falls to the domain check
            '*/rest/v1/telegram_project_tasks*' => Http::response([[
                'id' => 'task-1', 'title' => 'Send the client proposal',
                'due_date' => '2026-10-01', 'due_time' => '17:00',
            ]], 201),
        ]);

        $this->withSession($this->employeeSession())
            ->post('/mini-app/api/tasks', [
                'title' => 'Send the client proposal',
                'unit' => 'number',
                'target' => 1,
                'due_date' => '2026-10-01',
                'due_time' => '17:00',
                'notify_email' => 'someone@gmail.com',
            ])
            ->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/telegram_project_tasks')
                && $request->method() === 'POST'
                && $request['due_time'] === '17:00'
                && $request['notify_email'] === 'someone@gmail.com';
        });

        Mail::assertSent(TaskNotifyMail::class, fn ($mail) => $mail->hasTo('someone@gmail.com'));
    }

    public function test_store_rejects_a_notify_email_whose_domain_cannot_receive_mail(): void
    {
        $this->app->bind(EmailVerificationService::class, fn () => new EmailVerificationService(fn () => false));
        Mail::fake();

        Http::fake([
            '*/rest/v1/employees*' => Http::response([], 200), // no matching employee either
        ]);

        $response = $this->withSession($this->employeeSession())
            ->post('/mini-app/api/tasks', [
                'title' => 'Send the client proposal',
                'unit' => 'number',
                'target' => 1,
                'notify_email' => 'typo@thisdomaindoesnotexist.invalid',
            ]);

        $response->assertStatus(422);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rest/v1/telegram_project_tasks'));
        Mail::assertNothingSent();
    }

    public function test_store_accepts_a_notify_email_that_matches_a_known_employee_even_without_a_domain_check(): void
    {
        // The domain check would fail here -- accepted purely because it
        // matches an employee already on file.
        $this->app->bind(EmailVerificationService::class, fn () => new EmailVerificationService(fn () => false));
        Mail::fake();

        Http::fake([
            '*/rest/v1/telegram_projects*' => Http::response([['id' => 'proj-1']], 200),
            '*/rest/v1/employees*' => Http::response([['id' => 'colleague-1']], 200),
            '*/rest/v1/telegram_project_tasks*' => Http::response([['id' => 'task-1', 'title' => 'Weekly ops sync']], 201),
        ]);

        $this->withSession($this->employeeSession())
            ->post('/mini-app/api/tasks', [
                'title' => 'Weekly ops sync',
                'unit' => 'number',
                'target' => 1,
                'notify_email' => 'colleague@richworks.com',
            ])
            ->assertOk();

        Mail::assertSent(TaskNotifyMail::class, fn ($mail) => $mail->hasTo('colleague@richworks.com'));
    }

    public function test_update_does_not_resend_notify_email_when_it_is_unchanged(): void
    {
        $this->app->bind(EmailVerificationService::class, fn () => new EmailVerificationService(fn () => true));
        Mail::fake();

        Http::fake([
            '*/rest/v1/employees*' => Http::response([], 200),
            '*/rest/v1/telegram_project_tasks*' => function ($request) {
                if ($request->method() === 'GET') {
                    return Http::response([[
                        'id' => 'task-1', 'employee_id' => 'emp-1', 'assignee_employee_id' => 'emp-1',
                        'title' => 'Old title', 'unit' => 'number', 'target' => 10, 'actual' => 0,
                        'status' => 'not_started', 'priority' => 'medium', 'notify_email' => 'someone@gmail.com',
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
                'notify_email' => 'someone@gmail.com',
            ])
            ->assertOk();

        Mail::assertNothingSent();
    }

    public function test_update_sends_notify_email_when_it_changes(): void
    {
        $this->app->bind(EmailVerificationService::class, fn () => new EmailVerificationService(fn () => true));
        Mail::fake();

        Http::fake([
            '*/rest/v1/employees*' => Http::response([], 200),
            '*/rest/v1/telegram_project_tasks*' => function ($request) {
                if ($request->method() === 'GET') {
                    return Http::response([[
                        'id' => 'task-1', 'employee_id' => 'emp-1', 'assignee_employee_id' => 'emp-1',
                        'title' => 'Old title', 'unit' => 'number', 'target' => 10, 'actual' => 0,
                        'status' => 'not_started', 'priority' => 'medium', 'notify_email' => 'old@gmail.com',
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
                'notify_email' => 'new@gmail.com',
            ])
            ->assertOk();

        Mail::assertSent(TaskNotifyMail::class, fn ($mail) => $mail->hasTo('new@gmail.com'));
    }
}
