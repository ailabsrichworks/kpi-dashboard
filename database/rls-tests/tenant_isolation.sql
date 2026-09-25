-- Performix Platform Blueprint, Phase 3: automated RLS isolation test suite.
--
-- Exercises spec requirement #36 directly against Postgres: Company A can
-- only see Company A, Company B can only see Company B, the Center (Super
-- Admin) can see both, forged company_id values are rejected on insert, and
-- cross-company updates are silently no-ops (0 rows affected, not an error --
-- that's what RLS does to an UPDATE whose USING clause excludes every
-- matching row).
--
-- WHY THIS ISN'T A PHPUNIT TEST: config/database.php's `pgsql` connection
-- always reads SUPABASE_DB_URL, and phpunit.xml only swaps the *default*
-- connection (sqlite) for testing -- it does not, and cannot without
-- reworking that config, redirect `pgsql`. A PHPUnit test that touched the
-- `pgsql` connection would run against the real production database on every
-- `php artisan test`, which is exactly the risk this file is designed to
-- avoid. Run this script by hand instead, with `psql`, against a disposable
-- target -- ideally a Supabase preview branch created off the same schema,
-- never the production project directly (mlggobjdsicuokblbsww as of
-- 2026-08-18; previously eavmrurxxdxbufkkzlup).
--
-- HOW auth.uid() IS SIMULATED: Supabase's `auth.uid()` reads the caller's
-- user id out of the `request.jwt.claims` session setting -- exactly what a
-- real PostgREST request populates from the caller's JWT. `set_config(...)`
-- below fakes that same setting directly in a plain psql session, and
-- `SET LOCAL ROLE authenticated` downgrades out of the superuser/table-owner
-- role migrations run as (RLS is bypassed for superusers and table owners
-- by default -- without this, every query below would silently ignore RLS
-- entirely and the test would pass even if every policy were broken).
--
-- Everything happens inside one transaction that always rolls back at the
-- end (or on failure) -- no fixture data is left behind either way, in
-- disposable Test-Co-A/B naming makes it obvious even if a rollback fails
-- for some external reason.

begin;

do $$
declare
  v_company_a uuid;
  v_company_b uuid;
  v_dept_a uuid;
  v_kpi_a uuid;
  v_kpi_b uuid;
  v_auth_a uuid := gen_random_uuid();
  v_auth_a2 uuid := gen_random_uuid();
  v_auth_b uuid := gen_random_uuid();
  v_auth_center uuid := gen_random_uuid();
  v_user_a uuid;
  v_user_a2 uuid;
  v_user_b uuid;
  v_user_center uuid;
  v_count integer;
  v_rows integer;
  v_auth_a3 uuid := gen_random_uuid();
  v_user_a3 uuid;
  v_kpi_restricted uuid;
begin
  -- ---------------------------------------------------------------------
  -- Fixtures (run as the connecting superuser/owner -- RLS doesn't apply
  -- yet, which is fine, this is just test setup).
  -- ---------------------------------------------------------------------
  insert into companies (name, code) values ('RLS Test Co A', 'RLSTEST_A') returning id into v_company_a;
  insert into companies (name, code) values ('RLS Test Co B', 'RLSTEST_B') returning id into v_company_b;

  -- Inserted directly into both auth.users and public.users rather than
  -- relying on the on_auth_user_created trigger: that trigger is documented
  -- (SupabaseUserService::firstEventually()'s docblock) as writing the
  -- public.users row asynchronously, which this synchronous test can't wait
  -- on. Testing RLS policies doesn't require exercising the signup trigger.
  insert into auth.users (
    id, instance_id, aud, role, email, encrypted_password,
    email_confirmed_at, created_at, updated_at, raw_app_meta_data, raw_user_meta_data
  ) values
    (v_auth_a, '00000000-0000-0000-0000-000000000000', 'authenticated', 'authenticated',
     'rls-test-a@example.invalid', crypt('rls-test-password', gen_salt('bf')), now(), now(), now(), '{}', '{}'),
    (v_auth_a2, '00000000-0000-0000-0000-000000000000', 'authenticated', 'authenticated',
     'rls-test-a2@example.invalid', crypt('rls-test-password', gen_salt('bf')), now(), now(), now(), '{}', '{}'),
    (v_auth_b, '00000000-0000-0000-0000-000000000000', 'authenticated', 'authenticated',
     'rls-test-b@example.invalid', crypt('rls-test-password', gen_salt('bf')), now(), now(), now(), '{}', '{}'),
    (v_auth_center, '00000000-0000-0000-0000-000000000000', 'authenticated', 'authenticated',
     'rls-test-center@example.invalid', crypt('rls-test-password', gen_salt('bf')), now(), now(), now(), '{}', '{}');

  insert into users (auth_user_id, name, email, role) values (v_auth_a, 'RLS Test User A', 'rls-test-a@example.invalid', 'member') returning id into v_user_a;
  insert into users (auth_user_id, name, email, role) values (v_auth_a2, 'RLS Test User A2', 'rls-test-a2@example.invalid', 'member') returning id into v_user_a2;
  insert into users (auth_user_id, name, email, role) values (v_auth_b, 'RLS Test User B', 'rls-test-b@example.invalid', 'member') returning id into v_user_b;
  insert into users (auth_user_id, name, email, role) values (v_auth_center, 'RLS Test Center Admin', 'rls-test-center@example.invalid', 'richworks_super_admin') returning id into v_user_center;

  insert into company_users (company_id, user_id, role) values (v_company_a, v_user_a, 'company_admin');
  -- Second Company A member -- needed to exercise users_select's
  -- "company_admin can see everyone in their company" branch (scenario 8),
  -- which a single-user company can't exercise at all.
  insert into company_users (company_id, user_id, role) values (v_company_a, v_user_a2, 'employee');
  insert into company_users (company_id, user_id, role) values (v_company_b, v_user_b, 'company_admin');

  insert into departments (company_id, name, code) values (v_company_a, 'RLS Test Dept A', 'RLSDEPTA') returning id into v_dept_a;

  insert into kpis (company_id, name, target) values (v_company_a, 'RLS Test KPI A', 100) returning id into v_kpi_a;
  insert into kpis (company_id, name, target) values (v_company_b, 'RLS Test KPI B', 100) returning id into v_kpi_b;

  -- ---------------------------------------------------------------------
  -- Scenario 1: Company A → Company A data ✓, Company B data ✗
  -- ---------------------------------------------------------------------
  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a)::text, true);
  execute 'set local role authenticated';

  select count(*) into v_count from companies where id = v_company_a;
  if v_count <> 1 then raise exception 'FAIL (1a): Company A user cannot see their own company'; end if;

  select count(*) into v_count from companies where id = v_company_b;
  if v_count <> 0 then raise exception 'FAIL (1b): Company A user can see Company B — isolation breach'; end if;

  select count(*) into v_count from kpis where company_id = v_company_b;
  if v_count <> 0 then raise exception 'FAIL (1c): Company A user can see Company B''s KPI — isolation breach'; end if;

  execute 'reset role';
  raise notice 'PASS (1): Company A isolation holds';

  -- ---------------------------------------------------------------------
  -- Scenario 2: Company B → Company B data ✓, Company A data ✗ (symmetric)
  -- ---------------------------------------------------------------------
  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_b)::text, true);
  execute 'set local role authenticated';

  select count(*) into v_count from companies where id = v_company_b;
  if v_count <> 1 then raise exception 'FAIL (2a): Company B user cannot see their own company'; end if;

  select count(*) into v_count from companies where id = v_company_a;
  if v_count <> 0 then raise exception 'FAIL (2b): Company B user can see Company A — isolation breach'; end if;

  execute 'reset role';
  raise notice 'PASS (2): Company B isolation holds';

  -- ---------------------------------------------------------------------
  -- Scenario 3: Center → Company A ✓, Company B ✓
  -- ---------------------------------------------------------------------
  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_center)::text, true);
  execute 'set local role authenticated';

  select count(*) into v_count from companies where id in (v_company_a, v_company_b);
  if v_count <> 2 then raise exception 'FAIL (3): Center cannot see both test companies'; end if;

  execute 'reset role';
  raise notice 'PASS (3): Center cross-company access holds';

  -- ---------------------------------------------------------------------
  -- Scenario 4: forged company_id rejected on insert. department_id
  -- belongs to Company A, but company_id below is forged to Company B --
  -- kpi_submissions_insert's WITH CHECK re-derives company_id from the
  -- department row, so this must fail regardless of the value submitted.
  -- ---------------------------------------------------------------------
  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a)::text, true);
  execute 'set local role authenticated';

  begin
    insert into kpi_submissions (company_id, department_id, kpi_id, value, submitted_by)
    values (v_company_b, v_dept_a, v_kpi_a, 50, v_user_a);
    raise exception 'FAIL (4): forged company_id on kpi_submissions was accepted';
  exception
    when insufficient_privilege then
      raise notice 'PASS (4): forged company_id on kpi_submissions rejected';
  end;

  execute 'reset role';

  -- ---------------------------------------------------------------------
  -- Scenario 5: Company A cannot update Company B's KPI. RLS makes this an
  -- UPDATE that matches zero rows, not a thrown error -- assert on
  -- row_count, not on an exception.
  -- ---------------------------------------------------------------------
  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a)::text, true);
  execute 'set local role authenticated';

  update kpis set target = 999 where id = v_kpi_b;
  get diagnostics v_rows = row_count;
  if v_rows <> 0 then raise exception 'FAIL (5): Company A user updated Company B''s KPI'; end if;

  execute 'reset role';
  raise notice 'PASS (5): cross-company update is a no-op, not a leak';

  -- ---------------------------------------------------------------------
  -- Scenario 6: delete protection. No DELETE policy exists on `kpis` at
  -- all (confirmed against 2026_08_12_000000_create_platform_foundation_schema.php)
  -- -- RLS denies by default, so even the owning Company Admin can't
  -- delete their own KPI today. Documenting this as a confirmed gap
  -- (Blueprint §16) rather than assuming it's a bug: whether KPIs should
  -- be soft-deleted via `status` instead of hard-deleted is a product
  -- decision, not something this test should silently paper over.
  --
  -- IMPORTANT: a missing RLS policy for a given command does NOT raise
  -- `insufficient_privilege` (that's only for table-level GRANT failures).
  -- For DELETE/UPDATE, a table with RLS enabled and zero policies for that
  -- command evaluates its implicit USING clause as `false`, so the DELETE
  -- runs without error and simply matches zero rows — exactly like
  -- scenario 5's cross-company UPDATE above. An earlier version of this
  -- scenario caught `insufficient_privilege` and treated "no exception" as
  -- "delete succeeded," which is wrong: it would have reported a real
  -- delete policy as absent even when Postgres denied every row. Found by
  -- actually running this against real Postgres, not by re-reading the
  -- policy SQL — checking the row count, the same way scenario 5 already
  -- does, is what actually distinguishes the two cases.
  -- ---------------------------------------------------------------------
  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a)::text, true);
  execute 'set local role authenticated';

  begin
    delete from kpis where id = v_kpi_a;
    get diagnostics v_rows = row_count;

    if v_rows = 0 then
      raise notice 'CONFIRMED (6): no delete policy on kpis — even the owning Company Admin''s DELETE matched zero rows. Matches Blueprint §16; not treated as a failure.';
    else
      raise notice 'NOTE (6): Company A admin WAS able to delete their own KPI (% row(s)) — a delete policy exists now; update this comment and the Blueprint if that''s an intentional change.', v_rows;
    end if;
  exception
    when insufficient_privilege then
      raise notice 'CONFIRMED (6): no delete policy on kpis — DELETE denied outright (permission denied) for the owning Company Admin. Matches Blueprint §16; not treated as a failure.';
  end;

  execute 'reset role';

  -- ---------------------------------------------------------------------
  -- Scenario 7: cross-tenant row movement. `company_id` must be immutable
  -- after creation (2026_08_14_080000_restore_immutability_after_recursion_fix.php)
  -- -- attempting to move Company A's own KPI into Company B by updating
  -- company_id must be rejected by the BEFORE UPDATE trigger, not silently
  -- ignored and not merely blocked by RLS (which would report 0 rows
  -- affected rather than an error, and wouldn't prove the trigger itself is
  -- still attached). Must run before scenario 11 suspends Company A --
  -- once RLS itself excludes the row, this couldn't tell "trigger rejected
  -- it" apart from "RLS never selected it in the first place."
  -- ---------------------------------------------------------------------
  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a)::text, true);
  execute 'set local role authenticated';

  begin
    update kpis set company_id = v_company_b where id = v_kpi_a;
    raise exception 'FAIL (7): Company A''s own KPI was moved to Company B — company_id is not immutable';
  exception
    when others then
      if SQLERRM like '%company_id cannot be changed%' then
        raise notice 'PASS (7): company_id is immutable — cross-tenant row movement rejected';
      else
        raise;
      end if;
  end;

  execute 'reset role';

  -- ---------------------------------------------------------------------
  -- Scenario 8: direct cross-tenant insert. Unlike scenario 4 (which
  -- forges company_id on kpi_submissions while department_id still points
  -- at Company A, exercising the derive-from-parent logic), this attempts
  -- the simpler, more direct attack: a Company A admin inserting a brand
  -- new `kpis` row with company_id set straight to Company B. kpis_insert's
  -- `with check (auth_can_administer_company(company_id))` must reject it
  -- regardless of the fact that the caller legitimately administers a
  -- *different* company.
  -- ---------------------------------------------------------------------
  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a)::text, true);
  execute 'set local role authenticated';

  begin
    insert into kpis (company_id, name, target) values (v_company_b, 'Forged Company B KPI', 50);
    raise exception 'FAIL (8): Company A admin inserted a KPI directly into Company B';
  exception
    when insufficient_privilege then
      raise notice 'PASS (8): direct cross-tenant KPI insert rejected';
  end;

  execute 'reset role';

  -- ---------------------------------------------------------------------
  -- Scenario 9: role escalation. `prevent_self_role_change()`
  -- (2026_08_17_160000_fix_users_update_self_recursion.php) must block a
  -- user from changing their OWN `users.role` (the platform tier), even
  -- though the row is otherwise theirs to update (e.g. via the Telegram
  -- linking self-service flow this trigger was written for). Legitimate
  -- admin-driven promotions of *other* users are a different code path
  -- (NEW.auth_user_id <> the acting admin's own auth.uid()) and are not
  -- what this trigger — or this scenario — is about.
  -- ---------------------------------------------------------------------
  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a)::text, true);
  execute 'set local role authenticated';

  begin
    update users set role = 'richworks_super_admin' where id = v_user_a;
    raise exception 'FAIL (9): Company A admin self-escalated their own platform role to richworks_super_admin';
  exception
    when others then
      if SQLERRM like '%Cannot change your own role%' then
        raise notice 'PASS (9): self role escalation blocked';
      else
        raise;
      end if;
  end;

  execute 'reset role';

  -- ---------------------------------------------------------------------
  -- Scenario 10: individual-user suspension bypass via auth_department_ids()
  -- (2026_08_18_000000_fix_auth_department_ids_suspension_bypass.php).
  -- auth_company_ids()/auth_role_in_company() have always checked
  -- `company_users.status = 'active'`, but auth_department_ids() never did
  -- -- only the company's own status was excluded. Every policy that ORs in
  -- `department_id in (select auth_department_ids())` inherited that gap:
  -- department_users_select, kpi_access_grants_select, reports_select
  -- /insert, and (via auth_can_view_kpi()'s department-grant branch)
  -- kpis_select and kpi_submissions_insert. A suspended Employee whose
  -- department had an explicit `kpi_access_grants.department_id` grant kept
  -- read (and submission-insert) access to that KPI after being suspended.
  -- Must also run before scenario 11 suspends the whole of Company A --
  -- once the COMPANY is suspended, everyone in it loses access regardless
  -- of their own individual status, which would make this scenario
  -- indistinguishable from testing company-level suspension instead.
  -- ---------------------------------------------------------------------
  insert into auth.users (id, instance_id, aud, role, email, encrypted_password, email_confirmed_at, created_at, updated_at, raw_app_meta_data, raw_user_meta_data)
    values (v_auth_a3, '00000000-0000-0000-0000-000000000000', 'authenticated', 'authenticated', 'rls-test-a3@example.invalid', crypt('rls-test-password', gen_salt('bf')), now(), now(), now(), '{}', '{}');
  insert into users (auth_user_id, name, email, role) values (v_auth_a3, 'RLS Test User A3 (suspended)', 'rls-test-a3@example.invalid', 'member') returning id into v_user_a3;
  insert into company_users (company_id, user_id, role, status) values (v_company_a, v_user_a3, 'employee', 'suspended');
  insert into department_users (department_id, user_id, company_id, role) values (v_dept_a, v_user_a3, v_company_a, 'employee');

  insert into kpis (company_id, name, target, visibility) values (v_company_a, 'RLS Test Restricted KPI', 100, 'restricted') returning id into v_kpi_restricted;
  insert into kpi_access_grants (company_id, kpi_id, department_id, granted_by) values (v_company_a, v_kpi_restricted, v_dept_a, v_user_a);

  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a3)::text, true);
  execute 'set local role authenticated';

  select count(*) into v_count from kpis where id = v_kpi_restricted;
  if v_count <> 0 then raise exception 'FAIL (10a): suspended employee can still see a KPI via their department''s access grant'; end if;

  select count(*) into v_count from kpi_access_grants where department_id = v_dept_a;
  if v_count <> 0 then raise exception 'FAIL (10b): suspended employee can still read kpi_access_grants for their department'; end if;

  select count(*) into v_count from department_users where user_id = v_user_a2;
  if v_count <> 0 then raise exception 'FAIL (10c): suspended employee can still see a fellow (active) department member''s row'; end if;

  execute 'reset role';
  raise notice 'PASS (10): individually-suspended user loses department-scoped access, not just company-wide access';

  -- ---------------------------------------------------------------------
  -- Scenario 11: suspending a company actually revokes its own users'
  -- access (2026_08_14_060000_enforce_company_suspension_in_rls.php) --
  -- not just a cosmetic status label. Requires companies.status to allow
  -- 'suspended' (2026_08_14_030000's check constraint) -- run that
  -- migration first, or this UPDATE itself will fail the constraint.
  --
  -- Also baselines users_select's "company_admin sees their own company's
  -- members" branch before suspension -- v_user_a (company_admin) must be
  -- able to see v_user_a2 (a plain employee) right now, so scenario 12
  -- below (same query, after suspension) is a real regression check and not
  -- just an already-broken query returning zero for unrelated reasons.
  -- ---------------------------------------------------------------------
  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a)::text, true);
  execute 'set local role authenticated';

  select count(*) into v_count from users where id = v_user_a2;
  if v_count <> 1 then raise exception 'FAIL (11 baseline): Company A admin cannot see a fellow Company A member before suspension'; end if;

  execute 'reset role';

  update companies set status = 'suspended' where id = v_company_a;

  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a)::text, true);
  execute 'set local role authenticated';

  select count(*) into v_count from companies where id = v_company_a;
  if v_count <> 0 then raise exception 'FAIL (11a): suspended Company A''s own user can still see their company'; end if;

  select count(*) into v_count from kpis where company_id = v_company_a;
  if v_count <> 0 then raise exception 'FAIL (11b): suspended Company A''s own user can still see their KPIs'; end if;

  execute 'reset role';
  raise notice 'PASS (11): suspension actually revokes access, not just a status label';

  -- ---------------------------------------------------------------------
  -- Scenario 12: users_select's company_admin branch also respects
  -- suspension (2026_08_14_090000_fix_users_select_suspension_bypass.php).
  -- Before that fix, this raw self-join bypassed auth_company_ids() et al.
  -- entirely -- a suspended company's own admin could still read every
  -- user row in their company (name/email/role), directly contradicting
  -- CompanyController::suspend()'s "its users have lost access" message.
  -- ---------------------------------------------------------------------
  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a)::text, true);
  execute 'set local role authenticated';

  select count(*) into v_count from users where id = v_user_a2;
  if v_count <> 0 then raise exception 'FAIL (12): suspended Company A''s admin can still read a fellow member''s user row'; end if;

  execute 'reset role';
  raise notice 'PASS (12): users_select no longer bypasses suspension';

  -- ---------------------------------------------------------------------
  -- Scenario 13: 'archived' locks a company out exactly like 'suspended'
  -- (2026_08_17_130000_add_company_lifecycle_states.php). Requires that
  -- migration to have run -- before it, auth_company_ids()/auth_role_in_company()
  -- /auth_department_ids() only excluded ('suspended', 'inactive'), so an
  -- archived company's own users kept full access (nothing had ever set
  -- 'archived' in practice, which is exactly how that gap went unnoticed).
  -- Company A is already 'suspended' from scenario 11 -- transitioning it
  -- straight to 'archived' (mirroring CompanyController::archive()'s
  -- allowed-from list, which includes 'suspended') must not accidentally
  -- restore access on the way through.
  -- ---------------------------------------------------------------------
  update companies set status = 'archived' where id = v_company_a;

  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a)::text, true);
  execute 'set local role authenticated';

  select count(*) into v_count from companies where id = v_company_a;
  if v_count <> 0 then raise exception 'FAIL (13a): archived Company A''s own user can still see their company'; end if;

  select count(*) into v_count from kpis where company_id = v_company_a;
  if v_count <> 0 then raise exception 'FAIL (13b): archived Company A''s own user can still see their KPIs'; end if;

  select count(*) into v_count from users where id = v_user_a2;
  if v_count <> 0 then raise exception 'FAIL (13c): archived Company A''s admin can still read a fellow member''s user row'; end if;

  execute 'reset role';
  raise notice 'PASS (13): archived locks a company out exactly like suspended';

  raise notice '=== ALL RLS ISOLATION SCENARIOS COMPLETED ===';

  -- Force a rollback even on full success -- this is disposable fixture
  -- data, never meant to persist.
  raise exception 'INTENTIONAL ROLLBACK: test fixtures are disposable, discarding them now.';
end $$;

rollback;
