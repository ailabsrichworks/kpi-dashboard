<?php

namespace App\Http\Controllers;

use App\Services\KpiQuarterUpdateService;
use App\Services\SupabaseService;
use App\Services\TaskAccessPolicy;
use Illuminate\Http\Request;

/**
 * Web version of the Telegram Mini App's KPI screens (see
 * Telegram\TelegramMiniAppController) — same underlying data and services,
 * but reached from a normal browser tab under the existing kpi.auth session
 * instead of Telegram's initData signature. Kept as separate controllers
 * (rather than refactoring the Telegram ones to share a trait) so the live
 * Telegram bot flow carries zero risk from this addition.
 */
class MiniAppController extends Controller
{
    private function nowMy(): string
    {
        return now()->timezone('Asia/Kuala_Lumpur')->toDateTimeString();
    }

    private function todayMy(): string
    {
        return now('Asia/Kuala_Lumpur')->toDateString();
    }

    public function index(SupabaseService $supabase, TaskAccessPolicy $policy)
    {
        $user = $supabase->first('users', [
            'id' => 'eq.' . session('user_uuid'),
            'select' => 'telegram_username,telegram_linked_at',
        ]);

        return view('mini-app.index', [
            'telegramLinked' => !empty($user['telegram_linked_at']),
            'hasTeam' => $policy->hasTeam(session('employee') ?? []),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /mini-app/api/kpis/open
    |--------------------------------------------------------------------------
    | Today's open-quarter KPIs, each flagged with whether a daily task has
    | already been logged today — the signal the "reminder" banner is built
    | from on the frontend.
    */
    public function openKpis(Request $request, SupabaseService $supabase)
    {
        $employeeId = session('employee.id');
        $companyCode = session('employee.company_code');

        $today = $this->todayMy();
        $fy = 'FY' . now('Asia/Kuala_Lumpur')->year;

        $kpis = $supabase->get('kpis', [
            'employee_id' => 'eq.' . $employeeId,
            'company_code' => 'eq.' . $companyCode,
            'financial_year' => 'eq.' . $fy,
            'select' => '*',
        ]) ?? [];

        if (empty($kpis)) {
            return response()->json(['date' => $today, 'kpis' => []]);
        }

        $kpiIds = array_column($kpis, 'id');

        // quarters and existingTasks don't depend on each other — sent
        // concurrently instead of one after another.
        $batch = $supabase->getMany([
            'quarters' => ['table' => 'kpi_quarters', 'query' => [
                'kpi_id' => 'in.(' . implode(',', $kpiIds) . ')',
                'select' => '*',
            ]],
            'existingTasks' => ['table' => 'telegram_daily_tasks', 'query' => [
                'employee_id' => 'eq.' . $employeeId,
                'task_date' => 'eq.' . $today,
                'select' => '*',
            ]],
        ]);

        $quarters = $batch['quarters'] ?? [];
        $quartersByKpi = collect($quarters)->groupBy('kpi_id');

        $existingTasks = $batch['existingTasks'] ?? [];

        $taskByQuarter = collect($existingTasks)->keyBy('kpi_quarter_id');

        $result = [];

        foreach ($kpis as $kpi) {
            $openQuarter = collect($quartersByKpi->get($kpi['id'], []))
                ->first(fn($q) => !empty($q['start_date']) && !empty($q['end_date'])
                    && $q['start_date'] <= $today && $q['end_date'] >= $today);

            if (!$openQuarter) {
                continue;
            }

            $task = $taskByQuarter->get($openQuarter['id']);

            $result[] = [
                'kpi_id' => $kpi['id'],
                'kpi_quarter_id' => $openQuarter['id'],
                'kpi_title' => $kpi['kpi_title'],
                'category' => $kpi['category'] ?? null,
                'sub_category' => $kpi['sub_category'] ?? null,
                'unit' => $kpi['unit'],
                'quarter' => $openQuarter['quarter'],
                'quarter_target' => (float) ($openQuarter['quarter_target'] ?? 0),
                'quarter_actual' => (float) ($openQuarter['quarter_actual'] ?? 0),
                'already_logged_today' => (bool) $task && ($task['status'] ?? null) === 'done',
                'existing_task_id' => $task['id'] ?? null,
            ];
        }

        return response()->json(['date' => $today, 'kpis' => $result]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /mini-app/api/kpis/{kpiId}/quarters/{quarterId}/adjust
    |--------------------------------------------------------------------------
    | Fast inline "+/-" control on the My KPIs screen — same as the Telegram
    | Mini App's adjustQuarter, unchanged math (KpiQuarterUpdateService).
    */
    public function adjustQuarter(Request $request, SupabaseService $supabase, KpiQuarterUpdateService $quarterService, string $kpiId, string $quarterId)
    {
        $validated = $request->validate([
            'delta' => 'required|numeric',
        ]);

        if ((float) $validated['delta'] === 0.0) {
            return response()->json(['success' => false, 'message' => 'Enter a non-zero amount.'], 422);
        }

        $employeeId = session('employee.id');
        $companyCode = session('employee.company_code');

        $kpi = $supabase->first('kpis', [
            'id' => 'eq.' . $kpiId,
            'employee_id' => 'eq.' . $employeeId,
            'company_code' => 'eq.' . $companyCode,
            'select' => '*',
        ]);

        if (empty($kpi)) {
            return response()->json(['success' => false, 'message' => 'KPI not found.'], 404);
        }

        $quarter = $supabase->first('kpi_quarters', [
            'id' => 'eq.' . $quarterId,
            'kpi_id' => 'eq.' . $kpiId,
            'select' => '*',
        ]);

        if (empty($quarter)) {
            return response()->json(['success' => false, 'message' => 'Quarter not found.'], 404);
        }

        $today = $this->todayMy();

        if ($quarter['start_date'] > $today || $quarter['end_date'] < $today) {
            return response()->json([
                'success' => false,
                'message' => 'This quarter is not currently open. Use the approval request flow for retroactive updates.',
            ], 422);
        }

        $liveQuarterActual = (float) ($quarter['quarter_actual'] ?? 0);
        $newQuarterActual = $liveQuarterActual + (float) $validated['delta'];

        if ($newQuarterActual < 0) {
            return response()->json([
                'success' => false,
                'message' => "Can't reduce — this quarter's actual is only " . $liveQuarterActual . '.',
            ], 422);
        }

        $result = $quarterService->applyQuarterActualChange($kpi, $quarter, $newQuarterActual);

        // Mark today's daily-task row (if any) as logged, so the reminder
        // banner clears immediately without a page reload.
        $existing = $supabase->first('telegram_daily_tasks', [
            'employee_id' => 'eq.' . $employeeId,
            'kpi_quarter_id' => 'eq.' . $quarterId,
            'task_date' => 'eq.' . $today,
            'select' => 'id',
        ]);

        if ($existing) {
            $supabase->safePatch('telegram_daily_tasks', ['id' => 'eq.' . $existing['id']], [
                'status' => 'done',
                'updated_at' => $this->nowMy(),
            ]);
        } else {
            $supabase->safeInsert('telegram_daily_tasks', [
                'employee_id' => $employeeId,
                'kpi_id' => $kpiId,
                'kpi_quarter_id' => $quarterId,
                'task_date' => $today,
                'unit' => $kpi['unit'],
                'planned_target' => 0,
                'baseline_actual' => $liveQuarterActual,
                'progress_value' => (float) $validated['delta'],
                'status' => 'done',
            ]);
        }

        return response()->json(['success' => true] + $result);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /mini-app/api/kpis/summary
    |--------------------------------------------------------------------------
    */
    public function summary(Request $request, SupabaseService $supabase)
    {
        $employeeId = session('employee.id');
        $companyCode = session('employee.company_code');

        $fy = 'FY' . now('Asia/Kuala_Lumpur')->year;

        $kpis = $supabase->get('kpis', [
            'employee_id' => 'eq.' . $employeeId,
            'company_code' => 'eq.' . $companyCode,
            'financial_year' => 'eq.' . $fy,
            'select' => '*',
            'order' => 'created_at.asc',
        ]) ?? [];

        if (empty($kpis)) {
            return response()->json(['financial_year' => $fy, 'kpis' => []]);
        }

        $today = $this->todayMy();
        $kpiIds = array_column($kpis, 'id');

        $quarters = $supabase->get('kpi_quarters', [
            'kpi_id' => 'in.(' . implode(',', $kpiIds) . ')',
            'select' => '*',
            'order' => 'start_date.asc',
        ]) ?? [];

        $quartersByKpi = collect($quarters)->groupBy('kpi_id');

        $result = array_map(function ($kpi) use ($quartersByKpi, $today) {
            $kpiQuarters = $quartersByKpi->get($kpi['id'], collect())->map(function ($q) use ($today) {
                $target = (float) ($q['quarter_target'] ?? 0);
                $actual = (float) ($q['quarter_actual'] ?? 0);

                $state = 'upcoming';
                if (!empty($q['start_date']) && !empty($q['end_date'])) {
                    if ($q['end_date'] < $today) {
                        $state = 'ended';
                    } elseif ($q['start_date'] <= $today && $q['end_date'] >= $today) {
                        $state = 'current';
                    }
                }

                return [
                    'id' => $q['id'],
                    'quarter' => $q['quarter'],
                    'target' => $target,
                    'actual' => $actual,
                    'achievement_percentage' => $target > 0 ? round(($actual / $target) * 100, 2) : 0,
                    'state' => $state,
                ];
            })->values();

            return [
                'kpi_id' => $kpi['id'],
                'kpi_title' => $kpi['kpi_title'],
                'category' => $kpi['category'] ?? null,
                'sub_category' => $kpi['sub_category'] ?? null,
                'unit' => $kpi['unit'],
                'base_target' => (float) ($kpi['base_target'] ?? 0),
                'stretch_target' => (float) ($kpi['stretch_target'] ?? 0),
                'actual_value' => (float) ($kpi['actual_value'] ?? 0),
                'achievement_percentage' => (float) ($kpi['achievement_percentage'] ?? 0),
                'status' => $kpi['status'] ?? 'not_started',
                'quarters' => $kpiQuarters,
            ];
        }, $kpis);

        return response()->json(['financial_year' => $fy, 'kpis' => $result]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /mini-app/api/reviews
    |--------------------------------------------------------------------------
    | Reads AI-generated reviews already produced by TelegramReviewService's
    | cron — no live generation happens here, same as the Telegram version.
    */
    public function reviews(Request $request, SupabaseService $supabase)
    {
        $validated = $request->validate([
            'period' => 'required|in:weekly,monthly,quarterly',
        ]);

        $employeeId = session('employee.id');
        $companyCode = session('employee.company_code');

        $reviews = $supabase->get('telegram_ai_reviews', [
            'employee_id' => 'eq.' . $employeeId,
            'company_code' => 'eq.' . $companyCode,
            'period_type' => 'eq.' . $validated['period'],
            'select' => 'id,period_label,period_start,period_end,score,narrative,generated_at',
            'order' => 'period_start.desc',
            'limit' => '8',
        ]) ?? [];

        return response()->json([
            'period' => $validated['period'],
            'latest' => $reviews[0] ?? null,
            'history' => array_slice($reviews, 1),
        ]);
    }
}
