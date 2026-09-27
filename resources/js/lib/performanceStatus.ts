/**
 * Shared "On Track / At Risk / Critical" tiering for a KPI/goal/department
 * achievement percentage — a distinct concept from `kpiAchievement.ts`'s raw
 * formula (which produces the percentage itself). One threshold set used by
 * every role view (CEO needs-attention, HR distribution, department lists)
 * so the same 61% reads as "At Risk" everywhere it appears.
 */
export type PerformanceStatus = 'on_track' | 'at_risk' | 'critical' | 'no_data';

export function performanceStatusFor(pct: number | null | undefined): PerformanceStatus {
    if (pct === null || pct === undefined) return 'no_data';
    if (pct >= 85) return 'on_track';
    if (pct >= 60) return 'at_risk';
    return 'critical';
}

export const PERFORMANCE_STATUS_LABEL: Record<PerformanceStatus, string> = {
    on_track: 'On Track',
    at_risk: 'At Risk',
    critical: 'Critical',
    no_data: 'No data yet',
};
