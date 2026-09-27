<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Client Account Manager assignment and workload (spec §8's "Client
 * Success" section) — Center-only. `cam_assignments` is one active CAM per
 * company (`unique(company_id)`), so re-assigning is an upsert, not a
 * history table; if "who was the CAM before" ever matters, that's what
 * `admin_action_logs` already records for this exact write.
 */
class CamController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function index(Request $request)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companies = $supabase->get('companies', ['select' => 'id,name,code', 'order' => 'name.asc', 'limit' => 200]);
        $assignments = $supabase->get('cam_assignments', ['select' => '*,users(name,email)']);
        $users = $supabase->get('users', ['select' => 'id,name,email', 'order' => 'name.asc', 'limit' => 500]);

        $assignmentsByCompany = collect($assignments)->keyBy('company_id');
        $companiesByCam = collect($assignments)->groupBy('cam_user_id');

        $roster = $companiesByCam->map(function ($rows, $camUserId) {
            $first = $rows->first();
            return [
                'cam_user_id' => $camUserId,
                'cam_name' => $first['users']['name'] ?? '—',
                'account_count' => $rows->count(),
            ];
        })->values();

        $companyRows = collect($companies)->map(fn ($c) => $c + ['cam' => $assignmentsByCompany->get($c['id'])])->values();

        return Inertia::render('Platform/Hq/Cam/Index', [
            'companies' => $companyRows,
            'roster' => $roster,
            'users' => $users,
        ]);
    }

    public function assign(Request $request, string $company)
    {
        $this->ensureSuperAdmin($request);

        $request->validate(['cam_user_id' => 'required|uuid']);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $existing = $supabase->first('cam_assignments', ['company_id' => 'eq.' . $company, 'select' => 'id']);
        $me = $request->attributes->get('platformUser')['id'] ?? null;

        try {
            if ($existing) {
                $supabase->update('cam_assignments', ['id' => 'eq.' . $existing['id']], [
                    'cam_user_id' => $request->cam_user_id,
                    'assigned_by' => $me,
                    'assigned_at' => now()->toIso8601String(),
                ], false);
            } else {
                $supabase->insert('cam_assignments', [
                    'company_id' => $company,
                    'cam_user_id' => $request->cam_user_id,
                    'assigned_by' => $me,
                ], false);
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not assign CAM: ' . $e->getMessage());
        }

        $this->logAdminAction($request, 'assign_cam', $company, $request->cam_user_id, [], 'cam_assignment');

        return back()->with('success', 'CAM assigned.');
    }

    public function actions(Request $request)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        // `cam_actions` has two separate foreign keys into `users`
        // (cam_user_id, created_by) — resolved via a follow-up `in.()` query
        // rather than guessing PostgREST's embed-disambiguation syntax,
        // same reasoning AuditLogController documents for actor/target.
        $actions = $supabase->get('cam_actions', [
            'select' => '*,companies(name,code)',
            'order' => 'due_date.asc.nullslast',
        ]);

        $camUserIds = collect($actions)->pluck('cam_user_id')->filter()->unique()->values()->all();
        $camUsersById = empty($camUserIds)
            ? collect()
            : collect($supabase->get('users', ['id' => 'in.(' . implode(',', $camUserIds) . ')', 'select' => 'id,name']))->keyBy('id');

        $actions = collect($actions)->map(fn ($a) => $a + ['cam_name' => $camUsersById->get($a['cam_user_id'])['name'] ?? '—'])->values();

        $companies = $supabase->get('companies', ['select' => 'id,name,code', 'order' => 'name.asc', 'limit' => 200]);
        $camUsers = collect($supabase->get('cam_assignments', ['select' => 'cam_user_id,users(name,email)']))
            ->unique('cam_user_id')
            ->map(fn ($a) => ['id' => $a['cam_user_id'], 'name' => $a['users']['name'] ?? '—'])
            ->values();

        return Inertia::render('Platform/Hq/Cam/Actions', [
            'actions' => $actions,
            'companies' => $companies,
            'camUsers' => $camUsers,
        ]);
    }

    public function storeAction(Request $request)
    {
        $this->ensureSuperAdmin($request);

        $request->validate([
            'company_id' => 'required|uuid',
            'cam_user_id' => 'required|uuid',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            $supabase->insert('cam_actions', [
                'company_id' => $request->company_id,
                'cam_user_id' => $request->cam_user_id,
                'title' => $request->title,
                'description' => $request->description,
                'due_date' => $request->due_date,
                'created_by' => $request->attributes->get('platformUser')['id'] ?? null,
            ], false);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not create action: ' . $e->getMessage());
        }

        $this->logAdminAction($request, 'create_cam_action', $request->company_id, $request->cam_user_id, ['title' => $request->title], 'cam_action');

        return back()->with('success', 'Action created.');
    }

    public function updateAction(Request $request, string $action)
    {
        $this->ensureSuperAdmin($request);

        $request->validate(['status' => 'required|in:open,in_progress,done,cancelled']);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            $supabase->update('cam_actions', ['id' => 'eq.' . $action], [
                'status' => $request->status,
                'updated_at' => now()->toIso8601String(),
            ], false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not update action: ' . $e->getMessage());
        }

        return back()->with('success', 'Action updated.');
    }
}
