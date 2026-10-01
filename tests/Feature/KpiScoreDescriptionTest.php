<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `Platform\KpiController::scoreDescription()` — the Create KPI page's
 * advisory-only ANIRA score button, mirroring legacy's own
 * `AiController::scoreDescription()` exactly (same request/response shape,
 * same underlying `AiService::scoreKpiDescription()`). Never stored, never
 * required to submit — see the controller's own docblock.
 */
class KpiScoreDescriptionTest extends TestCase
{
    private function fakeToken(): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['sub' => 'member-auth-id', 'role' => 'authenticated'])), '+/', '-_'), '=');

        return "{$header}.{$payload}.fake-signature";
    }

    private function fakeMemberSession(): array
    {
        return [
            '*/rest/v1/users*' => Http::response([[
                'id' => 'member-id', 'name' => 'Member', 'email' => 'member@example.com',
                'role' => 'member', 'status' => 'active',
            ]], 200),
            '*/rest/v1/company_users*' => Http::response([[
                'company_id' => 'company-a', 'role' => 'employee', 'status' => 'active',
            ]], 200),
            '*/rest/v1/platform_admin_assignments*' => Http::response([], 200),
        ];
    }

    public function test_a_company_member_can_score_a_kpi_description(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*openai.com*' => Http::response([
                'choices' => [['message' => ['content' => '{"score": 8, "feedback": "Add a measurement method."}']]],
            ], 200),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken()])
            ->post('/platform/companies/company-a/kpis/score-description', [
                'kpi_title' => 'Increase Monthly Revenue',
                'kpi_description' => 'Track MRR growth month over month via the billing dashboard.',
                'target' => 100000,
                'unit' => 'currency',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'score' => 8, 'feedback' => 'Add a measurement method.']);
    }

    public function test_a_failed_openai_call_returns_a_graceful_failure_response(): void
    {
        Http::fake($this->fakeMemberSession() + [
            '*openai.com*' => Http::response([], 500),
        ]);

        $response = $this->withSession(['platform_access_token' => $this->fakeToken()])
            ->post('/platform/companies/company-a/kpis/score-description', [
                'kpi_title' => 'Increase Monthly Revenue',
                'kpi_description' => 'Track MRR growth month over month.',
            ]);

        $response->assertStatus(500);
        $response->assertJson(['success' => false]);
    }
}
