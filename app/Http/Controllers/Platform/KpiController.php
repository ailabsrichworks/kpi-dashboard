<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\KpiCalculationService;
use App\Services\PerformancePeriodService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * KPI categories and KPI definitions, scoped to one company at a time.
 * Viewing is open to any member of the company (department users need to
 * know what KPIs apply to them before Phase 7 adds submissions); creating is
 * restricted to Company Admins — enforced twice, here for a clean redirect
 * and in `kpi_categories_insert`/`kpis_insert` for the real guarantee.
 */
class KpiController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function index(Request $request, string $company, KpiCalculationService $calc)
    {
        $this->ensureCompanyMember($request, $company);

        $this->logAdminAccessIfCrossCompany($request, 'view_kpis', $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', [
            'id' => 'eq.' . $company,
            'select' => 'id,name,code,financial_year_start_month',
        ]);

        $categories = $supabase->get('kpi_categories', [
            'company_id' => 'eq.' . $company,
            'select' => '*',
            'order' => 'name.asc',
        ]);

        $kpis = $supabase->get('kpis', [
            'company_id' => 'eq.' . $company,
            'select' => '*,kpi_categories(name)',
            'order' => 'created_at.desc',
        ]);

        $goals = $supabase->get('company_goals', [
            'company_id' => 'eq.' . $company,
            'status' => 'in.(draft,active,at_risk)',
            'select' => 'id,title',
            'order' => 'title.asc',
        ]);

        $kpiIds = array_column($kpis, 'id');

        // Only fetched for KPIs that could possibly have one — 'company'
        // visibility (the default) never checks this table at read time, so
        // there is nothing here to show for the common case.
        $grants = empty($kpiIds)
            ? []
            : $supabase->get('kpi_access_grants', [
                'kpi_id' => 'in.(' . implode(',', $kpiIds) . ')',
                'select' => 'id,kpi_id,user_id,department_id,users(name,email),departments(name)',
            ]);

        $departments = $supabase->get('departments', [
            'company_id' => 'eq.' . $company,
            'select' => 'id,name',
            'order' => 'name.asc',
        ]);

        $members = $supabase->get('company_users', [
            'company_id' => 'eq.' . $company,
            'status' => 'eq.active',
            'select' => 'user_id,users(name,email)',
        ]);

        $templates = $supabase->get('kpi_templates', [
            'status' => 'eq.active',
            'select' => 'id,name,description',
            'order' => 'name.asc',
        ]);

        $templateIds = array_column($templates, 'id');

        $templateItems = empty($templateIds)
            ? []
            : $supabase->get('kpi_template_items', [
                'template_id' => 'in.(' . implode(',', $templateIds) . ')',
                'select' => 'id,template_id,category_name,name',
            ]);

        $kpis = $this->attachComputedPerformance($supabase, $kpis, $companyRow, $calc);

        return Inertia::render('Platform/Kpis/Index', [
            'company' => $companyRow,
            'categories' => $categories,
            'kpis' => $kpis,
            'templates' => $templates,
            'templateItems' => $templateItems,
            'grants' => $grants,
            'goals' => $goals,
            'departments' => $departments,
            'members' => $members,
        ]);
    }

    /**
     * Attaches server-computed `computed_achievement`/`computed_status`/
     * `computed_expected_progress` (from each KPI's own most recent
     * submission) and, for KPIs with children, `rollup_achievement` (the
     * weighted roll-up of those children) — spec §11/§18: a single,
     * reusable calculation engine instead of per-page ad-hoc formulas.
     *
     * Bounded, not deeply recursive: rolls up exactly one level (children
     * into their direct parent). A KPI three cascade levels deep would need
     * its own children rolled up first — fine for the common
     * Company KPI <- Department KPI shape this pass targets, worth
     * revisiting if a real 4-level cascade shows up in practice.
     */
    private function attachComputedPerformance(SupabaseUserService $supabase, array $kpis, array $companyRow, KpiCalculationService $calc): array
    {
        if (empty($kpis)) {
            return $kpis;
        }

        $periods = new PerformancePeriodService((int) ($companyRow['financial_year_start_month'] ?? 1));
        $now = Carbon::now();
        $currentFy = $periods->financialYearFor($now);
        $currentQuarter = $periods->quarterFor($now);
        [$fyStart, $fyEnd] = $periods->financialYearBounds($currentFy);
        $elapsedFraction = $periods->periodElapsedFraction($fyStart, $fyEnd, $now);

        $kpiIds = array_column($kpis, 'id');

        // Latest submission per KPI this financial year — reduced in PHP
        // from one ordered query rather than one query per KPI.
        $submissions = $supabase->get('kpi_submissions', [
            'kpi_id' => 'in.(' . implode(',', $kpiIds) . ')',
            'submission_date' => 'gte.' . $fyStart->toDateString(),
            'select' => 'kpi_id,value,submission_date',
            'order' => 'submission_date.desc',
        ]);

        $latestValueByKpi = [];
        foreach ($submissions as $submission) {
            $latestValueByKpi[$submission['kpi_id']] ??= (float) $submission['value'];
        }

        // This FY's quarterly period targets, if any were set — used for a
        // precise "expected progress to date" instead of a linear estimate
        // when the company has actually split the target unevenly.
        $periodTargets = $supabase->get('kpi_period_targets', [
            'kpi_id' => 'in.(' . implode(',', $kpiIds) . ')',
            'financial_year' => 'eq.' . $currentFy,
            'period_type' => 'eq.quarter',
            'select' => 'kpi_id,period_number,target',
        ]);

        $quarterTargetsByKpi = [];
        foreach ($periodTargets as $pt) {
            $quarterTargetsByKpi[$pt['kpi_id']][(int) $pt['period_number']] = (float) $pt['target'];
        }

        $computed = [];

        foreach ($kpis as $kpi) {
            $target = $kpi['target'] !== null ? (float) $kpi['target'] : null;
            $stretch = $kpi['stretch_target'] !== null ? (float) $kpi['stretch_target'] : null;
            $direction = $kpi['measurement_direction'] ?? 'higher_is_better';
            $latest = $latestValueByKpi[$kpi['id']] ?? null;

            $achievement = $latest !== null ? $calc->achievement($latest, $target, $stretch, $direction) : null;

            $expectedProgress = null;
            if (isset($quarterTargetsByKpi[$kpi['id']]) && $target !== null && $target != 0) {
                $allocatedToDate = 0.0;
                for ($q = 1; $q <= $currentQuarter; $q++) {
                    $allocatedToDate += $quarterTargetsByKpi[$kpi['id']][$q] ?? 0.0;
                }
                $expectedProgress = min(100.0, ($allocatedToDate / $target) * 100);
            } elseif ($target !== null) {
                $expectedProgress = $calc->expectedProgressPct($elapsedFraction);
            }

            $status = $calc->status($achievement, $expectedProgress);

            $computed[$kpi['id']] = $kpi + [
                'computed_achievement' => $achievement,
                'computed_expected_progress' => $expectedProgress,
                'computed_status' => $status,
                'rollup_achievement' => null,
            ];
        }

        $childrenByParent = [];
        foreach ($computed as $kpi) {
            if ($kpi['parent_kpi_id']) {
                $childrenByParent[$kpi['parent_kpi_id']][] = $kpi;
            }
        }

        foreach ($childrenByParent as $parentId => $children) {
            if (!isset($computed[$parentId])) {
                continue;
            }

            $computed[$parentId]['rollup_achievement'] = $calc->weightedRollup(array_map(
                fn ($c) => ['achievement' => $c['computed_achievement'], 'weightage' => $c['weightage'] !== null ? (float) $c['weightage'] : null],
                $children,
            ));
        }

        return array_values($computed);
    }

    /**
     * Copy-on-apply (Blueprint §10): every KPI/category created here is an
     * independent row owned by this company, with no foreign key back to
     * the template it came from. Editing the template afterward can never
     * reshape a company that already applied it — the one property the
     * spec's "do not create a shared KPI record" requirement is protecting.
     *
     * Categories are deduplicated by name against what the company already
     * has (kpi_categories has a real unique(company_id, name) constraint) —
     * applying the same template twice, or a second template that happens to
     * share a category name, reuses the existing category rather than
     * erroring or duplicating it.
     */
    public function applyTemplate(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate(['template_id' => 'required|uuid']);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $items = $supabase->get('kpi_template_items', [
            'template_id' => 'eq.' . $request->template_id,
            'select' => '*',
        ]);

        if (empty($items)) {
            return back()->with('error', 'That template has no items to apply.');
        }

        $existingCategories = collect($supabase->get('kpi_categories', [
            'company_id' => 'eq.' . $company,
            'select' => 'id,name',
        ]))->keyBy('name');

        $neededCategoryNames = collect($items)->pluck('category_name')->filter()->unique();
        $newCategoryNames = $neededCategoryNames->diff($existingCategories->keys());

        try {
            if ($newCategoryNames->isNotEmpty()) {
                $insertedCategories = $supabase->insert('kpi_categories', $newCategoryNames
                    ->map(fn ($name) => ['company_id' => $company, 'name' => $name])
                    ->values()
                    ->all());

                foreach ($insertedCategories as $cat) {
                    $existingCategories->put($cat['name'], $cat);
                }
            }

            // return=minimal (3rd arg false) — see the note on store()'s own
            // kpis insert for why: `kpis_select` self-references `kpis` via
            // `auth_can_view_kpi()`, which breaks RETURNING for non-Super-Admin
            // callers. A Company Admin applying a template hits this exactly
            // like creating a KPI by hand does.
            $supabase->insert('kpis', collect($items)->map(fn ($item) => [
                'company_id' => $company,
                'category_id' => $item['category_name'] ? ($existingCategories[$item['category_name']]['id'] ?? null) : null,
                'name' => $item['name'],
                'description' => $item['description'],
                'target' => $item['target'],
                'unit' => $item['unit'],
                'frequency' => $item['frequency'],
            ])->all(), false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not apply template: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'apply_kpi_template', $company, null, ['template_id' => $request->template_id, 'items_added' => count($items)], 'kpi_template');
        } catch (\Throwable) {
            return back()->with('error', 'Template was applied, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', count($items) . ' KPI(s) added from template.');
    }

    public function storeCategory(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            $supabase->insert('kpi_categories', [
                'company_id' => $company,
                'name' => $request->name,
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not create category: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'create_kpi_category', $company, null, ['name' => $request->name], 'kpi_category');
        } catch (\Throwable) {
            return back()->with('error', 'Category was created, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Category "' . $request->name . '" created.');
    }

    public function store(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'category_id' => 'nullable|uuid',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'target' => 'nullable|numeric',
            'unit' => 'nullable|string|max:50',
            'frequency' => 'required|in:daily,weekly,monthly,quarterly,custom',
            'visibility' => 'nullable|in:company,department,restricted',
            'company_goal_id' => 'nullable|uuid',
            'parent_kpi_id' => 'nullable|uuid',
            'department_id' => 'nullable|uuid',
            'owner_user_id' => 'nullable|uuid',
            'measurement_unit' => 'nullable|in:number,currency,percentage,ratio,days,hours,score,binary,custom',
            'measurement_direction' => 'nullable|in:higher_is_better,lower_is_better,target_range,on_or_before,binary_completion',
            'stretch_target' => 'nullable|numeric',
            'weightage' => 'nullable|numeric|min:0|max:100',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        if ($request->filled('weightage')) {
            $error = $this->weightageOverageError($supabase, $company, (float) $request->weightage, $request->parent_kpi_id ?: null, $request->company_goal_id ?: null);
            if ($error) {
                return back()->withInput()->with('error', $error);
            }
        }

        try {
            // return=minimal (3rd arg false): `kpis_select`'s policy calls
            // `auth_can_view_kpi()`, which queries back into `kpis` for the
            // row being checked. Requesting the row back via `return=representation`
            // (the default) makes PostgREST evaluate that SELECT policy as
            // part of the INSERT's RETURNING clause — confirmed, for a
            // non-Super-Admin caller, to fail there even though the INSERT's
            // own WITH CHECK independently and correctly evaluates true. This
            // was a real bug: every KPI created by anyone other than a Super
            // Admin failed. Nothing here uses the returned row anyway.
            $supabase->insert('kpis', [
                'company_id' => $company,
                'category_id' => $request->category_id ?: null,
                'name' => $request->name,
                'description' => $request->description,
                'target' => $request->target,
                'unit' => $request->unit,
                'frequency' => $request->frequency,
                'visibility' => $request->input('visibility', 'company'),
                'company_goal_id' => $request->company_goal_id ?: null,
                'parent_kpi_id' => $request->parent_kpi_id ?: null,
                'department_id' => $request->department_id ?: null,
                'owner_user_id' => $request->owner_user_id ?: null,
                'measurement_unit' => $request->input('measurement_unit', 'number'),
                'measurement_direction' => $request->input('measurement_direction', 'higher_is_better'),
                'stretch_target' => $request->stretch_target,
                'weightage' => $request->weightage,
            ], false);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not create KPI: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'create_kpi', $company, null, [], 'kpi', null, null, [
                'name' => $request->name,
                'target' => $request->target,
                'unit' => $request->unit,
                'frequency' => $request->frequency,
                'visibility' => $request->input('visibility', 'company'),
            ]);
        } catch (\Throwable) {
            return back()->with('error', 'KPI was created, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'KPI "' . $request->name . '" created.');
    }

    /**
     * Sums the weightage of this KPI's siblings — other KPIs sharing the
     * same `parent_kpi_id` (Department/Individual KPI cascade level), or
     * failing that the same `company_goal_id` (top-level Company KPI), or
     * failing that unset entirely (no natural 100% bucket to check against,
     * so nothing is validated) — and rejects outright if the incoming
     * weightage would push the group's total over 100. Spec §15/§56: "do not
     * silently accept invalid total weightage."
     */
    private function weightageOverageError(SupabaseUserService $supabase, string $company, float $incoming, ?string $parentKpiId, ?string $companyGoalId, ?string $excludeKpiId = null): ?string
    {
        if (!$parentKpiId && !$companyGoalId) {
            return null;
        }

        $siblings = $parentKpiId
            ? $supabase->get('kpis', ['parent_kpi_id' => 'eq.' . $parentKpiId, 'select' => 'id,weightage'])
            : $supabase->get('kpis', ['company_goal_id' => 'eq.' . $companyGoalId, 'parent_kpi_id' => 'is.null', 'select' => 'id,weightage']);

        $existingTotal = collect($siblings)
            ->when($excludeKpiId, fn ($c) => $c->reject(fn ($k) => $k['id'] === $excludeKpiId))
            ->sum(fn ($k) => (float) ($k['weightage'] ?? 0));

        $newTotal = $existingTotal + $incoming;

        if ($newTotal > 100) {
            return sprintf(
                'Total weightage among these sibling KPIs would be %s%% (exceeds 100%% by %s%%). Adjust another KPI weightage first, or lower this one.',
                rtrim(rtrim(number_format($newTotal, 2), '0'), '.'),
                rtrim(rtrim(number_format($newTotal - 100, 2), '0'), '.'),
            );
        }

        return null;
    }

    /**
     * Edits an existing KPI's configuration — the piece "KPI configuration
     * changes" and "Target changes" (requirement #8) needed and never had:
     * `store()` could only create a KPI, never edit one afterward. Same
     * validation as `store()`, plus `return=minimal` (4th arg false on the
     * update call) for the identical reason `store()`'s insert needs it —
     * `kpis_select` self-references `kpis` via `auth_can_view_kpi()`, which
     * breaks the implicit RETURNING for a non-Super-Admin caller.
     *
     * Deletion is deliberately not added alongside this — CLAUDE.md's own
     * "Known limitation" already flags that no DELETE policy exists on
     * `kpis` at all, pending a real product decision (soft-delete via
     * `status` vs. a real delete policy) that this change isn't the place to
     * make unilaterally.
     */
    public function update(Request $request, string $company, string $kpi)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'category_id' => 'nullable|uuid',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'target' => 'nullable|numeric',
            'unit' => 'nullable|string|max:50',
            'frequency' => 'required|in:daily,weekly,monthly,quarterly,custom',
            'visibility' => 'nullable|in:company,department,restricted',
            'company_goal_id' => 'nullable|uuid',
            'parent_kpi_id' => 'nullable|uuid',
            'department_id' => 'nullable|uuid',
            'owner_user_id' => 'nullable|uuid',
            'measurement_unit' => 'nullable|in:number,currency,percentage,ratio,days,hours,score,binary,custom',
            'measurement_direction' => 'nullable|in:higher_is_better,lower_is_better,target_range,on_or_before,binary_completion',
            'stretch_target' => 'nullable|numeric',
            'weightage' => 'nullable|numeric|min:0|max:100',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $before = $supabase->first('kpis', [
            'id' => 'eq.' . $kpi,
            'company_id' => 'eq.' . $company,
            'select' => '*',
        ]);

        if (!$before) {
            abort(404, 'That KPI does not belong to this company.');
        }

        if ($kpi === $request->parent_kpi_id) {
            return back()->withInput()->with('error', 'A KPI cannot be its own parent.');
        }

        if ($request->filled('weightage') && (float) $request->weightage !== (float) ($before['weightage'] ?? -1)) {
            $error = $this->weightageOverageError(
                $supabase, $company, (float) $request->weightage,
                $request->parent_kpi_id ?: null, $request->company_goal_id ?: null, $kpi,
            );
            if ($error) {
                return back()->withInput()->with('error', $error);
            }
        }

        $after = [
            'category_id' => $request->category_id ?: null,
            'name' => $request->name,
            'description' => $request->description,
            'target' => $request->target,
            'unit' => $request->unit,
            'frequency' => $request->frequency,
            'visibility' => $request->input('visibility', $before['visibility']),
            'company_goal_id' => $request->company_goal_id ?: null,
            'parent_kpi_id' => $request->parent_kpi_id ?: null,
            'department_id' => $request->department_id ?: null,
            'owner_user_id' => $request->owner_user_id ?: null,
            'measurement_unit' => $request->input('measurement_unit', $before['measurement_unit'] ?? 'number'),
            'measurement_direction' => $request->input('measurement_direction', $before['measurement_direction'] ?? 'higher_is_better'),
            'stretch_target' => $request->stretch_target,
            'weightage' => $request->weightage,
        ];

        try {
            $supabase->update('kpis', ['id' => 'eq.' . $kpi], $after, false);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not update KPI: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'update_kpi', $company, null, [], 'kpi', $kpi, $before, $after);

            // "Target changes" (requirement #8) gets its own, separately
            // filterable action rather than being buried inside a general
            // update_kpi diff, since a target change is the one edit with
            // direct financial/performance-review consequences.
            if ((string) ($before['target'] ?? '') !== (string) ($after['target'] ?? '')) {
                $this->logCompanyAction($request, 'change_kpi_target', $company, null, [
                    'kpi_name' => $after['name'],
                ], 'kpi', $kpi, ['target' => $before['target']], ['target' => $after['target']]);
            }
        } catch (\Throwable) {
            return back()->with('error', 'KPI was updated, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'KPI "' . $request->name . '" updated.');
    }

    /**
     * Widens a 'department' or 'restricted' KPI's visibility to one extra
     * user or department. `trg_validate_kpi_grant_tenancy` (2026_08_17_110000)
     * is what actually rejects a cross-tenant grant — this only turns that
     * into a clean redirect instead of a raw Postgres error.
     */
    public function storeGrant(Request $request, string $company, string $kpi)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'user_id' => 'nullable|uuid',
            'department_id' => 'nullable|uuid',
        ]);

        if (($request->user_id && $request->department_id) || (!$request->user_id && !$request->department_id)) {
            return back()->with('error', 'Grant access to exactly one user or one department, not both or neither.');
        }

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            $grant = $supabase->insert('kpi_access_grants', [
                'company_id' => $company,
                'kpi_id' => $kpi,
                'user_id' => $request->user_id ?: null,
                'department_id' => $request->department_id ?: null,
                'granted_by' => $request->attributes->get('platformUser')['id'],
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not grant access: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'grant_kpi_access', $company, $request->user_id, [
                'department_id' => $request->department_id,
            ], 'kpi_access_grant', $grant[0]['id'] ?? null, null, [
                'kpi_id' => $kpi,
                'user_id' => $request->user_id ?: null,
                'department_id' => $request->department_id ?: null,
            ]);
        } catch (\Throwable) {
            return back()->with('error', 'Access was granted, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Access granted.');
    }

    public function destroyGrant(Request $request, string $company, string $kpi, string $grant)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $before = $supabase->first('kpi_access_grants', [
            'id' => 'eq.' . $grant,
            'select' => 'user_id,department_id',
        ]);

        try {
            $supabase->delete('kpi_access_grants', ['id' => 'eq.' . $grant, 'kpi_id' => 'eq.' . $kpi]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not revoke access: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'revoke_kpi_access', $company, $before['user_id'] ?? null, [], 'kpi_access_grant', $grant, $before, null);
        } catch (\Throwable) {
            return back()->with('error', 'Access was revoked, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Access revoked.');
    }
}
