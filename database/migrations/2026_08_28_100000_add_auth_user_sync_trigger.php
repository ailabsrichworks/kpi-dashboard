<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The standard Supabase "sync auth.users into a public profile table"
 * trigger — a well-known recipe (create a `security definer` function that
 * inserts into `public.users`, then an `AFTER INSERT` trigger on
 * `auth.users` calling it) that every piece of this codebase's own code
 * assumes already exists: `BootstrapSuperAdmin`, `CompanyController::storeAdmin()`,
 * `DepartmentController::storeUser()`, and `UserCreationController::store()`
 * all call `SupabaseAuthService::createUser()`/`inviteUser()` and then poll
 * `public.users` for a row that only this trigger ever creates.
 *
 * It was never in any migration file in this repo — confirmed by grepping
 * every migration for `on_auth_user_created`/`handle_new_user`/`auth.users`
 * before writing this one. The most likely explanation, consistent with
 * `2026_08_12_000000`'s own docblock ("written retroactively... every table
 * here already exists there"): whoever originally built this Platform set
 * this trigger up by hand once, directly in the Supabase SQL editor, on
 * whichever project it was actually verified against at the time, and it
 * was simply never captured back into a migration file the way the rest of
 * the schema eventually was. Surfaced for real by `platform:bootstrap-super-
 * admin` failing on `drmgngqgnqggfmtkqthb` — the first genuinely fresh
 * project this schema has ever been applied to from scratch.
 *
 * `on conflict (auth_user_id) do nothing` guards against the trigger firing
 * twice for the same auth user (Supabase can re-fire triggers on retried
 * webhooks in some configurations) without turning that into a hard error.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function public.handle_new_auth_user()
            returns trigger
            language plpgsql
            security definer
            set search_path to 'public'
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
                for each row execute procedure public.handle_new_auth_user()
        SQL);
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop trigger if exists on_auth_user_created on auth.users');
        DB::connection('pgsql')->statement('drop function if exists public.handle_new_auth_user()');
    }
};
