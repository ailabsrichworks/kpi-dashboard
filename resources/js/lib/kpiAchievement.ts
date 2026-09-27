/**
 * Reusable KPI achievement calculation — spec §55 (Performix Company
 * Platform): "Do not scatter formulas throughout UI components." Before
 * this, achievement was computed inline in Submissions/Index.tsx as a plain
 * `value / target * 100`, which silently assumed every KPI is
 * higher-is-better and ignored a stretch target entirely.
 *
 * Base/stretch formula matches the legacy app's own (documented in
 * CLAUDE.md's "KPI Scoring Formula"):
 *   actual < base            -> (actual / base) * 100
 *   actual > base w/ stretch -> 100 + ((actual - base) / (stretch - base)) * 100, capped at 200
 *   actual > base, no stretch -> capped at 100
 *
 * 'lower_is_better' mirrors that shape with the actual/base relationship
 * inverted (overshooting the base is the good direction). 'binary_completion'
 * ignores target/stretch entirely — any truthy actual is 100%, otherwise 0%.
 *
 * 'target_range' and 'on_or_before' are NOT calculable from a single
 * actual/base/stretch triple (a range needs a min AND max; a deadline needs
 * a date, not a number) — this returns null for those rather than silently
 * guessing, so callers can render "not scored" instead of a wrong number.
 */

export type MeasurementDirection = 'higher_is_better' | 'lower_is_better' | 'target_range' | 'on_or_before' | 'binary_completion';

export function calculateAchievement(
    actual: number,
    base: number | null,
    stretch: number | null,
    direction: MeasurementDirection = 'higher_is_better',
): number | null {
    if (direction === 'binary_completion') {
        return actual ? 100 : 0;
    }

    if (direction === 'target_range' || direction === 'on_or_before') {
        return null;
    }

    if (base === null || base === 0) {
        return null;
    }

    if (direction === 'lower_is_better') {
        if (actual > base) {
            // Overshot the target the wrong way — partial credit, same
            // shape as higher_is_better's "actual < base" branch.
            return (base / actual) * 100;
        }
        if (stretch !== null && stretch !== base && actual < base) {
            return Math.min(200, 100 + ((base - actual) / (base - stretch)) * 100);
        }
        return 100;
    }

    // higher_is_better (the default/fallback for any other/unknown value)
    if (actual < base) {
        return (actual / base) * 100;
    }
    if (stretch !== null && stretch !== base) {
        return Math.min(200, 100 + ((actual - base) / (stretch - base)) * 100);
    }
    return 100;
}
