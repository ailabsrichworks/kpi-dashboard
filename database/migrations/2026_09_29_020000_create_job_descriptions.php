<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ports legacy's "Job Description" page (JobDescriptionController,
 * resources/views/job-description.blade.php) to the Platform — one of the
 * four confirmed gaps from auditing legacy's full sidebar against what the
 * Platform actually has, alongside Attendance, Performance/Appraisal, and
 * Target Linkages.
 *
 * Two deliberate scope departures from the legacy page, both forced by real,
 * already-documented Platform constraints rather than convenience:
 *
 * 1. No manager-hierarchy sign-off. Legacy notifies `reports_to_id` (the
 *    employee's manager) on submit, and captures three separate e-signatures
 *    (HR / Jobholder / Supervisor) via a canvas signature pad. The Platform
 *    has no manager/reports-to relationship anywhere in its schema — the
 *    exact same gap that blocks Target Linkages, confirmed repeatedly
 *    elsewhere in this codebase's history. Rather than fake a manager chain
 *    that doesn't exist, this uses the one real approver concept the
 *    Platform already has and that every other self-service feature this
 *    session built already uses: the company's own Company Admin reviews and
 *    decides (approved / changes_requested), the same shape as
 *    `kpi_weight_change_requests` and `kpi_quarters`' completion sign-off.
 * 2. No e-signature capture. With no HR/Supervisor role concept to sign as,
 *    a signature canvas would have nobody structurally required to
 *    countersign — UI theater, not a real workflow. Omitted rather than
 *    faked.
 *
 * Everything else — summary / responsibilities / requirements / competencies,
 * draft-then-submit, one row per employee — carries over directly.
 *
 * Write shape mirrors `kpi_quarters`' completion state machine exactly: a
 * single RLS policy widens UPDATE to the owner, and a BEFORE UPDATE trigger
 * (`restrict_job_description_owner_update`) confines that branch to editing
 * content and moving draft/changes_requested -> submitted — never touching
 * `status` beyond that, nor `reviewed_at`/`reviewed_by`/`decision_note`,
 * which only the admin branch may set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('job_descriptions', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->text('summary')->nullable();
            $table->text('responsibilities')->nullable();
            $table->text('requirements')->nullable();
            $table->text('competencies')->nullable();
            $table->text('status')->default('draft');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users');
            $table->unique(['company_id', 'user_id']);
            $table->index('status');
        });

        DB::connection('pgsql')->statement(
            "alter table job_descriptions add constraint job_descriptions_status_check check (status in ('draft','submitted','approved','changes_requested'))"
        );

        DB::connection('pgsql')->statement('alter table job_descriptions enable row level security');

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy job_descriptions_select on job_descriptions for select
              using (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or user_id = auth_current_user_id()
              )
        SQL);

        // The owner must be an ACTIVE member of the company they're
        // inserting into -- `user_id` carries no tenant guarantee of its own
        // (users is a global identity table), same reasoning as the
        // weightage/quarters self-service tables before this one.
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy job_descriptions_insert on job_descriptions for insert
              with check (
                auth_can_administer_company(company_id)
                or (
                  user_id = auth_current_user_id()
                  and exists (
                    select 1 from company_users
                    where company_id = job_descriptions.company_id
                      and user_id = auth_current_user_id()
                      and status = 'active'
                  )
                )
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy job_descriptions_update on job_descriptions for update
              using (auth_can_administer_company(company_id) or user_id = auth_current_user_id())
              with check (auth_can_administer_company(company_id) or user_id = auth_current_user_id())
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function restrict_job_description_owner_update()
            returns trigger
            language plpgsql
            security definer
            set search_path = public
            as $$
            begin
              if auth_is_richworks_super_admin() or auth_can_administer_company(new.company_id) then
                return new;
              end if;

              if not exists (
                select 1 from company_users
                where company_id = new.company_id
                  and user_id = auth_current_user_id()
                  and status = 'active'
              ) then
                raise exception 'You are not an active member of this company.';
              end if;

              if new.user_id is distinct from old.user_id
                or new.company_id is distinct from old.company_id
                or new.reviewed_at is distinct from old.reviewed_at
                or new.reviewed_by is distinct from old.reviewed_by
                or new.decision_note is distinct from old.decision_note
              then
                raise exception 'Only a Company Admin can decide a job description review.';
              end if;

              if old.status not in ('draft', 'changes_requested') then
                raise exception 'This job description is awaiting review -- it can''t be edited until changes are requested.';
              end if;

              if new.status not in ('draft', 'submitted') then
                raise exception 'You may save a draft or submit for review -- nothing else.';
              end if;

              return new;
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement(
            'create trigger trg_restrict_job_description_owner_update before update on job_descriptions for each row execute function restrict_job_description_owner_update()'
        );

        DB::connection('pgsql')->statement(
            'create trigger trg_prevent_company_id_change before update on job_descriptions for each row execute function prevent_company_id_change()'
        );

        DB::connection('pgsql')->statement('grant select, insert, update on job_descriptions to authenticated');
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('job_descriptions');
        DB::connection('pgsql')->statement('drop function if exists restrict_job_description_owner_update()');
    }
};
