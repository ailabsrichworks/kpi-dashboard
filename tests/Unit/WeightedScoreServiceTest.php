<?php

namespace Tests\Unit;

use App\Services\WeightedScoreService;
use Tests\TestCase;

class WeightedScoreServiceTest extends TestCase
{
    public function test_it_computes_the_weighted_average_of_achievement_and_weight(): void
    {
        $kpis = [
            ['id' => 'a', 'name' => 'KPI A', 'target' => 100, 'weight' => 60],
            ['id' => 'b', 'name' => 'KPI B', 'target' => 50, 'weight' => 40],
        ];
        $latest = [
            'a' => ['value' => 100, 'submission_date' => '2026-01-01'], // 100% achievement
            'b' => ['value' => 25, 'submission_date' => '2026-01-01'],  // 50% achievement
        ];

        $result = (new WeightedScoreService())->summarize($kpis, $latest);

        // (100*60 + 50*40) / 100 = 80
        $this->assertSame(80.0, $result['overall_score']);
        $this->assertSame(100.0, $result['total_weight']);
        $this->assertSame(2, $result['kpi_count']);
        $this->assertSame(1, $result['on_track']);
        $this->assertSame(1, $result['at_risk']);
        $this->assertCount(1, $result['needs_attention']);
        $this->assertSame('KPI B', $result['needs_attention'][0]['name']);
    }

    public function test_a_kpi_with_no_submission_yet_counts_as_at_risk_and_is_excluded_from_the_score(): void
    {
        $kpis = [
            ['id' => 'a', 'name' => 'KPI A', 'target' => 100, 'weight' => 100],
            ['id' => 'b', 'name' => 'Unreported KPI', 'target' => 100, 'weight' => 0],
        ];
        $latest = [
            'a' => ['value' => 100, 'submission_date' => '2026-01-01'],
        ];

        $result = (new WeightedScoreService())->summarize($kpis, $latest);

        $this->assertSame(100.0, $result['overall_score']);
        $this->assertSame(1, $result['on_track']);
        $this->assertSame(1, $result['at_risk']);
    }

    public function test_no_weighted_kpis_at_all_yields_a_null_score_instead_of_dividing_by_zero(): void
    {
        $result = (new WeightedScoreService())->summarize([], []);

        $this->assertNull($result['overall_score']);
        $this->assertSame(0, $result['kpi_count']);
    }

    public function test_needs_attention_is_capped_at_three_and_ranked_worst_first(): void
    {
        $kpis = [
            ['id' => 'a', 'name' => 'Fourth worst', 'target' => 100, 'weight' => 25],
            ['id' => 'b', 'name' => 'Worst', 'target' => 100, 'weight' => 25],
            ['id' => 'c', 'name' => 'Second worst', 'target' => 100, 'weight' => 25],
            ['id' => 'd', 'name' => 'Third worst', 'target' => 100, 'weight' => 25],
        ];
        $latest = [
            'a' => ['value' => 40, 'submission_date' => '2026-01-01'],
            'b' => ['value' => 10, 'submission_date' => '2026-01-01'],
            'c' => ['value' => 20, 'submission_date' => '2026-01-01'],
            'd' => ['value' => 30, 'submission_date' => '2026-01-01'],
        ];

        $result = (new WeightedScoreService())->summarize($kpis, $latest);

        $this->assertCount(3, $result['needs_attention']);
        $this->assertSame(['Worst', 'Second worst', 'Third worst'], array_column($result['needs_attention'], 'name'));
    }

    public function test_a_kpi_with_quarter_rows_uses_the_sum_actual_over_sum_target_rollup_instead_of_the_latest_submission(): void
    {
        $kpis = [
            ['id' => 'a', 'name' => 'Quarterly KPI', 'target' => 999, 'weight' => 100],
        ];
        // A stale/irrelevant "latest submission" is deliberately present here
        // too, to prove the quarter rollup takes precedence over it whenever
        // quarter rows exist for the KPI.
        $latest = ['a' => ['value' => 1, 'submission_date' => '2026-01-01']];
        $quarters = [
            'a' => [
                ['quarter' => 'Q1', 'target' => 100, 'actual' => 50, 'status' => 'on_track'],
                ['quarter' => 'Q2', 'target' => 100, 'actual' => 100, 'status' => 'completed'],
                ['quarter' => 'Q3', 'target' => 100, 'actual' => 80, 'status' => 'at_risk'],
                ['quarter' => 'Q4', 'target' => 100, 'actual' => 70, 'status' => 'not_started'],
            ],
        ];

        $result = (new WeightedScoreService())->summarize($kpis, $latest, $quarters);

        // (50+100+80+70) / (100*4) * 100 = 75
        $this->assertSame(75.0, $result['overall_score']);
        $this->assertSame(1, $result['at_risk']);
        $this->assertSame(0, $result['on_track']);
        $this->assertSame(0, $result['completed_annual']);
        $this->assertSame(['completed' => 1, 'total' => 1, 'progress' => 100.0], $result['quarterly']['Q2']);
        $this->assertSame(['completed' => 0, 'total' => 1, 'progress' => 50.0], $result['quarterly']['Q1']);
    }

    public function test_completed_annual_only_counts_a_kpi_once_all_four_quarters_are_signed_off(): void
    {
        $kpis = [
            ['id' => 'a', 'name' => 'Fully signed off', 'target' => null, 'weight' => 50],
            ['id' => 'b', 'name' => 'Three of four', 'target' => null, 'weight' => 50],
        ];
        $completedQuarter = fn (string $q) => ['quarter' => $q, 'target' => 10, 'actual' => 10, 'status' => 'completed'];
        $quarters = [
            'a' => [$completedQuarter('Q1'), $completedQuarter('Q2'), $completedQuarter('Q3'), $completedQuarter('Q4')],
            'b' => [$completedQuarter('Q1'), $completedQuarter('Q2'), $completedQuarter('Q3'), ['quarter' => 'Q4', 'target' => 10, 'actual' => 5, 'status' => 'on_track']],
        ];

        $result = (new WeightedScoreService())->summarize($kpis, [], $quarters);

        $this->assertSame(1, $result['completed_annual']);
        $this->assertSame(2, $result['quarterly']['Q1']['total']);
    }

    public function test_quarterly_progress_is_shown_even_when_the_kpi_has_no_weight_set(): void
    {
        // Real bug found during this feature's end-to-end verification:
        // per-quarter progress used to be scaled by `weight`, so a quarterly
        // KPI with no weight (the default -- weight is optional everywhere
        // on the Platform) silently always showed 0% progress even with a
        // real actual reported. `weight` should only gate the portfolio-wide
        // overall_score, never a KPI's own quarter progress.
        $kpis = [
            ['id' => 'a', 'name' => 'Unweighted quarterly KPI', 'target' => null, 'weight' => null],
        ];
        $quarters = [
            'a' => [
                ['quarter' => 'Q1', 'target' => 100, 'actual' => 60, 'status' => 'on_track'],
            ],
        ];

        $result = (new WeightedScoreService())->summarize($kpis, [], $quarters);

        $this->assertSame(60.0, $result['quarterly']['Q1']['progress']);
        $this->assertNull($result['overall_score']); // weight is still required for the weighted score itself
    }

    public function test_a_quarter_kpi_with_zero_total_target_is_treated_as_no_achievement_yet(): void
    {
        $kpis = [
            ['id' => 'a', 'name' => 'No targets set', 'target' => null, 'weight' => 100],
        ];
        $quarters = [
            'a' => [
                ['quarter' => 'Q1', 'target' => 0, 'actual' => null, 'status' => 'not_started'],
            ],
        ];

        $result = (new WeightedScoreService())->summarize($kpis, [], $quarters);

        $this->assertNull($result['overall_score']);
        $this->assertSame(1, $result['at_risk']);
        $this->assertSame(0, $result['on_track']);
    }
}
