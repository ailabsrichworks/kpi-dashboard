<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Appraiser scoring: once a KPI submission is approved, the submitter's
 * manager (department_users.manager_user_id — resolved once, the same
 * "submitter_manager" lookup ApprovalWorkflowService already uses for
 * approval steps) may give it a numeric score plus an optional justification
 * comment, visible read-only to the submitter. One row per submission —
 * there is no re-scoring path, matching kpi_submissions' own "never edit,
 * only ever create" posture.
 *
 * `resolved_appraiser_user_id` is frozen by its own dedicated trigger
 * (prevent_appraiser_reassignment) rather than left to application
 * convention — approval_request_steps.resolved_approver_user_id has no such
 * DB-level guarantee (only an app-level one), which is a real, documented
 * gap; this table closes it, since "the appraiser identity must never
 * change later" is this feature's own explicit requirement.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            create table kpi_submission_scores (
                id uuid primary key default gen_random_uuid(),
                company_id uuid not null references companies(id) on delete cascade,
                kpi_submission_id uuid not null unique references kpi_submissions(id) on delete cascade,
                resolved_appraiser_user_id uuid null references users(id) on delete set null,
                score numeric(3,1) not null,
                comment text null,
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now()
            )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpi_submission_scores add constraint kpi_submission_scores_score_check
              check (score >= 0 and score <= 5)
        SQL);

        DB::connection('pgsql')->statement('create index kpi_submission_scores_company_id_index on kpi_submission_scores (company_id)');
        DB::connection('pgsql')->statement('create index kpi_submission_scores_appraiser_id_index on kpi_submission_scores (resolved_appraiser_user_id)');

        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.kpi_submission_scores to authenticated');

        // company_id is derived server-side from the parent submission, never
        // trusted from the client — same derive-then-freeze pair every
        // tenant table uses (see derive_company_id_from_approval_request()).
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function derive_company_id_from_kpi_submission()
            returns trigger language plpgsql security definer
            set search_path to 'public' as $$
            declare parent_company uuid;
            begin
              select company_id into parent_company from kpi_submissions where id = NEW.kpi_submission_id;

              if parent_company is null then
                raise exception 'kpi submission % does not exist; cannot derive company_id.', NEW.kpi_submission_id;
              end if;

              NEW.company_id := parent_company;
              return NEW;
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_derive_company_id
              before insert on kpi_submission_scores
              for each row execute function derive_company_id_from_kpi_submission()
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_company_id_change
              before update on kpi_submission_scores
              for each row execute function prevent_company_id_change()
        SQL);

        // Improves on approval_request_steps.resolved_approver_user_id, which
        // has no equivalent DB-level freeze — this feature's own requirement
        // is that the scoring appraiser's identity must never change later,
        // so it's enforced here rather than left as an app-level convention.
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function prevent_appraiser_reassignment()
            returns trigger language plpgsql as $$
            begin
              if NEW.resolved_appraiser_user_id is distinct from OLD.resolved_appraiser_user_id then
                raise exception 'The scoring appraiser cannot be changed after the score is given.';
              end if;

              return NEW;
            end;
            $$
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_appraiser_reassignment
              before update on kpi_submission_scores
              for each row execute function prevent_appraiser_reassignment()
        SQL);

        DB::connection('pgsql')->statement('alter table kpi_submission_scores enable row level security');

        // Mirrors auth_is_submitter_of_request() exactly, one level down: lets
        // the original submitter read the score given on their own
        // submission (spec: "the appraisee can see why they got that
        // score") without granting them company-wide visibility.
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function auth_is_submitter_of_kpi_submission(p_submission_id uuid)
            returns boolean
            language sql stable security definer
            set search_path to 'public'
            as $$
                select exists (
                  select 1 from kpi_submissions
                  where id = p_submission_id and submitted_by = auth_current_user_id()
                )
            $$
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_submission_scores_select on kpi_submission_scores for select
              using (
                auth_is_richworks_super_admin()
                or auth_can_view_company_wide(company_id)
                or resolved_appraiser_user_id = auth_current_user_id()
                or auth_is_submitter_of_kpi_submission(kpi_submission_id)
              )
        SQL);

        // Only the resolved appraiser may create the score row, and only for
        // their own company — enforced here as the real boundary; the
        // controller's own "are you actually this submitter's manager" check
        // is a UX nicety (a clean 403 instead of an opaque RLS denial), not
        // the security control.
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_submission_scores_insert on kpi_submission_scores for insert
              with check (
                resolved_appraiser_user_id = auth_current_user_id()
                and company_id in (select auth_company_ids())
              )
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop table if exists kpi_submission_scores');
        DB::connection('pgsql')->statement('drop function if exists auth_is_submitter_of_kpi_submission(uuid)');
        DB::connection('pgsql')->statement('drop function if exists prevent_appraiser_reassignment()');
        DB::connection('pgsql')->statement('drop function if exists derive_company_id_from_kpi_submission()');
    }
};
