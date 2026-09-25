<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
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
            'select' => 'user_id,users(name,email)',
        ]);

        return Inertia::render('Platform/Tasks/Index', [
            'company' => $companyRow,
            'tasks' => $tasks,
            'links' => $links,
            'kpis' => $kpis,
            'members' => $members,
        ]);
    }

    public function store(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:open,in_progress,done,cancelled',
            'priority' => 'nullable|in:low,medium,high',
            'due_date' => 'nullable|date',
            'meeting_time' => 'nullable|date_format:H:i',
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
            'status' => 'required|in:open,in_progress,done,cancelled',
            'priority' => 'required|in:low,medium,high',
            'due_date' => 'nullable|date',
            'meeting_time' => 'nullable|date_format:H:i',
            'assignee_user_id' => 'nullable|uuid',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $before = $supabase->first('tasks', [
            'id' => 'eq.' . $task,
            'company_id' => 'eq.' . $company,
            'select' => 'id,title,description,status,priority,due_date,meeting_time,assignee_user_id',
        ]);

        if (!$before) {
            abort(404, 'That task does not belong to this company.');
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
            'select' => 'id,title,status,assignee_user_id',
        ]);

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
}
