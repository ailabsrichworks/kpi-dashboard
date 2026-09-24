<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Same bug class as `2026_08_17_150000_grant_authenticated_role_on_missing_tables`,
 * this time on the ORIGINAL foundational-schema tables themselves. That
 * earlier migration assumed "every sibling table from the original
 * foundational schema already has SELECT,INSERT,UPDATE,DELETE granted to
 * `authenticated`" — true on the production project it was written against
 * (Supabase's own project template applies those default grants
 * automatically), but not a property of the schema migration itself: nothing
 * in `2026_08_12_000000_create_platform_foundation_schema` ever grants them
 * explicitly. Applying that same migration to a brand-new Supabase project
 * via a direct Postgres connection (`php artisan migrate`, not through
 * Supabase's own dashboard/CLI tooling) skips whatever normally seeds those
 * default privileges, so every foundational table came up with only
 * `REFERENCES,TRIGGER,TRUNCATE` for `authenticated` — confirmed directly
 * against `information_schema.role_table_grants`, and confirmed as the exact
 * cause of a real production 500 on `/platform/login` (PostgREST returned
 * `permission denied for table users`, 42501, the moment `landingUrlFor()`
 * queried `users` with the caller's own token).
 *
 * `company_kpi_summary` is a `security_invoker` view, not a base table — only
 * SELECT applies to it.
 */
return new class extends Migration
{
    private const TABLES = [
        'companies',
        'users',
        'departments',
        'company_users',
        'department_users',
        'kpi_categories',
        'kpis',
        'kpi_submissions',
        'audit_logs',
        'notifications',
        'reports',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::connection('pgsql')->statement("grant select, insert, update, delete on public.{$table} to authenticated");
        }

        DB::connection('pgsql')->statement('grant select on public.company_kpi_summary to authenticated');
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::connection('pgsql')->statement("revoke select, insert, update, delete on public.{$table} from authenticated");
        }

        DB::connection('pgsql')->statement('revoke select on public.company_kpi_summary from authenticated');
    }
};
