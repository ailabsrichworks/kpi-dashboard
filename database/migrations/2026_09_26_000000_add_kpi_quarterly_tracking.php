<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ports the legacy single-tenant app's per-quarter KPI tracking
 * (`kpi_quarters` + `kpi_update_approvals`) to the Platform — the piece the
 * Dashboard redesign earlier this session deliberately left out because no
 * per-period schema existed yet. Two real differences from the legacy design,
 * both explicit scope decisions rather than oversights:
 *
 * 1. Legacy gates approval on a manager hierarchy
 *    (EXECUTIVE->MANAGER->VP->SLT via employees.manager_id/vp_id/
 *    reports_to_id). The Platform has no manager-of-employee edge anywhere —
 *    confirmed by a full migration grep before writing this. Building that
 *    hierarchy is a separate, much larger feature than "quarterly tracking,"
 *    so this reuses the Company-Admin-approves shape
 *    `kpi_weight_change_requests` (2026_09_24_080000) already proved out —
 *    same RLS shape, same immutability trigger, same
 *    owner-may-make-exactly-one-kind-of-direct-change pattern.
 *
 * 2. Legacy overloads ONE approval table (`kpi_update_approvals`) for both
 *    actual-value changes AND completion sign-off, distinguished only by a
 *    string-prefix hack (`[[COMPLETION]]`) on the `reason` column. Completion
 *    sign-off here is instead a state machine directly on `kpi_quarters.status`
 *    (`pending_completion` <-> `completed`/`on_track`, both admin-only
 *    transitions) — consistent with how CompanyLifecycleService's own status
 *    transitions already work in this codebase, and it means only ONE new
 *    approval table is needed: `kpi_quarter_update_requests`, for the single
 *    remaining case that needs one (changing `actual` on an
 *    already-`completed` quarter).
 *
 * `financial_year`/`start_date`/`end_date` are always computed server-side
 * (KpiController, see the same migration's sibling changes there) from the
 * current calendar year — never client-supplied, and no custom fiscal-year
 * offset or historical-year browsing in this pass (matches this session's
 * established honest-scoping pattern).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('kpi_quarters', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->uuid('company_id');
            $table->uuid('kpi_id');
            $table->text('financial_year');
            $table->text('quarter');
            $table->decimal('target')->default(0);
            $table->decimal('actual')->nullable();
            $table->text('status')->default('not_started');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('completion_note')->nullable();
            $table->timestampTz('completion_submitted_at')->nullable();
            $table->uuid('completion_submitted_by')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('kpi_id')->references('id')->on('kpis')->onDelete('cascade');
            $table->foreign('completion_submitted_by')->references('id')->on('users');
            $table->index('company_id');
            $table->index('kpi_id');
            $table->unique(['kpi_id', 'financial_year', 'quarter']);
        });

        DB::connection('pgsql')->statement(
            "alter table kpi_quarters add constraint kpi_quarters_quarter_check check (quarter in ('Q1','Q2','Q3','Q4'))"
        );
        DB::connection('pgsql')->statement(
            "alter table kpi_quarters add constraint kpi_quarters_status_check check (status in ('not_started','on_track','at_risk','pending_completion','completed'))"
        );

        DB::connection('pgsql')->statement('alter table kpi_quarters enable row level security');

        // Reuses auth_can_view_kpi() -- the exact predicate kpis_select and
        // kpi_submissions_select already share, so a quarter's visibility can
        // never drift from the rest of the KPI system.
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_quarters_select on kpi_quarters for select
              using (auth_is_richworks_super_admin() or auth_can_view_kpi(kpi_id))
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_quarters_insert on kpi_quarters for insert
              with check (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
        SQL);

        // Widened for the owner's two direct-write paths (actual, and
        // submitting for sign-off) -- restrict_kpi_quarter_owner_update()
        // below confines exactly which columns/transitions that covers.
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_quarters_update on kpi_quarters for update
              using (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or exists (select 1 from kpis where id = kpi_quarters.kpi_id and assigned_user_id = auth_current_user_id())
              )
              with check (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or exists (select 1 from kpis where id = kpi_quarters.kpi_id and assigned_user_id = auth_current_user_id())
              )
        SQL);

        DB::connection('pgsql')->statement(
            'create trigger trg_prevent_company_id_change before update on kpi_quarters for each row execute function prevent_company_id_change()'
        );

        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function restrict_kpi_quarter_owner_update()
            returns trigger
            language plpgsql
            security definer
            set search_path = public
            as $$
            begin
              if auth_is_richworks_super_admin() or auth_can_administer_company(new.company_id) then
                return new;
              end if;

              -- Only the KPI's assigned owner reaches this branch at all
              -- (RLS's USING clause already refused anyone else). Confirm
              -- they are still an active member of the KPI's own company --
              -- same tenant-gap closure as restrict_kpi_owner_weight_update().
              if not exists (
                select 1 from company_users
                where company_id = new.company_id
                  and user_id = auth_current_user_id()
                  and status = 'active'
              ) then
                raise exception 'You are not an active member of this KPI''s company.';
              end if;

              if new.company_id is distinct from old.company_id
                or new.kpi_id is distinct from old.kpi_id
                or new.financial_year is distinct from old.financial_year
                or new.quarter is distinct from old.quarter
                or new.target is distinct from old.target
                or new.start_date is distinct from old.start_date
                or new.end_date is distinct from old.end_date
              then
                raise exception 'You may only update this quarter''s actual value or submit it for sign-off.';
              end if;

              -- Path 1: update `actual` only, before sign-off has started.
              if new.status is not distinct from old.status then
                if old.status in ('completed', 'pending_completion') then
                  raise exception 'This quarter is locked -- request a change instead of editing it directly.';
                end if;
                if new.completion_note is distinct from old.completion_note
                  or new.completion_submitted_at is distinct from old.completion_submitted_at
                  or new.completion_submitted_by is distinct from old.completion_submitted_by
                then
                  raise exception 'You may only update this quarter''s actual value or submit it for sign-off.';
                end if;
                return new;
              end if;

              -- Path 2: submit for sign-off (not_started/on_track/at_risk -> pending_completion).
              if old.status in ('not_started', 'on_track', 'at_risk') and new.status = 'pending_completion' then
                if new.actual is distinct from old.actual then
                  raise exception 'You may only update this quarter''s actual value or submit it for sign-off.';
                end if;
                if new.completion_submitted_by is distinct from auth_current_user_id() then
                  raise exception 'You may only submit sign-off as yourself.';
                end if;
                if new.completion_submitted_at is null then
                  raise exception 'completion_submitted_at is required when submitting for sign-off.';
                end if;
                return new;
              end if;

              raise exception 'That status change requires an administrator.';
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_restrict_kpi_quarter_owner_update
              before update on kpi_quarters
              for each row execute function restrict_kpi_quarter_owner_update()
        SQL);

        DB::connection('pgsql')->statement('grant select, insert, update on kpi_quarters to authenticated');

        // --- kpi_quarter_update_requests -----------------------------------
        // Same shape as kpi_weight_change_requests: the one remaining case
        // that needs a request row (changing `actual` after a quarter is
        // already `completed`).
        Schema::connection('pgsql')->create('kpi_quarter_update_requests', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->uuid('company_id');
            $table->uuid('kpi_id');
            $table->uuid('quarter_id');
            $table->uuid('requested_by');
            $table->decimal('old_actual')->nullable();
            $table->decimal('requested_actual');
            $table->text('reason');
            $table->text('status')->default('pending');
            $table->uuid('decided_by')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('kpi_id')->references('id')->on('kpis')->onDelete('cascade');
            $table->foreign('quarter_id')->references('id')->on('kpi_quarters')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('decided_by')->references('id')->on('users');
            $table->index('company_id');
            $table->index('kpi_id');
            $table->index('status');
        });

        DB::connection('pgsql')->statement(
            "alter table kpi_quarter_update_requests add constraint kpi_quarter_update_requests_status_check check (status in ('pending','approved','rejected'))"
        );

        DB::connection('pgsql')->statement('alter table kpi_quarter_update_requests enable row level security');

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_quarter_update_requests_select on kpi_quarter_update_requests for select
              using (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or requested_by = auth_current_user_id()
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_quarter_update_requests_insert on kpi_quarter_update_requests for insert
              with check (
                requested_by = auth_current_user_id()
                and exists (
                  select 1 from kpis
                  where id = kpi_id
                    and company_id = kpi_quarter_update_requests.company_id
                    and assigned_user_id = auth_current_user_id()
                )
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_quarter_update_requests_update on kpi_quarter_update_requests for update
              using (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
              with check (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
        SQL);

        DB::connection('pgsql')->statement(
            'create trigger trg_prevent_company_id_change before update on kpi_quarter_update_requests for each row execute function prevent_company_id_change()'
        );

        DB::connection('pgsql')->statement('grant select, insert, update on kpi_quarter_update_requests to authenticated');
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('kpi_quarter_update_requests');

        DB::connection('pgsql')->statement('drop trigger if exists trg_restrict_kpi_quarter_owner_update on kpi_quarters');
        DB::connection('pgsql')->statement('drop function if exists restrict_kpi_quarter_owner_update()');

        Schema::connection('pgsql')->dropIfExists('kpi_quarters');
    }
};
