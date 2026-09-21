<?php

namespace App\Http\Controllers;

use App\Services\AiService;
use App\Services\NotificationService;
use App\Services\SupabaseService;
use App\Services\TaskAccessPolicy;
use Illuminate\Http\Request;

/**
 * The web Mini App's "TTD" (to-do) list — a personal task tracker that is
 * deliberately NOT linked to KPI actuals (unlike the Telegram Mini App's
 * "Things To Do", which requires at least one linked KPI). Reuses the same
 * telegram_project_tasks / telegram_project_task_updates tables so nothing
 * new needed on the DB side; KPI linking here is optional, not mandatory.
 *
 * Every create/update/progress/delete pushes a notification via
 * NotificationService — which both logs an in-app notification row and, if
 * the employee has linked their Telegram account, pushes the same message
 * there. No bespoke Telegram-sending code needed for this.
 */
class MiniAppTaskController extends Controller
{
    private function nowMy(): string
    {
        return now()->timezone('Asia/Kuala_Lumpur')->toDateTimeString();
    }

    private function employeeName(): string
    {
        return session('employee.short_name') ?? 'You';
    }

    /**
     * TTD tasks live under a project row (DB requires project_id NOT NULL),
     * but the web UI has no "projects" concept — every task is filed under
     * one auto-created "My To-Do List" project per employee, transparently.
     */
    private function defaultProjectId(SupabaseService $supabase, string $employeeId, string $companyCode): string
    {
        $project = $supabase->first('telegram_projects', [
            'employee_id' => 'eq.' . $employeeId,
            'company_code' => 'eq.' . $companyCode,
            'name' => 'eq.My To-Do List',
            'select' => 'id',
        ]);

        if ($project) {
            return $project['id'];
        }

        $inserted = $supabase->insert('telegram_projects', [
            'employee_id' => $employeeId,
            'company_code' => $companyCode,
            'name' => 'My To-Do List',
        ]);

        return $inserted[0]['id'];
    }

    /*
    |--------------------------------------------------------------------------
    | GET /mini-app/api/tasks
    |--------------------------------------------------------------------------
    */
    public function index(Request $request, SupabaseService $supabase)
    {
        $employeeId = session('employee.id');
        $companyCode = session('employee.company_code');

        $tasks = $supabase->get('telegram_project_tasks', [
            'employee_id' => 'eq.' . $employeeId,
            'company_code' => 'eq.' . $companyCode,
            'select' => '*',
            'order' => 'created_at.desc',
        ]) ?? [];

        if (empty($tasks)) {
            return response()->json(['tasks' => []]);
        }

        $taskIds = array_column($tasks, 'id');
        $links = $supabase->get('telegram_project_task_kpi_links', [
            'task_id' => 'in.(' . implode(',', $taskIds) . ')',
            'select' => '*',
        ]) ?? [];

        $kpiIds = array_unique(array_column($links, 'kpi_id'));
        $kpis = empty($kpiIds) ? [] : ($supabase->get('kpis', [
            'id' => 'in.(' . implode(',', $kpiIds) . ')',
            'select' => 'id,kpi_title,unit,category',
        ]) ?? []);
        $kpiMap = collect($kpis)->keyBy('id');

        $linksByTask = collect($links)->groupBy('task_id');

        // Tasks created via the Telegram bot carry a real project name (users
        // pick/create projects there); web-created ones all sit under the
        // single auto-created "My To-Do List" — surfacing the name either way
        // so a unified task list reads correctly regardless of which channel
        // created the task.
        $projectIds = array_unique(array_filter(array_column($tasks, 'project_id')));
        $projects = empty($projectIds) ? [] : ($supabase->get('telegram_projects', [
            'id' => 'in.(' . implode(',', $projectIds) . ')',
            'select' => 'id,name',
        ]) ?? []);
        $projectMap = collect($projects)->keyBy('id');

        // Kanban cards show who a task is assigned to (per docs/performix-
        // design.md's "who assign" requirement) — resolve names once here
        // rather than per-card, same two-query-joined-in-PHP pattern used
        // elsewhere in this codebase instead of a Supabase embed.
        $assigneeIds = array_unique(array_filter(array_map(
            fn ($t) => $t['assignee_employee_id'] ?? $t['employee_id'] ?? null,
            $tasks
        )));
        $employees = empty($assigneeIds) ? [] : ($supabase->get('employees', [
            'id' => 'in.(' . implode(',', $assigneeIds) . ')',
            'select' => 'id,short_name',
        ]) ?? []);
        $employeeMap = collect($employees)->keyBy('id');

        $result = array_map(function ($task) use ($linksByTask, $kpiMap, $projectMap, $employeeMap) {
            $linkedKpis = $linksByTask->get($task['id'], collect())->map(function ($link) use ($kpiMap) {
                $kpi = $kpiMap->get($link['kpi_id']);
                return $kpi ? ['kpi_id' => $kpi['id'], 'kpi_title' => $kpi['kpi_title'], 'category' => $kpi['category'] ?? null] : null;
            })->filter()->values();

            return [
                'id' => $task['id'],
                'title' => $task['title'],
                'description' => $task['description'] ?? null,
                'project_name' => $projectMap->get($task['project_id'])['name'] ?? null,
                'unit' => $task['unit'],
                'target' => (float) $task['target'],
                'actual' => (float) $task['actual'],
                'progress_percentage' => (float) ($task['progress_percentage'] ?? 0),
                'status' => $task['status'],
                'priority' => $task['priority'] ?? 'medium',
                'task_type' => $task['task_type'] ?? null,
                'estimated_effort_hours' => isset($task['estimated_effort_hours']) ? (float) $task['estimated_effort_hours'] : null,
                'start_date' => $task['start_date'] ?? null,
                'due_date' => $task['due_date'] ?? null,
                'meeting_time' => $task['meeting_time'] ?? null,
                'reminder_at' => $task['reminder_at'] ?? null,
                'visibility' => $task['visibility'] ?? 'private',
                'recurrence_rule' => $task['recurrence_rule'] ?? 'none',
                'is_unplanned' => (bool) ($task['is_unplanned'] ?? false),
                'assignee_employee_id' => $task['assignee_employee_id'] ?? $task['employee_id'],
                'assignee_name' => $employeeMap->get($task['assignee_employee_id'] ?? $task['employee_id'])['short_name'] ?? null,
                'linked_kpis' => $linkedKpis,
            ];
        }, $tasks);

        return response()->json(['tasks' => $result]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /mini-app/api/tasks
    |--------------------------------------------------------------------------
    | KPI linking is optional here — a TTD task can exist purely as a
    | personal to-do, with no effect on any KPI's actual.
    */
    public function store(Request $request, SupabaseService $supabase, NotificationService $notifications, TaskAccessPolicy $policy)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'unit' => 'required|in:number,currency,percentage',
            'target' => 'required|numeric|min:0',
            'kpi_ids' => 'nullable|array',
            'kpi_ids.*' => 'string',
            'assignee_employee_id' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,critical',
            'task_type' => 'nullable|string|max:50',
            'estimated_effort_hours' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'meeting_time' => 'nullable|date_format:H:i',
            'reminder_at' => 'nullable|date',
            'visibility' => 'nullable|in:private,team,department',
            'recurrence_rule' => 'nullable|in:none,daily,weekdays,weekly,monthly',
            'is_unplanned' => 'nullable|boolean',
        ]);

        $employeeId = session('employee.id');
        $companyCode = session('employee.company_code');

        $assigneeId = $validated['assignee_employee_id'] ?? $employeeId;

        if ($assigneeId !== $employeeId && !$policy->canAssign(session('employee'), $assigneeId)) {
            return response()->json(['success' => false, 'message' => "You're not allowed to assign tasks to this person."], 403);
        }

        $kpiIds = array_unique($validated['kpi_ids'] ?? []);

        if (!empty($kpiIds)) {
            $kpis = $supabase->get('kpis', [
                'id' => 'in.(' . implode(',', $kpiIds) . ')',
                'employee_id' => 'eq.' . $employeeId,
                'company_code' => 'eq.' . $companyCode,
                'select' => 'id,unit',
            ]) ?? [];

            if (count($kpis) !== count($kpiIds)) {
                return response()->json(['success' => false, 'message' => 'One or more KPIs were not found.'], 404);
            }

            $mismatched = collect($kpis)->first(fn($k) => $k['unit'] !== $validated['unit']);
            if ($mismatched) {
                return response()->json(['success' => false, 'message' => "Unit mismatch — this task is in \"{$validated['unit']}\", but a selected KPI isn't."], 422);
            }
        }

        $projectId = $this->defaultProjectId($supabase, $employeeId, $companyCode);

        $inserted = $supabase->insert('telegram_project_tasks', [
            'project_id' => $projectId,
            'employee_id' => $employeeId,
            'assignee_employee_id' => $assigneeId,
            'company_code' => $companyCode,
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? null,
            'unit' => $validated['unit'],
            'target' => (float) $validated['target'],
            'priority' => $validated['priority'] ?? 'medium',
            'task_type' => $validated['task_type'] ?? null,
            'estimated_effort_hours' => $validated['estimated_effort_hours'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            // A meeting is a task with a specific time-of-day, not just a due
            // date — kept as a plain nullable column rather than a separate
            // "is_meeting" flag, matching the Platform Tasks feature's own
            // meeting_time column.
            'meeting_time' => $validated['meeting_time'] ?? null,
            'reminder_at' => $validated['reminder_at'] ?? null,
            'visibility' => $validated['visibility'] ?? 'private',
            'recurrence_rule' => $validated['recurrence_rule'] ?? 'none',
            'is_unplanned' => $validated['is_unplanned'] ?? false,
        ]);

        $task = $inserted[0] ?? null;

        if ($task && !empty($kpiIds)) {
            foreach ($kpiIds as $kpiId) {
                $supabase->insert('telegram_project_task_kpi_links', [
                    'task_id' => $task['id'],
                    'kpi_id' => $kpiId,
                ]);
            }
        }

        if ($task) {
            $notifications->notify(
                [$employeeId],
                'ttd_task_created',
                ['id' => $employeeId, 'name' => $this->employeeName()],
                'New to-do task created',
                "\"{$task['title']}\" — target " . (float) $validated['target'] . ' ' . $validated['unit'],
                route('mini-app')
            );
        }

        return response()->json(['task' => $task]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /mini-app/api/tasks/{id}
    |--------------------------------------------------------------------------
    | Task Details screen: the task itself, its linked KPIs, and its full
    | update history (telegram_project_task_updates) — the "what happened,
    | and when" audit trail behind every status/progress change.
    */
    public function show(Request $request, SupabaseService $supabase, string $id)
    {
        $employeeId = session('employee.id');

        $task = $supabase->first('telegram_project_tasks', [
            'id' => 'eq.' . $id,
            'or' => '(employee_id.eq.' . $employeeId . ',assignee_employee_id.eq.' . $employeeId . ')',
            'select' => '*',
        ]);

        if (empty($task)) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }

        $links = $supabase->get('telegram_project_task_kpi_links', [
            'task_id' => 'eq.' . $id,
            'select' => '*',
        ]) ?? [];

        $kpiIds = array_column($links, 'kpi_id');
        $kpis = empty($kpiIds) ? [] : ($supabase->get('kpis', [
            'id' => 'in.(' . implode(',', $kpiIds) . ')',
            'select' => 'id,kpi_title,category',
        ]) ?? []);
        $kpiMap = collect($kpis)->keyBy('id');

        $linkedKpis = collect($links)->map(function ($link) use ($kpiMap) {
            $kpi = $kpiMap->get($link['kpi_id']);
            return $kpi ? [
                'kpi_id' => $kpi['id'],
                'kpi_title' => $kpi['kpi_title'],
                'category' => $kpi['category'] ?? null,
                'ai_suggested' => (bool) ($link['ai_suggested'] ?? false),
                'ai_confidence' => $link['ai_confidence'] ?? null,
                'ai_reason' => $link['ai_reason'] ?? null,
            ] : null;
        })->filter()->values();

        $updates = $supabase->get('telegram_project_task_updates', [
            'task_id' => 'eq.' . $id,
            'select' => '*',
            'order' => 'created_at.desc',
        ]) ?? [];

        return response()->json([
            'task' => [
                'id' => $task['id'],
                'title' => $task['title'],
                'description' => $task['description'] ?? null,
                'unit' => $task['unit'],
                'target' => (float) $task['target'],
                'actual' => (float) $task['actual'],
                'progress_percentage' => (float) ($task['progress_percentage'] ?? 0),
                'status' => $task['status'],
                'priority' => $task['priority'] ?? 'medium',
                'due_date' => $task['due_date'] ?? null,
                'start_date' => $task['start_date'] ?? null,
                'assignee_employee_id' => $task['assignee_employee_id'] ?? $task['employee_id'],
                'linked_kpis' => $linkedKpis,
            ],
            'updates' => array_map(fn ($u) => [
                'delta' => (float) $u['delta'],
                'new_actual' => (float) $u['new_actual'],
                'status_at_update' => $u['status_at_update'] ?? null,
                'progress_at_update' => isset($u['progress_at_update']) ? (float) $u['progress_at_update'] : null,
                'note' => $u['note'] ?? null,
                'reschedule_reason' => $u['reschedule_reason'] ?? null,
                'created_at' => $u['created_at'],
            ], $updates),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /mini-app/api/tasks/assignable
    |--------------------------------------------------------------------------
    | Employees the caller may assign a task to, for the "Assign To" field
    | on the task detail update form. Just TaskAccessPolicy's own visibility
    | set (an EXECUTIVE only ever sees themselves) -- no separate rule here.
    */
    public function assignableEmployees(Request $request, SupabaseService $supabase, TaskAccessPolicy $policy)
    {
        $employeeIds = $policy->visibleEmployeeIds(session('employee') ?? []);

        if (empty($employeeIds)) {
            return response()->json(['employees' => []]);
        }

        $employees = $supabase->get('employees', [
            'id' => 'in.(' . implode(',', $employeeIds) . ')',
            'select' => 'id,short_name',
        ]) ?? [];

        return response()->json(['employees' => $employees]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /mini-app/api/tasks/kpi-options
    |--------------------------------------------------------------------------
    | KPIs eligible to link a task to: same employee/company, matching unit,
    | currently has an open quarter — same eligibility rule as the Telegram
    | Mini App's kpiOptions() (TelegramProjectTaskController).
    */
    public function kpiOptions(Request $request, SupabaseService $supabase, \App\Services\KpiQuarterUpdateService $quarterService)
    {
        $validated = $request->validate([
            'unit' => 'required|in:number,currency,percentage',
        ]);

        $employeeId = session('employee.id');
        $companyCode = session('employee.company_code');
        $fy = 'FY' . now('Asia/Kuala_Lumpur')->year;
        $today = now('Asia/Kuala_Lumpur')->toDateString();

        $kpis = $supabase->get('kpis', [
            'employee_id' => 'eq.' . $employeeId,
            'company_code' => 'eq.' . $companyCode,
            'financial_year' => 'eq.' . $fy,
            'unit' => 'eq.' . $validated['unit'],
            'select' => '*',
        ]) ?? [];

        $options = [];
        foreach ($kpis as $kpi) {
            if ($quarterService->findOpenQuarter($kpi['id'], $today, $fy)) {
                $options[] = [
                    'kpi_id' => $kpi['id'],
                    'kpi_title' => $kpi['kpi_title'],
                    'category' => $kpi['category'] ?? null,
                ];
            }
        }

        return response()->json(['kpis' => $options]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /mini-app/api/tasks/{id}/kpi-suggestion
    |--------------------------------------------------------------------------
    | AI-suggests the best-fitting KPI (docs/performix-design.md §3.6) but
    | never writes the link itself — the user must confirm via linkKpis().
    */
    public function kpiSuggestion(Request $request, SupabaseService $supabase, AiService $ai, string $id)
    {
        $employeeId = session('employee.id');
        $companyCode = session('employee.company_code');

        $task = $supabase->first('telegram_project_tasks', [
            'id' => 'eq.' . $id,
            'employee_id' => 'eq.' . $employeeId,
            'select' => 'id,title,description,unit',
        ]);

        if (empty($task)) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }

        $fy = 'FY' . now('Asia/Kuala_Lumpur')->year;
        $kpis = $supabase->get('kpis', [
            'employee_id' => 'eq.' . $employeeId,
            'company_code' => 'eq.' . $companyCode,
            'financial_year' => 'eq.' . $fy,
            'unit' => 'eq.' . $task['unit'],
            'select' => 'id,kpi_title,category',
        ]) ?? [];

        $employeeKpis = array_map(fn ($k) => ['kpi_id' => $k['id'], 'kpi_title' => $k['kpi_title'], 'category' => $k['category'] ?? null], $kpis);

        $suggestion = $ai->suggestTaskKpiLink($task['title'], $task['description'] ?? null, $employeeKpis);

        return response()->json(['suggestion' => $suggestion]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /mini-app/api/tasks/{id}/link-kpis
    |--------------------------------------------------------------------------
    | Replaces the set of KPIs this task feeds into — same replace-all
    | semantics as the Telegram Mini App's linkKpis(). Unlike the Telegram
    | surface, an empty kpi_ids array is allowed here ("Not linked to KPI"
    | per docs/performix-design.md §3.6), matching this controller's
    | already-optional KPI linking on create.
    */
    public function linkKpis(Request $request, SupabaseService $supabase, string $id)
    {
        $validated = $request->validate([
            'kpi_ids' => 'nullable|array',
            'kpi_ids.*' => 'string',
            'ai_suggested' => 'nullable|boolean',
            'ai_confidence' => 'nullable|numeric|min:0|max:100',
            'ai_reason' => 'nullable|string|max:500',
        ]);

        $employeeId = session('employee.id');
        $companyCode = session('employee.company_code');

        $task = $supabase->first('telegram_project_tasks', [
            'id' => 'eq.' . $id,
            'employee_id' => 'eq.' . $employeeId,
            'select' => '*',
        ]);

        if (empty($task)) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }

        $kpiIds = array_unique($validated['kpi_ids'] ?? []);

        if (!empty($kpiIds)) {
            $kpis = $supabase->get('kpis', [
                'id' => 'in.(' . implode(',', $kpiIds) . ')',
                'employee_id' => 'eq.' . $employeeId,
                'company_code' => 'eq.' . $companyCode,
                'select' => 'id,unit',
            ]) ?? [];

            if (count($kpis) !== count($kpiIds)) {
                return response()->json(['success' => false, 'message' => 'One or more KPIs were not found.'], 404);
            }

            $mismatched = collect($kpis)->first(fn($k) => $k['unit'] !== $task['unit']);
            if ($mismatched) {
                return response()->json(['success' => false, 'message' => "Unit mismatch — this task is in \"{$task['unit']}\", but a selected KPI isn't."], 422);
            }
        }

        $supabase->delete('telegram_project_task_kpi_links', ['task_id' => 'eq.' . $id]);

        foreach ($kpiIds as $kpiId) {
            $supabase->insert('telegram_project_task_kpi_links', [
                'task_id' => $id,
                'kpi_id' => $kpiId,
                'ai_suggested' => $validated['ai_suggested'] ?? false,
                'ai_confidence' => $validated['ai_confidence'] ?? null,
                'ai_reason' => $validated['ai_reason'] ?? null,
                'confirmed_by_user' => true,
            ]);
        }

        return response()->json(['success' => true, 'linked_count' => count($kpiIds)]);
    }

    /*
    |--------------------------------------------------------------------------
    | PATCH /mini-app/api/tasks/{id}
    |--------------------------------------------------------------------------
    | Edits the task's own details (title/target/unit) — the Telegram
    | controller never had this, it only ever adjusted progress.
    */
    public function update(Request $request, SupabaseService $supabase, NotificationService $notifications, TaskAccessPolicy $policy, string $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'unit' => 'required|in:number,currency,percentage',
            'target' => 'required|numeric|min:0',
            'priority' => 'nullable|in:low,medium,high,critical',
            'task_type' => 'nullable|string|max:50',
            'estimated_effort_hours' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'meeting_time' => 'nullable|date_format:H:i',
            'reminder_at' => 'nullable|date',
            'visibility' => 'nullable|in:private,team,department',
            'recurrence_rule' => 'nullable|in:none,daily,weekdays,weekly,monthly',
            'assignee_employee_id' => 'nullable|string',
        ]);

        $employeeId = session('employee.id');

        $task = $supabase->first('telegram_project_tasks', [
            'id' => 'eq.' . $id,
            'employee_id' => 'eq.' . $employeeId,
            'select' => '*',
        ]);

        if (empty($task)) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }

        $currentAssignee = $task['assignee_employee_id'] ?? $task['employee_id'];
        $newAssignee = $validated['assignee_employee_id'] ?? null;
        if ($newAssignee && $newAssignee !== $currentAssignee) {
            if (!$policy->canAssign(session('employee') ?? [], $newAssignee)) {
                return response()->json(['success' => false, 'message' => 'You cannot assign this task to that employee.'], 403);
            }
        }

        $supabase->safePatch('telegram_project_tasks', ['id' => 'eq.' . $id], [
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? $task['description'] ?? null,
            'unit' => $validated['unit'],
            'target' => (float) $validated['target'],
            'priority' => $validated['priority'] ?? $task['priority'] ?? 'medium',
            'task_type' => $validated['task_type'] ?? $task['task_type'] ?? null,
            'estimated_effort_hours' => $validated['estimated_effort_hours'] ?? $task['estimated_effort_hours'] ?? null,
            'start_date' => $validated['start_date'] ?? $task['start_date'] ?? null,
            'due_date' => $validated['due_date'] ?? $task['due_date'] ?? null,
            'meeting_time' => $validated['meeting_time'] ?? null,
            'reminder_at' => $validated['reminder_at'] ?? $task['reminder_at'] ?? null,
            'visibility' => $validated['visibility'] ?? $task['visibility'] ?? 'private',
            'recurrence_rule' => $validated['recurrence_rule'] ?? $task['recurrence_rule'] ?? 'none',
            'assignee_employee_id' => $newAssignee ?: $currentAssignee,
            'status' => (float) $task['actual'] >= (float) $validated['target'] && (float) $validated['target'] > 0 ? 'done' : $task['status'],
            'updated_at' => $this->nowMy(),
        ]);

        $notifications->notify(
            [$employeeId],
            'ttd_task_updated',
            ['id' => $employeeId, 'name' => $this->employeeName()],
            'To-do task updated',
            "\"{$validated['title']}\" details were updated.",
            route('mini-app')
        );

        return response()->json(['success' => true]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /mini-app/api/tasks/{id}/progress
    |--------------------------------------------------------------------------
    | Adds $delta to the task's own actual ONLY — does not touch any linked
    | KPI's quarter_actual, matching the Telegram version's documented
    | behavior. Logged to telegram_project_task_updates for history.
    */
    public function progress(Request $request, SupabaseService $supabase, NotificationService $notifications, string $id)
    {
        $validated = $request->validate([
            'delta' => 'required|numeric',
        ]);

        if ((float) $validated['delta'] === 0.0) {
            return response()->json(['success' => false, 'message' => 'Enter a non-zero amount.'], 422);
        }

        $employeeId = session('employee.id');

        $task = $supabase->first('telegram_project_tasks', [
            'id' => 'eq.' . $id,
            'employee_id' => 'eq.' . $employeeId,
            'select' => '*',
        ]);

        if (empty($task)) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }

        $delta = (float) $validated['delta'];
        $liveActual = (float) ($task['actual'] ?? 0);
        $newActual = $liveActual + $delta;

        if ($newActual < 0) {
            return response()->json([
                'success' => false,
                'message' => "Can't reduce — this task's actual is only {$liveActual}.",
            ], 422);
        }

        $supabase->safePatch('telegram_project_tasks', ['id' => 'eq.' . $id], [
            'actual' => $newActual,
            'status' => $newActual >= (float) $task['target'] && (float) $task['target'] > 0 ? 'done' : 'in_progress',
            'updated_at' => $this->nowMy(),
        ]);

        $supabase->safeInsert('telegram_project_task_updates', [
            'task_id' => $id,
            'delta' => $delta,
            'new_actual' => $newActual,
        ]);

        $notifications->notify(
            [$employeeId],
            'ttd_task_progress',
            ['id' => $employeeId, 'name' => $this->employeeName()],
            'To-do progress logged',
            "\"{$task['title']}\": " . ($delta >= 0 ? '+' : '') . $delta . " added → now {$newActual}.",
            route('mini-app')
        );

        return response()->json(['success' => true, 'task_actual' => $newActual]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /mini-app/api/tasks/{id}/daily-update
    |--------------------------------------------------------------------------
    | The single consolidated "Save Update" action on the task detail screen:
    | status, a free-text remark, and (optionally) reassigning the task.
    | `progress` is sent by the frontend already computed from actual/target
    | -- there is no separate manual input for it anymore (it was a second,
    | redundant source of truth alongside the auto-calculated percentage
    | shown at the top of the page). Deliberately separate from progress()
    | — this never touches actual/target itself, only lifecycle state, the
    | assignee, and the audit trail in telegram_project_task_updates.
    */
    public function dailyUpdate(Request $request, SupabaseService $supabase, NotificationService $notifications, TaskAccessPolicy $policy, string $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:not_started,in_progress,done,blocked,cancelled',
            'progress' => 'nullable|numeric|min:0|max:100',
            'note' => 'nullable|string|max:1000',
            'assignee_employee_id' => 'nullable|string',
        ]);

        $employeeId = session('employee.id');

        $task = $supabase->first('telegram_project_tasks', [
            'id' => 'eq.' . $id,
            'or' => '(employee_id.eq.' . $employeeId . ',assignee_employee_id.eq.' . $employeeId . ')',
            'select' => '*',
        ]);

        if (empty($task)) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }

        $patch = [
            'status' => $validated['status'],
            'progress_percentage' => $validated['progress'] ?? $task['progress_percentage'] ?? 0,
            'updated_at' => $this->nowMy(),
        ];

        $newAssignee = $validated['assignee_employee_id'] ?? null;
        $currentAssignee = $task['assignee_employee_id'] ?? $task['employee_id'];
        if ($newAssignee && $newAssignee !== $currentAssignee) {
            if (!$policy->canAssign(session('employee') ?? [], $newAssignee)) {
                return response()->json(['success' => false, 'message' => 'You cannot assign this task to that employee.'], 403);
            }
            $patch['assignee_employee_id'] = $newAssignee;
        }

        $supabase->safePatch('telegram_project_tasks', ['id' => 'eq.' . $id], $patch);

        $supabase->safeInsert('telegram_project_task_updates', [
            'task_id' => $id,
            'delta' => 0,
            'new_actual' => (float) ($task['actual'] ?? 0),
            'updated_by_employee_id' => $employeeId,
            'status_at_update' => $validated['status'],
            'progress_at_update' => $validated['progress'] ?? null,
            'note' => $validated['note'] ?? null,
            'channel' => 'web',
        ]);

        $notifyIds = array_unique(array_filter([$employeeId, $patch['assignee_employee_id'] ?? null]));
        $notifications->notify(
            $notifyIds,
            'ttd_task_daily_update',
            ['id' => $employeeId, 'name' => $this->employeeName()],
            'Daily update logged',
            "\"{$task['title']}\" marked as {$validated['status']}.",
            route('mini-app')
        );

        return response()->json(['success' => true]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE /mini-app/api/tasks/{id}
    |--------------------------------------------------------------------------
    | Hard delete — telegram_project_task_kpi_links and
    | telegram_project_task_updates both cascade on task_id (see
    | database/telegram_projects.sql / telegram_project_task_updates.sql),
    | so this is the only row that needs deleting directly.
    */
    public function destroy(Request $request, SupabaseService $supabase, NotificationService $notifications, string $id)
    {
        $employeeId = session('employee.id');

        $task = $supabase->first('telegram_project_tasks', [
            'id' => 'eq.' . $id,
            'employee_id' => 'eq.' . $employeeId,
            'select' => 'id,title',
        ]);

        if (empty($task)) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }

        $deleted = $supabase->safeDelete('telegram_project_tasks', ['id' => 'eq.' . $id]);

        if (!$deleted) {
            return response()->json(['success' => false, 'message' => 'Could not delete task.'], 500);
        }

        $notifications->notify(
            [$employeeId],
            'ttd_task_deleted',
            ['id' => $employeeId, 'name' => $this->employeeName()],
            'To-do task deleted',
            "\"{$task['title']}\" was deleted.",
            route('mini-app')
        );

        return response()->json(['success' => true]);
    }
}
