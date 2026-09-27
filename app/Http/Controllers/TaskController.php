<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SupabaseService;

class TaskController extends Controller
{
    private function currentUser(SupabaseService $supabase): array
    {
        $employeeUuid = session('employee_uuid');

        if (!$employeeUuid) {
            abort(403, 'Employee not logged in.');
        }

        $employees = $supabase->get('employees', [
            'id' => 'eq.' . $employeeUuid,
            'is_active' => 'eq.true',
            'select' => '*'
        ]);

        if (empty($employees)) {
            abort(403, 'Employee not found.');
        }

        return $employees[0];
    }

    public function index(SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);

        $query = [
            'company_code' => 'eq.' . $user['company_code'],
            'select' => '*',
            'order' => 'created_at.desc'
        ];

        // SLT → See ALL
        if ($user['role'] === 'SLT') {
            $tasks = $supabase->get('tasks', $query);
        }

        // VP / Manager → Only own department
        elseif (in_array($user['role'], ['VP', 'Manager'])) {
            $query['department_code'] = 'eq.' . $user['department_code'];
            $tasks = $supabase->get('tasks', $query);
        }

        // 🔥 Executive → Only own tasks
        else {
            $query['employee_id'] = 'eq.' . $user['id'];
            $tasks = $supabase->get('tasks', $query);
        }

        return view('tasks.index', [
            'tasks' => $tasks ?? [],
            'user' => $user
        ]);
    }

    public function destroy(string $id, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()
                ->route('login')
                ->with('error', 'Sila login terlebih dahulu.');
        }

        $employeeUuid = session('employee_uuid');
        $companyCode = session('company_code');

        $users = $supabase->get('employees', [
            'id' => 'eq.' . $employeeUuid,
            'is_active' => 'eq.true',
            'select' => '*',
            'limit' => 1,
        ]);

        if (empty($users)) {
            session()->flush();

            return redirect()
                ->route('login')
                ->with('error', 'Session tidak sah. Sila login semula.');
        }

        $user = $users[0];

        // ⚠️ YOU ARE DELETING KPI, NOT TASK
        $kpis = $supabase->get('kpis', [
            'id' => 'eq.' . $id,
            'company_code' => 'eq.' . $companyCode,
            'select' => '*',
            'limit' => 1,
        ]);

        if (empty($kpis)) {
            return back()->with('error', 'KPI tidak dijumpai.');
        }

        $kpi = $kpis[0];

        $allowedRoles = ['SLT', 'VP'];

        $canDelete =
            in_array($user['role'] ?? '', $allowedRoles)
            || (($kpi['employee_id'] ?? null) === ($user['id'] ?? null))
            || (($kpi['created_by'] ?? null) === ($user['id'] ?? null));

        if (!$canDelete) {
            return back()->with('error', 'Anda tiada akses untuk delete KPI ini.');
        }

        // 🔥 Delete child first (avoid FK issues)
        $supabase->delete('kpi_histories', [
            'kpi_id' => 'eq.' . $id,
        ]);

        $supabase->delete('kpi_target_change_requests', [
            'kpi_id' => 'eq.' . $id,
        ]);

        // 🔥 Delete KPI
        $supabase->delete('kpis', [
            'id' => 'eq.' . $id,
            'company_code' => 'eq.' . $companyCode,
        ]);

        return redirect()
            ->route('kpi.index')
            ->with('success', 'KPI berjaya dipadam.');
    }
}
