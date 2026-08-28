<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performix Company Platform, Phase 1 hardening (Part 1): `company_kpi_summary`
 * was the one remaining piece of duplicated, direction-blind KPI math left
 * after `KpiCalculationService`/`kpiAchievement.ts` were introduced —
 * confirmed by full-codebase audit. Its `avg(value / target * 100)` ignored
 * `measurement_direction` entirely (a lower-is-better KPI would score
 * backwards) and averaged EVERY submission ever made against a KPI, not the
 * current one — a KPI with 12 monthly submissions would show a blended
 * year-long average on the dashboard while the KPI list right next to it
 * showed only the latest value's achievement.
 *
 * A Postgres view cannot call into PHP, so this adds `kpi_calc_achievement()`
 * — a third, deliberate port of `KpiCalculationService::achievement()`'s
 * exact branches (mirrored again in `resources/js/lib/kpiAchievement.ts` for
 * the client). All three are unit-tested independently (see
 * `tests/Unit/KpiCalculationServiceTest.php` for the PHP version;
 * `database/rls-tests/tenant_isolation.sql` scenario 20 below exercises this
 * SQL version against the same fixtures) — a raw SQL view has no other way
 * to stay in sync with the same formula.
 *
 * The view now also only considers each KPI's latest APPROVED submission
 * (Part 4: dashboards use approved performance, never a pending/unreviewed
 * value) rather than averaging historical submissions — matching how
 * `KpiController::attachComputedPerformance()` already picks "the current
 * value" per KPI.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function kpi_calc_achievement(actual numeric, base numeric, stretch numeric, direction text)
            returns numeric
            language plpgsql immutable
            as $$
            begin
              if direction = 'binary_completion' then
                return case when actual is not null and actual <> 0 then 100.0 else 0.0 end;
              end if;

              if direction in ('target_range', 'on_or_before') then
                return null;
              end if;

              if base is null or base = 0 then
                return null;
              end if;

              if direction = 'lower_is_better' then
                if actual > base then
                  return (base / actual) * 100;
                end if;
                if stretch is not null and stretch <> base and actual < base then
                  return least(200.0, 100 + ((base - actual) / (base - stretch)) * 100);
                end if;
                return 100.0;
              end if;

              -- higher_is_better (default/fallback)
              if actual < base then
                return (actual / base) * 100;
              end if;
              if stretch is not null and stretch <> base then
                return least(200.0, 100 + ((actual - base) / (stretch - base)) * 100);
              end if;
              return 100.0;
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace view company_kpi_summary
            with (security_invoker = true)
            as
            select
                c.id as company_id,
                (select count(*) from departments d where d.company_id = c.id) as department_count,
                (select count(*) from company_users cu where cu.company_id = c.id and cu.status = 'active') as user_count,
                (select count(*) from kpis k where k.company_id = c.id) as kpi_count,
                (select count(*) from kpi_submissions ks where ks.company_id = c.id) as submission_count,
                (
                    select round(avg(kpi_calc_achievement(latest.value, k.target, k.stretch_target, k.measurement_direction)), 1)
                    from kpis k
                    join lateral (
                        select ks.value
                        from kpi_submissions ks
                        where ks.kpi_id = k.id and ks.status = 'approved'
                        order by ks.submission_date desc, ks.created_at desc
                        limit 1
                    ) latest on true
                    where k.company_id = c.id
                ) as avg_achievement_pct
            from companies c
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace view company_kpi_summary
            with (security_invoker = true)
            as
            select
                c.id as company_id,
                (select count(*) from departments d where d.company_id = c.id) as department_count,
                (select count(*) from company_users cu where cu.company_id = c.id and cu.status = 'active') as user_count,
                (select count(*) from kpis k where k.company_id = c.id) as kpi_count,
                (select count(*) from kpi_submissions ks where ks.company_id = c.id) as submission_count,
                (
                    select round(avg(ks.value / k.target * 100), 1)
                    from kpi_submissions ks
                    join kpis k on k.id = ks.kpi_id
                    where ks.company_id = c.id and k.target is not null and k.target <> 0
                ) as avg_achievement_pct
            from companies c
        SQL);

        DB::connection('pgsql')->statement('drop function if exists kpi_calc_achievement(numeric, numeric, numeric, text)');
    }
};
