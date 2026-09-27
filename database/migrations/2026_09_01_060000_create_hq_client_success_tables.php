<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performix HQ Admin view, backing schema. Everything the HQ role-based
 * redesign needs that genuinely didn't exist anywhere in the schema before:
 * client health scoring, CAM (Client Account Manager) assignment and their
 * action items, a module catalog, and a support ticket queue.
 *
 * All six tables are Center-owned infrastructure, the same category as
 * `admin_action_logs`/`platform_admin_assignments` — not tenant data a
 * company owns, so they sit outside the tenant-isolation rule's `company_id`
 * requirement in spirit even though most of them carry a `company_id` column
 * (they need one to know *which* company a health score/CAM/ticket is
 * about — that's a foreign key, not tenant ownership). Every write stays
 * Super-Admin-only; a company's own admin/SLT gets read-only visibility into
 * the rows about their own company (mirrors `admin_action_logs_select_company`),
 * nothing more.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- company_health_scores ---------------------------------------
        DB::connection('pgsql')->statement(<<<'SQL'
            create table company_health_scores (
                id uuid primary key default gen_random_uuid(),
                company_id uuid not null references companies(id) on delete cascade,
                period_date date not null default current_date,
                adoption_score numeric not null default 0,
                activity_score numeric not null default 0,
                kpi_completion_score numeric not null default 0,
                cam_engagement_score numeric not null default 0,
                support_score numeric not null default 0,
                subscription_score numeric not null default 0,
                overall_score numeric not null default 0,
                status text not null default 'healthy',
                notes text null,
                created_by uuid null references users(id) on delete set null,
                created_at timestamptz not null default now(),
                unique (company_id, period_date)
            )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table company_health_scores add constraint company_health_scores_status_check
              check (status in ('healthy', 'at_risk', 'critical'))
        SQL);

        DB::connection('pgsql')->statement('create index company_health_scores_company_id_index on company_health_scores (company_id)');
        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.company_health_scores to authenticated');
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_company_id_change
              before update on company_health_scores
              for each row execute function prevent_company_id_change()
        SQL);
        DB::connection('pgsql')->statement('alter table company_health_scores enable row level security');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy company_health_scores_select on company_health_scores for select
              using (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy company_health_scores_write on company_health_scores for all
              using (auth_is_richworks_super_admin())
              with check (auth_is_richworks_super_admin())
        SQL);

        // --- cam_assignments ----------------------------------------------
        DB::connection('pgsql')->statement(<<<'SQL'
            create table cam_assignments (
                id uuid primary key default gen_random_uuid(),
                company_id uuid not null references companies(id) on delete cascade,
                cam_user_id uuid not null references users(id) on delete cascade,
                assigned_by uuid null references users(id) on delete set null,
                assigned_at timestamptz not null default now(),
                unique (company_id)
            )
        SQL);

        DB::connection('pgsql')->statement('create index cam_assignments_cam_user_id_index on cam_assignments (cam_user_id)');
        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.cam_assignments to authenticated');
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_company_id_change
              before update on cam_assignments
              for each row execute function prevent_company_id_change()
        SQL);
        DB::connection('pgsql')->statement('alter table cam_assignments enable row level security');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy cam_assignments_select on cam_assignments for select
              using (
                auth_is_richworks_super_admin()
                or cam_user_id = auth_current_user_id()
                or auth_can_administer_company(company_id)
              )
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy cam_assignments_write on cam_assignments for all
              using (auth_is_richworks_super_admin())
              with check (auth_is_richworks_super_admin())
        SQL);

        // --- cam_actions ----------------------------------------------------
        DB::connection('pgsql')->statement(<<<'SQL'
            create table cam_actions (
                id uuid primary key default gen_random_uuid(),
                company_id uuid not null references companies(id) on delete cascade,
                cam_user_id uuid not null references users(id) on delete cascade,
                title text not null,
                description text null,
                due_date date null,
                status text not null default 'open',
                created_by uuid null references users(id) on delete set null,
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now()
            )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table cam_actions add constraint cam_actions_status_check
              check (status in ('open', 'in_progress', 'done', 'cancelled'))
        SQL);

        DB::connection('pgsql')->statement('create index cam_actions_company_id_index on cam_actions (company_id)');
        DB::connection('pgsql')->statement('create index cam_actions_cam_user_id_index on cam_actions (cam_user_id)');
        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.cam_actions to authenticated');
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_company_id_change
              before update on cam_actions
              for each row execute function prevent_company_id_change()
        SQL);
        DB::connection('pgsql')->statement('alter table cam_actions enable row level security');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy cam_actions_select on cam_actions for select
              using (auth_is_richworks_super_admin() or cam_user_id = auth_current_user_id())
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy cam_actions_insert on cam_actions for insert
              with check (auth_is_richworks_super_admin() or cam_user_id = auth_current_user_id())
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy cam_actions_update on cam_actions for update
              using (auth_is_richworks_super_admin() or cam_user_id = auth_current_user_id())
              with check (auth_is_richworks_super_admin() or cam_user_id = auth_current_user_id())
        SQL);

        // --- platform_modules (shared catalog, no company_id — same
        //     exemption as kpi_templates/subscription_plans) --------------
        DB::connection('pgsql')->statement(<<<'SQL'
            create table platform_modules (
                id uuid primary key default gen_random_uuid(),
                key text not null,
                name text not null,
                description text null,
                is_active boolean not null default true,
                created_at timestamptz not null default now(),
                unique (key)
            )
        SQL);

        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.platform_modules to authenticated');
        DB::connection('pgsql')->statement('alter table platform_modules enable row level security');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy platform_modules_select on platform_modules for select
              using (auth_current_user_id() is not null)
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy platform_modules_write on platform_modules for all
              using (auth_is_richworks_super_admin())
              with check (auth_is_richworks_super_admin())
        SQL);

        // --- company_module_grants -----------------------------------------
        DB::connection('pgsql')->statement(<<<'SQL'
            create table company_module_grants (
                id uuid primary key default gen_random_uuid(),
                company_id uuid not null references companies(id) on delete cascade,
                module_id uuid not null references platform_modules(id) on delete cascade,
                enabled boolean not null default true,
                granted_by uuid null references users(id) on delete set null,
                granted_at timestamptz not null default now(),
                unique (company_id, module_id)
            )
        SQL);

        DB::connection('pgsql')->statement('create index company_module_grants_company_id_index on company_module_grants (company_id)');
        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.company_module_grants to authenticated');
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_company_id_change
              before update on company_module_grants
              for each row execute function prevent_company_id_change()
        SQL);
        DB::connection('pgsql')->statement('alter table company_module_grants enable row level security');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy company_module_grants_select on company_module_grants for select
              using (auth_is_richworks_super_admin() or auth_can_administer_company(company_id))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy company_module_grants_write on company_module_grants for all
              using (auth_is_richworks_super_admin())
              with check (auth_is_richworks_super_admin())
        SQL);

        // --- support_tickets --------------------------------------------
        DB::connection('pgsql')->statement(<<<'SQL'
            create table support_tickets (
                id uuid primary key default gen_random_uuid(),
                company_id uuid not null references companies(id) on delete cascade,
                subject text not null,
                description text null,
                status text not null default 'open',
                raised_by uuid null references users(id) on delete set null,
                assigned_to uuid null references users(id) on delete set null,
                created_at timestamptz not null default now(),
                updated_at timestamptz not null default now()
            )
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table support_tickets add constraint support_tickets_status_check
              check (status in ('open', 'in_progress', 'resolved', 'closed'))
        SQL);

        DB::connection('pgsql')->statement('create index support_tickets_company_id_index on support_tickets (company_id)');
        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.support_tickets to authenticated');
        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_company_id_change
              before update on support_tickets
              for each row execute function prevent_company_id_change()
        SQL);
        DB::connection('pgsql')->statement('alter table support_tickets enable row level security');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy support_tickets_select on support_tickets for select
              using (auth_is_richworks_super_admin() or auth_role_in_company(company_id) in ('company_admin', 'slt'))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy support_tickets_insert on support_tickets for insert
              with check (auth_is_richworks_super_admin() or auth_role_in_company(company_id) in ('company_admin', 'slt'))
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy support_tickets_update on support_tickets for update
              using (auth_is_richworks_super_admin())
              with check (auth_is_richworks_super_admin())
        SQL);

        // Seed a small, honest starting catalog — matches what the app
        // actually has today (ANIRA, Telegram, KPI templates, Excel import)
        // rather than inventing modules nothing gates yet.
        DB::connection('pgsql')->table('platform_modules')->insert([
            ['id' => DB::raw('gen_random_uuid()'), 'key' => 'anira', 'name' => 'ANIRA', 'description' => 'Tenant-aware AI performance assistant.', 'is_active' => true, 'created_at' => now()],
            ['id' => DB::raw('gen_random_uuid()'), 'key' => 'telegram', 'name' => 'Telegram', 'description' => 'Telegram linking, digests, and task management.', 'is_active' => true, 'created_at' => now()],
            ['id' => DB::raw('gen_random_uuid()'), 'key' => 'kpi_templates', 'name' => 'KPI Templates', 'description' => 'Apply the shared KPI template library to a company.', 'is_active' => true, 'created_at' => now()],
            ['id' => DB::raw('gen_random_uuid()'), 'key' => 'excel_import', 'name' => 'Excel Import', 'description' => 'Bulk import departments, KPIs, and employees from a spreadsheet.', 'is_active' => true, 'created_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop table if exists support_tickets');
        DB::connection('pgsql')->statement('drop table if exists company_module_grants');
        DB::connection('pgsql')->statement('drop table if exists platform_modules');
        DB::connection('pgsql')->statement('drop table if exists cam_actions');
        DB::connection('pgsql')->statement('drop table if exists cam_assignments');
        DB::connection('pgsql')->statement('drop table if exists company_health_scores');
    }
};
