<?php

namespace App\Http\Controllers;

use App\Services\AiService;
use App\Services\SupabaseService;
use App\Services\TaskAccessPolicy;
use App\Services\TaskScoreCalculator;
use App\Services\TaskScoreService;
use Illuminate\Http\Request;

/**
 * Task Score + AI summary + "My Team" endpoints for the web Mini App —
 * everything in docs/performix-design.md §4/§5 that isn't plain task CRUD
 * (that's MiniAppTaskController). Every method here goes through
 * TaskAccessPolicy before touching another employee's data (§2/§6-R1).
 *
 * Team/department/company aggregates read from the already-computed
 * task_score_snapshots table rather than recomputing live per employee —
 * docs/performix-design.md §6-R5: dashboard aggregates must be precomputed,
 * never recalculated live for a whole team on every page load. The one
 * exception is a single employee checking their own current score, which
 * recomputes fresh (cheap — it's one person's data, not a fan-out).
 */
class PerformixInsightsController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET /mini-app/api/tasks/score
    |--------------------------------------------------------------------------
    */
    public function myScore(Request $request, TaskScoreService $scoreService)
    {
        $validated = $request->validate([
            'period' => 'nullable|in:daily,weekly,monthly',
        ]);

        $periodType = $validated['period'] ?? 'weekly';
        $employeeId = session('employee.id');
        $companyCode = session('employee.company_code');

        [$start, $end] = $scoreService->currentPeriodBounds($periodType);

        $result = $scoreService->scoreForPeriod($employeeId, $companyCode, $periodType, $start, $end);

        return response()->json([
            'period_type' => $periodType,
            'period_start' => $start,
            'period_end' => $end,
            'score' => $result['score'],
            'status' => $result['status'],
            'breakdown' => $result['breakdown'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /mini-app/api/team/attention
    |--------------------------------------------------------------------------
    | Manager/VP/SLT only. Reads the current week's already-computed
    | task_score_snapshots for everyone TaskAccessPolicy says this actor can
    | see — sorted worst-first so who needs attention is obvious at a glance.
    */
    public function teamAttention(Request $request, SupabaseService $supabase, TaskAccessPolicy $policy, TaskScoreService $scoreService)
    {
        $actor = session('employee') ?? [];

        if (!$policy->hasTeam($actor)) {
            return response()->json(['success' => false, 'message' => 'No team to show.'], 403);
        }

        $employeeIds = array_values(array_diff($policy->visibleEmployeeIds($actor), [$actor['id']]));

        if (empty($employeeIds)) {
            return response()->json(['members' => []]);
        }

        [$periodStart, $periodEnd] = $scoreService->currentPeriodBounds('weekly');

        $employees = $supabase->get('employees', [
            'id' => 'in.(' . implode(',', $employeeIds) . ')',
            'select' => 'id,short_name,department_code',
        ]) ?? [];

        $snapshots = $supabase->get('task_score_snapshots', [
            'employee_id' => 'in.(' . implode(',', $employeeIds) . ')',
            'period_type' => 'eq.weekly',
            'period_start' => 'eq.' . $periodStart,
            'select' => 'employee_id,score,breakdown',
        ]) ?? [];
        $snapshotByEmployee = collect($snapshots)->keyBy('employee_id');

        $calculator = new TaskScoreCalculator();

        $members = array_map(function ($employee) use ($snapshotByEmployee, $calculator) {
            $snapshot = $snapshotByEmployee->get($employee['id']);
            $score = $snapshot['score'] ?? null;

            return [
                'employee_id' => $employee['id'],
                'name' => $employee['short_name'],
                'department_code' => $employee['department_code'] ?? null,
                'score' => $score !== null ? (float) $score : null,
                'status' => $calculator->statusForScore($score !== null ? (float) $score : null),
            ];
        }, $employees);

        $statusRank = ['critical' => 0, 'at_risk' => 1, 'insufficient_data' => 2, 'on_track' => 3];
        usort($members, fn ($a, $b) => ($statusRank[$a['status']] ?? 9) <=> ($statusRank[$b['status']] ?? 9));

        return response()->json([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'members' => $members,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /mini-app/api/summaries
    |--------------------------------------------------------------------------
    | Reads the latest persisted ai_summaries row for this actor/scope/period
    | — never calls the AI live on a page load. Returns summary: null if
    | nothing has been generated yet for this period (the UI offers a
    | "Generate now" action, which calls regenerate() below).
    */
    public function summaries(Request $request, SupabaseService $supabase, TaskAccessPolicy $policy, TaskScoreService $scoreService)
    {
        $validated = $request->validate([
            'scope' => 'required|in:employee,team,department,company',
            'period' => 'required|in:daily,weekly,monthly',
        ]);

        $actor = session('employee') ?? [];

        if ($validated['scope'] !== 'employee' && !$policy->hasTeam($actor)) {
            return response()->json(['success' => false, 'message' => 'Not authorized for this scope.'], 403);
        }

        [$start, $end] = $scoreService->currentPeriodBounds($validated['period']);

        $summary = $supabase->first('ai_summaries', [
            'employee_id' => 'eq.' . $actor['id'],
            'scope' => 'eq.' . $validated['scope'],
            'period_type' => 'eq.' . $validated['period'],
            'period_start' => 'eq.' . $start,
            'select' => '*',
            'order' => 'generated_at.desc',
        ]);

        return response()->json([
            'period_start' => $start,
            'period_end' => $end,
            'summary' => $summary ? [
                'narrative' => $summary['summary_text'],
                'facts' => $summary['facts'],
                'model_version' => $summary['model_version'],
                'generated_at' => $summary['generated_at'],
            ] : null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /mini-app/api/summaries/regenerate
    |--------------------------------------------------------------------------
    | On-demand generation, audit-chained via regenerated_from_id — a
    | deliberate user action (button press), not something that runs on
    | every page load. Facts come from task_score_snapshots (employee scope
    | recomputes fresh for just the one caller; team/department/company read
    | the already-computed snapshots for everyone in scope).
    */
    public function regenerate(Request $request, SupabaseService $supabase, AiService $ai, TaskAccessPolicy $policy, TaskScoreService $scoreService)
    {
        $validated = $request->validate([
            'scope' => 'required|in:employee,team,department,company',
            'period' => 'required|in:daily,weekly,monthly',
        ]);

        $actor = session('employee') ?? [];
        $scope = $validated['scope'];
        $periodType = $validated['period'];

        if ($scope !== 'employee' && !$policy->hasTeam($actor)) {
            return response()->json(['success' => false, 'message' => 'Not authorized for this scope.'], 403);
        }

        [$start, $end] = $scoreService->currentPeriodBounds($periodType);
        $periodLabel = $start === $end ? $start : "{$start} to {$end}";

        if ($scope === 'employee') {
            $result = $scoreService->scoreForPeriod($actor['id'], $actor['company_code'], $periodType, $start, $end);
            $facts = [
                'score' => $result['score'],
                'status' => $result['status'],
                'scored_task_count' => $result['breakdown']['scored_task_count'] ?? 0,
                'completed_count' => $result['breakdown']['completed_count'] ?? 0,
                'overdue_count' => $result['breakdown']['overdue_count'] ?? 0,
                'blocked_count' => $result['breakdown']['blocked_count'] ?? 0,
                'on_time_pct' => $result['breakdown']['components']['on_time']['score'] ?? null,
                'update_consistency_pct' => $result['breakdown']['components']['update_consistency']['score'] ?? null,
            ];
            $subjectName = $actor['short_name'] ?? 'You';
        } else {
            $employeeIds = $policy->visibleEmployeeIds($actor);

            $employees = $supabase->get('employees', [
                'id' => 'in.(' . implode(',', $employeeIds) . ')',
                'select' => 'id,short_name',
            ]) ?? [];

            $snapshots = $supabase->get('task_score_snapshots', [
                'employee_id' => 'in.(' . implode(',', $employeeIds) . ')',
                'period_type' => 'eq.' . $periodType,
                'period_start' => 'eq.' . $start,
                'select' => 'employee_id,score',
            ]) ?? [];
            $snapshotByEmployee = collect($snapshots)->keyBy('employee_id');

            $calculator = new TaskScoreCalculator();
            $members = array_map(function ($e) use ($snapshotByEmployee, $calculator) {
                $score = $snapshotByEmployee->get($e['id'])['score'] ?? null;
                return [
                    'name' => $e['short_name'],
                    'score' => $score !== null ? (float) $score : null,
                    'status' => $calculator->statusForScore($score !== null ? (float) $score : null),
                ];
            }, $employees);

            $scored = array_filter($members, fn ($m) => $m['score'] !== null);
            $avgScore = count($scored) ? round(array_sum(array_column($scored, 'score')) / count($scored), 2) : null;

            $facts = [
                'score' => $avgScore,
                'status' => $calculator->statusForScore($avgScore),
                'scored_task_count' => count($scored),
                'completed_count' => null,
                'overdue_count' => count(array_filter($members, fn ($m) => $m['status'] === 'critical')),
                'blocked_count' => count(array_filter($members, fn ($m) => $m['status'] === 'at_risk')),
                'on_time_pct' => null,
                'update_consistency_pct' => null,
                'members' => $members,
            ];
            $subjectName = $actor['short_name'] ?? 'the team';
        }

        $narrativeResult = $ai->generateTaskSummary($subjectName, $scope, $periodType, $periodLabel, $facts);

        $previous = $supabase->first('ai_summaries', [
            'employee_id' => 'eq.' . $actor['id'],
            'scope' => 'eq.' . $scope,
            'period_type' => 'eq.' . $periodType,
            'period_start' => 'eq.' . $start,
            'select' => 'id',
            'order' => 'generated_at.desc',
        ]);

        $inserted = $supabase->insert('ai_summaries', [
            'employee_id' => $actor['id'],
            'scope' => $scope,
            'period_type' => $periodType,
            'period_start' => $start,
            'period_end' => $end,
            'summary_text' => $narrativeResult['narrative'],
            'facts' => $facts + ['recommendations' => $narrativeResult['recommendations']],
            'model_version' => $narrativeResult['model_version'],
            'regenerated_from_id' => $previous['id'] ?? null,
        ]);

        $summary = $inserted[0] ?? null;

        return response()->json([
            'summary' => $summary ? [
                'narrative' => $summary['summary_text'],
                'facts' => $summary['facts'],
                'model_version' => $summary['model_version'],
                'generated_at' => $summary['generated_at'],
            ] : null,
        ]);
    }
}
