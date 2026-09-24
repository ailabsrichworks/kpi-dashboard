<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Same bug class, same root cause, as `2026_09_24_010000_grant_authenticated_
 * role_on_foundational_tables` and `2026_09_24_020000_grant_authenticated_
 * role_on_import_batches` — applying the Platform schema to this brand-new
 * Supabase project via a direct Postgres connection (`php artisan migrate`)
 * instead of through Supabase's own dashboard/CLI skipped whatever normally
 * seeds default privileges. Those two prior migrations fixed `authenticated`;
 * this one discovers the identical gap on `service_role`, confirmed directly
 * against `information_schema.role_table_grants`: EVERY public table has only
 * `REFERENCES,TRIGGER,TRUNCATE` for `service_role` — no SELECT/INSERT/UPDATE/
 * DELETE anywhere at all.
 *
 * Confirmed as the exact cause of a real production bug: creating a company
 * via `/platform/companies` (POST) succeeded (that insert goes through the
 * caller's own `SupabaseUserService`/`authenticated` token, already fixed),
 * but the very next step — `AuditLogService::record()`, which deliberately
 * uses `SupabaseService`/`service_role` because a failed login has no
 * authenticated session to write through (see that class's own docblock) —
 * failed with "permission denied for table admin_action_logs", surfacing to
 * the admin as "Company was created, but the action could not be logged —
 * contact support before continuing."
 *
 * `service_role` is used sparingly and deliberately in this codebase — a
 * full sweep of every `SupabaseService`/`$privileged` call site under
 * `app/Http/Controllers/Platform` and `app/Services` confirms it only ever
 * touches two tables: `users` (the three narrow, documented exceptions —
 * `CompanyController::storeAdmin()`, `DepartmentController::storeUser()`,
 * `UserCreationController::store()` — reading back a just-invited Supabase
 * Auth user's row before RLS would let the caller see it yet; also
 * `TelegramAuthorizedScope`/`PlatformTelegramLinkService`/
 * `PlatformTelegramDigestService`/`BootstrapSuperAdmin`) and
 * `admin_action_logs` (the sole write path, `AuditLogService`). Granting
 * broadly to every table `service_role` doesn't touch would be scope creep
 * with nothing to verify it against — these two are the whole real surface.
 */
return new class extends Migration
{
    private const TABLES = [
        'users',
        'admin_action_logs',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::connection('pgsql')->statement("grant select, insert, update, delete on public.{$table} to service_role");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::connection('pgsql')->statement("revoke select, insert, update, delete on public.{$table} from service_role");
        }
    }
};
