<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds `blocked` as a real task status — the Platform's Tasks ("Things To
 * Do") board only ever had 4 columns (To Do, In Progress, Done, Cancelled)
 * while the legacy app's equivalent board has always had 5 (the same 4 plus
 * Blocked in between In Progress and Done). Found missing while bringing the
 * Platform sidebar/pages into parity with legacy's "Things To Do" screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement('alter table tasks drop constraint tasks_status_check');
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table tasks add constraint tasks_status_check
              check (status in ('open', 'in_progress', 'blocked', 'done', 'cancelled'))
        SQL);
    }

    public function down(): void
    {
        // Any 'blocked' rows would violate the narrower constraint on
        // rollback -- move them back to 'in_progress' first rather than
        // leaving a migration that can fail depending on live data.
        DB::connection('pgsql')->statement("update tasks set status = 'in_progress' where status = 'blocked'");

        DB::connection('pgsql')->statement('alter table tasks drop constraint tasks_status_check');
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table tasks add constraint tasks_status_check
              check (status in ('open', 'in_progress', 'done', 'cancelled'))
        SQL);
    }
};
