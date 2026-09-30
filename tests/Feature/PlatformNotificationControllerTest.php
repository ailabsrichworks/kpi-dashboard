<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `Platform\NotificationController` — mirrors the legacy
 * `NotificationController` exactly (see `NotificationReadStateTest`'s own
 * docblock for the history): `markAllRead()` must not filter by `is_read`,
 * since a row's real value being NULL instead of false (or any future column
 * default drifting) would otherwise make the bulk-read endpoint silently skip
 * it forever, no matter how many times "Mark all as read" is pressed. RLS
 * (`notifications_update`, `user_id = auth_current_user_id()`) is what scopes
 * this to the caller's own rows — no app-level filter is needed for that.
 */
class PlatformNotificationControllerTest extends TestCase
{
    private function fakeToken(): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => 'employee-auth-id', 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

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
        ];
    }

    public function test_mark_all_read_does_not_filter_by_is_read(): void
    {
        Http::fake(array_merge($this->fakeEmployeeSessionFakes(), [
            '*/rest/v1/notifications*' => Http::response([], 200),
        ]));

        $this->withSession(['platform_access_token' => $this->fakeToken()])
            ->post('/platform/notifications/read-all');

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/rest/v1/notifications') || $request->method() !== 'PATCH') {
                return false;
            }

            $query = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return !array_key_exists('is_read', $query) && $request['is_read'] === true;
        });
    }
}
