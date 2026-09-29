<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ports legacy's Target Linkages (LinkageController, `kpi_linkages`) — the
 * last of the four confirmed sidebar gaps. Cascades a target from a manager
 * to a direct report: the manager records "you must cover X of this
 * category," and coverage is computed live by summing the assignee's own
 * KPI targets in that same category.
 *
 * Two adaptations, both forced by real shape differences already documented
 * elsewhere in this codebase, not convenience:
 *
 * 1. Legacy matches on a free-text `category`/`sub_category` PAIR (its own
 *    KPIs have both). Platform KPIs have only ONE category level
 *    (`kpis.category_id`, a real FK to `kpi_categories` — see
 *    2026_08_12_000000's foundational schema) and no `sub_category` column
 *    at all. This uses `category_id` directly — a real foreign key instead
 *    of a free-text string, which is a strictly more reliable match than
 *    legacy's own string comparison, not a downgrade.
 * 2. Legacy's `unit` is a constrained 3-value enum (number/currency/
 *    percentage); Platform's `kpis.unit` is free text (e.g. "%", "$",
 *    "calls" — see KpiController). This keeps `unit` as free text to match
 *    what a real Platform KPI's own unit actually looks like.
 *
 * One real, structural improvement over legacy, not just a port: legacy's
 * `store()` never verified server-side that the assignee actually reports to
 * the assigner before accepting a linkage — "direct reports" was computed
 * only for the UI list, not re-checked on write. `validate_kpi_target_linkage()`
 * below enforces it for real, using `company_users.manager_user_id`
 * (2026_09_29_040000, built for Performance/Appraisal's appraiser chain and
 * reused here for exactly the purpose its own migration named).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('kpi_target_linkages', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->uuid('company_id');
            $table->text('financial_year');
            $table->uuid('assigner_user_id');
            $table->uuid('assignee_user_id');
            $table->uuid('category_id');
            $table->text('unit')->nullable();
            $table->decimal('assigned_target', 14, 2);
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('assigner_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assignee_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('kpi_categories')->onDelete('cascade');
            $table->unique(['company_id', 'financial_year', 'assigner_user_id', 'assignee_user_id', 'category_id', 'unit'], 'kpi_target_linkages_unique_link');
            $table->index(['company_id', 'financial_year']);
        });

        DB::connection('pgsql')->statement('alter table kpi_target_linkages enable row level security');

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_target_linkages_select on kpi_target_linkages for select
              using (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or assigner_user_id = auth_current_user_id()
                or assignee_user_id = auth_current_user_id()
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_target_linkages_write on kpi_target_linkages for all
              using (auth_is_richworks_super_admin() or auth_can_administer_company(company_id) or assigner_user_id = auth_current_user_id())
              with check (auth_is_richworks_super_admin() or auth_can_administer_company(company_id) or assigner_user_id = auth_current_user_id())
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function validate_kpi_target_linkage()
            returns trigger
            language plpgsql
            security definer
            set search_path = public
            as $$
            begin
              if auth_is_richworks_super_admin() or auth_can_administer_company(new.company_id) then
                return new;
              end if;

              if new.assigner_user_id is distinct from auth_current_user_id() then
                raise exception 'You may only create or edit linkages you assigned.';
              end if;

              if not exists (
                select 1 from company_users
                where company_id = new.company_id
                  and user_id = new.assignee_user_id
                  and manager_user_id = new.assigner_user_id
                  and status = 'active'
              ) then
                raise exception 'You can only assign a target linkage to one of your own direct reports.';
              end if;

              return new;
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement(
            'create trigger trg_validate_kpi_target_linkage before insert or update on kpi_target_linkages for each row execute function validate_kpi_target_linkage()'
        );

        DB::connection('pgsql')->statement(
            'create trigger trg_prevent_company_id_change before update on kpi_target_linkages for each row execute function prevent_company_id_change()'
        );

        DB::connection('pgsql')->statement('grant select, insert, update, delete on kpi_target_linkages to authenticated');
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('kpi_target_linkages');
        DB::connection('pgsql')->statement('drop function if exists validate_kpi_target_linkage()');
    }
};
