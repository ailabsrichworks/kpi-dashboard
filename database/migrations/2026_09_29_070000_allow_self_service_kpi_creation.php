<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Widens KPI creation from Company-Admin-only to any active company member —
 * "everyone can create their own KPI," not just admin-assigned ones. Mirrors
 * the exact shape `kpis_update` already uses for the weight self-service
 * path (2026_09_24_080000): a narrow, structurally-enforced self branch
 * alongside the existing admin branch, not a wholesale open door.
 *
 * The self branch requires BOTH `assigned_user_id = auth_current_user_id()`
 * AND `visibility = 'company'` — not just the former. `auth_can_view_kpi()`
 * (2026_08_17_110000) has no "you can always see a KPI assigned to you"
 * clause; visibility is the only thing that decides read access. Without
 * this second check, a self-created 'restricted' or 'department' KPI could
 * be invisible to its own creator the moment the request completes (no
 * grant exists yet, and grants are admin-only) — a real self-lockout trap,
 * not a security hole, but one worth closing structurally rather than
 * leaving as a support ticket waiting to happen. Company Admins are
 * unaffected and keep full control over visibility as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement('drop policy if exists kpis_insert on kpis');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpis_insert on kpis for insert
              with check (
                auth_can_administer_company(company_id)
                or (
                  assigned_user_id = auth_current_user_id()
                  and visibility = 'company'
                  and exists (
                    select 1 from company_users
                    where company_id = kpis.company_id
                      and user_id = auth_current_user_id()
                      and status = 'active'
                  )
                )
              )
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop policy if exists kpis_insert on kpis');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy kpis_insert on kpis for insert
              with check (auth_can_administer_company(company_id))
        SQL);
    }
};
