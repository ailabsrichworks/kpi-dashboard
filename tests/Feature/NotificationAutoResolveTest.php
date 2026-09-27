<?php

namespace Tests\Feature;

use App\Http\Controllers\ApprovalController;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Approving/rejecting a request from the Approval Center, or actually
 * submitting your part of an appraisal, is at least as strong a signal that
 * the underlying notification has been dealt with as clicking the
 * notification row itself — but until now only clicking the row (or "Mark
 * all as read") ever set `is_read`. That left the Approvals/Needs
 * Appraisal/Ready to Sign badges stuck showing requests that were already
 * resolved through their normal screens, which looks exactly like "Mark all
 * as read" being broken all over again even though it isn't.
 */
class NotificationAutoResolveTest extends TestCase
{
    public function test_mark_approval_resolved_matches_on_the_links_embedded_request_id(): void
    {
        Http::fake(['*/rest/v1/notifications*' => Http::response([], 200)]);

        app(NotificationService::class)->markApprovalResolved('approver-1', 'approval-123');

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/rest/v1/notifications') || $request->method() !== 'PATCH') {
                return false;
            }

            $query = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return ($query['recipient_employee_id'] ?? null) === 'eq.approver-1'
                && ($query['link'] ?? null) === 'ilike.*highlight=approval-123*'
                && $request['is_read'] === true;
        });
    }

    public function test_mark_appraisal_resolved_matches_recipient_subject_quarter_and_type(): void
    {
        Http::fake(['*/rest/v1/notifications*' => Http::response([], 200)]);

        app(NotificationService::class)->markAppraisalResolved('manager-1', 'employee-1', 'Q2', ['appraisal_submitted', 'appraisal_appraised']);

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/rest/v1/notifications') || $request->method() !== 'PATCH') {
                return false;
            }

            $query = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return ($query['recipient_employee_id'] ?? null) === 'eq.manager-1'
                && ($query['subject_employee_id'] ?? null) === 'eq.employee-1'
                && ($query['quarter'] ?? null) === 'eq.Q2'
                && ($query['type'] ?? null) === 'in.(appraisal_submitted,appraisal_appraised)'
                && $request['is_read'] === true;
        });
    }

    /**
     * Exercises the real endpoint via the "already processed" branch — the
     * one path that reaches the new markApprovalResolved() call without
     * needing to fake the entire approve chain (kpi_quarters patches, history
     * logging, etc.) that a genuinely pending approval would trigger next.
     */
    public function test_approve_clears_the_requests_notification_even_when_already_processed(): void
    {
        Http::fake([
            '*/rest/v1/kpi_update_approvals*' => Http::response([[
                'id' => 'approval-1', 'status' => 'approved', 'reason' => 'Looks good',
            ]], 200),
            '*/rest/v1/notifications*' => Http::response([], 200),
        ]);

        $this->withSession(['employee_uuid' => 'approver-1', 'short_name' => 'Approver']);

        $response = app(ApprovalController::class)->approve(request(), 'approval-1');

        $this->assertSame(422, $response->getStatusCode());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/notifications')
                && $request->method() === 'PATCH'
                && str_contains($request->url(), 'highlight%3Dapproval-1')
                && $request['is_read'] === true;
        });
    }
}
