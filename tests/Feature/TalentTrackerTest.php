<?php

namespace Tests\Feature;

use App\Services\SupabaseService;
use Tests\TestCase;

/**
 * Talent Tracker permissions: everyone logged in can view, only SLT and BTS
 * can add/edit/delete. Uses an in-memory SupabaseService so nothing touches
 * the real database.
 */
class TalentTrackerTest extends TestCase
{
    private const EMP = '11111111-1111-4111-8111-111111111111';

    private object $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fake = new class extends SupabaseService {
            public array $records = [];
            public array $writes = [];

            public function __construct() {}

            public function get(string $table, array $query = [])
            {
                if ($table === 'employees') {
                    return [['id' => '11111111-1111-4111-8111-111111111111', 'full_name' => 'AYDEN ARENGA NATHAN', 'short_name' => 'Ayden', 'position' => 'Vice President - Operations', 'department_code' => 'OPS', 'role' => 'VP']];
                }

                return $this->records;
            }

            public function insert(string $table, array $data)
            {
                $this->writes[] = ['insert', $data];

                return [$data];
            }

            public function update(string $table, array $filters, array $data)
            {
                $this->writes[] = ['update', $filters, $data];

                return [];
            }

            public function delete(string $table, array $filters = [])
            {
                $this->writes[] = ['delete', $filters];

                return [];
            }
        };

        $this->app->instance(SupabaseService::class, $this->fake);
    }

    private function as(string $role, string $dept = 'OPS'): static
    {
        return $this->withSession([
            'employee_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'company_code'  => 'RCG',
            'role'          => $role,
            'department_code' => $dept,
        ]);
    }

    private function payload(): array
    {
        return ['personId' => self::EMP, 'type' => 'speaker', 'title' => 'Workshop CS', 'date' => '2026-03-12', 'notes' => ''];
    }

    public function test_everyone_can_view_but_only_sees_view_only_flag(): void
    {
        $this->as('EXECUTIVE')->getJson('/talent-tracker/data')
            ->assertOk()->assertJsonPath('canEdit', false)->assertJsonPath('people.0.name', 'AYDEN ARENGA NATHAN');
    }

    public function test_slt_and_bts_get_edit_flag(): void
    {
        $this->as('SLT')->getJson('/talent-tracker/data')->assertJsonPath('canEdit', true);
        $this->as('EXECUTIVE', 'BTS')->getJson('/talent-tracker/data')->assertJsonPath('canEdit', true);
    }

    public function test_others_cannot_write(): void
    {
        foreach (['EXECUTIVE', 'MANAGER', 'VP'] as $role) {
            $this->as($role)->postJson('/talent-tracker/records', $this->payload())->assertForbidden();
            $this->as($role)->patchJson('/talent-tracker/records/abc', $this->payload())->assertForbidden();
            $this->as($role)->deleteJson('/talent-tracker/records/abc')->assertForbidden();
        }
        $this->assertSame([], $this->fake->writes);
    }

    public function test_slt_can_add_edit_delete(): void
    {
        $this->as('SLT')->postJson('/talent-tracker/records', $this->payload())->assertCreated();
        $this->as('SLT')->patchJson('/talent-tracker/records/abc', $this->payload())->assertOk();
        $this->as('SLT')->deleteJson('/talent-tracker/records/abc')->assertOk();
        $this->assertSame(['insert', 'update', 'delete'], array_column($this->fake->writes, 0));
    }

    public function test_bts_can_add_and_rejects_bad_type(): void
    {
        $this->as('EXECUTIVE', 'BTS')->postJson('/talent-tracker/records', $this->payload())->assertCreated();
        $this->as('EXECUTIVE', 'BTS')->postJson('/talent-tracker/records', ['type' => 'nope'] + $this->payload())->assertStatus(422);
    }

    public function test_trainer_type_is_no_longer_accepted(): void
    {
        $this->as('SLT')->postJson('/talent-tracker/records', ['type' => 'trainer'] + $this->payload())->assertStatus(422);
    }

    public function test_requires_login(): void
    {
        // Logged-out requests are bounced to login by the route group and never reach a write.
        $this->getJson('/talent-tracker/data')->assertRedirect();
        $this->postJson('/talent-tracker/records', $this->payload())->assertRedirect();
        $this->assertSame([], $this->fake->writes);
    }

    public function test_tracker_page_renders(): void
    {
        $this->as('EXECUTIVE')->get('/talent-tracker/app')->assertOk()->assertSee('Talent Tracker')->assertSee('csrf-token', false);
    }
}
