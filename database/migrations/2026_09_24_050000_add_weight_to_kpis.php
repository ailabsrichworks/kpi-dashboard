<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `kpis` has never had a weight/weightage column — the Phase 7 Excel import
 * docblock already flagged this as a known gap ("KPI 'Weight' has no column
 * on `kpis` at all"), kept only on the parsed row under `_weight` for the
 * Preview screen, never inserted. Adding it for real now: nullable, since
 * every existing KPI (and every KPI a template applies) has no weight
 * opinion yet and shouldn't be forced to one retroactively.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement('alter table public.kpis add column if not exists weight numeric null');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('alter table public.kpis drop column if exists weight');
    }
};
