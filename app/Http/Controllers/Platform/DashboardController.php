<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Two genuinely different pages behind one URL, split by platform tier —
     * requirement #9: "Since Richworks is effectively the platform operator,
     * Performix should have a separate platform view. A company user should
     * never even know another company exists." A Richworks Super Admin gets
     * `platformOverview()` — the Center-wide operator dashboard (total/active/
     * suspended companies, total users, onboarding progress, system health,
     * recent admin activity, security alerts). Everyone else — a Company
     * Admin, SLT, Executive, Employee, or a Platform Admin scoped to their
     * assigned companies — gets `companyLanding()`, which only ever shows
     * what RLS already scoped `companies` to for that specific caller. These
     * were previously one shared component with an `is_super_admin`-gated
     * block bolted on; a company user landing here saw operator-flavored
     * copy ("Companies visible to you") even though the isolation itself was
     * never actually broken (RLS, not this controller, already narrowed the
     * result to their own company). Splitting them into two components makes
     * that separation structural, not just a conditional render.
     */
    public function index(Request $request)
    {
        $platformUser = $request->attributes->get('platformUser');

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        if ($platformUser['is_super_admin'] ?? false) {
            return $this->platformOverview($request, $platformUser, $supabase);
        }

        return $this->companyLanding($platformUser, $supabase);
    }

    /**
     * Deliberately queries `companies` with no filter at all — RLS is what
     * decides whether one row comes back or all of them. A Company Admin
     * (or a Platform Admin, scoped to their assigned companies) seeing only
     * their own here — not because this controller filtered it, but because
     * Postgres did — is the actual proof that isolation works.
     */
    private function companyLanding(array $platformUser, SupabaseUserService $supabase)
    {
        $companies = $supabase->get('companies', ['select' => '*']);

        // `company_kpi_summary` is a Postgres view with `security_invoker`,
        // so this returns exactly the same set of companies RLS already
        // scoped `companies` to above — the aggregation happens in Postgres,
        // never by summing kpi_submissions rows here.
        try {
            $summaries = $supabase->get('company_kpi_summary', ['select' => '*']);
        } catch (\Throwable) {
            $summaries = [];
        }
        $summariesByCompany = collect($summaries)->keyBy('company_id');

        $companiesWithStats = collect($companies)->map(function ($company) use ($summariesByCompany) {
            $summary = $summariesByCompany->get($company['id']);

            return $company + [
                'department_count' => $summary['department_count'] ?? 0,
                'user_count' => $summary['user_count'] ?? 0,
                'kpi_count' => $summary['kpi_count'] ?? 0,
                'submission_count' => $summary['submission_count'] ?? 0,
                'avg_achievement_pct' => $summary['avg_achievement_pct'] ?? null,
            ];
        })->values();

        return Inertia::render('Platform/Dashboard', [
            'me' => $platformUser,
            'visibleCompanies' => $companiesWithStats,
        ]);
    }

    /**
     * The Center-wide operator view. Every widget here is real, derived data
     * — nothing fabricated to fill a tile:
     *   - Total/Active/Suspended Companies, Total Users — one query each
     *     (`companies`, `users`), same 200-row defensive cap already used by
     *     `CompanyController::index()` (spec requirement #37).
     *   - Onboarding Progress — a breakdown by `companies.status`, the real
     *     lifecycle column (`draft → onboarding → configuring → active →
     *     suspended → archived`, see CompanyLifecycleService), not a
     *     separately tracked progress percentage that could drift from it.
     *   - System Health — deliberately narrow: DB reachability (this
     *     method's own queries either succeed or this degrades gracefully
     *     below) and a link to Horizon's own dashboard for queue/job health,
     *     which already exists and would be a redundant custom page to
     *     rebuild (see the Phase 11 audit log note). No fabricated CPU/memory
     *     metrics this app has no way to actually observe.
     *   - Recent Admin Activity / Security Alerts — both read straight from
     *     `admin_action_logs` (the comprehensive audit system, requirement
     *     #8) rather than a second, separate tracking mechanism. "Security
     *     Alerts" specifically surfaces `login_failed`/`access_denied`/
     *     `telegram_link_failed` — the three action types that already exist
     *     for exactly this purpose — rather than inventing a new anomaly-
     *     detection engine.
     */
    private function platformOverview(Request $request, array $platformUser, SupabaseUserService $supabase)
    {
        $health = ['database' => 'reachable'];

        try {
            $companies = $supabase->get('companies', [
                'select' => 'id,name,code,status,onboarding_status,created_at',
                'order' => 'created_at.desc',
                'limit' => 200,
            ]);
        } catch (\Throwable) {
            $companies = [];
            $health['database'] = 'unreachable';
        }

        $statusCounts = collect($companies)->countBy('status');

        $totalUsers = 0;
        if ($health['database'] === 'reachable') {
            try {
                $totalUsers = count($supabase->get('users', ['select' => 'id', 'limit' => 5000]));
            } catch (\Throwable) {
                $health['database'] = 'degraded';
            }
        }

        $securityActionTypes = ['login_failed', 'access_denied', 'telegram_link_failed'];
        $recentActivity = [];
        $securityEvents = [];
        $securityCounts24h = ['login_failed' => 0, 'access_denied' => 0];

        try {
            $recentActivity = $supabase->get('admin_action_logs', [
                'select' => 'id,action,actor_email,target_company_id,target_type,occurred_at',
                'order' => 'occurred_at.desc',
                'limit' => 10,
            ]);

            $securityEvents = $supabase->get('admin_action_logs', [
                'action' => 'in.(' . implode(',', $securityActionTypes) . ')',
                'select' => 'id,action,actor_email,metadata,occurred_at',
                'order' => 'occurred_at.desc',
                'limit' => 10,
            ]);

            $since24h = now()->subDay()->toIso8601String();

            $securityCounts24h['login_failed'] = count($supabase->get('admin_action_logs', [
                'action' => 'eq.login_failed', 'occurred_at' => 'gte.' . $since24h, 'select' => 'id',
            ]));
            $securityCounts24h['access_denied'] = count($supabase->get('admin_action_logs', [
                'action' => 'eq.access_denied', 'occurred_at' => 'gte.' . $since24h, 'select' => 'id',
            ]));
        } catch (\Throwable) {
            // The audit log itself being unreachable shouldn't take the
            // whole operator dashboard down with it — the stats above are
            // still worth showing even if the activity feed can't load.
        }

        $companiesById = collect($companies)->keyBy('id');

        $recentActivity = collect($recentActivity)->map(fn ($log) => [
            ...$log,
            'target_company_name' => $log['target_company_id']
                ? ($companiesById->get($log['target_company_id'])['name'] ?? null)
                : null,
        ])->values()->all();

        return Inertia::render('Platform/PlatformOverview', [
            'me' => $platformUser,
            'stats' => [
                'total_companies' => count($companies),
                'active_companies' => $statusCounts->get('active', 0),
                'suspended_companies' => $statusCounts->get('suspended', 0),
                'total_users' => $totalUsers,
            ],
            'onboardingProgress' => [
                'draft' => $statusCounts->get('draft', 0),
                'onboarding' => $statusCounts->get('onboarding', 0),
                'configuring' => $statusCounts->get('configuring', 0),
                'active' => $statusCounts->get('active', 0),
                'suspended' => $statusCounts->get('suspended', 0),
                'archived' => $statusCounts->get('archived', 0),
            ],
            'systemHealth' => [
                'database' => $health['database'],
                'horizonUrl' => '/horizon',
            ],
            'recentActivity' => $recentActivity,
            'securityAlerts' => [
                'loginFailed24h' => $securityCounts24h['login_failed'],
                'accessDenied24h' => $securityCounts24h['access_denied'],
                'recent' => $securityEvents,
            ],
            'companies' => $companies,
        ]);
    }
}