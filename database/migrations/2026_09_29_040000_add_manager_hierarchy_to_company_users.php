<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the one piece of schema two of the four confirmed legacy-parity gaps
 * both independently need: a real "who does this person report to" edge.
 * Legacy has this as `employees.manager_id`/`vp_id`/`reports_to_id` (a
 * 3-level chain used by both the appraisal appraiser-chain and Target
 * Linkages' target cascade) — the Platform has never had any equivalent,
 * confirmed repeatedly across this codebase's history (see
 * PlaceholderController's own prior docblocks for Target Linkages and
 * Performance Evaluation, both citing this exact gap).
 *
 * Deliberately ONE level, not legacy's three (manager/vp/reports_to):
 * `company_users.role` already has its own 4-tier axis (company_admin / slt
 * / executive / employee) for READ scope and administration — layering a
 * second, parallel 3-level seniority chain on top for every feature that
 * needs "who's above me" would be two competing hierarchy concepts in one
 * schema. `manager_user_id` covers what both consuming features actually
 * need (Performance's appraiser chain, Target Linkages' cascade target) with
 * one direct edge; a company with a deeper chain expresses it by chaining
 * `manager_user_id` itself (A reports to B, B reports to C), not by adding
 * more columns.
 *
 * `manager_user_id` references `users(id)` (not another `company_users` row)
 * so it survives a user's `company_users` row being recreated, matching how
 * `kpis.assigned_user_id` is already modeled. Tenant safety is enforced in
 * `validate_manager_same_company()`: the manager must be an ACTIVE member of
 * the SAME company_id as the report — `users` is a global identity table
 * with no company-membership guarantee of its own, the same reasoning every
 * other cross-reference to `users` in this codebase already applies
 * (kpis.assigned_user_id, job_descriptions.user_id, etc).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('company_users', function (Blueprint $table) {
            $table->uuid('manager_user_id')->nullable()->after('role');
            $table->foreign('manager_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('manager_user_id');
        });

        DB::connection('pgsql')->statement(<<<'SQL'
            create or replace function validate_manager_same_company()
            returns trigger
            language plpgsql
            security definer
            set search_path = public
            as $$
            begin
              if new.manager_user_id is null then
                return new;
              end if;

              if new.manager_user_id = new.user_id then
                raise exception 'A member cannot be their own manager.';
              end if;

              if not exists (
                select 1 from company_users
                where company_id = new.company_id
                  and user_id = new.manager_user_id
                  and status = 'active'
              ) then
                raise exception 'The chosen manager is not an active member of this company.';
              end if;

              return new;
            end;
            $$
        SQL);

        DB::connection('pgsql')->statement(
            'create trigger trg_validate_manager_same_company before insert or update on company_users for each row execute function validate_manager_same_company()'
        );
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('drop trigger if exists trg_validate_manager_same_company on company_users');
        DB::connection('pgsql')->statement('drop function if exists validate_manager_same_company()');

        Schema::connection('pgsql')->table('company_users', function (Blueprint $table) {
            $table->dropForeign(['manager_user_id']);
            $table->dropColumn('manager_user_id');
        });
    }
};
