<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Computes the "My Performance" figures the legacy dashboard's
 * DashboardController::index() derives from kpi_quarters (per-quarter
 * target/actual + weightage). Now that the Platform has its own
 * `kpi_quarters` table (2026_09_26_000000_add_kpi_quarterly_tracking), a KPI
 * with quarter rows for the current financial year uses the same
 * "sum(actual)/sum(target) across quarters" rollup legacy's
 * DashboardController::calculateKpiScore() uses, in preference to a single
 * latest-submission value; a KPI with no quarters (its frequency isn't
 * `quarterly`, or none have been set yet) falls back to the single
 * latest-`kpi_submissions`-row achievement exactly as before. Same overall
 * formula CLAUDE.md documents for legacy — Σ(achievement% × weight%) /
 * Σ(weight%) — whichever way each KPI's own achievement was computed.
 *
 * Pure computation, no I/O — callers (DashboardController) fetch the KPI +
 * submission + quarter rows via SupabaseUserService (so RLS still governs
 * what's visible) and hand them here.
 */
class WeightedScoreService
{
    private const QUARTERS = ['Q1', 'Q2', 'Q3', 'Q4'];

    /**
     * @param array<int, array{id: string, name: string, target: float|null, weight: float|null}> $kpis
     * @param array<string, array{value: float, submission_date: string}> $latestSubmissionByKpiId
     * @param array<string, array<int, array{quarter: string, target: float|null, actual: float|null, status: string}>> $quartersByKpiId
     * @return array{
     *     overall_score: float|null,
     *     kpi_count: int,
     *     on_track: int,
     *     at_risk: int,
     *     needs_attention: array<int, array{name: string, achievement: float}>,
     *     total_weight: float,
     *     quarterly: array<string, array{completed: int, total: int, progress: float}>,
     *     completed_annual: int,
     * }
     */
    public function summarize(array $kpis, array $latestSubmissionByKpiId, array $quartersByKpiId = []): array
    {
        $weightedSum = 0.0;
        $weightTotal = 0.0;
        $onTrack = 0;
        $atRisk = 0;
        $ranked = [];
        $completedAnnual = 0;

        $quarterProgress = array_fill_keys(self::QUARTERS, 0.0);
        $quarterCompleted = array_fill_keys(self::QUARTERS, 0);
        $quarterTotal = array_fill_keys(self::QUARTERS, 0);

        foreach ($kpis as $kpi) {
            $quarters = collect($quartersByKpiId[$kpi['id']] ?? [])->keyBy('quarter');
            $weight = $kpi['weight'] ?? null;

            if ($quarters->isNotEmpty()) {
                $achievement = $this->quarterRollupAchievement($quarters);
                $this->accumulateQuarterlyBreakdown($quarters, $quarterProgress, $quarterCompleted, $quarterTotal);

                if ($quarters->count() === 4 && $quarters->every(fn ($q) => ($q['status'] ?? null) === 'completed')) {
                    $completedAnnual++;
                }
            } else {
                $submission = $latestSubmissionByKpiId[$kpi['id']] ?? null;
                $achievement = $this->achievement($kpi['target'] ?? null, $submission['value'] ?? null);
            }

            if ($achievement === null) {
                // No target, or nothing reported yet — the legacy dashboard
                // folds "not started" into "at risk" rather than a third
                // tile; matched here for the same reason (a KPI with
                // nothing reported yet is exactly the kind of thing that
                // "needs attention").
                $atRisk++;
                continue;
            }

            if ($achievement >= 100) {
                $onTrack++;
            } else {
                $atRisk++;
                $ranked[] = ['name' => $kpi['name'], 'achievement' => $achievement];
            }

            if ($weight !== null) {
                $weightedSum += $achievement * $weight;
                $weightTotal += $weight;
            }
        }

        usort($ranked, fn ($a, $b) => $a['achievement'] <=> $b['achievement']);

        $quarterly = [];
        foreach (self::QUARTERS as $label) {
            $quarterly[$label] = [
                'completed' => $quarterCompleted[$label],
                'total' => $quarterTotal[$label],
                'progress' => $quarterTotal[$label] > 0
                    ? round(min(100, $quarterProgress[$label] / $quarterTotal[$label]), 1)
                    : 0.0,
            ];
        }

        return [
            'overall_score' => $weightTotal > 0 ? round($weightedSum / $weightTotal, 2) : null,
            'kpi_count' => count($kpis),
            'on_track' => $onTrack,
            'at_risk' => $atRisk,
            'needs_attention' => array_slice($ranked, 0, 3),
            'total_weight' => round($weightTotal, 2),
            'quarterly' => $quarterly,
            'completed_annual' => $completedAnnual,
        ];
    }

    /**
     * "sum(actual)/sum(target)" across whichever quarters this KPI has for
     * the current financial year — mirrors legacy's calculateKpiScore(),
     * except a missing `actual` (nothing reported for that quarter yet)
     * counts as 0 rather than being skipped, since its `target` still counts
     * toward the denominator: a quarter nobody has reported on yet should
     * pull the rollup down, not be invisible to it.
     */
    private function quarterRollupAchievement(Collection $quarters): ?float
    {
        $targetSum = (float) $quarters->sum(fn ($q) => (float) ($q['target'] ?? 0));
        $actualSum = (float) $quarters->sum(fn ($q) => (float) ($q['actual'] ?? 0));

        if ($targetSum <= 0) {
            return null;
        }

        return round(($actualSum / $targetSum) * 100, 2);
    }

    /**
     * Feeds the Dashboard's Q1-Q4 tiles — legacy's myProgressByQ/
     * myCompletedByQ/myTotalByQ, reimplemented here so every KPI's
     * contribution is computed once, in the same pass as its overall
     * achievement, rather than a second loop over the same data.
     *
     * Deliberately independent of `weight` — a real bug found during this
     * feature's own end-to-end verification: gating a quarter's contribution
     * on `weight` (as an earlier version of this method did, mirroring how
     * `overall_score` uses it) silently showed 0% progress for any quarterly
     * KPI with no weight set, even though `weight` is optional everywhere
     * else on the Platform and has nothing to do with how much of ITS OWN
     * target that KPI has reached this quarter. `$progress`/`$total` here
     * accumulate a plain sum/count instead, averaged by the caller — weight
     * still (correctly) only gates a KPI's contribution to the portfolio-wide
     * `overall_score` above, not this per-quarter self-progress figure.
     */
    private function accumulateQuarterlyBreakdown(Collection $quarters, array &$progress, array &$completed, array &$total): void
    {
        foreach (self::QUARTERS as $label) {
            $quarter = $quarters->get($label);
            if (!$quarter) {
                continue;
            }

            $total[$label]++;

            if (($quarter['status'] ?? null) === 'completed') {
                $completed[$label]++;
            }

            // A quarter with no target set yet can't be assessed -- treated
            // as 0% (pulls the average down, same "unreported counts as 0"
            // rule quarterRollupAchievement() already documents) rather than
            // being skipped, which would otherwise inflate the average by
            // shrinking its own denominator.
            $target = (float) ($quarter['target'] ?? 0);
            $actual = (float) ($quarter['actual'] ?? 0);
            $progress[$label] += $target > 0 ? min(100, ($actual / $target) * 100) : 0.0;
        }
    }

    private function achievement(?float $target, ?float $value): ?float
    {
        if ($target === null || $target == 0 || $value === null) {
            return null;
        }

        return round(($value / $target) * 100, 2);
    }
}
