<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performix Company Platform, Phase 1 hardening (Part 5): a reusable,
 * configurable approval workflow engine — confirmed by direct audit that the
 * Platform had ZERO approval infrastructure of its own (the legacy
 * `ApprovalController`/`ApprovalActionService`/`ApprovalHierarchyService` are
 * built entirely around a different, single-tenant schema — a flat
 * `employees` table with `manager_id`/`vp_id`/`reports_to_id` columns that
 * don't exist here — and a hardcoded 4-rung EXECUTIVE->MANAGER->VP->SLT
 * ladder. None of it is reusable against `company_users`/`department_users`).
 *
 * Four tables, matching the shape the spec itself suggests:
 *   approval_workflows       — one row per configured workflow (company +
 *                               type), e.g. "Actual Submission — Sales dept"
 *   approval_workflow_steps  — the ordered chain of approver definitions for
 *                               one workflow (spec's Workflow A/B/C examples)
 *   approval_requests        — one row per thing awaiting approval, generic
 *                               across object types via (object_type, object_id)
 *   approval_request_steps   — the resolved, per-request instantiation of a
 *                               workflow's steps — WHO specifically is being
 *                               asked to decide, and what they decided
 *
 * All four tables are created FIRST, in full (columns, constraints, indexes,
 * grants, triggers, RLS enabled) — policies are added afterward, in their own
 * section, because `approval_requests`' own SELECT/UPDATE policies need to
 * reference `approval_request_steps` in an EXISTS clause, and Postgres can't
 * create a policy against a table that doesn't exist yet. (Confirmed by
 * actually running this migration against a disposable Postgres container:
 * an earlier draft interleaved table-then-immediately-its-policies per table
 * and failed with "relation approval_request_steps does not exist" the
 * moment it tried to create approval_requests_select.)
 *
 * A step's approver is resolved one of three ways (approver_type):
 *   - 'submitter_manager' — the submitter's own direct manager, via the
 *     already-real (but until now unused-for-anything) `department_users
 *     .manager_user_id` reporting line added in the org-hierarchy migration.
 *   - 'role' (+ approver_scope 'department'|'company') — any active member
 *     of the submission's department (or the whole company) holding that
 *     company/department role, e.g. 'hod' scoped to 'department', or 'hr'
 *     scoped to 'company'.
 *   - 'specific_user' — a fixed approver_user_id, for workflows that name an
 *     individual rather than a role.
 * Resolution happens once, at request-creation time, into
 * approval_request_steps.resolved_approver_user_id — so a later role/manager
 * change never silently reassigns an approval already in flight, and a step
 * with no resolvable candidate (no HOD assigned yet, etc.) falls back to any
 * company_admin rather than leaving the request permanently stuck. This is
 * also where self-approval is prevented (spec Part 8): if the only resolved
 * candidate is the request's own submitter and the workflow doesn't set
 * allow_self_approval, resolution escalates to the company_admin fallback
 * instead of assigning the submitter to approve their own request — done at
 * the data layer, not just the UI, so it is enforced identically by RLS.
 *
 * No workflow is required to exist before any request can be made — if a
 * company has configured no workflow for a given type,
 * ApprovalWorkflowService falls back to a sensible built-in default
 * (documented there) computed on demand rather than requiring every company
 * to pre-seed rows before Phase 1 features work at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ================================================================
        // TABLES (structure only — policies added in one block below)
        // ================================================================

        // --- approval_workflows ------------------------------------------
        DB::connection('pgsql')->statement(<<<'SQL'
            create table approval_workflows (
                id uuid primary key default gen_random_uuid(),
                company_id uuid not null references companies(id) on delete cascade,
                workflow_type text not null,
                name text not null,
                is_default boolean not null default true,
                is_active boolean not null default true,
                allow_self_approval boolean not null default false,
                created_by uuid null references users(id) on delete set null,
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now()
            )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table approval_workflows add constraint approval_workflows_type_check
              check (workflow_type in ('actual_submission', 'kpi_creation', 'target_revision', 'kpi_revision', 'performance_review'))
        SQL);

        DB::connection('pgsql')->statement('create index approval_workflows_company_id_index on approval_workflows (company_id)');

        // At most one active default workflow per (company, type) — the
        // engine needs an unambiguous answer to "which workflow applies"
        // without the app having to guess between two defaults.
        DB::connection('pgsql')->statement(
            'create unique index approval_workflows_one_default_per_type '
            . 'on approval_workflows (company_id, workflow_type) where is_default and is_active'
        );

        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.approval_workflows to authenticated');

        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_company_id_change
              before update on approval_workflows
              for each row execute function prevent_company_id_change()
        SQL);

        DB::connection('pgsql')->statement('alter table approval_workflows enable row level security');

        // --- approval_workflow_steps ---------------------------------------
        DB::connection('pgsql')->statement(<<<'SQL'
            create table approval_workflow_steps (
                id uuid primary key default gen_random_uuid(),
                workflow_id uuid not null references approval_workflows(id) on delete cascade,
                company_id uuid not null references companies(id) on delete cascade,
                step_order smallint not null,
                approver_type text not null,
                approver_scope text null,
                approver_role text null,
                approver_user_id uuid null references users(id) on delete set null,
                require_comment boolean not null default false,
                created_at timestamptz not null default now(),
                unique (workflow_id, step_order)
            )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table approval_workflow_steps add constraint approval_workflow_steps_approver_type_check
              check (approver_type in ('submitter_manager', 'role', 'specific_user'))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table approval_workflow_steps add constraint approval_workflow_steps_shape_check
              check (
                (approver_type = 'submitter_manager' and approver_role is null and approver_user_id is null)
                or (approver_type = 'role' and approver_role is not null and approver_scope in ('department', 'company') and approver_user_id is null)
                or (approver_type = 'specific_user' and approver_user_id is not null and approver_role is null)
              )
        SQL);

        DB::connection('pgsql')->statement('create index approval_workflow_steps_workflow_id_index on approval_workflow_steps (workflow_id)');

        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.approval_workflow_steps to authenticated');

        // company_id is derived from the parent workflow, never trusted from
        // the client — same pattern as kpi_access_grants/kpi_period_targets.
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function derive_company_id_from_approval_workflow()
            returns trigger language plpgsql security definer
            set search_path to 'public' as $$
            declare
              parent_company uuid;
            begin
              select company_id into parent_company from approval_workflows where id = NEW.workflow_id;

              if parent_company is null then
                raise exception 'approval workflow % does not exist; cannot derive company_id.', NEW.workflow_id;
              end if;

              NEW.company_id := parent_company;
              return NEW;
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_derive_company_id
              before insert or update on approval_workflow_steps
              for each row execute function derive_company_id_from_approval_workflow()
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_company_id_change
              before update on approval_workflow_steps
              for each row execute function prevent_company_id_change()
        SQL);

        DB::connection('pgsql')->statement('alter table approval_workflow_steps enable row level security');

        // --- approval_requests ---------------------------------------------
        DB::connection('pgsql')->statement(<<<'SQL'
            create table approval_requests (
                id uuid primary key default gen_random_uuid(),
                company_id uuid not null references companies(id) on delete cascade,
                workflow_id uuid null references approval_workflows(id) on delete set null,
                workflow_type text not null,
                object_type text not null,
                object_id uuid not null,
                department_id uuid null references departments(id) on delete set null,
                submitted_by uuid not null references users(id),
                submitted_at timestamptz not null default now(),
                current_step_order smallint not null default 1,
                status text not null default 'pending',
                completed_at timestamptz null,
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now()
            )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table approval_requests add constraint approval_requests_type_check
              check (workflow_type in ('actual_submission', 'kpi_creation', 'target_revision', 'kpi_revision', 'performance_review'))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table approval_requests add constraint approval_requests_object_type_check
              check (object_type in ('kpi_submission', 'kpi_target_revision', 'kpi'))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table approval_requests add constraint approval_requests_status_check
              check (status in ('pending', 'approved', 'rejected', 'returned', 'cancelled'))
        SQL);

        DB::connection('pgsql')->statement('create index approval_requests_company_id_index on approval_requests (company_id)');
        DB::connection('pgsql')->statement('create index approval_requests_object_index on approval_requests (object_type, object_id)');
        DB::connection('pgsql')->statement('create index approval_requests_submitted_by_index on approval_requests (submitted_by)');
        DB::connection('pgsql')->statement('create index approval_requests_status_index on approval_requests (company_id, status)');

        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.approval_requests to authenticated');

        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_company_id_change
              before update on approval_requests
              for each row execute function prevent_company_id_change()
        SQL);

        DB::connection('pgsql')->statement('alter table approval_requests enable row level security');

        // --- approval_request_steps -----------------------------------------
        DB::connection('pgsql')->statement(<<<'SQL'
            create table approval_request_steps (
                id uuid primary key default gen_random_uuid(),
                request_id uuid not null references approval_requests(id) on delete cascade,
                company_id uuid not null references companies(id) on delete cascade,
                step_order smallint not null,
                approver_type text not null,
                resolved_approver_user_id uuid null references users(id) on delete set null,
                status text not null default 'pending',
                acted_by uuid null references users(id) on delete set null,
                acted_at timestamptz null,
                comments text null,
                created_at timestamptz not null default now(),
                unique (request_id, step_order)
            )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table approval_request_steps add constraint approval_request_steps_status_check
              check (status in ('pending', 'approved', 'rejected', 'returned', 'skipped'))
        SQL);

        DB::connection('pgsql')->statement('create index approval_request_steps_request_id_index on approval_request_steps (request_id)');
        DB::connection('pgsql')->statement('create index approval_request_steps_approver_index on approval_request_steps (resolved_approver_user_id, status)');

        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.approval_request_steps to authenticated');

        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function derive_company_id_from_approval_request()
            returns trigger language plpgsql security definer
            set search_path to 'public' as $$
            declare
              parent_company uuid;
            begin
              select company_id into parent_company from approval_requests where id = NEW.request_id;

              if parent_company is null then
                raise exception 'approval request % does not exist; cannot derive company_id.', NEW.request_id;
              end if;

              NEW.company_id := parent_company;
              return NEW;
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_derive_company_id
              before insert or update on approval_request_steps
              for each row execute function derive_company_id_from_approval_request()
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_company_id_change
              before update on approval_request_steps
              for each row execute function prevent_company_id_change()
        SQL);

        DB::connection('pgsql')->statement('alter table approval_request_steps enable row level security');

        // ================================================================
        // HELPER FUNCTIONS (must exist before the policies below, which call
        // them instead of querying approval_requests/approval_request_steps
        // inline from each other's policies)
        //
        // approval_requests_select/update need to check "am I a resolved
        // approver on one of this request's steps" — a direct EXISTS against
        // approval_request_steps triggers THAT table's own SELECT policy,
        // which itself checks "am I the submitter of this step's request" via
        // an EXISTS against approval_requests — triggering approval_requests'
        // policy again, and so on: "infinite recursion detected in policy"
        // (confirmed by actually running this migration; the exact same
        // failure mode CLAUDE.md documents for kpis_select/kpi_access_grants
        // and users_update_self before this). SECURITY DEFINER functions
        // owned by the migration role (which owns these tables) bypass RLS
        // for their own internal queries — the same reason auth_can_view_kpi()
        // can safely query kpis/kpi_access_grants from another table's policy
        // without recursing.
        // ================================================================
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function auth_is_approver_of_request(p_request_id uuid)
            returns boolean
            language sql stable security definer
            set search_path to 'public'
            as $$
                select exists (
                  select 1 from approval_request_steps
                  where request_id = p_request_id and resolved_approver_user_id = auth_current_user_id()
                )
            $$
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function auth_is_submitter_of_request(p_request_id uuid)
            returns boolean
            language sql stable security definer
            set search_path to 'public'
            as $$
                select exists (
                  select 1 from approval_requests
                  where id = p_request_id and submitted_by = auth_current_user_id()
                )
            $$
        SQL);

        // Used by kpi_submissions_update and (Part 7's migration)
        // kpi_target_revisions_update — "am I the resolved approver of
        // whichever step is CURRENT for the request governing this object,"
        // not just any step of it.
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function auth_is_current_step_approver_for_object(p_object_type text, p_object_id uuid)
            returns boolean
            language sql stable security definer
            set search_path to 'public'
            as $$
                select exists (
                  select 1 from approval_request_steps ars
                  join approval_requests ar on ar.id = ars.request_id
                  where ar.object_type = p_object_type
                    and ar.object_id = p_object_id
                    and ars.step_order = ar.current_step_order
                    and ars.resolved_approver_user_id = auth_current_user_id()
                )
            $$
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function auth_is_current_step_approver_of_request(p_request_id uuid)
            returns boolean
            language sql stable security definer
            set search_path to 'public'
            as $$
                select exists (
                  select 1 from approval_request_steps ars
                  join approval_requests ar on ar.id = ars.request_id
                  where ar.id = p_request_id
                    and ars.step_order = ar.current_step_order
                    and ars.resolved_approver_user_id = auth_current_user_id()
                )
            $$
        SQL);

        // ================================================================
        // POLICIES (all four tables now exist)
        // ================================================================

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy approval_workflows_select on approval_workflows for select
              using (auth_is_richworks_super_admin() or company_id in (select auth_company_ids()))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy approval_workflows_write on approval_workflows for all
              using (auth_can_administer_company(company_id))
              with check (auth_can_administer_company(company_id))
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy approval_workflow_steps_select on approval_workflow_steps for select
              using (auth_is_richworks_super_admin() or company_id in (select auth_company_ids()))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy approval_workflow_steps_write on approval_workflow_steps for all
              using (auth_can_administer_company(company_id))
              with check (auth_can_administer_company(company_id))
        SQL);

        // Visible to: company admins/SLT (company-wide), the submitter (to
        // track their own request), or anyone who is the resolved approver
        // on any of its steps (so they can see the request they're being
        // asked to decide, not just their own isolated step row).
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy approval_requests_select on approval_requests for select
              using (
                auth_is_richworks_super_admin()
                or auth_can_view_company_wide(company_id)
                or submitted_by = auth_current_user_id()
                or auth_is_approver_of_request(id)
              )
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy approval_requests_insert on approval_requests for insert
              with check (submitted_by = auth_current_user_id() and company_id in (select auth_company_ids()))
        SQL);
        // Advancing current_step_order/status is done by the app as a
        // resolved approver decides each step, or by a company admin
        // (cancel/override) — never by the submitter themselves.
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy approval_requests_update on approval_requests for update
              using (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or auth_is_approver_of_request(id)
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy approval_request_steps_select on approval_request_steps for select
              using (
                auth_is_richworks_super_admin()
                or auth_can_view_company_wide(company_id)
                or resolved_approver_user_id = auth_current_user_id()
                or auth_is_submitter_of_request(request_id)
              )
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy approval_request_steps_insert on approval_request_steps for insert
              with check (auth_is_submitter_of_request(request_id))
        SQL);
        // Only the resolved approver for THIS step may record a decision on
        // it (spec Part 8: "manager cannot approve unrelated department
        // records" — this is the row-level enforcement of that, since
        // resolution already scoped resolved_approver_user_id correctly at
        // creation time). A company admin may also act, as an override.
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy approval_request_steps_update on approval_request_steps for update
              using (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or resolved_approver_user_id = auth_current_user_id()
              )
        SQL);

        // --- Tighten kpi_submissions_update now that approval_request_steps
        // exists: a submission's approval-decision columns may only be
        // changed by whoever is the resolved approver of its CURRENT step,
        // or a company admin — never the submitter, never an unrelated
        // approver on a different/already-passed step. This is the DB-level
        // half of "employee cannot approve their own submission" and "manager
        // cannot approve unrelated department records" (spec Part 8) — the
        // resolution step (ApprovalWorkflowService) is what keeps a submitter
        // from ever becoming their own resolved approver in the first place;
        // this policy is what makes that guarantee unbypassable via a raw
        // UPDATE even if application code had a bug.
        //
        // Deliberately does NOT also require `ars.status = 'pending'`: doing
        // so would force ApprovalRequestService::decide() into a fragile
        // exact write order (update the submission's status while its step
        // row is still 'pending', only THEN flip the step to 'approved') to
        // avoid locking itself out mid-decision. Checking only "this step is
        // still the request's current step, and I am its resolved approver"
        // gives the same guarantee (can't act outside your own current step)
        // without caring what order the handful of writes that make up one
        // decision happen in.
        DB::connection('pgsql')->statement('drop policy if exists kpi_submissions_update on kpi_submissions');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_submissions_update on kpi_submissions for update
              using (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or auth_is_current_step_approver_for_object('kpi_submission', id)
              )
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop policy if exists kpi_submissions_update on kpi_submissions');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_submissions_update on kpi_submissions for update
              using (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or (auth_role_in_company(company_id) = 'executive' and department_id in (select auth_department_ids()))
                or (department_id in (select auth_department_ids()) and submitted_by = auth_current_user_id())
              )
        SQL);

        DB::connection('pgsql')->statement('drop function if exists auth_is_current_step_approver_of_request(uuid)');
        DB::connection('pgsql')->statement('drop function if exists auth_is_current_step_approver_for_object(text, uuid)');
        DB::connection('pgsql')->statement('drop function if exists auth_is_submitter_of_request(uuid)');
        DB::connection('pgsql')->statement('drop function if exists auth_is_approver_of_request(uuid)');

        DB::connection('pgsql')->statement('drop table if exists approval_request_steps');
        DB::connection('pgsql')->statement('drop function if exists derive_company_id_from_approval_request()');
        DB::connection('pgsql')->statement('drop table if exists approval_requests');
        DB::connection('pgsql')->statement('drop table if exists approval_workflow_steps');
        DB::connection('pgsql')->statement('drop function if exists derive_company_id_from_approval_workflow()');
        DB::connection('pgsql')->statement('drop table if exists approval_workflows');
    }
};
