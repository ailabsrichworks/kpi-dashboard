<?php

namespace App\Http\Controllers\Platform\Concerns;

/**
 * Where to send someone the moment they have a working Platform session —
 * shared between `AuthController::submitLogin()` (an ordinary password
 * login) and `InviteController::setPassword()` (finishing an invite or a
 * password reset). Both are "a session now exists, decide the landing page"
 * moments for the exact same account state, so they must never disagree.
 *
 * Everyone lands on `platform.dashboard`, matching the Richworks reference —
 * login always opens the Main Dashboard, regardless of role. That one route
 * already branches by platform tier internally (`DashboardController::index()`:
 * a Richworks Super Admin gets the Center-wide operator overview, everyone
 * else gets their own RLS-scoped company view), so there's nothing left for
 * this trait to decide — no extra Supabase round trip needed just to pick a
 * landing page.
 */
trait ResolvesLandingUrl
{
    protected function landingUrlFor(string $accessToken): string
    {
        return route('platform.dashboard');
    }
}
