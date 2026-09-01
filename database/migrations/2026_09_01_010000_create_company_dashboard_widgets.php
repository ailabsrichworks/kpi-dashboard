<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The per-company customizable dashboard: "the company that subscribed
 * decides how their dashboard looks." One shared, saved widget layout per
 * company (not per-user) — a Company Admin edits it, everyone in that
 * company sees the same arrangement, matching how the subscription model
 * (2026_09_01_000000) is also company-scoped, not per-user.
 *
 * `widget_type` is a fixed allow-list (CHECK constraint), not a free-form
 * string — every widget renders data through an existing, already-proven
 * calculation path (KpiCalculationService, ApprovalController's own pending-
 * approvals query, PeriodLifecycleService), never a new parallel one. This
 * table only stores WHICH widgets a company chose and in what order, never
 * any KPI/performance number itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('company_dashboard_widgets', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->uuid('company_id');
            $table->text('widget_type');
            $table->integer('position')->default(0);
            $table->timestampTz('created_at')->default(DB::raw('now()'));

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->index('company_id');
        });

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table company_dashboard_widgets add constraint company_dashboard_widgets_type_check
              check (widget_type in ('company_overview', 'pending_approvals', 'recent_submissions', 'period_status'))
        SQL);

        DB::connection('pgsql')->statement('alter table company_dashboard_widgets enable row level security');

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy company_dashboard_widgets_select on company_dashboard_widgets for select
              using (
                auth_is_richworks_super_admin()
                or company_id in (select auth_company_ids())
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy company_dashboard_widgets_write on company_dashboard_widgets for all
              using (
                auth_is_richworks_super_admin()
                or auth_role_in_company(company_id) = 'company_admin'
              )
              with check (
                auth_is_richworks_super_admin()
                or auth_role_in_company(company_id) = 'company_admin'
              )
        SQL);

        // company_id is derived server-side from the URL in the controller,
        // never trusted from the client, same as every other tenant-owned
        // table's own convention -- no immutability trigger is needed here
        // specifically because rows are replaced wholesale on every layout
        // save (delete-then-insert), never updated in place.
        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.company_dashboard_widgets to authenticated');
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('company_dashboard_widgets');
    }
};
