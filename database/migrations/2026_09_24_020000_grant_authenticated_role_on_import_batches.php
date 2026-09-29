<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Same bug as `2026_09_24_010000_grant_authenticated_role_on_foundational_tables`
 * — that migration's own table list was built by re-reading the foundational
 * schema migration, and missed `import_batches` (created by a *separate*
 * migration, `2026_08_14_040000_create_import_batches`, not the foundational
 * one). Confirmed as the exact cause of a real production 500 on
 * `/platform/companies/{company}/import` (PostgREST "permission denied for
 * table import_batches") and, transitively, on
 * `/platform/companies/{company}/onboarding` (`OnboardingController::index()`
 * reads `import_batches` to compute the Import/Validate-spreadsheet steps'
 * `done` state).
 *
 * A full `information_schema.role_table_grants` sweep after this migration
 * confirms `import_batches` is the only table that still had this gap.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.import_batches to authenticated');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('revoke select, insert, update, delete on public.import_batches from authenticated');
    }
};
