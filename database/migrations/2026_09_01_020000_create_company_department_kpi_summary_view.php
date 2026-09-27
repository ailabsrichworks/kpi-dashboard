<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Per-department achievement, for the "Department Achievement" dashboard
 * widget (DashboardWidgetService) — the department-level sibling of
 * `company_kpi_summary`, using the exact same `kpi_calc_achievement()`
 * function (2026_08_28_090000) so a department's number can never drift
 * from what the company-wide average is built out of.
 *
 * `security_invoker = true`, same reasoning as `company_kpi_summary`'s own
 * migration: without it, a security_definer-by-default view would bypass
 * RLS entirely. `departments`/`kpis`/`kpi_submissions` already carry their
 * own RLS, so this view needs none of its own — a caller querying it only
 * ever sees rows their own role could already read directly.
 *
 * Only KPIs with a `department_id` set are counted — company-wide KPIs
 * (`department_id is null`) have no single department to attribute
 * achievement to, and are already covered by `company_kpi_summary`'s own
 * average.
 *
 * `kpi_count` deliberately counts every department-owned KPI regardless of
 * whether it's ever been submitted against (so an admin can see a
 * newly-created KPI exists at all), but `avg_achievement_pct` must only
 * average over KPIs that HAVE an approved submission — feeding a null
 * `actual` into `kpi_calc_achievement()` isn't "no data", it's
 * misinterpreted as "target trivially met" (its higher_is_better branch
 * falls through to a flat 100.0 when `actual` is null, since neither of
 * its `actual < base` / stretch conditions evaluate true against a null).
 * Found by actually running this against a disposable Postgres container:
 * a department with one never-submitted KPI showed 100% instead of the
 * intended null. The `case when latest.value is not null` guard makes the
 * `avg()` call skip those rows entirely (aggregates already skip nulls),
 * without dropping the KPI from `kpi_count`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            create view company_department_kpi_summary
            with (security_invoker = true)
            as
            select
                d.id as department_id,
                d.company_id,
                d.name as department_name,
                count(distinct k.id) as kpi_count,
                round(avg(case when latest.value is not null then kpi_calc_achievement(latest.value, k.target, k.stretch_target, k.measurement_direction) end), 1) as avg_achievement_pct
            from departments d
            left join kpis k on k.department_id = d.id
            left join lateral (
                select ks.value
                from kpi_submissions ks
                where ks.kpi_id = k.id and ks.status = 'approved'
                order by ks.submission_date desc, ks.created_at desc
                limit 1
            ) latest on true
            group by d.id, d.company_id, d.name
        SQL);

        DB::connection('pgsql')->statement('grant select on public.company_department_kpi_summary to authenticated');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop view if exists company_department_kpi_summary');
    }
};
