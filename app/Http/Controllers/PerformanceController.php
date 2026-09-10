<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use App\Services\QuarterOverrideService;
use App\Services\AppraiserDelegationService;

class PerformanceController extends Controller
{
    private string $currentFinancialYear = 'FY2026';

    public function kpiAppraisal(SupabaseService $supabase, QuarterOverrideService $overrides)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login');
        }

        $employees = $supabase->get('employees', [
            'id'        => 'eq.' . session('employee_uuid'),
            'is_active' => 'eq.true',
            'select'    => '*',
        ]);
        $user = $employees[0] ?? null;

        if (!$user) {
            session()->flush();
            return redirect()->route('login');
        }

        // ── Tenure calculation ────────────────────────────────────────────────
        $joinDate = $user['join_date'] ?? null;
        $tenure   = '—';
        if ($joinDate) {
            $diff = \Carbon\Carbon::parse($joinDate)->diff(now());
            $parts = [];
            if ($diff->y > 0) $parts[] = $diff->y . ' year' . ($diff->y !== 1 ? 's' : '');
            if ($diff->m > 0) $parts[] = $diff->m . ' month' . ($diff->m !== 1 ? 's' : '');
            $tenure = $parts ? implode(' ', $parts) : 'Less than 1 month';
        }

        // Reporting-to (approver)
        $reportsTo = null;
        if (!empty($user['reports_to_id'])) {
            $managers  = $supabase->get('employees', [
                'id'     => 'eq.' . $user['reports_to_id'],
                'select' => 'id,short_name,full_name,role,position',
            ]);
            $reportsTo = $managers[0] ?? null;
        }

        // Department
        $department = null;
        if (!empty($user['department_code'])) {
            $depts      = $supabase->get('departments', [
                'code'   => 'eq.' . $user['department_code'],
                'select' => '*',
            ]);
            $department = $depts[0] ?? null;
        }

        // ── Quarter logic ─────────────────────────────────────────────────────
        $now   = now();
        $month = (int) $now->format('n');
        $year  = (int) $now->format('Y');

        // Calendar-year quarters
        $quarterOfMonth = match(true) {
            $month <= 3 => 1,
            $month <= 6 => 2,
            $month <= 9 => 3,
            default     => 4,
        };

        // Submission windows: last ~week of quarter + first ~week of next quarter
        $windows = [
            1 => ['start' => "{$year}-03-24", 'end' => "2026-07-31"],
            2 => ['start' => "{$year}-06-23", 'end' => "{$year}-07-31"],
            3 => ['start' => "{$year}-09-22", 'end' => "{$year}-10-06"],
            4 => ['start' => "{$year}-12-23", 'end' => ($year + 1) . "-01-06"],
        ];

        // Check if we are currently inside any submission window
        $displayQuarter = $quarterOfMonth;
        $isWindowOpen   = false;
        foreach ($windows as $q => $win) {
            if ($now->toDateString() >= $win['start'] && $now->toDateString() <= $win['end']) {
                $displayQuarter = $q;
                $isWindowOpen   = true;
                break;
            }
        }

        // A BTS-issued override (QuarterOverrideController) force-opens a
        // specific quarter's appraisal until a chosen deadline -- checked
        // only once none of the normal hardcoded windows match today, so it
        // never masks a genuinely-open window.
        if (!$isWindowOpen) {
            foreach ([1, 2, 3, 4] as $q) {
                if ($overrides->isActive($this->currentFinancialYear, 'Q' . $q)) {
                    $displayQuarter = $q;
                    $isWindowOpen   = true;
                    break;
                }
            }
        }

        $window     = $windows[$displayQuarter];
        $windowStart = \Carbon\Carbon::parse($window['start'])->format('d M Y');
        $windowEnd   = \Carbon\Carbon::parse($window['end'])->format('d M Y');
        $qLabel      = 'Q' . $displayQuarter;

        // ── KPIs for this user ────────────────────────────────────────────────
        $kpis = $supabase->get('kpis', [
            'employee_id'    => 'eq.' . $user['id'],
            'financial_year' => 'eq.' . $this->currentFinancialYear,
            'select'         => 'id,kpi_title,category,sub_category,unit,base_target,actual_value,status,weightage',
        ]) ?? [];

        $quarterScores = [];
        foreach ($kpis as $kpi) {
            $qRows = $supabase->get('kpi_quarters', [
                'kpi_id'  => 'eq.' . $kpi['id'],
                'quarter' => 'eq.' . $qLabel,
                'select'  => 'quarter,quarter_target,quarter_actual,status',
            ]);
            $quarterScores[$kpi['id']] = $qRows[0] ?? null;
        }

        return view('performance.kpi', [
            'user'                 => $user,
            'currentUserName'      => $user['full_name'] ?? $user['short_name'] ?? 'User',
            'userPosition'         => $user['position'] ?? $user['role'] ?? '-',
            'departmentName'       => $department['name'] ?? $user['department_code'] ?? '-',
            'reportsToName'        => $reportsTo ? ($reportsTo['full_name'] ?? $reportsTo['short_name'] ?? '-') : '-',
            'reportsToPosition'    => $reportsTo['position'] ?? $reportsTo['role'] ?? '-',
            'joinDate'             => $joinDate ? \Carbon\Carbon::parse($joinDate)->format('d M Y') : '—',
            'tenure'               => $tenure,
            'currentFinancialYear' => $this->currentFinancialYear,
            'displayQuarter'       => $displayQuarter,
            'qLabel'               => $qLabel,
            'isWindowOpen'         => $isWindowOpen,
            'windowStart'          => $windowStart,
            'windowEnd'            => $windowEnd,
            'kpis'                 => $kpis,
            'quarterScores'        => $quarterScores,
        ]);
    }

    public function attitude(SupabaseService $supabase, QuarterOverrideService $overrides)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login');
        }

        $employees = $supabase->get('employees', [
            'id'        => 'eq.' . session('employee_uuid'),
            'is_active' => 'eq.true',
            'select'    => '*',
        ]);
        $user = $employees[0] ?? null;

        if (!$user) {
            session()->flush();
            return redirect()->route('login');
        }

        $reportsTo = null;
        if (!empty($user['reports_to_id'])) {
            $managers  = $supabase->get('employees', [
                'id'     => 'eq.' . $user['reports_to_id'],
                'select' => 'id,short_name,full_name,role,position',
            ]);
            $reportsTo = $managers[0] ?? null;
        }

        $department = null;
        if (!empty($user['department_code'])) {
            $depts      = $supabase->get('departments', [
                'code'   => 'eq.' . $user['department_code'],
                'select' => '*',
            ]);
            $department = $depts[0] ?? null;
        }

        $now   = now();
        $month = (int) $now->format('n');
        $year  = (int) $now->format('Y');

        $quarterOfMonth = match(true) {
            $month <= 3 => 1,
            $month <= 6 => 2,
            $month <= 9 => 3,
            default     => 4,
        };

        $windows = [
            1 => ['start' => "{$year}-03-24", 'end' => "2026-07-31"],
            2 => ['start' => "{$year}-06-23", 'end' => "{$year}-07-31"],
            3 => ['start' => "{$year}-09-22", 'end' => "{$year}-10-06"],
            4 => ['start' => "{$year}-12-23", 'end' => ($year + 1) . "-01-06"],
        ];

        $displayQuarter = $quarterOfMonth;
        $isWindowOpen   = false;
        foreach ($windows as $q => $win) {
            if ($now->toDateString() >= $win['start'] && $now->toDateString() <= $win['end']) {
                $displayQuarter = $q;
                $isWindowOpen   = true;
                break;
            }
        }

        // See kpiAppraisal()'s identical block -- a BTS override force-opens
        // a specific quarter once none of the normal windows match today.
        if (!$isWindowOpen) {
            foreach ([1, 2, 3, 4] as $q) {
                if ($overrides->isActive($this->currentFinancialYear, 'Q' . $q)) {
                    $displayQuarter = $q;
                    $isWindowOpen   = true;
                    break;
                }
            }
        }

        $window      = $windows[$displayQuarter];
        $windowStart = \Carbon\Carbon::parse($window['start'])->format('d M Y');
        $windowEnd   = \Carbon\Carbon::parse($window['end'])->format('d M Y');
        $qLabel      = 'Q' . $displayQuarter;

        $assessmentAreas = [
            [
                'no'          => 1,
                'title'       => 'Knowledge of Job Requirements',
                'description' => 'Knowledge of job requirements, methods, techniques and skills involved in doing the job, and in applying these to perform efficiently.',
            ],
            [
                'no'          => 2,
                'title'       => 'Quality of Work Done',
                'description' => 'To what degree did the appraisee fulfil the quality expectations of the job? Was the work completed accurate and reliable? What is the degree of excellence of end results?',
            ],
            [
                'no'          => 3,
                'title'       => 'Planning and Organising Skills',
                'description' => 'To what degree did the appraisee anticipate needs, forecast conditions, set goals and standards, plan and schedule work?',
            ],
            [
                'no'          => 4,
                'title'       => 'Decision Making',
                'description' => 'Was the appraisee able to analyse problems effectively and make sound decisions and commit to those decisions to achieve an acceptable result?',
            ],
            [
                'no'          => 5,
                'title'       => 'Communication Skills',
                'description' => 'Did the appraisee communicate effectively verbal and written, with superiors and peers?',
            ],
            [
                'no'          => 6,
                'title'       => 'Teamwork',
                'description' => 'Was the appraisee able to adopt and adapt in work conditions/situations and work with others toward a common objective?',
            ],
            [
                'no'          => 7,
                'title'       => 'Interpersonal Relationships',
                'description' => 'How well did the appraisee relate to associates, superiors, and external contacts to get the desired cooperation and assistance?',
            ],
            [
                'no'          => 8,
                'title'       => 'Attitude Towards Work',
                'description' => 'Was the appraisee able to work independently without need for direct supervision? How well did the appraisee adapt to new tasks and to changes in the work environment? Did the appraisee show commitment in discharge of his/her duties?',
            ],
            [
                'no'          => 9,
                'title'       => 'Time Management / Tardiness',
                'description' => 'Is the appraisee able to plan, execute and complete assigned tasks within the deadline given? Did the appraisee conform to Company\'s rules and regulations at all times? Was the appraisee punctual in attendance and timekeeping?',
            ],
            [
                'no'          => 10,
                'title'       => 'Appearance',
                'description' => 'Was the appraisee well groomed? Did the appraisee make an excellent impression?',
            ],
            [
                'no'          => 11,
                'title'       => 'Dependability / Accountability',
                'description' => 'Able to carry out work with limited or minimum supervision and able to follow work instructions. Demonstrates high level of commitment to complete tasks assigned and shows initiative in ensuring job is completed efficiently and effectively.',
            ],
            [
                'no'          => 12,
                'title'       => 'Values',
                'description' => 'Does the appraisee understand and demonstrate organisation values all the time?',
            ],
        ];

        return view('performance.attitude', [
            'user'                 => $user,
            'currentUserName'      => $user['full_name'] ?? $user['short_name'] ?? 'User',
            'userPosition'         => $user['position'] ?? $user['role'] ?? '-',
            'departmentName'       => $department['name'] ?? $user['department_code'] ?? '-',
            'reportsToName'        => $reportsTo
                                        ? ($reportsTo['full_name'] ?? $reportsTo['short_name'] ?? '-')
                                        : '-',
            'reportsToPosition'    => $reportsTo['position'] ?? $reportsTo['role'] ?? '-',
            'currentFinancialYear' => $this->currentFinancialYear,
            'displayQuarter'       => $displayQuarter,
            'qLabel'               => $qLabel,
            'isWindowOpen'         => $isWindowOpen,
            'windowStart'          => $windowStart,
            'windowEnd'            => $windowEnd,
            'assessmentAreas'      => $assessmentAreas,
        ]);
    }

    public function reportQuarter(string $quarter, SupabaseService $supabase, QuarterOverrideService $overrides)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login');
        }

        $q = strtoupper($quarter);
        if (!in_array($q, ['Q1','Q2','Q3','Q4'])) abort(404);

        $employees = $supabase->get('employees', [
            'id'        => 'eq.' . session('employee_uuid'),
            'is_active' => 'eq.true',
            'select'    => '*',
        ]);
        $user = $employees[0] ?? null;
        if (!$user) { session()->flush(); return redirect()->route('login'); }

        // Tenure
        $joinDate = $user['join_date'] ?? null;
        $tenure   = '—';
        if ($joinDate) {
            $diff  = \Carbon\Carbon::parse($joinDate)->diff(now());
            $parts = [];
            if ($diff->y > 0) $parts[] = $diff->y . ' year' . ($diff->y !== 1 ? 's' : '');
            if ($diff->m > 0) $parts[] = $diff->m . ' month' . ($diff->m !== 1 ? 's' : '');
            $tenure = $parts ? implode(' ', $parts) : 'Less than 1 month';
        }

        // Reports-to
        $reportsTo = null;
        if (!empty($user['reports_to_id'])) {
            $mgr = $supabase->get('employees', ['id' => 'eq.' . $user['reports_to_id'], 'select' => 'id,short_name,full_name,role,position']);
            $reportsTo = $mgr[0] ?? null;
        }

        // Department
        $department = null;
        if (!empty($user['department_code'])) {
            $depts      = $supabase->get('departments', ['code' => 'eq.' . $user['department_code'], 'select' => '*']);
            $department = $depts[0] ?? null;
        }

        // Window for THIS specific quarter
        $now  = now()->timezone('Asia/Kuala_Lumpur');
        $year = (int) $now->year;
        $mon  = (int) $now->month;

        $windows = [
            'Q1' => ['start' => "{$year}-03-24", 'end' => "2026-07-31"],
            'Q2' => ['start' => "{$year}-06-23", 'end' => "{$year}-07-31"],
            'Q3' => ['start' => "{$year}-09-22", 'end' => "{$year}-10-06"],
            'Q4' => ['start' => "{$year}-12-23", 'end' => ($year + 1) . "-01-06"],
        ];
        // Q4 window bleeds into January — if it's Jan 1-6, reference last year's Q4
        if ($q === 'Q4' && $mon === 1 && $now->day <= 6) {
            $py = $year - 1;
            $windows['Q4'] = ['start' => "{$py}-12-23", 'end' => "{$year}-01-06"];
        }

        $window       = $windows[$q];
        $today        = $now->toDateString();
        $isWindowOpen = $today >= $window['start'] && $today <= $window['end'];
        $isFuture     = $today < $window['start'];
        $windowStart  = \Carbon\Carbon::parse($window['start'])->format('d M Y');
        $windowEnd    = \Carbon\Carbon::parse($window['end'])->format('d M Y');

        // A BTS-issued override (QuarterOverrideController) force-opens THIS
        // specific quarter's self-review until a chosen deadline, regardless
        // of its normal submission window -- e.g. reopening the quarter
        // before the current one for a late correction.
        $quarterOverride = $overrides->activeOverride($this->currentFinancialYear, $q);
        if ($quarterOverride) {
            $isWindowOpen = true;
            $isFuture     = false;
            $windowEnd    = \Carbon\Carbon::parse($quarterOverride['open_until'])
                ->timezone('Asia/Kuala_Lumpur')->format('d M Y, g:i A');
        }

        // KPIs
        $kpis = $supabase->get('kpis', [
            'employee_id'    => 'eq.' . $user['id'],
            'financial_year' => 'eq.' . $this->currentFinancialYear,
            'select'         => 'id,kpi_title,category,sub_category,unit,base_target,actual_value,status,weightage',
        ]) ?? [];

        $quarterScores = [];
        $allQuarters   = [];
        foreach ($kpis as $kpi) {
            $qRows = $supabase->get('kpi_quarters', [
                'kpi_id' => 'eq.' . $kpi['id'],
                'select' => 'quarter,quarter_title,quarter_target,quarter_actual,status,remark,completion_review,completion_proof_urls',
            ]);
            foreach ($qRows as $row) {
                $allQuarters[$kpi['id']][$row['quarter']] = $row;
            }
            $quarterScores[$kpi['id']] = $allQuarters[$kpi['id']][$q] ?? null;
        }

        // Attendance — aggregate months for this quarter
        $quarterMonths = match($q) {
            'Q1' => [1, 2, 3], 'Q2' => [4, 5, 6],
            'Q3' => [7, 8, 9], 'Q4' => [10, 11, 12],
        };
        $attYear = ($q === 'Q4' && $mon === 1 && $now->day <= 6) ? ($year - 1) : $year;

        $allAttendance = $supabase->get('attendance_summary', [
            'employee_id' => 'eq.' . $user['id'],
            'year'        => 'eq.' . $attYear,
            'select'      => 'month,working_days,present_days,absent_days,late_count,total_late_minutes,mc_days,al_days,other_leave_days,insufficient_count',
        ]) ?? [];

        $qAttendance = array_filter($allAttendance, fn($r) => in_array((int)$r['month'], $quarterMonths));

        $attendanceSummary = [
            'has_data' => !empty($qAttendance), 'working_days' => 0, 'present_days' => 0,
            'absent_days' => 0, 'late_count' => 0, 'total_late_minutes' => 0,
            'mc_days' => 0, 'al_days' => 0, 'other_leave_days' => 0, 'insufficient_count' => 0, 'months' => [],
        ];
        foreach ($qAttendance as $ar) {
            $attendanceSummary['working_days']        += (int)($ar['working_days'] ?? 0);
            $attendanceSummary['present_days']        += (int)($ar['present_days'] ?? 0);
            $attendanceSummary['absent_days']         += (int)($ar['absent_days'] ?? 0);
            $attendanceSummary['late_count']          += (int)($ar['late_count'] ?? 0);
            $attendanceSummary['total_late_minutes']  += (int)($ar['total_late_minutes'] ?? 0);
            $attendanceSummary['mc_days']             += (int)($ar['mc_days'] ?? 0);
            $attendanceSummary['al_days']             += (int)($ar['al_days'] ?? 0);
            $attendanceSummary['other_leave_days']    += (int)($ar['other_leave_days'] ?? 0);
            $attendanceSummary['insufficient_count']  += (int)($ar['insufficient_count'] ?? 0);
            $attendanceSummary['months'][]            = \Carbon\Carbon::create($attYear, (int)$ar['month'], 1)->format('M Y');
        }

        $attendanceYTD = ['has_data' => !empty($allAttendance), 'mc_days' => 0, 'other_leave_days' => 0, 'late_count' => 0];
        foreach ($allAttendance as $ar) {
            $attendanceYTD['mc_days']          += (int)($ar['mc_days'] ?? 0);
            $attendanceYTD['other_leave_days'] += (int)($ar['other_leave_days'] ?? 0);
            $attendanceYTD['late_count']       += (int)($ar['late_count'] ?? 0);
        }

        // Assessment areas — vary by role
        $role = strtolower($user['role'] ?? '');
        $assessmentAreas = match(true) {
            $role === 'executive' => [
                ['no' =>  1, 'title' => 'Knowledge of Job Requirements',  'description' => 'Knowledge of job requirements, methods, techniques and skills involved in doing the job, and applying these to perform efficiently.'],
                ['no' =>  2, 'title' => 'Quality of Work Done',           'description' => 'Degree to which quality expectations of the job were fulfilled — accuracy, reliability, and excellence of end results.'],
                ['no' =>  3, 'title' => 'Planning & Organising Skills',   'description' => 'Degree to which the appraisee anticipated needs, forecast conditions, set goals and standards, planned and scheduled work.'],
                ['no' =>  4, 'title' => 'Decision Making',                'description' => 'Able to analyse problems effectively, make sound decisions, and commit to those decisions to achieve an acceptable result.'],
                ['no' =>  5, 'title' => 'Communication Skills',           'description' => 'Communicated effectively — verbal and written — with superiors and peers.'],
                ['no' =>  6, 'title' => 'Teamwork',                       'description' => 'Able to adopt and adapt in work conditions/situations and work with others toward a common objective.'],
                ['no' =>  7, 'title' => 'Interpersonal Relationships',    'description' => 'How well the appraisee related to associates, superiors, and external contacts to get the desired cooperation and assistance.'],
                ['no' =>  8, 'title' => 'Attitude Towards Work',          'description' => 'Able to work independently without direct supervision; adapted well to new tasks/changes; showed commitment in discharge of duties.'],
                ['no' =>  9, 'title' => 'Time Management / Tardiness',    'description' => 'Able to plan, execute and complete assigned tasks within deadline; conformed to company rules; punctual in attendance and timekeeping.'],
                ['no' => 10, 'title' => 'Appearance',                     'description' => 'Well-groomed; made an excellent impression.'],
                ['no' => 11, 'title' => 'Dependability / Accountability', 'description' => 'Carries out work with limited/minimum supervision, follows instructions; shows initiative to complete tasks efficiently and effectively.'],
                ['no' => 12, 'title' => 'Values',                         'description' => 'Understands and demonstrates organisation values at all times.'],
            ],
            $role === 'manager' || $role === 'vp' => [
                ['no' =>  1, 'title' => 'Quality of Work',                        'description' => 'Consistently promotes quality awareness and continuous improvement without decreasing productivity or increasing cost.'],
                ['no' =>  2, 'title' => 'Dependability / Accountability / Ownership', 'description' => 'Works with minimal supervision, follows instructions clearly, and shows initiative to complete tasks efficiently.'],
                ['no' =>  3, 'title' => 'Problem-Solving & Decision Making',      'description' => 'Identifies and rectifies work problems independently; provides solutions and recommendations.'],
                ['no' =>  4, 'title' => 'Time Management',                        'description' => 'Plans, executes and completes assigned tasks within the required deadline.'],
                ['no' =>  5, 'title' => 'Work Relationship / Service Orientation','description' => 'Builds cordial, positive relationships with colleagues and external parties; strong client rapport.'],
                ['no' =>  6, 'title' => 'Performance Target',                     'description' => 'Has achieved the expected KPIs set by the superior and/or Management.'],
                ['no' =>  7, 'title' => 'Leadership',                             'description' => 'Able to lead, develop, guide and motivate others toward a common objective.'],
                ['no' =>  8, 'title' => 'Multi-Tasking Capabilities',             'description' => 'Willing to accept more tasks without complaint; works well under pressure.'],
                ['no' =>  9, 'title' => 'Discipline (Attendance & Punctuality)',  'description' => 'Conforms to company rules at all times; punctual in attendance and timekeeping.'],
                ['no' => 10, 'title' => 'Appearance',                             'description' => 'Well-groomed; makes an excellent impression.'],
                ['no' => 11, 'title' => 'Communication / Interpersonal Skills',   'description' => 'Communicates effectively — verbal and written — with superiors, peers and subordinates.'],
                ['no' => 12, 'title' => 'Values',                                 'description' => 'Understands and demonstrates organisation values at all times.'],
            ],
            default => [
                ['no' =>  1, 'title' => 'Knowledge of Job Requirements',  'description' => 'Knowledge of job requirements, methods, techniques and skills involved in doing the job, and in applying these to perform efficiently.'],
                ['no' =>  2, 'title' => 'Quality of Work Done',           'description' => 'To what degree did the appraisee fulfil the quality expectations of the job? Was the work completed accurate and reliable? What is the degree of excellence of end results?'],
                ['no' =>  3, 'title' => 'Planning and Organising Skills', 'description' => 'To what degree did the appraisee anticipate needs, forecast conditions, set goals and standards, plan and schedule work?'],
                ['no' =>  4, 'title' => 'Decision Making',                'description' => 'Was the appraisee able to analyse problems effectively and make sound decisions and commit to those decisions to achieve an acceptable result?'],
                ['no' =>  5, 'title' => 'Communication Skills',           'description' => 'Did the appraisee communicate effectively verbal and written, with superiors and peers?'],
                ['no' =>  6, 'title' => 'Teamwork',                       'description' => 'Was the appraisee able to adopt and adapt in work conditions/situations and work with others toward a common objective?'],
                ['no' =>  7, 'title' => 'Interpersonal Relationships',    'description' => 'How well did the appraisee relate to associates, superiors, and external contacts to get the desired cooperation and assistance?'],
                ['no' =>  8, 'title' => 'Attitude Towards Work',          'description' => 'Was the appraisee able to work independently without need for direct supervision? How well did the appraisee adapt to new tasks and to changes in the work environment? Did the appraisee show commitment in discharge of his/her duties?'],
                ['no' =>  9, 'title' => 'Time Management / Tardiness',    'description' => "Is the appraisee able to plan, execute and complete assigned tasks within the deadline given? Did the appraisee conform to Company's rules and regulations at all times? Was the appraisee punctual in attendance and timekeeping?"],
                ['no' => 10, 'title' => 'Appearance',                     'description' => 'Was the appraisee well groomed? Did the appraisee make an excellent impression?'],
                ['no' => 11, 'title' => 'Dependability / Accountability', 'description' => 'Able to carry out work with limited or minimum supervision and able to follow work instructions. Demonstrates high level of commitment to complete tasks assigned and shows initiative in ensuring job is completed efficiently and effectively.'],
                ['no' => 12, 'title' => 'Values',                         'description' => 'Does the appraisee understand and demonstrate organisation values all the time?'],
            ],
        };

        // Saved data for this quarter
        $savedRows    = $supabase->get('performance_reports', [
            'employee_id'    => 'eq.' . $user['id'],
            'financial_year' => 'eq.' . $this->currentFinancialYear,
            'quarter'        => 'eq.' . $q,
            'select'         => 'form_data,status,updated_at',
        ]) ?? [];
        $savedData    = !empty($savedRows) ? ($savedRows[0]['form_data'] ?? null) : null;
        $reportStatus = !empty($savedRows) ? ($savedRows[0]['status'] ?? 'draft') : 'draft';
        $submittedAt  = !empty($savedRows) ? ($savedRows[0]['updated_at'] ?? null) : null;

        // Once the manager's section is locked, the appraiser shown here must
        // stay whoever actually appraised it — snapshotted at submission time
        // in appraiserSave() — rather than a live reports_to_id lookup, which
        // can drift afterwards (reorg, promotion, a BTS delegation ending).
        // Reports completed before this snapshot existed fall back to Part
        // D's own saved appraiser name/designation, which was already being
        // captured at submission time, before falling back further to a live
        // lookup for a report that isn't locked yet.
        $frozenAppraiserName     = $savedData['_manager_appraiser_name'] ?? $savedData['part_d_name'] ?? null;
        $frozenAppraiserPosition = $savedData['_manager_appraiser_position'] ?? $savedData['part_d_designation'] ?? null;

        return view('performance.report', [
            'user'                 => $user,
            'currentUserName'      => $user['full_name'] ?? $user['short_name'] ?? 'User',
            'userPosition'         => $user['position'] ?? $user['role'] ?? '-',
            'departmentName'       => $department['name'] ?? $user['department_code'] ?? '-',
            'reportsToName'        => $frozenAppraiserName ?? ($reportsTo ? ($reportsTo['full_name'] ?? $reportsTo['short_name'] ?? '-') : '-'),
            'reportsToPosition'    => $frozenAppraiserPosition ?? ($reportsTo['position'] ?? $reportsTo['role'] ?? '-'),
            'joinDate'             => $joinDate ? \Carbon\Carbon::parse($joinDate)->format('d M Y') : '—',
            'tenure'               => $tenure,
            'currentFinancialYear' => $this->currentFinancialYear,
            'quarter'              => $q,
            'displayQuarter'       => (int) substr($q, 1),
            'qLabel'               => $q,
            'isWindowOpen'         => $isWindowOpen,
            'isFuture'             => $isFuture,
            'windowStart'          => $windowStart,
            'windowEnd'            => $windowEnd,
            'quarterOverrideActive' => (bool) $quarterOverride,
            'kpis'                 => $kpis,
            'quarterScores'        => $quarterScores,
            'allQuarters'          => $allQuarters,
            'assessmentAreas'      => $assessmentAreas,
            'attendanceSummary'    => $attendanceSummary,
            'attendanceYTD'        => $attendanceYTD,
            'savedData'            => $savedData,
            'submittedAt'          => $submittedAt,
            'status'               => $reportStatus,
            'isAppraiserView'      => false,
        ]);
    }

    public function saveReport(string $quarter, \Illuminate\Http\Request $request, SupabaseService $supabase, \App\Services\NotificationService $notifications)
    {
        if (!session()->has('employee_uuid')) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $q = strtoupper($quarter);
        if (!in_array($q, ['Q1','Q2','Q3','Q4'])) {
            return response()->json(['error' => 'Invalid quarter'], 422);
        }

        $action = $request->input('action', 'draft');

        $existing      = $supabase->get('performance_reports', [
            'employee_id'    => 'eq.' . session('employee_uuid'),
            'financial_year' => 'eq.' . $this->currentFinancialYear,
            'quarter'        => 'eq.' . $q,
            'select'         => 'form_data,status',
        ]) ?? [];
        $currentStatus = !empty($existing) ? ($existing[0]['status'] ?? 'draft') : 'draft';

        // Appraisee can only sign the final acknowledgment after the appraiser
        // has reviewed and signed (status = appraised). Enforced server-side
        // so it can't be bypassed by tampering with the client.
        if ($action === 'acknowledge' && $currentStatus !== 'appraised') {
            return response()->json([
                'error' => 'You can only sign after your appraiser has reviewed and signed the appraisal.',
            ], 422);
        }

        $status = match ($action) {
            'submit'      => 'submitted',
            'acknowledge' => 'completed',
            default       => 'draft',
        };

        $existingData = !empty($existing) ? ($existing[0]['form_data'] ?? []) : [];
        $newData      = array_merge($existingData, $request->input('form_data', []));

        $supabase->upsert('performance_reports', [
            'employee_id'    => session('employee_uuid'),
            'company_code'   => session('company_code'),
            'financial_year' => $this->currentFinancialYear,
            'quarter'        => $q,
            'form_data'      => $newData,
            'status'         => $status,
            'submitted_at'   => now()->toISOString(),
            'updated_at'     => now()->toISOString(),
        ], 'employee_id,financial_year,quarter');

        // Only notify on the transition INTO "submitted" — not on every call with
        // action=submit. Without this guard, a duplicate request (double-tab,
        // a retried fetch, a resubmit after reload) fires this a second time
        // and sends the manager a duplicate "submitted their appraisal" notification
        // even though nothing new was actually submitted.
        if ($action === 'submit' && !in_array($currentStatus, ['submitted', 'appraised', 'completed'], true)) {
            $employee = $supabase->first('employees', [
                'id'     => 'eq.' . session('employee_uuid'),
                'select' => 'id,full_name,short_name',
            ]);
            $employeeName = $employee['full_name'] ?? $employee['short_name'] ?? 'An employee';

            // Only the direct manager reviews a freshly-submitted self-assessment —
            // VP/SLT are notified separately once the manager has scored and signed
            // (see appraiserSave()), so they aren't alerted before there's anything
            // to add remarks on.
            $directManager = $notifications->appraiserChainFor(session('employee_uuid'))[0] ?? null;
            if ($directManager) {
                $notifications->notify(
                    [$directManager],
                    'appraisal_submitted',
                    ['id' => session('employee_uuid'), 'name' => $employeeName],
                    "{$employeeName} submitted their {$q} appraisal",
                    'Ready for your review.',
                    route('performance.appraise.report', [session('employee_uuid'), strtolower($q)]),
                    $q,
                    $this->currentFinancialYear
                );
            }
        }

        return response()->json(['success' => true, 'quarter' => $q, 'status' => $status]);
    }

    /**
     * Walks up an employee's approver chain (manager, then that manager's
     * approver = VP, then that VP's approver = SLT) to find which level —
     * if any — $viewerId sits at relative to $employee. Section 7 of the
     * appraisal form has a separate remarks block for each of these three
     * levels, so appraiser access isn't just "the direct manager".
     *
     * Each step's parent id is resolved with the exact same per-role field
     * priority as ApprovalHierarchyService::getApprover() (manager_id/vp_id
     * with reports_to_id as fallback) — this used to walk reports_to_id
     * only, which disagreed with who actually got the "submitted for
     * appraisal" notification whenever manager_id/vp_id was set but
     * reports_to_id wasn't pointing at the same person, 403ing the very
     * appraiser the notification was sent to.
     *
     * $getParent resolves an id into that employee's own record — either a
     * live Supabase lookup or a pre-fetched map, depending on caller.
     *
     * The per-role parent lookup (and any active BTS appraiser delegation --
     * see AppraiserDelegationService) is resolved by $delegations->nextParentId()
     * rather than inline, so this and NotificationService::appraiserChainFor()
     * can never disagree about who's next up the chain.
     */
    private function resolveAppraiserLevel(?array $employee, string $viewerId, callable $getParent, AppraiserDelegationService $delegations): ?string
    {
        $levels = ['manager', 'vp', 'slt'];
        $current = $employee;

        foreach ($levels as $level) {
            $parentId = $delegations->nextParentId($current ?? []);

            if (empty($parentId)) {
                return null;
            }
            if ($parentId === $viewerId) {
                return $level;
            }
            $current = $getParent($parentId);
            if (empty($current)) {
                return null;
            }
        }

        return null;
    }

    /**
     * Section 2's "View" — reuses the SLT staff-KPI-drilldown design
     * (dashboard/staff-kpi-detail.blade.php: header card + quarter cards
     * with target/actual/progress/remark/attachment) for any appraiser in
     * the employee's chain, not just SLT Office. Scoped to Q1 up through
     * whichever quarter is being evaluated — an appraiser reviewing Q1
     * only sees Q1, not unstarted future quarters.
     *
     * Serves two callers: a normal page load (full HTML, with sidebar) and
     * the Section 2 "View" popup, which passes ?partial=1 and gets back
     * just this content's markup to inject into its modal — same data,
     * same authorization, no separate endpoint to keep in sync.
     */
    public function viewAppraiseeKpi(string $employeeId, string $kpiId, \Illuminate\Http\Request $request, SupabaseService $supabase, AppraiserDelegationService $delegations)
    {
        if (!session()->has('employee_uuid')) {
            return $request->query('partial')
                ? response()->json(['error' => 'Not logged in.'], 401)
                : redirect()->route('login');
        }

        $fromQuarter = strtolower($request->query('quarter', 'q2'));
        if (!in_array($fromQuarter, ['q1', 'q2', 'q3', 'q4'], true)) {
            $fromQuarter = 'q2';
        }
        $quartersToShow = array_slice(['Q1', 'Q2', 'Q3', 'Q4'], 0, (int) substr($fromQuarter, 1));

        $viewerId = session('employee_uuid');
        $staff = $supabase->first('employees', [
            'id'        => 'eq.' . $employeeId,
            'is_active' => 'eq.true',
            'select'    => '*',
        ]);
        if (!$staff) {
            abort(404, 'Employee not found.');
        }

        $appraiserLevel = $this->resolveAppraiserLevel(
            $staff,
            $viewerId,
            fn ($id) => $supabase->first('employees', ['id' => 'eq.' . $id, 'select' => '*']),
            $delegations
        );
        if (!$appraiserLevel && $this->isBtsSession()) {
            $appraiserLevel = 'manager';
        }
        if (!$appraiserLevel) {
            abort(403, "You aren't in {$staff['short_name']}'s approver chain (manager/VP/SLT), so you can't view their KPI.");
        }

        $kpi = $supabase->first('kpis', [
            'id'          => 'eq.' . $kpiId,
            'employee_id' => 'eq.' . $employeeId,
            'select'      => '*',
        ]);
        if (!$kpi) {
            abort(404, 'KPI not found.');
        }

        $viewer = $supabase->first('employees', [
            'id'     => 'eq.' . $viewerId,
            'select' => '*',
        ]);

        $viewerDepartment = null;
        if (!empty($viewer['department_code'])) {
            $viewerDepartment = $supabase->first('departments', [
                'code'   => 'eq.' . $viewer['department_code'],
                'select' => '*',
            ]);
        }

        $quarters = $supabase->get('kpi_quarters', [
            'kpi_id' => 'eq.' . $kpiId,
            'select' => '*',
        ]) ?? [];

        $quarters = collect($quarters)->map(function ($q) {
            $target = max(0, (float) ($q['quarter_target'] ?? 0));
            $actual = max(0, (float) ($q['quarter_actual'] ?? 0));
            $q['progress_pct'] = $target > 0 ? round(($actual / $target) * 100, 1) : 0;
            return $q;
        })->sortBy('quarter')->values()->all();

        $filledQuarters = collect($quarters)->filter(
            fn ($q) => (float) ($q['quarter_actual'] ?? 0) > 0
        );

        $average = $filledQuarters->count() > 0
            ? round($filledQuarters->avg('progress_pct'), 1)
            : 0;

        $viewData = [
            'staff'                => $staff,
            'kpi'                  => $kpi,
            'quarters'             => $quarters,
            'quartersToShow'       => $quartersToShow,
            'average'              => $average,
            'currentFinancialYear' => $this->currentFinancialYear,
        ];

        if ($request->query('partial')) {
            return response(
                view('dashboard.partials.kpi-detail-content', $viewData)->render()
            )->header('Content-Type', 'text/html');
        }

        return view('dashboard.staff-kpi-detail', $viewData + [
            'user'                 => $viewer,
            'department'           => $viewerDepartment,
            'backUrl'              => route('performance.appraise.report', [$employeeId, $fromQuarter]),
            'backLabel'            => 'Back to ' . ($staff['short_name'] ?? $staff['full_name'] ?? 'Staff') . "'s Appraisal",
        ]);
    }

    public function appraiserReport(string $employeeId, string $quarter, SupabaseService $supabase, AppraiserDelegationService $delegations)
    {
        if (!session()->has('employee_uuid')) {
            return redirect()->route('login');
        }

        $q = strtoupper($quarter);
        if (!in_array($q, ['Q1','Q2','Q3','Q4'])) abort(404);

        // Verify the viewer sits somewhere in this employee's appraiser chain
        // (direct manager, VP, or SLT) — not just a direct report.
        $viewerId = session('employee_uuid');
        $employees = $supabase->get('employees', [
            'id'        => 'eq.' . $employeeId,
            'is_active' => 'eq.true',
            'select'    => '*',
        ]);
        if (empty($employees)) abort(403, 'Employee not found or inactive.');
        $user = $employees[0];

        $appraiserLevel = $this->resolveAppraiserLevel(
            $user,
            $viewerId,
            fn($id) => $supabase->first('employees', ['id' => 'eq.' . $id, 'select' => '*']),
            $delegations
        );
        // BTS gets full support access regardless of the actual approver
        // chain — including while impersonating someone via View As, where
        // the impersonated employee's own chain is irrelevant to BTS's role.
        // Defaults to 'manager', the widest level (KPI scores + all
        // sections), not just Section 7 remarks.
        if (!$appraiserLevel && $this->isBtsSession()) {
            $appraiserLevel = 'manager';
        }
        if (!$appraiserLevel) {
            abort(403, "You aren't in {$user['short_name']}'s approver chain (manager/VP/SLT), so you can't open their appraisal. If you're using View As, check the profile you're impersonating is still active — it may have reverted.");
        }

        // Tenure
        $joinDate = $user['join_date'] ?? null;
        $tenure   = '—';
        if ($joinDate) {
            $diff  = \Carbon\Carbon::parse($joinDate)->diff(now());
            $parts = [];
            if ($diff->y > 0) $parts[] = $diff->y . ' year' . ($diff->y !== 1 ? 's' : '');
            if ($diff->m > 0) $parts[] = $diff->m . ' month' . ($diff->m !== 1 ? 's' : '');
            $tenure = $parts ? implode(' ', $parts) : 'Less than 1 month';
        }

        // Reports-to — the employee's own manager (per reports_to_id), same
        // as every self-view page. Deliberately not session('employee_uuid'):
        // this used to show whichever appraiser (manager/VP/SLT) happened to
        // be viewing the report, so the same employee's "Reporting To" field
        // changed depending on who opened it instead of reflecting their
        // actual reporting line.
        $reportsTo = null;
        if (!empty($user['reports_to_id'])) {
            $managers  = $supabase->get('employees', [
                'id'     => 'eq.' . $user['reports_to_id'],
                'select' => 'id,short_name,full_name,role,position',
            ]);
            $reportsTo = $managers[0] ?? null;
        }

        // Department
        $department = null;
        if (!empty($user['department_code'])) {
            $depts      = $supabase->get('departments', ['code' => 'eq.' . $user['department_code'], 'select' => '*']);
            $department = $depts[0] ?? null;
        }

        // Window dates
        $now  = now()->timezone('Asia/Kuala_Lumpur');
        $year = (int) $now->year;
        $mon  = (int) $now->month;
        $windows = [
            'Q1' => ['start' => "{$year}-03-24", 'end' => "2026-07-31"],
            'Q2' => ['start' => "{$year}-06-23", 'end' => "{$year}-07-31"],
            'Q3' => ['start' => "{$year}-09-22", 'end' => "{$year}-10-06"],
            'Q4' => ['start' => "{$year}-12-23", 'end' => ($year + 1) . "-01-06"],
        ];
        if ($q === 'Q4' && $mon === 1 && $now->day <= 6) {
            $py = $year - 1;
            $windows['Q4'] = ['start' => "{$py}-12-23", 'end' => "{$year}-01-06"];
        }
        $window      = $windows[$q];
        $windowStart = \Carbon\Carbon::parse($window['start'])->format('d M Y');
        $windowEnd   = \Carbon\Carbon::parse($window['end'])->format('d M Y');

        // KPIs for the subordinate
        $kpis = $supabase->get('kpis', [
            'employee_id'    => 'eq.' . $user['id'],
            'financial_year' => 'eq.' . $this->currentFinancialYear,
            'select'         => 'id,kpi_title,category,sub_category,unit,base_target,actual_value,status,weightage',
        ]) ?? [];

        $quarterScores = [];
        $allQuarters   = [];
        foreach ($kpis as $kpi) {
            $qRows = $supabase->get('kpi_quarters', [
                'kpi_id' => 'eq.' . $kpi['id'],
                'select' => 'quarter,quarter_title,quarter_target,quarter_actual,status,remark,completion_review,completion_proof_urls',
            ]);
            foreach ($qRows as $row) {
                $allQuarters[$kpi['id']][$row['quarter']] = $row;
            }
            $quarterScores[$kpi['id']] = $allQuarters[$kpi['id']][$q] ?? null;
        }

        // Attendance
        $quarterMonths = match($q) {
            'Q1' => [1, 2, 3], 'Q2' => [4, 5, 6],
            'Q3' => [7, 8, 9], 'Q4' => [10, 11, 12],
        };
        $attYear = ($q === 'Q4' && $mon === 1 && $now->day <= 6) ? ($year - 1) : $year;
        $allAttendance = $supabase->get('attendance_summary', [
            'employee_id' => 'eq.' . $user['id'],
            'year'        => 'eq.' . $attYear,
            'select'      => 'month,working_days,present_days,absent_days,late_count,total_late_minutes,mc_days,al_days,other_leave_days,insufficient_count',
        ]) ?? [];
        $qAttendance = array_filter($allAttendance, fn($r) => in_array((int)$r['month'], $quarterMonths));
        $attendanceSummary = [
            'has_data' => !empty($qAttendance), 'working_days' => 0, 'present_days' => 0,
            'absent_days' => 0, 'late_count' => 0, 'total_late_minutes' => 0,
            'mc_days' => 0, 'al_days' => 0, 'other_leave_days' => 0, 'insufficient_count' => 0, 'months' => [],
        ];
        foreach ($qAttendance as $ar) {
            $attendanceSummary['working_days']        += (int)($ar['working_days'] ?? 0);
            $attendanceSummary['present_days']        += (int)($ar['present_days'] ?? 0);
            $attendanceSummary['absent_days']         += (int)($ar['absent_days'] ?? 0);
            $attendanceSummary['late_count']          += (int)($ar['late_count'] ?? 0);
            $attendanceSummary['total_late_minutes']  += (int)($ar['total_late_minutes'] ?? 0);
            $attendanceSummary['mc_days']             += (int)($ar['mc_days'] ?? 0);
            $attendanceSummary['al_days']             += (int)($ar['al_days'] ?? 0);
            $attendanceSummary['other_leave_days']    += (int)($ar['other_leave_days'] ?? 0);
            $attendanceSummary['insufficient_count']  += (int)($ar['insufficient_count'] ?? 0);
            $attendanceSummary['months'][]            = \Carbon\Carbon::create($attYear, (int)$ar['month'], 1)->format('M Y');
        }
        $attendanceYTD = ['has_data' => !empty($allAttendance), 'mc_days' => 0, 'other_leave_days' => 0, 'late_count' => 0];
        foreach ($allAttendance as $ar) {
            $attendanceYTD['mc_days']          += (int)($ar['mc_days'] ?? 0);
            $attendanceYTD['other_leave_days'] += (int)($ar['other_leave_days'] ?? 0);
            $attendanceYTD['late_count']       += (int)($ar['late_count'] ?? 0);
        }

        // Assessment areas — same logic as reportQuarter
        $role = strtolower($user['role'] ?? '');
        $assessmentAreas = match(true) {
            $role === 'executive' => [
                ['no' =>  1, 'title' => 'Knowledge of Job Requirements',  'description' => 'Knowledge of job requirements, methods, techniques and skills involved in doing the job, and applying these to perform efficiently.'],
                ['no' =>  2, 'title' => 'Quality of Work Done',           'description' => 'Degree to which quality expectations of the job were fulfilled — accuracy, reliability, and excellence of end results.'],
                ['no' =>  3, 'title' => 'Planning & Organising Skills',   'description' => 'Degree to which the appraisee anticipated needs, forecast conditions, set goals and standards, planned and scheduled work.'],
                ['no' =>  4, 'title' => 'Decision Making',                'description' => 'Able to analyse problems effectively, make sound decisions, and commit to those decisions to achieve an acceptable result.'],
                ['no' =>  5, 'title' => 'Communication Skills',           'description' => 'Communicated effectively — verbal and written — with superiors and peers.'],
                ['no' =>  6, 'title' => 'Teamwork',                       'description' => 'Able to adopt and adapt in work conditions/situations and work with others toward a common objective.'],
                ['no' =>  7, 'title' => 'Interpersonal Relationships',    'description' => 'How well the appraisee related to associates, superiors, and external contacts to get the desired cooperation and assistance.'],
                ['no' =>  8, 'title' => 'Attitude Towards Work',          'description' => 'Able to work independently without direct supervision; adapted well to new tasks/changes; showed commitment in discharge of duties.'],
                ['no' =>  9, 'title' => 'Time Management / Tardiness',    'description' => 'Able to plan, execute and complete assigned tasks within deadline; conformed to company rules; punctual in attendance and timekeeping.'],
                ['no' => 10, 'title' => 'Appearance',                     'description' => 'Well-groomed; made an excellent impression.'],
                ['no' => 11, 'title' => 'Dependability / Accountability', 'description' => 'Carries out work with limited/minimum supervision, follows instructions; shows initiative to complete tasks efficiently and effectively.'],
                ['no' => 12, 'title' => 'Values',                         'description' => 'Understands and demonstrates organisation values at all times.'],
            ],
            $role === 'manager' || $role === 'vp' => [
                ['no' =>  1, 'title' => 'Quality of Work',                        'description' => 'Consistently promotes quality awareness and continuous improvement without decreasing productivity or increasing cost.'],
                ['no' =>  2, 'title' => 'Dependability / Accountability / Ownership', 'description' => 'Works with minimal supervision, follows instructions clearly, and shows initiative to complete tasks efficiently.'],
                ['no' =>  3, 'title' => 'Problem-Solving & Decision Making',      'description' => 'Identifies and rectifies work problems independently; provides solutions and recommendations.'],
                ['no' =>  4, 'title' => 'Time Management',                        'description' => 'Plans, executes and completes assigned tasks within the required deadline.'],
                ['no' =>  5, 'title' => 'Work Relationship / Service Orientation','description' => 'Builds cordial, positive relationships with colleagues and external parties; strong client rapport.'],
                ['no' =>  6, 'title' => 'Performance Target',                     'description' => 'Has achieved the expected KPIs set by the superior and/or Management.'],
                ['no' =>  7, 'title' => 'Leadership',                             'description' => 'Able to lead, develop, guide and motivate others toward a common objective.'],
                ['no' =>  8, 'title' => 'Multi-Tasking Capabilities',             'description' => 'Willing to accept more tasks without complaint; works well under pressure.'],
                ['no' =>  9, 'title' => 'Discipline (Attendance & Punctuality)',  'description' => 'Conforms to company rules at all times; punctual in attendance and timekeeping.'],
                ['no' => 10, 'title' => 'Appearance',                             'description' => 'Well-groomed; makes an excellent impression.'],
                ['no' => 11, 'title' => 'Communication / Interpersonal Skills',   'description' => 'Communicates effectively — verbal and written — with superiors, peers and subordinates.'],
                ['no' => 12, 'title' => 'Values',                                 'description' => 'Understands and demonstrates organisation values at all times.'],
            ],
            default => [
                ['no' =>  1, 'title' => 'Knowledge of Job Requirements',  'description' => 'Knowledge of job requirements, methods, techniques and skills involved in doing the job, and in applying these to perform efficiently.'],
                ['no' =>  2, 'title' => 'Quality of Work Done',           'description' => 'To what degree did the appraisee fulfil the quality expectations of the job? Was the work completed accurate and reliable? What is the degree of excellence of end results?'],
                ['no' =>  3, 'title' => 'Planning and Organising Skills', 'description' => 'To what degree did the appraisee anticipate needs, forecast conditions, set goals and standards, plan and schedule work?'],
                ['no' =>  4, 'title' => 'Decision Making',                'description' => 'Was the appraisee able to analyse problems effectively and make sound decisions and commit to those decisions to achieve an acceptable result?'],
                ['no' =>  5, 'title' => 'Communication Skills',           'description' => 'Did the appraisee communicate effectively verbal and written, with superiors and peers?'],
                ['no' =>  6, 'title' => 'Teamwork',                       'description' => 'Was the appraisee able to adopt and adapt in work conditions/situations and work with others toward a common objective?'],
                ['no' =>  7, 'title' => 'Interpersonal Relationships',    'description' => 'How well did the appraisee relate to associates, superiors, and external contacts to get the desired cooperation and assistance?'],
                ['no' =>  8, 'title' => 'Attitude Towards Work',          'description' => 'Was the appraisee able to work independently without need for direct supervision? How well did the appraisee adapt to new tasks and to changes in the work environment? Did the appraisee show commitment in discharge of his/her duties?'],
                ['no' =>  9, 'title' => 'Time Management / Tardiness',    'description' => "Is the appraisee able to plan, execute and complete assigned tasks within the deadline given? Did the appraisee conform to Company's rules and regulations at all times? Was the appraisee punctual in attendance and timekeeping?"],
                ['no' => 10, 'title' => 'Appearance',                     'description' => 'Was the appraisee well groomed? Did the appraisee make an excellent impression?'],
                ['no' => 11, 'title' => 'Dependability / Accountability', 'description' => 'Able to carry out work with limited or minimum supervision and able to follow work instructions. Demonstrates high level of commitment to complete tasks assigned and shows initiative in ensuring job is completed efficiently and effectively.'],
                ['no' => 12, 'title' => 'Values',                         'description' => 'Does the appraisee understand and demonstrate organisation values all the time?'],
            ],
        };

        // Saved data
        $savedRows = $supabase->get('performance_reports', [
            'employee_id'    => 'eq.' . $user['id'],
            'financial_year' => 'eq.' . $this->currentFinancialYear,
            'quarter'        => 'eq.' . $q,
            'select'         => 'form_data,status,updated_at',
        ]) ?? [];
        $savedData    = !empty($savedRows) ? ($savedRows[0]['form_data'] ?? null) : null;
        $reportStatus = !empty($savedRows) ? ($savedRows[0]['status'] ?? 'draft') : 'draft';
        $submittedAt  = !empty($savedRows) ? ($savedRows[0]['updated_at'] ?? null) : null;

        // Each appraiser level submits its own section independently (manager's
        // 6B/7A, VP's 7B, SLT's 7C) — the lock flag lives inside form_data since
        // there's no dedicated column per level.
        $myLevelLocked = !empty($savedData["_{$appraiserLevel}_locked"]);

        // Once the manager's section is locked, the appraiser shown here must
        // stay whoever actually appraised it — snapshotted at submission time
        // in appraiserSave() — rather than a live reports_to_id lookup, which
        // can drift afterwards (reorg, promotion, a BTS delegation ending).
        // Reports completed before this snapshot existed fall back to Part
        // D's own saved appraiser name/designation, which was already being
        // captured at submission time, before falling back further to a live
        // lookup for a report that isn't locked yet.
        $frozenAppraiserName     = $savedData['_manager_appraiser_name'] ?? $savedData['part_d_name'] ?? null;
        $frozenAppraiserPosition = $savedData['_manager_appraiser_position'] ?? $savedData['part_d_designation'] ?? null;

        return view('performance.report', [
            'user'                 => $user,
            'currentUserName'      => $user['full_name'] ?? $user['short_name'] ?? 'User',
            'userPosition'         => $user['position'] ?? $user['role'] ?? '-',
            'departmentName'       => $department['name'] ?? $user['department_code'] ?? '-',
            'reportsToName'        => $frozenAppraiserName ?? ($reportsTo ? ($reportsTo['full_name'] ?? $reportsTo['short_name'] ?? '-') : '-'),
            'reportsToPosition'    => $frozenAppraiserPosition ?? ($reportsTo['position'] ?? $reportsTo['role'] ?? '-'),
            'joinDate'             => $joinDate ? \Carbon\Carbon::parse($joinDate)->format('d M Y') : '—',
            'tenure'               => $tenure,
            'currentFinancialYear' => $this->currentFinancialYear,
            'quarter'              => $q,
            'displayQuarter'       => (int) substr($q, 1),
            'qLabel'               => $q,
            'isWindowOpen'         => in_array($reportStatus, ['submitted', 'appraised']),
            'windowStart'          => $windowStart,
            'windowEnd'            => $windowEnd,
            'kpis'                 => $kpis,
            'quarterScores'        => $quarterScores,
            'allQuarters'          => $allQuarters,
            'assessmentAreas'      => $assessmentAreas,
            'attendanceSummary'    => $attendanceSummary,
            'attendanceYTD'        => $attendanceYTD,
            'savedData'            => $savedData,
            'submittedAt'          => $submittedAt,
            'status'               => $reportStatus,
            'isAppraiserView'      => true,
            'appraiseeId'          => $employeeId,
            'appraiserLevel'       => $appraiserLevel,
            'appraiserSaveUrl'     => route('performance.appraise.save', [$employeeId, $q]),
            'myLevelLocked'        => $myLevelLocked,
        ]);
    }

    // What must be filled before an appraiser level is allowed to submit (and
    // therefore lock) their section. Manager's submit is the one that advances
    // the whole appraisal to "appraised" and unlocks the appraisee's own
    // acknowledgment signature, so it's held to the fullest standard: their
    // KPI/attitude scores, Section 7 remarks, and both signature fields
    // (the overall appraiser sign-off plus their Section 7 one).
    private function missingAppraiserFields(string $level, array $data): array
    {
        $isBlank = fn($v) => !isset($v) || $v === '' || $v === null;

        $checks = match ($level) {
            'manager' => [
                'Appraiser score (Section 2)'        => $data['s6_s2_app'] ?? null,
                'Superior rating (Section 3)'        => $data['s6_s3_app'] ?? null,
                'Recommendations & remarks (Section 7)' => $data['s7_manager_remarks'] ?? null,
                'Appraiser signature'                => $data['sig_appraiser'] ?? null,
                'Section 7 signature'                => $data['s7_manager_sig'] ?? null,
            ],
            'vp' => [
                'VP remarks (Section 7)'    => $data['s7_vp_remarks'] ?? null,
                'VP signature (Section 7)'  => $data['s7_vp_sig'] ?? null,
            ],
            'slt' => [
                'SLT remarks (Section 7)'   => $data['s7_slt_remarks'] ?? null,
                'SLT signature (Section 7)' => $data['s7_slt_sig'] ?? null,
            ],
            default => [],
        };

        $missing = [];
        foreach ($checks as $label => $value) {
            if ($isBlank($value)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public function appraiserSave(string $employeeId, string $quarter, \Illuminate\Http\Request $request, SupabaseService $supabase, \App\Services\NotificationService $notifications, AppraiserDelegationService $delegations)
    {
        if (!session()->has('employee_uuid')) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $q = strtoupper($quarter);
        if (!in_array($q, ['Q1','Q2','Q3','Q4'])) {
            return response()->json(['error' => 'Invalid quarter'], 422);
        }

        // Verify the viewer sits somewhere in this employee's appraiser chain
        // (direct manager, VP, or SLT) — not just a direct report.
        $viewerId = session('employee_uuid');
        $employees = $supabase->get('employees', [
            'id'        => 'eq.' . $employeeId,
            'is_active' => 'eq.true',
            'select'    => '*',
        ]);
        if (empty($employees)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $appraiserLevel = $this->resolveAppraiserLevel(
            $employees[0],
            $viewerId,
            fn($id) => $supabase->first('employees', ['id' => 'eq.' . $id, 'select' => '*']),
            $delegations
        );
        // BTS gets full support access regardless of the actual approver
        // chain — see the same bypass in appraiserReport() above.
        if (!$appraiserLevel && $this->isBtsSession()) {
            $appraiserLevel = 'manager';
        }
        if (!$appraiserLevel) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $action = $request->input('action', 'draft');

        // Fetch existing form_data first — needed both to check whether this
        // level already submitted (and is therefore locked) and to merge onto.
        $existing      = $supabase->get('performance_reports', [
            'employee_id'    => 'eq.' . $employeeId,
            'financial_year' => 'eq.' . $this->currentFinancialYear,
            'quarter'        => 'eq.' . $q,
            'select'         => 'form_data,status',
        ]) ?? [];
        $existingData  = !empty($existing) ? ($existing[0]['form_data'] ?? []) : [];
        $currentStatus = !empty($existing) ? ($existing[0]['status'] ?? 'submitted') : 'submitted';

        // Each appraiser level submits its own section independently — once
        // locked, neither a draft-save nor another submit may touch it again,
        // even if the client were tampered with (the button is hidden, but this
        // is the actual enforcement).
        $lockKey = "_{$appraiserLevel}_locked";
        if (!empty($existingData[$lockKey])) {
            return response()->json(['error' => 'Your section has already been submitted and is locked.'], 403);
        }

        // Each appraiser level may only write its own portion of the form —
        // enforced here so a VP/SLT session can't smuggle in edits to the
        // manager's Section 6B / KPI scores, or another level's Section 7
        // block, even if the client were tampered with.
        $allowedPrefixes = match ($appraiserLevel) {
            'manager' => ['kpi_app_', 'att_comment_', 'att_count_', 'sup_', 'cv_app_', 'cv_remark_', 's6_', 'sig_appraiser', 's7_manager_'],
            'vp'      => ['s7_vp_'],
            'slt'     => ['s7_slt_'],
            default   => [],
        };

        $incoming = $request->input('form_data', []);
        $allowedData = [];
        foreach ($incoming as $key => $value) {
            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $allowedData[$key] = $value;
                    break;
                }
            }
        }

        $mergedData = array_merge($existingData, $allowedData);

        // Submitting locks this level's section, so require everything it's
        // responsible for to actually be filled in first — otherwise a
        // half-empty appraisal could get locked with no way to fix it. The
        // fields they *did* fill in are still saved as a draft so nothing
        // typed gets lost, just not locked/advanced.
        if ($action === 'submit') {
            $missing = $this->missingAppraiserFields($appraiserLevel, $mergedData);
            if (!empty($missing)) {
                $supabase->upsert('performance_reports', [
                    'employee_id'    => $employeeId,
                    'company_code'   => session('company_code'),
                    'financial_year' => $this->currentFinancialYear,
                    'quarter'        => $q,
                    'form_data'      => $mergedData,
                    'status'         => $currentStatus,
                    'updated_at'     => now()->toISOString(),
                ], 'employee_id,financial_year,quarter');

                return response()->json([
                    'error'   => 'Please complete before submitting: ' . implode(', ', $missing) . '.',
                    'missing' => $missing,
                ], 422);
            }

            $mergedData[$lockKey] = true;

            // Snapshot who actually appraised this level, frozen at the moment
            // of submission. employees.reports_to_id/manager_id/vp_id can
            // change afterwards (reorg, promotion, a BTS delegation ending),
            // which must never retroactively change who a completed section
            // is displayed as having been appraised by.
            $viewer = $supabase->first('employees', ['id' => 'eq.' . $viewerId, 'select' => 'full_name,short_name,position,role']);
            $mergedData["_{$appraiserLevel}_appraiser_name"]     = $viewer['full_name'] ?? $viewer['short_name'] ?? null;
            $mergedData["_{$appraiserLevel}_appraiser_position"] = $viewer['position'] ?? $viewer['role'] ?? null;
        }

        $newData = $mergedData;

        // Only the manager's explicit submit ("Mark as Appraised") advances the
        // overall status — everything else (a draft save, or a VP/SLT submitting
        // their own remarks) must leave the current status untouched, so it
        // never regresses from appraised/completed back to submitted.
        $status = match (true) {
            $currentStatus === 'completed' => 'completed',
            $action === 'submit' && $appraiserLevel === 'manager' => 'appraised',
            default => $currentStatus,
        };

        $supabase->upsert('performance_reports', [
            'employee_id'    => $employeeId,
            'company_code'   => session('company_code'),
            'financial_year' => $this->currentFinancialYear,
            'quarter'        => $q,
            'form_data'      => $newData,
            'status'         => $status,
            'updated_at'     => now()->toISOString(),
        ], 'employee_id,financial_year,quarter');

        // Manager's submit is what unlocks the appraisee's own acknowledgment
        // signature — tell them their turn has come, in-app and via Telegram.
        if ($action === 'submit' && $appraiserLevel === 'manager' && $status === 'appraised') {
            $appraiserName = $newData['_manager_appraiser_name'] ?? 'Your appraiser';

            $notifications->notify(
                [$employeeId],
                'appraisal_appraised',
                ['id' => $viewerId, 'name' => $appraiserName],
                "Your {$q} appraisal is ready for your signature",
                "{$appraiserName} has completed your review. Please sign to acknowledge.",
                route('performance.report.quarter', strtolower($q)),
                $q,
                $this->currentFinancialYear
            );

            // VP/SLT only need to add their own Section 7 remarks — no point
            // alerting them before the manager has actually scored and signed,
            // so that notification is sent here rather than on initial submit.
            $upperChain = array_slice($notifications->appraiserChainFor($employeeId), 1);
            if (!empty($upperChain)) {
                $appraiseeName = $employees[0]['full_name'] ?? $employees[0]['short_name'] ?? 'An employee';

                $notifications->notify(
                    $upperChain,
                    'appraisal_appraised',
                    ['id' => $employeeId, 'name' => $appraiseeName],
                    "{$appraiseeName}'s {$q} appraisal is ready for your remarks",
                    "{$appraiserName} has completed scoring and signed. Please add your remarks.",
                    route('performance.appraise.report', [$employeeId, strtolower($q)]),
                    $q,
                    $this->currentFinancialYear
                );
            }
        }

        return response()->json(['success' => true, 'status' => $status, 'locked' => $action === 'submit']);
    }
}
