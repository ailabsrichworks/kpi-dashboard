<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription plan catalog + assignment, for the Richworks "Control
 * Centre" to manage which plan each subscribed company is on. Deliberately
 * separate from the existing `companies.subscription_plan` free-text column
 * (added by `2026_08_28_000000`, part of the client-onboarding intake form)
 * -- that stays as whatever a salesperson typed during intake, this is the
 * real, structured catalog + status the Center actually manages afterward.
 * Neither column is dropped or renamed to avoid disturbing the existing
 * intake form/validation in CompanyController::storeAdmin().
 *
 * `subscription_plans` has no `company_id` -- a shared catalog like
 * `kpi_templates`, one of the Core Platform Rule's own documented
 * exemptions. RLS mirrors `kpi_templates` exactly: any signed-in platform
 * user may read the catalog, only the Center (Super Admin) writes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->default(DB::raw('gen_random_uuid()'))->primary();
            $table->text('name');
            $table->integer('price_cents')->default(0);
            $table->text('billing_period')->default('monthly');
            $table->integer('max_users')->nullable();
            $table->integer('max_departments')->nullable();
            $table->jsonb('features')->default(DB::raw("'{}'::jsonb"));
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->timestampTz('updated_at')->default(DB::raw('now()'));

            $table->unique('name');
        });

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table subscription_plans add constraint subscription_plans_billing_period_check
              check (billing_period in ('monthly', 'yearly'))
        SQL);

        DB::connection('pgsql')->statement('alter table subscription_plans enable row level security');

        DB::connection('pgsql')->statement(<<<'SQL'
            create policy subscription_plans_select on subscription_plans for select
              using (auth_current_user_id() is not null)
        SQL);
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy subscription_plans_write on subscription_plans for all
              using (auth_is_richworks_super_admin())
              with check (auth_is_richworks_super_admin())
        SQL);

        DB::connection('pgsql')->statement('grant select, insert, update, delete on public.subscription_plans to authenticated');

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table companies
                add column if not exists subscription_plan_id uuid references subscription_plans(id),
                add column if not exists subscription_status text,
                add column if not exists subscription_current_period_end date
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            alter table companies add constraint companies_subscription_status_check
              check (subscription_status is null or subscription_status in ('trialing', 'active', 'past_due', 'canceled'))
        SQL);

        // companies_update's own RLS policy permits richworks_super_admin OR
        // an assigned platform_admin -- but subscription assignment is
        // "Control Centre" (Super Admin) authority specifically, narrower
        // than general company administration. Same shape as
        // prevent_self_role_change(): a BEFORE UPDATE trigger, not a second
        // RLS policy, since RLS can't express column-level restrictions.
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function prevent_non_super_admin_subscription_change()
            returns trigger
            language plpgsql
            security definer
            set search_path to 'public'
            as $$
            begin
                if (
                    new.subscription_plan_id is distinct from old.subscription_plan_id
                    or new.subscription_status is distinct from old.subscription_status
                    or new.subscription_current_period_end is distinct from old.subscription_current_period_end
                ) and not coalesce(auth_is_richworks_super_admin(), false) then
                    raise exception 'only a Richworks Super Admin may change a company''s subscription';
                end if;
                return new;
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_prevent_non_super_admin_subscription_change
                before update on companies
                for each row execute function prevent_non_super_admin_subscription_change()
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop trigger if exists trg_prevent_non_super_admin_subscription_change on companies');
        DB::connection('pgsql')->statement('drop function if exists prevent_non_super_admin_subscription_change()');

        DB::connection('pgsql')->statement('alter table companies drop constraint if exists companies_subscription_status_check');
        DB::connection('pgsql')->statement(<<<'SQL'
            alter table companies
                drop column if exists subscription_current_period_end,
                drop column if exists subscription_status,
                drop column if exists subscription_plan_id
        SQL);

        Schema::connection('pgsql')->dropIfExists('subscription_plans');
    }
};
