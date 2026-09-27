<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\PeriodLifecycleService;
use App\Services\PerformancePeriodService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Performix Company Platform, Phase 1 hardening (Part 3): performance period
 * lifecycle — viewing the current financial year's quarters and their
 * status, and (Company Admin only) transitioning one. Every transition is
 * audited unconditionally, including reopen — spec: "Any reopen action must
 * be audited."
 */
class PeriodController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', [
            'id' => 'eq.' . $company,
            'select' => 'id,name,financial_year_start_month',
        ]);

        $periods = new PerformancePeriodService((int) ($companyRow['financial_year_start_month'] ?? 1));
        $now = Carbon::now();
        $currentFy = $periods->financialYearFor($now);

        $lifecycle = new PeriodLifecycleService($supabase);

        $quarters = [];
        for ($q = 1; $q <= 4; $q++) {
            [$start, $end] = $periods->quarterBounds($currentFy, $q);
            $state = $lifecycle->effectiveStatus($company, $currentFy, 'quarter', $q, $start, $end, $now);

            $quarters[] = [
                'period_type' => 'quarter',
                'period_number' => $q,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'status' => $state['status'],
                'reason' => $state['reason'],
                'set_at' => $state['set_at'],
            ];
        }

        return Inertia::render('Platform/Periods/Index', [
            'company' => $companyRow,
            'financialYear' => $currentFy,
            'quarters' => $quarters,
            'canManage' => $this->canAdministerCompany($request, $company),
        ]);
    }

    public function update(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'financial_year' => 'required|integer|min:2000|max:2200',
            'period_type' => 'required|in:quarter,month',
            'period_number' => 'required|integer|min:1|max:12',
            'status' => 'required|in:upcoming,open,submission_due,under_review,closed,locked',
            'reason' => 'nullable|string',
        ]);

        if (in_array($request->status, ['closed', 'locked', 'under_review'], true) && !$request->filled('reason')) {
            return back()->with('error', 'Please provide a reason for this period status change.');
        }

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $myUserId = $request->attributes->get('platformUser')['id'];

        $lifecycle = new PeriodLifecycleService($supabase);

        $before = $lifecycle->findOverride($company, (int) $request->financial_year, $request->period_type, (int) $request->period_number);

        try {
            $lifecycle->setStatus(
                $company,
                (int) $request->financial_year,
                $request->period_type,
                (int) $request->period_number,
                $request->status,
                $myUserId,
                $request->reason,
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not update period: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'set_period_status', $company, null, [
                'financial_year' => $request->financial_year,
                'period_type' => $request->period_type,
                'period_number' => $request->period_number,
            ], 'company_performance_period', null, $before, [
                'status' => $request->status,
                'reason' => $request->reason,
            ]);
        } catch (\Throwable) {
            return back()->with('error', 'Period was updated, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Period status set to "' . $request->status . '".');
    }
}
