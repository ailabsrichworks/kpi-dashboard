<?php

namespace App\Http\Controllers\Platform\Concerns;

/**
 * Shared by `TargetLinkageController` (the Target Linkages page) and
 * `KpiController::create()` (the cascading-target warning banner on the
 * Create KPI form, matching legacy's own `create()` — see
 * 2026_09_29_060000_create_kpi_target_linkages.php's docblock for the
 * category_id/unit adaptation this fixes a real legacy coverage bug with).
 * Extracted rather than duplicated so the two pages can never compute
 * coverage differently.
 */
trait ComputesLinkageCoverage
{
    /** @return array<string, float> keyed by "category_id|unit" */
    private function linkageCoverageMap(array $kpis): array
    {
        $sums = [];
        foreach ($kpis as $kpi) {
            $key = ($kpi['category_id'] ?? '') . '|' . ($kpi['unit'] ?? '');
            $sums[$key] = ($sums[$key] ?? 0) + (float) ($kpi['target'] ?? 0);
        }

        return $sums;
    }

    private function withLinkageCoverage(array $linkages, array $coverage): array
    {
        return array_map(function ($lnk) use ($coverage) {
            $key = $lnk['category_id'] . '|' . ($lnk['unit'] ?? '');
            $covered = $coverage[$key] ?? 0.0;
            $target = (float) $lnk['assigned_target'];
            $gap = max(0, $target - $covered);
            $pct = $target > 0 ? min(100, round($covered / $target * 100)) : 100;

            return $lnk + ['covered' => $covered, 'gap' => $gap, 'pct' => $pct, 'met' => $covered >= $target];
        }, $linkages);
    }
}
