<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Ports resources/views/kpi/weightage.blade.php (the legacy "Manage
 * Weightage" page) to the Platform — see the migration docblock
 * (2026_09_24_080000_add_kpi_weightage_self_service) for the full schema
 * rationale. This controller is deliberately separate from KpiController:
 * that one is Company-Admin-only KPI configuration, this one is a regular
 * member's own self-service page, with a genuinely different authorization
 * shape (RLS gates writes by `assigned_user_id`, not company-admin status).
 *
 * Two write paths, matching the legacy page's own rule (previously only
 * enforced in client-side JS, now for real at the RLS/trigger layer too —
 * see restrict_kpi_owner_weight_update()):
 *   - allocate(): direct save, only for a KPI whose weight is currently
 *     empty (0/null) — a brand-new allocation needs no one's approval.
 *   - requestChange(): a KPI that already has a weight can only be changed
 *     by creating a kpi_weight_change_requests row; approve()/reject() (Company
 *     Admin only) is what actually applies it.
 */
class WeightageController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $platformUser = $request->attributes->get('platformUser');
        $userId = $platformUser['id'];
        $isAdmin = $this->canAdministerCompany($request, $company);

        $companyRow = $supabase->first('companies', [
            'id' => 'eq.' . $company,
            'select' => 'id,name,code',
        ]);

        $myKpis = $supabase->get('kpis', [
            'company_id' => 'eq.' . $company,
            'assigned_user_id' => 'eq.' . $userId,
            'select' => 'id,name,description,weight,category_id,kpi_categories(name)',
            'order' => 'created_at.asc',
        ]);

        $myKpiIds = array_column($myKpis, 'id');

        // Same PGRST201 ambiguous-embed trap KpiController::index() already
        // documents: two foreign keys into `users` (requested_by, decided_by)
        // need the qualified `users!<constraint_name>(...)` form or PostgREST
        // returns a 300 Multiple Choices payload in place of the real rows.
        $myPendingRequests = empty($myKpiIds)
            ? []
            : $supabase->get('kpi_weight_change_requests', [
                'kpi_id' => 'in.(' . implode(',', $myKpiIds) . ')',
                'status' => 'eq.pending',
                'select' => 'id,kpi_id,old_weight,new_weight,reason,created_at',
            ]);

        $pendingByKpi = collect($myPendingRequests)->keyBy('kpi_id');

        $reviewQueue = [];
        if ($isAdmin) {
            $reviewQueue = $supabase->get('kpi_weight_change_requests', [
                'company_id' => 'eq.' . $company,
                'status' => 'eq.pending',
                'select' => 'id,kpi_id,old_weight,new_weight,reason,created_at,kpis(name),users!kpi_weight_change_requests_requested_by_foreign(name,email)',
                'order' => 'created_at.asc',
            ]);
        }

        return Inertia::render('Platform/Weightage/Index', [
            'company' => $companyRow,
            'kpis' => collect($myKpis)->map(function ($kpi) use ($pendingByKpi) {
                $kpi['pending_request'] = $pendingByKpi->get($kpi['id']);

                return $kpi;
            })->values(),
            'isAdmin' => $isAdmin,
            'reviewQueue' => $reviewQueue,
        ]);
    }

    /**
     * Direct-save for one or more of the caller's own KPIs, all currently at
     * an empty/zero weight. Anything else — a KPI not assigned to the
     * caller, or one that already has a weight — is refused by
     * restrict_kpi_owner_weight_update() regardless of what this validation
     * lets through; the checks here exist for a clean error message, not as
     * the real boundary.
     */
    public function allocate(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        $request->validate([
            'weights' => 'required|array|min:1',
            'weights.*' => 'required|numeric|min:0|max:100',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $errors = [];
        $saved = 0;

        foreach ($request->weights as $kpiId => $weight) {
            try {
                $supabase->update('kpis', ['id' => 'eq.' . $kpiId], ['weight' => $weight], false);
                $saved++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($saved > 0) {
            try {
                $this->logCompanyAction($request, 'allocate_kpi_weight', $company, null, [
                    'kpi_count' => $saved,
                ], 'kpi_weightage');
            } catch (\Throwable) {
                // Best-effort here specifically: the allocation itself
                // already succeeded and is visible to the user immediately
                // on reload, unlike a Company Admin action where a silent
                // logging gap would hide real administrative history.
            }
        }

        if (!empty($errors)) {
            return back()->with('error', 'Some allocations could not be saved: ' . implode('; ', $errors));
        }

        return back()->with('success', $saved . ' KPI weight(s) saved.');
    }

    /**
     * Requests a change to a KPI that already has a weight. Soft-skips
     * (rather than erroring) when a pending request already exists for this
     * KPI — mirrors the legacy page's own "already exists" tolerance,
     * matching what a person re-submitting the same form twice would expect.
     */
    public function requestChange(Request $request, string $company, string $kpi)
    {
        $this->ensureCompanyMember($request, $company);

        $request->validate([
            'old_weight' => 'required|numeric|min:0|max:100',
            'new_weight' => 'required|numeric|min:0|max:100',
            'reason' => 'required|string|min:20',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $platformUser = $request->attributes->get('platformUser');

        $existing = $supabase->first('kpi_weight_change_requests', [
            'kpi_id' => 'eq.' . $kpi,
            'status' => 'eq.pending',
            'select' => 'id',
        ]);

        if ($existing) {
            return back()->with('error', 'This KPI already has a pending weight-change request.');
        }

        try {
            $created = $supabase->insert('kpi_weight_change_requests', [
                'company_id' => $company,
                'kpi_id' => $kpi,
                'requested_by' => $platformUser['id'],
                'old_weight' => $request->old_weight,
                'new_weight' => $request->new_weight,
                'reason' => $request->reason,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not submit request: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'request_kpi_weight_change', $company, null, [
                'old_weight' => $request->old_weight,
                'new_weight' => $request->new_weight,
            ], 'kpi_weight_change_request', $created[0]['id'] ?? null);
        } catch (\Throwable) {
            return back()->with('error', 'Request was submitted, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Weight-change request submitted for approval.');
    }

    public function approve(Request $request, string $company, string $weightChangeRequest)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $platformUser = $request->attributes->get('platformUser');

        $pending = $supabase->first('kpi_weight_change_requests', [
            'id' => 'eq.' . $weightChangeRequest,
            'company_id' => 'eq.' . $company,
            'status' => 'eq.pending',
            'select' => 'id,kpi_id,new_weight',
        ]);

        if (!$pending) {
            return back()->with('error', 'That request is no longer pending.');
        }

        try {
            $supabase->update('kpis', ['id' => 'eq.' . $pending['kpi_id']], ['weight' => $pending['new_weight']], false);

            $supabase->update('kpi_weight_change_requests', ['id' => 'eq.' . $weightChangeRequest], [
                'status' => 'approved',
                'decided_by' => $platformUser['id'],
                'decided_at' => now()->toIso8601String(),
            ], false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not approve request: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'approve_kpi_weight_change', $company, null, [
                'new_weight' => $pending['new_weight'],
            ], 'kpi_weight_change_request', $weightChangeRequest);
        } catch (\Throwable) {
            return back()->with('error', 'Request was approved, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Weight change approved and applied.');
    }

    public function reject(Request $request, string $company, string $weightChangeRequest)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate(['decision_note' => 'nullable|string|max:1000']);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $platformUser = $request->attributes->get('platformUser');

        $pending = $supabase->first('kpi_weight_change_requests', [
            'id' => 'eq.' . $weightChangeRequest,
            'company_id' => 'eq.' . $company,
            'status' => 'eq.pending',
            'select' => 'id',
        ]);

        if (!$pending) {
            return back()->with('error', 'That request is no longer pending.');
        }

        try {
            $supabase->update('kpi_weight_change_requests', ['id' => 'eq.' . $weightChangeRequest], [
                'status' => 'rejected',
                'decided_by' => $platformUser['id'],
                'decided_at' => now()->toIso8601String(),
                'decision_note' => $request->decision_note,
            ], false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not reject request: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'reject_kpi_weight_change', $company, null, [], 'kpi_weight_change_request', $weightChangeRequest);
        } catch (\Throwable) {
            return back()->with('error', 'Request was rejected, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Weight change rejected.');
    }
}
