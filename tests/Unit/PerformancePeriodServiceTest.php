<?php

namespace Tests\Unit;

use App\Services\PerformancePeriodService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PerformancePeriodServiceTest extends TestCase
{
    public function test_january_start_matches_plain_calendar_year(): void
    {
        $periods = new PerformancePeriodService(1);

        $this->assertSame(2027, $periods->financialYearFor(Carbon::create(2027, 3, 15)));
        $this->assertSame(1, $periods->quarterFor(Carbon::create(2027, 1, 15)));
        $this->assertSame(2, $periods->quarterFor(Carbon::create(2027, 4, 1)));
        $this->assertSame(4, $periods->quarterFor(Carbon::create(2027, 12, 31)));
    }

    /** Spec §8: "Do not assume every company uses January to December." */
    public function test_april_start_financial_year(): void
    {
        $periods = new PerformancePeriodService(4);

        // A date in Jan 2028 belongs to FY2027 (started April 2027).
        $this->assertSame(2027, $periods->financialYearFor(Carbon::create(2028, 1, 15)));
        // A date in April 2027 itself starts FY2027.
        $this->assertSame(2027, $periods->financialYearFor(Carbon::create(2027, 4, 1)));
        // A date in March 2027 still belongs to the PRIOR FY (FY2026).
        $this->assertSame(2026, $periods->financialYearFor(Carbon::create(2027, 3, 31)));
    }

    public function test_april_start_quarters(): void
    {
        $periods = new PerformancePeriodService(4);

        $this->assertSame(1, $periods->quarterFor(Carbon::create(2027, 4, 15))); // Apr-Jun = Q1
        $this->assertSame(2, $periods->quarterFor(Carbon::create(2027, 7, 1)));  // Jul-Sep = Q2
        $this->assertSame(3, $periods->quarterFor(Carbon::create(2027, 10, 1))); // Oct-Dec = Q3
        $this->assertSame(4, $periods->quarterFor(Carbon::create(2028, 1, 15))); // Jan-Mar = Q4
    }

    public function test_financial_year_bounds_april_start(): void
    {
        $periods = new PerformancePeriodService(4);
        [$start, $end] = $periods->financialYearBounds(2027);

        $this->assertSame('2027-04-01', $start->toDateString());
        $this->assertSame('2028-03-31', $end->toDateString());
    }

    public function test_quarter_bounds(): void
    {
        $periods = new PerformancePeriodService(1);
        [$start, $end] = $periods->quarterBounds(2027, 2);

        $this->assertSame('2027-04-01', $start->toDateString());
        $this->assertSame('2027-06-30', $end->toDateString());
    }

    public function test_period_elapsed_fraction_halfway(): void
    {
        $periods = new PerformancePeriodService(1);
        $fraction = $periods->periodElapsedFraction(
            Carbon::create(2027, 1, 1),
            Carbon::create(2027, 12, 31, 23, 59, 59),
            Carbon::create(2027, 7, 2, 12), // roughly halfway through the year
        );

        $this->assertEqualsWithDelta(0.5, $fraction, 0.01);
    }

    public function test_period_elapsed_fraction_clamped_before_and_after(): void
    {
        $periods = new PerformancePeriodService(1);
        $start = Carbon::create(2027, 1, 1);
        $end = Carbon::create(2027, 12, 31);

        $this->assertSame(0.0, $periods->periodElapsedFraction($start, $end, Carbon::create(2026, 6, 1)));
        $this->assertSame(1.0, $periods->periodElapsedFraction($start, $end, Carbon::create(2028, 1, 1)));
    }

    public function test_month_number_from_name_is_case_insensitive_and_defaults_safely(): void
    {
        $this->assertSame(4, PerformancePeriodService::monthNumberFromName('April'));
        $this->assertSame(4, PerformancePeriodService::monthNumberFromName('  april  '));
        $this->assertSame(1, PerformancePeriodService::monthNumberFromName('not a month'));
        $this->assertSame(1, PerformancePeriodService::monthNumberFromName(null));
    }
}
