<?php

namespace Tests\Feature;

use App\Http\Controllers\AppraiserDelegationController;
use App\Services\NotificationService;
use App\Services\SupabaseService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * When BTS activates a Manager -> VP appraiser delegation, anything an
 * Executive already submitted BEFORE the delegation existed had already
 * sent its "ready for your review" notification to the absent Manager --
 * and since this app has no "my team's pending appraisals" listing page
 * (appraisal pages are only ever reached via a notification's link), the
 * new delegate would otherwise have no way to discover it. This proves
 * AppraiserDelegationController::notifyDelegateOfAlreadyPendingAppraisals()
 * re-sends that notification to the delegate for every still-pending case,
 * and only for genuinely pending (status=submitted) reports.
 */
class AppraiserDelegationControllerTest extends TestCase
{
    private function invoke(array $manager, array $delegate): int
    {
        $controller = app(AppraiserDelegationController::class);
        $ref = new \ReflectionMethod($controller, 'notifyDelegateOfAlreadyPendingAppraisals');
        $ref->setAccessible(true);

        return $ref->invoke($controller, $manager, $delegate, app(SupabaseService::class), app(NotificationService::class));
    }

    public function test_re_notifies_the_delegate_for_every_executive_with_a_pending_submitted_appraisal(): void
    {
        Http::fake([
            '*manager_id=eq.manager-1*' => Http::response([
                ['id' => 'exec-1', 'full_name' => 'Exec One', 'short_name' => 'EXEC1'],
                ['id' => 'exec-2', 'full_name' => 'Exec Two', 'short_name' => 'EXEC2'],
            ], 200),
            '*/rest/v1/performance_reports*' => Http::response([
                ['employee_id' => 'exec-1', 'quarter' => 'Q2'],
            ], 200),
            '*/rest/v1/notifications*' => Http::response([], 201),
            '*/rest/v1/user_company_roles*' => Http::response([], 200),
        ]);

        $manager = ['id' => 'manager-1', 'short_name' => 'MGR'];
        $delegate = ['id' => 'delegate-1', 'short_name' => 'VP1'];

        $count = $this->invoke($manager, $delegate);

        $this->assertSame(1, $count);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/rest/v1/notifications')
                && $request->method() === 'POST'
                && $request['recipient_employee_id'] === 'delegate-1'
                && $request['subject_employee_id'] === 'exec-1'
                && $request['quarter'] === 'Q2'
                && str_contains($request['title'], 'Exec One')
                && str_contains($request['message'], 'MGR');
        });
    }

    public function test_does_not_notify_for_executives_with_no_pending_submitted_report(): void
    {
        Http::fake([
            '*manager_id=eq.manager-1*' => Http::response([
                ['id' => 'exec-1', 'full_name' => 'Exec One', 'short_name' => 'EXEC1'],
            ], 200),
            '*/rest/v1/performance_reports*' => Http::response([], 200),
            '*/rest/v1/notifications*' => Http::response([], 201),
        ]);

        $manager = ['id' => 'manager-1', 'short_name' => 'MGR'];
        $delegate = ['id' => 'delegate-1', 'short_name' => 'VP1'];

        $count = $this->invoke($manager, $delegate);

        $this->assertSame(0, $count);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/rest/v1/notifications'));
    }

    public function test_returns_zero_when_the_manager_has_no_active_executives(): void
    {
        Http::fake([
            '*manager_id=eq.manager-1*' => Http::response([], 200),
        ]);

        $count = $this->invoke(['id' => 'manager-1', 'short_name' => 'MGR'], ['id' => 'delegate-1', 'short_name' => 'VP1']);

        $this->assertSame(0, $count);
    }
}
