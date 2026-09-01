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
  v_dept_a_child uuid;
  v_dept_a2 uuid;
  v_kpi_a2 uuid;
  v_auth_hr uuid := gen_random_uuid();
  v_user_hr uuid;
  v_goal_a uuid;
  v_kpi_child uuid;
  v_submission_id uuid;
  v_request_id uuid;
  v_request_id2 uuid;
  v_revision_id uuid;
  v_achievement numeric;
begin
  -- ---------------------------------------------------------------------
  -- Fixtures (run as the connecting superuser/owner -- RLS doesn't apply
  -- yet, which is fine, this is just test setup).
  -- ---------------------------------------------------------------------
  insert into companies (name, code) values ('RLS Test Co A', 'RLSTEST_A') returning id into v_company_a;
  insert into companies (name, code) values ('RLS Test Co B', 'RLSTEST_B') returning id into v_company_b;

  -- Inserted into auth.users only; on_auth_user_created (added
  -- 2026_08_28_100000) fires synchronously within this same transaction and
  -- creates the matching public.users row itself, exactly as it does in
  -- production -- the UPDATEs below just fill in the role/friendly-name
  -- this test wants, they don't create the row. (Before that migration
  -- existed, this fixture inserted both rows explicitly; now the trigger
  -- owns the insert and a second explicit INSERT would collide on
  -- users_auth_user_id_unique -- see the CI failure that caught this.)
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

  update users set name = 'RLS Test User A' where auth_user_id = v_auth_a returning id into v_user_a;
  update users set name = 'RLS Test User A2' where auth_user_id = v_auth_a2 returning id into v_user_a2;
  update users set name = 'RLS Test User B' where auth_user_id = v_auth_b returning id into v_user_b;
  update users set name = 'RLS Test Center Admin', role = 'richworks_super_admin' where auth_user_id = v_auth_center returning id into v_user_center;

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
  update users set name = 'RLS Test User A3 (suspended)' where auth_user_id = v_auth_a3 returning id into v_user_a3;
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

  -- ---------------------------------------------------------------------
  -- Scenarios 14-16: Performix Company Platform Phase 1 (organisation
  -- hierarchy + reporting structure), added
  -- 2026_08_28_010000_add_organisation_hierarchy_and_reporting.php.
  -- Company A was archived by scenario 13 -- reactivated here since these
  -- scenarios test triggers/RLS-widening independent of company lifecycle
  -- state, not suspension itself.
  -- ---------------------------------------------------------------------
  update companies set status = 'active' where id = v_company_a;

  insert into departments (company_id, name, code, unit_type, parent_department_id)
    values (v_company_a, 'RLS Test Dept A Child', 'RLSDEPTAC', 'team', v_dept_a) returning id into v_dept_a_child;

  -- Scenario 14a: a department cannot be its own parent.
  begin
    update departments set parent_department_id = id where id = v_dept_a;
    raise exception 'FAIL (14a): a department was made its own parent';
  exception
    when others then
      if SQLERRM like '%cannot be its own parent%' then
        raise notice 'PASS (14a): self-parenting rejected';
      else
        raise;
      end if;
  end;

  -- Scenario 14b: a deeper cycle (A -> Child -> back to A) must also be
  -- rejected, not just the direct self-parent case above.
  begin
    update departments set parent_department_id = v_dept_a_child where id = v_dept_a;
    raise exception 'FAIL (14b): a circular organisation hierarchy (A -> Child -> A) was accepted';
  exception
    when others then
      if SQLERRM like '%circular organisation hierarchy%' then
        raise notice 'PASS (14b): multi-level circular hierarchy rejected';
      else
        raise;
      end if;
  end;

  -- Scenario 14c: a department's parent must belong to the same company --
  -- Company B has no departments of its own in this fixture, so borrow
  -- v_dept_a_child's sibling relationship the other way: attempt to parent
  -- a Company B department under a Company A one.
  declare
    v_dept_b uuid;
  begin
    insert into departments (company_id, name, code) values (v_company_b, 'RLS Test Dept B', 'RLSDEPTB') returning id into v_dept_b;

    begin
      update departments set parent_department_id = v_dept_a where id = v_dept_b;
      raise exception 'FAIL (14c): a Company B department was parented under a Company A department';
    exception
      when others then
        if SQLERRM like '%must belong to the same company%' then
          raise notice 'PASS (14c): cross-company parent rejected';
        else
          raise;
        end if;
    end;
  end;

  -- Scenario 15a: an employee cannot be their own manager. v_user_a2 has no
  -- department_users row yet in this fixture (only v_user_a3 does, from
  -- scenario 10) -- create one first, or the UPDATE below would match zero
  -- rows and never actually exercise the trigger.
  insert into department_users (department_id, user_id) values (v_dept_a, v_user_a2);

  begin
    update department_users set manager_user_id = v_user_a2 where department_id = v_dept_a and user_id = v_user_a2;
    raise exception 'FAIL (15a): an employee was made their own manager';
  exception
    when others then
      if SQLERRM like '%cannot be their own manager%' then
        raise notice 'PASS (15a): self-management rejected';
      else
        raise;
      end if;
  end;

  -- Scenario 15b: a circular management chain (A2 manages A3, then A3 is
  -- made to manage A2) must be rejected.
  insert into department_users (department_id, user_id) values (v_dept_a_child, v_user_a3)
    on conflict (department_id, user_id) do nothing;
  update department_users set manager_user_id = v_user_a3 where department_id = v_dept_a and user_id = v_user_a2;

  begin
    update department_users set manager_user_id = v_user_a2 where department_id = v_dept_a_child and user_id = v_user_a3;
    raise exception 'FAIL (15b): a circular management chain (A2 -> A3 -> A2) was accepted';
  exception
    when others then
      if SQLERRM like '%circular management chain%' then
        raise notice 'PASS (15b): circular management chain rejected';
      else
        raise;
      end if;
  end;

  -- Scenario 16: 'hr' gets widened read access to department_users (the
  -- organisation-hierarchy migration's whole point), but NOT to
  -- kpi_submissions -- that's still gated by auth_can_view_company_wide(),
  -- which was deliberately left untouched so HR doesn't gain company-wide
  -- KPI visibility as a side effect.
  insert into departments (company_id, name, code) values (v_company_a, 'RLS Test Dept A2', 'RLSDEPTA2') returning id into v_dept_a2;
  -- A real membership row to look for -- without one, "hr sees 0 rows"
  -- would be indistinguishable from "there's simply nothing there."
  insert into department_users (department_id, user_id) values (v_dept_a2, v_user_a2);
  insert into kpis (company_id, name, target) values (v_company_a, 'RLS Test KPI A2', 100) returning id into v_kpi_a2;
  insert into kpi_submissions (company_id, department_id, kpi_id, value, submitted_by)
    values (v_company_a, v_dept_a2, v_kpi_a2, 10, v_user_a);

  insert into auth.users (
    id, instance_id, aud, role, email, encrypted_password,
    email_confirmed_at, created_at, updated_at, raw_app_meta_data, raw_user_meta_data
  ) values
    (v_auth_hr, '00000000-0000-0000-0000-000000000000', 'authenticated', 'authenticated',
     'rls-test-hr@example.invalid', crypt('rls-test-password', gen_salt('bf')), now(), now(), now(), '{}', '{}');
  update users set name = 'RLS Test HR' where auth_user_id = v_auth_hr returning id into v_user_hr;
  insert into company_users (company_id, user_id, role) values (v_company_a, v_user_hr, 'hr');
  insert into department_users (department_id, user_id, role) values (v_dept_a, v_user_hr, 'hr');

  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_hr)::text, true);
  execute 'set local role authenticated';

  select count(*) into v_count from department_users where department_id = v_dept_a2;
  if v_count = 0 then raise exception 'FAIL (16a): hr could not see department_users for a department they do not belong to'; end if;

  select count(*) into v_count from kpi_submissions where department_id = v_dept_a2;
  if v_count <> 0 then raise exception 'FAIL (16b): hr saw a KPI submission in a department they do not belong to — auth_can_view_company_wide() was widened for hr'; end if;

  execute 'reset role';
  raise notice 'PASS (16): hr sees company-wide department_users but not company-wide kpi_submissions';

  -- ---------------------------------------------------------------------
  -- Scenarios 17-19: Performix Company Platform Phase 2 (Company Goals +
  -- KPI cascade), added
  -- 2026_08_28_020000_add_company_goals_and_kpi_cascade.php. Trigger tests
  -- (17) run without a role switch, same as scenarios 14/15 -- BEFORE
  -- triggers fire regardless of RLS/role, so there's nothing to bypass.
  -- ---------------------------------------------------------------------
  insert into kpis (company_id, name, target, parent_kpi_id) values (v_company_a, 'RLS Test KPI Child', 50, v_kpi_a) returning id into v_kpi_child;

  -- Scenario 17a: a KPI cannot be its own parent.
  begin
    update kpis set parent_kpi_id = id where id = v_kpi_a;
    raise exception 'FAIL (17a): a KPI was made its own parent';
  exception
    when others then
      if SQLERRM like '%cannot be its own parent%' then
        raise notice 'PASS (17a): self-parenting KPI rejected';
      else
        raise;
      end if;
  end;

  -- Scenario 17b: a deeper cycle (A -> Child -> back to A).
  begin
    update kpis set parent_kpi_id = v_kpi_child where id = v_kpi_a;
    raise exception 'FAIL (17b): a circular KPI cascade (A -> Child -> A) was accepted';
  exception
    when others then
      if SQLERRM like '%circular KPI cascade%' then
        raise notice 'PASS (17b): multi-level circular KPI cascade rejected';
      else
        raise;
      end if;
  end;

  -- Scenario 17c: a KPI's parent must belong to the same company.
  begin
    update kpis set parent_kpi_id = v_kpi_b where id = v_kpi_a;
    raise exception 'FAIL (17c): a Company A KPI was parented under a Company B KPI';
  exception
    when others then
      if SQLERRM like '%parent must belong to the same company%' then
        raise notice 'PASS (17c): cross-company KPI parent rejected';
      else
        raise;
      end if;
  end;

  -- Scenario 18: a KPI's company_goal_id must belong to the same company.
  declare
    v_goal_b uuid;
  begin
    insert into company_goals (company_id, title) values (v_company_b, 'RLS Test Goal B') returning id into v_goal_b;

    begin
      update kpis set company_goal_id = v_goal_b where id = v_kpi_a;
      raise exception 'FAIL (18): a Company A KPI was linked to a Company B goal';
    exception
      when others then
        if SQLERRM like '%goal must belong to the same company%' then
          raise notice 'PASS (18): cross-company company_goal_id rejected';
        else
          raise;
        end if;
    end;
  end;

  -- Scenario 19: company_goals is readable company-wide (any active member,
  -- like departments_select) but writable only by whoever can administer
  -- the company -- v_user_a2 is a plain employee in Company A, not an admin.
  insert into company_goals (company_id, title) values (v_company_a, 'RLS Test Goal A') returning id into v_goal_a;

  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_b)::text, true);
  execute 'set local role authenticated';

  select count(*) into v_count from company_goals where id = v_goal_a;
  if v_count <> 0 then raise exception 'FAIL (19a): Company B user could read Company A''s goal'; end if;

  execute 'reset role';

  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a2)::text, true);
  execute 'set local role authenticated';

  select count(*) into v_count from company_goals where id = v_goal_a;
  if v_count = 0 then raise exception 'FAIL (19b): Company A''s own plain employee could not read their company''s own goal'; end if;

  begin
    insert into company_goals (company_id, title) values (v_company_a, 'Employee-created goal');
    raise exception 'FAIL (19c): a plain employee (not an admin) created a company goal';
  exception
    when insufficient_privilege then
      raise notice 'PASS (19c): non-admin company_goals insert rejected';
  end;

  execute 'reset role';
  raise notice 'PASS (19a/19b): company_goals is readable by any company member, cross-company read denied';

  -- ---------------------------------------------------------------------
  -- Scenario 20: kpi_calc_achievement() (the SQL port of
  -- KpiCalculationService::achievement()/kpiAchievement.ts, since a Postgres
  -- view can't call into either) must agree with the other two ports on the
  -- same inputs -- these three specific cases are the exact ones
  -- tests/Unit/KpiCalculationServiceTest.php asserts in PHP.
  -- ---------------------------------------------------------------------
  select kpi_calc_achievement(80, 100, null, 'higher_is_better') into v_achievement;
  if v_achievement <> 80.0 then raise exception 'FAIL (20a): kpi_calc_achievement higher_is_better below target got %, want 80', v_achievement; end if;

  select kpi_calc_achievement(575000, 500000, null, 'lower_is_better') into v_achievement;
  if abs(v_achievement - 86.96) > 0.01 then raise exception 'FAIL (20b): kpi_calc_achievement lower_is_better overshoot got %, want ~86.96 (spec Part 11''s own example -- must not read as "exceeded")', v_achievement; end if;

  select kpi_calc_achievement(1, null, null, 'binary_completion') into v_achievement;
  if v_achievement <> 100.0 then raise exception 'FAIL (20c): kpi_calc_achievement binary_completion got %, want 100', v_achievement; end if;

  raise notice 'PASS (20): kpi_calc_achievement() SQL port agrees with KpiCalculationService/kpiAchievement.ts on all 3 cross-checked cases';

  -- ---------------------------------------------------------------------
  -- Scenario 21: approval-gated kpi_submissions_update. v_user_a2 submits;
  -- an approval_requests/approval_request_steps pair is created by hand
  -- (mirroring what ApprovalRequestService::createRequest() would do) with
  -- v_user_a resolved as the CURRENT step's approver. Three checks: (a) the
  -- submitter themselves cannot decide their own submission (spec Part 8:
  -- self-approval is blocked at the data layer, not just the UI), (b) the
  -- actual resolved approver CAN -- proving the mechanism grants access, not
  -- just denies it, (c) a second, non-current step's resolved approver
  -- (here, deliberately Company B's own admin, so this also doubles as a
  -- tenant-isolation check) cannot act on a step that isn't current yet.
  -- ---------------------------------------------------------------------
  insert into kpi_submissions (
    company_id, department_id, kpi_id, value, submitted_by, status,
    revision_number, financial_year, period_type, period_number
  ) values (
    v_company_a, v_dept_a, v_kpi_a, 42, v_user_a2, 'pending_review',
    1, 2027, 'month', 1
  ) returning id into v_submission_id;

  insert into approval_requests (company_id, workflow_type, object_type, object_id, department_id, submitted_by)
  values (v_company_a, 'actual_submission', 'kpi_submission', v_submission_id, v_dept_a, v_user_a2)
  returning id into v_request_id;

  insert into approval_request_steps (request_id, step_order, approver_type, resolved_approver_user_id, status)
  values (v_request_id, 1, 'submitter_manager', v_user_a, 'pending');
  insert into approval_request_steps (request_id, step_order, approver_type, resolved_approver_user_id, status)
  values (v_request_id, 2, 'role', v_user_b, 'pending');

  -- (a) the submitter cannot decide their own submission.
  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a2)::text, true);
  execute 'set local role authenticated';

  update kpi_submissions set status = 'approved' where id = v_submission_id;
  get diagnostics v_rows = row_count;
  if v_rows <> 0 then raise exception 'FAIL (21a): a submitter approved/updated their own kpi_submission'; end if;

  execute 'reset role';

  -- (c) the step-2 approver (not yet current) cannot act early -- also
  -- cross-company, so this doubles as an isolation check.
  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_b)::text, true);
  execute 'set local role authenticated';

  update kpi_submissions set status = 'approved' where id = v_submission_id;
  get diagnostics v_rows = row_count;
  if v_rows <> 0 then raise exception 'FAIL (21c): a non-current step''s resolved approver updated the submission early'; end if;

  execute 'reset role';
  raise notice 'PASS (21a/21c): submitter cannot self-approve; a non-current step''s approver cannot act early';

  -- (b) the actual current-step resolved approver CAN.
  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a)::text, true);
  execute 'set local role authenticated';

  update kpi_submissions set status = 'approved', decided_by = v_user_a where id = v_submission_id;
  get diagnostics v_rows = row_count;
  if v_rows <> 1 then raise exception 'FAIL (21b): the correctly-resolved current-step approver could not decide the submission'; end if;

  execute 'reset role';
  raise notice 'PASS (21b): the resolved current-step approver can decide the submission';

  -- ---------------------------------------------------------------------
  -- Scenario 22: apply_approved_target_revision() re-checks authorization
  -- and status itself (it's SECURITY DEFINER, so outer RLS on `kpis` doesn't
  -- gate it) -- must reject a not-yet-approved revision, then reject an
  -- unrelated company's caller even once approved, then succeed for the
  -- actual company admin.
  -- ---------------------------------------------------------------------
  insert into kpi_target_revisions (company_id, kpi_id, old_target, new_target, reason, requested_by, effective_financial_year, status)
  values (v_company_a, v_kpi_a, 100, 250, 'RLS test revision', v_user_a, 2027, 'pending')
  returning id into v_revision_id;

  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a)::text, true);
  execute 'set local role authenticated';

  begin
    perform apply_approved_target_revision(v_revision_id);
    raise exception 'FAIL (22a): applied a target revision that was not yet approved';
  exception
    when others then
      if SQLERRM like '%is not approved%' then
        raise notice 'PASS (22a): applying a not-yet-approved target revision is rejected';
      else
        raise;
      end if;
  end;

  execute 'reset role';

  -- Approve it (as the superuser/table owner doing test setup, bypassing
  -- RLS deliberately here -- getting the row into 'approved' status is
  -- fixture setup, not the thing being tested).
  update kpi_target_revisions set status = 'approved' where id = v_revision_id;

  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_b)::text, true);
  execute 'set local role authenticated';

  begin
    perform apply_approved_target_revision(v_revision_id);
    raise exception 'FAIL (22b): an unrelated company''s user applied another company''s approved target revision';
  exception
    when others then
      if SQLERRM like '%not authorized%' then
        raise notice 'PASS (22b): an unrelated company cannot apply another company''s target revision';
      else
        raise;
      end if;
  end;

  execute 'reset role';

  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a)::text, true);
  execute 'set local role authenticated';

  perform apply_approved_target_revision(v_revision_id);

  select target into v_achievement from kpis where id = v_kpi_a;
  if v_achievement <> 250 then raise exception 'FAIL (22c): apply_approved_target_revision did not update kpis.target (got %)', v_achievement; end if;

  execute 'reset role';
  raise notice 'PASS (22c): the company''s own admin can apply an approved target revision, and kpis.target is actually updated';

  -- ---------------------------------------------------------------------
  -- Scenario 23: company_performance_periods (period lifecycle overrides)
  -- can only be written by whoever can administer the company.
  -- ---------------------------------------------------------------------
  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a2)::text, true);
  execute 'set local role authenticated';

  begin
    insert into company_performance_periods (company_id, financial_year, period_type, period_number, status)
    values (v_company_a, 2027, 'quarter', 3, 'closed');
    raise exception 'FAIL (23a): a plain employee closed a company performance period';
  exception
    when insufficient_privilege then
      raise notice 'PASS (23a): non-admin cannot set a period''s lifecycle status';
  end;

  execute 'reset role';

  perform set_config('request.jwt.claims', json_build_object('sub', v_auth_a)::text, true);
  execute 'set local role authenticated';

  insert into company_performance_periods (company_id, financial_year, period_type, period_number, status, set_by)
  values (v_company_a, 2027, 'quarter', 3, 'closed', v_user_a);

  execute 'reset role';
  raise notice 'PASS (23b): a company admin can set a period''s lifecycle status';

  raise notice '=== ALL RLS ISOLATION SCENARIOS COMPLETED ===';

  -- Force a rollback even on full success -- this is disposable fixture
  -- data, never meant to persist.
  raise exception 'INTENTIONAL ROLLBACK: test fixtures are disposable, discarding them now.';
end $$;

rollback;
