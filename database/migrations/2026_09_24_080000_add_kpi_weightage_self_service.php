<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ports the legacy single-tenant app's "Manage Weightage" page
 * (resources/views/kpi/weightage.blade.php) to the Platform, as a real
 * feature rather than the plain admin-only `kpis.weight` number field added
 * earlier this session. That field stays — this adds the self-service layer
 * on top of it: an employee can only be assigned specific KPIs and only
 * allocate/change weight on THEIR OWN assigned KPIs, with the same
 * direct-save-if-new / approval-if-changing split the legacy page enforced
 * in JS alone (never a real server-side rule there).
 *
 * Two schema pieces:
 *
 * 1. `kpis.assigned_user_id` — nullable, mirrors the legacy `kpi.employee_id`
 *    concept, but deliberately optional: most Platform KPIs are company- or
 *    department-wide with no single owner, and stay that way. Only a KPI a
 *    Company Admin explicitly assigns to one person becomes "their" KPI for
 *    weightage purposes. This does NOT affect `auth_can_view_kpi()` / who can
 *    read the KPI — visibility is unchanged, this column only ever gates
 *    who may write `weight` outside the admin path.
 *
 * 2. `kpi_weight_change_requests` — the approval queue. A request is created
 *    when the assigned owner wants to change an EXISTING (> 0) weight; a
 *    Company Admin approves (which applies the change to `kpis.weight`) or
 *    rejects it. Modeled after `kpi_access_grants`' shape (company_id +
 *    immutability trigger), not a generic `approval_requests` table — this
 *    codebase already established (Blueprint decisions throughout) that a
 *    narrow, purpose-built table beats a premature generic one.
 *
 * RLS changes to `kpis_update`: widened from admin-only to also allow the
 * row's own `assigned_user_id`, but a new BEFORE UPDATE trigger
 * (`restrict_kpi_owner_weight_update`) confines that branch to exactly the
 * legacy page's "new allocation" case — only `weight` may change, only from
 * null/0, and only by someone who is actually an ACTIVE member of the KPI's
 * own company (checked in the trigger itself, not just at assignment time —
 * `assigned_user_id` has no company-membership constraint of its own since
 * `users` is a global identity table, so without this check a KPI mistakenly
 * or maliciously assigned to a user of a DIFFERENT company would let that
 * user write into this company's `kpis` row, a real tenant-boundary gap this
 * migration must not introduce). Every other case — changing an existing
 * weight, or anything other than `weight` — is refused, matching the
 * legacy page's "changing existing requires approval" rule for real instead
 * of only in client-side JS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('kpis', function (Blueprint $table) {
            $table->uuid('assigned_user_id')->nullable()->after('weight');
            $table->foreign('assigned_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('assigned_user_id');
        });

        Schema::connection('pgsql')->create('kpi_weight_change_requests', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->uuid('company_id');
            $table->uuid('kpi_id');
            $table->uuid('requested_by');
            $table->decimal('old_weight');
            $table->decimal('new_weight');
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
            "alter table kpi_weight_change_requests add constraint kpi_weight_change_requests_status_check check (status in ('pending','approved','rejected'))"
        );

        DB::connection('pgsql')->statement('alter table kpi_weight_change_requests enable row level security');

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_weight_change_requests_select on kpi_weight_change_requests for select
              using (
                auth_is_richworks_super_admin()
                or auth_can_administer_company(company_id)
                or requested_by = auth_current_user_id()
              )
        SQL);

        // The requester must be the caller, and the KPI must actually be
        // theirs (assigned_user_id) within the SAME company_id the row
        // claims -- both checked here, not trusted from the client.
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_weight_change_requests_insert on kpi_weight_change_requests for insert
              with check (
                requested_by = auth_current_user_id()
                and exists (
                  select 1 from kpis
                  where id = kpi_id
                    and company_id = kpi_weight_change_requests.company_id
                    and assigned_user_id = auth_current_user_id()
                )
              )
        SQL);

        // Only an admin decides -- the requester cannot flip their own
        // request's status (no self-approval, no self-cancel; if a cancel
        // feature is wanted later it needs its own, narrower policy).
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpi_weight_change_requests_update on kpi_weight_change_requests for update
              using (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
              with check (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
        SQL);

        DB::connection('pgsql')->statement(
            'create trigger trg_prevent_company_id_change before update on kpi_weight_change_requests for each row execute function prevent_company_id_change()'
        );

        DB::connection('pgsql')->statement('grant select, insert, update on kpi_weight_change_requests to authenticated');

        // --- widen kpis_update for the owner's direct-save path -----------
        DB::connection('pgsql')->statement('drop policy if exists kpis_update on kpis');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpis_update on kpis for update
              using (auth_can_administer_company(company_id) or assigned_user_id = auth_current_user_id())
              with check (auth_can_administer_company(company_id) or assigned_user_id = auth_current_user_id())
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function restrict_kpi_owner_weight_update()
            returns trigger
            language plpgsql
            security definer
            set search_path = public
            as $$
            begin
              if auth_is_richworks_super_admin() or auth_can_administer_company(new.company_id) then
                return new;
              end if;

              -- Only the assigned owner reaches this branch at all (RLS's
              -- USING clause already refused anyone else). Confirm they are
              -- still an active member of the KPI's own company -- a global
              -- user id on assigned_user_id carries no tenant guarantee of
              -- its own.
              if not exists (
                select 1 from company_users
                where company_id = new.company_id
                  and user_id = auth_current_user_id()
                  and status = 'active'
              ) then
                raise exception 'You are not an active member of this KPI''s company.';
              end if;

              if new.assigned_user_id is distinct from old.assigned_user_id
                or new.company_id is distinct from old.company_id
                or new.category_id is distinct from old.category_id
                or new.name is distinct from old.name
                or new.description is distinct from old.description
                or new.target is distinct from old.target
                or new.unit is distinct from old.unit
                or new.frequency is distinct from old.frequency
                or new.status is distinct from old.status
                or new.visibility is distinct from old.visibility
              then
                raise exception 'You may only set your own KPI''s weight, and only for a new (empty) allocation.';
              end if;

              if coalesce(old.weight, 0) > 0 then
                raise exception 'This KPI already has a weight -- request a change instead of editing it directly.';
              end if;

              if new.weight is null or new.weight < 0 or new.weight > 100 then
                raise exception 'Weight must be between 0 and 100.';
              end if;

              return new;
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement('drop trigger if exists trg_restrict_kpi_owner_weight_update on kpis');
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_restrict_kpi_owner_weight_update
              before update on kpis
              for each row execute function restrict_kpi_owner_weight_update()
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop trigger if exists trg_restrict_kpi_owner_weight_update on kpis');
        DB::connection('pgsql')->statement('drop function if exists restrict_kpi_owner_weight_update()');

        DB::connection('pgsql')->statement('drop policy if exists kpis_update on kpis');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpis_update on kpis for update
              using (auth_can_administer_company(company_id))
              with check (auth_can_administer_company(company_id))
        SQL);

        Schema::connection('pgsql')->dropIfExists('kpi_weight_change_requests');

        Schema::connection('pgsql')->table('kpis', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
            $table->dropColumn('assigned_user_id');
        });
    }
};
