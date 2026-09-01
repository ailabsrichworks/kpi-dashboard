<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Per-KPI achievement, for the CEO "Needs Attention" and HR compliance
 * views — every other achievement figure in this schema is an aggregate
 * (`company_kpi_summary`, `company_department_kpi_summary`,
 * `company_period_kpi_summary`); nothing exposes "this one KPI is at 61%."
 * Reuses `kpi_calc_achievement()` (2026_08_28_090000) exactly, the same
 * latest-approved-submission-per-KPI shape as `company_department_kpi_summary`
 * — so this can never disagree with either aggregate it's built from the
 * same rows as. `security_invoker = true`: `kpis`/`kpi_submissions`/
 * `departments` already carry their own RLS, so a caller querying this view
 * only ever sees rows their role could already read directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            create view company_kpi_achievement
            with (security_invoker = true)
            as
            select
                k.id as kpi_id,
                k.company_id,
                k.name,
                k.department_id,
                d.name as department_name,
                k.owner_user_id,
                k.company_goal_id,
                k.target,
                k.stretch_target,
                k.measurement_direction,
                k.weightage,
                latest.value as latest_value,
                latest.submission_date as latest_submission_date,
                kpi_calc_achievement(latest.value, k.target, k.stretch_target, k.measurement_direction) as achievement_pct
            from kpis k
            left join departments d on d.id = k.department_id
            left join lateral (
                select ks.value, ks.submission_date
                from kpi_submissions ks
                where ks.kpi_id = k.id and ks.status = 'approved'
                order by ks.submission_date desc, ks.created_at desc
                limit 1
            ) latest on true
        SQL);

        DB::connection('pgsql')->statement('grant select on public.company_kpi_achievement to authenticated');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop view if exists company_kpi_achievement');
    }
};
