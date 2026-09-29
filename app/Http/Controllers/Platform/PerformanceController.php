<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\ComputesFinancialYear;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\PlatformNotificationService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Ports legacy's Performance/Appraisal subsystem. See the migration's own
 * docblock (2026_09_29_050000_create_performance_reviews.php) for the full
 * list of what's kept exactly (the 4-stage workflow, the scoring formula and
 * banding thresholds, the KPI/Attendance auto-scoring) versus what's
 * deliberately scoped down (one appraiser via `manager_user_id` instead of
 * legacy's 3-tier Manager/VP/SLT delegation chain; no canvas e-signature).
 *
 * Assessment-area copy is legacy's own real wording (PerformanceController's
 * `$assessmentAreas`), mapped onto the Platform's different role vocabulary:
 * legacy's `EXECUTIVE` tier (entry-level) -> Platform `employee`; legacy's
 * `MANAGER`/`VP` tier -> Platform `executive`/`slt`/`company_admin`. The two
 * vocabularies don't line up 1:1 (legacy is a single EXECUTIVE->MANAGER->VP
 * ->SLT ladder; the Platform splits platform-tier and company-tier), so this
 * is a stated adaptation, not a literal transcription.
 */
class PerformanceController extends Controller
{
    use ComputesFinancialYear;
    use LogsAdminActions;
    use PlatformAuthorization;

    private const QUARTERS = ['Q1', 'Q2', 'Q3', 'Q4'];

    private const ATTENDANCE_CATEGORIES = ['lateness', 'absent', 'insufficient', 'leave', 'disciplinary'];

    private const ATTENDANCE_LABELS = [
        'lateness' => 'Lateness',
        'absent' => 'Absent',
        'insufficient' => 'Insufficient Working Hours',
        'leave' => 'EL / Unpaid Leave',
        'disciplinary' => 'Disciplinary Matters',
    ];

    private const CULTURE_VALUES = [
        'Integrity & Honesty',
        'Teamwork & Collaboration',
        'Customer Focus',
        'Innovation & Creativity',
        'Accountability & Ownership',
        'Continuous Learning',
    ];

    private const AREAS_JUNIOR = [
        ['no' => 1, 'title' => 'Knowledge of Job Requirements', 'description' => 'Knowledge of job requirements, methods, techniques and skills involved in doing the job, and applying these to perform efficiently.'],
        ['no' => 2, 'title' => 'Quality of Work Done', 'description' => 'Degree to which quality expectations of the job were fulfilled — accuracy, reliability, and excellence of end results.'],
        ['no' => 3, 'title' => 'Planning & Organising Skills', 'description' => 'Degree to which the appraisee anticipated needs, forecast conditions, set goals and standards, planned and scheduled work.'],
        ['no' => 4, 'title' => 'Decision Making', 'description' => 'Able to analyse problems effectively, make sound decisions, and commit to those decisions to achieve an acceptable result.'],
        ['no' => 5, 'title' => 'Communication Skills', 'description' => 'Communicated effectively — verbal and written — with superiors and peers.'],
        ['no' => 6, 'title' => 'Teamwork', 'description' => 'Able to adopt and adapt in work conditions/situations and work with others toward a common objective.'],
        ['no' => 7, 'title' => 'Interpersonal Relationships', 'description' => 'How well the appraisee related to associates, superiors, and external contacts to get the desired cooperation and assistance.'],
        ['no' => 8, 'title' => 'Attitude Towards Work', 'description' => 'Able to work independently without direct supervision; adapted well to new tasks/changes; showed commitment in discharge of duties.'],
        ['no' => 9, 'title' => 'Time Management / Tardiness', 'description' => 'Able to plan, execute and complete assigned tasks within deadline; conformed to company rules; punctual in attendance and timekeeping.'],
        ['no' => 10, 'title' => 'Appearance', 'description' => 'Well-groomed; made an excellent impression.'],
        ['no' => 11, 'title' => 'Dependability / Accountability', 'description' => 'Carries out work with limited/minimum supervision, follows instructions; shows initiative to complete tasks efficiently and effectively.'],
        ['no' => 12, 'title' => 'Values', 'description' => 'Understands and demonstrates organisation values at all times.'],
    ];

    private const AREAS_SENIOR = [
        ['no' => 1, 'title' => 'Quality of Work', 'description' => 'Consistently promotes quality awareness and continuous improvement without decreasing productivity or increasing cost.'],
        ['no' => 2, 'title' => 'Dependability / Accountability / Ownership', 'description' => 'Works with minimal supervision, follows instructions clearly, and shows initiative to complete tasks efficiently.'],
        ['no' => 3, 'title' => 'Problem-Solving & Decision Making', 'description' => 'Identifies and rectifies work problems independently; provides solutions and recommendations.'],
        ['no' => 4, 'title' => 'Time Management', 'description' => 'Plans, executes and completes assigned tasks within the required deadline.'],
        ['no' => 5, 'title' => 'Work Relationship / Service Orientation', 'description' => 'Builds cordial, positive relationships with colleagues and external parties; strong client rapport.'],
        ['no' => 6, 'title' => 'Performance Target', 'description' => 'Has achieved the expected KPIs set by the superior and/or Management.'],
        ['no' => 7, 'title' => 'Leadership', 'description' => 'Able to lead, develop, guide and motivate others toward a common objective.'],
        ['no' => 8, 'title' => 'Multi-Tasking Capabilities', 'description' => 'Willing to accept more tasks without complaint; works well under pressure.'],
        ['no' => 9, 'title' => 'Discipline (Attendance & Punctuality)', 'description' => 'Conforms to company rules at all times; punctual in attendance and timekeeping.'],
        ['no' => 10, 'title' => 'Appearance', 'description' => 'Well-groomed; makes an excellent impression.'],
        ['no' => 11, 'title' => 'Communication / Interpersonal Skills', 'description' => 'Communicates effectively — verbal and written — with superiors, peers and subordinates.'],
        ['no' => 12, 'title' => 'Values', 'description' => 'Understands and demonstrates organisation values at all times.'],
    ];

    private function normalizeQuarter(string $quarter): string
    {
        $q = strtoupper($quarter);
        abort_unless(in_array($q, self::QUARTERS, true), 404, 'Unknown quarter.');

        return $q;
    }

    private function assessmentAreasFor(?string $companyRole): array
    {
        return $companyRole === 'employee' ? self::AREAS_JUNIOR : self::AREAS_SENIOR;
    }

    public function index(Request $request, string $company, string $quarter)
    {
        $this->ensureCompanyMember($request, $company);
        $quarter = $this->normalizeQuarter($quarter);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name,code']);
        abort_if(!$companyRow, 404);

        $meId = $request->attributes->get('platformUser')['id'];
        $financialYear = $this->currentFinancialYear();
        $myRole = $this->companyRole($request, $company);

        $mine = $supabase->first('performance_reviews', [
            'company_id' => 'eq.' . $company,
            'user_id' => 'eq.' . $meId,
            'financial_year' => 'eq.' . $financialYear,
            'quarter' => 'eq.' . $quarter,
            'select' => '*',
        ]);

        $isAdmin = $this->canAdministerCompany($request, $company);

        // Who I need to appraise: company members whose manager_user_id is
        // me, PLUS any reports of a manager who has delegated their
        // appraisal duty to me (see appraiser_delegations /
        // 2026_09_29_090000) — a delegate picks up the delegating manager's
        // full report list, not a hand-picked subset, matching legacy's own
        // "delegate takes over the whole chain hop" behavior.
        $delegatedFromManagerIds = array_column($supabase->get('appraiser_delegations', [
            'company_id' => 'eq.' . $company,
            'delegate_user_id' => 'eq.' . $meId,
            'select' => 'manager_user_id',
        ]), 'manager_user_id');

        $managerIds = array_unique([$meId, ...$delegatedFromManagerIds]);

        $reports = $supabase->get('company_users', [
            'company_id' => 'eq.' . $company,
            'manager_user_id' => 'in.(' . implode(',', $managerIds) . ')',
            'status' => 'eq.active',
            'select' => 'user_id,manager_user_id,users!company_users_user_id_foreign(name,email)',
        ]);

        $reportIds = array_column($reports, 'user_id');
        $reportReviews = empty($reportIds) ? collect() : collect($supabase->get('performance_reviews', [
            'company_id' => 'eq.' . $company,
            'user_id' => 'in.(' . implode(',', $reportIds) . ')',
            'financial_year' => 'eq.' . $financialYear,
            'quarter' => 'eq.' . $quarter,
            'select' => '*',
        ]))->keyBy('user_id');

        // Only fetched for reports actually ready to be scored — a manager
        // with 20 reports who have nothing submitted yet shouldn't cost 20
        // extra KPI queries on every page load.
        $reviewQueue = collect($reports)->map(function ($r) use ($supabase, $company, $reportReviews) {
            $review = $reportReviews->get($r['user_id']);
            $kpis = ($review && $review['status'] === 'submitted')
                ? $supabase->get('kpis', [
                    'company_id' => 'eq.' . $company,
                    'assigned_user_id' => 'eq.' . $r['user_id'],
                    'select' => 'id,name,weight',
                ])
                : [];

            return [
                'user_id' => $r['user_id'],
                'name' => $r['users']['name'] ?? 'Unknown',
                'email' => $r['users']['email'] ?? '',
                'review' => $review,
                'kpis' => $kpis,
            ];
        })->values()->all();

        $adminQueue = [];
        $managers = [];
        $delegations = [];
        if ($isAdmin) {
            $adminQueue = $supabase->get('performance_reviews', [
                'company_id' => 'eq.' . $company,
                'financial_year' => 'eq.' . $financialYear,
                'quarter' => 'eq.' . $quarter,
                'select' => '*,users!performance_reviews_user_id_foreign(name,email)',
                'order' => 'updated_at.desc',
            ]);

            // Appraiser Delegation admin panel (2026_09_29_090000) — anyone
            // currently named as someone's manager is a candidate to
            // delegate away; their own manager_user_id (if any) is who
            // store() will compute as the delegate.
            $allMembers = $supabase->get('company_users', [
                'company_id' => 'eq.' . $company,
                'status' => 'eq.active',
                'select' => 'user_id,manager_user_id,users!company_users_user_id_foreign(name,email)',
            ]);
            $membersByUserId = collect($allMembers)->keyBy('user_id');
            $managerIdsInUse = collect($allMembers)->pluck('manager_user_id')->filter()->unique();

            $managers = $managerIdsInUse->map(function ($managerId) use ($membersByUserId) {
                $row = $membersByUserId->get($managerId);

                return [
                    'user_id' => $managerId,
                    'name' => $row['users']['name'] ?? 'Unknown',
                    'email' => $row['users']['email'] ?? '',
                    'delegate_candidate' => $row['manager_user_id']
                        ? ($membersByUserId->get($row['manager_user_id'])['users']['name'] ?? 'Unknown')
                        : null,
                ];
            })->values()->all();

            $delegations = $supabase->get('appraiser_delegations', [
                'company_id' => 'eq.' . $company,
                'select' => 'id,manager_user_id,delegate_user_id,reason,created_at',
            ]);
        }

        return Inertia::render('Platform/Performance/Index', [
            'company' => $companyRow,
            'quarter' => $quarter,
            'financialYear' => $financialYear,
            'isQ4' => $quarter === 'Q4',
            'assessmentAreas' => $this->assessmentAreasFor($myRole),
            'cultureValues' => self::CULTURE_VALUES,
            'attendanceLabels' => self::ATTENDANCE_LABELS,
            'mine' => $mine,
            'reviewQueue' => $reviewQueue,
            'adminQueue' => $isAdmin ? $adminQueue : null,
            'managers' => $isAdmin ? $managers : null,
            'delegations' => $isAdmin ? $delegations : null,
        ]);
    }

    /** @return array{total: float, items: array} */
    private function computeSelfKpiScore(SupabaseUserService $supabase, string $company, string $userId, string $financialYear, string $quarter): array
    {
        $kpis = $supabase->get('kpis', [
            'company_id' => 'eq.' . $company,
            'assigned_user_id' => 'eq.' . $userId,
            'select' => 'id,name,weight',
        ]);

        if (empty($kpis)) {
            return ['total' => 0.0, 'items' => []];
        }

        $kpiIds = array_column($kpis, 'id');
        $quarters = collect($supabase->get('kpi_quarters', [
            'kpi_id' => 'in.(' . implode(',', $kpiIds) . ')',
            'financial_year' => 'eq.' . $financialYear,
            'quarter' => 'eq.' . $quarter,
            'select' => 'kpi_id,target,actual',
        ]))->keyBy('kpi_id');

        $items = [];
        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($kpis as $kpi) {
            $q = $quarters->get($kpi['id']);
            $target = (float) ($q['target'] ?? 0);
            $actual = (float) ($q['actual'] ?? 0);
            $selfScore = $target > 0 ? min($actual / $target, 1) * 5 : 0.0;
            $weight = (float) ($kpi['weight'] ?? 0);

            $items[] = ['kpi_id' => $kpi['id'], 'name' => $kpi['name'], 'self_score' => round($selfScore, 2), 'weight' => $weight];
            $weightedSum += $selfScore * $weight;
            $totalWeight += $weight;
        }

        $total = $totalWeight > 0 ? ($weightedSum / $totalWeight) / 5 * 70 : 0.0;

        return ['total' => round($total, 2), 'items' => $items];
    }

    public function save(Request $request, string $company, string $quarter)
    {
        $this->ensureCompanyMember($request, $company);
        $quarter = $this->normalizeQuarter($quarter);

        $request->validate([
            'attitude_ratings' => 'required|array|size:12',
            'attitude_ratings.*' => 'required|integer|min:1|max:5',
            'culture_ratings' => $quarter === 'Q4' ? 'required|array|size:6' : 'nullable',
            'culture_ratings.*' => 'integer|min:1|max:5',
            'action' => 'required|in:save,submit',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $meId = $request->attributes->get('platformUser')['id'];
        $financialYear = $this->currentFinancialYear();

        $existing = $supabase->first('performance_reviews', [
            'company_id' => 'eq.' . $company,
            'user_id' => 'eq.' . $meId,
            'financial_year' => 'eq.' . $financialYear,
            'quarter' => 'eq.' . $quarter,
            'select' => 'id,status',
        ]);

        if ($existing && !in_array($existing['status'], ['draft'], true)) {
            return back()->with('error', 'This review is no longer editable at this stage.');
        }

        $attitudeSum = array_sum($request->input('attitude_ratings'));
        $selfScores = [
            'kpi' => $this->computeSelfKpiScore($supabase, $company, $meId, $financialYear, $quarter),
            'attitude' => [
                'ratings' => $request->input('attitude_ratings'),
                'total' => round($attitudeSum / 60 * 25, 2),
            ],
        ];

        if ($quarter === 'Q4') {
            $cultureSum = array_sum($request->input('culture_ratings'));
            $selfScores['culture'] = [
                'ratings' => $request->input('culture_ratings'),
                'total' => round($cultureSum / 30 * 5, 2),
            ];
        }

        $isSubmit = $request->input('action') === 'submit';
        $payload = ['self_scores' => $selfScores, 'status' => $isSubmit ? 'submitted' : 'draft'];
        if ($isSubmit) {
            $payload['submitted_at'] = now()->toIso8601String();
        }

        try {
            if ($existing) {
                $supabase->update('performance_reviews', ['id' => 'eq.' . $existing['id']], $payload, false);
            } else {
                $supabase->insert('performance_reviews', $payload + [
                    'company_id' => $company,
                    'user_id' => $meId,
                    'financial_year' => $financialYear,
                    'quarter' => $quarter,
                ], false);
            }
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not save your self-assessment: ' . $e->getMessage());
        }

        if ($isSubmit) {
            $manager = $supabase->first('company_users', [
                'company_id' => 'eq.' . $company,
                'user_id' => 'eq.' . $meId,
                'select' => 'manager_user_id',
            ]);

            if (!empty($manager['manager_user_id'])) {
                try {
                    app(PlatformNotificationService::class)->notify(
                        $supabase,
                        $company,
                        $manager['manager_user_id'],
                        'Performance review submitted',
                        "A {$quarter} self-assessment is ready for your review."
                    );
                } catch (\Throwable) {
                    // Best-effort — the submission itself already succeeded.
                }
            }
        }

        return back()->with('success', $isSubmit ? 'Self-assessment submitted for review.' : 'Draft saved.');
    }

    private function attendanceScoreFor(int $count): float
    {
        return match (true) {
            $count === 0 => 1.0,
            $count <= 5 => 0.7,
            $count <= 10 => 0.3,
            default => 0.0,
        };
    }

    public function appraise(Request $request, string $company, string $quarter, string $user)
    {
        $this->ensureCompanyMember($request, $company);
        $quarter = $this->normalizeQuarter($quarter);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $isAdmin = $this->canAdministerCompany($request, $company);

        if (!$isAdmin) {
            $meId = $request->attributes->get('platformUser')['id'];

            $membership = $supabase->first('company_users', [
                'company_id' => 'eq.' . $company,
                'user_id' => 'eq.' . $user,
                'status' => 'eq.active',
                'select' => 'manager_user_id',
            ]);

            $isDirectManager = $membership && $membership['manager_user_id'] === $meId;

            // Or I've been delegated this employee's actual manager's
            // appraisal duty (appraiser_delegations) — see index()'s own
            // reviewQueue for the matching "who do I need to appraise" build.
            $isDelegate = !$isDirectManager && $membership && $membership['manager_user_id']
                ? (bool) $supabase->first('appraiser_delegations', [
                    'company_id' => 'eq.' . $company,
                    'manager_user_id' => 'eq.' . $membership['manager_user_id'],
                    'delegate_user_id' => 'eq.' . $meId,
                    'select' => 'id',
                ])
                : false;

            abort_unless($isDirectManager || $isDelegate, 403, 'You are not this employee\'s manager.');
        }

        $request->validate([
            'kpi_scores' => 'nullable|array',
            'kpi_scores.*' => 'numeric|min:0|max:5',
            'attitude_ratings' => 'required|array|size:12',
            'attitude_ratings.*.rating' => 'required|integer|min:1|max:5',
            'attitude_ratings.*.comment' => 'nullable|string',
            'attendance_counts' => 'required|array',
            'culture_ratings' => $quarter === 'Q4' ? 'required|array|size:6' : 'nullable',
            'culture_ratings.*' => 'integer|min:1|max:5',
            'manager_remarks' => 'nullable|string',
            'strengths' => 'nullable|string',
            'ethics' => 'nullable|string',
            'improvement' => 'nullable|string',
            'training' => 'nullable|string',
            'recommendation' => 'nullable|array',
            'action' => 'required|in:save,submit',
        ]);

        $financialYear = $this->currentFinancialYear();

        $existing = $supabase->first('performance_reviews', [
            'company_id' => 'eq.' . $company,
            'user_id' => 'eq.' . $user,
            'financial_year' => 'eq.' . $financialYear,
            'quarter' => 'eq.' . $quarter,
            'select' => 'id,status',
        ]);

        if (!$existing || $existing['status'] !== 'submitted') {
            return back()->with('error', 'This employee has not submitted their self-assessment for this quarter yet.');
        }

        // KPI: only the raw per-KPI score is trusted from the client — the
        // weight used to combine them always comes from the KPI's own row.
        $kpis = $supabase->get('kpis', [
            'company_id' => 'eq.' . $company,
            'assigned_user_id' => 'eq.' . $user,
            'select' => 'id,name,weight',
        ]);

        $kpiScores = $request->input('kpi_scores', []);
        $kpiItems = [];
        $weightedSum = 0.0;
        $totalWeight = 0.0;
        foreach ($kpis as $kpi) {
            $score = (float) ($kpiScores[$kpi['id']] ?? 0);
            $weight = (float) ($kpi['weight'] ?? 0);
            $kpiItems[] = ['kpi_id' => $kpi['id'], 'name' => $kpi['name'], 'score' => $score, 'weight' => $weight];
            $weightedSum += $score * $weight;
            $totalWeight += $weight;
        }
        $kpiTotal = $totalWeight > 0 ? round(($weightedSum / $totalWeight) / 5 * 70, 2) : 0.0;

        $attitudeInput = $request->input('attitude_ratings');
        $attitudeSum = array_sum(array_column($attitudeInput, 'rating'));
        $attitudeTotal = round($attitudeSum / 60 * 25, 2);

        $attendanceCounts = $request->input('attendance_counts');
        $attendanceItems = [];
        $attendanceTotal = 0.0;
        foreach (self::ATTENDANCE_CATEGORIES as $cat) {
            $count = (int) ($attendanceCounts[$cat] ?? 0);
            $score = $this->attendanceScoreFor($count);
            $attendanceItems[] = ['category' => $cat, 'count' => $count, 'score' => $score];
            $attendanceTotal += $score;
        }
        $attendanceTotal = round($attendanceTotal, 2);

        $appraiserScores = [
            'kpi' => ['total' => $kpiTotal, 'items' => $kpiItems],
            'attitude' => ['total' => $attitudeTotal, 'items' => $attitudeInput],
            'attendance' => ['total' => $attendanceTotal, 'items' => $attendanceItems],
            'manager_remarks' => $request->input('manager_remarks'),
            'strengths' => $request->input('strengths'),
            'ethics' => $request->input('ethics'),
            'improvement' => $request->input('improvement'),
            'training' => $request->input('training'),
            'recommendation' => [
                'confirmation' => (bool) $request->input('recommendation.confirmation', false),
                'salary_review' => (bool) $request->input('recommendation.salary_review', false),
                'promotion' => (bool) $request->input('recommendation.promotion', false),
            ],
        ];

        $cultureTotal = 0.0;
        if ($quarter === 'Q4') {
            $cultureRatings = $request->input('culture_ratings');
            $cultureTotal = round(array_sum($cultureRatings) / 30 * 5, 2);
            $appraiserScores['culture'] = ['total' => $cultureTotal, 'ratings' => $cultureRatings];
        }

        $isSubmit = $request->input('action') === 'submit';
        $payload = ['appraiser_scores' => $appraiserScores, 'status' => $isSubmit ? 'appraised' : 'submitted'];

        if ($isSubmit) {
            $finalScore = round($kpiTotal + $attitudeTotal + $attendanceTotal + $cultureTotal, 2);
            $payload['final_score'] = $finalScore;
            $payload['band'] = match (true) {
                $finalScore >= 90 => 'Outstanding',
                $finalScore >= 70 => 'Meets Expectations',
                $finalScore >= 50 => 'Below Average',
                default => 'Unsatisfactory',
            };
            $payload['appraised_at'] = now()->toIso8601String();
        }

        try {
            $supabase->update('performance_reviews', ['id' => 'eq.' . $existing['id']], $payload, false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not save this appraisal: ' . $e->getMessage());
        }

        if ($isSubmit) {
            try {
                app(PlatformNotificationService::class)->notify($supabase, $company, $user, 'Performance review scored', "Your {$quarter} performance review has been appraised — sign off to complete it.");
            } catch (\Throwable) {
                // Best-effort.
            }

            try {
                $this->logCompanyAction($request, 'appraise_performance_review', $company, $user, [
                    'quarter' => $quarter, 'financial_year' => $financialYear, 'final_score' => $payload['final_score'],
                ], 'performance_review', $existing['id']);
            } catch (\Throwable) {
                return back()->with('error', 'Appraisal was saved, but the action could not be logged — contact support before continuing.');
            }
        }

        return back()->with('success', $isSubmit ? 'Appraisal submitted.' : 'Draft saved.');
    }

    public function acknowledge(Request $request, string $company, string $quarter)
    {
        $this->ensureCompanyMember($request, $company);
        $quarter = $this->normalizeQuarter($quarter);

        $request->validate(['acknowledgment' => 'required|string|min:5']);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $meId = $request->attributes->get('platformUser')['id'];
        $financialYear = $this->currentFinancialYear();

        $existing = $supabase->first('performance_reviews', [
            'company_id' => 'eq.' . $company,
            'user_id' => 'eq.' . $meId,
            'financial_year' => 'eq.' . $financialYear,
            'quarter' => 'eq.' . $quarter,
            'select' => 'id,status',
        ]);

        if (!$existing || $existing['status'] !== 'appraised') {
            return back()->with('error', 'This review is not ready to be signed off yet.');
        }

        try {
            $supabase->update('performance_reviews', ['id' => 'eq.' . $existing['id']], [
                'appraisee_acknowledgment' => $request->acknowledgment,
                'status' => 'completed',
                'completed_at' => now()->toIso8601String(),
            ], false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not sign off: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'complete_performance_review', $company, $meId, [
                'quarter' => $quarter, 'financial_year' => $financialYear,
            ], 'performance_review', $existing['id']);
        } catch (\Throwable) {
            return back()->with('error', 'Signed off, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Signed off. Your performance review is complete.');
    }
}
