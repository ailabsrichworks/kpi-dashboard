<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Brings the Platform's `notifications` table up to parity with the legacy
 * app's own notifications table (the real "Richworks" reference —
 * resources/js/Pages/Notifications.tsx + resources/js/config/
 * notificationMeta.ts, requested verbatim: "make sure all controller are
 * same like Richworks"). Legacy's table has `type`/`link`/`quarter`/
 * `financial_year` columns backing its 9-type/3-category filter chips; the
 * Platform's table (2026_08_12_000000) never had them, so
 * Platform/Notifications/Index.tsx currently infers a type from the title
 * string instead of reading a real one — a stopgap, not the real thing.
 *
 * All 4 columns are plain, nullable, unconstrained text (no CHECK on `type`)
 * — deliberately mirroring legacy's own table shape (no CHECK there either)
 * rather than the tighter enum style used elsewhere in this schema
 * (tasks.status, kpi_quarters.status): notification types are additive as
 * features are built (this migration's own companion PR wires 3 real ones —
 * kpi_completion_approval, kpi_actual_approval, kpi_weightage_approval — for
 * QuarterlyController/WeightageController; the appraisal_ and
 * job_description_ prefixed types stay unused here until those features
 * exist), and a CHECK constraint would
 * mean a migration every time one is added.
 *
 * NOT YET APPLIED to production — the schema-change tool available in this
 * session refused it (a permission gate on modifying shared/production
 * resources, not a technical failure). Written and ready; needs either
 * `php artisan migrate --force` run with the right access, or the four
 * `alter table` statements below run by hand in the Supabase SQL editor.
 * Once applied, `PlatformNotificationService::notify()` and the 6 real call
 * sites (QuarterlyController x4, WeightageController x2) can start writing
 * real `type`/`link`/`quarter`/`financial_year` values instead of the
 * frontend inferring one — see this commit's own follow-up for that wiring,
 * held back specifically so nothing here starts sending an insert with a
 * column PostgREST doesn't recognize yet against the live database.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement('alter table notifications add column if not exists type text');
        DB::connection('pgsql')->statement('alter table notifications add column if not exists link text');
        DB::connection('pgsql')->statement('alter table notifications add column if not exists quarter text');
        DB::connection('pgsql')->statement('alter table notifications add column if not exists financial_year text');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('alter table notifications drop column if exists type');
        DB::connection('pgsql')->statement('alter table notifications drop column if exists link');
        DB::connection('pgsql')->statement('alter table notifications drop column if exists quarter');
        DB::connection('pgsql')->statement('alter table notifications drop column if exists financial_year');
    }
};
