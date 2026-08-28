<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performix Company Platform, Phase 1 hardening: KPI actual submissions
 * become period-specific and versioned instead of a bare, unstructured
 * insert (confirmed by direct audit: `kpi_submissions` had no `status`, no
 * period columns beyond a raw `submission_date`, and no unique/versioning
 * constraint at all — `KpiSubmissionController::store()` was a plain INSERT
 * every time).
 *
 * Deliberately additive and non-destructive to existing rows: every new
 * column is nullable or defaulted, and no existing column is renamed or
 * dropped. Existing submissions (if any) get `status = 'approved'` by
 * backfill below — the pre-this-migration world had no approval concept at
 * all, so treating history as already-approved is the only backfill that
 * doesn't retroactively invalidate every KPI number the dashboard has ever
 * shown.
 *
 * `financial_year`/`period_type`/`period_number` mirror `kpi_period_targets`'
 * own period shape exactly (FY-relative "month/quarter of FY" numbering, not
 * calendar month) so the two tables can be joined/compared directly. Unlike
 * `kpi_period_targets`, there is NO unique constraint on
 * (kpi_id, financial_year, period_type, period_number) — that's the whole
 * point of `revision_number`: a second submission for the same period is a
 * new, versioned revision, not an edit, so multiple rows per period are
 * expected and preserved, never overwritten.
 *
 * Evidence is a plain text field (a URL/description), not a file upload —
 * there is no file-storage mechanism anywhere in the Platform to build on
 * (confirmed by full-codebase audit: the only "proof file" feature in this
 * repo is the unrelated, legacy Tasks-completion flow). Building real file
 * upload infrastructure is out of scope for this pass; noted as a Phase 2
 * follow-up rather than silently skipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpi_submissions
                add column if not exists status text not null default 'pending_review',
                add column if not exists revision_number integer not null default 1,
                add column if not exists financial_year integer null,
                add column if not exists period_type text null,
                add column if not exists period_number smallint null,
                add column if not exists evidence_note text null,
                add column if not exists decided_by uuid null,
                add column if not exists decided_at timestamptz null,
                add column if not exists decision_comments text null
        SQL);

        // Pre-existing rows (from before this migration, if any) predate any
        // approval concept — treating them as already-decided/approved is
        // the only backfill that doesn't retroactively hide real historical
        // performance data behind a "pending" state nobody will ever act on.
        DB::connection('pgsql')->statement(<<<'SQL'
            update kpi_submissions set status = 'approved' where status = 'pending_review'
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpi_submissions add constraint kpi_submissions_status_check
              check (status in ('pending_review', 'approved', 'rejected', 'returned'))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpi_submissions add constraint kpi_submissions_period_type_check
              check (period_type is null or period_type in ('quarter', 'month'))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpi_submissions add constraint kpi_submissions_period_number_check
              check (
                period_type is null
                or (period_type = 'quarter' and period_number between 1 and 4)
                or (period_type = 'month' and period_number between 1 and 12)
              )
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpi_submissions
                add constraint kpi_submissions_decided_by_fkey
                foreign key (decided_by) references users(id) on delete set null
        SQL);

        DB::connection('pgsql')->statement(
            'create index if not exists kpi_submissions_period_index '
            . 'on kpi_submissions (kpi_id, financial_year, period_type, period_number)'
        );
        DB::connection('pgsql')->statement(
            'create index if not exists kpi_submissions_status_index on kpi_submissions (kpi_id, status)'
        );

        // Immutability: once inserted, a submission's substantive facts
        // (what was submitted, for which period, by whom) can never change —
        // only the approval-decision columns may, and only via the narrower
        // kpi_submissions_update policy the approval-engine migration adds
        // right after this one. This is what makes "preserve submission
        // history, never overwrite an actual" a database guarantee rather
        // than an application convention someone could bypass with a raw
        // UPDATE.
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function prevent_kpi_submission_value_change()
            returns trigger language plpgsql as $$
            begin
              if NEW.value is distinct from OLD.value
                or NEW.submission_date is distinct from OLD.submission_date
                or NEW.financial_year is distinct from OLD.financial_year
                or NEW.period_type is distinct from OLD.period_type
                or NEW.period_number is distinct from OLD.period_number
                or NEW.revision_number is distinct from OLD.revision_number
                or NEW.submitted_by is distinct from OLD.submitted_by
              then
                raise exception 'A submitted actual value cannot be edited — submit a new revision instead.';
              end if;

              return NEW;
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement('drop trigger if exists trg_prevent_kpi_submission_value_change on kpi_submissions');
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_kpi_submission_value_change
              before update on kpi_submissions
              for each row execute function prevent_kpi_submission_value_change()
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop trigger if exists trg_prevent_kpi_submission_value_change on kpi_submissions');
        DB::connection('pgsql')->statement('drop function if exists prevent_kpi_submission_value_change()');

        DB::connection('pgsql')->statement('drop index if exists kpi_submissions_status_index');
        DB::connection('pgsql')->statement('drop index if exists kpi_submissions_period_index');

        DB::connection('pgsql')->statement('alter table kpi_submissions drop constraint if exists kpi_submissions_decided_by_fkey');
        DB::connection('pgsql')->statement('alter table kpi_submissions drop constraint if exists kpi_submissions_period_number_check');
        DB::connection('pgsql')->statement('alter table kpi_submissions drop constraint if exists kpi_submissions_period_type_check');
        DB::connection('pgsql')->statement('alter table kpi_submissions drop constraint if exists kpi_submissions_status_check');

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table kpi_submissions
                drop column if exists decision_comments,
                drop column if exists decided_at,
                drop column if exists decided_by,
                drop column if exists evidence_note,
                drop column if exists period_number,
                drop column if exists period_type,
                drop column if exists financial_year,
                drop column if exists revision_number,
                drop column if exists status
        SQL);
    }
};
