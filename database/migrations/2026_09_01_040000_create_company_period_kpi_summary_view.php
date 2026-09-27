<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Per-quarter achievement history, for the "Achievement Trend" dashboard
 * widget. Unlike `company_kpi_summary`/`company_department_kpi_summary`
 * (which only ever look at each KPI's single latest approved submission),
 * this groups by (financial_year, period_number) so a past quarter's number
 * doesn't move every time a new quarter's actual comes in.
 *
 * Within one (kpi, financial_year, period_number), a KPI can have more than
 * one approved submission over time (revisions) — `distinct on` picks the
 * most recent one per period, the exact same "latest wins" rule
 * `company_kpi_summary` already applies globally, just scoped to one period
 * instead of all-time.
 *
 * Known, deliberate simplification: this joins against `kpis.target`
 * (today's current target), not whatever the target actually was at the
 * time that quarter closed. Reconstructing point-in-time targets from
 * `kpi_target_revisions`' own history is a real, larger feature this view
 * doesn't attempt — the same "don't build it until it's actually needed"
 * judgment call this codebase already made for per-employee/per-period
 * kpi_targets (see Phase 2's own note). A trend chart showing "how are we
 * doing against today's targets, historically" is still a real, honest
 * answer to the question a Company Admin is actually asking.
 *
 * `security_invoker = true`, same reasoning as every other view in this
 * schema — `kpi_submissions`/`kpis` already carry their own RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            create view company_period_kpi_summary
            with (security_invoker = true)
            as
            select
                company_id,
                financial_year,
                period_number,
                round(avg(kpi_calc_achievement(value, target, stretch_target, measurement_direction)), 1) as avg_achievement_pct,
                count(distinct kpi_id) as kpi_count
            from (
                select distinct on (ks.kpi_id, ks.financial_year, ks.period_number)
                    ks.company_id, ks.kpi_id, ks.financial_year, ks.period_number, ks.value,
                    k.target, k.stretch_target, k.measurement_direction
                from kpi_submissions ks
                join kpis k on k.id = ks.kpi_id
                where ks.status = 'approved' and ks.period_type = 'quarter'
                order by ks.kpi_id, ks.financial_year, ks.period_number, ks.submission_date desc, ks.created_at desc
            ) latest_per_period
            group by company_id, financial_year, period_number
        SQL);

        DB::connection('pgsql')->statement('grant select on public.company_period_kpi_summary to authenticated');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop view if exists company_period_kpi_summary');
    }
};
