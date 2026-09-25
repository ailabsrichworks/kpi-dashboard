<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Platform's `notifications` table (2026_08_12_000000) has had
 * select/update RLS policies since the foundational schema, but no INSERT
 * policy at all — nothing has ever been able to write to it, which is why
 * CLAUDE.md's own inventory calls it schema-only/dormant. This is the first
 * real write path: `PlatformNotificationService::notify()`, called from
 * `WeightageController::approve()`/`reject()` to tell the original requester
 * their weight-change request was decided.
 *
 * `notifications` is one of `SupabaseService::TENANT_OWNED_TABLES`'s guarded
 * tables (a deliberate Core Platform Rule exemption never applies to it), so
 * this must go through the RLS-respecting SupabaseUserService/caller-token
 * path, not a service-role bypass — the policy below is the real boundary,
 * not an app-level check standing in for one.
 *
 * `with check (auth_can_administer_company(company_id) or user_id =
 * auth_current_user_id())`: a company admin (or assigned Platform Admin, or
 * Super Admin — all covered by auth_can_administer_company) can notify
 * anyone in a company they administer (the actual shape every real
 * notification trigger in this codebase takes — an admin acting on someone
 * else's request), and anyone can write a notification row for themselves
 * (a self-reminder, or a future feature that doesn't need an admin in the
 * loop). Nobody can write a notification "as" or "to" an arbitrary other
 * user outside a company they administer.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy notifications_insert on notifications for insert
              with check (auth_can_administer_company(company_id) or user_id = auth_current_user_id())
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop policy if exists notifications_insert on notifications');
    }
};
