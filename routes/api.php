<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Telegram\TelegramWebhookController;
use App\Http\Controllers\Telegram\TelegramMiniAppController;
use App\Http\Controllers\Telegram\TelegramLinkController;
use App\Http\Controllers\Telegram\TelegramCronController;
use App\Http\Controllers\Telegram\TelegramProjectTaskController;
use App\Http\Controllers\Telegram\TelegramPerformixController;

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->middleware('telegram.webhook.secret');

Route::middleware('telegram.cron.secret')->prefix('telegram/cron')->group(function () {
    Route::post('/morning', [TelegramCronController::class, 'morning']);
    Route::post('/evening', [TelegramCronController::class, 'evening']);
    Route::post('/review/{period}', [TelegramCronController::class, 'review']);
    Route::post('/tasks-morning', [TelegramCronController::class, 'tasksMorning']);
    Route::post('/tasks-evening', [TelegramCronController::class, 'tasksEvening']);
    Route::post('/tasks-weekly', [TelegramCronController::class, 'tasksWeekly']);
    Route::post('/tasks-monthly', [TelegramCronController::class, 'tasksMonthly']);
});

// The tenant-aware Platform digest — a distinct route tree from the legacy
// one above, sharing only the secret-check middleware (which has no tenant
// dimension of its own). See PlatformTelegramDigestService.
Route::middleware('telegram.cron.secret')->prefix('platform/telegram/cron')->group(function () {
    Route::post('/morning', [\App\Http\Controllers\Platform\TelegramCronController::class, 'morning']);
    Route::post('/evening', [\App\Http\Controllers\Platform\TelegramCronController::class, 'evening']);
});

Route::middleware('telegram.webapp.auth')->prefix('telegram')->group(function () {
    Route::get('/theme', [TelegramMiniAppController::class, 'theme']);
    Route::get('/kpis/open', [TelegramMiniAppController::class, 'openKpis']);
    Route::get('/kpis/summary', [TelegramMiniAppController::class, 'summary']);
    Route::post('/tasks', [TelegramMiniAppController::class, 'storeTasks']);
    Route::get('/tasks/today', [TelegramMiniAppController::class, 'todayTasks']);
    Route::post('/tasks/{id}/progress', [TelegramMiniAppController::class, 'submitProgress']);
    Route::post('/kpis/{kpiId}/quarters/{quarterId}/adjust', [TelegramMiniAppController::class, 'adjustQuarter']);
    Route::get('/link/status', [TelegramLinkController::class, 'status']);
    Route::post('/link/disconnect', [TelegramLinkController::class, 'disconnect']);

    Route::get('/projects', [TelegramProjectTaskController::class, 'listProjects']);
    Route::post('/projects', [TelegramProjectTaskController::class, 'createProject']);
    Route::get('/project-tasks', [TelegramProjectTaskController::class, 'listTasks']);
    Route::post('/project-tasks', [TelegramProjectTaskController::class, 'createTask']);
    Route::get('/project-tasks/kpi-options', [TelegramProjectTaskController::class, 'kpiOptions']);
    Route::get('/project-tasks/assignable-employees', [TelegramProjectTaskController::class, 'assignableEmployees']);
    Route::post('/project-tasks/kpi-suggestion-draft', [TelegramProjectTaskController::class, 'suggestKpiForDraft']);
    Route::get('/project-tasks/{id}', [TelegramProjectTaskController::class, 'show']);
    Route::patch('/project-tasks/{id}', [TelegramProjectTaskController::class, 'update']);
    Route::delete('/project-tasks/{id}', [TelegramProjectTaskController::class, 'destroy']);
    Route::post('/project-tasks/{id}/link-kpis', [TelegramProjectTaskController::class, 'linkKpis']);
    Route::post('/project-tasks/{id}/kpi-suggestion', [TelegramProjectTaskController::class, 'kpiSuggestion']);
    Route::post('/project-tasks/{id}/progress', [TelegramProjectTaskController::class, 'updateProgress']);
    Route::post('/project-tasks/{id}/daily-update', [TelegramProjectTaskController::class, 'dailyUpdate']);
    Route::get('/kpis/{kpiId}/task-history', [TelegramProjectTaskController::class, 'kpiTaskHistory']);

    Route::get('/reviews', [TelegramMiniAppController::class, 'reviews']);

    Route::get('/tasks/score', [TelegramPerformixController::class, 'myScore']);
    Route::get('/summaries', [TelegramPerformixController::class, 'summaries']);
    Route::post('/summaries/regenerate', [TelegramPerformixController::class, 'regenerate']);
    Route::get('/team/attention', [TelegramPerformixController::class, 'teamAttention']);
});
