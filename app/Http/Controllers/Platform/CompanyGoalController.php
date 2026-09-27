<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Company Goals — the top of the Performix cascade (Company Goal -> Company
 * KPI -> Department KPI -> Individual KPI). Viewing is open to any member of
 * the company (spec: management should be able to answer "are we achieving
 * our company goals," which only makes sense if everyone can see the
 * direction they're contributing to); creating/editing is restricted to
 * Company Admins, mirroring KpiController exactly.
 */
class CompanyGoalController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', [
            'id' => 'eq.' . $company,
            'select' => 'id,name,code',
        ]);

        $goals = $supabase->get('company_goals', [
            'company_id' => 'eq.' . $company,
            'select' => '*,users(name)',
            'order' => 'created_at.desc',
        ]);

        $goalIds = array_column($goals, 'id');

        // Contributing KPIs for the cascade view (spec §17/§19: "what
        // contributes to this company goal") -- only top-level KPIs are
        // linked directly to a goal; deeper cascade levels (Department KPI,
        // Individual KPI) are reached by following parent_kpi_id from there.
        $linkedKpis = empty($goalIds)
            ? []
            : $supabase->get('kpis', [
                'company_goal_id' => 'in.(' . implode(',', $goalIds) . ')',
                'select' => 'id,name,company_goal_id,target,stretch_target,measurement_direction',
            ]);

        $members = $supabase->get('company_users', [
            'company_id' => 'eq.' . $company,
            'status' => 'eq.active',
            'select' => 'user_id,users(name,email)',
        ]);

        return Inertia::render('Platform/Goals/Index', [
            'company' => $companyRow,
            'goals' => $goals,
            'linkedKpis' => $linkedKpis,
            'members' => $members,
        ]);
    }

    public function store(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'subcategory' => 'nullable|string|max:100',
            'owner_user_id' => 'nullable|uuid',
            'weightage' => 'nullable|numeric|min:0|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:draft,active,at_risk,completed,cancelled,archived',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        // Same "must not silently accept invalid total weightage" rule the
        // spec asks for on KPI weightage (§13/§56) applied here too: warn by
        // rejecting outright rather than normalising, leaving the decision
        // to the admin. Only active/draft goals count toward the total —
        // a cancelled or archived goal's old weightage shouldn't block a
        // new one from using that room.
        if ($request->filled('weightage')) {
            $error = $this->weightageOverageError($supabase, $company, (float) $request->weightage);
            if ($error) {
                return back()->withInput()->with('error', $error);
            }
        }

        try {
            $goal = $supabase->insert('company_goals', [
                'company_id' => $company,
                'title' => $request->title,
                'description' => $request->description,
                'category' => $request->category,
                'subcategory' => $request->subcategory,
                'owner_user_id' => $request->owner_user_id ?: null,
                'weightage' => $request->weightage,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => $request->input('status', 'draft'),
            ], false);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not create company goal: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'create_company_goal', $company, null, [], 'company_goal', null, null, [
                'title' => $request->title,
                'weightage' => $request->weightage,
                'status' => $request->input('status', 'draft'),
            ]);
        } catch (\Throwable) {
            return back()->with('error', 'Goal was created, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Company goal "' . $request->title . '" created.');
    }

    public function update(Request $request, string $company, string $goal)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'subcategory' => 'nullable|string|max:100',
            'owner_user_id' => 'nullable|uuid',
            'weightage' => 'nullable|numeric|min:0|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:draft,active,at_risk,completed,cancelled,archived',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $before = $supabase->first('company_goals', [
            'id' => 'eq.' . $goal,
            'company_id' => 'eq.' . $company,
            'select' => '*',
        ]);

        if (!$before) {
            abort(404, 'That goal does not belong to this company.');
        }

        if ($request->filled('weightage') && (float) $request->weightage !== (float) ($before['weightage'] ?? -1)) {
            $error = $this->weightageOverageError($supabase, $company, (float) $request->weightage, $goal);
            if ($error) {
                return back()->withInput()->with('error', $error);
            }
        }

        $after = [
            'title' => $request->title,
            'description' => $request->description,
            'category' => $request->category,
            'subcategory' => $request->subcategory,
            'owner_user_id' => $request->owner_user_id ?: null,
            'weightage' => $request->weightage,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status,
        ];

        try {
            $supabase->update('company_goals', ['id' => 'eq.' . $goal], $after);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not update company goal: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'update_company_goal', $company, null, [], 'company_goal', $goal, $before, $after);
        } catch (\Throwable) {
            return back()->with('error', 'Goal was updated, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Company goal "' . $request->title . '" updated.');
    }

    /**
     * Sums this company's other draft/active goal weightages and returns a
     * human-readable error if adding/changing one would push the total over
     * 100 — spec §13/§56: "do not silently accept invalid total weightage."
     * $excludeGoalId lets an update check against every goal EXCEPT the one
     * being edited (otherwise its own old weightage would double-count).
     */
    private function weightageOverageError(SupabaseUserService $supabase, string $company, float $incoming, ?string $excludeGoalId = null): ?string
    {
        $siblings = $supabase->get('company_goals', [
            'company_id' => 'eq.' . $company,
            'status' => 'in.(draft,active)',
            'select' => 'id,weightage',
        ]);

        $existingTotal = collect($siblings)
            ->when($excludeGoalId, fn ($c) => $c->reject(fn ($g) => $g['id'] === $excludeGoalId))
            ->sum(fn ($g) => (float) ($g['weightage'] ?? 0));

        $newTotal = $existingTotal + $incoming;

        if ($newTotal > 100) {
            return sprintf(
                'Total weightage across active company goals would be %s%% (exceeds 100%% by %s%%). Adjust another goal weightage first, or lower this one.',
                rtrim(rtrim(number_format($newTotal, 2), '0'), '.'),
                rtrim(rtrim(number_format($newTotal - 100, 2), '0'), '.'),
            );
        }

        return null;
    }
}
