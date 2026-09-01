<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\KpiTemplateController;
use App\Http\Controllers\TitanKpiController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AiController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('platform.login');
});

// Telegram Mini App shell — opened inside Telegram's WebView, no Laravel session
// is available there, so this stays outside the kpi.auth group. Auth for the
// data it loads happens per-request via Telegram initData (see routes/api.php).
Route::view('/telegram/app', 'telegram.app', [
    'botUsername' => env('TELEGRAM_BOT_USERNAME', ''),
])->name('telegram.app');

// Login system correction, reversed (2026-09-01): the reasoning below was
// correct for the production project active at the time (`employees`/
// `users.password_hash` were real and live there, Supabase Auth had zero
// accounts). Production has since moved to `drmgngqgnqggfmtkqthb`, where
// the opposite is true -- confirmed directly against the live database:
// no `employees` table exists at all, and a real Supabase Auth Super Admin
// account now exists and works end-to-end. So this legacy form is the one
// that can never succeed here, and /platform/login is the one that can.
// The legacy routes below are left in place, unlinked, rather than deleted
// -- same as this codebase's other confirmed-dead legacy paths (Telegram,
// AiController) -- in case a future production project ever reintroduces
// the legacy schema this depends on.
Route::get('/login', function () {
    return redirect()->route('platform.login');
})->name('login');

Route::post('/login', [AuthController::class, 'submitLogin'])
    ->name('login.submit');

// Lets the front-end recover from a stale CSRF token (see partials/sidebar.blade.php's
// fetch patch) without a full page reload -- deliberately outside kpi.auth so it still
// works even when the session itself, not just the token, has expired.
Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->name('csrf.refresh');

/*
|--------------------------------------------------------------------------
| MULTI-COMPANY PLATFORM (Supabase Auth + RLS — separate from the legacy
| employee/company session-based auth above)
|--------------------------------------------------------------------------
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
});

/*
|--------------------------------------------------------------------------
| FORGOT / RESET PASSWORD
|--------------------------------------------------------------------------
*/

Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])
    ->name('password.forgot');

Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])
    ->name('password.forgot.submit');

Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])
    ->name('password.reset');

Route::post('/reset-password', [AuthController::class, 'submitResetPassword'])
    ->name('password.reset.submit');

/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout');

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| COMPANY SELECTION (no employee session needed yet — set after choosing)
|--------------------------------------------------------------------------
*/

Route::get('/choose-dashboard', [AuthController::class, 'showChooseDashboard'])
    ->name('dashboard.choose');

Route::post('/choose-dashboard', [AuthController::class, 'selectDashboard'])
    ->name('dashboard.select');

Route::middleware(['kpi.auth'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::post('/switch-department', [DashboardController::class, 'switchDepartment'])
        ->name('switch.department');

    // SLT Office only — staff KPI drill-down
    Route::get('/dashboard/staff/{employeeId}', [DashboardController::class, 'staffKpis'])
        ->name('dashboard.staff.kpis');

    Route::get('/dashboard/staff/{employeeId}/kpi/{kpiId}', [DashboardController::class, 'staffKpiDetail'])
        ->name('dashboard.staff.kpi.detail');

    // SLT Office / BTS only — company-wide quarterly appraisal summary
    Route::get('/slt-dashboard', [\App\Http\Controllers\SltDashboardController::class, 'index'])
        ->name('slt-dashboard');

    /*
    |--------------------------------------------------------------------------
    | KPI MAIN
    |--------------------------------------------------------------------------
    */

    Route::get('/kpi', [KpiController::class, 'index'])
        ->name('kpi.index');

    Route::get('/kpi/create', [KpiController::class, 'create'])
        ->name('kpi.create');

    Route::post('/kpi', [KpiController::class, 'store'])
        ->name('kpi.store');

    /*
    |--------------------------------------------------------------------------
    | KPI VIEW / EDIT
    |--------------------------------------------------------------------------
    */

    Route::get('/kpi/{id}/edit', [KpiController::class, 'edit'])
        ->name('kpi.edit');

    Route::put('/kpi/{id}', [KpiController::class, 'update'])
        ->name('kpi.update');

    Route::put('/kpi/{id}/inline-update', [KpiController::class, 'inlineUpdate'])
        ->name('kpi.update.inline');

    Route::delete('/kpi/{id}', [KpiController::class, 'destroy'])
        ->name('kpi.destroy');

    Route::post('/kpi/assignment/{id}/accept', [KpiController::class, 'acceptAssignment'])
        ->name('kpi.assignment.accept');

    Route::post('/kpi/assignment/{id}/reject', [KpiController::class, 'rejectAssignment'])
        ->name('kpi.assignment.reject');

    /*
    |--------------------------------------------------------------------------
    | KPI QUARTER
    |--------------------------------------------------------------------------
    */

    Route::post('/kpi/{kpiId}/quarters', [KpiController::class, 'storeQuarter'])
        ->name('kpi.quarters.store');

    Route::get('/kpi-quarter/{id}/edit', [KpiController::class, 'editQuarter'])
        ->name('kpi.quarter.edit');

    Route::post('/kpi-quarter/{id}/update', [KpiController::class, 'updateQuarter'])
        ->name('kpi.quarter.update');

    Route::post('/kpi-quarter/save', [KpiController::class, 'saveQuarter'])
        ->name('kpi.quarter.save');

    /*
    |--------------------------------------------------------------------------
    | KPI QUARTER UPDATE GOVERNANCE
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/kpi/update-quarter',
        [KpiController::class, 'updateQuarterActual']
    )->name('kpi.quarter.actual.update');

    Route::post(
        '/kpi/request-quarter-approval',
        [KpiController::class, 'requestQuarterApproval']
    )->name('kpi.quarter.approval.request');

    /*
    |--------------------------------------------------------------------------
    | ACTUAL CHANGE REQUEST
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/kpi/{kpiId}/quarter/{quarterId}/actual-request',
        [KpiController::class, 'submitActualUpdateRequest']
    )->name('kpi.actual.request');

    Route::post(
        '/kpi/quarter/{quarterId}/status',
        [KpiController::class, 'saveQuarterStatus']
    )->name('kpi.quarter.status');

    Route::put(
        '/kpi/quarter/{id}/inline-update',
        [KpiController::class, 'inlineUpdateQuarter']
    )->name('kpi.quarter.inline-update');

    Route::post(
        '/kpi/quarter/{id}/complete',
        [KpiController::class, 'completeQuarter']
    )->name('kpi.quarter.complete');

    Route::delete(
        '/kpi/quarter/{id}/proof-file',
        [KpiController::class, 'deleteProofFile']
    )->name('kpi.quarter.proof.delete');

    /*
    |--------------------------------------------------------------------------
    | KPI GOVERNANCE REQUESTS
    |--------------------------------------------------------------------------
    */

    /*
    | REQUEST EDIT
    */

    Route::get('/kpi/{id}/request-edit', [KpiController::class, 'requestEdit'])
        ->name('kpi.request.edit');

    Route::post('/kpi/{id}/request-edit', [KpiController::class, 'submitEditRequest'])
        ->name('kpi.request.edit.submit');

    /*
    | REQUEST TARGET CHANGE
    */

    Route::post(

        '/kpi/{id}/request-target-change',

        [KpiController::class,
        'requestTargetChange']

    )->name(
        'kpi.requestTargetChange'
    );

    Route::post(
        '/kpi/{id}/request-weightage-change',
        [
            ApprovalController::class,
            'requestWeightageChange'
        ]
    )->name(
        'kpi.request-weightage-change'
    );

    /*
    | REQUEST DELETE
    */

    Route::get('/kpi/{id}/request-delete', [KpiController::class, 'requestDelete'])
        ->name('kpi.request.delete');

    Route::post('/kpi/{id}/request-delete', [KpiController::class, 'submitDeleteRequest'])
        ->name('kpi.request.delete.submit');

    /*
    |--------------------------------------------------------------------------
    | APPROVAL CENTER
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/approval',
        [ApprovalController::class, 'index']
    )->name('approval.index');

    Route::post(
        '/approval/{id}/approve',
        [ApprovalController::class, 'approve']
    )->name('approval.approve');

    Route::post(
        '/approval/{id}/reject',
        [ApprovalController::class, 'reject']
    )->name('approval.reject');

    Route::get(
        '/approval/rejected',
        [ApprovalController::class,'rejected']
    )->name('approval.rejected');

    /*
    |--------------------------------------------------------------------------
    | WEIGHTAGE
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/weightage',
        [KpiController::class, 'weightage']
    )->name('weightage');

    Route::post(
        '/weightage/bulk-update',
        [KpiController::class, 'bulkUpdateWeightage']
    )->name('weightage.bulk-update');

    /*
    |--------------------------------------------------------------------------
    | ACTIVITY LOG
    |--------------------------------------------------------------------------
    */

    Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-log');

    /*
    |--------------------------------------------------------------------------
    | PROFILE
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'index'])->name('profile');
    Route::get('/settings', [\App\Http\Controllers\ProfileController::class, 'settings'])->name('settings');
    Route::post('/settings/telegram/connect', [\App\Http\Controllers\ProfileController::class, 'connectTelegram'])->name('settings.telegram.connect');
    Route::get('/settings/telegram/status', [\App\Http\Controllers\ProfileController::class, 'telegramStatus'])->name('settings.telegram.status');
    Route::post('/settings/email', [\App\Http\Controllers\ProfileController::class, 'updateEmail'])->name('settings.email.update');
    Route::post('/settings/password', [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('settings.password.update');
    Route::post('/settings/theme', [\App\Http\Controllers\ProfileController::class, 'updateTheme'])->name('settings.theme.update');
    Route::post('/settings/salutation', [\App\Http\Controllers\ProfileController::class, 'updateSalutation'])->name('settings.salutation.update');

    /*
    |--------------------------------------------------------------------------
    | JOB DESCRIPTION
    |--------------------------------------------------------------------------
    */

    Route::get('/job-description', [\App\Http\Controllers\JobDescriptionController::class, 'index'])->name('job-description');
    Route::post('/job-description', [\App\Http\Controllers\JobDescriptionController::class, 'update'])->name('job-description.update');

    /*
    |--------------------------------------------------------------------------
    | NOTIFICATIONS
    |--------------------------------------------------------------------------
    */

    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    /*
    |--------------------------------------------------------------------------
    | HELP
    |--------------------------------------------------------------------------
    */

    Route::get('/help', [\App\Http\Controllers\HelpController::class, 'index'])->name('help');

    /*
    |--------------------------------------------------------------------------
    | PERFORMANCE EVALUATION
    |--------------------------------------------------------------------------
    */
    Route::get('/attendance',             [\App\Http\Controllers\AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/import',      fn() => redirect()->route('attendance.index')); // GET fallback — prevents 405 on refresh/back
    Route::post('/attendance/import',     [\App\Http\Controllers\AttendanceController::class, 'import'])->name('attendance.import');
    Route::post('/attendance/save',       [\App\Http\Controllers\AttendanceController::class, 'save'])->name('attendance.save');

    Route::get('/performance/kpi',                          [\App\Http\Controllers\PerformanceController::class, 'kpiAppraisal'])->name('performance.kpi');
    Route::get('/performance/attitude',                     [\App\Http\Controllers\PerformanceController::class, 'attitude'])->name('performance.attitude');
    Route::get('/performance/report',                       fn() => redirect('/performance/report/q2'))->name('performance.report');
    Route::get('/performance/report/{quarter}',             [\App\Http\Controllers\PerformanceController::class, 'reportQuarter'])->middleware('no-cache')->name('performance.report.quarter');
    Route::post('/performance/report/{quarter}/save',       [\App\Http\Controllers\PerformanceController::class, 'saveReport'])->name('performance.report.save');
    Route::get('/performance/appraise/{employeeId}/{quarter}', [\App\Http\Controllers\PerformanceController::class, 'appraiserReport'])->middleware('no-cache')->name('performance.appraise.report');
    Route::post('/performance/appraise/{employeeId}/{quarter}/save', [\App\Http\Controllers\PerformanceController::class, 'appraiserSave'])->name('performance.appraise.save');
    Route::get('/performance/appraise/{employeeId}/kpi/{kpiId}', [\App\Http\Controllers\PerformanceController::class, 'viewAppraiseeKpi'])->middleware('no-cache')->name('performance.appraise.kpi');

    /*
    |--------------------------------------------------------------------------
    | MINI APP — web version of the Telegram Mini App, same session auth as
    | the rest of the dashboard (see MiniAppController / MiniAppTaskController)
    |--------------------------------------------------------------------------
    */
    Route::get('/mini-app', [\App\Http\Controllers\MiniAppController::class, 'index'])->name('mini-app');

    // Deliberately NOT gated behind 'telegram.linked' -- task/KPI management
    // in Performix shouldn't wait on a Telegram round trip just to be usable.
    // Telegram is only needed for reminders/notifications, which is now an
    // optional, dismissible prompt in mini-app/index.blade.php rather than a
    // hard block on the whole app. See EnsureTelegramLinked's docblock.
    Route::get('/mini-app/api/kpis/open', [\App\Http\Controllers\MiniAppController::class, 'openKpis'])->name('mini-app.kpis.open');
    Route::get('/mini-app/api/kpis/summary', [\App\Http\Controllers\MiniAppController::class, 'summary'])->name('mini-app.kpis.summary');
    Route::post('/mini-app/api/kpis/{kpiId}/quarters/{quarterId}/adjust', [\App\Http\Controllers\MiniAppController::class, 'adjustQuarter'])->name('mini-app.kpis.adjust');
    Route::get('/mini-app/api/reviews', [\App\Http\Controllers\MiniAppController::class, 'reviews'])->name('mini-app.reviews');

    Route::get('/mini-app/api/tasks', [\App\Http\Controllers\MiniAppTaskController::class, 'index'])->name('mini-app.tasks.index');
    Route::get('/mini-app/api/tasks/kpi-options', [\App\Http\Controllers\MiniAppTaskController::class, 'kpiOptions'])->name('mini-app.tasks.kpi-options');
    Route::get('/mini-app/api/tasks/assignable', [\App\Http\Controllers\MiniAppTaskController::class, 'assignableEmployees'])->name('mini-app.tasks.assignable');
    Route::get('/mini-app/api/tasks/score', [\App\Http\Controllers\PerformixInsightsController::class, 'myScore'])->name('mini-app.tasks.score');
    Route::post('/mini-app/api/tasks', [\App\Http\Controllers\MiniAppTaskController::class, 'store'])->name('mini-app.tasks.store');
    Route::get('/mini-app/api/tasks/{id}', [\App\Http\Controllers\MiniAppTaskController::class, 'show'])->name('mini-app.tasks.show');
    Route::patch('/mini-app/api/tasks/{id}', [\App\Http\Controllers\MiniAppTaskController::class, 'update'])->name('mini-app.tasks.update');
    Route::post('/mini-app/api/tasks/{id}/progress', [\App\Http\Controllers\MiniAppTaskController::class, 'progress'])->name('mini-app.tasks.progress');
    Route::post('/mini-app/api/tasks/{id}/daily-update', [\App\Http\Controllers\MiniAppTaskController::class, 'dailyUpdate'])->name('mini-app.tasks.daily-update');
    Route::post('/mini-app/api/tasks/{id}/kpi-suggestion', [\App\Http\Controllers\MiniAppTaskController::class, 'kpiSuggestion'])->name('mini-app.tasks.kpi-suggestion');
    Route::post('/mini-app/api/tasks/{id}/link-kpis', [\App\Http\Controllers\MiniAppTaskController::class, 'linkKpis'])->name('mini-app.tasks.link-kpis');
    Route::delete('/mini-app/api/tasks/{id}', [\App\Http\Controllers\MiniAppTaskController::class, 'destroy'])->name('mini-app.tasks.destroy');

    Route::get('/mini-app/api/team/attention', [\App\Http\Controllers\PerformixInsightsController::class, 'teamAttention'])->name('mini-app.team.attention');
    Route::get('/mini-app/api/summaries', [\App\Http\Controllers\PerformixInsightsController::class, 'summaries'])->name('mini-app.summaries.index');
    Route::post('/mini-app/api/summaries/regenerate', [\App\Http\Controllers\PerformixInsightsController::class, 'regenerate'])->name('mini-app.summaries.regenerate');

    /*
    |--------------------------------------------------------------------------
    | MY DEPARTMENT KPI
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/my-department-kpi',
        [KpiController::class, 'myDepartmentKpi']
    )->name('kpi.my-department-kpi');

    Route::post(
        '/kpi/apply-template',
        [KpiController::class, 'applyTemplate']
    )->name('kpi.apply-template');

    /*
    |--------------------------------------------------------------------------
    | KPI TEMPLATE CRUD
    |--------------------------------------------------------------------------
    */

    Route::get('/kpi-templates',          [KpiTemplateController::class, 'index'])->name('kpi-templates.index');
    Route::post('/kpi-templates',         [KpiTemplateController::class, 'store'])->name('kpi-templates.store');
    Route::delete('/kpi-templates/{id}',  [KpiTemplateController::class, 'destroy'])->name('kpi-templates.destroy');

    /*
    |--------------------------------------------------------------------------
    | TITAN KPI DASHBOARD (RCG / TITAN dept only, no VP)
    |--------------------------------------------------------------------------
    */

    Route::get('/titan-kpi',              [TitanKpiController::class, 'index'])->name('titan-kpi.index');
    Route::post('/titan-kpi/sync',        [TitanKpiController::class, 'sync'])->name('titan-kpi.sync');
    Route::post('/titan-kpi/weightage',   [TitanKpiController::class, 'updateWeightage'])->name('titan-kpi.weightage');

    /*
    |--------------------------------------------------------------------------
    | KPI LINKAGES (cascading targets)
    |--------------------------------------------------------------------------
    */

    Route::get('/linkages', [\App\Http\Controllers\LinkageController::class, 'index'])->name('linkages');
    Route::post('/linkages', [\App\Http\Controllers\LinkageController::class, 'store'])->name('linkage.store');
    Route::delete('/linkages/{id}', [\App\Http\Controllers\LinkageController::class, 'destroy'])->name('linkage.destroy');

    /*
    |--------------------------------------------------------------------------
    | AI
    |--------------------------------------------------------------------------
    */

    Route::post('/ai/chat', [AiController::class, 'chat'])
        ->name('ai.chat');

    Route::post('/ai/suggest-description', [AiController::class, 'suggestDescription'])
        ->name('ai.suggest-description');

    Route::post('/ai/score-description', [AiController::class, 'scoreDescription'])
        ->name('ai.score-description');

    Route::post('/ai/suggest-targets', [AiController::class, 'suggestTargets'])
        ->name('ai.suggest-targets');

    Route::post('/ai/score-quarter', [AiController::class, 'scoreQuarter'])
        ->name('ai.score-quarter');

    Route::post('/ai/suggest-kpi', [AiController::class, 'suggestKpi'])
        ->name('ai.suggest-kpi');

    /*
    |--------------------------------------------------------------------------
    | ADMIN — VIEW AS (BTS department only)
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/view-as', [\App\Http\Controllers\AdminController::class, 'index'])->name('admin.view-as');
    Route::post('/admin/view-as/stop', [\App\Http\Controllers\AdminController::class, 'stop'])->name('admin.view-as.stop');
    Route::post('/admin/view-as/{employeeId}', [\App\Http\Controllers\AdminController::class, 'start'])->name('admin.view-as.start');

    /*
    |--------------------------------------------------------------------------
    | ADMIN — QUARTER CONTROL (BTS department only)
    |--------------------------------------------------------------------------
    | Force a quarter open (KPI actuals + appraisal) until a chosen deadline,
    | regardless of its normal dates -- see QuarterOverrideController.
    */

    Route::get('/admin/quarter-control', [\App\Http\Controllers\QuarterOverrideController::class, 'index'])->name('admin.quarter-control');
    Route::post('/admin/quarter-control', [\App\Http\Controllers\QuarterOverrideController::class, 'store'])->name('admin.quarter-control.store');
    Route::delete('/admin/quarter-control/{quarter}', [\App\Http\Controllers\QuarterOverrideController::class, 'destroy'])->name('admin.quarter-control.destroy');

    /*
    |--------------------------------------------------------------------------
    | ADMIN — APPRAISER DELEGATION (BTS department only)
    |--------------------------------------------------------------------------
    | Stand a Manager's own VP in as appraiser for that Manager's Executives
    | while the Manager is on long leave -- see AppraiserDelegationController
    | and AppraiserDelegationService. Rendered as a section on the same
    | Quarter Control page.
    */

    Route::post('/admin/appraiser-delegation', [\App\Http\Controllers\AppraiserDelegationController::class, 'store'])->name('admin.appraiser-delegation.store');
    Route::delete('/admin/appraiser-delegation/{managerId}', [\App\Http\Controllers\AppraiserDelegationController::class, 'destroy'])->name('admin.appraiser-delegation.destroy');

});
