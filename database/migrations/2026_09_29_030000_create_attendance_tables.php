<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ports legacy's Attendance page (AttendanceController, resources/views/
 * attendance/index.blade.php) — the second of the four confirmed gaps from
 * auditing legacy's sidebar against the Platform (alongside Job Description,
 * already built, and Performance/Appraisal, Target Linkages still to come).
 *
 * Legacy's design carries over directly with no schema-forced departure this
 * time (unlike Job Description's manager-hierarchy gap): fetch a public CSV
 * export of a company-supplied Google Sheet, compute present/absent/late/
 * insufficient-hours stats against a working-day calendar (weekends + this
 * company's own public holidays excluded), let an admin fill in MC/AL/Other
 * leave days, then save one row per employee per month. Two tables:
 *
 * 1. `public_holidays` — per-company, needed for the working-day math to be
 *    correct at all (legacy's own `AttendanceController::import()` excludes
 *    these from `$workingDays` before computing anything). Legacy has no
 *    admin UI for these either (they're managed directly in the table) — the
 *    Platform gets a minimal one on the same page, not a separate feature.
 * 2. `attendance_summary` — one row per (company, external_employee_id,
 *    month, year). `external_employee_id` is the raw clock-in-system id from
 *    the CSV, kept as plain text rather than a foreign key: the Platform's
 *    `users`/`company_users` have no column recording an external
 *    attendance-system id today (legacy's own match is against
 *    `employees.employee_id`, a column that has no Platform equivalent), so
 *    `user_id` stays nullable and unenriched for now — every row still saves
 *    correctly, exactly like legacy's own designed fallback for a CSV
 *    employee it can't find in its `employees` table (blank department, no
 *    linked id). Enrichment is a real, separate follow-up once something
 *    exists to link a clock-in id to a Platform account, not invented here
 *    with no UI to set it.
 *
 * Admin-only both ways (no owner self-service branch, unlike Job
 * Description/Weightage/Quarterly) — legacy restricts this whole page to
 * SLT/VP (`hr_access` session flag), and the Platform has no equivalent
 * self-view for attendance at all, so `auth_can_administer_company()` is the
 * only real access concept to route through, matching how Quarter Control
 * and other legacy admin-only pages were already substituted this session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('public_holidays', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->uuid('company_id');
            $table->date('holiday_date');
            $table->text('label')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unique(['company_id', 'holiday_date']);
        });

        DB::connection('pgsql')->statement('alter table public_holidays enable row level security');

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy public_holidays_select on public_holidays for select
              using (
                auth_is_richworks_super_admin()
                or exists (
                  select 1 from company_users
                  where company_id = public_holidays.company_id
                    and user_id = auth_current_user_id()
                    and status = 'active'
                )
              )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy public_holidays_write on public_holidays for all
              using (auth_can_administer_company(company_id))
              with check (auth_can_administer_company(company_id))
        SQL);

        DB::connection('pgsql')->statement(
            'create trigger trg_prevent_company_id_change before update on public_holidays for each row execute function prevent_company_id_change()'
        );

        DB::connection('pgsql')->statement('grant select, insert, update, delete on public_holidays to authenticated');

        Schema::connection('pgsql')->create('attendance_summary', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->uuid('company_id');
            $table->uuid('user_id')->nullable();
            $table->text('external_employee_id');
            $table->text('name');
            $table->text('email')->nullable();
            $table->text('department')->nullable();
            $table->unsignedSmallInteger('month');
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('working_days')->default(0);
            $table->unsignedSmallInteger('present_days')->default(0);
            $table->unsignedSmallInteger('absent_days')->default(0);
            $table->unsignedSmallInteger('late_count')->default(0);
            $table->unsignedInteger('total_late_minutes')->default(0);
            $table->unsignedSmallInteger('insufficient_count')->default(0);
            $table->unsignedSmallInteger('mc_days')->default(0);
            $table->unsignedSmallInteger('al_days')->default(0);
            $table->unsignedSmallInteger('other_leave_days')->default(0);
            $table->text('sheet_url')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->unique(['company_id', 'external_employee_id', 'month', 'year']);
            $table->index(['company_id', 'year', 'month']);
        });

        DB::connection('pgsql')->statement(
            'alter table attendance_summary add constraint attendance_summary_month_check check (month between 1 and 12)'
        );

        DB::connection('pgsql')->statement('alter table attendance_summary enable row level security');

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy attendance_summary_admin_only on attendance_summary for all
              using (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
              with check (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
        SQL);

        DB::connection('pgsql')->statement(
            'create trigger trg_prevent_company_id_change before update on attendance_summary for each row execute function prevent_company_id_change()'
        );

        DB::connection('pgsql')->statement('grant select, insert, update, delete on attendance_summary to authenticated');
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('attendance_summary');
        Schema::connection('pgsql')->dropIfExists('public_holidays');
    }
};
