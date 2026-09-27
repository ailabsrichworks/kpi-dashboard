<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performix Company Platform, Phase 2 (Performance Core): Company Goals and
 * the KPI cascade (Company Goal -> Company KPI -> Department KPI ->
 * Individual KPI), plus the measurement configuration (unit/direction/
 * stretch target/weightage) `kpis` never had — it only ever supported a
 * single flat `target`, implicitly "higher is better."
 *
 * Deliberately additive, same as the Phase 1 migration: no existing column
 * is renamed (`target` stays `target` and is treated as the base target;
 * `stretch_target` is new and sits alongside it, matching the legacy app's
 * own base/stretch achievement formula documented in CLAUDE.md rather than
 * inventing a different one). Every new `kpis` column is nullable, so every
 * existing KPI keeps behaving exactly as it does today — a KPI with no
 * `parent_kpi_id` is a root (a Company KPI), matching current behavior.
 *
 * `kpis.department_id` is a new, independent concept from the existing
 * submission-inferred "department visibility" in `auth_can_view_kpi()`
 * (which infers a department's access to a 'department'-visibility KPI from
 * whether that department has actually submitted against it, not from any
 * column on `kpis`). This column is ownership/cascade metadata only — which
 * department a "Department KPI" belongs to — and is deliberately NOT wired
 * into any RLS policy, so the already-verified visibility system is
 * untouched by this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- company_goals ----------------------------------------------
        DB::connection('pgsql')->statement(<<<'SQL'
            create table company_goals (
                id uuid primary key default gen_random_uuid(),
                company_id uuid not null references companies(id) on delete cascade,
                title text not null,
                description text null,
                category text null,
                subcategory text null,
                owner_user_id uuid null references users(id) on delete set null,
                weightage numeric null,
                start_date date null,
                end_date date null,
                status text not null default 'draft',
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now()
            )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table company_goals add constraint company_goals_status_check
              check (status in ('draft', 'active', 'at_risk', 'completed', 'cancelled', 'archived'))
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table company_goals add constraint company_goals_weightage_check
              check (weightage is null or (weightage >= 0 and weightage <= 100))
        SQL);

        DB::connection('pgsql')->statement('create index company_goals_company_id_index on company_goals (company_id)');

        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.company_goals to authenticated');

        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_company_id_change
              before update on company_goals
              for each row execute function prevent_company_id_change()
        SQL);

        DB::connection('pgsql')->statement('alter table company_goals enable row level security');

        // Company direction is meant to be understood company-wide (spec:
        // "management should be able to answer are we achieving our company
        // goals") -- readable by any active member, mirroring departments_select
        // (auth_company_ids(), not auth_can_view_company_wide()). Writes stay
        // restricted to whoever can administer the company, same as kpis_insert.
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy company_goals_select on company_goals for select
              using (auth_is_richworks_super_admin() or company_id in (select auth_company_ids()))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy company_goals_insert on company_goals for insert
              with check (auth_can_administer_company(company_id))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy company_goals_update on company_goals for update
              using (auth_can_administer_company(company_id))
              with check (auth_can_administer_company(company_id))
        SQL);

        // --- KPI cascade + measurement configuration ---------------------
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpis
                add column if not exists company_goal_id uuid null references company_goals(id) on delete set null,
                add column if not exists parent_kpi_id uuid null references kpis(id) on delete set null,
                add column if not exists department_id uuid null references departments(id) on delete set null,
                add column if not exists owner_user_id uuid null references users(id) on delete set null,
                add column if not exists measurement_unit text not null default 'number',
                add column if not exists measurement_direction text not null default 'higher_is_better',
                add column if not exists stretch_target numeric null,
                add column if not exists weightage numeric null
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpis add constraint kpis_measurement_unit_check
              check (measurement_unit in ('number', 'currency', 'percentage', 'ratio', 'days', 'hours', 'score', 'binary', 'custom'))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpis add constraint kpis_measurement_direction_check
              check (measurement_direction in ('higher_is_better', 'lower_is_better', 'target_range', 'on_or_before', 'binary_completion'))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpis add constraint kpis_weightage_check
              check (weightage is null or (weightage >= 0 and weightage <= 100))
        SQL);

        DB::connection('pgsql')->statement('create index kpis_parent_kpi_id_index on kpis (parent_kpi_id)');
        DB::connection('pgsql')->statement('create index kpis_company_goal_id_index on kpis (company_goal_id)');
        DB::connection('pgsql')->statement('create index kpis_department_id_index on kpis (department_id)');

        // A KPI cannot be its own ancestor, its parent/goal/department must
        // belong to the same company, and (unlike departments, which are
        // shallow) the walk is bounded generously since a KPI cascade can
        // legitimately run Company -> Department -> Individual, i.e. deeper
        // than the 4-level organisation hierarchy.
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function prevent_circular_kpi_cascade()
            returns trigger language plpgsql as $$
            declare
              current_parent uuid;
              related_company uuid;
              visited uuid[] := array[NEW.id];
              depth integer := 0;
            begin
              if NEW.parent_kpi_id is not null then
                if NEW.parent_kpi_id = NEW.id then
                  raise exception 'A KPI cannot be its own parent.';
                end if;

                select company_id into related_company from kpis where id = NEW.parent_kpi_id;

                if related_company is null then
                  raise exception 'Parent KPI % does not exist.', NEW.parent_kpi_id;
                end if;

                if related_company <> NEW.company_id then
                  raise exception 'A KPI''s parent must belong to the same company.';
                end if;

                current_parent := NEW.parent_kpi_id;

                while current_parent is not null and depth < 100 loop
                  if current_parent = any(visited) then
                    raise exception 'This would create a circular KPI cascade.';
                  end if;

                  visited := visited || current_parent;

                  select parent_kpi_id into current_parent from kpis where id = current_parent;

                  depth := depth + 1;
                end loop;
              end if;

              if NEW.company_goal_id is not null then
                select company_id into related_company from company_goals where id = NEW.company_goal_id;

                if related_company is null then
                  raise exception 'Company goal % does not exist.', NEW.company_goal_id;
                end if;

                if related_company <> NEW.company_id then
                  raise exception 'A KPI''s company goal must belong to the same company.';
                end if;
              end if;

              if NEW.department_id is not null then
                select company_id into related_company from departments where id = NEW.department_id;

                if related_company is null then
                  raise exception 'Department % does not exist.', NEW.department_id;
                end if;

                if related_company <> NEW.company_id then
                  raise exception 'A KPI''s department must belong to the same company.';
                end if;
              end if;

              return NEW;
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement('drop trigger if exists trg_prevent_circular_kpi_cascade on kpis');
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_circular_kpi_cascade
              before insert or update on kpis
              for each row execute function prevent_circular_kpi_cascade()
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop trigger if exists trg_prevent_circular_kpi_cascade on kpis');
        DB::connection('pgsql')->statement('drop function if exists prevent_circular_kpi_cascade()');

        DB::connection('pgsql')->statement('drop index if exists kpis_department_id_index');
        DB::connection('pgsql')->statement('drop index if exists kpis_company_goal_id_index');
        DB::connection('pgsql')->statement('drop index if exists kpis_parent_kpi_id_index');

        DB::connection('pgsql')->statement('alter table kpis drop constraint if exists kpis_weightage_check');
        DB::connection('pgsql')->statement('alter table kpis drop constraint if exists kpis_measurement_direction_check');
        DB::connection('pgsql')->statement('alter table kpis drop constraint if exists kpis_measurement_unit_check');

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpis
                drop column if exists weightage,
                drop column if exists stretch_target,
                drop column if exists measurement_direction,
                drop column if exists measurement_unit,
                drop column if exists owner_user_id,
                drop column if exists department_id,
                drop column if exists parent_kpi_id,
                drop column if exists company_goal_id
        SQL);

        DB::connection('pgsql')->statement('drop table if exists company_goals');
    }
};
