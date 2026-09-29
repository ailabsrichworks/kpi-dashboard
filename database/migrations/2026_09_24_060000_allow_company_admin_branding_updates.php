<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lets a plain Company Admin (not just a Super Admin or an assigned Platform
 * Admin) update their own company's branding via the new Settings page
 * (`CompanyController::settings()`/`updateBranding()`) — previously
 * `companies_update`'s policy was `auth_is_richworks_super_admin() OR id IN
 * auth_platform_company_ids()`, which never included plain `company_admin`
 * membership (`auth_platform_company_ids()` is Platform-Admin-assignment-only
 * — confirmed by reading its definition), so a Company Admin's branding save
 * would have silently affected 0 rows under RLS even after the controller's
 * own `ensureCompanyAdmin()` check passed.
 *
 * Widening the policy outright would also let a Company Admin change `status`,
 * `code`, `name`, etc. via a raw API call, bypassing
 * `CompanyController::ALLOWED_TRANSITIONS`'s lifecycle state machine entirely
 * — RLS is this codebase's real security boundary (application checks are
 * documented as defense-in-depth, not a substitute), so that gap can't be
 * left to "the app controller wouldn't let you." Instead: the RLS policy
 * widens to `auth_can_administer_company(id)` (the same predicate
 * `ensureCompanyAdmin()` already mirrors), and a new `BEFORE UPDATE` trigger
 * enforces column-level restriction for the "company_admin but not
 * super-admin, not an assigned platform-admin" case — allowlisting
 * display_name/primary_color/secondary_color (plus updated_at) and rejecting
 * any other column change. Super Admins and assigned Platform Admins are
 * unaffected (checked first, return early) — this only narrows what a plain
 * Company Admin's own UPDATE can touch.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement('drop policy if exists companies_update on public.companies');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy companies_update on public.companies
                for update
                using (auth_can_administer_company(id))
                with check (auth_can_administer_company(id))
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function restrict_company_admin_to_branding()
            returns trigger
            language plpgsql
            security definer
            set search_path = public
            as $$
            begin
                if auth_is_richworks_super_admin()
                    or new.id in (select auth_platform_company_ids())
                then
                    return new;
                end if;

                if new.name is distinct from old.name
                    or new.code is distinct from old.code
                    or new.logo_url is distinct from old.logo_url
                    or new.status is distinct from old.status
                    or new.onboarding_status is distinct from old.onboarding_status
                    or new.activated_at is distinct from old.activated_at
                then
                    raise exception 'A Company Admin may only update branding (display_name, primary_color, secondary_color).';
                end if;

                return new;
            end;
            $$;
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger trg_restrict_company_admin_to_branding
                before update on public.companies
                for each row execute function restrict_company_admin_to_branding();
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop trigger if exists trg_restrict_company_admin_to_branding on public.companies');
        DB::connection('pgsql')->statement('drop function if exists restrict_company_admin_to_branding()');

        DB::connection('pgsql')->statement('drop policy if exists companies_update on public.companies');
        DB::connection('pgsql')->statement(<<<'SQL'
            create policy companies_update on public.companies
                for update
                using (auth_is_richworks_super_admin() or id in (select auth_platform_company_ids()))
                with check (auth_is_richworks_super_admin() or id in (select auth_platform_company_ids()))
        SQL);
    }
};
