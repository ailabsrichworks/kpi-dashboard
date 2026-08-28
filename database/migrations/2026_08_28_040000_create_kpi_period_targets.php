<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performix Company Platform, Phase 1 (Performance Foundation): quarterly
 * and monthly KPI target splitting (spec §9/§10). Before this, a KPI had
 * only a single annual `target` — there was nowhere to store "Q1 20%, Q2
 * 20%, Q3 25%, Q4 35%" or a monthly breakdown at all (confirmed absent by a
 * full-codebase audit: zero `kpi_targets`/`kpi_quarter_targets` hits in any
 * prior migration).
 *
 * One row per KPI per financial year per period (quarter 1-4, or month-of-FY
 * 1-12 -- NOT calendar month, matching PerformancePeriodService's own
 * "month of FY" numbering so a non-January-start company's "month 1" always
 * means its own first FY month, not literally January). Reconciliation
 * against the KPI's annual target is enforced in the application layer
 * (KpiPeriodTargetController), not here, since "does this add up" is a
 * multi-row aggregate check more naturally done once per save than
 * re-derived per-row in a trigger.
 *
 * company_id is derived from the parent KPI (reusing the existing
 * derive_company_id_from_kpi()/prevent_company_id_change() pair from
 * 2026_08_17_110000_separate_platform_and_company_roles.php — same pattern
 * as kpi_access_grants), never trusted from the client.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            create table kpi_period_targets (
                id uuid primary key default gen_random_uuid(),
                company_id uuid not null references companies(id) on delete cascade,
                kpi_id uuid not null references kpis(id) on delete cascade,
                financial_year integer not null,
                period_type text not null,
                period_number smallint not null,
                target numeric not null,
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now()
            )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpi_period_targets add constraint kpi_period_targets_period_type_check
              check (period_type in ('quarter', 'month'))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpi_period_targets add constraint kpi_period_targets_period_number_check
              check (
                (period_type = 'quarter' and period_number between 1 and 4)
                or (period_type = 'month' and period_number between 1 and 12)
              )
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpi_period_targets add constraint kpi_period_targets_unique
              unique (kpi_id, financial_year, period_type, period_number)
        SQL);

        DB::connection('pgsql')->statement('create index kpi_period_targets_kpi_id_index on kpi_period_targets (kpi_id)');
        DB::connection('pgsql')->statement('create index kpi_period_targets_company_id_index on kpi_period_targets (company_id)');

        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.kpi_period_targets to authenticated');

        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_derive_company_id
              before insert or update on kpi_period_targets
              for each row execute function derive_company_id_from_kpi()
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_company_id_change
              before update on kpi_period_targets
              for each row execute function prevent_company_id_change()
        SQL);

        DB::connection('pgsql')->statement('alter table kpi_period_targets enable row level security');

        // Readable by whoever can see the parent KPI (mirrors kpi_submissions_select's
        // reasoning: a period target is meaningless to expose independently
        // of the KPI it belongs to). Writable only by whoever can administer
        // the company -- same tier as kpis_insert/kpis_update, since target
        // planning is an admin action, not a submission.
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_period_targets_select on kpi_period_targets for select
              using (auth_is_richworks_super_admin() or auth_can_view_kpi(kpi_id))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_period_targets_write on kpi_period_targets for all
              using (auth_can_administer_company(company_id))
              with check (auth_can_administer_company(company_id))
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop table if exists kpi_period_targets');
    }
};
