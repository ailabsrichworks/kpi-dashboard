<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performix Company Platform, Phase 1 (Foundation): organisation hierarchy
 * and employee-level reporting structure — both real, previously-confirmed
 * gaps (see CLAUDE.md's Configuration Tiering table: "Reporting structure —
 * Missing", and `departments` has always been a flat per-company list with
 * no parent/type).
 *
 * Kept deliberately additive: every new column is nullable or defaulted, so
 * every existing row (and every existing RLS guarantee already verified by
 * `tenant_isolation.sql`) keeps behaving exactly as it does today. No values
 * are renamed — `slt` stays `slt` in storage; only the UI labels it
 * "CEO / Management" — repeating the risky live-rename pattern documented
 * in the Phase 14 migration is not warranted here.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Organisation hierarchy on departments ----------------------
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table departments
                add column if not exists unit_type text not null default 'department',
                add column if not exists parent_department_id uuid null
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table departments
                add constraint departments_unit_type_check
                check (unit_type in ('business_unit', 'branch', 'department', 'team'))
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table departments
                add constraint departments_parent_department_id_fkey
                foreign key (parent_department_id) references departments(id) on delete set null
        SQL);

        DB::connection('pgsql')->statement('create index if not exists departments_parent_department_id_index on departments (parent_department_id)');

        // A department cannot be its own ancestor. Self-parenting and a
        // mismatched-company parent are checked directly; deeper cycles are
        // caught by walking the chain with a bounded loop (mirrors the
        // plpgsql style already used by prevent_zero_company_admins()) — a
        // recursive CTE would work too, but a table this shallow (a handful
        // of organisation levels) doesn't need one, and a loop is easier to
        // reason about inside a trigger.
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function prevent_circular_department_hierarchy()
            returns trigger language plpgsql as $$
            declare
              current_parent uuid;
              parent_company uuid;
              visited uuid[] := array[NEW.id];
              depth integer := 0;
            begin
              if NEW.parent_department_id is null then
                return NEW;
              end if;

              if NEW.parent_department_id = NEW.id then
                raise exception 'A department cannot be its own parent.';
              end if;

              select company_id into parent_company from departments where id = NEW.parent_department_id;

              if parent_company is null then
                raise exception 'Parent department % does not exist.', NEW.parent_department_id;
              end if;

              if parent_company <> NEW.company_id then
                raise exception 'A department''s parent must belong to the same company.';
              end if;

              current_parent := NEW.parent_department_id;

              while current_parent is not null and depth < 100 loop
                if current_parent = any(visited) then
                  raise exception 'This would create a circular organisation hierarchy.';
                end if;

                visited := visited || current_parent;

                select parent_department_id into current_parent from departments where id = current_parent;

                depth := depth + 1;
              end loop;

              return NEW;
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement('drop trigger if exists trg_prevent_circular_department_hierarchy on departments');
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_circular_department_hierarchy
              before insert or update on departments
              for each row execute function prevent_circular_department_hierarchy()
        SQL);

        // --- Employee profile + reporting structure on department_users -
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table department_users
                add column if not exists manager_user_id uuid null,
                add column if not exists position_title text null,
                add column if not exists join_date date null,
                add column if not exists employment_status text not null default 'active',
                add column if not exists employment_type text null,
                add column if not exists employee_grade text null,
                add column if not exists location text null,
                add column if not exists mobile_number text null
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table department_users
                add constraint department_users_manager_user_id_fkey
                foreign key (manager_user_id) references users(id) on delete set null
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table department_users
                add constraint department_users_employment_status_check
                check (employment_status in ('active', 'inactive', 'on_leave', 'resigned', 'terminated', 'archived'))
        SQL);

        DB::connection('pgsql')->statement('create index if not exists department_users_manager_user_id_index on department_users (manager_user_id)');

        // Mirrors the department-hierarchy guard above: no self-management,
        // no cycles. Only follows one department_users row per candidate
        // manager (limit 1) — a person with more than one department
        // membership could in principle have different managers on each,
        // which this loop doesn't fully unwind; acceptable for Phase 1,
        // worth revisiting if multi-department membership turns out to be
        // common in practice.
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function prevent_circular_management_chain()
            returns trigger language plpgsql as $$
            declare
              current_manager uuid;
              visited uuid[] := array[NEW.user_id];
              depth integer := 0;
            begin
              if NEW.manager_user_id is null then
                return NEW;
              end if;

              if NEW.manager_user_id = NEW.user_id then
                raise exception 'An employee cannot be their own manager.';
              end if;

              current_manager := NEW.manager_user_id;

              while current_manager is not null and depth < 100 loop
                if current_manager = any(visited) then
                  raise exception 'This reporting assignment would create a circular management chain.';
                end if;

                visited := visited || current_manager;

                select manager_user_id into current_manager
                  from department_users
                  where user_id = current_manager and manager_user_id is not null
                  limit 1;

                depth := depth + 1;
              end loop;

              return NEW;
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement('drop trigger if exists trg_prevent_circular_management_chain on department_users');
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_circular_management_chain
              before insert or update on department_users
              for each row execute function prevent_circular_management_chain()
        SQL);

        // --- Role model: add hr / hod / manager -------------------------
        // Additive only — no existing value is renamed or reinterpreted.
        DB::connection('pgsql')->statement('alter table company_users drop constraint if exists company_users_role_check');
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table company_users add constraint company_users_role_check
              check (role in ('company_admin', 'slt', 'executive', 'employee', 'hr', 'hod', 'manager'))
        SQL);

        DB::connection('pgsql')->statement('alter table department_users drop constraint if exists department_users_role_check');
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table department_users add constraint department_users_role_check
              check (role in ('executive', 'employee', 'hr', 'hod', 'manager'))
        SQL);

        // --- RLS -------------------------------------------------------
        // auth_department_ids() now returns each directly-accessible
        // department PLUS every department beneath it in the new
        // hierarchy — so someone who belongs to a Business Unit or
        // Department automatically sees its Branches/Teams too, the same
        // way it already worked for a flat, single-level org. This is a
        // membership-based widening, not a role-based one: it applies
        // identically regardless of company_users.role, exactly as the
        // function already did before this migration.
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function auth_department_ids()
            returns setof uuid
            language sql stable security definer
            set search_path to 'public'
            as $$
                with recursive base_departments as (
                    select du.department_id from department_users du
                    join users u on u.id = du.user_id
                    join departments d on d.id = du.department_id
                    join companies c on c.id = d.company_id
                    join company_users cu on cu.user_id = du.user_id and cu.company_id = d.company_id
                    where u.auth_user_id = auth.uid()
                      and cu.status = 'active'
                      and c.status not in ('suspended', 'archived')
                ),
                department_tree as (
                    select department_id from base_departments
                    union
                    select d.id as department_id
                    from departments d
                    join department_tree dt on d.parent_department_id = dt.department_id
                )
                select department_id from department_tree;
            $$
        SQL);

        // department_users_select (the membership+role rows, distinct from
        // departments_select, which was already company-wide via
        // auth_company_ids() and needs no change) gets one more OR branch
        // for 'hr' — deliberately NOT added to the shared
        // auth_can_view_company_wide() function, which kpi_submissions_select
        // and reports_select also use: HR gets organisation/employee
        // visibility, not company-wide KPI visibility.
        DB::connection('pgsql')->statement('drop policy if exists department_users_select on department_users');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy department_users_select on department_users for select
              using (
                auth_is_richworks_super_admin()
                or user_id = auth_current_user_id()
                or department_id in (select auth_department_ids())
                or auth_can_view_company_wide(company_id)
                or auth_role_in_company(company_id) = 'hr'
              )
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop policy if exists department_users_select on department_users');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy department_users_select on department_users for select
              using (
                auth_is_richworks_super_admin()
                or user_id = auth_current_user_id()
                or department_id in (select auth_department_ids())
                or auth_can_view_company_wide(company_id)
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function auth_department_ids()
            returns setof uuid
            language sql stable security definer
            set search_path to 'public'
            as $$
                select du.department_id from department_users du
                join users u on u.id = du.user_id
                join departments d on d.id = du.department_id
                join companies c on c.id = d.company_id
                join company_users cu on cu.user_id = du.user_id and cu.company_id = d.company_id
                where u.auth_user_id = auth.uid()
                  and cu.status = 'active'
                  and c.status not in ('suspended', 'archived');
            $$
        SQL);

        DB::connection('pgsql')->statement('alter table department_users drop constraint if exists department_users_role_check');
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table department_users add constraint department_users_role_check
              check (role in ('executive', 'employee'))
        SQL);

        DB::connection('pgsql')->statement('alter table company_users drop constraint if exists company_users_role_check');
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table company_users add constraint company_users_role_check
              check (role in ('company_admin', 'slt', 'executive', 'employee'))
        SQL);

        DB::connection('pgsql')->statement('drop trigger if exists trg_prevent_circular_management_chain on department_users');
        DB::connection('pgsql')->statement('drop function if exists prevent_circular_management_chain()');

        DB::connection('pgsql')->statement('drop index if exists department_users_manager_user_id_index');
        DB::connection('pgsql')->statement('alter table department_users drop constraint if exists department_users_employment_status_check');
        DB::connection('pgsql')->statement('alter table department_users drop constraint if exists department_users_manager_user_id_fkey');
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table department_users
                drop column if exists mobile_number,
                drop column if exists location,
                drop column if exists employee_grade,
                drop column if exists employment_type,
                drop column if exists employment_status,
                drop column if exists join_date,
                drop column if exists position_title,
                drop column if exists manager_user_id
        SQL);

        DB::connection('pgsql')->statement('drop trigger if exists trg_prevent_circular_department_hierarchy on departments');
        DB::connection('pgsql')->statement('drop function if exists prevent_circular_department_hierarchy()');

        DB::connection('pgsql')->statement('drop index if exists departments_parent_department_id_index');
        DB::connection('pgsql')->statement('alter table departments drop constraint if exists departments_parent_department_id_fkey');
        DB::connection('pgsql')->statement('alter table departments drop constraint if exists departments_unit_type_check');
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table departments
                drop column if exists parent_department_id,
                drop column if exists unit_type
        SQL);
    }
};
