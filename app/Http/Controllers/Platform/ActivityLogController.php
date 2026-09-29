<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * "User Activity Log" — legacy's version (App\Http\Controllers\ActivityLogController)
 * is a self-scoped timeline: "what did I do", built by hand from `kpis`/
 * `kpi_histories` on the legacy schema. The Platform already has a real,
 * comprehensive event log for this — `admin_action_logs` (every KPI/target/
 * role/weight-request/quarterly action already writes a row via
 * AuditLogService) — but its RLS (`admin_action_logs_select_company`) only
 * lets a `company_admin` read it; a plain employee/executive/slt member has
 * no read path onto their own actions at all today.
 *
 * Rather than widen RLS (a schema/security change with its own review, out
 * of scope for what was asked here), this reads through the plain
 * service-role `SupabaseService` — safe specifically because
 * `admin_action_logs` is already one of the Core Platform Rule's own
 * documented tenant-ownership exemptions (Center-level infrastructure, not
 * tenant data — see `SupabaseService::TENANT_OWNED_TABLES`), and every
 * filter here (`actor_user_id`, `target_company_id`) is hardcoded from the
 * authenticated caller's own session, never taken from the request — the
 * same shape as the codebase's three other narrow, documented service-role
 * exceptions.
 */
class ActivityLogController extends Controller
{
    use PlatformAuthorization;

    private const SELECT = 'id,action,target_type,target_id,before,after,occurred_at';

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        $platformUser = $request->attributes->get('platformUser');
        $meId = $platformUser['id'];

        $supabase = app(SupabaseService::class);

        $companyRow = $supabase->get('companies', ['id' => 'eq.' . $company, 'select' => 'id,name,code'])[0] ?? null;

        $logs = $supabase->get('admin_action_logs', [
            'actor_user_id' => 'eq.' . $meId,
            'target_company_id' => 'eq.' . $company,
            'select' => self::SELECT,
            'order' => 'occurred_at.desc',
            'limit' => 200,
        ]) ?? [];

        return Inertia::render('Platform/ActivityLog/Index', [
            'company' => $companyRow,
            'logs' => $logs,
        ]);
    }
}
