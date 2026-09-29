<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\SupabaseAuthService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Self-service profile for the multi-company Platform — the "user profile /
 * role detection / organization detection" piece of Phase 4 that had no
 * screen of its own yet. `platformUser` is already fully assembled by
 * PlatformAuth on every request (id, name, email, is_super_admin,
 * company_memberships with each membership's role and company name/code),
 * so this controller has nothing left to fetch.
 */
class ProfileController extends Controller
{
    private const THEME_FIELDS = [
        'theme_bg', 'theme_card', 'theme_accent', 'theme_accent2', 'theme_border', 'theme_text',
        'theme_sidebar_bg', 'theme_sidebar_accent', 'theme_sidebar_text', 'theme_font_family', 'theme_font_size',
    ];

    public function index(Request $request)
    {
        $me = $request->attributes->get('platformUser');
        $supabase = $request->attributes->get('platformSupabase');

        // Filtered explicitly on PlatformAuth's already-resolved id, not left
        // unfiltered — a Super Admin or Company Admin can see other users'
        // rows under RLS, so an unfiltered lookup here isn't guaranteed to be
        // "yourself." See SupabaseUserService::currentAuthUserId()'s docblock.
        $row = $supabase->first('users', [
            'id' => 'eq.' . $me['id'],
            'select' => 'telegram_username,telegram_linked_at,' . implode(',', self::THEME_FIELDS),
        ]);

        return Inertia::render('Platform/Profile', [
            'me' => $me,
            'telegram' => [
                'linked' => !empty($row['telegram_linked_at']),
                'username' => $row['telegram_username'] ?? null,
            ],
            'theme' => array_intersect_key($row ?? [], array_flip(self::THEME_FIELDS)),
        ]);
    }

    public function updatePassword(Request $request, SupabaseAuthService $auth)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $auth->setPassword(session('platform_access_token'), $request->password);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not update your password: ' . $e->getMessage());
        }

        return back()->with('success', 'Password updated.');
    }

    /**
     * Ports legacy's Account Settings appearance theme (ProfileController::
     * updateTheme(), persisted on `employees`) — see the migration's own
     * docblock (2026_09_29_100000_add_theme_preferences_to_users.php) for why
     * no new RLS was needed. Every field is nullable and independently
     * clearable (sending an empty string resets that one field to the
     * Platform's own default), matching legacy's own "any field can be
     * unset" behavior.
     */
    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'theme_bg' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme_card' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme_accent' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme_accent2' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme_border' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme_text' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme_sidebar_bg' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme_sidebar_accent' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme_sidebar_text' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme_font_family' => 'nullable|in:Inter,Poppins,Roboto,Nunito,Merriweather,Fira Code',
            'theme_font_size' => 'nullable|in:sm,md,lg',
        ]);

        $me = $request->attributes->get('platformUser');
        $supabase = $request->attributes->get('platformSupabase');

        $payload = [];
        foreach (self::THEME_FIELDS as $field) {
            $payload[$field] = $validated[$field] ?: null;
        }

        try {
            $supabase->update('users', ['id' => 'eq.' . $me['id']], $payload, false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not save your theme: ' . $e->getMessage());
        }

        return back()->with('success', 'Appearance updated.');
    }
}
