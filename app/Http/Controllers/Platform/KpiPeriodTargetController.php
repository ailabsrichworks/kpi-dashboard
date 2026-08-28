<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;

/**
 * Quarterly/monthly KPI target splitting (spec §9/§10). Deliberately a
 * "replace the whole set for this financial year + period type" endpoint
 * rather than one row at a time — reconciliation against the KPI's annual
 * target only makes sense checked against the complete set, and a partial
 * update would leave stale periods from a previous split lying around.
 *
 * Reconciliation is enforced here (§9: "do not silently correct invalid
 * allocations"): if the KPI has an annual `target` set, the submitted
 * periods must sum to it (small floating-point tolerance aside) or the
 * whole save is rejected with the allocated/remaining amounts shown, the
 * same way KpiController/CompanyGoalController reject over-100% weightage
 * rather than normalising it.
 */
class KpiPeriodTargetController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    private const RECONCILIATION_TOLERANCE = 0.01;

    public function store(Request $request, string $company, string $kpi)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'financial_year' => 'required|integer|min:2000|max:2200',
            'period_type' => 'required|in:quarter,month',
            'targets' => 'required|array|min:1',
            'targets.*' => 'required|numeric|min:0',
        ]);

        $expectedCount = $request->period_type === 'quarter' ? 4 : 12;

        if (count($request->targets) !== $expectedCount) {
            return back()->with('error', "Provide all {$expectedCount} periods — leave any you don't want to set at 0 rather than omitting them.");
        }

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $kpiRow = $supabase->first('kpis', [
            'id' => 'eq.' . $kpi,
            'company_id' => 'eq.' . $company,
            'select' => 'id,name,target',
        ]);

        if (!$kpiRow) {
            abort(404, 'That KPI does not belong to this company.');
        }

        $allocated = array_sum($request->targets);
        $annualTarget = $kpiRow['target'] !== null ? (float) $kpiRow['target'] : null;

        if ($annualTarget !== null && abs($allocated - $annualTarget) > self::RECONCILIATION_TOLERANCE) {
            $remaining = $annualTarget - $allocated;
            $direction = $remaining > 0 ? 'short of' : 'over';

            return back()->withInput()->with('error', sprintf(
                'These periods total %s, which is %s the annual target of %s by %s. Adjust the split so it reconciles, or update the annual target first.',
                rtrim(rtrim(number_format($allocated, 2), '0'), '.'),
                $direction,
                rtrim(rtrim(number_format($annualTarget, 2), '0'), '.'),
                rtrim(rtrim(number_format(abs($remaining), 2), '0'), '.'),
            ));
        }

        $rows = [];
        foreach ($request->targets as $periodNumber => $value) {
            $rows[] = [
                'kpi_id' => $kpi,
                'financial_year' => $request->financial_year,
                'period_type' => $request->period_type,
                'period_number' => (int) $periodNumber,
                'target' => $value,
            ];
        }

        try {
            // Replace-the-whole-set semantics: delete this KPI's existing
            // rows for this FY + period type, then insert the new split, so
            // a shorter/changed split never leaves stale periods behind.
            $supabase->delete('kpi_period_targets', [
                'kpi_id' => 'eq.' . $kpi,
                'financial_year' => 'eq.' . $request->financial_year,
                'period_type' => 'eq.' . $request->period_type,
            ]);

            $supabase->insert('kpi_period_targets', $rows, false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not save the period targets: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'set_kpi_period_targets', $company, null, [
                'financial_year' => $request->financial_year,
                'period_type' => $request->period_type,
            ], 'kpi', $kpi, null, ['targets' => $request->targets]);
        } catch (\Throwable) {
            return back()->with('error', 'Period targets were saved, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'FY' . $request->financial_year . ' ' . $request->period_type . 'ly targets for "' . $kpiRow['name'] . '" saved.');
    }
}
