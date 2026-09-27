<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Performix HQ's own settings hub — Super-Admin-only. Deliberately not a
 * mega-controller: every item that already has a real, working screen
 * elsewhere (Companies, Modules, Subscription Plans, KPI Templates, Platform
 * Admins, Audit Log) is linked to from SettingsNav rather than reimplemented,
 * matching this codebase's own pattern of pointing at an existing screen
 * instead of duplicating one (see OnboardingController::reportingHierarchy()).
 *
 * The pages this controller actually renders are scoped to what the app can
 * honestly say today:
 *   - Integrations / API Keys read environment configuration and report
 *     presence/absence. There is no per-tenant integration-settings table
 *     to back an editable form (see CLAUDE.md's "Configuration tiering"
 *     notes on AI provider / Telegram settings both being unbuilt), so these
 *     stay read-only rather than faking a save button nothing would persist.
 *   - Billing is an honest "not built" placeholder — subscription *plans*
 *     are real (SubscriptionPlanController); invoicing/payment processing
 *     is not, anywhere in this codebase.
 *   - Compliance describes the real, already-enforced guarantees (RLS
 *     tenant isolation, append-only audit trail) rather than a fabricated
 *     certifications list.
 */
class SettingsController extends Controller
{
    use PlatformAuthorization;

    public function index(Request $request)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companies = $supabase->get('companies', ['select' => 'id', 'limit' => 200]);
        $admins = $supabase->get('platform_admin_assignments', ['select' => 'user_id']);
        $templates = $supabase->get('kpi_templates', ['select' => 'id']);
        $plans = $supabase->get('subscription_plans', ['select' => 'id']);

        return Inertia::render('Platform/Settings/Index', [
            'stats' => [
                'companies' => count($companies),
                'platform_admins' => count(array_unique(array_column($admins, 'user_id'))),
                'kpi_templates' => count($templates),
                'subscription_plans' => count($plans),
            ],
            'integrations' => $this->integrationSummary(),
        ]);
    }

    public function integrations(Request $request)
    {
        $this->ensureSuperAdmin($request);

        return Inertia::render('Platform/Settings/Integrations', [
            'integrations' => $this->integrationSummary(),
        ]);
    }

    public function apiKeys(Request $request)
    {
        $this->ensureSuperAdmin($request);

        return Inertia::render('Platform/Settings/ApiKeys', [
            'keys' => [
                [
                    'name' => 'OPENAI_API_KEY',
                    'purpose' => 'ANIRA — chat, KPI scoring, description suggestions',
                    'value' => $this->mask(env('OPENAI_API_KEY')),
                ],
                [
                    'name' => 'TELEGRAM_BOT_TOKEN',
                    'purpose' => 'Telegram Bot API access',
                    'value' => $this->mask(env('TELEGRAM_BOT_TOKEN')),
                ],
                [
                    'name' => 'TELEGRAM_WEBHOOK_SECRET',
                    'purpose' => 'Validates incoming Telegram webhook requests',
                    'value' => $this->mask(env('TELEGRAM_WEBHOOK_SECRET')),
                ],
                [
                    'name' => 'TELEGRAM_CRON_SECRET',
                    'purpose' => 'Authorises cron-triggered Telegram digests',
                    'value' => $this->mask(env('TELEGRAM_CRON_SECRET')),
                ],
            ],
        ]);
    }

    public function billing(Request $request)
    {
        $this->ensureSuperAdmin($request);

        return Inertia::render('Platform/Settings/Billing');
    }

    public function compliance(Request $request)
    {
        $this->ensureSuperAdmin($request);

        return Inertia::render('Platform/Settings/Compliance');
    }

    private function integrationSummary(): array
    {
        $telegramUsername = env('TELEGRAM_BOT_USERNAME');
        $mailer = env('MAIL_MAILER');

        return [
            [
                'name' => 'ANIRA (OpenAI)',
                'configured' => !empty(env('OPENAI_API_KEY')),
                'detail' => !empty(env('OPENAI_API_KEY')) ? 'API key configured' : 'OPENAI_API_KEY not set',
            ],
            [
                'name' => 'Telegram',
                'configured' => !empty(env('TELEGRAM_BOT_TOKEN')),
                'detail' => $telegramUsername ? '@' . ltrim($telegramUsername, '@') : 'Bot token not set',
            ],
            [
                'name' => 'Email',
                'configured' => !empty($mailer) && $mailer !== 'log',
                'detail' => 'Driver: ' . ($mailer ?: 'not set'),
            ],
        ];
    }

    private function mask(?string $value): string
    {
        if (empty($value)) {
            return 'Not configured';
        }

        return strlen($value) <= 8
            ? str_repeat('•', strlen($value))
            : substr($value, 0, 4) . str_repeat('•', 8) . substr($value, -4);
    }
}
