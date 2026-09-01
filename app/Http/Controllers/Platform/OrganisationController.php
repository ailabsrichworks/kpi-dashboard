<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The organisation hierarchy tree/list view (spec §23) — the
 * `unit_type`/`parent_department_id` structure has existed since
 * 2026_08_28_010000, but until now the only screen reading it was
 * `Departments/Index` as a flat list. Read-only here; adding/editing units
 * stays on the existing Departments admin page, which already owns that
 * workflow.
 */
class OrganisationController extends Controller
{
    use PlatformAuthorization;

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name,code']);

        $departments = $supabase->get('departments', [
            'company_id' => 'eq.' . $company,
            'select' => 'id,name,unit_type,parent_department_id,status',
            'order' => 'name.asc',
        ]);

        $departmentIds = array_column($departments, 'id');

        $memberCounts = [];
        if (!empty($departmentIds)) {
            $members = $supabase->get('department_users', [
                'department_id' => 'in.(' . implode(',', $departmentIds) . ')',
                'select' => 'department_id',
            ]);
            $memberCounts = collect($members)->countBy('department_id')->all();
        }

        $departments = collect($departments)->map(fn ($d) => $d + ['member_count' => $memberCounts[$d['id']] ?? 0])->values();

        return Inertia::render('Platform/Organisation/Index', [
            'company' => $companyRow,
            'departments' => $departments,
        ]);
    }
}
