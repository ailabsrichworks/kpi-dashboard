<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Mail\PlatformInviteMail;
use App\Services\SupabaseAuthService;
use App\Services\SupabaseService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

/**
 * Department management, scoped to one company at a time (the URL's
 * {company} segment). As with CompanyController, every read/write goes
 * through the caller's own RLS-scoped token — the app-level check in
 * `ensureCompanyAdmin()` (PlatformAuthorization) exists so a non-member
 * never even loads this page, not because RLS needs the help to keep data
 * safe.
 */
class DepartmentController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        $this->logAdminAccessIfCrossCompany($request, 'view_departments', $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', [
            'id' => 'eq.' . $company,
            'select' => 'id,name,code',
        ]);

        $departments = $supabase->get('departments', [
            'company_id' => 'eq.' . $company,
            'select' => '*',
            'order' => 'created_at.desc',
        ]);

        $departmentIds = array_column($departments, 'id');

        $members = empty($departmentIds)
            ? []
            : $supabase->get('department_users', [
                'department_id' => 'in.(' . implode(',', $departmentIds) . ')',
                'select' => 'department_id,user_id,role,role_id,manager_user_id,position_title,join_date,employment_status,employment_type,employee_grade,location,mobile_number,users(name,email)',
            ]);

        $roles = empty($departmentIds)
            ? []
            : $supabase->get('roles', [
                'department_id' => 'in.(' . implode(',', $departmentIds) . ')',
                'select' => '*',
                'order' => 'rank.asc',
            ]);

        // Suspend/reactivate (requirement #8's "user suspension") acts on
        // `company_users.status`, not anything `department_users` carries —
        // fetched separately and keyed by user_id so the department member
        // list can show each person's actual membership status.
        // Includes name/email so the reporting-structure UI can offer a
        // company-wide manager picker (a manager may sit in a different
        // department from the person reporting to them).
        $memberStatus = $supabase->get('company_users', [
            'company_id' => 'eq.' . $company,
            'select' => 'user_id,role,status,users(name,email)',
        ]);

        return Inertia::render('Platform/Departments/Index', [
            'company' => $companyRow,
            'departments' => $departments,
            'members' => $members,
            'roles' => $roles,
            'memberStatus' => collect($memberStatus)->keyBy('user_id'),
        ]);
    }

    public function store(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'unit_type' => 'nullable|in:business_unit,branch,department,team',
            'parent_department_id' => 'nullable|uuid',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        // Confirm the chosen parent actually belongs to this company before
        // it ever reaches Postgres — trg_prevent_circular_department_hierarchy
        // would reject a cross-company parent anyway, but this gives a clean
        // error instead of a raw trigger exception.
        if ($request->filled('parent_department_id')) {
            $parentBelongsToCompany = $supabase->first('departments', [
                'id' => 'eq.' . $request->parent_department_id,
                'company_id' => 'eq.' . $company,
                'select' => 'id',
            ]);

            if (!$parentBelongsToCompany) {
                return back()->withInput()->with('error', 'Choose a parent unit that belongs to this company.');
            }
        }

        try {
            $newDepartment = $supabase->insert('departments', [
                'company_id' => $company,
                'name' => $request->name,
                'code' => strtoupper($request->code),
                'unit_type' => $request->unit_type ?? 'department',
                'parent_department_id' => $request->parent_department_id,
            ]);

            // Guardrail: a department with zero roles has nothing to assign
            // new members to. Seeding one default role keeps self-service
            // unblocked — a company that wants a "Lead" tier adds it
            // afterward; one that doesn't can leave this as-is forever.
            $supabase->insert('roles', [
                'department_id' => $newDepartment[0]['id'],
                'label' => 'Member',
                'rank' => 0,
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not create department: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'create_department', $company, null, ['name' => $request->name], 'department', $newDepartment[0]['id']);
        } catch (\Throwable) {
            return back()->with('error', 'Department was created, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Department "' . $request->name . '" created.');
    }

    /**
     * Invites a Department Admin or Department User. Two links are created
     * beyond the auth account itself: a `company_users` row (so they count as
     * a member of the company at all) and a `department_users` row (so they
     * are scoped to this specific department). Both go through the caller's
     * own token — a Company Admin inviting someone into a department they
     * don't actually own gets rejected by RLS on the `departments` lookup
     * inside `department_users_insert`, not by anything in this method.
     */
    public function storeUser(Request $request, string $company, string $department, SupabaseAuthService $auth, SupabaseService $privileged)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'role' => 'required|in:executive,employee,hr,hod,manager',
            'role_id' => 'required|uuid',
            'manager_user_id' => 'nullable|uuid',
            'position_title' => 'nullable|string|max:255',
            'join_date' => 'nullable|date',
            'employment_type' => 'nullable|string|max:100',
            'employee_grade' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'mobile_number' => 'nullable|string|max:50',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        // role_id is the company's own configurable job-level role (§3 of the
        // blueprint) — confirm it actually belongs to this department rather
        // than trusting a client-supplied id blindly. RLS would reject the
        // department_users insert below regardless, but this gives a clean
        // error instead of a raw constraint failure.
        $roleBelongsToDepartment = $supabase->first('roles', [
            'id' => 'eq.' . $request->role_id,
            'department_id' => 'eq.' . $department,
            'select' => 'id',
        ]);

        if (!$roleBelongsToDepartment) {
            return back()->withInput()->with('error', 'Choose a role that belongs to this department.');
        }

        if ($request->filled('manager_user_id')) {
            $managerIsCompanyMember = $supabase->first('company_users', [
                'company_id' => 'eq.' . $company,
                'user_id' => 'eq.' . $request->manager_user_id,
                'select' => 'user_id',
            ]);

            if (!$managerIsCompanyMember) {
                return back()->withInput()->with('error', 'Choose a manager who is already a member of this company.');
            }
        }

        // Phase 11: as in CompanyController::storeAdmin(), no password is
        // generated here — the invitee sets their own when they accept.
        try {
            $invite = $auth->generateInviteLink($request->email, [
                'name' => $request->name,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not create the account: ' . $e->getMessage());
        }

        // Supabase's generate_link response has `id` at the top level, not
        // nested under a `user` key (confirmed against the real API) — see
        // the matching fix and note in CompanyController::storeAdmin().
        $newUserId = $invite['id'] ?? null;

        if (!$newUserId) {
            return back()->with('error', 'Invite was created but the new account id was missing — try again.');
        }

        // Looked up via the privileged client, not the caller's own RLS-scoped
        // one: `users_select` only lets a caller see people already linked to
        // one of their companies — which this brand-new user isn't, yet. The
        // ensureCompanyAdmin() check above is what actually authorizes this.
        // generateInviteLink() already gives us the id synchronously; this
        // wait is only for the `on_auth_user_created` trigger to finish
        // writing the matching `public.users` row `company_users` needs.
        //
        // Filtered on `auth_user_id`, not `id` — see the note in
        // CompanyController::storeAdmin(); `$newUserId` is auth.users.id,
        // stored on `public.users` as `auth_user_id`, a separate column from
        // `public.users.id`.
        $newUser = $privileged->firstEventually('users', [
            'auth_user_id' => 'eq.' . $newUserId,
            'select' => 'id',
        ]);

        if (!$newUser) {
            return back()->with('error', 'Account was created but its profile row never appeared — try adding them again in a moment.');
        }

        try {
            $supabase->insert('company_users', [
                'company_id' => $company,
                'user_id' => $newUser['id'],
                'role' => $request->role,
            ]);

            $supabase->insert('department_users', [
                'department_id' => $department,
                'user_id' => $newUser['id'],
                'role' => $request->role,
                'role_id' => $request->role_id,
                'manager_user_id' => $request->manager_user_id,
                'position_title' => $request->position_title,
                'join_date' => $request->join_date,
                'employment_type' => $request->employment_type,
                'employee_grade' => $request->employee_grade,
                'location' => $request->location,
                'mobile_number' => $request->mobile_number,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Account was created but could not be linked to the department: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'invite_department_user', $company, $newUser['id'], [
                'department_id' => $department,
                'email' => $request->email,
                'role' => $request->role,
            ], 'department_user', $newUser['id']);
        } catch (\Throwable) {
            return back()->with('error', 'Account was created and linked, but the action could not be logged — contact support before continuing.');
        }

        $companyRow = $supabase->first('companies', [
            'id' => 'eq.' . $company,
            'select' => 'name',
        ]);

        $roleLabel = match ($request->role) {
            'executive' => 'an Executive',
            'hr' => 'an HR member',
            'hod' => 'a Head of Department',
            'manager' => 'a Manager',
            default => 'an Employee',
        };

        try {
            Mail::to($request->email)->send(new PlatformInviteMail(
                route('platform.invite.accept', ['token' => $invite['hashed_token']]),
                $request->name,
                $companyRow['name'] ?? 'Performix',
                $roleLabel,
            ));
        } catch (\Throwable $e) {
            return back()->with('error', 'Account was created and linked, but the invite email could not be sent: ' . $e->getMessage());
        }

        return back()->with('success', "Invite sent to {$request->email}.");
    }

    /**
     * Changes an EXISTING member's company-tier role (executive/employee)
     * and/or department-tier job-level `role_id` — the piece that was
     * missing for the onboarding wizard's "Assign roles" step: every
     * imported/invited employee lands as a plain `employee` on the
     * department's lowest-rank role (Phase 8), and nothing before this let
     * an admin promote a few of them (to `executive`, or a higher job-level
     * role) without re-inviting them from scratch.
     *
     * Both `company_users.role` and `department_users.role` are updated
     * together — `storeUser()` already sets them identically at invite time,
     * and letting them drift apart (someone `executive` at the company tier
     * but `employee` at the department tier, or vice versa) has no
     * meaningful interpretation in this schema.
     */
    public function updateUserRole(Request $request, string $company, string $department, string $user)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'role' => 'required|in:executive,employee,hr,hod,manager',
            'role_id' => 'required|uuid',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $roleBelongsToDepartment = $supabase->first('roles', [
            'id' => 'eq.' . $request->role_id,
            'department_id' => 'eq.' . $department,
            'select' => 'id',
        ]);

        if (!$roleBelongsToDepartment) {
            return back()->with('error', 'Choose a role that belongs to this department.');
        }

        $before = $supabase->first('department_users', [
            'department_id' => 'eq.' . $department,
            'user_id' => 'eq.' . $user,
            'select' => 'role,role_id',
        ]);

        try {
            $supabase->update('department_users', [
                'department_id' => 'eq.' . $department,
                'user_id' => 'eq.' . $user,
            ], [
                'role' => $request->role,
                'role_id' => $request->role_id,
            ]);

            $supabase->update('company_users', [
                'company_id' => 'eq.' . $company,
                'user_id' => 'eq.' . $user,
            ], [
                'role' => $request->role,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not update this member\'s role: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'update_user_role', $company, $user, [
                'department_id' => $department,
            ], 'department_user', $user, $before, ['role' => $request->role, 'role_id' => $request->role_id]);
        } catch (\Throwable) {
            return back()->with('error', 'Role was updated, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Role updated.');
    }

    /**
     * Changes just a member's manager (and/or the plain profile fields
     * introduced alongside it) without touching their role — deliberately
     * separate from updateUserRole() above, which is about access tier, not
     * reporting line. Backs the onboarding wizard's "Configure reporting
     * hierarchy" step, previously an honest `builtYet: false` placeholder
     * (see OnboardingController) because no manager column existed anywhere
     * in the Platform schema.
     */
    public function updateReporting(Request $request, string $company, string $department, string $user)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'manager_user_id' => 'nullable|uuid',
            'position_title' => 'nullable|string|max:255',
            'join_date' => 'nullable|date',
            'employment_status' => 'nullable|in:active,inactive,on_leave,resigned,terminated,archived',
            'employment_type' => 'nullable|string|max:100',
            'employee_grade' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'mobile_number' => 'nullable|string|max:50',
        ]);

        if ($request->filled('manager_user_id') && $request->manager_user_id === $user) {
            return back()->with('error', 'An employee cannot be their own manager.');
        }

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        if ($request->filled('manager_user_id')) {
            $managerIsCompanyMember = $supabase->first('company_users', [
                'company_id' => 'eq.' . $company,
                'user_id' => 'eq.' . $request->manager_user_id,
                'select' => 'user_id',
            ]);

            if (!$managerIsCompanyMember) {
                return back()->with('error', 'Choose a manager who is already a member of this company.');
            }
        }

        $before = $supabase->first('department_users', [
            'department_id' => 'eq.' . $department,
            'user_id' => 'eq.' . $user,
            'select' => 'manager_user_id,position_title,join_date,employment_status,employment_type,employee_grade,location,mobile_number',
        ]);

        $after = [
            'manager_user_id' => $request->manager_user_id,
            'position_title' => $request->position_title,
            'join_date' => $request->join_date,
            'employment_status' => $request->employment_status ?? ($before['employment_status'] ?? 'active'),
            'employment_type' => $request->employment_type,
            'employee_grade' => $request->employee_grade,
            'location' => $request->location,
            'mobile_number' => $request->mobile_number,
        ];

        try {
            $supabase->update('department_users', [
                'department_id' => 'eq.' . $department,
                'user_id' => 'eq.' . $user,
            ], $after);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not update this member\'s reporting details: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'update_reporting', $company, $user, [
                'department_id' => $department,
            ], 'department_user', $user, $before, $after);
        } catch (\Throwable) {
            return back()->with('error', 'Reporting details were updated, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Reporting details updated.');
    }

    /**
     * Suspends a company member's ACCOUNT within this company —
     * `company_users.status`, not their platform-wide `users.status` (a
     * bigger, Super-Admin-scale action this controller has no business
     * performing). `department_users` carries no status of its own
     * (confirmed against the foundational schema: only `company_users` has
     * one), so this is the one flag that actually governs "is this person an
     * active member of this company" — RLS's `auth_company_ids()`/
     * `auth_role_in_company()` already exclude non-active memberships the
     * same way they exclude suspended companies.
     *
     * Guards against zeroing out a company's admins the same way
     * `prevent_zero_company_admins` guards DELETE/role-change on
     * `company_users` — that trigger doesn't fire on a status-only UPDATE, so
     * without this check a Company Admin could suspend the last other admin
     * (or themselves) and leave the company with no one able to manage it.
     */
    public function suspendUser(Request $request, string $company, string $user)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $before = $supabase->first('company_users', [
            'company_id' => 'eq.' . $company,
            'user_id' => 'eq.' . $user,
            'select' => 'role,status',
        ]);

        if (!$before) {
            return back()->with('error', 'That person is not a member of this company.');
        }

        if ($before['role'] === 'company_admin') {
            $otherActiveAdmins = $supabase->get('company_users', [
                'company_id' => 'eq.' . $company,
                'role' => 'eq.company_admin',
                'status' => 'eq.active',
                'user_id' => 'neq.' . $user,
                'select' => 'user_id',
            ]);

            if (empty($otherActiveAdmins)) {
                return back()->with('error', 'Cannot suspend the last active Company Admin for this company.');
            }
        }

        try {
            $supabase->update('company_users', [
                'company_id' => 'eq.' . $company,
                'user_id' => 'eq.' . $user,
            ], ['status' => 'suspended']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not suspend this member: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'suspend_user', $company, $user, [], 'company_user', $user, ['status' => $before['status']], ['status' => 'suspended']);
        } catch (\Throwable) {
            return back()->with('error', 'Member was suspended, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Member suspended — they have lost access to this company.');
    }

    public function reactivateUser(Request $request, string $company, string $user)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $before = $supabase->first('company_users', [
            'company_id' => 'eq.' . $company,
            'user_id' => 'eq.' . $user,
            'select' => 'status',
        ]);

        if (!$before) {
            return back()->with('error', 'That person is not a member of this company.');
        }

        try {
            $supabase->update('company_users', [
                'company_id' => 'eq.' . $company,
                'user_id' => 'eq.' . $user,
            ], ['status' => 'active']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not reactivate this member: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'reactivate_user', $company, $user, [], 'company_user', $user, ['status' => $before['status']], ['status' => 'active']);
        } catch (\Throwable) {
            return back()->with('error', 'Member was reactivated, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Member reactivated.');
    }
}