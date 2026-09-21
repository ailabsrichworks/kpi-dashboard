<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional time-of-day to a task, distinct from its date-only
 * `due_date` — the Kanban/Calendar redesign of the Platform Tasks page
 * needs to tell "due sometime today" apart from "a meeting at 2:30 PM".
 * Nullable: a plain to-do never sets it. No RLS/grant changes needed —
 * this only adds a column to an already-policied, already-granted table
 * (`2026_08_18_010000_create_tasks_feature.php`), which applies row-wide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->time('meeting_time')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('tasks', function (Blueprint $table) {
            $table->dropColumn('meeting_time');
        });
    }
};
