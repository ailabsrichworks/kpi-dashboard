<?php

namespace Tests\Feature;

use App\Http\Controllers\PerformanceController;
use App\Services\AppraiserDelegationService;
use App\Services\NotificationService;
use App\Services\SupabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Section 7's Confirmation/Salary Review/Promotion checkboxes are what
 * actually require VP/SLT to act — a manager who signs WITHOUT ticking any
 * of them has settled the whole thing themselves, and nothing further
 * should be required from anyone above. This proves the real server-side
 * enforcement (PerformanceController::appraiserSave()'s SLT access gate),
 * not just the notification text — an SLT must be able to add optional
 * remarks in that case, and must still be blocked in the normal case where
 * something WAS ticked and VP hasn't signed yet.
 */
class Section7NotRequiredTest extends TestCase
{
    private function chainFakes(): array
    {
        return [
            '*/rest/v1/appraiser_delegations*' => Http::response([], 200),
            // Anchored to "/employees?id=eq...", not just "id=eq..." — the
            // latter also matches performance_reports' "employee_id=eq.exec-1"
            // as a substring, silently hijacking that request.
            '*/employees?id=eq.exec-1*' => Http::response([[
                'id' => 'exec-1', 'role' => 'EXECUTIVE', 'manager_id' => 'manager-1',
                'is_active' => true,
            ]], 200),
            '*/employees?id=eq.manager-1*' => Http::response([[
                'id' => 'manager-1', 'role' => 'MANAGER', 'vp_id' => 'vp-1',
            ]], 200),
            '*/employees?id=eq.vp-1*' => Http::response([[
                'id' => 'vp-1', 'role' => 'VP', 'reports_to_id' => 'slt-1',
            ]], 200),
            '*/employees?id=eq.slt-1*' => Http::response([[
                'id' => 'slt-1', 'role' => 'SLT',
            ]], 200),
        ];
    }

    private function submitAsSlt(array $existingFormData, string $currentStatus = 'appraised')
    {
        Http::fake(array_merge($this->chainFakes(), [
            '*/rest/v1/performance_reports*' => function ($request) use ($existingFormData, $currentStatus) {
                if ($request->method() === 'GET') {
                    return Http::response([[
                        'form_data' => $existingFormData,
                        'status'    => $currentStatus,
                    ]], 200);
                }

                return Http::response([], 200);
            },
        ]));

        $this->withSession(['employee_uuid' => 'slt-1']);

        $request = Request::create('/performance/appraise/exec-1/q2/save', 'POST', [
            'action'    => 'draft',
            'form_data' => ['s7_slt_remarks' => 'Looks good to me.'],
        ]);

        return app(PerformanceController::class)->appraiserSave(
            'exec-1',
            'q2',
            $request,
            app(SupabaseService::class),
            app(NotificationService::class),
            app(AppraiserDelegationService::class)
        );
    }

    public function test_slt_can_add_optional_remarks_once_manager_settled_with_nothing_ticked(): void
    {
        $response = $this->submitAsSlt([
            's7_manager_sig'           => 'data:image/png;base64,abc',
            's7_manager_confirmation'  => false,
            's7_manager_salary_review' => false,
            's7_manager_promotion'     => false,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true)['success']);
    }

    public function test_slt_is_still_blocked_when_something_was_ticked_and_vp_has_not_signed(): void
    {
        $response = $this->submitAsSlt([
            's7_manager_sig'          => 'data:image/png;base64,abc',
            's7_manager_confirmation' => true,
            // no s7_vp_sig — VP hasn't signed Part B yet.
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString(
            "VP hasn't signed",
            json_decode($response->getContent(), true)['error']
        );
    }
}
