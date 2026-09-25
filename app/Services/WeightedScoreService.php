<?php

namespace App\Services;

/**
 * Computes the "My Performance" figures the legacy dashboard's
 * DashboardController::index() derives from kpi_quarters (per-quarter
 * target/actual + weightage) — the Platform has no per-period tracking yet
 * (see database/migrations/2026_09_25_000000's own docblock and this
 * session's Weightage-feature research), so this uses each assigned KPI's
 * MOST RECENT kpi_submissions row as "current achievement" instead of a
 * quarterly rollup. Same overall formula CLAUDE.md documents for legacy —
 * Σ(achievement% × weight%) / Σ(weight%) — just fed from a single current
 * value per KPI rather than four quarters.
 *
 * Pure computation, no I/O — callers (DashboardController) fetch the KPI +
 * submission rows via SupabaseUserService (so RLS still governs what's
 * visible) and hand them here.
 */
class WeightedScoreService
{
    /**
     * @param array<int, array{id: string, name: string, target: float|null, weight: float|null}> $kpis
     * @param array<string, array{value: float, submission_date: string}> $latestSubmissionByKpiId
     * @return array{
     *     overall_score: float|null,
     *     kpi_count: int,
     *     on_track: int,
     *     at_risk: int,
     *     needs_attention: array<int, array{name: string, achievement: float}>,
     *     total_weight: float,
     * }
     */
    public function summarize(array $kpis, array $latestSubmissionByKpiId): array
    {
        $weightedSum = 0.0;
        $weightTotal = 0.0;
        $onTrack = 0;
        $atRisk = 0;
        $ranked = [];

        foreach ($kpis as $kpi) {
            $submission = $latestSubmissionByKpiId[$kpi['id']] ?? null;
            $achievement = $this->achievement($kpi['target'] ?? null, $submission['value'] ?? null);

            if ($achievement === null) {
                // No target, or no submission yet — the legacy dashboard
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

            $weight = $kpi['weight'] ?? null;
            if ($weight !== null) {
                $weightedSum += $achievement * $weight;
                $weightTotal += $weight;
            }
        }

        usort($ranked, fn ($a, $b) => $a['achievement'] <=> $b['achievement']);

        return [
            'overall_score' => $weightTotal > 0 ? round($weightedSum / $weightTotal, 2) : null,
            'kpi_count' => count($kpis),
            'on_track' => $onTrack,
            'at_risk' => $atRisk,
            'needs_attention' => array_slice($ranked, 0, 3),
            'total_weight' => round($weightTotal, 2),
        ];
    }

    private function achievement(?float $target, ?float $value): ?float
    {
        if ($target === null || $target == 0 || $value === null) {
            return null;
        }

        return round(($value / $target) * 100, 2);
    }
}
