<?php

namespace Tests\Unit;

use App\Services\PlatformTaskScoreCalculator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlatformTaskScoreCalculatorTest extends TestCase
{
    private Carbon $periodStart;
    private Carbon $periodEnd;

    protected function setUp(): void
    {
        parent::setUp();
        // A fixed Mon-Sun week, matched to how TaskController computes "this week".
        $this->periodStart = Carbon::parse('2026-09-21'); // Monday
        $this->periodEnd = Carbon::parse('2026-09-27');   // Sunday
    }

    public function test_no_scoped_tasks_yields_insufficient_data(): void
    {
        $result = (new PlatformTaskScoreCalculator())->calculate([], $this->periodStart, $this->periodEnd);

        $this->assertNull($result['score']);
        $this->assertSame('insufficient_data', $result['status']);
    }

    public function test_a_task_with_no_due_date_created_outside_the_period_is_out_of_scope(): void
    {
        $tasks = [
            ['status' => 'open', 'priority' => 'medium', 'due_date' => null, 'created_at' => '2026-08-01T00:00:00Z', 'updated_at' => '2026-08-01T00:00:00Z'],
        ];

        $result = (new PlatformTaskScoreCalculator())->calculate($tasks, $this->periodStart, $this->periodEnd);

        $this->assertNull($result['score']);
        $this->assertSame('insufficient_data', $result['status']);
    }

    public function test_a_single_high_priority_task_done_on_time_scores_100_and_is_on_track(): void
    {
        $tasks = [
            ['status' => 'done', 'priority' => 'high', 'due_date' => '2026-09-23', 'created_at' => '2026-09-21T00:00:00Z', 'updated_at' => '2026-09-23T10:00:00Z'],
        ];

        $result = (new PlatformTaskScoreCalculator())->calculate($tasks, $this->periodStart, $this->periodEnd);

        $this->assertSame(100.0, $result['score']);
        $this->assertSame('on_track', $result['status']);
        $this->assertSame(100.0, $result['breakdown']['completion_rate']);
        $this->assertSame(100.0, $result['breakdown']['on_time']);
        $this->assertSame(100.0, $result['breakdown']['priority_impact']);
    }

    public function test_carryover_overdue_task_applies_a_penalty_to_the_score(): void
    {
        $tasks = [
            // Carried over from before the period, still open -- penalized.
            ['status' => 'open', 'priority' => 'low', 'due_date' => '2026-09-10', 'created_at' => '2026-09-01T00:00:00Z', 'updated_at' => '2026-09-01T00:00:00Z'],
            // Completed on time within the period.
            ['status' => 'done', 'priority' => 'medium', 'due_date' => '2026-09-22', 'created_at' => '2026-09-21T00:00:00Z', 'updated_at' => '2026-09-22T09:00:00Z'],
        ];

        $result = (new PlatformTaskScoreCalculator())->calculate($tasks, $this->periodStart, $this->periodEnd);

        // completion_rate = 1.5/(1.5+1.0) = 60; on_time = 100; priority_impact = 1.5/(1*2.0) = 75.
        // All 3 components applicable -> renormalized relative to each other
        // (40:25:15, i.e. 0.5:0.3125:0.1875 of the total, since only 3 of
        // legacy's 4 components exist here at all): 60*.5 + 100*.3125 +
        // 75*.1875 = 75.3125; overdue penalty (low) = 1.0 -> 74.3125 -> 74.31.
        $this->assertSame(74.31, $result['score']);
        $this->assertSame('at_risk', $result['status']);
        $this->assertSame(1.0, $result['breakdown']['overdue_penalty']);
    }

    public function test_no_completed_tasks_renormalizes_onto_completion_rate_alone(): void
    {
        $tasks = [
            ['status' => 'open', 'priority' => 'medium', 'due_date' => '2026-09-26', 'created_at' => '2026-09-21T00:00:00Z', 'updated_at' => '2026-09-21T00:00:00Z'],
        ];

        $result = (new PlatformTaskScoreCalculator())->calculate($tasks, $this->periodStart, $this->periodEnd);

        $this->assertNull($result['breakdown']['on_time']);
        $this->assertNull($result['breakdown']['priority_impact']);
        $this->assertSame(0.0, $result['breakdown']['completion_rate']);
        // Only completion_rate applicable -> fully renormalized onto it -> weightedSum = 0.
        $this->assertSame(0.0, $result['score']);
        $this->assertSame('critical', $result['status']);
    }

    public function test_cancelled_tasks_are_dropped_entirely(): void
    {
        $tasks = [
            ['status' => 'cancelled', 'priority' => 'high', 'due_date' => '2026-09-01', 'created_at' => '2026-08-01T00:00:00Z', 'updated_at' => '2026-08-01T00:00:00Z'],
        ];

        $result = (new PlatformTaskScoreCalculator())->calculate($tasks, $this->periodStart, $this->periodEnd);

        $this->assertNull($result['score']);
        $this->assertSame('insufficient_data', $result['status']);
    }
}
