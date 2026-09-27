<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performix Company Platform, Phase 1 hardening (Part 7): target-revision
 * governance. A target change must not silently alter performance history —
 * `kpis.target` stays the single "currently approved" value used by every
 * calculation (`KpiCalculationService`, dashboards, ANIRA) at all times; a
 * *proposed* new target lives only in this table until an approval decides
 * it, at which point it's applied via `apply_approved_target_revision()`
 * (below), never by the requester writing to `kpis.target` directly.
 *
 * `apply_approved_target_revision` is a SECURITY DEFINER function rather
 * than relying on the caller's own `kpis_update` grant, because the person
 * deciding a target-revision approval step (e.g. a department HOD) may not
 * themselves be a company_admin and so has no direct UPDATE right on `kpis`
 * — the function re-checks authorization itself (company admin, or the
 * actual resolved+approved approver of this revision's request) before
 * touching `kpis.target`, so this is a narrow, audited privilege escalation
 * for one specific, already-approved row — not a general bypass.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            create table kpi_target_revisions (
                id uuid primary key default gen_random_uuid(),
                company_id uuid not null references companies(id) on delete cascade,
                kpi_id uuid not null references kpis(id) on delete cascade,
                old_target numeric null,
                new_target numeric not null,
                reason text not null,
                requested_by uuid not null references users(id),
                requested_at timestamptz not null default now(),
                effective_financial_year integer not null,
                approval_request_id uuid null,
                status text not null default 'pending',
                decided_by uuid null references users(id) on delete set null,
                decided_at timestamptz null,
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now()
            )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpi_target_revisions add constraint kpi_target_revisions_status_check
              check (status in ('pending', 'approved', 'rejected'))
        SQL);

        DB::connection('pgsql')->statement('create index kpi_target_revisions_company_id_index on kpi_target_revisions (company_id)');
        DB::connection('pgsql')->statement('create index kpi_target_revisions_kpi_id_index on kpi_target_revisions (kpi_id, status)');

        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.kpi_target_revisions to authenticated');

        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_derive_company_id
              before insert or update on kpi_target_revisions
              for each row execute function derive_company_id_from_kpi()
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_company_id_change
              before update on kpi_target_revisions
              for each row execute function prevent_company_id_change()
        SQL);

        // The proposed value/reason/effective-year, once submitted, can never
        // be silently edited — only the decision columns may change, via the
        // same narrower approval-decision path everything else in this
        // engine uses. Mirrors prevent_kpi_submission_value_change().
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function prevent_kpi_target_revision_value_change()
            returns trigger language plpgsql as $$
            begin
              if NEW.new_target is distinct from OLD.new_target
                or NEW.old_target is distinct from OLD.old_target
                or NEW.kpi_id is distinct from OLD.kpi_id
                or NEW.requested_by is distinct from OLD.requested_by
                or NEW.effective_financial_year is distinct from OLD.effective_financial_year
              then
                raise exception 'A submitted target revision cannot be edited — cancel and submit a new one instead.';
              end if;

              return NEW;
            end;
            $$
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_kpi_target_revision_value_change
              before update on kpi_target_revisions
              for each row execute function prevent_kpi_target_revision_value_change()
        SQL);

        DB::connection('pgsql')->statement('alter table kpi_target_revisions enable row level security');

        // `auth_is_approver_of_request()` (SECURITY DEFINER, defined in the
        // approval-engine migration this one runs after) rather than an
        // inline EXISTS against approval_request_steps — a direct EXISTS here
        // would trigger that table's own SELECT policy, which queries back
        // into approval_requests, whose own SELECT policy queries back into
        // approval_request_steps again: "infinite recursion detected in
        // policy" (confirmed by actually running this against a disposable
        // Postgres container — the same failure mode already fixed for
        // kpi_submissions_update in the approval-engine migration).
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_target_revisions_select on kpi_target_revisions for select
              using (
                auth_is_richworks_super_admin()
                or auth_can_view_company_wide(company_id)
                or requested_by = auth_current_user_id()
                or (approval_request_id is not null and auth_is_approver_of_request(approval_request_id))
              )
        SQL);
        // Requesting a target revision requires being able to administer the
        // company or being the KPI's own designated owner — an ordinary
        // employee cannot propose changing a KPI target they merely report
        // actuals against.
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_target_revisions_insert on kpi_target_revisions for insert
              with check (
                requested_by = auth_current_user_id()
                and (
                  auth_can_administer_company(company_id)
                  or exists (select 1 from kpis k where k.id = kpi_id and k.owner_user_id = auth_current_user_id())
                )
              )
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_target_revisions_update on kpi_target_revisions for update
              using (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or (approval_request_id is not null and auth_is_current_step_approver_of_request(approval_request_id))
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function apply_approved_target_revision(revision_id uuid)
            returns void language plpgsql security definer
            set search_path to 'public' as $$
            declare
              rev record;
              authorized boolean;
            begin
              select * into rev from kpi_target_revisions where id = revision_id;

              if rev.id is null then
                raise exception 'target revision % not found', revision_id;
              end if;

              if rev.status <> 'approved' then
                raise exception 'target revision % is not approved', revision_id;
              end if;

              -- coalesce(...) each branch to false explicitly: auth_role_in_company()
              -- (which auth_can_administer_company() calls) returns a bare
              -- NULL, not false, when the caller has no membership row at
              -- all -- `false or null` is NULL, not false, in SQL's
              -- three-valued logic, and `if not <NULL> then ... end if` in
              -- plpgsql silently DOESN'T raise (NULL is neither true nor
              -- false). An earlier version of this function had exactly that
              -- bug: an unrelated company's admin passed this check because
              -- `authorized` evaluated to NULL instead of false, and
              -- `if not authorized` treated NULL as "don't raise" — caught by
              -- actually running RLS scenario 22b against a real disposable
              -- Postgres, not by re-reading the SQL.
              select (
                coalesce(auth_is_richworks_super_admin(), false)
                or coalesce(auth_can_administer_company(rev.company_id), false)
                or coalesce(exists (
                  select 1 from approval_request_steps ars
                  where ars.request_id = rev.approval_request_id
                    and ars.resolved_approver_user_id = auth_current_user_id()
                    and ars.status = 'approved'
                ), false)
              ) into authorized;

              if not authorized then
                raise exception 'not authorized to apply target revision %', revision_id;
              end if;

              update kpis set target = rev.new_target where id = rev.kpi_id;
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement('grant execute on function apply_approved_target_revision(uuid) to authenticated');

        // Now that approval_requests exists, kpi_submissions can reference it
        // (which approval request, if any, governs this submission's
        // approved/pending state) — nullable, since a company with no active
        // actual_submission workflow may still accept submissions with no
        // request at all (see ApprovalWorkflowService's built-in-default
        // fallback for why a request usually does exist in practice).
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpi_submissions
                add column if not exists approval_request_id uuid null references approval_requests(id) on delete set null
        SQL);
        DB::connection('pgsql')->statement('create index if not exists kpi_submissions_approval_request_id_index on kpi_submissions (approval_request_id)');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop index if exists kpi_submissions_approval_request_id_index');
        DB::connection('pgsql')->statement('alter table kpi_submissions drop column if exists approval_request_id');

        DB::connection('pgsql')->statement('drop function if exists apply_approved_target_revision(uuid)');
        DB::connection('pgsql')->statement('drop table if exists kpi_target_revisions');
        DB::connection('pgsql')->statement('drop function if exists prevent_kpi_target_revision_value_change()');
    }
};
