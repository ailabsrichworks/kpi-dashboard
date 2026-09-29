<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Written retroactively, same reason and same rule as
 * 2026_08_12_000000_create_platform_foundation_schema's own docblock: a
 * table-by-table audit of the live project (tqhfzsjwxypbkcpilkzi, requested
 * as "make sure table in supabase [and] migrate are same") found
 * `quarterly_appraisals` — RLS enabled, real FKs to companies/users, a real
 * restrict_quarterly_appraisal_update() trigger, real indexes, real
 * authenticated/anon/service_role grants — live in production with **no
 * migration file anywhere in this repo creating it**. Diffed systematically:
 * every other live table (24 total) has a matching `Schema::create` in an
 * existing migration; this was the only one that didn't. No application code
 * reads or writes it either (a full grep of `app/` for `quarterly_appraisals`
 * returns nothing) — it's schema for the "Q1-Q4 Evaluation" / appraisal
 * feature that PlaceholderController::performanceEvaluation() still serves as
 * an honest "Not built yet" page, built ahead of that feature the same way
 * `reports`/`audit_logs` were in the original foundational schema, but never
 * given its own migration file the way those two were.
 *
 * DO NOT run `up()` against the existing production database — the table
 * already exists there. Mark it applied the same way the foundational schema
 * migration documents, by inserting straight into the local tracking table:
 *
 *   insert into migrations (migration, batch)
 *   values ('2026_09_29_000000_document_quarterly_appraisals_table', 13);
 *
 * `up()` is for provisioning a genuinely fresh environment (new client
 * deploy, disaster-recovery restore, a new Supabase project seeded from
 * scratch) where this table doesn't exist yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('quarterly_appraisals', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->text('financial_year');
            $table->text('quarter');
            $table->text('status')->default('awaiting_appraisal');
            $table->text('self_assessment_note')->nullable();
            $table->timestampTz('self_submitted_at')->default(DB::raw('now()'));
            $table->decimal('attitude_score')->nullable();
            $table->text('attitude_note')->nullable();
            $table->uuid('appraised_by')->nullable();
            $table->timestampTz('appraised_at')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('appraised_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['user_id', 'financial_year', 'quarter']);
            $table->index('company_id');
            $table->index('user_id');
        });

        DB::connection('pgsql')->statement("alter table quarterly_appraisals add constraint quarterly_appraisals_quarter_check check (quarter = any (array['Q1','Q2','Q3','Q4']))");
        DB::connection('pgsql')->statement("alter table quarterly_appraisals add constraint quarterly_appraisals_status_check check (status = any (array['awaiting_appraisal','completed']))");

        DB::connection('pgsql')->statement('alter table quarterly_appraisals enable row level security');

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy quarterly_appraisals_select on quarterly_appraisals for select
              using (
                auth_is_richworks_super_admin()
                or user_id = auth_current_user_id()
                or auth_can_view_company_wide(company_id)
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy quarterly_appraisals_insert on quarterly_appraisals for insert
              with check (
                user_id = auth_current_user_id()
                and company_id in (select auth_company_ids())
                and status = 'awaiting_appraisal'
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy quarterly_appraisals_update on quarterly_appraisals for update
              using (
                auth_is_richworks_super_admin()
                or user_id = auth_current_user_id()
                or auth_can_view_company_wide(company_id)
              )
              with check (
                auth_is_richworks_super_admin()
                or user_id = auth_current_user_id()
                or auth_can_view_company_wide(company_id)
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function restrict_quarterly_appraisal_update()
            returns trigger
            language plpgsql
            security definer
            set search_path to 'public'
            as $function$
                begin
                  if new.self_assessment_note is distinct from old.self_assessment_note
                    or new.self_submitted_at is distinct from old.self_submitted_at
                  then
                    if new.user_id <> auth_current_user_id() then
                      raise exception 'Only the employee being appraised may edit their own self-assessment.';
                    end if;
                    if old.status = 'completed' then
                      raise exception 'This appraisal has already been completed and can no longer be edited.';
                    end if;
                  end if;

                  if new.attitude_score is distinct from old.attitude_score
                    or new.attitude_note is distinct from old.attitude_note
                    or new.appraised_by is distinct from old.appraised_by
                    or new.appraised_at is distinct from old.appraised_at
                    or new.status is distinct from old.status
                  then
                    if not auth_can_view_company_wide(new.company_id) then
                      raise exception 'Only your appraiser may set the attitude score and mark this appraisal complete.';
                    end if;
                    if old.status = 'completed' then
                      raise exception 'This appraisal has already been completed and cannot be re-scored.';
                    end if;
                  end if;

                  new.updated_at = now();
                  return new;
                end;
            $function$
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_restrict_quarterly_appraisal_update
              before update on quarterly_appraisals
              for each row execute function restrict_quarterly_appraisal_update()
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_company_id_change
              before update on quarterly_appraisals
              for each row execute function prevent_company_id_change()
        SQL);

        foreach (['anon', 'authenticated', 'service_role'] as $role) {
            DB::connection('pgsql')->statement("grant select, insert, update, delete on quarterly_appraisals to {$role}");
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('quarterly_appraisals');
    }
};
