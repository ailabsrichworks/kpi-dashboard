<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SupabaseService;
use Inertia\Inertia;

class NotificationController extends Controller
{
    private function currentUser(SupabaseService $supabase): array
    {
        $employees = $supabase->get('employees', [
            'id'        => 'eq.' . session('employee_uuid'),
            'is_active' => 'eq.true',
            'select'    => '*',
        ]);

        if (empty($employees)) {
            session()->flush();
            abort(403, 'Employee not found.');
        }

        return $employees[0];
    }

    private function sidebarData(SupabaseService $supabase, array $user): array
    {
        $departments = $supabase->get('departments', [
            'company_code' => 'eq.' . $user['company_code'],
            'select'       => '*',
            'order'        => 'name.asc',
        ]) ?? [];

        $role                   = strtoupper(trim($user['role'] ?? ''));
        // BTS has cross-department admin/support access, same level as SLT.
        $canSwitchDepartment    = $role === 'SLT' || ($user['department_code'] ?? '') === 'BTS';
        $selectedDepartmentCode = session('selected_department_code') ?? $user['department_code'] ?? null;

        $department = null;
        if ($selectedDepartmentCode) {
            $res        = $supabase->get('departments', ['code' => 'eq.' . $selectedDepartmentCode, 'select' => '*']);
            $department = $res[0] ?? null;
        }

        $pendingApprovalCount = count($supabase->get('kpi_update_approvals', [
            'approver_id' => 'eq.' . $user['id'],
            'status'      => 'eq.pending',
            'select'      => 'id',
        ]) ?? []);

        return compact('departments', 'department', 'canSwitchDepartment', 'selectedDepartmentCode', 'pendingApprovalCount');
    }

    public function index(SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login')->with('error', 'Sila login terlebih dahulu.');
        }

        $user = $this->currentUser($supabase);

        $notifications = $supabase->get('notifications', [
            'recipient_employee_id' => 'eq.' . $user['id'],
            'select'                => '*',
            'order'                 => 'created_at.desc',
            'limit'                 => 100,
        ]) ?? [];

        return Inertia::render('Notifications', [
            'user'          => $user,
            'notifications' => $notifications,
        ]);
    }

    public function markRead(string $id, SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);

        $supabase->update('notifications', [
            'id'                     => 'eq.' . $id,
            'recipient_employee_id'  => 'eq.' . $user['id'],
        ], ['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function markAllRead(SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);

        // Deliberately no `is_read => eq.false` filter here. Rows inserted
        // before NotificationService::notify() started setting `is_read`
        // explicitly have it as NULL, not false — and Postgres/PostgREST's
        // `eq.false` never matches NULL, so that filter silently skipped
        // every such row forever (the bell/page kept counting them as
        // unread no matter how many times "Mark all as read" was pressed).
        // Filtering on recipient alone and unconditionally setting true is
        // idempotent for already-read rows, so it's safe to drop.
        $supabase->update('notifications', [
            'recipient_employee_id' => 'eq.' . $user['id'],
        ], ['is_read' => true]);

        return redirect()->route('notifications');
    }
}
