<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\AiService;
use App\Services\PlatformTaskScoreCalculator;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Tasks, scoped to one company at a time, optionally linked to one or more
 * KPIs for visibility/alignment only — a task's KPI links never touch a
 * KPI's actual value. Any active company member may create/view tasks (this
 * is a day-to-day productivity tool, not a KPI definition); editing/deleting
 * is restricted to the task's creator, its assignee, or a company admin —
 * enforced here for a clean redirect, and in `tasks_update`/`tasks_delete`
 * for the real guarantee.
 */
class TaskController extends Controller
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

        $tasks = $supabase->get('tasks', [
            'company_id' => 'eq.' . $company,
            'select' => '*',
            'order' => 'created_at.desc',
        ]);

        // Two follow-up `in.()` queries joined in PHP, matching
        // AuditLogController's own resolution of two separate FKs into
        // `users` — deliberately not PostgREST's FK-constraint-name
        // embed-disambiguation syntax, which needs the exact generated
        // constraint name verified against a live database to get right.
        $userIds = collect($tasks)
            ->flatMap(fn ($t) => [$t['assignee_user_id'] ?? null, $t['created_by'] ?? null])
            ->filter()
            ->unique()
            ->values();

        $users = $userIds->isEmpty()
            ? collect()
            : collect($supabase->get('users', [
                'id' => 'in.(' . $userIds->implode(',') . ')',
                'select' => 'id,name,email',
            ]))->keyBy('id');

        $tasks = collect($tasks)->map(fn ($t) => [
            ...$t,
            'assignee' => $t['assignee_user_id'] ? $users->get($t['assignee_user_id']) : null,
            'creator' => $t['created_by'] ? $users->get($t['created_by']) : null,
        ])->values()->all();

        $taskIds = array_column($tasks, 'id');

        $links = empty($taskIds)
            ? []
            : $supabase->get('task_kpi_links', [
                'task_id' => 'in.(' . implode(',', $taskIds) . ')',
                'select' => 'id,task_id,kpi_id,kpis(name)',
            ]);

        $kpis = $supabase->get('kpis', [
            'company_id' => 'eq.' . $company,
            'select' => 'id,name',
            'order' => 'name.asc',
        ]);

        $members = $supabase->get('company_users', [
            'company_id' => 'eq.' . $company,
            'status' => 'eq.active',
            'select' => 'user_id,users!company_users_user_id_foreign(name,email)',
        ]);

        $callerId = $request->attributes->get('platformUser')['id'];
        $myTasks = collect($tasks)->where('assignee_user_id', $callerId)->values()->all();
        $weekStart = now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = now()->endOfWeek(Carbon::SUNDAY);
        $taskScore = (new PlatformTaskScoreCalculator())->calculate($myTasks, $weekStart, $weekEnd);

        return Inertia::render('Platform/Tasks/Index', [
            'company' => $companyRow,
            'tasks' => $tasks,
            'links' => $links,
            'kpis' => $kpis,
            'members' => $members,
            'taskScore' => $taskScore,
        ]);
    }

    public function store(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:open,in_progress,blocked,done,cancelled',
            'priority' => 'nullable|in:low,medium,high',
            'due_date' => 'nullable|date',
            // Accepts BOTH shapes this field is ever actually submitted in:
            // a fresh <input type="time"> sends `H:i` (no seconds), but
            // Postgres's `time` column always comes back from PostgREST as
            // `H:i:s` -- and every write that round-trips a task's own
            // current value (drag-and-drop's moveTask(), which resends the
            // whole row unchanged except `status`) sends exactly that. A
            // single `date_format:H:i` silently rejected the `H:i:s` shape
            // on every request that included it -- this is the actual
            // reason dragging (or re-saving) a task that already had a
            // meeting time set never worked, confirmed live: PostgREST
            // really does return "12:00:00" for a task whose meeting was
            // set via this same form.
            'meeting_time' => 'nullable|date_format:H:i,H:i:s',
            'assignee_user_id' => 'nullable|uuid',
            'kpi_ids' => 'nullable|array',
            'kpi_ids.*' => 'uuid',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $callerId = $request->attributes->get('platformUser')['id'];

        try {
            $task = $supabase->insert('tasks', [
                'company_id' => $company,
                'title' => $request->title,
                'description' => $request->description,
                'status' => $request->input('status', 'open'),
                'priority' => $request->input('priority', 'medium'),
                'due_date' => $request->due_date ?: null,
                // A meeting is a task with a specific time-of-day, not just a
                // due date — kept as a plain nullable column rather than a
                // separate "is_meeting" flag, since "has a time" already
                // says exactly that with nothing else to fall out of sync.
                'meeting_time' => $request->meeting_time ?: null,
                'assignee_user_id' => $request->assignee_user_id ?: null,
                'created_by' => $callerId,
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not create task: ' . $e->getMessage());
        }

        $taskId = $task[0]['id'] ?? null;
        $kpiIds = $request->input('kpi_ids', []);

        if ($taskId && !empty($kpiIds)) {
            try {
                foreach ($kpiIds as $kpiId) {
                    $supabase->insert('task_kpi_links', [
                        'task_id' => $taskId,
                        'kpi_id' => $kpiId,
                        'linked_by' => $callerId,
                    ], false);
                }
            } catch (\Throwable $e) {
                return back()->with('error', 'Task was created, but linking it to the selected KPI(s) failed: ' . $e->getMessage());
            }
        }

        try {
            $this->logCompanyAction($request, 'create_task', $company, $request->assignee_user_id, [
                'kpi_ids' => $kpiIds,
            ], 'task', $taskId, null, [
                'title' => $request->title,
                'status' => $request->input('status', 'open'),
                'assignee_user_id' => $request->assignee_user_id ?: null,
            ]);
        } catch (\Throwable) {
            return back()->with('error', 'Task was created, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Task created.');
    }

    public function update(Request $request, string $company, string $task)
    {
        $this->ensureCompanyMember($request, $company);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:open,in_progress,blocked,done,cancelled',
            'priority' => 'required|in:low,medium,high',
            'due_date' => 'nullable|date',
            // See store()'s matching field for why both formats are needed.
            'meeting_time' => 'nullable|date_format:H:i,H:i:s',
            'assignee_user_id' => 'nullable|uuid',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $before = $supabase->first('tasks', [
            'id' => 'eq.' . $task,
            'company_id' => 'eq.' . $company,
            'select' => 'id,title,description,status,priority,due_date,meeting_time,assignee_user_id,created_by',
        ]);

        if (!$before) {
            abort(404, 'That task does not belong to this company.');
        }

        // The docblock above has always claimed this check exists; it never
        // did — `tasks_update`'s RLS policy (admin/creator/assignee) was the
        // only real gate, and PostgREST silently returns success with ZERO
        // rows affected when RLS filters every row a caller tries to touch.
        // Net effect: anyone who could SEE a task they didn't create or
        // aren't assigned to (any SLT/admin viewing the company-wide board)
        // could drag its card to another column, get a "success" response,
        // and watch it silently revert on reload with no explanation at all
        // — this is that missing check, matching the RLS boundary exactly.
        $meId = $request->attributes->get('platformUser')['id'];
        $isAuthorized = $this->canAdministerCompany($request, $company)
            || $before['created_by'] === $meId
            || $before['assignee_user_id'] === $meId;

        // A flash-message redirect, not abort_unless() — matches every other
        // "declined action" in this codebase (WeightageController,
        // ApprovalController, etc.) and renders through PlatformLayout's
        // existing flash.error banner on the very next Inertia visit, rather
        // than a raw HTTP error Inertia would otherwise show as a jarring
        // full-page modal.
        if (!$isAuthorized) {
            return back()->with('error', 'You can only edit or move a task you created or are assigned to.');
        }

        $after = [
            'title' => $request->title,
            'description' => $request->description,
            'status' => $request->status,
            'priority' => $request->priority,
            'due_date' => $request->due_date ?: null,
            'meeting_time' => $request->meeting_time ?: null,
            'assignee_user_id' => $request->assignee_user_id ?: null,
            'updated_at' => now()->toIso8601String(),
        ];

        try {
            $supabase->update('tasks', ['id' => 'eq.' . $task], $after, false);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not update task: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'update_task', $company, $request->assignee_user_id, [], 'task', $task, $before, $after);
        } catch (\Throwable) {
            return back()->with('error', 'Task was updated, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Task updated.');
    }

    public function destroy(Request $request, string $company, string $task)
    {
        $this->ensureCompanyMember($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $before = $supabase->first('tasks', [
            'id' => 'eq.' . $task,
            'company_id' => 'eq.' . $company,
            'select' => 'id,title,status,assignee_user_id,created_by',
        ]);

        if (!$before) {
            abort(404, 'That task does not belong to this company.');
        }

        // Matches `tasks_delete`'s RLS policy exactly (admin or creator —
        // narrower than update's admin/creator/assignee, since deleting
        // isn't something an assignee alone should be able to do to a task
        // someone else created for them). Same silent-no-op bug as
        // update()'s missing check above.
        $meId = $request->attributes->get('platformUser')['id'];
        $isAuthorized = $this->canAdministerCompany($request, $company) || $before['created_by'] === $meId;

        if (!$isAuthorized) {
            return back()->with('error', 'You can only delete a task you created.');
        }

        try {
            $supabase->delete('tasks', ['id' => 'eq.' . $task, 'company_id' => 'eq.' . $company]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not delete task: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'delete_task', $company, $before['assignee_user_id'] ?? null, [], 'task', $task, $before, null);
        } catch (\Throwable) {
            return back()->with('error', 'Task was deleted, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Task deleted.');
    }

    /**
     * Replace-all semantics for a task's KPI links, mirroring the legacy
     * Telegram Mini App's `linkKpis` behavior: the full set of checked KPIs
     * is sent every time, so the simplest correct implementation is delete
     * everything for this task, then re-insert the given set.
     */
    public function updateKpiLinks(Request $request, string $company, string $task)
    {
        $this->ensureCompanyMember($request, $company);

        $request->validate([
            'kpi_ids' => 'present|array',
            'kpi_ids.*' => 'uuid',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $callerId = $request->attributes->get('platformUser')['id'];

        $taskRow = $supabase->first('tasks', [
            'id' => 'eq.' . $task,
            'company_id' => 'eq.' . $company,
            'select' => 'id',
        ]);

        if (!$taskRow) {
            abort(404, 'That task does not belong to this company.');
        }

        try {
            $supabase->delete('task_kpi_links', ['task_id' => 'eq.' . $task]);

            foreach ($request->input('kpi_ids', []) as $kpiId) {
                $supabase->insert('task_kpi_links', [
                    'task_id' => $task,
                    'kpi_id' => $kpiId,
                    'linked_by' => $callerId,
                ], false);
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not update KPI links: ' . $e->getMessage());
        }

        $this->logBestEffort($request, 'link_task_kpis', $company, null, [
            'kpi_ids' => $request->input('kpi_ids', []),
        ], 'task', $task);

        return back()->with('success', 'KPI links updated.');
    }

    /**
     * On-demand AI narrative of the caller's own current weekly task score —
     * the Platform equivalent of the legacy Telegram Mini App's "AI Summary"
     * button (PerformixInsightsController::regenerate(), scope=employee).
     * Deliberately not persisted anywhere (that legacy feature keeps a full
     * ai_summaries history table keyed by the legacy employee/company_code
     * schema, which has no Platform equivalent) — a fresh call every time
     * the button is pressed, same "recompute fresh for one person" choice
     * PerformixInsightsController itself already documents for a single
     * employee's own score.
     */
    public function aiSummary(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $caller = $request->attributes->get('platformUser');

        $myTasks = $supabase->get('tasks', [
            'company_id' => 'eq.' . $company,
            'assignee_user_id' => 'eq.' . $caller['id'],
            'select' => 'status,priority,due_date,created_at,updated_at',
        ]) ?? [];

        $weekStart = now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = now()->endOfWeek(Carbon::SUNDAY);
        $taskScore = (new PlatformTaskScoreCalculator())->calculate($myTasks, $weekStart, $weekEnd);

        $today = now()->toDateString();
        $inScope = collect($myTasks)->reject(fn ($t) => ($t['status'] ?? null) === 'cancelled');

        $facts = [
            'score' => $taskScore['score'],
            'status' => $taskScore['status'],
            'scored_task_count' => $inScope->count(),
            'completed_count' => $inScope->where('status', 'done')->count(),
            'overdue_count' => $inScope->filter(fn ($t) => !empty($t['due_date']) && $t['due_date'] < $today && $t['status'] !== 'done')->count(),
            'blocked_count' => $inScope->where('status', 'blocked')->count(),
            'on_time_pct' => $taskScore['breakdown']['on_time'] ?? null,
            'update_consistency_pct' => null,
        ];

        $periodLabel = $weekStart->toDateString() . ' to ' . $weekEnd->toDateString();

        try {
            $result = app(AiService::class)->generateTaskSummary($caller['name'] ?? 'You', 'employee', 'weekly', $periodLabel, $facts);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => "Couldn't generate a summary right now."], 502);
        }

        $this->logBestEffort($request, 'generate_task_ai_summary', $company, null, [
            'score' => $taskScore['score'],
        ], 'task_ai_summary', null);

        return response()->json([
            'success' => true,
            'narrative' => $result['narrative'],
            'recommendations' => $result['recommendations'] ?? [],
        ]);
    }
}
