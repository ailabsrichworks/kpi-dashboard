<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\ApprovalRequestService;
use App\Services\ApprovalWorkflowService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Performix Company Platform, Phase 1 hardening (Part 6): "My Approvals" —
 * a real inbox for whoever a workflow step resolved to, not a company-wide
 * admin screen. `approval_request_steps_select`'s RLS already scopes
 * `resolved_approver_user_id = auth_current_user_id()` as the real boundary;
 * this controller only decides which of the caller's own steps go in which
 * tab.
 *
 * "Overdue" is computed here, not stored — a step becomes overdue purely by
 * the request's `submitted_at` aging past a fixed threshold, so it can never
 * drift from what "pending" already means.
 */
class ApprovalController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    private const OVERDUE_AFTER_DAYS = 3;

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $myUserId = $request->attributes->get('platformUser')['id'];

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name']);

        $pendingSteps = $supabase->get('approval_request_steps', [
            'resolved_approver_user_id' => 'eq.' . $myUserId,
            'status' => 'eq.pending',
            'company_id' => 'eq.' . $company,
            'select' => '*,approval_requests(*)',
            'order' => 'created_at.asc',
        ]);

        $decidedSteps = $supabase->get('approval_request_steps', [
            'acted_by' => 'eq.' . $myUserId,
            'company_id' => 'eq.' . $company,
            'select' => '*,approval_requests(*)',
            'order' => 'acted_at.desc',
            'limit' => 100,
        ]);

        $pending = $this->enrichSteps($supabase, $pendingSteps);
        $decided = $this->enrichSteps($supabase, $decidedSteps);

        $overdueCutoff = Carbon::now()->subDays(self::OVERDUE_AFTER_DAYS);
        $overdue = array_values(array_filter(
            $pending,
            fn ($s) => !empty($s['request']['submitted_at']) && Carbon::parse($s['request']['submitted_at'])->lessThan($overdueCutoff)
        ));

        return Inertia::render('Platform/Approvals/Index', [
            'company' => $companyRow,
            'pending' => $pending,
            'overdue' => $overdue,
            'approved' => array_values(array_filter($decided, fn ($s) => $s['status'] === 'approved')),
            'rejected' => array_values(array_filter($decided, fn ($s) => $s['status'] === 'rejected')),
            'returned' => array_values(array_filter($decided, fn ($s) => $s['status'] === 'returned')),
        ]);
    }

    private function enrichSteps(SupabaseUserService $supabase, array $steps): array
    {
        return array_map(function ($step) use ($supabase) {
            $req = $step['approval_requests'] ?? null;

            return $step + [
                'request' => $req,
                'object' => $req ? $this->loadObjectSummary($supabase, $req) : null,
            ];
        }, $steps);
    }

    /**
     * A small, object-type-specific summary (spec Part 6's own example:
     * "Target: RM750,000 / Current Approved Actual: RM680,000 / Proposed
     * Actual: RM720,000") — deliberately not a generic dump of the raw row,
     * since the two object types answer "what am I deciding?" differently.
     */
    private function loadObjectSummary(SupabaseUserService $supabase, array $req): ?array
    {
        if ($req['object_type'] === 'kpi_submission') {
            return $supabase->first('kpi_submissions', [
                'id' => 'eq.' . $req['object_id'],
                'select' => 'id,value,submission_date,notes,evidence_note,revision_number,kpis(name,target,unit),users(name)',
            ]);
        }

        if ($req['object_type'] === 'kpi_target_revision') {
            return $supabase->first('kpi_target_revisions', [
                'id' => 'eq.' . $req['object_id'],
                'select' => 'id,old_target,new_target,reason,effective_financial_year,kpis(name,unit)',
            ]);
        }

        return null;
    }

    /**
     * Rejecting or returning a request always requires a comment (spec Part
     * 6: "Require comments according to workflow rules") — approving does
     * not, unless the specific step was configured to require one.
     */
    public function decide(Request $request, string $company, string $approvalRequest)
    {
        $this->ensureCompanyMember($request, $company);

        $request->validate([
            'decision' => 'required|in:approved,rejected,returned',
            'comments' => 'nullable|string',
        ]);

        if (in_array($request->decision, ['rejected', 'returned'], true) && !$request->filled('comments')) {
            $label = $request->decision === 'rejected' ? 'rejecting' : 'returning for revision';

            return back()->with('error', "Please provide a comment explaining why you're {$label} this.");
        }

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $myUserId = $request->attributes->get('platformUser')['id'];

        $before = $supabase->first('approval_requests', [
            'id' => 'eq.' . $approvalRequest,
            'company_id' => 'eq.' . $company,
            'select' => '*',
        ]);

        if (!$before) {
            abort(404, 'Approval request not found.');
        }

        $service = new ApprovalRequestService($supabase, new ApprovalWorkflowService($supabase));

        try {
            $service->decide($approvalRequest, $request->decision, $myUserId, $request->comments);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not record decision: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'decide_approval_request', $company, null, [
                'workflow_type' => $before['workflow_type'],
                'object_type' => $before['object_type'],
            ], 'approval_request', $approvalRequest, ['status' => $before['status']], [
                'decision' => $request->decision,
                'comments' => $request->comments,
            ]);
        } catch (\Throwable) {
            return back()->with('error', 'Decision was recorded, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Decision recorded: ' . $request->decision . '.');
    }
}
