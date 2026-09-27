<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SupabaseService;
use Inertia\Inertia;

class AdminController extends Controller
{
    // Restricted to the BTS department — the only "admin" concept this app
    // has for cross-employee access. See resolveAppraiserLevel-style role
    // checks elsewhere; this one is department-based, not role-based.
    private function ensureBts(): void
    {
        if (strtoupper(trim(session('department_code') ?? '')) !== 'BTS') {
            abort(403, 'This area is restricted to BTS.');
        }
    }

    public function index(Request $request, SupabaseService $supabase)
    {
        $this->ensureBts();

        $employees = $supabase->get('employees', [
            'is_active' => 'eq.true',
            'select'    => 'id,employee_id,short_name,full_name,position,role,department_code,company_code',
            'order'     => 'full_name.asc',
        ]) ?? [];

        $search = trim($request->query('q', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $employees = array_values(array_filter($employees, function ($e) use ($needle) {
                return str_contains(mb_strtolower($e['full_name'] ?? ''), $needle)
                    || str_contains(mb_strtolower($e['short_name'] ?? ''), $needle)
                    || str_contains(mb_strtolower($e['employee_id'] ?? ''), $needle);
            }));
        }

        $departments = $supabase->get('departments', ['select' => 'code,name']) ?? [];
        $deptNames   = collect($departments)->pluck('name', 'code');

        return Inertia::render('Admin/ViewAs', [
            'employees' => $employees,
            'deptNames' => $deptNames,
            'search'    => $search,
        ]);
    }

    public function start(string $employeeId, SupabaseService $supabase)
    {
        $this->ensureBts();

        if (session('admin_impersonating')) {
            return back()->with('error', 'Return to your own account before viewing as someone else.');
        }

        $target = $supabase->first('employees', [
            'id'        => 'eq.' . $employeeId,
            'is_active' => 'eq.true',
            'select'    => '*',
        ]);

        if (!$target) {
            return back()->with('error', 'Employee not found.');
        }

        $company = $supabase->first('companies', ['code' => 'eq.' . $target['company_code'], 'select' => '*']);

        $adminEmployeeId = session('employee_uuid');
        $adminName       = session('full_name') ?? session('short_name') ?? 'BTS Admin';

        // Snapshot the admin's own session so "Return to my account" restores it exactly.
        session(['admin_original_session' => [
            'employee_uuid'         => session('employee_uuid'),
            'employee_id'           => session('employee_id'),
            'employee_name'         => session('employee_name'),
            'short_name'            => session('short_name'),
            'full_name'             => session('full_name'),
            'salutation'            => session('salutation'),
            'role'                  => session('role'),
            'hr_access'             => session('hr_access'),
            'position'              => session('position'),
            'department_code'       => session('department_code'),
            'company_code'          => session('company_code'),
            'company_name'          => session('company_name'),
            'company_display_name'  => session('company_display_name'),
        ]]);

        $log = $supabase->insert('admin_view_as_logs', [
            'admin_employee_id'  => $adminEmployeeId,
            'admin_name'         => $adminName,
            'target_employee_id' => $target['id'],
            'target_name'        => $target['full_name'] ?? $target['short_name'] ?? 'Unknown',
        ]);

        session([
            'admin_impersonating'  => true,
            'admin_view_as_log_id' => $log[0]['id'] ?? null,

            'employee_uuid'        => $target['id'],
            'employee_id'          => $target['employee_id'],
            'employee_name'        => $target['short_name'] ?? $target['full_name'] ?? 'User',
            'short_name'           => $target['short_name'] ?? null,
            'full_name'            => $target['full_name'] ?? null,
            'salutation'           => $target['salutation'] ?? null,
            'role'                 => $target['role'],
            'hr_access'            => (bool) ($target['hr_access'] ?? false),
            'position'             => $target['position'] ?? null,
            'department_code'      => $target['department_code'],
            'company_code'         => $target['company_code'],
            'company_name'         => $company['name'] ?? $target['company_code'],
            'company_display_name' => $company['display_name'] ?? ($company['name'] ?? $target['company_code']),
        ]);

        return redirect()->route('dashboard');
    }

    public function stop(SupabaseService $supabase)
    {
        $original = session('admin_original_session');

        if (!$original) {
            return redirect()->route('dashboard');
        }

        $logId = session('admin_view_as_log_id');
        if ($logId) {
            $supabase->safePatch('admin_view_as_logs', ['id' => 'eq.' . $logId], [
                'ended_at' => now()->toIso8601String(),
            ]);
        }

        session($original);
        session()->forget(['admin_impersonating', 'admin_original_session', 'admin_view_as_log_id']);

        return redirect()->route('dashboard');
    }
}
