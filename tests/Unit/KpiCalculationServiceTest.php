<?php

namespace Tests\Unit;

use App\Services\KpiCalculationService;
use PHPUnit\Framework\TestCase;

class KpiCalculationServiceTest extends TestCase
{
    private KpiCalculationService $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new KpiCalculationService();
    }

    public function test_higher_is_better_below_target(): void
    {
        $this->assertSame(80.0, $this->calc->achievement(80, 100, null, 'higher_is_better'));
    }

    public function test_higher_is_better_above_target_no_stretch_caps_at_100(): void
    {
        $this->assertSame(100.0, $this->calc->achievement(150, 100, null, 'higher_is_better'));
    }

    public function test_higher_is_better_above_target_with_stretch(): void
    {
        // base 100, stretch 200, actual 150 -> 100 + (150-100)/(200-100)*100 = 150
        $this->assertSame(150.0, $this->calc->achievement(150, 100, 200, 'higher_is_better'));
    }

    public function test_higher_is_better_caps_at_200_even_past_stretch(): void
    {
        $this->assertSame(200.0, $this->calc->achievement(1000, 100, 200, 'higher_is_better'));
    }

    /** Spec §11's own example: operating cost target <=500k, actual 575k must NOT read as "115% exceeded." */
    public function test_lower_is_better_overshoot_is_partial_credit_not_exceeded(): void
    {
        $achievement = $this->calc->achievement(575000, 500000, null, 'lower_is_better');

        $this->assertEqualsWithDelta(86.96, $achievement, 0.01);
        $this->assertLessThan(100, $achievement);
    }

    public function test_lower_is_better_meeting_target_exactly_is_100(): void
    {
        $this->assertSame(100.0, $this->calc->achievement(500, 500, null, 'lower_is_better'));
    }

    public function test_lower_is_better_beating_target_with_stretch(): void
    {
        // base 500 (max acceptable), stretch 400 (better), actual 450 -> 100 + (500-450)/(500-400)*100 = 150
        $this->assertSame(150.0, $this->calc->achievement(450, 500, 400, 'lower_is_better'));
    }

    public function test_binary_completion_ignores_target(): void
    {
        $this->assertSame(100.0, $this->calc->achievement(1, null, null, 'binary_completion'));
        $this->assertSame(0.0, $this->calc->achievement(0, null, null, 'binary_completion'));
    }

    public function test_target_range_and_on_or_before_are_not_calculable(): void
    {
        $this->assertNull($this->calc->achievement(50, 100, null, 'target_range'));
        $this->assertNull($this->calc->achievement(50, 100, null, 'on_or_before'));
    }

    public function test_null_or_zero_base_is_not_calculable_for_directional_kpis(): void
    {
        $this->assertNull($this->calc->achievement(50, null, null, 'higher_is_better'));
        $this->assertNull($this->calc->achievement(50, 0, null, 'higher_is_better'));
    }

    public function test_null_achievement_is_not_scored(): void
    {
        $this->assertSame('not_scored', $this->calc->status(null));
    }

    public function test_achievement_over_100_is_exceeded_regardless_of_expected_progress(): void
    {
        $this->assertSame('exceeded', $this->calc->status(120, 50));
    }

    public function test_achievement_exactly_100_is_achieved(): void
    {
        $this->assertSame('achieved', $this->calc->status(100, 50));
    }

    /** Spec §15's own example: 50% achievement at the halfway point of the period is ON TRACK, not "50% behind." */
    public function test_on_track_when_matching_expected_progress(): void
    {
        $this->assertSame('on_track', $this->calc->status(50, 50));
    }

    public function test_at_risk_between_70_and_89_percent_of_expected(): void
    {
        // 40 / 50 * 100 = 80% of expected
        $this->assertSame('at_risk', $this->calc->status(40, 50));
    }

    public function test_critical_below_70_percent_of_expected(): void
    {
        // 20 / 50 * 100 = 40% of expected
        $this->assertSame('critical', $this->calc->status(20, 50));
    }

    public function test_falls_back_to_flat_thresholds_when_no_expected_progress_given(): void
    {
        $this->assertSame('on_track', $this->calc->status(95, null));
        $this->assertSame('at_risk', $this->calc->status(75, null));
        $this->assertSame('critical', $this->calc->status(50, null));
    }

    public function test_weighted_rollup_uses_weights_when_present(): void
    {
        $rollup = $this->calc->weightedRollup([
            ['achievement' => 100.0, 'weightage' => 60.0],
            ['achievement' => 50.0, 'weightage' => 40.0],
        ]);

        // (100*60 + 50*40) / 100 = 80
        $this->assertSame(80.0, $rollup);
    }

    public function test_weighted_rollup_falls_back_to_simple_average_without_weights(): void
    {
        $rollup = $this->calc->weightedRollup([
            ['achievement' => 100.0, 'weightage' => null],
            ['achievement' => 50.0, 'weightage' => null],
        ]);

        $this->assertSame(75.0, $rollup);
    }

    public function test_weighted_rollup_excludes_unscored_children_rather_than_treating_as_zero(): void
    {
        $rollup = $this->calc->weightedRollup([
            ['achievement' => 100.0, 'weightage' => null],
            ['achievement' => null, 'weightage' => null],
        ]);

        $this->assertSame(100.0, $rollup);
    }

    public function test_weighted_rollup_with_no_scored_children_is_null(): void
    {
        $this->assertNull($this->calc->weightedRollup([
            ['achievement' => null, 'weightage' => null],
        ]));
    }
}
