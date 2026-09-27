<?php

namespace Tests\Feature;

use App\Services\NotificationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * notify() is documented as "each recipient is handled independently so one
 * failure doesn't block the rest" -- but sendTelegram()'s own chat-id lookup
 * (two more Supabase calls, telegramChatIdFor()) sat outside its try/catch,
 * so a lookup failure (a real Supabase hiccup, or simply an endpoint this
 * test forgot to fake) crashed the whole notify() call instead of just
 * skipping that recipient's Telegram message, exactly the failure mode this
 * class exists to prevent. Regression test for that fix.
 */
class NotificationServiceTest extends TestCase
{
    public function test_notify_survives_a_failed_telegram_chat_id_lookup(): void
    {
        Http::fake([
            '*/rest/v1/notifications*' => Http::response([['id' => 'notif-1']], 201),
            '*/rest/v1/employees*' => Http::response([], 200),
            // user_company_roles is deliberately left unfaked -- notify() must
            // not throw even though this lookup will fail.
        ]);

        $notifications = app(NotificationService::class);

        $notifications->notify(
            ['emp-1'],
            'test_event',
            ['id' => 'emp-1', 'name' => 'Test User'],
            'Test notification',
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), '/rest/v1/notifications'));
    }
}
