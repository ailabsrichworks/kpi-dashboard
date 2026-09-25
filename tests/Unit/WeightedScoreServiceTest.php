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
}
