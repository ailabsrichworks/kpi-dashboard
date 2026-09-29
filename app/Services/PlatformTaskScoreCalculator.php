<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ports the legacy Telegram/Mini-App "Things To Do" feature's task-scoring
 * formula (`App\Services\TaskScoreCalculator`, the LEGACY class — a separate,
 * untouched file this deliberately does not share a name with) onto the
 * Platform's own `tasks` table. Two deliberate differences from legacy, both
 * scope decisions made up front rather than discovered mid-build:
 *
 * 1. The Platform's `tasks.priority` only has 3 tiers (low/medium/high), not
 *    legacy's 4 (+ critical) — the weight tables below simply don't have a
 *    4th tier, nothing is silently dropped.
 * 2. Legacy's 4th scoring component, `update_consistency`, needs a per-day
 *    task-activity log (`telegram_project_task_updates`) the Platform has no
 *    equivalent of and which wasn't part of what was asked for building this
 *    — it's omitted entirely, its weight redistributed among the remaining
 *    three components via the exact same "renormalize among applicable
 *    components" rule legacy's own formula already applies whenever a
 *    component is inapplicable (e.g. no completed tasks yet), not a new
 *    invented behaviour.
 *
 * Pure computation, no I/O — the caller (Platform\TaskController) fetches
 * `tasks` via SupabaseUserService and hands the array here.
 */
class PlatformTaskScoreCalculator
{
    private const PRIORITY_WEIGHT = ['low' => 1.0, 'medium' => 1.5, 'high' => 2.0];
    private const MAX_PRIORITY_WEIGHT = 2.0;
    private const OVERDUE_PENALTY = ['low' => 1.0, 'medium' => 2.0, 'high' => 5.0];

    /** Legacy's base weights for the 3 components the Platform can compute. */
    private const BASE_WEIGHTS = ['completion_rate' => 40, 'on_time' => 25, 'priority_impact' => 15];

    /**
     * @param array<int, array{status: string, priority: string, due_date: string|null, created_at: string, updated_at: string}> $tasks
     * @return array{score: float|null, status: string, breakdown: array<string, float>}
     */
    public function calculate(array $tasks, Carbon $periodStart, Carbon $periodEnd): array
    {
        $scoped = $this->scopedTasks($tasks, $periodStart, $periodEnd);

        if ($scoped->isEmpty()) {
            return $this->result(null, []);
        }

        $done = $scoped->filter(fn ($t) => ($t['status'] ?? null) === 'done');
        $notDone = $scoped->reject(fn ($t) => ($t['status'] ?? null) === 'done');

        $totalWeight = $scoped->sum(fn ($t) => $this->priorityWeight($t));
        $doneWeight = $done->sum(fn ($t) => $this->priorityWeight($t));

        $components = [
            // Always applicable here: $scoped is non-empty, and every task
            // carries a priority weight of at least 1.0, so $totalWeight > 0.
            'completion_rate' => $this->pct($doneWeight, $totalWeight),
            'on_time' => null,
            'priority_impact' => null,
        ];

        $doneWithDueDate = $done->filter(fn ($t) => !empty($t['due_date']));
        if ($doneWithDueDate->isNotEmpty()) {
            $onTimeWeight = $doneWithDueDate->filter(fn ($t) => $this->completedOnTime($t))->sum(fn ($t) => $this->priorityWeight($t));
            $dueWeight = $doneWithDueDate->sum(fn ($t) => $this->priorityWeight($t));
            $components['on_time'] = $this->pct($onTimeWeight, $dueWeight);
        }

        $completedCount = $done->count();
        if ($completedCount > 0) {
            $components['priority_impact'] = $this->pct($doneWeight, $completedCount * self::MAX_PRIORITY_WEIGHT);
        }

        $applicable = array_filter($components, fn ($v) => $v !== null);
        $weightSum = array_sum(array_intersect_key(self::BASE_WEIGHTS, $applicable));

        if ($weightSum <= 0) {
            return $this->result(null, $components);
        }

        $weightedSum = 0.0;
        foreach ($applicable as $key => $value) {
            $weightedSum += $value * (self::BASE_WEIGHTS[$key] / $weightSum);
        }

        $overduePenalty = $notDone
            ->filter(fn ($t) => !empty($t['due_date']) && Carbon::parse($t['due_date'])->lt($periodEnd))
            ->sum(fn ($t) => self::OVERDUE_PENALTY[$t['priority'] ?? 'medium'] ?? 1.0);

        $score = round(max(0, min(100, $weightedSum - $overduePenalty)), 2);

        return $this->result($score, [
            ...$components,
            'overdue_penalty' => round($overduePenalty, 2),
        ]);
    }

    /**
     * A task is "in scope" for the period if: its due date falls within the
     * period; it's still open (not done) and overdue from before the period
     * started (carryover keeps being penalized until resolved); or it has no
     * due date but was created within the period. Cancelled tasks are
     * dropped before any of this — they neither help nor hurt the score.
     */
    private function scopedTasks(array $tasks, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $start = $periodStart->copy()->startOfDay();
        $end = $periodEnd->copy()->endOfDay();

        return collect($tasks)
            ->filter(fn ($t) => ($t['status'] ?? null) !== 'cancelled')
            ->filter(function ($t) use ($start, $end) {
                if (!empty($t['due_date'])) {
                    $due = Carbon::parse($t['due_date']);

                    if ($due->between($start, $end)) {
                        return true;
                    }

                    return ($t['status'] ?? null) !== 'done' && $due->lt($start);
                }

                return !empty($t['created_at']) && Carbon::parse($t['created_at'])->between($start, $end);
            })
            ->values();
    }

    /**
     * "Completed by the due date" — uses `updated_at` as the completion
     * timestamp, exactly the fallback legacy's own calculator uses when no
     * separate per-update log row records the real completion moment (the
     * Platform has no such log at all, so this is legacy's own documented
     * fallback path, not a new approximation invented for this port).
     */
    private function completedOnTime(array $task): bool
    {
        return Carbon::parse($task['updated_at'])->lte(Carbon::parse($task['due_date'])->endOfDay());
    }

    private function priorityWeight(array $task): float
    {
        return self::PRIORITY_WEIGHT[$task['priority'] ?? 'medium'] ?? 1.0;
    }

    private function pct(float $numerator, float $denominator): float
    {
        return $denominator > 0 ? round(min(100, $numerator / $denominator * 100), 2) : 0.0;
    }

    /** @param array<string, float|null> $breakdown */
    private function result(?float $score, array $breakdown): array
    {
        return [
            'score' => $score,
            'status' => $this->statusForScore($score),
            'breakdown' => $breakdown,
        ];
    }

    private function statusForScore(?float $score): string
    {
        if ($score === null) {
            return 'insufficient_data';
        }

        if ($score >= 80) {
            return 'on_track';
        }

        if ($score >= 60) {
            return 'at_risk';
        }

        return 'critical';
    }
}
