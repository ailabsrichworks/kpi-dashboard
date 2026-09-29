<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Platform\ViewAsController — the real "View As" ported from legacy's
 * AdminController (BTS-department session swap) onto real Supabase Auth
 * sessions. Covers: Super-Admin-only gate, the mint-then-swap start() flow,
 * the refusal to double-impersonate, and stop() restoring the ADMIN's own
 * session while attributing its log entry to the admin (not whichever
 * identity `platformUser` resolves to at that point in the request, which by
 * then is the impersonated target).
 */
class ViewAsControllerTest extends TestCase
{
    private function fakeToken(string $sub): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $sub, 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    private function fakeSuperAdminSession(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'super-admin-id', 'name' => 'Arina', 'email' => 'arina@example.com',
                'role' => 'richworks_super_admin', 'status' => 'active',
            ]], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([['id' => 'log-1']], 201),
        ]);
    }

    public function test_index_rejects_a_non_super_admin(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'member-id', 'name' => 'Member', 'email' => 'member@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->get('/platform/admin/view-as');

        $response->assertStatus(403);
    }

    public function test_index_lists_users_for_a_super_admin(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::sequence()
                ->push([[ // PlatformAuth's own "who am I" lookup
                    'id' => 'super-admin-id', 'name' => 'Arina', 'email' => 'arina@example.com',
                    'role' => 'richworks_super_admin', 'status' => 'active',
                ]], 200)
                ->push([ // ViewAsController::index()'s own listing
                    ['id' => 'user-1', 'name' => 'Izzati Ibrahim', 'email' => 'izzati@example.com', 'role' => 'member', 'status' => 'active'],
                ], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('super-admin-auth-id')])
            ->get('/platform/admin/view-as');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Platform/ViewAs/Index')
            ->has('users', 1)
            ->where('users.0.name', 'Izzati Ibrahim'));
    }

    public function test_start_mints_a_real_session_and_swaps_it_in(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::sequence()
                ->push([[
                    'id' => 'super-admin-id', 'name' => 'Arina', 'email' => 'arina@example.com',
                    'role' => 'richworks_super_admin', 'status' => 'active',
                ]], 200)
                ->push([[ // the target lookup inside start()
                    'id' => 'target-id', 'name' => 'Izzati Ibrahim', 'email' => 'izzati@example.com', 'status' => 'active',
                ]], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([['id' => 'log-1']], 201),
            '*/auth/v1/admin/generate_link' => Http::response(['properties' => ['hashed_token' => 'hashed-abc']], 200),
            '*/auth/v1/verify' => Http::response(['access_token' => 'minted-target-token'], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('super-admin-auth-id')])
            ->post('/platform/admin/view-as/target-id/start');

        $response->assertRedirect(route('platform.dashboard'));
        $this->assertSame('minted-target-token', session('platform_access_token'));
        $this->assertTrue(session('admin_impersonating'));
        $this->assertSame('super-admin-id', session('admin_original_session.admin_user_id'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/admin_action_logs')
                && $request->method() === 'POST'
                && ($request['action'] ?? null) === 'start_view_as'
                && ($request['target_user_id'] ?? null) === 'target-id';
        });
    }

    public function test_start_refuses_to_double_impersonate(): void
    {
        $this->fakeSuperAdminSession();

        $response = $this->withSession([
            'platform_access_token' => $this->fakeToken('super-admin-auth-id'),
            'admin_impersonating' => true,
        ])->post('/platform/admin/view-as/target-id/start');

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_stop_restores_the_admins_own_session_and_logs_the_admin_as_actor(): void
    {
        // While "impersonating", PlatformAuth resolves platformUser from the
        // still-live minted target token — stop() must not misattribute its
        // log entry to that identity.
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'target-id', 'name' => 'Izzati Ibrahim', 'email' => 'izzati@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/admin_action_logs*' => Http::response([['id' => 'log-2']], 201),
        ]);

        $response = $this->withSession([
            'platform_access_token' => $this->fakeToken('target-auth-id'),
            'admin_impersonating' => true,
            'admin_original_session' => [
                'platform_access_token' => 'original-admin-token',
                'platform_refresh_token' => 'original-admin-refresh',
                'admin_user_id' => 'super-admin-id',
                'admin_email' => 'arina@example.com',
                'target_user_id' => 'target-id',
            ],
        ])->post('/platform/admin/view-as/stop');

        $response->assertRedirect(route('platform.dashboard'));
        $this->assertSame('original-admin-token', session('platform_access_token'));
        $this->assertNull(session('admin_impersonating'));
        $this->assertNull(session('admin_original_session'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/admin_action_logs')
                && $request->method() === 'POST'
                && ($request['action'] ?? null) === 'stop_view_as'
                && ($request['actor_user_id'] ?? null) === 'super-admin-id'
                && ($request['actor_email'] ?? null) === 'arina@example.com';
        });
    }
}
