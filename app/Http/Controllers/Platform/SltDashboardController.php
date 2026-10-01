<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\ComputesFinancialYear;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * A real clone of legacy's SLT Dashboard -- `resources/js/Pages/SltDashboard.tsx`,
 * the page legacy's own `SltDashboardController::index()` actually renders via
 * `Inertia::render('SltDashboard', ...)`. `resources/views/slt-dashboard.blade.php`
 * is a stale snapshot nothing renders through, the exact same trap
 * `Platform/Notifications/Index.tsx`'s own docblock already documents for
 * `notifications.blade.php` -- verified the same way here: by reading the
 * controller's actual return statement, not by assuming a `.blade.php` file
 * sitting in the repo is live.
 *
 * An earlier pass of this page was deliberately NOT a port -- built on KPI
 * achievement instead, because at the time the Platform had no equivalent of
 * legacy's `performance_reports` appraisal workflow at all. That's no longer
 * true: `performance_reviews` (2026_09_29_050000) is a real port of that
 * workflow with an IDENTICAL state machine (draft/submitted/appraised/
 * completed) and IDENTICAL banding thresholds (>=90 Outstanding / >=70 Meets
 * Expectations / >=50 Below Average / else Unsatisfactory). This rewrite
 * reads that table directly -- simpler than legacy in one respect: legacy
 * has to re-derive a score from a free-form `form_data` blob
 * (`scoreFromFormData()`); here `final_score`/`band` are already real,
 * stored columns, computed once by `PerformanceController::appraise()`.
 *
 * Role grouping adapts legacy's SLT/VP/MANAGER/EXECUTIVE ladder onto the
 * Platform's actual company-tier vocabulary (company_admin/slt/executive/
 * employee) -- the same adaptation `PerformanceController`'s own docblock
 * already states for assessment-area copy.
 */
class SltDashboardController extends Controller
{
    use ComputesFinancialYear;
    use PlatformAuthorization;

    private const QUARTERS = ['Q1', 'Q2', 'Q3', 'Q4'];

    private const ROLE_PRIORITY = ['company_admin' => 1, 'slt' => 2, 'executive' => 3, 'employee' => 4];

    private function defaultQuarter(): string
    {
        return match (true) {
            now()->month <= 3 => 'Q1',
            now()->month <= 6 => 'Q2',
            now()->month <= 9 => 'Q3',
            default => 'Q4',
        };
    }

    /**
     * Same thresholds as `performance_reviews.band` -- needed here too for
     * the one summary number that's never itself a stored row: the
     * company-wide average score.
     */
    private function bandFor(float $score): array
    {
        return match (true) {
            $score >= 90 => ['key' => 'outstanding', 'label' => 'Outstanding', 'bg' => '#00B050', 'text' => '#FFFFFF'],
            $score >= 70 => ['key' => 'meets_expectations', 'label' => 'Meets Expectations', 'bg' => '#FFD700', 'text' => '#000000'],
            $score >= 50 => ['key' => 'below_average', 'label' => 'Below Average', 'bg' => '#FF8C00', 'text' => '#000000'],
            default => ['key' => 'unsatisfactory', 'label' => 'Unsatisfactory', 'bg' => '#ED1C24', 'text' => '#FFFFFF'],
        };
    }

    private const BAND_KEY_BY_LABEL = [
        'Unsatisfactory' => 'unsatisfactory',
        'Below Average' => 'below_average',
        'Meets Expectations' => 'meets_expectations',
        'Outstanding' => 'outstanding',
    ];

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyWideViewer($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name,code']);

        $quarter = strtoupper((string) $request->query('quarter', $this->defaultQuarter()));
        if (!in_array($quarter, self::QUARTERS, true)) {
            $quarter = $this->defaultQuarter();
        }
        $financialYear = $this->currentFinancialYear();
        $deptFilter = (string) $request->query('department', 'ALL');

        $departmentRows = $supabase->get('departments', [
            'company_id' => 'eq.' . $company,
            'select' => 'id,name,code',
            'order' => 'name.asc',
        ]);

        $members = $supabase->get('company_users', [
            'company_id' => 'eq.' . $company,
            'status' => 'eq.active',
            'select' => 'user_id,role,manager_user_id,users!company_users_user_id_foreign(name,email)',
        ]);

        // Manager-name lookup needs the full company roster, not just the
        // filtered department, so a manager outside the filter still
        // resolves -- same reasoning as legacy's own $nameById.
        $memberById = collect($members)->keyBy('user_id');

        $departmentMemberships = $supabase->get('department_users', [
            'company_id' => 'eq.' . $company,
            'select' => 'user_id,department_id,departments(name)',
        ]);
        $departmentByUser = collect($departmentMemberships)->keyBy('user_id');

        if ($deptFilter !== 'ALL') {
            $allowedUserIds = collect($departmentMemberships)->where('department_id', $deptFilter)->pluck('user_id');
            $members = collect($members)->whereIn('user_id', $allowedUserIds)->values()->all();
        }

        $userIds = array_column($members, 'user_id');

        $reviews = empty($userIds) ? [] : $supabase->get('performance_reviews', [
            'company_id' => 'eq.' . $company,
            'user_id' => 'in.(' . implode(',', $userIds) . ')',
            'financial_year' => 'eq.' . $financialYear,
            'quarter' => 'eq.' . $quarter,
            'select' => 'user_id,status,final_score,band',
        ]);
        $reviewByUser = collect($reviews)->keyBy('user_id');

        $bandCounts = array_fill_keys(array_values(self::BAND_KEY_BY_LABEL), 0);

        $staffRows = [];
        $notSubmittedCount = 0;
        $pendingCount = 0;
        $awaitingSignoffCount = 0;
        $completedCount = 0;
        $submittedOrFurtherCount = 0;
        $scoreSum = 0.0;
        $scoreCount = 0;

        foreach ($members as $member) {
            $review = $reviewByUser->get($member['user_id']);
            $status = $review['status'] ?? 'draft';
            $manager = $member['manager_user_id'] ? $memberById->get($member['manager_user_id']) : null;
            $dept = $departmentByUser->get($member['user_id']);

            $row = [
                'user_id' => $member['user_id'],
                'name' => $member['users']['name'] ?? 'Unknown',
                'department' => $dept['departments']['name'] ?? '—',
                'manager' => $manager ? ($manager['users']['name'] ?? '—') : '—',
                'role' => $member['role'],
                'role_priority' => self::ROLE_PRIORITY[$member['role']] ?? 99,
                'score' => null,
            ];

            if (!in_array($status, ['submitted', 'appraised', 'completed'], true)) {
                $row['status_key'] = 'not_submitted';
                $notSubmittedCount++;
                $staffRows[] = $row;
                continue;
            }

            $submittedOrFurtherCount++;

            // "appraised" means the manager is done but the appraisee hasn't
            // signed off yet -- same as legacy, must not show a score or
            // count towards completion until that happens.
            if ($status === 'appraised') {
                $row['status_key'] = 'awaiting_signoff';
                $awaitingSignoffCount++;
                $staffRows[] = $row;
                continue;
            }

            if ($status !== 'completed' || $review['final_score'] === null) {
                $row['status_key'] = 'pending';
                $pendingCount++;
                $staffRows[] = $row;
                continue;
            }

            $completedCount++;
            $score = (float) $review['final_score'];
            $scoreSum += $score;
            $scoreCount++;

            $bandKey = self::BAND_KEY_BY_LABEL[$review['band']] ?? $this->bandFor($score)['key'];
            $row['score'] = $score;
            $row['status_key'] = $bandKey;
            $bandCounts[$bandKey]++;
            $staffRows[] = $row;
        }

        usort($staffRows, fn ($a, $b) => $a['role_priority'] <=> $b['role_priority'] ?: strcasecmp($a['name'], $b['name']));

        $totalStaff = count($members);
        $participationRate = $totalStaff > 0 ? (int) round($submittedOrFurtherCount / $totalStaff * 100) : 0;
        $averageScore = $scoreCount > 0 ? round($scoreSum / $scoreCount, 1) : 0.0;

        return Inertia::render('Platform/SltDashboard', [
            'company' => $companyRow,
            'financialYear' => $financialYear,
            'quarter' => $quarter,
            'departments' => $departmentRows,
            'deptFilter' => $deptFilter,
            'totalStaff' => $totalStaff,
            'participationRate' => $participationRate,
            'completedCount' => $completedCount,
            'notSubmittedCount' => $notSubmittedCount,
            'pendingCount' => $pendingCount,
            'awaitingSignoffCount' => $awaitingSignoffCount,
            'averageScore' => $averageScore,
            'averageBand' => $this->bandFor($averageScore),
            'bandCounts' => $bandCounts,
            'staffRows' => array_values($staffRows),
        ]);
    }
}
