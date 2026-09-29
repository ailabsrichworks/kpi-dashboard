<?php

namespace App\Http\Controllers\Platform\Concerns;

/**
 * `'FY' . current calendar year` — computed dynamically wherever it's
 * needed, never hardcoded. Legacy's own KpiController::currentFY() already
 * does this; its DashboardController instead hardcoded `'FY2026'` as a class
 * property, a real latent bug (it'll silently stop matching once the
 * calendar rolls over) this deliberately does not repeat. No custom
 * fiscal-year offset — a financial year is a calendar year, matching every
 * other date computation in this feature (see the quarterly-tracking
 * migration's own docblock).
 */
trait ComputesFinancialYear
{
    private function currentFinancialYear(): string
    {
        return 'FY' . now()->year;
    }

    /**
     * Calendar-quarter boundaries for the given financial year — Jan-Mar,
     * Apr-Jun, Jul-Sep, Oct-Dec of that year. `$financialYear` is expected in
     * `'FY2026'` shape, as `currentFinancialYear()` produces.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function quarterDateRange(string $financialYear, string $quarter): array
    {
        $year = (int) substr($financialYear, 2);
        $startMonth = ['Q1' => 1, 'Q2' => 4, 'Q3' => 7, 'Q4' => 10][$quarter];

        $start = \Illuminate\Support\Carbon::create($year, $startMonth, 1)->startOfDay();
        $end = $start->copy()->addMonths(3)->subDay()->endOfDay();

        return [$start, $end];
    }
}
