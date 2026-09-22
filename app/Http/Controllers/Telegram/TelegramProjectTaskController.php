<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Telegram\Concerns\ResolvesTelegramEmployee;
use App\Services\AiService;
use App\Services\KpiQuarterUpdateService;
use App\Services\NotificationService;
use App\Services\SupabaseService;
use App\Services\TaskAccessPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class TelegramProjectTaskController extends Controller
{
    use ResolvesTelegramEmployee;

    private function todayMy(): string
    {
        return now('Asia/Kuala_Lumpur')->toDateString();
    }

    private function nowMy(): string
    {
        return now()->timezone('Asia/Kuala_Lumpur')->toDateTimeString();
    }

    /**
     * Same rule as the web Mini App's MiniAppTaskController::
     * verifyNotifyEmployee() — a task's optional notify_employee_id is only
     * accepted if it's someone the caller can actually see (the exact same
     * TaskAccessPolicy scope the "Notify via Telegram" dropdown is built
     * from), never an arbitrary employee id the caller was never shown.
     *
     * @return array{ok: bool, employee_id: ?string, message?: string}
     */
    private function verifyNotifyEmployee(?string $employeeId, array $actor, TaskAccessPolicy $policy): array
    {
        if (empty($employeeId)) {
            return ['ok' => true, 'employee_id' => null];
        }

        if (!$policy->canView($actor, $employeeId)) {
            return [
                'ok' => false,
                'employee_id' => null,
                'message' => "That's not someone you can notify — pick someone from the list.",
            ];
        }

        return ['ok' => true, 'employee_id' => $employeeId];
    }

    private function notifyMessageFor(array $task, string $actorName): string
    {
        $lines = [];

        if (!empty($task['description'])) {
            $lines[] = $task['description'];
        }

        $lines[] = 'Priority: ' . ucfirst($task['priority'] ?? 'medium');

        if (!empty($task['due_date'])) {
            $due = Carbon::parse($task['due_date'])->format('j M Y');
            if (!empty($task['due_time'])) {
                $due .= ' at ' . Carbon::parse($task['due_time'])->format('g:i A');
            }
            $lines[] = 'Due: ' . $due;
        }

        $lines[] = 'Assigned by: ' . $actorName;

        return implode("\n", $lines);
    }

    /**
     * Same pipeline as the web Mini App — NotificationService::notify() ->
     * sendTelegram(), never a bespoke sender. Returns whether the recipient
     * actually has Telegram linked, so the save response can say "notified"
     * vs. "saved, but they haven't linked Telegram yet" instead of claiming
     * success either way.
     */
    private function notifyAndDescribe(string $employeeId, array $task, string $actorId, string $actorName, SupabaseService $supabase, NotificationService $notifications): string
    {
        $linked = (bool) $notifications->telegramChatIdFor($employeeId);

        $notifications->notify(
            [$employeeId],
            'ttd_task_notify',
            ['id' => $actorId, 'name' => $actorName],
            $task['title'],
            $this->notifyMessageFor($task, $actorName),
            null
        );

        $recipient = $supabase->first('employees', ['id' => 'eq.' . $employeeId, 'select' => 'short_name']);
        $name = $recipient['short_name'] ?? 'They';

        return $linked
            ? "Notified {$name} via Telegram."
            : "{$name} hasn't linked Telegram yet, so they weren't notified.";
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/telegram/projects
    |--------------------------------------------------------------------------
    */
    public function listProjects(Request $request, SupabaseService $supabase)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'company_code' => 'required|string',
        ]);

        $this->resolveContext($request, $supabase, $validated['employee_id'], $validated['company_code']);

        $projects = $supabase->get('telegram_projects', [
            'employee_id' => 'eq.' . $validated['employee_id'],
            'company_code' => 'eq.' . $validated['company_code'],
            'select' => '*',
            'order' => 'created_at.desc',
        ]) ?? [];

        return response()->json(['projects' => $projects]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/telegram/projects
    |--------------------------------------------------------------------------
    */
    public function createProject(Request $request, SupabaseService $supabase)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'company_code' => 'required|string',
            'name' => 'required|string|max:120',
        ]);

        $this->resolveContext($request, $supabase, $validated['employee_id'], $validated['company_code']);

        $inserted = $supabase->insert('telegram_projects', [
            'employee_id' => $validated['employee_id'],
            'company_code' => $validated['company_code'],
            'name' => trim($validated['name']),
        ]);

        return response()->json(['project' => $inserted[0] ?? null]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/telegram/project-tasks
    |--------------------------------------------------------------------------
    | Lists tasks for the employee, each with its project name and linked
    | KPIs — the "collect tasks with their project" hub screen.
    */
    public function listTasks(Request $request, SupabaseService $supabase)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'company_code' => 'required|string',
            'project_id' => 'nullable|string',
        ]);

        $this->resolveContext($request, $supabase, $validated['employee_id'], $validated['company_code']);

        $filters = [
            'employee_id' => 'eq.' . $validated['employee_id'],
            'company_code' => 'eq.' . $validated['company_code'],
            'select' => '*',
            'order' => 'created_at.desc',
        ];

        if (!empty($validated['project_id'])) {
            $filters['project_id'] = 'eq.' . $validated['project_id'];
        }

        $tasks = $supabase->get('telegram_project_tasks', $filters) ?? [];

        if (empty($tasks)) {
            return response()->json(['tasks' => []]);
        }

        $projectIds = array_unique(array_column($tasks, 'project_id'));
        $projects = $supabase->get('telegram_projects', [
            'id' => 'in.(' . implode(',', $projectIds) . ')',
            'select' => 'id,name',
        ]) ?? [];
        $projectMap = collect($projects)->keyBy('id');

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

        $employeeIds = array_unique(array_filter(array_merge(
            array_map(fn ($t) => $t['assignee_employee_id'] ?? $t['employee_id'] ?? null, $tasks),
            array_column($tasks, 'notify_employee_id')
        )));
        $employeeMap = empty($employeeIds) ? collect() : collect($supabase->get('employees', [
            'id' => 'in.(' . implode(',', $employeeIds) . ')',
            'select' => 'id,short_name',
        ]) ?? [])->keyBy('id');

        $result = array_map(function ($task) use ($projectMap, $linksByTask, $kpiMap, $employeeMap) {
            $linkedKpis = $linksByTask->get($task['id'], collect())->map(function ($link) use ($kpiMap) {
                $kpi = $kpiMap->get($link['kpi_id']);
                return $kpi ? ['kpi_id' => $kpi['id'], 'kpi_title' => $kpi['kpi_title'], 'category' => $kpi['category'] ?? null] : null;
            })->filter()->values();

            $assigneeId = $task['assignee_employee_id'] ?? $task['employee_id'];

            return [
                'id' => $task['id'],
                'project_id' => $task['project_id'],
                'project_name' => $projectMap->get($task['project_id'])['name'] ?? 'Untitled Project',
                'title' => $task['title'],
                'description' => $task['description'] ?? null,
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
                'due_time' => $task['due_time'] ?? null,
                'reminder_at' => $task['reminder_at'] ?? null,
                'visibility' => $task['visibility'] ?? 'private',
                'recurrence_rule' => $task['recurrence_rule'] ?? 'none',
                'is_unplanned' => (bool) ($task['is_unplanned'] ?? false),
                'assignee_employee_id' => $assigneeId,
                'assignee_name' => $employeeMap->get($assigneeId)['short_name'] ?? null,
                'notify_employee_id' => $task['notify_employee_id'] ?? null,
                'notify_employee_name' => $employeeMap->get($task['notify_employee_id'] ?? null)['short_name'] ?? null,
                'linked_kpis' => $linkedKpis,
            ];
        }, $tasks);

        return response()->json(['tasks' => $result]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/telegram/project-tasks/assignable-employees
    |--------------------------------------------------------------------------
    | Same rule and shape as the web Mini App's MiniAppTaskController::
    | assignableEmployees() — every employee this caller is allowed to see
    | (TaskAccessPolicy::visibleEmployeeIds()), ordered alphabetically via
    | Supabase's own ORDER BY (never a PHP-side re-sort), each flagged with
    | has_telegram so "Notify via Telegram" can filter to only people who
    | can actually receive one.
    */
    public function assignableEmployees(Request $request, SupabaseService $supabase, TaskAccessPolicy $policy)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'company_code' => 'required|string',
        ]);

        $this->resolveContext($request, $supabase, $validated['employee_id'], $validated['company_code']);

        $actorEmployee = $supabase->first('employees', [
            'id' => 'eq.' . $validated['employee_id'],
            'select' => '*',
        ]);

        if (empty($actorEmployee)) {
            return response()->json(['employees' => []]);
        }

        $employeeIds = $policy->visibleEmployeeIds($actorEmployee);

        if (empty($employeeIds)) {
            return response()->json(['employees' => []]);
        }

        $employees = $supabase->get('employees', [
            'id' => 'in.(' . implode(',', $employeeIds) . ')',
            'select' => 'id,short_name',
            'order' => 'short_name.asc',
        ]) ?? [];

        $userIdByEmployeeId = collect();
        $chatIdByUserId = collect();
        try {
            $roles = $supabase->get('user_company_roles', [
                'employee_id' => 'in.(' . implode(',', array_column($employees, 'id')) . ')',
                'is_active' => 'eq.true',
                'select' => 'employee_id,user_id',
            ]) ?? [];
            $userIdByEmployeeId = collect($roles)->pluck('user_id', 'employee_id');
            $userIds = array_values(array_unique(array_filter($userIdByEmployeeId->all())));
            $chatIdByUserId = empty($userIds) ? collect() : collect($supabase->get('users', [
                'id' => 'in.(' . implode(',', $userIds) . ')',
                'select' => 'id,telegram_chat_id',
            ]) ?? [])->pluck('telegram_chat_id', 'id');
        } catch (\Throwable $e) {
            Log::warning('Could not resolve Telegram linkage for assignable employees (Telegram Mini App)', ['error' => $e->getMessage()]);
        }

        $employees = array_map(function ($employee) use ($userIdByEmployeeId, $chatIdByUserId) {
            $userId = $userIdByEmployeeId->get($employee['id']);
            $employee['has_telegram'] = $userId ? !empty($chatIdByUserId->get($userId)) : false;
            return $employee;
        }, $employees);

        return response()->json(['employees' => $employees]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/telegram/project-tasks
    |--------------------------------------------------------------------------
    */
    public function createTask(Request $request, SupabaseService $supabase, TaskAccessPolicy $policy, NotificationService $notifications)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'company_code' => 'required|string',
            'project_id' => 'required|string',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'unit' => 'required|in:number,currency,percentage',
            'target' => 'required|numeric|min:0',
            // KPI linking is optional here (docs/performix-design.md's
            // "Allow 'Not linked to KPI'"), matching the web Mini App's
            // MiniAppTaskController::store() — the original mandatory rule
            // was specific to the old multi-step creation wizard.
            'kpi_ids' => 'nullable|array',
            'kpi_ids.*' => 'string',
            'assignee_employee_id' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,critical',
            'task_type' => 'nullable|string|max:50',
            'estimated_effort_hours' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|date_format:H:i',
            'notify_employee_id' => 'nullable|string',
            'reminder_at' => 'nullable|date',
            'visibility' => 'nullable|in:private,team,department',
            'recurrence_rule' => 'nullable|in:none,daily,weekdays,weekly,monthly',
            'is_unplanned' => 'nullable|boolean',
        ]);

        $this->resolveContext($request, $supabase, $validated['employee_id'], $validated['company_code']);

        $project = $supabase->first('telegram_projects', [
            'id' => 'eq.' . $validated['project_id'],
            'employee_id' => 'eq.' . $validated['employee_id'],
            'select' => 'id',
        ]);

        if (empty($project)) {
            return response()->json(['success' => false, 'message' => 'Project not found.'], 404);
        }

        $actorEmployee = $supabase->first('employees', [
            'id' => 'eq.' . $validated['employee_id'],
            'select' => '*',
        ]);

        if (empty($actorEmployee)) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 404);
        }

        $assigneeId = $validated['assignee_employee_id'] ?? $validated['employee_id'];

        if ($assigneeId !== $validated['employee_id'] && !$policy->canAssign($actorEmployee, $assigneeId)) {
            return response()->json(['success' => false, 'message' => "You're not allowed to assign tasks to this person."], 403);
        }

        $notifyCheck = $this->verifyNotifyEmployee($validated['notify_employee_id'] ?? null, $actorEmployee, $policy);
        if (!$notifyCheck['ok']) {
            return response()->json(['success' => false, 'message' => $notifyCheck['message']], 422);
        }

        $kpiIds = array_unique($validated['kpi_ids'] ?? []);

        if (!empty($kpiIds)) {
            $kpis = $supabase->get('kpis', [
                'id' => 'in.(' . implode(',', $kpiIds) . ')',
                'employee_id' => 'eq.' . $validated['employee_id'],
                'company_code' => 'eq.' . $validated['company_code'],
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

        $inserted = $supabase->insert('telegram_project_tasks', [
            'project_id' => $validated['project_id'],
            'employee_id' => $validated['employee_id'],
            'assignee_employee_id' => $assigneeId,
            'company_code' => $validated['company_code'],
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? null,
            'unit' => $validated['unit'],
            'target' => (float) $validated['target'],
            'priority' => $validated['priority'] ?? 'medium',
            'task_type' => $validated['task_type'] ?? null,
            'estimated_effort_hours' => $validated['estimated_effort_hours'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'due_time' => $validated['due_time'] ?? null,
            'notify_employee_id' => $notifyCheck['employee_id'],
            'reminder_at' => $validated['reminder_at'] ?? null,
            'visibility' => $validated['visibility'] ?? 'private',
            'recurrence_rule' => $validated['recurrence_rule'] ?? 'none',
            'is_unplanned' => $validated['is_unplanned'] ?? false,
        ]);

        $task = $inserted[0] ?? null;

        if ($task) {
            foreach ($kpiIds as $kpiId) {
                $supabase->insert('telegram_project_task_kpi_links', [
                    'task_id' => $task['id'],
                    'kpi_id' => $kpiId,
                ]);
            }
        }

        $message = 'Task saved!';

        if ($task) {
            $notifications->notify(
                array_unique(array_filter([$validated['employee_id'], $assigneeId])),
                'ttd_task_created',
                ['id' => $validated['employee_id'], 'name' => $actorEmployee['short_name'] ?? 'You'],
                'New task created',
                "\"{$task['title']}\" was created.",
                null
            );

            if ($notifyCheck['employee_id']) {
                $message .= ' ' . $this->notifyAndDescribe($notifyCheck['employee_id'], $task, $validated['employee_id'], $actorEmployee['short_name'] ?? 'You', $supabase, $notifications);
            }
        }

        return response()->json(['task' => $task, 'message' => $message]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/telegram/project-tasks/kpi-options
    |--------------------------------------------------------------------------
    | KPIs eligible to link a task to: same employee/company, matching unit,
    | and currently has an open quarter (otherwise an update couldn't land
    | anywhere right now).
    */
    public function kpiOptions(Request $request, SupabaseService $supabase, KpiQuarterUpdateService $quarterService)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'company_code' => 'required|string',
            // Optional: when omitted, every open KPI is returned regardless
            // of unit — the simplified task-creation flow no longer asks
            // for a unit/target up front, so there's nothing to match
            // against (docs/performix-design.md's "Not linked to KPI" is
            // the categorical-alignment case; this is the same idea for
            // the eligible-KPI list itself).
            'unit' => 'nullable|in:number,currency,percentage',
        ]);

        $this->resolveContext($request, $supabase, $validated['employee_id'], $validated['company_code']);

        $options = $this->openKpiOptions($supabase, $quarterService, $validated['employee_id'], $validated['company_code'], $validated['unit'] ?? null);

        return response()->json(['kpis' => $options]);
    }

    /**
     * Every one of this employee's KPIs that currently has an open quarter
     * — optionally filtered to a specific unit. Shared by kpiOptions() (the
     * task-edit KPI picker) and suggestKpiForDraft() (the AI suggestion on
     * the create-task screen, before the task exists).
     */
    private function openKpiOptions(SupabaseService $supabase, KpiQuarterUpdateService $quarterService, string $employeeId, string $companyCode, ?string $unit = null): array
    {
        $fy = 'FY' . now('Asia/Kuala_Lumpur')->year;
        $today = $this->todayMy();

        $filters = [
            'employee_id' => 'eq.' . $employeeId,
            'company_code' => 'eq.' . $companyCode,
            'financial_year' => 'eq.' . $fy,
            'select' => '*',
        ];

        if ($unit) {
            $filters['unit'] = 'eq.' . $unit;
        }

        $kpis = $supabase->get('kpis', $filters) ?? [];

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

        return $options;
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/telegram/project-tasks/kpi-suggestion-draft
    |--------------------------------------------------------------------------
    | Same AI suggestion as kpiSuggestion(), but for a task that doesn't
    | exist yet — the create-task screen calls this as the user types,
    | before there's a task id to attach the link to.
    */
    public function suggestKpiForDraft(Request $request, SupabaseService $supabase, KpiQuarterUpdateService $quarterService, AiService $ai)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'company_code' => 'required|string',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
        ]);

        $this->resolveContext($request, $supabase, $validated['employee_id'], $validated['company_code']);

        $employeeKpis = $this->openKpiOptions($supabase, $quarterService, $validated['employee_id'], $validated['company_code']);

        $suggestion = $ai->suggestTaskKpiLink($validated['title'], $validated['description'] ?? null, $employeeKpis);

        return response()->json(['suggestion' => $suggestion]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/telegram/project-tasks/{id}/link-kpis
    |--------------------------------------------------------------------------
    | Replaces the set of KPIs this task feeds into. Every kpi_id must belong
    | to the same employee/company and share the task's unit. An empty
    | array is allowed — "Not linked to KPI" per docs/performix-design.md.
    */
    public function linkKpis(Request $request, SupabaseService $supabase, string $id)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'company_code' => 'required|string',
            'kpi_ids' => 'nullable|array',
            'kpi_ids.*' => 'string',
            'ai_suggested' => 'nullable|boolean',
            'ai_confidence' => 'nullable|numeric|min:0|max:100',
            'ai_reason' => 'nullable|string|max:500',
        ]);

        $this->resolveContext($request, $supabase, $validated['employee_id'], $validated['company_code']);

        $task = $supabase->first('telegram_project_tasks', [
            'id' => 'eq.' . $id,
            'employee_id' => 'eq.' . $validated['employee_id'],
            'select' => '*',
        ]);

        if (empty($task)) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }

        $kpiIds = array_unique($validated['kpi_ids'] ?? []);

        if (!empty($kpiIds)) {
            $kpis = $supabase->get('kpis', [
                'id' => 'in.(' . implode(',', $kpiIds) . ')',
                'employee_id' => 'eq.' . $validated['employee_id'],
                'company_code' => 'eq.' . $validated['company_code'],
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
    | POST /api/telegram/project-tasks/{id}/kpi-suggestion
    |--------------------------------------------------------------------------
    | AI-suggests the best-fitting KPI (docs/performix-design.md §3.6) but
    | never writes the link itself — the user must confirm via linkKpis(),
    | same contract as the web Mini App's kpiSuggestion().
    */
    public function kpiSuggestion(Request $request, SupabaseService $supabase, AiService $ai, string $id)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'company_code' => 'required|string',
        ]);

        $this->resolveContext($request, $supabase, $validated['employee_id'], $validated['company_code']);

        $task = $supabase->first('telegram_project_tasks', [
            'id' => 'eq.' . $id,
            'employee_id' => 'eq.' . $validated['employee_id'],
            'select' => 'id,title,description,unit',
        ]);

        if (empty($task)) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }

        $fy = 'FY' . now('Asia/Kuala_Lumpur')->year;
        $kpis = $supabase->get('kpis', [
            'employee_id' => 'eq.' . $validated['employee_id'],
            'company_code' => 'eq.' . $validated['company_code'],
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
    | PATCH /api/telegram/project-tasks/{id}
    |--------------------------------------------------------------------------
    | Full task-details edit — same fields and scoping as the web Mini App's
    | MiniAppTaskController::update().
    */
    public function update(Request $request, SupabaseService $supabase, TaskAccessPolicy $policy, NotificationService $notifications, string $id)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'company_code' => 'required|string',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'unit' => 'required|in:number,currency,percentage',
            'target' => 'required|numeric|min:0',
            'priority' => 'nullable|in:low,medium,high,critical',
            'task_type' => 'nullable|string|max:50',
            'estimated_effort_hours' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|date_format:H:i',
            'assignee_employee_id' => 'nullable|string',
            'notify_employee_id' => 'nullable|string',
            'reminder_at' => 'nullable|date',
            'visibility' => 'nullable|in:private,team,department',
            'recurrence_rule' => 'nullable|in:none,daily,weekdays,weekly,monthly',
        ]);

        $this->resolveContext($request, $supabase, $validated['employee_id'], $validated['company_code']);

        $task = $supabase->first('telegram_project_tasks', [
            'id' => 'eq.' . $id,
            'employee_id' => 'eq.' . $validated['employee_id'],
            'select' => '*',
        ]);

        if (empty($task)) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }

        $actorEmployee = $supabase->first('employees', [
            'id' => 'eq.' . $validated['employee_id'],
            'select' => '*',
        ]);

        if (empty($actorEmployee)) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 404);
        }

        $currentAssignee = $task['assignee_employee_id'] ?? $task['employee_id'];
        $newAssignee = $validated['assignee_employee_id'] ?? null;
        if ($newAssignee && $newAssignee !== $currentAssignee && !$policy->canAssign($actorEmployee, $newAssignee)) {
            return response()->json(['success' => false, 'message' => 'You cannot assign this task to that employee.'], 403);
        }

        $notifyCheck = $this->verifyNotifyEmployee($validated['notify_employee_id'] ?? null, $actorEmployee, $policy);
        if (!$notifyCheck['ok']) {
            return response()->json(['success' => false, 'message' => $notifyCheck['message']], 422);
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
            'due_time' => $validated['due_time'] ?? null,
            'assignee_employee_id' => $newAssignee ?: $currentAssignee,
            'notify_employee_id' => $notifyCheck['employee_id'],
            'reminder_at' => $validated['reminder_at'] ?? $task['reminder_at'] ?? null,
            'visibility' => $validated['visibility'] ?? $task['visibility'] ?? 'private',
            'recurrence_rule' => $validated['recurrence_rule'] ?? $task['recurrence_rule'] ?? 'none',
            'status' => (float) $task['actual'] >= (float) $validated['target'] && (float) $validated['target'] > 0 ? 'done' : $task['status'],
            'updated_at' => $this->nowMy(),
        ]);

        $notifications->notify(
            array_unique(array_filter([$validated['employee_id'], $newAssignee ?: $currentAssignee])),
            'ttd_task_updated',
            ['id' => $validated['employee_id'], 'name' => $actorEmployee['short_name'] ?? 'You'],
            'Task updated',
            "\"{$validated['title']}\" details were updated.",
            null
        );

        $message = 'Changes saved!';

        // Only send when notify_employee_id is new or has actually changed —
        // it's always resent by the client on every save, so without this
        // check every unrelated edit would re-notify the same person.
        if ($notifyCheck['employee_id'] && $notifyCheck['employee_id'] !== ($task['notify_employee_id'] ?? null)) {
            $message .= ' ' . $this->notifyAndDescribe($notifyCheck['employee_id'], [
                'title' => trim($validated['title']),
                'description' => $validated['description'] ?? $task['description'] ?? null,
                'due_date' => $validated['due_date'] ?? $task['due_date'] ?? null,
                'due_time' => $validated['due_time'] ?? null,
                'priority' => $validated['priority'] ?? $task['priority'] ?? 'medium',
            ], $validated['employee_id'], $actorEmployee['short_name'] ?? 'You', $supabase, $notifications);
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE /api/telegram/project-tasks/{id}
    |--------------------------------------------------------------------------
    | Hard delete — telegram_project_task_kpi_links and
    | telegram_project_task_updates both cascade on task_id, so this is the
    | only row that needs deleting directly. Same contract as the web Mini
    | App's MiniAppTaskController::destroy().
    */
    public function destroy(Request $request, SupabaseService $supabase, NotificationService $notifications, string $id)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'company_code' => 'required|string',
        ]);

        $this->resolveContext($request, $supabase, $validated['employee_id'], $validated['company_code']);

        $task = $supabase->first('telegram_project_tasks', [
            'id' => 'eq.' . $id,
            'employee_id' => 'eq.' . $validated['employee_id'],
            'select' => 'id,title,assignee_employee_id',
        ]);

        if (empty($task)) {
            return response()->json(['success' => false, 'message' => 'Task not found.'], 404);
        }

        $deleted = $supabase->safeDelete('telegram_project_tasks', ['id' => 'eq.' . $id]);

        if (!$deleted) {
            return response()->json(['success' => false, 'message' => 'Could not delete task.'], 500);
        }

        $actorEmployee = $supabase->first('employees', ['id' => 'eq.' . $validated['employee_id'], 'select' => 'short_name']);

        $notifications->notify(
            array_unique(array_filter([$validated['employee_id'], $task['assignee_employee_id'] ?? null])),
            'ttd_task_deleted',
            ['id' => $validated['employee_id'], 'name' => $actorEmployee['short_name'] ?? 'You'],
            'Task deleted',
            "\"{$task['title']}\" was deleted.",
            null
        );

        return response()->json(['success' => true]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/telegram/project-tasks/{id}
    |--------------------------------------------------------------------------
    | Task Details screen: the task itself, its linked KPIs (with any AI
    | suggestion metadata), and its full update history — same shape as the
    | web Mini App's show().
    */
    public function show(Request $request, SupabaseService $supabase, string $id)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'company_code' => 'required|string',
        ]);

        $this->resolveContext($request, $supabase, $validated['employee_id'], $validated['company_code']);

        $task = $supabase->first('telegram_project_tasks', [
            'id' => 'eq.' . $id,
            'employee_id' => 'eq.' . $validated['employee_id'],
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

        $assigneeId = $task['assignee_employee_id'] ?? $task['employee_id'];
        $namedIds = array_unique(array_filter([$assigneeId, $task['notify_employee_id'] ?? null]));
        $employeeMap = empty($namedIds) ? collect() : collect($supabase->get('employees', [
            'id' => 'in.(' . implode(',', $namedIds) . ')',
            'select' => 'id,short_name',
        ]) ?? [])->keyBy('id');

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
                'due_time' => $task['due_time'] ?? null,
                'start_date' => $task['start_date'] ?? null,
                'assignee_employee_id' => $assigneeId,
                'assignee_name' => $employeeMap->get($assigneeId)['short_name'] ?? null,
                'notify_employee_id' => $task['notify_employee_id'] ?? null,
                'notify_employee_name' => $employeeMap->get($task['notify_employee_id'] ?? null)['short_name'] ?? null,
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
    | POST /api/telegram/project-tasks/{id}/progress
    |--------------------------------------------------------------------------
    | Adds $delta to the task's own actual ONLY — this does not touch any
    | linked KPI's quarter_actual (a KPI's official actual is only ever
    | changed via the "My KPIs" inline Update box / adjustQuarter). Each
    | update is also logged to telegram_project_task_updates so a KPI's
    | linked tasks can show a "what was updated, and when" history.
    */
    public function updateProgress(Request $request, SupabaseService $supabase, NotificationService $notifications, string $id)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'company_code' => 'required|string',
            'delta' => 'required|numeric',
        ]);

        if ((float) $validated['delta'] === 0.0) {
            return response()->json(['success' => false, 'message' => 'Enter a non-zero amount.'], 422);
        }

        $this->resolveContext($request, $supabase, $validated['employee_id'], $validated['company_code']);

        $task = $supabase->first('telegram_project_tasks', [
            'id' => 'eq.' . $id,
            'employee_id' => 'eq.' . $validated['employee_id'],
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

        $actorEmployee = $supabase->first('employees', ['id' => 'eq.' . $validated['employee_id'], 'select' => 'short_name']);

        $notifications->notify(
            array_unique(array_filter([$validated['employee_id'], $task['assignee_employee_id'] ?? null])),
            'ttd_task_progress',
            ['id' => $validated['employee_id'], 'name' => $actorEmployee['short_name'] ?? 'You'],
            'Task progress updated',
            "\"{$task['title']}\" progress was updated.",
            null
        );

        return response()->json([
            'success' => true,
            'task_actual' => $newActual,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/telegram/project-tasks/{id}/daily-update
    |--------------------------------------------------------------------------
    | The evening check-in flow (docs/performix-design.md §3.3): status +
    | progress percentage, a required note when blocked, and an optional
    | reschedule with reason. Deliberately separate from updateProgress() —
    | this never touches actual/target, only the task's lifecycle state and
    | the audit trail in telegram_project_task_updates.
    */
    public function dailyUpdate(Request $request, SupabaseService $supabase, NotificationService $notifications, string $id)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'company_code' => 'required|string',
            'status' => 'required|in:not_started,in_progress,done,blocked,cancelled',
            'progress' => 'nullable|numeric|min:0|max:100',
            'note' => 'required_if:status,blocked|nullable|string|max:1000',
            'reschedule_to' => 'nullable|date',
            'reschedule_reason' => 'required_with:reschedule_to|nullable|string|max:500',
        ]);

        $this->resolveContext($request, $supabase, $validated['employee_id'], $validated['company_code']);

        $task = $supabase->first('telegram_project_tasks', [
            'id' => 'eq.' . $id,
            'or' => '(employee_id.eq.' . $validated['employee_id'] . ',assignee_employee_id.eq.' . $validated['employee_id'] . ')',
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

        if (!empty($validated['reschedule_to'])) {
            $patch['due_date'] = $validated['reschedule_to'];
        }

        $supabase->safePatch('telegram_project_tasks', ['id' => 'eq.' . $id], $patch);

        $supabase->safeInsert('telegram_project_task_updates', [
            'task_id' => $id,
            'delta' => 0,
            'new_actual' => (float) ($task['actual'] ?? 0),
            'updated_by_employee_id' => $validated['employee_id'],
            'status_at_update' => $validated['status'],
            'progress_at_update' => $validated['progress'] ?? null,
            'note' => $validated['note'] ?? null,
            'reschedule_reason' => $validated['reschedule_reason'] ?? null,
            'channel' => 'telegram',
        ]);

        $actorEmployee = $supabase->first('employees', ['id' => 'eq.' . $validated['employee_id'], 'select' => 'short_name']);

        $notifications->notify(
            array_unique(array_filter([$validated['employee_id'], $task['assignee_employee_id'] ?? $task['employee_id'] ?? null])),
            'ttd_task_progress',
            ['id' => $validated['employee_id'], 'name' => $actorEmployee['short_name'] ?? 'You'],
            'Daily update logged',
            "\"{$task['title']}\" was marked " . str_replace('_', ' ', $validated['status']) . '.',
            null
        );

        return response()->json(['success' => true]);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/telegram/kpis/{kpiId}/task-history
    |--------------------------------------------------------------------------
    | Every task linked to this KPI, each with its timestamped update log —
    | "list what tasks are under that KPI, like a history."
    */
    public function kpiTaskHistory(Request $request, SupabaseService $supabase, string $kpiId)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string',
            'company_code' => 'required|string',
        ]);

        $this->resolveContext($request, $supabase, $validated['employee_id'], $validated['company_code']);

        $kpi = $supabase->first('kpis', [
            'id' => 'eq.' . $kpiId,
            'employee_id' => 'eq.' . $validated['employee_id'],
            'company_code' => 'eq.' . $validated['company_code'],
            'select' => 'id,kpi_title',
        ]);

        if (empty($kpi)) {
            return response()->json(['success' => false, 'message' => 'KPI not found.'], 404);
        }

        $links = $supabase->get('telegram_project_task_kpi_links', [
            'kpi_id' => 'eq.' . $kpiId,
            'select' => '*',
        ]) ?? [];

        if (empty($links)) {
            return response()->json(['kpi_title' => $kpi['kpi_title'], 'tasks' => []]);
        }

        $taskIds = array_column($links, 'task_id');
        $tasks = $supabase->get('telegram_project_tasks', [
            'id' => 'in.(' . implode(',', $taskIds) . ')',
            'select' => '*',
            'order' => 'created_at.desc',
        ]) ?? [];

        if (empty($tasks)) {
            return response()->json(['kpi_title' => $kpi['kpi_title'], 'tasks' => []]);
        }

        $projectIds = array_unique(array_column($tasks, 'project_id'));
        $projects = $supabase->get('telegram_projects', [
            'id' => 'in.(' . implode(',', $projectIds) . ')',
            'select' => 'id,name',
        ]) ?? [];
        $projectMap = collect($projects)->keyBy('id');

        $updates = $supabase->get('telegram_project_task_updates', [
            'task_id' => 'in.(' . implode(',', $taskIds) . ')',
            'select' => '*',
            'order' => 'created_at.desc',
        ]) ?? [];
        $updatesByTask = collect($updates)->groupBy('task_id');

        $result = array_map(function ($task) use ($projectMap, $updatesByTask) {
            return [
                'id' => $task['id'],
                'title' => $task['title'],
                'project_name' => $projectMap->get($task['project_id'])['name'] ?? 'Untitled Project',
                'unit' => $task['unit'],
                'target' => (float) $task['target'],
                'actual' => (float) $task['actual'],
                'status' => $task['status'],
                'updates' => $updatesByTask->get($task['id'], collect())->map(fn($u) => [
                    'delta' => (float) $u['delta'],
                    'new_actual' => (float) $u['new_actual'],
                    'created_at' => $u['created_at'],
                ])->values(),
            ];
        }, $tasks);

        return response()->json(['kpi_title' => $kpi['kpi_title'], 'tasks' => $result]);
    }
}
