<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ports legacy's Appraiser Delegation feature (AppraiserDelegationController/
 * Service, database/sql/create_appraiser_delegations.sql) — a Manager going
 * on leave has their VP stand in as appraiser for that Manager's own
 * Executives, without reassigning any actual report record.
 *
 * Legacy's mechanics, adapted rather than copied:
 *   - Legacy: BTS-only self-service (no approval step), delegate is always
 *     the manager's own computed `vp_id`/`reports_to_id` (never client-
 *     chosen), one active row per `manager_id` (DB-unique, upsert
 *     overwrites), no expiry — ended by manually deleting the row. The
 *     3-tier chain (Manager->VP->SLT) meant delegation only ever had one
 *     hop to consider (a VP's own SLT-appraisal duty could never itself be
 *     delegated onward).
 *   - Platform has no BTS tier — the closest equivalent authority is a
 *     Company Admin, so delegation here is Company-Admin-only self-service
 *     (matches every other admin-run configuration action already on this
 *     schema, e.g. `updateUserManager()`), not a request/approval flow.
 *   - Platform has no vp_id/reports_to_id columns — `company_users.
 *     manager_user_id` is the only hierarchy edge (2026_09_29_040000). The
 *     Platform equivalent of "the manager's own VP" is simply that manager's
 *     OWN `manager_user_id` (one hop further up the same chain) — computed
 *     server-side in the controller exactly like legacy computes its VP,
 *     never accepted from the client. A manager with nobody above them
 *     (manager_user_id is null) cannot be delegated for, same as legacy's
 *     "no VP found" rejection.
 *
 * `delegate_to_id` is stored (not recomputed live on every read) so a
 * delegation's target doesn't silently drift if the delegating manager's own
 * manager_user_id changes later — ending and re-creating the delegation is
 * the correct way to pick up a new delegate, matching legacy's own
 * "overwrite on new upsert" semantics for a changed VP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('appraiser_delegations', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->uuid('company_id');
            $table->uuid('manager_user_id');
            $table->uuid('delegate_user_id');
            $table->text('reason')->nullable();
            $table->uuid('created_by');
            $table->timestampTz('created_at')->default(DB::raw('now()'));

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('manager_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('delegate_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->unique(['company_id', 'manager_user_id']);
            $table->index('delegate_user_id');
        });

        DB::connection('pgsql')->statement('alter table appraiser_delegations enable row level security');

        // Both parties can see a delegation that names them (the delegating
        // manager should see their own status; the delegate needs to know
        // they've picked up someone else's appraisal duty) — same "both
        // sides can read" shape as kpi_weight_change_requests' requester
        // clause, just two id columns instead of one.
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy appraiser_delegations_select on appraiser_delegations for select
              using (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or manager_user_id = auth_current_user_id()
                or delegate_user_id = auth_current_user_id()
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy appraiser_delegations_write on appraiser_delegations for all
              using (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
              with check (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
        SQL);

        DB::connection('pgsql')->statement(
            'create trigger trg_prevent_company_id_change before update on appraiser_delegations for each row execute function prevent_company_id_change()'
        );

        DB::connection('pgsql')->statement('grant select, insert, update, delete on appraiser_delegations to authenticated');
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('appraiser_delegations');
    }
};
