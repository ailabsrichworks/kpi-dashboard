<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Performix Company Platform, Phase 1 hardening (Part 3): performance period
 * lifecycle. `company_performance_periods` only ever holds an OVERRIDE row —
 * one written the moment an admin explicitly closes, locks, marks
 * under-review, or reopens a period. Absent a row, the period's status is
 * computed purely from dates via `PerformancePeriodService`, so nothing has
 * to pre-seed a row for every quarter/month of every company's future.
 *
 * Gating (`canSubmitFor()`) checks BOTH the month AND the quarter containing
 * a date, and requires both to allow submission — an admin can close a whole
 * quarter at once, or a single month within an otherwise-open quarter, and
 * either one blocks new submissions for that date. The status SHOWN in the
 * UI for a given quarter is the quarter-level override/default only (a
 * company practically always manages periods at quarter granularity); month-
 * level overrides are a finer-grained escape hatch, not the normal path, so
 * they are not separately surfaced everywhere the quarter status is.
 *
 * Type-hinted against `Carbon\CarbonInterface`, not `Illuminate\Support\Carbon`,
 * deliberately: `PerformancePeriodService::quarterBounds()`/`monthBounds()`
 * return plain `Carbon\Carbon` instances, which do NOT satisfy a parameter
 * type-hinted against Laravel's `Illuminate\Support\Carbon` subclass (PHP's
 * type variance goes the other way — a subtype can be passed where a
 * supertype/interface is expected, never vice versa). An earlier version of
 * this class type-hinted `Illuminate\Support\Carbon` and failed with a real
 * TypeError the first time it was exercised end to end through
 * `KpiSubmissionController`, not caught by static review — the interface
 * both classes actually implement is the correct, safe type-hint here.
 */
class PeriodLifecycleService
{
    private const STATUSES = ['upcoming', 'open', 'submission_due', 'under_review', 'closed', 'locked'];

    private const SUBMITTABLE = ['open', 'submission_due'];

    public function __construct(private readonly SupabaseUserService $supabase)
    {
    }

    /**
     * The effective status of one period: an explicit override if one has
     * ever been set for it, otherwise computed purely from where `$now`
     * falls relative to the period's own [start, end] bounds.
     */
    public function effectiveStatus(string $companyId, int $financialYear, string $periodType, int $periodNumber, CarbonInterface $start, CarbonInterface $end, ?CarbonInterface $now = null): array
    {
        $override = $this->findOverride($companyId, $financialYear, $periodType, $periodNumber);

        if ($override) {
            return $override;
        }

        $now = $now ?? now();

        if ($now->lessThan($start)) {
            $status = 'upcoming';
        } elseif ($now->greaterThan($end)) {
            $status = 'submission_due';
        } else {
            $status = 'open';
        }

        return ['status' => $status, 'reason' => null, 'set_by' => null, 'set_at' => null];
    }

    public function canSubmitStatus(string $status): bool
    {
        return in_array($status, self::SUBMITTABLE, true);
    }

    /**
     * Whether a new actual submission dated `$date` is currently allowed —
     * both the month it falls in AND the quarter containing that month must
     * be in an open/submission_due state.
     */
    public function canSubmitFor(string $companyId, PerformancePeriodService $periods, CarbonInterface $date, ?CarbonInterface $now = null): array
    {
        $fy = $periods->financialYearFor($date);
        $quarter = $periods->quarterFor($date);
        $monthOfFy = $periods->monthOfFinancialYearFor($date);

        [$quarterStart, $quarterEnd] = $periods->quarterBounds($fy, $quarter);
        [$monthStart, $monthEnd] = $periods->monthBounds($fy, $monthOfFy);

        $quarterState = $this->effectiveStatus($companyId, $fy, 'quarter', $quarter, $quarterStart, $quarterEnd, $now);
        $monthState = $this->effectiveStatus($companyId, $fy, 'month', $monthOfFy, $monthStart, $monthEnd, $now);

        $allowed = $this->canSubmitStatus($quarterState['status']) && $this->canSubmitStatus($monthState['status']);

        return [
            'allowed' => $allowed,
            'financial_year' => $fy,
            'quarter' => $quarter,
            'month_of_fy' => $monthOfFy,
            'quarter_status' => $quarterState['status'],
            'month_status' => $monthState['status'],
        ];
    }

    public function findOverride(string $companyId, int $financialYear, string $periodType, int $periodNumber): ?array
    {
        return $this->supabase->first('company_performance_periods', [
            'company_id' => 'eq.' . $companyId,
            'financial_year' => 'eq.' . $financialYear,
            'period_type' => 'eq.' . $periodType,
            'period_number' => 'eq.' . $periodNumber,
            'select' => 'status,reason,set_by,set_at',
        ]);
    }

    /**
     * Sets (creates or replaces) an explicit override for one period.
     * Reopening is just this with `$status = 'open'` and a required reason —
     * the caller is responsible for auditing the action (this service has no
     * Request context of its own).
     */
    public function setStatus(string $companyId, int $financialYear, string $periodType, int $periodNumber, string $status, string $userId, ?string $reason = null): array
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid period status: {$status}");
        }

        $existing = $this->supabase->first('company_performance_periods', [
            'company_id' => 'eq.' . $companyId,
            'financial_year' => 'eq.' . $financialYear,
            'period_type' => 'eq.' . $periodType,
            'period_number' => 'eq.' . $periodNumber,
            'select' => 'id',
        ]);

        $payload = [
            'status' => $status,
            'reason' => $reason,
            'set_by' => $userId,
            'set_at' => now()->toIso8601String(),
        ];

        if ($existing) {
            return $this->supabase->update('company_performance_periods', ['id' => 'eq.' . $existing['id']], $payload)[0] ?? $payload;
        }

        return $this->supabase->insert('company_performance_periods', $payload + [
            'company_id' => $companyId,
            'financial_year' => $financialYear,
            'period_type' => $periodType,
            'period_number' => $periodNumber,
        ])[0] ?? $payload;
    }
}
