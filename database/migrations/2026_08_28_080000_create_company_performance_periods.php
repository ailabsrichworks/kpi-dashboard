<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performix Company Platform, Phase 1 hardening (Part 3): performance period
 * lifecycle. Deliberately an OVERRIDE table, not a pre-seeded calendar — a
 * row here only exists once an admin has explicitly acted on that period
 * (closed it, locked it, put it under review, or reopened it). Absent a row,
 * `PeriodLifecycleService` computes the default state purely from dates
 * (upcoming / open / submission_due) via the same `PerformancePeriodService`
 * math the calculation engine already uses — this avoids needing to
 * pre-create a row for every quarter/month of every company's future, which
 * would need constant background maintenance for no benefit (nobody acts on
 * a period until they actually want to close/lock/reopen it).
 *
 * `financial_year`/`period_type`/`period_number` mirror `kpi_period_targets`
 * and `kpi_submissions`' own period shape exactly, so all three tables key
 * periods identically.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            create table company_performance_periods (
                id uuid primary key default gen_random_uuid(),
                company_id uuid not null references companies(id) on delete cascade,
                financial_year integer not null,
                period_type text not null,
                period_number smallint not null,
                status text not null,
                reason text null,
                set_by uuid null references users(id) on delete set null,
                set_at timestamptz not null default now(),
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now(),
                unique (company_id, financial_year, period_type, period_number)
            )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table company_performance_periods add constraint company_performance_periods_period_type_check
              check (period_type in ('quarter', 'month'))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table company_performance_periods add constraint company_performance_periods_period_number_check
              check (
                (period_type = 'quarter' and period_number between 1 and 4)
                or (period_type = 'month' and period_number between 1 and 12)
              )
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table company_performance_periods add constraint company_performance_periods_status_check
              check (status in ('upcoming', 'open', 'submission_due', 'under_review', 'closed', 'locked'))
        SQL);

        DB::connection('pgsql')->statement('create index company_performance_periods_company_id_index on company_performance_periods (company_id)');

        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.company_performance_periods to authenticated');

        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_company_id_change
              before update on company_performance_periods
              for each row execute function prevent_company_id_change()
        SQL);

        DB::connection('pgsql')->statement('alter table company_performance_periods enable row level security');

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy company_performance_periods_select on company_performance_periods for select
              using (auth_is_richworks_super_admin() or company_id in (select auth_company_ids()))
        SQL);
        // Period lifecycle transitions (close/lock/reopen/under-review) are a
        // company-administration action, not something an individual
        // department can trigger for the whole company.
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy company_performance_periods_write on company_performance_periods for all
              using (auth_can_administer_company(company_id))
              with check (auth_can_administer_company(company_id))
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop table if exists company_performance_periods');
    }
};
