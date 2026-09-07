<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('platform.login');
});

// Telegram Mini App shell — opened inside Telegram's WebView, no Laravel session
// is available there, so this stays outside any auth group. Auth for the
// data it loads happens per-request via Telegram initData (see routes/api.php).
Route::view('/telegram/app', 'telegram.app', [
    'botUsername' => env('TELEGRAM_BOT_USERNAME', ''),
])->name('telegram.app');

// Lets the front-end recover from a stale CSRF token without a full page
// reload, even when the session itself (not just the token) has expired.
Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->name('csrf.refresh');

/*
|--------------------------------------------------------------------------
| PERFORMIX PLATFORM (Supabase Auth + RLS)
|--------------------------------------------------------------------------
| The only application this codebase runs. The single-tenant legacy app
| (session/employee-based auth against `employees`/`users.password_hash`)
| was removed once production confirmed that table no longer exists and a
| real Supabase Auth Super Admin account works end-to-end here.
*/

Route::get('/platform/login', [\App\Http\Controllers\Platform\AuthController::class, 'showLogin'])
    ->name('platform.login');

Route::post('/platform/logout', [\App\Http\Controllers\Platform\AuthController::class, 'logout'])
    ->name('platform.logout');

Route::get('/platform/forgot-password', [\App\Http\Controllers\Platform\ForgotPasswordController::class, 'show'])
    ->name('platform.forgot-password');

// Throttled: none of these had any request limiting at all -- unmetered,
// this is a credential-stuffing surface against /platform/login, a
// mail-bombing/Supabase-admin-API-abuse surface against /platform/forgot-password,
// and a token-guessing surface against the two token-redemption endpoints.
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/platform/login', [\App\Http\Controllers\Platform\AuthController::class, 'submitLogin'])
        ->name('platform.login.submit');

    Route::get('/platform/invite/accept', [\App\Http\Controllers\Platform\InviteController::class, 'accept'])
        ->name('platform.invite.accept');

    Route::post('/platform/invite/set-password', [\App\Http\Controllers\Platform\InviteController::class, 'setPassword'])
        ->name('platform.invite.set-password');

    Route::post('/platform/forgot-password', [\App\Http\Controllers\Platform\ForgotPasswordController::class, 'sendResetLink'])
        ->name('platform.forgot-password.send');

    Route::get('/platform/reset-password/accept', [\App\Http\Controllers\Platform\ForgotPasswordController::class, 'accept'])
        ->name('platform.reset-password.accept');
});

Route::middleware(['platform.auth', 'platform.audit'])->prefix('platform')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Platform\DashboardController::class, 'index'])
        ->name('platform.dashboard');

    Route::get('/profile', [\App\Http\Controllers\Platform\ProfileController::class, 'index'])
        ->name('platform.profile');

    Route::post('/profile/password', [\App\Http\Controllers\Platform\ProfileController::class, 'updatePassword'])
        ->name('platform.profile.password');

    Route::post('/telegram/link-code', [\App\Http\Controllers\Platform\TelegramLinkController::class, 'generateCode'])
        ->name('platform.telegram.link-code');

    Route::post('/telegram/disconnect', [\App\Http\Controllers\Platform\TelegramLinkController::class, 'disconnect'])
        ->name('platform.telegram.disconnect');

    Route::get('/audit-log', [\App\Http\Controllers\Platform\AuditLogController::class, 'index'])
        ->name('platform.audit-log.index');

    Route::get('/audit-log/export', [\App\Http\Controllers\Platform\AuditLogController::class, 'export'])
        ->name('platform.audit-log.export');

    Route::get('/companies/{company}/audit-log', [\App\Http\Controllers\Platform\AuditLogController::class, 'companyIndex'])
        ->name('platform.companies.audit-log.index');

    Route::get('/companies/{company}/audit-log/export', [\App\Http\Controllers\Platform\AuditLogController::class, 'companyExport'])
        ->name('platform.companies.audit-log.export');

    Route::get('/anira', [\App\Http\Controllers\Platform\AniraController::class, 'index'])
        ->name('platform.anira.index');

    Route::post('/ai/chat', [\App\Http\Controllers\Platform\AniraController::class, 'chat'])
        ->name('platform.ai.chat');

    Route::get('/admins', [\App\Http\Controllers\Platform\PlatformAdminController::class, 'index'])
        ->name('platform.admins.index');

    Route::post('/admins', [\App\Http\Controllers\Platform\PlatformAdminController::class, 'store'])
        ->name('platform.admins.store');

    Route::delete('/admins/{assignment}', [\App\Http\Controllers\Platform\PlatformAdminController::class, 'destroy'])
        ->name('platform.admins.destroy');

    Route::post('/admins/{user}/demote', [\App\Http\Controllers\Platform\PlatformAdminController::class, 'demote'])
        ->name('platform.admins.demote');

    Route::get('/companies', [\App\Http\Controllers\Platform\CompanyController::class, 'index'])
        ->name('platform.companies.index');

    Route::post('/companies', [\App\Http\Controllers\Platform\CompanyController::class, 'store'])
        ->name('platform.companies.store');

    Route::post('/companies/{company}/admins', [\App\Http\Controllers\Platform\CompanyController::class, 'storeAdmin'])
        ->name('platform.companies.admins.store');

    Route::post('/companies/{company}/activate', [\App\Http\Controllers\Platform\CompanyController::class, 'activate'])
        ->name('platform.companies.activate');

    Route::post('/companies/{company}/suspend', [\App\Http\Controllers\Platform\CompanyController::class, 'suspend'])
        ->name('platform.companies.suspend');

    Route::post('/companies/{company}/reactivate', [\App\Http\Controllers\Platform\CompanyController::class, 'reactivate'])
        ->name('platform.companies.reactivate');

    Route::post('/companies/{company}/archive', [\App\Http\Controllers\Platform\CompanyController::class, 'archive'])
        ->name('platform.companies.archive');

    Route::post('/companies/{company}/unarchive', [\App\Http\Controllers\Platform\CompanyController::class, 'unarchive'])
        ->name('platform.companies.unarchive');

    Route::post('/companies/{company}/branding', [\App\Http\Controllers\Platform\CompanyController::class, 'updateBranding'])
        ->name('platform.companies.branding');

    Route::post('/companies/{company}/subscription', [\App\Http\Controllers\Platform\CompanyController::class, 'updateSubscription'])
        ->name('platform.companies.subscription');

    Route::post('/companies/{company}/dashboard-layout', [\App\Http\Controllers\Platform\DashboardWidgetController::class, 'updateLayout'])
        ->name('platform.companies.dashboard-layout');

    Route::get('/companies/{company}/onboarding', [\App\Http\Controllers\Platform\OnboardingController::class, 'index'])
        ->name('platform.onboarding.show');

    Route::get('/companies/{company}/onboarding/assign-roles', [\App\Http\Controllers\Platform\OnboardingController::class, 'assignRoles'])
        ->name('platform.onboarding.assign-roles');

    Route::get('/companies/{company}/onboarding/reporting-hierarchy', [\App\Http\Controllers\Platform\OnboardingController::class, 'reportingHierarchy'])
        ->name('platform.onboarding.reporting-hierarchy');

    Route::get('/companies/{company}/onboarding/anira-config', [\App\Http\Controllers\Platform\OnboardingController::class, 'aniraConfig'])
        ->name('platform.onboarding.anira-config');

    Route::get('/companies/{company}/onboarding/telegram-config', [\App\Http\Controllers\Platform\OnboardingController::class, 'telegramConfig'])
        ->name('platform.onboarding.telegram-config');

    Route::get('/companies/{company}/review-settings', [\App\Http\Controllers\Platform\OnboardingController::class, 'reviewSettings'])
        ->name('platform.review-settings.show');

    Route::get('/companies/{company}/import', [\App\Http\Controllers\Platform\ImportController::class, 'show'])
        ->name('platform.import.show');

    Route::post('/companies/{company}/import/preview', [\App\Http\Controllers\Platform\ImportController::class, 'preview'])
        ->name('platform.import.preview');

    Route::post('/companies/{company}/import/confirm', [\App\Http\Controllers\Platform\ImportController::class, 'confirm'])
        ->name('platform.import.confirm');

    Route::get('/companies/{company}/import/{batch}/users', [\App\Http\Controllers\Platform\UserCreationController::class, 'show'])
        ->name('platform.import.users.show');

    Route::post('/companies/{company}/import/{batch}/users', [\App\Http\Controllers\Platform\UserCreationController::class, 'store'])
        ->name('platform.import.users.store');

    Route::get('/companies/{company}/organisation', [\App\Http\Controllers\Platform\OrganisationController::class, 'index'])
        ->name('platform.organisation.index');

    Route::get('/companies/{company}/departments', [\App\Http\Controllers\Platform\DepartmentController::class, 'index'])
        ->name('platform.departments.index');

    Route::post('/companies/{company}/departments', [\App\Http\Controllers\Platform\DepartmentController::class, 'store'])
        ->name('platform.departments.store');

    Route::post('/companies/{company}/departments/{department}/users', [\App\Http\Controllers\Platform\DepartmentController::class, 'storeUser'])
        ->name('platform.departments.users.store');

    Route::patch('/companies/{company}/departments/{department}/users/{user}/role', [\App\Http\Controllers\Platform\DepartmentController::class, 'updateUserRole'])
        ->name('platform.departments.users.role.update');

    Route::patch('/companies/{company}/departments/{department}/users/{user}/reporting', [\App\Http\Controllers\Platform\DepartmentController::class, 'updateReporting'])
        ->name('platform.departments.users.reporting.update');

    Route::post('/companies/{company}/users/{user}/suspend', [\App\Http\Controllers\Platform\DepartmentController::class, 'suspendUser'])
        ->name('platform.companies.users.suspend');

    Route::post('/companies/{company}/users/{user}/reactivate', [\App\Http\Controllers\Platform\DepartmentController::class, 'reactivateUser'])
        ->name('platform.companies.users.reactivate');

    Route::post('/companies/{company}/departments/{department}/roles', [\App\Http\Controllers\Platform\RoleController::class, 'store'])
        ->name('platform.roles.store');

    Route::delete('/companies/{company}/departments/{department}/roles/{role}', [\App\Http\Controllers\Platform\RoleController::class, 'destroy'])
        ->name('platform.roles.destroy');

    Route::get('/companies/{company}/goals', [\App\Http\Controllers\Platform\CompanyGoalController::class, 'index'])
        ->name('platform.goals.index');

    Route::post('/companies/{company}/goals', [\App\Http\Controllers\Platform\CompanyGoalController::class, 'store'])
        ->name('platform.goals.store');

    Route::patch('/companies/{company}/goals/{goal}', [\App\Http\Controllers\Platform\CompanyGoalController::class, 'update'])
        ->name('platform.goals.update');

    Route::get('/companies/{company}/kpis', [\App\Http\Controllers\Platform\KpiController::class, 'index'])
        ->name('platform.kpis.index');

    Route::post('/companies/{company}/kpi-categories', [\App\Http\Controllers\Platform\KpiController::class, 'storeCategory'])
        ->name('platform.kpi-categories.store');

    Route::post('/companies/{company}/kpis', [\App\Http\Controllers\Platform\KpiController::class, 'store'])
        ->name('platform.kpis.store');

    Route::patch('/companies/{company}/kpis/{kpi}', [\App\Http\Controllers\Platform\KpiController::class, 'update'])
        ->name('platform.kpis.update');

    Route::post('/companies/{company}/kpis/apply-template', [\App\Http\Controllers\Platform\KpiController::class, 'applyTemplate'])
        ->name('platform.kpis.apply-template');

    Route::post('/companies/{company}/kpis/{kpi}/period-targets', [\App\Http\Controllers\Platform\KpiPeriodTargetController::class, 'store'])
        ->name('platform.kpis.period-targets.store');

    Route::post('/companies/{company}/kpis/{kpi}/grants', [\App\Http\Controllers\Platform\KpiController::class, 'storeGrant'])
        ->name('platform.kpis.grants.store');

    Route::delete('/companies/{company}/kpis/{kpi}/grants/{grant}', [\App\Http\Controllers\Platform\KpiController::class, 'destroyGrant'])
        ->name('platform.kpis.grants.destroy');

    Route::post('/companies/{company}/kpis/{kpi}/target-revisions', [\App\Http\Controllers\Platform\TargetRevisionController::class, 'store'])
        ->name('platform.kpis.target-revisions.store');

    Route::get('/companies/{company}/periods', [\App\Http\Controllers\Platform\PeriodController::class, 'index'])
        ->name('platform.periods.index');

    Route::post('/companies/{company}/periods', [\App\Http\Controllers\Platform\PeriodController::class, 'update'])
        ->name('platform.periods.update');

    Route::get('/companies/{company}/approvals', [\App\Http\Controllers\Platform\ApprovalController::class, 'index'])
        ->name('platform.approvals.index');

    Route::post('/companies/{company}/approvals/{approvalRequest}/decide', [\App\Http\Controllers\Platform\ApprovalController::class, 'decide'])
        ->name('platform.approvals.decide');

    Route::get('/companies/{company}/tasks', [\App\Http\Controllers\Platform\TaskController::class, 'index'])
        ->name('platform.tasks.index');

    Route::post('/companies/{company}/tasks', [\App\Http\Controllers\Platform\TaskController::class, 'store'])
        ->name('platform.tasks.store');

    Route::patch('/companies/{company}/tasks/{task}', [\App\Http\Controllers\Platform\TaskController::class, 'update'])
        ->name('platform.tasks.update');

    Route::delete('/companies/{company}/tasks/{task}', [\App\Http\Controllers\Platform\TaskController::class, 'destroy'])
        ->name('platform.tasks.destroy');

    Route::put('/companies/{company}/tasks/{task}/kpi-links', [\App\Http\Controllers\Platform\TaskController::class, 'updateKpiLinks'])
        ->name('platform.tasks.kpi-links.update');

    Route::get('/kpi-templates', [\App\Http\Controllers\Platform\KpiTemplateController::class, 'index'])
        ->name('platform.kpi-templates.index');

    Route::post('/kpi-templates', [\App\Http\Controllers\Platform\KpiTemplateController::class, 'store'])
        ->name('platform.kpi-templates.store');

    Route::delete('/kpi-templates/{template}', [\App\Http\Controllers\Platform\KpiTemplateController::class, 'destroy'])
        ->name('platform.kpi-templates.destroy');

    Route::post('/kpi-templates/{template}/items', [\App\Http\Controllers\Platform\KpiTemplateController::class, 'storeItem'])
        ->name('platform.kpi-templates.items.store');

    Route::delete('/kpi-templates/{template}/items/{item}', [\App\Http\Controllers\Platform\KpiTemplateController::class, 'destroyItem'])
        ->name('platform.kpi-templates.items.destroy');

    Route::get('/subscription-plans', [\App\Http\Controllers\Platform\SubscriptionPlanController::class, 'index'])
        ->name('platform.subscription-plans.index');

    Route::post('/subscription-plans', [\App\Http\Controllers\Platform\SubscriptionPlanController::class, 'store'])
        ->name('platform.subscription-plans.store');

    Route::patch('/subscription-plans/{plan}', [\App\Http\Controllers\Platform\SubscriptionPlanController::class, 'update'])
        ->name('platform.subscription-plans.update');

    Route::delete('/subscription-plans/{plan}', [\App\Http\Controllers\Platform\SubscriptionPlanController::class, 'destroy'])
        ->name('platform.subscription-plans.destroy');

    Route::get('/companies/{company}/departments/{department}/submissions', [\App\Http\Controllers\Platform\KpiSubmissionController::class, 'index'])
        ->name('platform.submissions.index');

    Route::post('/companies/{company}/departments/{department}/submissions', [\App\Http\Controllers\Platform\KpiSubmissionController::class, 'store'])
        ->name('platform.submissions.store');

    /*
    |----------------------------------------------------------------------
    | CEO / Top Management — company performance command centre
    |----------------------------------------------------------------------
    */

    Route::get('/companies/{company}/performance', [\App\Http\Controllers\Platform\CompanyPerformanceController::class, 'index'])
        ->name('platform.performance.index');

    Route::get('/companies/{company}/performance/goals/{goal}', [\App\Http\Controllers\Platform\CompanyPerformanceController::class, 'goal'])
        ->name('platform.performance.goal');

    /*
    |----------------------------------------------------------------------
    | HR / People Management — people performance command centre
    |----------------------------------------------------------------------
    */

    Route::get('/companies/{company}/people', [\App\Http\Controllers\Platform\PeopleController::class, 'index'])
        ->name('platform.people.index');

    Route::get('/companies/{company}/people/employees', [\App\Http\Controllers\Platform\PeopleController::class, 'employees'])
        ->name('platform.people.employees');

    Route::get('/companies/{company}/people/managers', [\App\Http\Controllers\Platform\PeopleController::class, 'managers'])
        ->name('platform.people.managers');

    /*
    |----------------------------------------------------------------------
    | Performix HQ — client success (Center-only, see PlatformAuthorization)
    |----------------------------------------------------------------------
    */

    Route::get('/hq/client-health', [\App\Http\Controllers\Platform\ClientHealthController::class, 'index'])
        ->name('platform.hq.client-health.index');

    Route::get('/hq/client-health/{company}', [\App\Http\Controllers\Platform\ClientHealthController::class, 'show'])
        ->name('platform.hq.client-health.show');

    Route::post('/hq/client-health/{company}', [\App\Http\Controllers\Platform\ClientHealthController::class, 'store'])
        ->name('platform.hq.client-health.store');

    Route::get('/hq/renewals', [\App\Http\Controllers\Platform\RenewalController::class, 'index'])
        ->name('platform.hq.renewals.index');

    Route::get('/hq/cam-team', [\App\Http\Controllers\Platform\CamController::class, 'index'])
        ->name('platform.hq.cam-team.index');

    Route::post('/hq/companies/{company}/cam', [\App\Http\Controllers\Platform\CamController::class, 'assign'])
        ->name('platform.hq.cam.assign');

    Route::get('/hq/actions', [\App\Http\Controllers\Platform\CamController::class, 'actions'])
        ->name('platform.hq.actions.index');

    Route::post('/hq/actions', [\App\Http\Controllers\Platform\CamController::class, 'storeAction'])
        ->name('platform.hq.actions.store');

    Route::patch('/hq/actions/{action}', [\App\Http\Controllers\Platform\CamController::class, 'updateAction'])
        ->name('platform.hq.actions.update');

    Route::get('/hq/modules', [\App\Http\Controllers\Platform\ModuleController::class, 'index'])
        ->name('platform.hq.modules.index');

    Route::post('/hq/companies/{company}/modules/{module}', [\App\Http\Controllers\Platform\ModuleController::class, 'toggle'])
        ->name('platform.hq.modules.toggle');

    Route::get('/hq/support', [\App\Http\Controllers\Platform\SupportController::class, 'index'])
        ->name('platform.hq.support.index');

    Route::post('/companies/{company}/support-tickets', [\App\Http\Controllers\Platform\SupportController::class, 'store'])
        ->name('platform.support-tickets.store');

    Route::patch('/hq/support/{ticket}', [\App\Http\Controllers\Platform\SupportController::class, 'update'])
        ->name('platform.hq.support.update');

    /*
    |----------------------------------------------------------------------
    | Performix HQ — Settings hub (Super Admin only, see PlatformAuthorization)
    |----------------------------------------------------------------------
    */

    Route::get('/settings', [\App\Http\Controllers\Platform\SettingsController::class, 'index'])
        ->name('platform.settings.index');

    Route::get('/settings/integrations', [\App\Http\Controllers\Platform\SettingsController::class, 'integrations'])
        ->name('platform.settings.integrations');

    Route::get('/settings/api-keys', [\App\Http\Controllers\Platform\SettingsController::class, 'apiKeys'])
        ->name('platform.settings.api-keys');

    Route::get('/settings/billing', [\App\Http\Controllers\Platform\SettingsController::class, 'billing'])
        ->name('platform.settings.billing');

    Route::get('/settings/compliance', [\App\Http\Controllers\Platform\SettingsController::class, 'compliance'])
        ->name('platform.settings.compliance');
});
