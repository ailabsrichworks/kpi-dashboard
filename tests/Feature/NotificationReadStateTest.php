<?php

namespace Tests\Feature;

use App\Http\Controllers\NotificationController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Services\NotificationService;
use App\Services\SupabaseService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Mark all as read" and the sidebar/page unread badge used to disagree
 * forever: rows written before this fix never had `is_read` set at insert
 * time, so a fresh row's real value in Postgres is NULL, not false. Every
 * unread-count query and markAllRead() itself filtered on `is_read =>
 * eq.false`, and Postgres's `eq.false` never matches NULL — so those rows
 * were silently skipped by every write and every count, no matter how many
 * times the user pressed "Mark all as read". These tests cover the three
 * places that had to agree: the row is created with a real boolean
 * (NotificationService::notify), the bulk-read endpoint no longer filters on
 * the column it's trying to fix (NotificationController::markAllRead), and
 * the unread count treats NULL the same as false, matching what the
 * Notifications page itself already did client-side (`!n.is_read`).
 */
class NotificationReadStateTest extends TestCase
{
    public function test_notify_inserts_is_read_false_explicitly(): void
    {
        Http::fake([
            '*/rest/v1/notifications*' => Http::response([], 201),
            '*/rest/v1/user_company_roles*' => Http::response([], 200),
            '*/rest/v1/employees*' => Http::response([], 200),
        ]);

        app(NotificationService::class)->notify(
            ['recipient-1'],
            'appraisal_submitted',
            ['id' => 'subject-1', 'name' => 'Subject One'],
            'Test title'
        );

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/notifications')
                && $request->method() === 'POST'
                && array_key_exists('is_read', $request->data())
                && $request['is_read'] === false;
        });
    }

    public function test_mark_all_read_does_not_filter_by_is_read(): void
    {
        Http::fake([
            '*/rest/v1/employees*' => Http::response([[
                'id' => 'employee-1', 'is_active' => true, 'company_code' => 'RGHB',
            ]], 200),
            '*/rest/v1/notifications*' => Http::response([], 200),
        ]);

        $this->withSession(['employee_uuid' => 'employee-1', 'company_code' => 'RGHB']);

        app(NotificationController::class)->markAllRead(app(SupabaseService::class));

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/rest/v1/notifications') || $request->method() !== 'PATCH') {
                return false;
            }

            $query = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return ($query['recipient_employee_id'] ?? null) === 'eq.employee-1'
                && !array_key_exists('is_read', $query)
                && $request['is_read'] === true;
        });
    }

    public function test_unread_notification_count_treats_null_is_read_as_unread(): void
    {
        Http::fake([
            '*/rest/v1/notifications*' => Http::response([
                ['is_read' => null],
                ['is_read' => false],
                ['is_read' => true],
            ], 200),
        ]);

        $this->withSession(['employee_uuid' => 'employee-1']);

        $middleware = app(HandleInertiaRequests::class);
        $ref = new \ReflectionMethod($middleware, 'unreadNotificationCount');
        $ref->setAccessible(true);

        $this->assertSame(2, $ref->invoke($middleware));
    }
}
