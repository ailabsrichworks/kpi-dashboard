<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Same root cause as the two `2026_09_24_0{1,2}0000` grant migrations and
 * `2026_09_24_030000` (service_role grants) — this brand-new Supabase project
 * had its Platform schema applied via a direct Postgres connection
 * (`php artisan migrate`), not through Supabase's own dashboard/CLI, which
 * skips whatever normally gets provisioned alongside it. This time it isn't a
 * missing GRANT, it's a missing trigger that this codebase's own comments
 * have always assumed exists: `BootstrapSuperAdmin`'s docblock says "the
 * auth.users trigger auto-creates the matching `users` row a moment later",
 * `CompanyController::storeAdmin()`/`DepartmentController::storeUser()`/
 * `UserCreationController::store()` all poll `public.users` for a row they
 * expect an `on_auth_user_created` trigger to have written — but no migration
 * anywhere in `database/migrations/` ever creates that trigger or its
 * function. `2026_08_12_000000_create_platform_foundation_schema.php` (itself
 * written *retroactively* against an already-live project, per its own
 * docblock) documents the `auth_user_id` foreign key but never the trigger
 * that populates it — on the original production project this was written
 * against, that trigger was evidently created directly in the Supabase
 * dashboard/SQL editor at some point outside any migration, so the gap was
 * invisible until a genuinely fresh project tried to replay migration
 * history alone.
 *
 * Confirmed as a real, live production bug: inviting a Company Admin via
 * `/platform/companies/{company}/admins` created a real `auth.users` row
 * (confirmed directly against the database) but `public.users` never
 * gained a matching row, so `CompanyController::storeAdmin()`'s poll
 * (`firstEventually`) timed out and the admin never got linked to the
 * company at all — "Admin account was created but its profile row never
 * appeared." The identical poll exists in `DepartmentController::storeUser()`
 * and `UserCreationController::store()` (bulk import account creation), so
 * every "invite a new person" flow in the whole Platform was broken by this,
 * not just company admin invites.
 *
 * `security definer` + `set search_path = public` follows Supabase's own
 * documented pattern for this exact trigger (a function owned by a role with
 * INSERT rights on `public.users`, invoked by `auth.users`' insert regardless
 * of who's asking). `on conflict (auth_user_id) do nothing` guards against a
 * retried trigger body, not a realistic data collision — `auth.users.email`
 * is already unique, so two different auth rows sharing a `public.users`
 * email can't happen upstream of this.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function public.handle_new_platform_user()
            returns trigger
            language plpgsql
            security definer
            set search_path = public
            as $$
            begin
              insert into public.users (auth_user_id, name, email)
              values (
                new.id,
                coalesce(new.raw_user_meta_data->>'name', new.email),
                new.email
              )
              on conflict (auth_user_id) do nothing;
              return new;
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            drop trigger if exists on_auth_user_created on auth.users
        SQL);

        DB::connection('pgsql')->statement(<<<'SQL'
            create trigger on_auth_user_created
              after insert on auth.users
              for each row execute function public.handle_new_platform_user()
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop trigger if exists on_auth_user_created on auth.users');
        DB::connection('pgsql')->statement('drop function if exists public.handle_new_platform_user()');
    }
};
