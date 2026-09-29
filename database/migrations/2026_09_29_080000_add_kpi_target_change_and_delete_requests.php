<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ports the two legacy approval types `kpi_target_change_requests`/
 * `kpi_delete_requests` never got a Platform equivalent for: changing an
 * existing KPI's `target`, and deleting a KPI — both self-service-owner-
 * requests-Company-Admin-decides, the exact shape
 * `kpi_weight_change_requests` (2026_09_24_080000) already proved out. Kept
 * as two narrow, purpose-built tables rather than legacy's single
 * `kpi_target_change_requests` table overloaded via a `[[WC]]` reason-string
 * prefix to also carry weightage-change requests — that hack is a real,
 * documented legacy wart (found auditing `ApprovalController`), not a
 * pattern worth repeating now that a clean alternative already exists on
 * this schema.
 *
 * `kpis_delete` never existed on this schema at all (a real, previously
 * documented gap — RLS scenario 6 in tenant_isolation.sql) — a Company Admin
 * could not delete a KPI even directly. Added here, admin-only and direct
 * (mirrors every other admin write on `kpis`); a non-admin never deletes
 * directly, only ever through `kpi_delete_requests` + an admin's approval,
 * matching legacy's "delete always goes through approval unless the
 * requester IS the top of the chain" rule (Platform's Company Admin is
 * already that top, so their own delete needs no request row at all).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement('drop policy if exists kpis_delete on kpis');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpis_delete on kpis for delete
              using (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
        SQL);

        Schema::connection('pgsql')->create('kpi_target_change_requests', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->uuid('company_id');
            $table->uuid('kpi_id');
            $table->uuid('requested_by');
            $table->decimal('old_target')->nullable();
            $table->decimal('new_target');
            $table->text('reason');
            $table->text('status')->default('pending');
            $table->uuid('decided_by')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('kpi_id')->references('id')->on('kpis')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('decided_by')->references('id')->on('users');
            $table->index('company_id');
            $table->index('kpi_id');
            $table->index('status');
        });

        DB::connection('pgsql')->statement(
            "alter table kpi_target_change_requests add constraint kpi_target_change_requests_status_check check (status in ('pending','approved','rejected'))"
        );

        DB::connection('pgsql')->statement('alter table kpi_target_change_requests enable row level security');

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_target_change_requests_select on kpi_target_change_requests for select
              using (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or requested_by = auth_current_user_id()
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_target_change_requests_insert on kpi_target_change_requests for insert
              with check (
                requested_by = auth_current_user_id()
                and exists (
                  select 1 from kpis
                  where id = kpi_id
                    and company_id = kpi_target_change_requests.company_id
                    and assigned_user_id = auth_current_user_id()
                )
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_target_change_requests_update on kpi_target_change_requests for update
              using (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
              with check (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
        SQL);

        DB::connection('pgsql')->statement(
            'create trigger trg_prevent_company_id_change before update on kpi_target_change_requests for each row execute function prevent_company_id_change()'
        );

        DB::connection('pgsql')->statement('grant select, insert, update on kpi_target_change_requests to authenticated');

        Schema::connection('pgsql')->create('kpi_delete_requests', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->uuid('company_id');
            $table->uuid('kpi_id');
            $table->uuid('requested_by');
            $table->text('reason');
            $table->text('status')->default('pending');
            $table->uuid('decided_by')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('kpi_id')->references('id')->on('kpis')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('decided_by')->references('id')->on('users');
            $table->index('company_id');
            $table->index('kpi_id');
            $table->index('status');
        });

        DB::connection('pgsql')->statement(
            "alter table kpi_delete_requests add constraint kpi_delete_requests_status_check check (status in ('pending','approved','rejected'))"
        );

        DB::connection('pgsql')->statement('alter table kpi_delete_requests enable row level security');

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_delete_requests_select on kpi_delete_requests for select
              using (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or requested_by = auth_current_user_id()
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_delete_requests_insert on kpi_delete_requests for insert
              with check (
                requested_by = auth_current_user_id()
                and exists (
                  select 1 from kpis
                  where id = kpi_id
                    and company_id = kpi_delete_requests.company_id
                    and assigned_user_id = auth_current_user_id()
                )
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_delete_requests_update on kpi_delete_requests for update
              using (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
              with check (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
        SQL);

        DB::connection('pgsql')->statement(
            'create trigger trg_prevent_company_id_change before update on kpi_delete_requests for each row execute function prevent_company_id_change()'
        );

        DB::connection('pgsql')->statement('grant select, insert, update on kpi_delete_requests to authenticated');
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('kpi_delete_requests');
        Schema::connection('pgsql')->dropIfExists('kpi_target_change_requests');

        DB::connection('pgsql')->statement('drop policy if exists kpis_delete on kpis');
    }
};
