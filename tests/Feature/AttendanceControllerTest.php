<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Platform\AttendanceController — the real port of legacy's Attendance page.
 * See the migration's own docblock (2026_09_29_030000_create_attendance_tables.php)
 * for why this is admin-only both ways and doesn't enrich CSV rows against a
 * Platform account yet.
 */
class AttendanceControllerTest extends TestCase
{
    private function fakeToken(string $sub): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => $sub, 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    private function fakeMemberSession(string $role = 'employee'): array
    {
        return [
            '*/rest/v1/users*' => Http::response([[
                'id' => 'member-id', 'name' => 'Member', 'email' => 'member@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-a', 'role' => $role, 'status' => 'active',
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
        ];
    }

    public function test_a_non_admin_is_rejected(): void
    {
        Http::fake($this->fakeMemberSession('employee'));

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('member-auth-id')])
            ->get('/platform/companies/company-a/attendance');

        $response->assertStatus(403);
    }

    public function test_an_admin_sees_the_month_status_grid(): void
    {
        Http::fake($this->fakeMemberSession('company_admin') + [
            '*/rest/v1/companies*' => Http::response([['id' => 'company-a', 'name' => 'Company A', 'code' => 'COA']], 200),
            '*/rest/v1/attendance_summary*' => Http::response([
                ['month' => 3, 'updated_at' => '2026-04-01T00:00:00Z'],
            ], 200),
            '*/rest/v1/public_holidays*' => Http::response([], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('admin-auth-id')])
            ->get('/platform/companies/company-a/attendance');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Platform/Attendance/Index')
            ->where('monthStatus.3', '2026-04-01T00:00:00Z'));
    }

    public function test_import_rejects_an_invalid_sheet_url(): void
    {
        Http::fake($this->fakeMemberSession('company_admin') + [
            '*/rest/v1/companies*' => Http::response([['id' => 'company-a', 'name' => 'Company A', 'code' => 'COA']], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('admin-auth-id')])
            ->post('/platform/companies/company-a/attendance/import', [
                'sheet_url' => 'https://example.com/not-a-sheet',
                'month' => 3,
                'year' => 2026,
            ]);

        $response->assertSessionHas('error', 'Invalid Google Sheet URL.');
    }

    public function test_import_parses_a_csv_and_stages_a_preview(): void
    {
        // Clock-in times are "H:MM"/"HH:MM" (colon-separated, matching
        // self::WORK_START's own "08:30" format) -- a single-digit hour like
        // "8:00" is exactly 4 characters, which is what the controller's
        // leading-zero padding actually targets (not a bare "0800").
        $csv = "Internal ID,First,Last,Preferred,Email,Clock In,Date\n"
            . "E001,Jane,Doe,Jane,jane@example.com,8:00,2026-03-02\n"
            . "E001,Jane,Doe,Jane,jane@example.com,8:45,2026-03-03\n";

        Http::fake($this->fakeMemberSession('company_admin') + [
            '*/rest/v1/companies*' => Http::response([['id' => 'company-a', 'name' => 'Company A', 'code' => 'COA']], 200),
            '*/rest/v1/public_holidays*' => Http::response([], 200),
            '*/rest/v1/attendance_summary*' => Http::response([], 200),
            'docs.google.com/*' => Http::response($csv, 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('admin-auth-id')])
            ->post('/platform/companies/company-a/attendance/import', [
                'sheet_url' => 'https://docs.google.com/spreadsheets/d/abc123/edit',
                'month' => 3,
                'year' => 2026,
            ]);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Platform/Attendance/Index')
            ->has('preview.results', 1)
            ->where('preview.results.0.external_employee_id', 'E001')
            ->where('preview.results.0.present_days', 2)
            ->where('preview.results.0.late_count', 1));
    }

    public function test_save_persists_the_staged_preview_and_logs_it(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([[
                'id' => 'admin-id', 'name' => 'Admin', 'email' => 'admin@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-a', 'role' => 'company_admin', 'status' => 'active',
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
            '*/rest/v1/attendance_summary*' => Http::sequence()
                ->push([], 200) // save()'s own "does a row already exist" lookup
                ->push([['id' => 'row-1']], 201),
            '*/rest/v1/admin_action_logs*' => Http::response([['id' => 'log-1']], 201),
        ]);

        $staged = [
            'company_id' => 'company-a',
            'month' => 3,
            'year' => 2026,
            'sheet_url' => 'https://docs.google.com/spreadsheets/d/abc123/edit',
            'results' => [
                'E001' => [
                    'external_employee_id' => 'E001', 'name' => 'Jane Doe', 'email' => 'jane@example.com',
                    'department' => null, 'working_days' => 21, 'present_days' => 20, 'absent_days' => 1,
                    'late_count' => 2, 'total_late_minutes' => 30, 'insufficient_count' => 0,
                    'mc_days' => 0, 'al_days' => 0, 'other_leave_days' => 0,
                ],
            ],
        ];

        $response = $this->withSession([
            'platform_access_token' => $this->fakeToken('admin-auth-id'),
            'attendance_preview.tok-1' => $staged,
        ])->post('/platform/companies/company-a/attendance/save', [
            'token' => 'tok-1',
            'leave' => ['E001' => ['mc_days' => 1, 'al_days' => 0, 'other_leave_days' => 0]],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Saved attendance for 1 employee(s).');
        $this->assertNull(session('attendance_preview.tok-1'));

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/rest/v1/attendance_summary')
                && ($request['external_employee_id'] ?? null) === 'E001'
                && ($request['mc_days'] ?? null) === 1
                && ($request['absent_days'] ?? null) === 0; // 1 - 1 mc day
        });
    }

    public function test_save_refuses_an_expired_or_foreign_token(): void
    {
        Http::fake($this->fakeMemberSession('company_admin'));

        $response = $this->withSession(['platform_access_token' => $this->fakeToken('admin-auth-id')])
            ->post('/platform/companies/company-a/attendance/save', ['token' => 'does-not-exist']);

        $response->assertSessionHas('error', 'This preview has expired — import the sheet again.');
    }
}
