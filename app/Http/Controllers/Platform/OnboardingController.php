<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\CompanyLifecycleService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The Company Onboarding Wizard — the sequence a Super Admin walks a new
 * client through so that "add the next client" is a repeatable flow rather
 * than engineering work: Company details -> Import Employees -> Validate
 * spreadsheet -> Create users/accounts -> Assign roles -> Configure KPI
 * structure -> Apply KPI template -> Configure reporting hierarchy ->
 * Configure ANIRA -> Configure Telegram -> Review -> Activate. ("Create
 * Company" itself happens on the Companies index page, before this
 * company-scoped wizard exists at all; "Company Admin" and "Departments" are
 * two more foundational steps kept from the wizard's original checklist —
 * neither was named in that list, but nothing else in it is reachable
 * without them, so dropping them would leave a real gap, not a simplification.)
 *
 * Deliberately doesn't track step completion as a separately-clicked
 * checkbox — every step's "done" state is computed live from the same rows
 * `CompanyController::activate()` itself checks, so it can never drift out of
 * sync with reality (a department deleted after being counted "done"
 * immediately shows as not-done again), and "resume" falls out for free:
 * `currentStepKey` is whichever built step isn't done yet, and that's where
 * a returning Center Admin lands.
 *
 * Three steps — reporting hierarchy, ANIRA config, Telegram config — have no
 * backend at all yet and are marked `builtYet: false` rather than faking a
 * working feature (the same honesty the original "Import" step used before
 * Excel import was actually built). They never block Review/Activate.
 * Reporting hierarchy has no home in the schema at all (no manager/reports-to
 * relationship exists anywhere in the Platform's tables); ANIRA has no
 * Platform-facing feature yet (`AuthorizedDataScope` is prepared
 * infrastructure, not a UI); Telegram's only existing code is the legacy
 * integration that `SupabaseService::TENANT_OWNED_TABLES` now specifically
 * blocks from touching real Platform data — building this step for real
 * means designing an RLS-safe replacement, not reviving that.
 *
 * Depends on the `onboarding_status`/`display_name`/`primary_color` columns
 * from `2026_08_14_030000_add_onboarding_lifecycle_to_companies.php`.
 */
class OnboardingController extends Controller
{
    use PlatformAuthorization;

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', [
            'id' => 'eq.' . $company,
            'select' => '*',
        ]);

        abort_if(!$companyRow, 404);

        $departmentCount = count($supabase->get('departments', [
            'company_id' => 'eq.' . $company,
            'select' => 'id',
        ]));

        $companyUsers = $supabase->get('company_users', [
            'company_id' => 'eq.' . $company,
            'status' => 'eq.active',
            'select' => 'role',
        ]);

        $hasActiveAdmin = collect($companyUsers)->contains(fn ($cu) => $cu['role'] === 'company_admin');
        $nonAdminUserCount = collect($companyUsers)->filter(fn ($cu) => $cu['role'] !== 'company_admin')->count();

        $kpiCount = count($supabase->get('kpis', [
            'company_id' => 'eq.' . $company,
            'select' => 'id',
        ]));

        $hasBranding = !empty($companyRow['display_name'] ?? null) || !empty($companyRow['primary_color'] ?? null);

        $employeeBatches = $supabase->get('import_batches', [
            'company_id' => 'eq.' . $company,
            'type' => 'eq.employees',
            'select' => 'id,filename,status',
            'order' => 'created_at.desc',
        ]);

        $hasImportedEmployees = count($employeeBatches) > 0;
        $hasValidatedSpreadsheet = collect($employeeBatches)->contains(fn ($b) => in_array($b['status'], ['validated', 'completed'], true));

        // Phase 8: if an Employees import has staged rows waiting for
        // accounts, link straight there instead of the generic "invite
        // manually" fallback — the most recent non-completed batch is
        // almost always the one still being worked through.
        $pendingEmployeeBatch = collect($employeeBatches)->firstWhere('status', 'validated');

        $steps = [
            ['key' => 'company', 'label' => 'Create Company', 'done' => true, 'builtYet' => true, 'href' => null],
            ['key' => 'details', 'label' => 'Company details', 'done' => $hasBranding, 'builtYet' => true, 'href' => null],
            ['key' => 'admin', 'label' => 'Company Admin', 'done' => $hasActiveAdmin, 'builtYet' => true, 'href' => '/platform/companies'],
            ['key' => 'departments', 'label' => 'Departments', 'done' => $departmentCount > 0, 'builtYet' => true, 'href' => "/platform/companies/{$company}/import"],
            ['key' => 'import_employees', 'label' => 'Import Employees', 'done' => $hasImportedEmployees, 'builtYet' => true, 'href' => "/platform/companies/{$company}/import"],
            ['key' => 'validate_spreadsheet', 'label' => 'Validate spreadsheet', 'done' => $hasValidatedSpreadsheet, 'builtYet' => true, 'href' => "/platform/companies/{$company}/import"],
            ['key' => 'create_users', 'label' => 'Create users/accounts', 'done' => $nonAdminUserCount > 0, 'builtYet' => true, 'href' => $pendingEmployeeBatch ? "/platform/companies/{$company}/import/{$pendingEmployeeBatch['id']}/users" : "/platform/companies/{$company}/departments"],
            ['key' => 'assign_roles', 'label' => 'Assign roles', 'done' => $nonAdminUserCount > 0, 'builtYet' => true, 'href' => "/platform/companies/{$company}/onboarding/assign-roles"],
            ['key' => 'kpi_structure', 'label' => 'Configure KPI structure', 'done' => $kpiCount > 0, 'builtYet' => true, 'href' => "/platform/companies/{$company}/kpis"],
            ['key' => 'apply_kpi_template', 'label' => 'Apply KPI template', 'done' => $kpiCount > 0, 'builtYet' => true, 'href' => "/platform/companies/{$company}/kpis"],
            ['key' => 'reporting_hierarchy', 'label' => 'Configure reporting hierarchy', 'done' => false, 'builtYet' => false, 'href' => "/platform/companies/{$company}/onboarding/reporting-hierarchy"],
            ['key' => 'anira_config', 'label' => 'Configure ANIRA', 'done' => false, 'builtYet' => false, 'href' => "/platform/companies/{$company}/onboarding/anira-config"],
            ['key' => 'telegram_config', 'label' => 'Configure Telegram', 'done' => false, 'builtYet' => false, 'href' => "/platform/companies/{$company}/onboarding/telegram-config"],
            ['key' => 'review', 'label' => 'Review', 'done' => $hasActiveAdmin && $departmentCount > 0 && $nonAdminUserCount > 0 && $kpiCount > 0, 'builtYet' => true, 'href' => null],
            ['key' => 'activate', 'label' => 'Activate Company', 'done' => $companyRow['status'] === 'active', 'builtYet' => true, 'href' => null],
        ];

        // Resume-at-first-incomplete: skip steps with no backend yet, since
        // they can never be "the thing blocking you right now."
        $currentStep = collect($steps)->first(fn ($s) => $s['builtYet'] && !$s['done']);

        $computedStatus = match (true) {
            $companyRow['status'] === 'active' => 'completed',
            $hasActiveAdmin && $departmentCount > 0 && $nonAdminUserCount > 0 && $kpiCount > 0 => 'review',
            $kpiCount > 0 => 'kpi_configured',
            $nonAdminUserCount > 0 => 'users_created',
            $departmentCount > 0 => 'data_imported',
            default => 'company_created',
        };

        $statusRank = [
            'not_started' => 0, 'company_created' => 1, 'data_imported' => 2, 'users_created' => 3,
            'kpi_configured' => 4, 'review' => 5, 'ready' => 6, 'completed' => 7,
        ];

        $currentRank = $statusRank[$companyRow['onboarding_status'] ?? 'not_started'] ?? 0;

        // Best-effort: advance the persisted status to reflect real
        // progress, but never move it backwards and never treat a write
        // failure here as fatal — the page below shows live-computed
        // progress regardless of whether this succeeds. This column is a
        // reporting aid for the Center's own dashboards, not the source of
        // truth for what's actually allowed (RLS + activate()'s own admin
        // check are).
        if (($statusRank[$computedStatus] ?? 0) > $currentRank) {
            try {
                $supabase->update('companies', ['id' => 'eq.' . $company], ['onboarding_status' => $computedStatus]);
                $companyRow['onboarding_status'] = $computedStatus;
            } catch (\Throwable) {
                // Non-critical, see above.
            }
        }

        // Same best-effort, monotonic spirit as the onboarding_status bump
        // above, but for the coarse `companies.status` lifecycle
        // (draft -> onboarding -> configuring -> active -> ...): once KPIs
        // exist, the company is past "just being set up" and into
        // "configuring for go-live." No-ops once activate()/suspend() have
        // already moved status past the pre-active stages.
        if ($kpiCount > 0) {
            try {
                CompanyLifecycleService::advanceTo($supabase, $company, 'configuring');
                $companyRow['status'] = $companyRow['status'] === 'onboarding' ? 'configuring' : $companyRow['status'];
            } catch (\Throwable) {
                // Non-critical, see above.
            }
        }

        return Inertia::render('Platform/Onboarding/Show', [
            'company' => $companyRow,
            'steps' => $steps,
            'currentStepKey' => $currentStep['key'] ?? null,
            'counts' => [
                'departments' => $departmentCount,
                'users' => $nonAdminUserCount,
                'kpis' => $kpiCount,
            ],
            'hasActiveAdmin' => $hasActiveAdmin,
            'canActivate' => $request->attributes->get('platformUser')['is_super_admin'] ?? false,
            'pendingEmployeeBatch' => $pendingEmployeeBatch,
        ]);
    }

    /**
     * "Assign roles" — reviews and adjusts what Phase 8's bulk account
     * creation assigned automatically (every imported employee lands as a
     * plain `employee` on the department's lowest-rank job-level role).
     * Grouped by department since that's the natural unit an admin thinks
     * in ("who on the Sales team should be an Executive").
     */
    public function assignRoles(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name']);
        abort_if(!$companyRow, 404);

        $departments = $supabase->get('departments', [
            'company_id' => 'eq.' . $company,
            'select' => 'id,name',
            'order' => 'name.asc',
        ]);

        $departmentIds = array_column($departments, 'id');

        $roles = empty($departmentIds)
            ? []
            : $supabase->get('roles', [
                'department_id' => 'in.(' . implode(',', $departmentIds) . ')',
                'select' => '*',
                'order' => 'rank.asc',
            ]);

        $members = empty($departmentIds)
            ? []
            : $supabase->get('department_users', [
                'department_id' => 'in.(' . implode(',', $departmentIds) . ')',
                'select' => 'user_id,department_id,role,role_id,users(name,email)',
            ]);

        return Inertia::render('Platform/Onboarding/AssignRoles', [
            'company' => $companyRow,
            'departments' => $departments,
            'roles' => $roles,
            'members' => $members,
        ]);
    }

    public function reportingHierarchy(Request $request, string $company)
    {
        return $this->comingSoon($request, $company, [
            'title' => 'Configure reporting hierarchy',
            'body' => 'The Platform schema has no manager/reports-to relationship yet — company_users and department_users record membership and role, but nothing records who reports to whom. Building this for real means a schema addition (likely a manager_id on department_users, plus the RLS policies and approval-routing logic that would depend on it), not a UI on top of an existing column. Skipping this step never blocks Review or Activate.',
        ]);
    }

    public function aniraConfig(Request $request, string $company)
    {
        return $this->comingSoon($request, $company, [
            'title' => 'Configure ANIRA',
            'body' => "ANIRA itself now exists for the Platform (/platform/anira) — every request resolves who's asking, which company, and what they're authorized to see via AuthorizedDataScope before anything reaches the model. What's still missing is PER-COMPANY configuration of it: no way yet to set a company-specific tone, disable ANIRA for a company, or choose a different model/system-prompt per client — it currently behaves identically for every company. Skipping this step never blocks Review or Activate.",
        ]);
    }

    public function telegramConfig(Request $request, string $company)
    {
        return $this->comingSoon($request, $company, [
            'title' => 'Configure Telegram',
            'body' => "Per-user Telegram linking now exists for the Platform (My Profile → Connect Telegram) and is fully tenant-aware — TelegramAuthorizedScope mints each linked user their own short-lived Supabase session and every reminder is built from AuthorizedDataScope's RLS-filtered result, so a suspended account or a suspended company stops receiving messages on its very next digest, with no separate step to remember. The legacy Telegram integration (employees/user_company_roles-based) remains untouched and still dead. What's still missing is PER-COMPANY configuration of this: no way yet to disable Telegram reminders for a company, customize their content, or point a company at its own bot. Skipping this step never blocks Review or Activate.",
        ]);
    }

    private function comingSoon(Request $request, string $company, array $copy)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name']);
        abort_if(!$companyRow, 404);

        return Inertia::render('Platform/Onboarding/ComingSoon', [
            'company' => $companyRow,
            'title' => $copy['title'],
            'body' => $copy['body'],
        ]);
    }
}
