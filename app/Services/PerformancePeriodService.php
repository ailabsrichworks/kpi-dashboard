<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Performix Company Platform, Phase 1 (Performance Foundation): the one
 * place financial-year/quarter/month math happens. Before this, nothing in
 * the Platform module read `companies.financial_year_start` at all — it was
 * a free-text field written once at intake and never parsed (confirmed by a
 * full-codebase audit before writing this). Do NOT assume every company's
 * financial year runs January-December; every method here takes the
 * company's own start month rather than hardcoding one.
 *
 * "Financial year label" convention: the FY a date falls in is named after
 * the calendar year its start month falls in — e.g. a company whose FY
 * starts in April: 1 Apr 2027 - 31 Mar 2028 is "FY2027". For a January-start
 * company this is just the calendar year, matching ordinary expectations.
 */
class PerformancePeriodService
{
    private const MONTH_NAMES = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    public function __construct(private readonly int $financialYearStartMonth = 1)
    {
    }

    /**
     * Maps a free-text month name (however a form field happens to spell
     * it) to its 1-12 number, defaulting to January for anything
     * unrecognised — used only to backfill/derive `financial_year_start_month`
     * from the existing free-text `financial_year_start` field.
     */
    public static function monthNumberFromName(?string $name, int $default = 1): int
    {
        if (!$name) {
            return $default;
        }

        $normalized = strtolower(trim($name));

        foreach (self::MONTH_NAMES as $number => $monthName) {
            if (strtolower($monthName) === $normalized) {
                return $number;
            }
        }

        return $default;
    }

    /** Which FY (as a label year) a given date falls into. */
    public function financialYearFor(CarbonInterface $date): int
    {
        if ($this->financialYearStartMonth === 1) {
            return $date->year;
        }

        return $date->month >= $this->financialYearStartMonth ? $date->year : $date->year - 1;
    }

    /** [start, end] Carbon instants for the given FY label year. */
    public function financialYearBounds(int $fyYear): array
    {
        $start = Carbon::create($fyYear, $this->financialYearStartMonth, 1)->startOfDay();
        $end = $start->copy()->addYear()->subSecond();

        return [$start, $end];
    }

    /** Which quarter (1-4) of its own FY a date falls into. */
    public function quarterFor(CarbonInterface $date): int
    {
        [$fyStart] = $this->financialYearBounds($this->financialYearFor($date));
        $monthsSinceStart = $fyStart->diffInMonths($date->copy()->startOfMonth());

        return intdiv(min($monthsSinceStart, 11), 3) + 1;
    }

    /** Which month-of-FY (1-12, not calendar month) a date falls into. */
    public function monthOfFinancialYearFor(CarbonInterface $date): int
    {
        [$fyStart] = $this->financialYearBounds($this->financialYearFor($date));

        return min($fyStart->diffInMonths($date->copy()->startOfMonth()), 11) + 1;
    }

    /** [start, end] Carbon instants for one quarter (1-4) of the given FY. */
    public function quarterBounds(int $fyYear, int $quarter): array
    {
        [$fyStart] = $this->financialYearBounds($fyYear);
        $start = $fyStart->copy()->addMonths(($quarter - 1) * 3);
        $end = $start->copy()->addMonths(3)->subSecond();

        return [$start, $end];
    }

    /** [start, end] Carbon instants for one month-of-FY (1-12) of the given FY. */
    public function monthBounds(int $fyYear, int $monthOfFy): array
    {
        [$fyStart] = $this->financialYearBounds($fyYear);
        $start = $fyStart->copy()->addMonths($monthOfFy - 1);
        $end = $start->copy()->addMonth()->subSecond();

        return [$start, $end];
    }

    /**
     * How far through a period we are, as a 0.0-1.0 fraction — the basis for
     * "expected progress to date" (spec §15): a KPI at 50% of its annual
     * target isn't necessarily behind if only 50% of the year has elapsed.
     */
    public function periodElapsedFraction(CarbonInterface $periodStart, CarbonInterface $periodEnd, ?CarbonInterface $asOf = null): float
    {
        $asOf = $asOf ?? Carbon::now();

        if ($asOf->lessThanOrEqualTo($periodStart)) {
            return 0.0;
        }
        if ($asOf->greaterThanOrEqualTo($periodEnd)) {
            return 1.0;
        }

        $totalSeconds = $periodStart->diffInSeconds($periodEnd);

        return $totalSeconds > 0 ? $periodStart->diffInSeconds($asOf) / $totalSeconds : 1.0;
    }
}
