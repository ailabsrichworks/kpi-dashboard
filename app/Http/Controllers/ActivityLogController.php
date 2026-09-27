<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SupabaseService;
use Inertia\Inertia;

class ActivityLogController extends Controller
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

        $role                 = strtoupper(trim($user['role'] ?? ''));
        // BTS has cross-department admin/support access, same level as SLT.
        $canSwitchDepartment  = $role === 'SLT' || ($user['department_code'] ?? '') === 'BTS';
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

    /**
     * Builds the current user's own activity feed — every entry's actor_id
     * is checked against $user['id'] before it's returned, so this is
     * "what did I do", not "what happened on KPIs visible to me". Shared by
     * both the timeline view (index) and the printable report view (report).
     */
    private function gatherOwnActivity(SupabaseService $supabase, array $user, string $fy): \Illuminate\Support\Collection
    {
        $role        = strtoupper(trim($user['role'] ?? ''));
        $companyCode = $user['company_code'];

        // ── Determine which KPIs this user can see ─────────────────────────
        $kpiFilters = [
            'company_code'   => 'eq.' . $companyCode,
            'financial_year' => 'eq.' . $fy,
            'select'         => 'id,kpi_title,employee_id',
            'order'          => 'created_at.desc',
        ];

        if ($role === 'EXECUTIVE') {
            $kpiFilters['employee_id'] = 'eq.' . $user['id'];
        } elseif ($role === 'MANAGER' || $role === 'VP') {
            $kpiFilters['department_code'] = 'eq.' . $user['department_code'];
        }
        // SLT sees all KPIs in the company

        $visibleKpis = $supabase->get('kpis', $kpiFilters) ?? [];
        $kpiMap      = collect($visibleKpis)->keyBy('id');
        $kpiIds      = array_column($visibleKpis, 'id');

        // ── Fetch employee names for KPI owners ────────────────────────────
        $empIds = array_unique(array_filter(array_merge(
            array_column($visibleKpis, 'employee_id'),
            [$user['id']]
        )));
        $empMap = [];
        if (!empty($empIds)) {
            $emps = $supabase->get('employees', [
                'id'     => 'in.(' . implode(',', $empIds) . ')',
                'select' => 'id,full_name,short_name',
            ]) ?? [];
            foreach ($emps as $e) {
                $empMap[$e['id']] = $e['short_name'] ?? $e['full_name'] ?? '-';
            }
        }

        $logs = collect();

        // ── KPI Created ────────────────────────────────────────────────────
        $kpiCreations = $supabase->get('kpis', array_merge(
            $kpiFilters,
            ['select' => 'id,kpi_title,created_at,employee_id,created_by']
        )) ?? [];

        foreach ($kpiCreations as $k) {
            $logs->push([
                'type'      => 'kpi_created',
                'label'     => 'KPI Created',
                'color'     => 'blue',
                'who'       => $empMap[$k['created_by']] ?? $empMap[$k['employee_id']] ?? '-',
                'kpi_title' => $k['kpi_title'] ?? '-',
                'detail'    => 'New KPI added for ' . $fy,
                'at'        => $k['created_at'] ?? '',
                'actor_id'  => $k['created_by'] ?? $k['employee_id'] ?? null,
            ]);
        }

        if (!empty($kpiIds)) {
            $inFilter = 'in.(' . implode(',', $kpiIds) . ')';

            // ── KPI Field Edits ────────────────────────────────────────────
            $histories = $supabase->get('kpi_histories', [
                'kpi_id' => $inFilter,
                'select' => '*',
                'order'  => 'created_at.desc',
                'limit'  => '300',
            ]) ?? [];

            foreach ($histories as $h) {
                $kpi = $kpiMap->get($h['kpi_id']);
                $fieldLabel = ucwords(str_replace('_', ' ', $h['field_name'] ?? ''));
                $logs->push([
                    'type'      => 'kpi_edited',
                    'label'     => 'KPI Edited',
                    'color'     => 'indigo',
                    'who'       => $h['edited_by_name'] ?? '-',
                    'kpi_title' => $kpi['kpi_title'] ?? '-',
                    'detail'    => $fieldLabel . ': "' . ($h['old_value'] ?? '-') . '" → "' . ($h['new_value'] ?? '-') . '"',
                    'at'        => $h['created_at'] ?? '',
                    'actor_id'  => $h['edited_by'] ?? null,
                ]);
            }

            // ── Quarter Update Approvals ───────────────────────────────────
            $approvals = $supabase->get('kpi_update_approvals', [
                'kpi_id' => $inFilter,
                'select' => '*',
                'order'  => 'created_at.desc',
                'limit'  => '300',
            ]) ?? [];

            foreach ($approvals as $a) {
                $kpi = $kpiMap->get($a['kpi_id']);

                $logs->push([
                    'type'      => 'update_submitted',
                    'label'     => 'Update Submitted',
                    'color'     => 'amber',
                    'who'       => $a['requested_by_name'] ?? '-',
                    'kpi_title' => $kpi['kpi_title'] ?? '-',
                    'detail'    => ($a['quarter'] ?? '') . ' — Actual: ' . number_format((float)($a['requested_actual'] ?? 0), 1),
                    'at'        => $a['created_at'] ?? '',
                    'actor_id'  => $a['requested_by'] ?? null,
                ]);

                if (!empty($a['approved_at'])) {
                    $logs->push([
                        'type'      => 'update_approved',
                        'label'     => 'Update Approved',
                        'color'     => 'green',
                        'who'       => $a['approved_by_name'] ?? $a['approver_name'] ?? '-',
                        'kpi_title' => $kpi['kpi_title'] ?? '-',
                        'detail'    => ($a['quarter'] ?? '') . ' approved' . ($a['approver_remark'] ? ' — ' . $a['approver_remark'] : ''),
                        'at'        => $a['approved_at'],
                        'actor_id'  => $a['approved_by'] ?? null,
                    ]);
                }

                if (!empty($a['rejected_at'])) {
                    $logs->push([
                        'type'      => 'update_rejected',
                        'label'     => 'Update Rejected',
                        'color'     => 'red',
                        'who'       => $a['rejected_by_name'] ?? $a['approver_name'] ?? '-',
                        'kpi_title' => $kpi['kpi_title'] ?? '-',
                        'detail'    => ($a['quarter'] ?? '') . ' rejected' . ($a['rejection_reason'] ? ' — ' . $a['rejection_reason'] : ''),
                        'at'        => $a['rejected_at'],
                        'actor_id'  => $a['rejected_by'] ?? null,
                    ]);
                }
            }

            // ── Quarter Completion Submissions ─────────────────────────────
            $quarters = $supabase->get('kpi_quarters', [
                'kpi_id'                   => $inFilter,
                'completion_submitted_at'  => 'not.is.null',
                'select'                   => 'id,kpi_id,quarter,status,completion_submitted_at,completion_submitted_by',
                'order'                    => 'completion_submitted_at.desc',
                'limit'                    => '200',
            ]) ?? [];

            $submitterIds = array_unique(array_filter(array_column($quarters, 'completion_submitted_by')));
            $submitterMap = [];
            if (!empty($submitterIds)) {
                $submitters = $supabase->get('employees', [
                    'id'     => 'in.(' . implode(',', $submitterIds) . ')',
                    'select' => 'id,full_name,short_name',
                ]) ?? [];
                foreach ($submitters as $s) {
                    $submitterMap[$s['id']] = $s['short_name'] ?? $s['full_name'] ?? '-';
                }
            }

            foreach ($quarters as $q) {
                $kpi = $kpiMap->get($q['kpi_id']);
                $logs->push([
                    'type'      => 'completion_submitted',
                    'label'     => 'Completion Submitted',
                    'color'     => 'purple',
                    'who'       => $submitterMap[$q['completion_submitted_by'] ?? ''] ?? '-',
                    'kpi_title' => $kpi['kpi_title'] ?? '-',
                    'detail'    => ($q['quarter'] ?? '') . ' — quarter completion submitted for review',
                    'at'        => $q['completion_submitted_at'] ?? '',
                    'actor_id'  => $q['completion_submitted_by'] ?? null,
                ]);
            }

            // ── KPI Delete Requests ────────────────────────────────────────
            $deleteReqs = $supabase->get('kpi_delete_requests', [
                'kpi_id' => $inFilter,
                'select' => '*',
                'order'  => 'created_at.desc',
                'limit'  => '100',
            ]) ?? [];

            foreach ($deleteReqs as $d) {
                $kpi = $kpiMap->get($d['kpi_id']);
                $logs->push([
                    'type'      => 'delete_requested',
                    'label'     => 'Delete Requested',
                    'color'     => 'rose',
                    'who'       => $d['requested_by_name'] ?? '-',
                    'kpi_title' => $kpi['kpi_title'] ?? '-',
                    'detail'    => 'Status: ' . ucfirst($d['status'] ?? 'pending') . ($d['reason'] ? ' — ' . $d['reason'] : ''),
                    'at'        => $d['created_at'] ?? '',
                    'actor_id'  => $d['requested_by'] ?? null,
                ]);
            }
        }

        // ── Quarter Evaluation / Performance Appraisal ───────────────────────
        // performance_reports has no separate audit trail (unlike KPIs), just
        // the current status + submitted_at/updated_at — so only two actions
        // are unambiguous from the data alone: the report owner submitting
        // their own self-assessment, and the report owner signing off once
        // their manager appraised them. A third — the direct manager doing
        // the appraising — is derivable too: only a "manager"-level submit
        // advances status to 'appraised' (see PerformanceController), so if
        // this employee's reports_to_id is the current user, the current
        // user is who did it.
        $directReports = $supabase->get('employees', [
            'reports_to_id' => 'eq.' . $user['id'],
            'is_active'     => 'eq.true',
            'select'        => 'id,short_name,full_name',
        ]) ?? [];
        $directReportIds  = array_column($directReports, 'id');
        $directReportName = collect($directReports)->keyBy('id')
            ->map(fn($e) => $e['short_name'] ?? $e['full_name'] ?? '-');

        $perfEmployeeIds = array_unique(array_merge([$user['id']], $directReportIds));
        $perfReports = $supabase->get('performance_reports', [
            'employee_id'    => 'in.(' . implode(',', $perfEmployeeIds) . ')',
            'financial_year' => 'eq.' . $fy,
            'status'         => 'in.(submitted,appraised,completed)',
            'select'         => 'employee_id,quarter,status,submitted_at,updated_at',
        ]) ?? [];

        foreach ($perfReports as $p) {
            $isOwn = $p['employee_id'] === $user['id'];
            $label = ($p['quarter'] ?? '') . ' ' . $fy . ' Appraisal';

            if ($isOwn) {
                if (!empty($p['submitted_at'])) {
                    $logs->push([
                        'type'      => 'appraisal_submitted',
                        'label'     => 'Appraisal Submitted',
                        'color'     => 'teal',
                        'who'       => $user['short_name'] ?? $user['full_name'] ?? '-',
                        'kpi_title' => $label,
                        'detail'    => 'Self-assessment submitted for review',
                        'at'        => $p['submitted_at'],
                        'actor_id'  => $user['id'],
                    ]);
                }
                if ($p['status'] === 'completed') {
                    $logs->push([
                        'type'      => 'appraisal_signed',
                        'label'     => 'Appraisal Signed',
                        'color'     => 'cyan',
                        'who'       => $user['short_name'] ?? $user['full_name'] ?? '-',
                        'kpi_title' => $label,
                        'detail'    => 'Signed to acknowledge appraiser\'s review',
                        'at'        => $p['updated_at'] ?? '',
                        'actor_id'  => $user['id'],
                    ]);
                }
            } elseif (in_array($p['status'], ['appraised', 'completed'], true)) {
                $logs->push([
                    'type'      => 'appraisal_reviewed',
                    'label'     => 'Appraisal Reviewed',
                    'color'     => 'orange',
                    'who'       => $user['short_name'] ?? $user['full_name'] ?? '-',
                    'kpi_title' => $label . ' — ' . ($directReportName[$p['employee_id']] ?? '-'),
                    'detail'    => 'Scored and signed as appraiser',
                    'at'        => $p['updated_at'] ?? '',
                    'actor_id'  => $user['id'],
                ]);
            }
        }

        // ── Own activity only — this log is "what did I do", not "what
        // happened on KPIs I can see". A manager approving a subordinate's
        // update, for example, is filtered out of the subordinate's own log.
        return $logs
            ->filter(fn($l) => ($l['actor_id'] ?? null) === $user['id'])
            ->filter(fn($l) => !empty($l['at']))
            ->sortByDesc('at')
            ->values();
    }

    public function index(Request $request, SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);
        $fy   = 'FY' . now()->year;

        $logs = $this->gatherOwnActivity($supabase, $user, $fy);

        $typeFilter = $request->get('type', '');
        if ($typeFilter) {
            $logs = $logs->filter(fn($l) => $l['type'] === $typeFilter)->values();
        }

        return Inertia::render('ActivityLog', [
            'user'       => $user,
            'logs'       => $logs->values()->all(),
            'typeFilter' => $typeFilter,
            'fy'         => $fy,
        ]);
    }
}
