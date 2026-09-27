<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The module catalog (spec §8's "Platform > Modules") and per-company
 * enablement — `platform_modules` has no `company_id`, the same shared-
 * catalog shape as `kpi_templates`/`subscription_plans`; enabling one for a
 * company is a `company_module_grants` row, not a flag on `companies`
 * itself, so a company can be granted several independently.
 */
class ModuleController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function index(Request $request)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $modules = $supabase->get('platform_modules', ['select' => '*', 'order' => 'name.asc']);
        $companies = $supabase->get('companies', ['select' => 'id,name,code', 'order' => 'name.asc', 'limit' => 200]);
        $grants = $supabase->get('company_module_grants', ['select' => 'company_id,module_id,enabled']);

        $grantMap = [];
        foreach ($grants as $g) {
            $grantMap[$g['company_id']][$g['module_id']] = $g['enabled'];
        }

        return Inertia::render('Platform/Hq/Modules/Index', [
            'modules' => $modules,
            'companies' => $companies,
            'grants' => $grantMap,
        ]);
    }

    public function toggle(Request $request, string $company, string $module)
    {
        $this->ensureSuperAdmin($request);

        $request->validate(['enabled' => 'required|boolean']);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $existing = $supabase->first('company_module_grants', [
            'company_id' => 'eq.' . $company,
            'module_id' => 'eq.' . $module,
            'select' => 'id',
        ]);

        $me = $request->attributes->get('platformUser')['id'] ?? null;

        try {
            if ($existing) {
                $supabase->update('company_module_grants', ['id' => 'eq.' . $existing['id']], [
                    'enabled' => $request->boolean('enabled'),
                    'granted_by' => $me,
                    'granted_at' => now()->toIso8601String(),
                ], false);
            } else {
                $supabase->insert('company_module_grants', [
                    'company_id' => $company,
                    'module_id' => $module,
                    'enabled' => $request->boolean('enabled'),
                    'granted_by' => $me,
                ], false);
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not update module: ' . $e->getMessage());
        }

        $this->logAdminAction($request, 'toggle_company_module', $company, null, ['module_id' => $module, 'enabled' => $request->boolean('enabled')], 'company_module_grant');

        return back()->with('success', 'Module updated.');
    }
}
