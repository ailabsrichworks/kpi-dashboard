<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A new, legacy-side table — `staff_history` has no Platform `company_id`
 * column and is never routed through SupabaseService::TENANT_OWNED_TABLES
 * (that guard is Platform-only; this table belongs to the legacy
 * single-tenant app, same as `employees` itself). Records HR/BTS-entered
 * staff lifecycle events (resigned, terminated, suspended, rejoined,
 * transferred, promoted, other) — StaffHistoryController is the only
 * reader/writer. `employee_id`/`recorded_by_id` reference `employees.id`,
 * the same UUID primary key every other FK in this legacy schema
 * (manager_id/vp_id/reports_to_id) already points at — deliberately no
 * ON DELETE CASCADE, since employees are never hard-deleted in this app
 * (is_active is the soft-delete flag), so a cascade here would just be
 * silent, unused dead-code risk.
 *
 * `employees` itself predates every migration in this repo (created
 * directly in production, same as every other legacy table) and is never
 * created by any migration here — so the CI "tenant-isolation" job, which
 * replays every migration against a fresh, empty disposable Postgres
 * container, has no `employees` table to reference at all. This is the
 * first migration to write real SQL against a legacy table rather than
 * just mention one in a comment (confirmed by grepping every prior
 * migration), which is why this didn't surface until now. Guarded the
 * same way `2026_06_19_033957_create_sessions_table`'s duplicate-table fix
 * was: skip cleanly when the dependency isn't there, instead of failing
 * the whole migrate run for an environment that was never going to have
 * this table anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('pgsql')->hasTable('employees')) {
            return;
        }

        DB::connection('pgsql')->statement(<<<'SQL'
            create table if not exists public.staff_history (
                id uuid primary key default gen_random_uuid(),
                employee_id uuid not null references public.employees(id),
                company_code text not null,
                event_type text not null check (event_type in (
                    'resigned', 'terminated', 'suspended', 'rejoined', 'transferred', 'promoted', 'other'
                )),
                event_date date not null,
                reason text,
                notes text,
                recorded_by_id uuid references public.employees(id),
                recorded_by_name text,
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now()
            )
        SQL);

        DB::connection('pgsql')->statement(
            'create index if not exists staff_history_employee_id_idx on public.staff_history (employee_id)'
        );
        DB::connection('pgsql')->statement(
            'create index if not exists staff_history_company_code_idx on public.staff_history (company_code)'
        );
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop table if exists public.staff_history');
    }
};
