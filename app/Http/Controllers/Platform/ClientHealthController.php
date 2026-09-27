<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Performix HQ's client health scoring (spec §10/§13) — Center-only, one of
 * the five genuinely new schema pieces this redesign needed
 * (`company_health_scores`). A score isn't computed automatically from
 * usage data (there's no page-view telemetry in this codebase to compute
 * "activity" from) — a Super Admin/CAM records a period snapshot, same
 * spirit as `KpiSubmissionController` for KPI actuals.
 */
class ClientHealthController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function index(Request $request)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companies = $supabase->get('companies', ['select' => 'id,name,code,status', 'order' => 'name.asc', 'limit' => 200]);
        $companyIds = array_column($companies, 'id');

        $latestByCompany = [];
        if (!empty($companyIds)) {
            $scores = $supabase->get('company_health_scores', [
                'company_id' => 'in.(' . implode(',', $companyIds) . ')',
                'select' => '*',
                'order' => 'period_date.desc',
            ]);
            foreach ($scores as $score) {
                $latestByCompany[$score['company_id']] ??= $score;
            }
        }

        $rows = collect($companies)->map(fn ($c) => $c + ['health' => $latestByCompany[$c['id']] ?? null])->values();

        return Inertia::render('Platform/Hq/ClientHealth/Index', ['companies' => $rows]);
    }

    public function show(Request $request, string $company)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name,code']);

        $history = $supabase->get('company_health_scores', [
            'company_id' => 'eq.' . $company,
            'select' => '*',
            'order' => 'period_date.desc',
            'limit' => 12,
        ]);

        return Inertia::render('Platform/Hq/ClientHealth/Show', [
            'company' => $companyRow,
            'history' => array_reverse($history),
        ]);
    }

    public function store(Request $request, string $company)
    {
        $this->ensureSuperAdmin($request);

        $request->validate([
            'period_date' => 'required|date',
            'adoption_score' => 'required|numeric|min:0|max:100',
            'activity_score' => 'required|numeric|min:0|max:100',
            'kpi_completion_score' => 'required|numeric|min:0|max:100',
            'cam_engagement_score' => 'required|numeric|min:0|max:100',
            'support_score' => 'required|numeric|min:0|max:100',
            'subscription_score' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $overall = round(collect([
            $request->adoption_score, $request->activity_score, $request->kpi_completion_score,
            $request->cam_engagement_score, $request->support_score, $request->subscription_score,
        ])->avg(), 1);

        $status = $overall >= 80 ? 'healthy' : ($overall >= 60 ? 'at_risk' : 'critical');

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            $supabase->insert('company_health_scores', [
                'company_id' => $company,
                'period_date' => $request->period_date,
                'adoption_score' => $request->adoption_score,
                'activity_score' => $request->activity_score,
                'kpi_completion_score' => $request->kpi_completion_score,
                'cam_engagement_score' => $request->cam_engagement_score,
                'support_score' => $request->support_score,
                'subscription_score' => $request->subscription_score,
                'overall_score' => $overall,
                'status' => $status,
                'notes' => $request->notes,
                'created_by' => $request->attributes->get('platformUser')['id'] ?? null,
            ], false);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not record health score: ' . $e->getMessage());
        }

        $this->logAdminAction($request, 'record_client_health_score', $company, null, ['overall_score' => $overall, 'status' => $status], 'company_health_score');

        return back()->with('success', 'Health score recorded.');
    }
}
