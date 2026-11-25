<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Switching the attendance source from BrioHR (raw clock punches, no leave
 * codes at all -- MC/AL/Other had to be hand-typed by HR after the fact) to
 * InfoTech/ETMS (one row per staff per day, with a real reason code --
 * ABS/AL/ALPM/EL/MC/RES/RPLC/etc. -- and explicit Normal/Actual hours).
 * `attendance_summary` only ever stored monthly totals; there was no
 * per-day record to click into, re-import against, or delete individually.
 * This table is that missing layer -- one row per company+staff+day, which
 * `attendance_summary` is now recomputed FROM rather than fed by hand.
 *
 * `el_days` is new on `attendance_summary` for the same reason: the old
 * BrioHR flow had no EL concept at all, so Emergency Leave was folded into
 * the generic `other_leave_days` bucket (see PerformanceController's
 * "EL / Unpaid Leave" label, which actually read other_leave_days). Now
 * that EL is a real code in the source data, it gets its own column;
 * `other_leave_days` narrows to mean "everything that isn't ABS/AL/EL/MC/
 * a rest day" (e.g. PTL).
 *
 * Legacy table, like `staff_history` -- guarded against CI's fresh,
 * migration-only disposable database, which never has `attendance_summary`
 * or `employees` (both created directly in production, predating every
 * migration here).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('pgsql')->hasTable('attendance_summary')) {
            return;
        }

        DB::connection('pgsql')->statement(<<<'SQL'
            create table if not exists public.attendance_daily (
                id uuid primary key default gen_random_uuid(),
                company_code text not null,
                internal_id text not null,
                employee_id uuid references public.employees(id),
                name text,
                department_raw text,
                date date not null,
                day_name text,
                clock_in text,
                clock_out text,
                status text not null check (status in (
                    'present', 'absent', 'mc', 'al', 'el', 'off', 'other'
                )),
                status_raw text,
                is_late boolean not null default false,
                is_early_out boolean not null default false,
                late_minutes integer not null default 0,
                normal_hours numeric(6,2),
                actual_hours numeric(6,2),
                source_batch text,
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now(),
                unique (company_code, internal_id, date)
            )
        SQL);

        DB::connection('pgsql')->statement(
            'create index if not exists attendance_daily_lookup_idx on public.attendance_daily (company_code, date)'
        );
        DB::connection('pgsql')->statement(
            'create index if not exists attendance_daily_staff_idx on public.attendance_daily (internal_id, date)'
        );

        DB::connection('pgsql')->statement(
            'alter table public.attendance_summary add column if not exists el_days integer not null default 0'
        );
        // Both are new here. Previously, the staff name/department shown on
        // the import-preview screen came straight from that request's live
        // Google Sheet fetch and was never saved -- the moment you left the
        // page, `attendance_summary`'s own rows had no name to show again.
        // Refreshed on every re-import so a later name change (or an
        // internal_id the `employees` table doesn't recognise yet, e.g.
        // right after switching HRMS) still displays something real.
        DB::connection('pgsql')->statement(
            'alter table public.attendance_summary add column if not exists name text'
        );
        DB::connection('pgsql')->statement(
            'alter table public.attendance_summary add column if not exists department_raw text'
        );
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop table if exists public.attendance_daily');
        DB::connection('pgsql')->statement('alter table public.attendance_summary drop column if exists el_days');
        DB::connection('pgsql')->statement('alter table public.attendance_summary drop column if exists name');
        DB::connection('pgsql')->statement('alter table public.attendance_summary drop column if exists department_raw');
    }
};
