<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\Request;

class StaffEvaluationSummaryController extends Controller
{
    private string $currentFinancialYear = 'FY2026';

    private const QUARTERS = ['Q1', 'Q2', 'Q3', 'Q4'];

    // BTS / CORPORATE GOVERNANCE & HR DEPT only -- deliberately narrower than
    // the SLT Dashboard's own gate (which also lets SLT Office in): this page
    // is for HR and BTS specifically, per the request that created it.
    private function requireHrBtsAccess(): void
    {
        if (!$this->isCrossCompanyMonitorSession()) {
            abort(403, 'This page is only accessible to BTS and Corporate Governance & HR.');
        }
    }

    // Same band thresholds/labels used everywhere else a score is shown
    // (SltDashboardController::bandFor(), performance/report.blade.php's
    // Performance Distribution chart) -- kept in sync so a score means the
    // same thing on every page.
    private function bandFor(float $score): array
    {
        if ($score >= 90) return ['key' => 'outstanding',        'label' => 'Outstanding',        'bg' => '#00B050', 'text' => '#FFFFFF'];
        if ($score >= 70) return ['key' => 'meets_expectations', 'label' => 'Meets Expectations', 'bg' => '#FFD700', 'text' => '#000000'];
        if ($score >= 50) return ['key' => 'below_average',      'label' => 'Below Average',      'bg' => '#FF8C00', 'text' => '#000000'];
        return                   ['key' => 'unsatisfactory',     'label' => 'Unsatisfactory',     'bg' => '#ED1C24', 'text' => '#FFFFFF'];
    }

    private function rolePriority(?string $role): int
    {
        return match (strtoupper(trim($role ?? ''))) {
            'SLT'       => 1,
            'VP'        => 2,
            'MANAGER'   => 3,
            'EXECUTIVE' => 4,
            default     => 5,
        };
    }

    // Mirrors SltDashboardController::scoreFromFormData() -- sum of
    // s6_s2_app (KPI, /70) + s6_s3_app (Attitude, /25) + s6_s4_app
    // (Attendance, /5) + s6_s5_app (Culture, /5, Q4 only), all already
    // scaled to their weight when saved.
    private function scoreFromFormData(?array $formData, string $quarter): ?float
    {
        if (!$formData) return null;

        $keys = ['s6_s2_app', 's6_s3_app', 's6_s4_app'];
        if ($quarter === 'Q4') $keys[] = 's6_s5_app';

        $sum = 0;
        $found = false;
        foreach ($keys as $key) {
            if (isset($formData[$key]) && is_numeric($formData[$key])) {
                $sum += (float) $formData[$key];
                $found = true;
            }
        }

        return $found ? round($sum, 1) : null;
    }

    public function index(Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login')->with('error', 'Sila login terlebih dahulu.');
        }

        $this->requireHrBtsAccess();

        $companyCode = session('company_code');
        $deptFilter  = strtoupper(trim($request->query('department', 'ALL')));

        $departments = $supabase->get('departments', [
            'company_code' => 'eq.' . $companyCode,
            'select'       => 'code,name',
            'order'        => 'name.asc',
        ]) ?? [];

        $employeeFilters = [
            'company_code' => 'eq.' . $companyCode,
            'is_active'    => 'eq.true',
            'select'       => 'id,employee_id,short_name,full_name,department_code,role,position',
            'order'        => 'short_name.asc',
        ];
        if ($deptFilter !== 'ALL') {
            $employeeFilters['department_code'] = 'eq.' . $deptFilter;
        }

        $employees = $supabase->get('employees', $employeeFilters) ?? [];

        // Same cross-company-monitor roster exclusion as the SLT Dashboard:
        // a BTS/HR employee row only counts as THIS company's own staff when
        // it's their default company membership, so viewing this company's
        // page doesn't put the viewer's own row in its own roster.
        $empIdsForDefaultCheck = collect($employees)->pluck('id')->filter()->values()->toArray();
        if (!empty($empIdsForDefaultCheck)) {
            $rolesForCompany = $supabase->get('user_company_roles', [
                'company_code' => 'eq.' . $companyCode,
                'employee_id'  => 'in.(' . implode(',', $empIdsForDefaultCheck) . ')',
                'select'       => 'employee_id,is_default',
            ]);
            $isDefaultByEmployeeId = collect($rolesForCompany)->pluck('is_default', 'employee_id');
            $employees = array_values(array_filter($employees, function ($emp) use ($isDefaultByEmployeeId) {
                return $isDefaultByEmployeeId->get($emp['id'], true) !== false;
            }));
        }

        $empIds = collect($employees)->pluck('id')->filter()->values()->toArray();

        $reports = [];
        if (!empty($empIds)) {
            $reports = $supabase->get('performance_reports', [
                'employee_id'    => 'in.(' . implode(',', $empIds) . ')',
                'financial_year' => 'eq.' . $this->currentFinancialYear,
                'quarter'        => 'in.(' . implode(',', self::QUARTERS) . ')',
                'select'         => 'employee_id,quarter,status,form_data,updated_at',
            ]) ?? [];
        }

        $reportsByEmpQuarter = [];
        foreach ($reports as $r) {
            $reportsByEmpQuarter[$r['employee_id']][$r['quarter']] = $r;
        }

        $quarterTotals = array_fill_keys(self::QUARTERS, ['scoreSum' => 0, 'scoreCount' => 0, 'completed' => 0, 'submitted' => 0]);

        $staffRows = [];
        foreach ($employees as $emp) {
            $role = strtoupper(trim($emp['role'] ?? ''));
            $row = [
                'employee_id'   => $emp['employee_id'] ?? '-',
                'name'          => $emp['short_name'] ?? $emp['full_name'] ?? 'Unknown',
                'department'    => $emp['department_code'] ?? '-',
                'role'          => $role ?: '-',
                'role_priority' => $this->rolePriority($role),
                'quarters'      => [],
            ];

            $rowScoreSum   = 0;
            $rowScoreCount = 0;

            foreach (self::QUARTERS as $q) {
                $report = $reportsByEmpQuarter[$emp['id']][$q] ?? null;
                $status = $report['status'] ?? 'draft';

                if (!in_array($status, ['submitted', 'appraised', 'completed'])) {
                    $row['quarters'][$q] = ['status_key' => 'not_submitted', 'label' => 'Not Submitted', 'score' => null];
                    continue;
                }

                $quarterTotals[$q]['submitted']++;

                // "appraised" means the manager has scored but the appraisee
                // hasn't signed off yet -- no score surfaces until the cycle
                // is fully closed (matches SltDashboardController exactly).
                if ($status === 'appraised') {
                    $row['quarters'][$q] = ['status_key' => 'awaiting_signoff', 'label' => 'Awaiting Sign-off', 'score' => null];
                    continue;
                }

                $score = $status === 'completed'
                    ? $this->scoreFromFormData($report['form_data'] ?? null, $q)
                    : null;

                if ($score === null) {
                    $row['quarters'][$q] = ['status_key' => 'pending', 'label' => 'Pending', 'score' => null];
                    continue;
                }

                $band = $this->bandFor($score);
                $row['quarters'][$q] = [
                    'status_key' => $band['key'],
                    'label'      => $band['label'],
                    'score'      => $score,
                    'bg'         => $band['bg'],
                    'text'       => $band['text'],
                ];

                $quarterTotals[$q]['completed']++;
                $quarterTotals[$q]['scoreSum'] += $score;
                $quarterTotals[$q]['scoreCount']++;

                $rowScoreSum += $score;
                $rowScoreCount++;
            }

            $row['average_score'] = $rowScoreCount > 0 ? round($rowScoreSum / $rowScoreCount, 1) : null;
            $row['average_band']  = $row['average_score'] !== null ? $this->bandFor($row['average_score']) : null;
            $staffRows[] = $row;
        }

        usort($staffRows, function ($a, $b) {
            return $a['role_priority'] <=> $b['role_priority'] ?: strcasecmp($a['name'], $b['name']);
        });

        $totalStaff = count($employees);

        $quarterSummary = [];
        foreach (self::QUARTERS as $q) {
            $t = $quarterTotals[$q];
            $quarterSummary[$q] = [
                'completed'          => $t['completed'],
                'submitted'          => $t['submitted'],
                'participation_rate' => $totalStaff > 0 ? round($t['submitted'] / $totalStaff * 100) : 0,
                'average_score'      => $t['scoreCount'] > 0 ? round($t['scoreSum'] / $t['scoreCount'], 1) : null,
            ];
        }

        return view('staff-evaluation.summary', [
            'today'                => now()->timezone('Asia/Kuala_Lumpur')->format('d M Y'),
            'currentFinancialYear' => $this->currentFinancialYear,
            'companyCode'          => $companyCode,
            'departments'          => $departments,
            'deptFilter'           => $deptFilter,
            'quarters'             => self::QUARTERS,
            'quarterSummary'       => $quarterSummary,
            'totalStaff'           => $totalStaff,
            'staffRows'            => $staffRows,
        ]);
    }
}
