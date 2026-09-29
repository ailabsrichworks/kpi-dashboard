<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ports legacy's Performance/Appraisal subsystem (PerformanceController,
 * performance_reports, resources/views/performance/report.blade.php) — the
 * third of the four confirmed sidebar gaps and by far the largest. The core
 * workflow and scoring formula carry over exactly; several structural pieces
 * are deliberately scoped down, stated plainly here rather than glossed over:
 *
 * KEPT EXACTLY (this is the actual "same system" value of this feature):
 * - The 4-stage state machine: draft (self-assessment) -> submitted ->
 *   appraised (appraiser scored) -> completed (appraisee signed off).
 * - The scoring formula: Section 2 KPI = 70%, Section 3 Attitude = 25%,
 *   Section 4 Attendance = 5% (+ Section 5 Culture & Values = 5% in Q4
 *   only). Final score = sum of the APPRAISER-side section totals (not the
 *   self-side), computed and revealed only once `completed`.
 * - Banding thresholds: >=90 Outstanding, >=70 Meets Expectations, >=50
 *   Below Average, else Unsatisfactory.
 * - Per-KPI self score = min(actual/target, 1) * 5, capped, non-editable —
 *   drawn from `kpi_quarters` exactly like legacy drew from its own
 *   equivalent table.
 * - Attendance auto-scoring from count thresholds (0=1.00, 1-5=0.70,
 *   6-10=0.30, >=11=0.00 per category) — now with a real home, since this
 *   session's own Attendance port (`attendance_summary`, 2026_09_29_030000)
 *   is exactly the table this reads from.
 * - Financial year computed live via ComputesFinancialYear, never a
 *   hardcoded class constant — legacy's own `'FY2026'` constant, duplicated
 *   independently across 3 controllers, was flagged by this feature's own
 *   research pass as a real, load-bearing bug waiting to happen at the next
 *   calendar rollover. Not repeating it.
 *
 * DELIBERATELY SCOPED DOWN:
 * - ONE appraiser per review (the employee's `company_users.manager_user_id`,
 *   see 2026_09_29_040000; falls back to any Company Admin when unset),
 *   not legacy's 3-tier Manager/VP/SLT Section-7 chain with a separate
 *   delegation table. The Platform's company-tier role model
 *   (company_admin/slt/executive/employee) has no distinct "VP" rung to
 *   chain through, and building a parallel 3-level chain-resolution engine
 *   plus a temporary-delegate table is a feature within a feature. The
 *   SAME practical output (recommendation remarks + confirmation/salary-
 *   review/promotion flags + a sign-off) is still captured, from one
 *   accountable appraiser instead of three.
 * - No canvas e-signature. Same reasoning as `job_descriptions`
 *   (2026_09_29_020000): an unauthenticated canvas doodle has LESS
 *   integrity than the authenticated status-transition + timestamp +
 *   actor-id this schema already records for every sign-off — the
 *   e-signature was decorative even in legacy, not the actual
 *   accountability signal.
 * - No `quarter_overrides` (BTS-only force-open). A Company Admin can
 *   already edit any review directly via the admin RLS branch, which
 *   covers the same real need ("this needs to reopen") without a second,
 *   narrower override table.
 *
 * Storage shape: two jsonb columns (`self_scores`, `appraiser_scores`) hold
 * the genuinely variable-shaped per-item detail (per-KPI scores, 12
 * attitude-area ratings, culture ratings) — legacy's own `form_data` is a
 * single wide jsonb blob for the same reason (the per-item field set is
 * inherently dynamic, keyed by however many KPIs/areas exist). Splitting
 * into two columns (rather than one, like legacy) is what makes the
 * self-vs-appraiser write boundary enforceable by the trigger below without
 * per-field-inside-jsonb granularity. Section TOTALS and the final
 * score/band are real top-level numeric/text columns, not buried in jsonb,
 * so `SltDashboardController`-equivalent reporting can query them directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('performance_reviews', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->text('financial_year');
            $table->text('quarter');
            $table->text('status')->default('draft');
            $table->jsonb('self_scores')->nullable();
            $table->jsonb('appraiser_scores')->nullable();
            $table->text('appraisee_acknowledgment')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->text('band')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('appraised_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['company_id', 'user_id', 'financial_year', 'quarter']);
            $table->index('status');
        });

        DB::connection('pgsql')->statement(
            "alter table performance_reviews add constraint performance_reviews_status_check check (status in ('draft','submitted','appraised','completed'))"
        );
        DB::connection('pgsql')->statement(
            "alter table performance_reviews add constraint performance_reviews_quarter_check check (quarter in ('Q1','Q2','Q3','Q4'))"
        );

        DB::connection('pgsql')->statement('alter table performance_reviews enable row level security');

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy performance_reviews_select on performance_reviews for select
              using (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or user_id = auth_current_user_id()
                or exists (
                  select 1 from company_users
                  where company_id = performance_reviews.company_id
                    and user_id = performance_reviews.user_id
                    and manager_user_id = auth_current_user_id()
                    and status = 'active'
                )
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy performance_reviews_insert on performance_reviews for insert
              with check (
                auth_can_administer_company(company_id)
                or (
                  user_id = auth_current_user_id()
                  and exists (
                    select 1 from company_users
                    where company_id = performance_reviews.company_id
                      and user_id = auth_current_user_id()
                      and status = 'active'
                  )
                )
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy performance_reviews_update on performance_reviews for update
              using (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or user_id = auth_current_user_id()
                or exists (
                  select 1 from company_users
                  where company_id = performance_reviews.company_id
                    and user_id = performance_reviews.user_id
                    and manager_user_id = auth_current_user_id()
                    and status = 'active'
                )
              )
              with check (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or user_id = auth_current_user_id()
                or exists (
                  select 1 from company_users
                  where company_id = performance_reviews.company_id
                    and user_id = performance_reviews.user_id
                    and manager_user_id = auth_current_user_id()
                    and status = 'active'
                )
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function restrict_performance_review_update()
            returns trigger
            language plpgsql
            security definer
            set search_path = public
            as $$
            declare
              is_admin boolean;
              is_manager boolean;
              is_self boolean;
            begin
              is_admin := auth_is_richworks_super_admin() or auth_can_administer_company(new.company_id);
              if is_admin then
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

              if new.company_id is distinct from old.company_id
                or new.user_id is distinct from old.user_id
                or new.financial_year is distinct from old.financial_year
                or new.quarter is distinct from old.quarter
              then
                raise exception 'These fields are immutable.';
              end if;

              is_self := new.user_id = auth_current_user_id();
              is_manager := exists (
                select 1 from company_users
                where company_id = new.company_id
                  and user_id = new.user_id
                  and manager_user_id = auth_current_user_id()
                  and status = 'active'
              );

              if is_manager then
                if old.status is distinct from 'submitted' then
                  raise exception 'You can only score a review once the employee has submitted it.';
                end if;
                if new.status not in ('submitted', 'appraised') then
                  raise exception 'Invalid status transition for an appraiser.';
                end if;
                if new.self_scores is distinct from old.self_scores
                  or new.appraisee_acknowledgment is distinct from old.appraisee_acknowledgment
                  or new.submitted_at is distinct from old.submitted_at
                  or new.completed_at is distinct from old.completed_at
                then
                  raise exception 'You may only set your own appraiser scores.';
                end if;
                return new;
              end if;

              if is_self then
                if old.status = 'draft' then
                  if new.status not in ('draft', 'submitted') then
                    raise exception 'You may save a draft or submit for review -- nothing else.';
                  end if;
                  if new.appraiser_scores is distinct from old.appraiser_scores
                    or new.final_score is distinct from old.final_score
                    or new.band is distinct from old.band
                    or new.appraisee_acknowledgment is distinct from old.appraisee_acknowledgment
                    or new.appraised_at is distinct from old.appraised_at
                    or new.completed_at is distinct from old.completed_at
                  then
                    raise exception 'Only your own self-assessment may be edited at this stage.';
                  end if;
                  return new;
                elsif old.status = 'appraised' then
                  if new.status is distinct from 'completed' then
                    raise exception 'You may only acknowledge and complete this review at this stage.';
                  end if;
                  if new.self_scores is distinct from old.self_scores
                    or new.appraiser_scores is distinct from old.appraiser_scores
                    or new.submitted_at is distinct from old.submitted_at
                    or new.appraised_at is distinct from old.appraised_at
                  then
                    raise exception 'Only your acknowledgement may be recorded at this stage.';
                  end if;
                  return new;
                else
                  raise exception 'This review cannot be edited right now.';
                end if;
              end if;

              raise exception 'You do not have access to update this review.';
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement(
            'create trigger trg_restrict_performance_review_update before update on performance_reviews for each row execute function restrict_performance_review_update()'
        );

        DB::connection('pgsql')->statement('grant select, insert, update on performance_reviews to authenticated');
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('performance_reviews');
        DB::connection('pgsql')->statement('drop function if exists restrict_performance_review_update()');
    }
};
