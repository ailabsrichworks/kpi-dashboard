<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * KPI submissions, scoped to one department. Viewing is open to
 * Company/Department Admins (company-wide) and to anyone assigned to this
 * specific department; submitting is narrower still — `kpi_submissions_insert`
 * requires the department itself be in the caller's own `auth_department_ids()`,
 * so even a Company Admin can't submit on a department's behalf unless they
 * are personally a member of it too. `ensureDepartmentAccess()` below calls
 * that same RPC to decide what the UI should offer, not to enforce anything —
 * the enforcement is the policy, this just avoids showing a submit form that
 * would fail anyway.
 */
class KpiSubmissionController extends Controller
{
    use LogsAdminActions;

    private function ensureDepartmentAccess(Request $request, string $company, string $department): array
    {
        $platformUser = $request->attributes->get('platformUser');

        if ($platformUser['is_super_admin'] ?? false) {
            return ['can_submit' => false];
        }

        $isCompanyOrDeptAdmin = collect($platformUser['company_memberships'] ?? [])
            ->contains(fn ($m) => $m['company_id'] === $company && in_array($m['role'], ['company_admin', 'slt', 'executive'], true));

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $myDepartmentIds = $supabase->rpc('auth_department_ids');
        $isDepartmentMember = in_array($department, $myDepartmentIds, true);

        abort_unless($isCompanyOrDeptAdmin || $isDepartmentMember, 403, 'You do not have access to this department.');

        return ['can_submit' => $isDepartmentMember];
    }

    public function index(Request $request, string $company, string $department)
    {
        $access = $this->ensureDepartmentAccess($request, $company, $department);

        $this->logAdminAccessIfCrossCompany($request, 'view_submissions', $company, ['department_id' => $department]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $departmentRow = $supabase->first('departments', [
            'id' => 'eq.' . $department,
            'select' => 'id,name,code,company_id',
        ]);

        $kpis = $supabase->get('kpis', [
            'company_id' => 'eq.' . $company,
            'status' => 'eq.active',
            'select' => 'id,name,target,unit,frequency',
        ]);

        $submissions = $supabase->get('kpi_submissions', [
            'department_id' => 'eq.' . $department,
            'select' => '*,kpis(name,unit,target),users(name)',
            'order' => 'submission_date.desc',
        ]);

        return Inertia::render('Platform/Submissions/Index', [
            'department' => $departmentRow,
            'kpis' => $kpis,
            'submissions' => $submissions,
            'canSubmit' => $access['can_submit'],
        ]);
    }

    public function store(Request $request, string $company, string $department)
    {
        $access = $this->ensureDepartmentAccess($request, $company, $department);

        abort_unless($access['can_submit'], 403, 'Only members of this department can submit KPI values.');

        $request->validate([
            'kpi_id' => 'required|uuid',
            'value' => 'required|numeric',
            'submission_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $platformUser = $request->attributes->get('platformUser');

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            // return=minimal (3rd arg false): `kpi_submissions_select` routes
            // through `auth_can_view_kpi()` on the parent KPI, which queries
            // back into `kpis` — requesting the row back via the default
            // `return=representation` makes PostgREST evaluate that SELECT
            // policy as part of this INSERT's RETURNING clause, which fails
            // for non-Super-Admin callers even though the INSERT's own WITH
            // CHECK independently evaluates true (same confirmed bug as
            // KpiController::store()). This meant every KPI submission by
            // anyone other than a Super Admin failed — the single most
            // frequently used write in the whole Platform. Nothing here uses
            // the returned row anyway.
            $supabase->insert('kpi_submissions', [
                'company_id' => $company,
                'department_id' => $department,
                'kpi_id' => $request->kpi_id,
                'value' => $request->value,
                'submission_date' => $request->submission_date,
                'submitted_by' => $platformUser['id'],
                'notes' => $request->notes,
            ], false);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not save submission: ' . $e->getMessage());
        }

        return back()->with('success', 'Submission saved.');
    }
}
