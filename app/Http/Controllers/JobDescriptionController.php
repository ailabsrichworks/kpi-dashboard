<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SupabaseService;

class JobDescriptionController extends Controller
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

    public function index(Request $request, SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);

        $jobDescription = $supabase->first('job_descriptions', [
            'employee_id' => 'eq.' . $user['id'],
            'select'      => '*',
        ]);

        $manager = null;
        if (!empty($user['reports_to_id'])) {
            $res     = $supabase->get('employees', ['id' => 'eq.' . $user['reports_to_id'], 'select' => 'short_name,full_name,position']);
            $manager = $res[0] ?? null;
        }

        return view('job-description', array_merge([
            'user'            => $user,
            'manager'         => $manager,
            'jobDescription'  => $jobDescription,
        ], $this->sidebarData($supabase, $user)));
    }

    // Content comes from a CKEditor rich-text editor (see job-description.blade.php), which
    // only ever emits a fixed set of formatting tags — this allowlist strips anything else
    // (including <script>) and any inline event-handler attributes that might slip through.
    private function sanitizeHtml(?string $html): string
    {
        $allowed = '<p><br><ul><ol><li><figure><table><thead><tbody><tr><td><th><strong><b><em><i><u><s>';
        $html    = strip_tags((string) $html, $allowed);

        return preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    }

    public function update(Request $request, SupabaseService $supabase, \App\Services\NotificationService $notifications)
    {
        $user = $this->currentUser($supabase);

        $isSubmit = $request->input('action') === 'submit';

        $payload = [
            'employee_id'      => $user['id'],
            'summary'          => $this->sanitizeHtml($request->input('summary')),
            'responsibilities' => $this->sanitizeHtml($request->input('responsibilities')),
            'requirements'     => $this->sanitizeHtml($request->input('requirements')),
            'competencies'     => $this->sanitizeHtml($request->input('competencies')),
            'status'           => $isSubmit ? 'submitted' : 'draft',
            'updated_at'       => now()->toIso8601String(),
        ];

        foreach (['hr', 'jobholder', 'supervisor'] as $party) {
            $sig = $request->input("sig_{$party}");
            if ($sig && str_starts_with($sig, 'data:image/')) {
                $payload["sig_{$party}"]      = $sig;
                $payload["sig_{$party}_date"] = $request->input("sig_{$party}_date") ?: now()->toDateString();
            }
        }

        if ($isSubmit) {
            $payload['submitted_at'] = now()->toIso8601String();
        }

        $supabase->upsert('job_descriptions', $payload, 'employee_id');

        if ($isSubmit && !empty($user['reports_to_id'])) {
            $employeeName = $user['full_name'] ?? $user['short_name'] ?? 'An employee';
            $notifications->notify(
                [$user['reports_to_id']],
                'job_description_submitted',
                ['id' => $user['id'], 'name' => $employeeName],
                "{$employeeName} submitted their Job Description",
                'Ready for your review.'
            );
        }

        return redirect()->route('job-description')->with(
            'success',
            $isSubmit ? 'Job description submitted.' : 'Draft saved.'
        );
    }
}
