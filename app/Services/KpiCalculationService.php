<?php

namespace App\Services;

/**
 * Performix Company Platform, Phase 1 (Performance Foundation): the single,
 * reusable KPI calculation engine (spec §11: "do not calculate performance
 * independently inside each dashboard"). Before this, achievement math
 * existed only client-side in resources/js/lib/kpiAchievement.ts — this is
 * the server-authoritative version those rules were ported from, so the
 * same numbers can be computed for pages, future reports, and ANIRA without
 * re-deriving the formula each time.
 *
 * Deliberately stateless / pure-function shaped — every method takes plain
 * values in and returns a plain value out, so it's trivially unit-testable
 * without a database or HTTP context.
 */
class KpiCalculationService
{
    public const STATUSES = ['not_scored', 'critical', 'at_risk', 'on_track', 'achieved', 'exceeded'];

    /**
     * Base/stretch achievement, direction-aware. Matches the legacy app's
     * own formula for 'higher_is_better' (documented in CLAUDE.md's "KPI
     * Scoring Formula"):
     *   actual < base            -> (actual / base) * 100
     *   actual > base w/ stretch -> 100 + ((actual - base) / (stretch - base)) * 100, capped at 200
     *   actual > base, no stretch -> capped at 100
     * 'lower_is_better' mirrors that shape with the actual/base relationship
     * inverted. 'binary_completion' ignores target/stretch. 'target_range'
     * and 'on_or_before' are NOT calculable from a single actual/base/stretch
     * triple (a range needs a min AND max; a deadline needs a date) -- this
     * returns null for those rather than silently guessing.
     */
    public function achievement(float $actual, ?float $base, ?float $stretch, string $direction = 'higher_is_better'): ?float
    {
        if ($direction === 'binary_completion') {
            return $actual ? 100.0 : 0.0;
        }

        if (in_array($direction, ['target_range', 'on_or_before'], true)) {
            return null;
        }

        if ($base === null || $base == 0.0) {
            return null;
        }

        if ($direction === 'lower_is_better') {
            if ($actual > $base) {
                return ($base / $actual) * 100;
            }
            if ($stretch !== null && $stretch != $base && $actual < $base) {
                return min(200.0, 100 + (($base - $actual) / ($base - $stretch)) * 100);
            }

            return 100.0;
        }

        // higher_is_better (default/fallback for any other value)
        if ($actual < $base) {
            return ($actual / $base) * 100;
        }
        if ($stretch !== null && $stretch != $base) {
            return min(200.0, 100 + (($actual - $base) / ($stretch - $base)) * 100);
        }

        return 100.0;
    }

    /**
     * "Expected progress to date" (spec §15) as a percentage of the base
     * target — how far along the KPI SHOULD be, given how much of its
     * period has elapsed. Assumes even distribution across the period; a
     * KPI with its own quarter/month targets set should compare against
     * those directly instead of this linear estimate (the caller's choice,
     * not this method's — it only knows about time, not period-target rows).
     */
    public function expectedProgressPct(float $periodElapsedFraction): float
    {
        return max(0.0, min(1.0, $periodElapsedFraction)) * 100;
    }

    /**
     * Status engine (spec §16). Achievement standing on its own decides
     * achieved/exceeded regardless of timing. Below 100%, status compares
     * achievement AGAINST expected progress (not against a flat 100%) --
     * default thresholds are the spec's own example (>=90% of expected =
     * on track, 70-89% = at risk, <70% = critical), overridable per company
     * via $thresholds without touching this method's logic.
     *
     * Returns 'not_scored' (not one of the spec's lifecycle statuses like
     * NOT_STARTED/CANCELLED/ARCHIVED -- those are states the caller already
     * knows independently of any calculation) when $achievementPct is null,
     * i.e. achievement() itself couldn't compute a number.
     */
    public function status(?float $achievementPct, ?float $expectedProgressPct = null, array $thresholds = ['on_track' => 90.0, 'at_risk' => 70.0]): string
    {
        if ($achievementPct === null) {
            return 'not_scored';
        }

        if ($achievementPct > 100) {
            return 'exceeded';
        }
        if ($achievementPct == 100.0) {
            return 'achieved';
        }

        if ($expectedProgressPct === null || $expectedProgressPct <= 0) {
            // No timing context available -- fall back to flat thresholds
            // against 100%, the only reference point left.
            if ($achievementPct >= $thresholds['on_track']) {
                return 'on_track';
            }

            return $achievementPct >= $thresholds['at_risk'] ? 'at_risk' : 'critical';
        }

        $pctOfExpected = ($achievementPct / $expectedProgressPct) * 100;

        if ($pctOfExpected >= $thresholds['on_track']) {
            return 'on_track';
        }

        return $pctOfExpected >= $thresholds['at_risk'] ? 'at_risk' : 'critical';
    }

    /**
     * Weighted roll-up (spec §18/§53): a parent KPI's (or company goal's)
     * achievement from its children's, respecting configured weightage
     * rather than a blind average. $children is a list of
     * ['achievement' => ?float, 'weightage' => ?float].
     *
     * Children with a weightage set are averaged against each other only
     * (weighted); if none have a weightage, falls back to a simple average
     * of whichever children do have a computed achievement. A child with
     * neither is excluded, not counted as a zero -- an unscored contributor
     * shouldn't silently drag the roll-up down.
     */
    public function weightedRollup(array $children): ?float
    {
        $weighted = array_filter($children, fn ($c) => ($c['weightage'] ?? null) !== null && ($c['achievement'] ?? null) !== null);

        if (!empty($weighted)) {
            $totalWeight = array_sum(array_column($weighted, 'weightage'));

            if ($totalWeight <= 0) {
                return null;
            }

            $weightedSum = array_reduce($weighted, fn ($carry, $c) => $carry + $c['achievement'] * $c['weightage'], 0.0);

            return $weightedSum / $totalWeight;
        }

        $scored = array_filter($children, fn ($c) => ($c['achievement'] ?? null) !== null);

        if (empty($scored)) {
            return null;
        }

        return array_sum(array_column($scored, 'achievement')) / count($scored);
    }
}
