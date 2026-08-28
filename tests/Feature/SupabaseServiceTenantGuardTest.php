<?php

namespace Tests\Feature;

use App\Services\SupabaseService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SupabaseService is the service_role client — it bypasses Postgres RLS
 * entirely, so it must never touch a tenant-owned Platform table (the ones
 * carrying `company_id` under the Core Platform Rule in CLAUDE.md). This
 * guard is what makes that a property of the class rather than a fact that
 * happens to be true today because the code paths that would violate it are
 * currently dead (see SupabaseService::TENANT_OWNED_TABLES' docblock).
 */
class SupabaseServiceTenantGuardTest extends TestCase
{
    public function test_read_of_a_tenant_owned_table_is_refused(): void
    {
        $service = new SupabaseService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/kpis/');

        $service->get('kpis', ['select' => '*']);
    }

    public function test_write_of_a_tenant_owned_table_is_refused(): void
    {
        $service = new SupabaseService();

        $this->expectException(\RuntimeException::class);

        $service->insert('kpi_submissions', ['value' => 1]);
    }

    public function test_get_many_refuses_if_any_requested_table_is_tenant_owned(): void
    {
        $service = new SupabaseService();

        $this->expectException(\RuntimeException::class);

        $service->getMany([
            'ok'  => ['table' => 'companies', 'query' => []],
            'bad' => ['table' => 'department_users', 'query' => []],
        ]);
    }

    public function test_governance_tables_added_this_session_are_also_refused(): void
    {
        $service = new SupabaseService();

        $this->expectException(\RuntimeException::class);

        $service->get('approval_requests', ['select' => '*']);
    }

    public function test_exempt_tables_are_still_reachable(): void
    {
        Http::fake([
            '*/rest/v1/users*' => Http::response([['id' => 'u1']], 200),
        ]);

        $service = new SupabaseService();

        $result = $service->get('users', ['select' => '*']);

        $this->assertSame([['id' => 'u1']], $result);
    }
}
