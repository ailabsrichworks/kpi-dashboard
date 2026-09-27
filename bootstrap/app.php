<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function ($middleware) {
        $middleware->alias([
            'kpi.auth' => \App\Http\Middleware\KpiAuth::class,
            'telegram.webapp.auth' => \App\Http\Middleware\TelegramWebAppAuth::class,
            'telegram.webhook.secret' => \App\Http\Middleware\TelegramWebhookSecret::class,
            'telegram.cron.secret' => \App\Http\Middleware\TelegramCronSecret::class,
            'no-cache' => \App\Http\Middleware\NoCacheHeaders::class,
            'telegram.linked' => \App\Http\Middleware\EnsureTelegramLinked::class,
            'platform.auth' => \App\Http\Middleware\PlatformAuth::class,
            'platform.audit' => \App\Http\Middleware\LogPlatformAccessDenials::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Railway terminates TLS at its own edge and forwards plain HTTP to
        // this container, setting X-Forwarded-Proto/For/Host itself — without
        // trusting that proxy, Laravel thinks every request is insecure
        // (wrong scheme in generated URLs, wrong client IP in audit logs).
        // TRUSTED_PROXIES=* was already declared in .env.example with this
        // exact intent but was never actually wired up here. Left unset
        // (Laravel's own default: trust nothing) when the env var isn't
        // configured at all, e.g. local dev and CI.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
