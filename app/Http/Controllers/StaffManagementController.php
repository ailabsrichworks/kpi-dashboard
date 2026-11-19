<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffManagementController extends Controller
{
    // Same gate as OrgChartController/StaffHistoryController -- HR and BTS
    // specifically. Every action below (not just index) calls this
    // independently, since these are write routes, not just a read-only
    // page a sidebar link happens to hide.
    private function requireHrBtsAccess(): void
    {
        if (!$this->isCrossCompanyMonitorSession()) {
            abort(403, 'This page is only accessible to BTS and Corporate Governance & HR.');
        }
    }

    public const ROLES = ['EXECUTIVE', 'MANAGER', 'VP', 'SLT'];

    private function companies(SupabaseService $supabase): array
    {
        return $supabase->get('companies', ['select' => 'code,name', 'order' => 'name.asc']) ?? [];
    }

    private function resolveCompanyCode(Request $request, array $companies): string
    {
        $requested = strtoupper(trim($request->query('company', $request->input('company', ''))));
        $valid     = collect($companies)->pluck('code')->map(fn ($c) => strtoupper($c))->all();

        return in_array($requested, $valid, true) ? $requested : strtoupper(session('company_code'));
    }

    public function index(Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login')->with('error', 'Please log in first.');
        }

        $this->requireHrBtsAccess();

        $companies   = $this->companies($supabase);
        $companyCode = $this->resolveCompanyCode($request, $companies);

        // Active staff only -- an inactive/resigned staff member's data is
        // now edited through Staff History instead (its own Edit modal on
        // the history record, plus the "Staff Status Selepas Ini" action),
        // so there's no longer a reason for them to also appear here.
        $employees = $supabase->get('employees', [
            'company_code' => 'eq.' . $companyCode,
            'is_active'    => 'eq.true',
            'select'       => 'id,employee_id,short_name,full_name,email,role,position,department_code,is_active,join_date',
            'order'        => 'short_name.asc',
        ]) ?? [];

        // Same cross-company-monitor roster exclusion used on Org Chart /
        // Staff History / Staff Summary -- a BTS/HR viewer's own row only
        // shows up here as part of a company that isn't their default one
        // if it's genuinely that company's own staff.
        $empIds = collect($employees)->pluck('id')->filter()->values()->toArray();
        if (!empty($empIds)) {
            $roles = $supabase->get('user_company_roles', [
                'company_code' => 'eq.' . $companyCode,
                'employee_id'  => 'in.(' . implode(',', $empIds) . ')',
                'select'       => 'employee_id,is_default',
            ]);
            $isDefaultByEmployeeId = collect($roles)->pluck('is_default', 'employee_id');
            $employees = array_values(array_filter($employees, function ($emp) use ($isDefaultByEmployeeId) {
                return $isDefaultByEmployeeId->get($emp['id'], true) !== false;
            }));
        }

        $departments    = $supabase->get('departments', [
            'company_code' => 'eq.' . $companyCode,
            'select'       => 'code,name',
        ]) ?? [];
        $deptNameByCode = collect($departments)->pluck('name', 'code')->map(fn ($name, $code) => $name ?: $code);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $needle    = mb_strtolower($search);
            $employees = array_values(array_filter($employees, function ($emp) use ($needle) {
                $haystack = mb_strtolower(($emp['short_name'] ?? '') . ' ' . ($emp['full_name'] ?? '') . ' ' . ($emp['employee_id'] ?? ''));
                return str_contains($haystack, $needle);
            }));
        }

        foreach ($employees as &$emp) {
            $emp['department_name'] = $deptNameByCode->get($emp['department_code'] ?? '', $emp['department_code'] ?? '-');
        }
        unset($emp);

        return view('staff-management.index', [
            'today'        => now()->timezone('Asia/Kuala_Lumpur')->format('d M Y'),
            'companyCode'  => $companyCode,
            'companies'    => $companies,
            'employees'    => $employees,
            'search'       => $search,
        ]);
    }

    public function edit(string $id, Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login')->with('error', 'Please log in first.');
        }

        $this->requireHrBtsAccess();

        $employee = $supabase->get('employees', ['id' => 'eq.' . $id, 'select' => '*']);
        $employee = $employee[0] ?? null;

        if (!$employee) {
            abort(404, 'Staff not found.');
        }

        $companies = $this->companies($supabase);

        // Every department, across every company, grouped in the view by
        // company_code -- lets HR move someone to a different company and
        // pick that company's own department in the same form, without a
        // JS-driven cascading select. The server re-validates the pair
        // matches on submit regardless of what the dropdown showed.
        $allDepartments = $supabase->get('departments', ['select' => 'code,name,company_code', 'order' => 'company_code.asc,name.asc']) ?? [];

        // Every employee, across every company, excluding this one --
        // same reasoning for manager/VP/reports-to: pick from the right
        // company's roster once company_code is decided, re-checked on
        // submit.
        $allEmployees = $supabase->get('employees', [
            'select' => 'id,short_name,full_name,role,company_code,department_code',
            'order'  => 'company_code.asc,short_name.asc',
        ]) ?? [];
        $allEmployees = array_values(array_filter($allEmployees, fn ($e) => $e['id'] !== $id));

        return view('staff-management.edit', [
            'companyCode'    => strtoupper(session('company_code')),
            'employee'       => $employee,
            'companies'      => collect($companies),
            'allDepartments' => collect($allDepartments),
            'allEmployees'   => collect($allEmployees),
            'roles'          => self::ROLES,
        ]);
    }

    public function update(string $id, Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login')->with('error', 'Please log in first.');
        }

        $this->requireHrBtsAccess();

        $original = $supabase->get('employees', ['id' => 'eq.' . $id, 'select' => '*']);
        $original = $original[0] ?? null;

        if (!$original) {
            abort(404, 'Staff not found.');
        }

        $validator = Validator::make($request->all(), [
            'employee_id'    => ['required', 'string', 'max:50'],
            'salutation'     => ['nullable', 'string', 'max:30'],
            'short_name'     => ['required', 'string', 'max:100'],
            'full_name'      => ['required', 'string', 'max:150'],
            'email'          => ['nullable', 'email', 'max:150'],
            'role'           => ['required', 'string', 'in:' . implode(',', self::ROLES)],
            'position'       => ['nullable', 'string', 'max:150'],
            'company_code'   => ['required', 'string'],
            'department_code'=> ['required', 'string'],
            'manager_id'     => ['nullable', 'uuid'],
            'vp_id'          => ['nullable', 'uuid'],
            'reports_to_id'  => ['nullable', 'uuid'],
            'join_date'      => ['nullable', 'date'],
            'hr_access'      => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $data = $validator->validated();
        $companyCode = strtoupper($data['company_code']);

        $companies    = $this->companies($supabase);
        $validCompany = in_array($companyCode, collect($companies)->pluck('code')->map(fn ($c) => strtoupper($c))->all(), true);
        if (!$validCompany) {
            return back()->withInput()->withErrors(['company_code' => 'Invalid company.']);
        }

        // department_code has a real FK into departments.code -- must be
        // validated against the SUBMITTED company, not the original one, or
        // this patch fails with a raw Postgres 23503.
        $deptMatch = $supabase->get('departments', [
            'company_code' => 'eq.' . $companyCode,
            'code'         => 'eq.' . $data['department_code'],
            'select'       => 'code',
        ]);
        if (empty($deptMatch)) {
            return back()->withInput()->withErrors(['department_code' => 'This department does not exist in the selected company.']);
        }

        $movedCompany = strtoupper($original['company_code']) !== $companyCode;

        $managerId   = $data['manager_id'] ?? null;
        $vpId        = $data['vp_id'] ?? null;
        $reportsToId = $data['reports_to_id'] ?? null;

        if ($movedCompany) {
            // Whoever was picked in manager/VP/reports-to belonged to the
            // OLD company's roster -- carrying them over after a company
            // move would silently create a cross-company reporting link
            // that doesn't resolve to anything real. Cleared here,
            // deliberately, same as the Amie RGHB move earlier: HR re-opens
            // this same edit page afterwards to assign a manager/VP from
            // the new company.
            $managerId = $vpId = $reportsToId = null;
        } else {
            foreach (['manager_id' => $managerId, 'vp_id' => $vpId, 'reports_to_id' => $reportsToId] as $field => $value) {
                if ($value === null) {
                    continue;
                }
                if ($value === $id) {
                    return back()->withInput()->withErrors([$field => 'Staff cannot report to themselves.']);
                }
                $match = $supabase->get('employees', ['id' => 'eq.' . $value, 'company_code' => 'eq.' . $companyCode, 'select' => 'id']);
                if (empty($match)) {
                    return back()->withInput()->withErrors([$field => 'The selected staff member is not in the same company.']);
                }
            }
        }

        $supabase->patch('employees', ['id' => 'eq.' . $id], [
            'employee_id'     => $data['employee_id'],
            'salutation'      => $data['salutation'] ?? null,
            'short_name'      => $data['short_name'],
            'full_name'       => $data['full_name'],
            'email'           => $data['email'] ?? null,
            'role'            => $data['role'],
            'position'        => $data['position'] ?? null,
            'company_code'    => $companyCode,
            'department_code' => $data['department_code'],
            'manager_id'      => $managerId,
            'vp_id'           => $vpId,
            'reports_to_id'   => $reportsToId,
            'join_date'       => $data['join_date'] ?? null,
            'hr_access'       => $request->boolean('hr_access'),
            // is_active is deliberately not editable here -- only Staff
            // History can deactivate/reactivate a staff member now, since
            // that's where the reason/event for it gets recorded.
        ]);

        $successMessage = 'Staff data updated successfully.';
        if ($movedCompany) {
            $successMessage .= ' Company was changed -- please re-assign Manager/VP/Reports To for the new company.';
        }

        return redirect()->route('staff-management.edit', $id)->with('success', $successMessage);
    }
}
