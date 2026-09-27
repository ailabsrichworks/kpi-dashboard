<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The subscription plan catalog the Richworks "Control Centre" manages —
 * deliberately not scoped to a company, since `subscription_plans` carries
 * no `company_id` at all (see its migration's docblock), the same shape as
 * `kpi_templates`. Only the Center curates the catalog
 * (`subscription_plans_write` requires `auth_is_richworks_super_admin()`);
 * any signed-in Platform user may read it (so a future "your plan" display
 * on a company's own dashboard could resolve a plan name without needing
 * elevated access) — this controller itself stays Super-Admin-only, since
 * only the Center creates/edits/retires plans.
 *
 * A plan already assigned to a company is deactivated (`is_active = false`)
 * rather than deleted — deleting it would either cascade-null every
 * company's `subscription_plan_id` or fail on the foreign key, neither of
 * which is a real answer to "retire this plan." `destroy()` is only reachable
 * for a plan with zero companies currently assigned to it.
 */
class SubscriptionPlanController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function index(Request $request)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $plans = $supabase->get('subscription_plans', [
            'select' => '*',
            'order' => 'name.asc',
        ]);

        $planIds = array_column($plans, 'id');

        $companyCounts = [];
        if (!empty($planIds)) {
            $companies = $supabase->get('companies', [
                'subscription_plan_id' => 'in.(' . implode(',', $planIds) . ')',
                'select' => 'subscription_plan_id',
            ]);
            foreach ($companies as $c) {
                $planId = $c['subscription_plan_id'];
                $companyCounts[$planId] = ($companyCounts[$planId] ?? 0) + 1;
            }
        }

        return Inertia::render('Platform/SubscriptionPlans/Index', [
            'plans' => $plans,
            'companyCounts' => $companyCounts,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureSuperAdmin($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'price_cents' => 'required|integer|min:0',
            'billing_period' => 'required|in:monthly,yearly',
            'max_users' => 'nullable|integer|min:1',
            'max_departments' => 'nullable|integer|min:1',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            $supabase->insert('subscription_plans', [
                'name' => $request->name,
                'price_cents' => $request->price_cents,
                'billing_period' => $request->billing_period,
                'max_users' => $request->max_users,
                'max_departments' => $request->max_departments,
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not create plan: ' . $e->getMessage());
        }

        try {
            $this->logAdminAction($request, 'create_subscription_plan', null, null, ['name' => $request->name], 'subscription_plan');
        } catch (\Throwable) {
            return back()->with('error', 'Plan was created, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Plan "' . $request->name . '" created.');
    }

    public function update(Request $request, string $plan)
    {
        $this->ensureSuperAdmin($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'price_cents' => 'required|integer|min:0',
            'billing_period' => 'required|in:monthly,yearly',
            'max_users' => 'nullable|integer|min:1',
            'max_departments' => 'nullable|integer|min:1',
            'is_active' => 'required|boolean',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $before = $supabase->first('subscription_plans', ['id' => 'eq.' . $plan, 'select' => '*']);

        try {
            $supabase->update('subscription_plans', ['id' => 'eq.' . $plan], [
                'name' => $request->name,
                'price_cents' => $request->price_cents,
                'billing_period' => $request->billing_period,
                'max_users' => $request->max_users,
                'max_departments' => $request->max_departments,
                'is_active' => $request->boolean('is_active'),
                'updated_at' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not update plan: ' . $e->getMessage());
        }

        try {
            $this->logAdminAction($request, 'update_subscription_plan', null, null, [], 'subscription_plan', $plan, $before, $request->only([
                'name', 'price_cents', 'billing_period', 'max_users', 'max_departments', 'is_active',
            ]));
        } catch (\Throwable) {
            return back()->with('error', 'Plan was updated, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Plan updated.');
    }

    public function destroy(Request $request, string $plan)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $inUse = $supabase->first('companies', [
            'subscription_plan_id' => 'eq.' . $plan,
            'select' => 'id',
        ]);

        if ($inUse) {
            return back()->with('error', 'Cannot delete — at least one company is currently on this plan. Reassign or deactivate it instead.');
        }

        try {
            $supabase->delete('subscription_plans', ['id' => 'eq.' . $plan]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not delete plan: ' . $e->getMessage());
        }

        try {
            $this->logAdminAction($request, 'delete_subscription_plan', null, null, [], 'subscription_plan', $plan);
        } catch (\Throwable) {
            return back()->with('error', 'Plan was deleted, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Plan deleted.');
    }
}
