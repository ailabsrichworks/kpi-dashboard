<?php

namespace App\Http\Controllers;

use App\Services\AppraiserDelegationService;
use App\Services\QuarterOverrideService;
use App\Services\SupabaseService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * BTS-only "Quarter Control": lets BTS force a specific quarter open for
 * both KPI actual submission and the appraisal self-review/appraiser flow,
 * until a chosen deadline -- see QuarterOverrideService for what that
 * actually changes, and where it's read (KpiQuarterUpdateService,
 * KpiController::updateQuarterActual/enrichKpiRecord,
 * PerformanceController's window checks).
 *
 * Gated the same way every other BTS-only feature in this app is --
 * Controller::isBtsSession(), checked per-method (there's no BTS
 * middleware in this codebase) -- not by route middleware.
 */
class QuarterOverrideController extends Controller
{
    private const QUARTERS = ['Q1', 'Q2', 'Q3', 'Q4'];

    public function __construct(
        private QuarterOverrideService $overrides,
        private AppraiserDelegationService $delegations,
    ) {
    }

    private function ensureBts(): void
    {
        abort_unless($this->isQuarterControlAuthorized(), 403, 'Not authorized for Quarter Control.');
    }

    private function currentFY(): string
    {
        return 'FY' . now()->year;
    }

    public function index(Request $request, SupabaseService $supabase)
    {
        $this->ensureBts();

        $financialYear = $request->query('financial_year', $this->currentFY());
        $rows = collect($this->overrides->allForYear($financialYear))->keyBy('quarter');

        $quarters = collect(self::QUARTERS)->map(function (string $quarter) use ($rows) {
            $row = $rows->get($quarter);
            $openUntil = $row['open_until'] ?? null;
            $isActive = $openUntil && Carbon::parse($openUntil)->isFuture();

            return [
                'quarter' => $quarter,
                'is_active' => $isActive,
                'open_until' => $openUntil,
                'open_until_display' => $openUntil ? Carbon::parse($openUntil)->timezone('Asia/Kuala_Lumpur')->format('d M Y, g:i A') : null,
                'created_by_name' => $row['created_by_name'] ?? null,
            ];
        })->all();

        return view('admin.quarter-control', array_merge([
            'financialYear' => $financialYear,
            'quarters' => $quarters,
        ], $this->appraiserDelegationData($supabase)));
    }

    /**
     * Every active Manager (candidate to be absent) alongside their own
     * resolved VP (the only person this feature will ever substitute in —
     * see AppraiserDelegationController::store()) and whether a delegation
     * is already active for them, plus the reverse lookup so an active
     * delegation row can show real names instead of raw ids.
     */
    private function appraiserDelegationData(SupabaseService $supabase): array
    {
        $managers = $supabase->get('employees', [
            'role'      => 'eq.MANAGER',
            'is_active' => 'eq.true',
            'select'    => 'id,short_name,department_code,vp_id,reports_to_id',
            'order'     => 'short_name.asc',
        ]) ?? [];

        $activeRows = collect($this->delegations->all())->keyBy('manager_id');

        $nameIds = collect($managers)->flatMap(fn ($m) => [$m['vp_id'] ?? null, $m['reports_to_id'] ?? null])
            ->merge($activeRows->pluck('delegate_to_id'))
            ->filter()
            ->unique()
            ->values();

        $names = $nameIds->isEmpty() ? collect() : collect($supabase->get('employees', [
            'id'     => 'in.(' . $nameIds->implode(',') . ')',
            'select' => 'id,short_name',
        ]) ?? [])->keyBy('id');

        $managerRows = collect($managers)->map(function (array $m) use ($activeRows, $names) {
            $candidateVpId = $m['vp_id'] ?? $m['reports_to_id'] ?? null;
            $active = $activeRows->get($m['id']);

            return [
                'id' => $m['id'],
                'short_name' => $m['short_name'],
                'department_code' => $m['department_code'] ?? null,
                'candidate_vp_id' => $candidateVpId,
                'candidate_vp_name' => $candidateVpId ? ($names->get($candidateVpId)['short_name'] ?? null) : null,
                'is_delegated' => (bool) $active,
                'delegate_name' => $active ? ($names->get($active['delegate_to_id'])['short_name'] ?? null) : null,
                'reason' => $active['reason'] ?? null,
            ];
        })->all();

        return ['appraiserManagers' => $managerRows];
    }

    public function store(Request $request)
    {
        $this->ensureBts();

        $validated = $request->validate([
            'financial_year' => 'nullable|string|max:20',
            'quarter' => 'required|in:Q1,Q2,Q3,Q4',
            'open_until' => 'required|date|after:now',
        ]);

        $financialYear = $validated['financial_year'] ?: $this->currentFY();
        $openUntil = Carbon::parse($validated['open_until'], 'Asia/Kuala_Lumpur');

        $this->overrides->setOverride(
            $financialYear,
            $validated['quarter'],
            $openUntil,
            session('employee_uuid'),
            session('employee.short_name') ?? session('short_name')
        );

        return back()->with('success', "{$validated['quarter']} ({$financialYear}) is now open until {$openUntil->format('d M Y, g:i A')}.");
    }

    public function destroy(Request $request, string $quarter)
    {
        $this->ensureBts();

        $quarter = strtoupper($quarter);
        abort_unless(in_array($quarter, self::QUARTERS, true), 404);

        $financialYear = $request->input('financial_year', $this->currentFY());
        $this->overrides->clearOverride($financialYear, $quarter);

        return back()->with('success', "{$quarter} ({$financialYear}) override closed.");
    }
}
