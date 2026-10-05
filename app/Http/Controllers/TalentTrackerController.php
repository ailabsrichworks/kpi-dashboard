<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Talent Tracker -- who has attended training, been a speaker/trainer, or
 * answered questions. Staff are the company's existing `employees`; only the
 * records live in `talent_records` (see database/sql/create_talent_records.sql).
 *
 * Everyone logged in can VIEW. Only SLT (employees.role) and BTS (department,
 * including while BTS is using View As -- see isBtsSession()) can add, edit or
 * delete a record. That rule is enforced here on every write endpoint, not
 * just by hiding buttons in the page.
 */
class TalentTrackerController extends Controller
{
    private const TYPES = ['attended', 'speaker', 'trainer', 'qna', 'other'];

    private function canEdit(): bool
    {
        return $this->isBtsSession() || strtoupper(trim(session('role') ?? '')) === 'SLT';
    }

    private function company(): string
    {
        return strtoupper((string) session('company_code'));
    }

    private function loggedIn(): bool
    {
        return session()->has('employee_uuid') && session()->has('company_code');
    }

    /** Shell page: app sidebar + the tracker UI in a same-origin iframe (keeps its CSS isolated from the sidebar/Tailwind). */
    public function index()
    {
        if (!$this->loggedIn()) return redirect()->route('login');

        return view('talent-tracker.index');
    }

    public function app()
    {
        if (!$this->loggedIn()) return redirect()->route('login');

        return view('talent-tracker.app');
    }

    public function data(SupabaseService $supabase): JsonResponse
    {
        if (!$this->loggedIn()) return response()->json(['error' => 'unauthenticated'], 401);

        $company = $this->company();

        $employees = $supabase->get('employees', [
            'company_code' => 'eq.' . $company,
            'is_active'    => 'eq.true',
            'select'       => 'id,full_name,short_name,position,department_code,role',
            'order'        => 'full_name.asc',
            'limit'        => 2000,
        ]) ?? [];

        $records = $supabase->get('talent_records', [
            'company_code' => 'eq.' . $company,
            'select'       => 'id,employee_id,type,title,record_date,notes',
            'order'        => 'record_date.desc',
            'limit'        => 10000,
        ]) ?? [];

        return response()->json([
            'canEdit' => $this->canEdit(),
            'people'  => array_map(function ($e) {
                $role = strtoupper(trim($e['role'] ?? ''));

                return [
                    'id'         => $e['id'],
                    'name'       => $e['full_name'] ?? $e['short_name'] ?? '(no name)',
                    'position'   => $e['position'] ?? '',
                    'department' => $e['department_code'] ?? '',
                    'remarks'    => in_array($role, ['VP', 'SLT'], true) ? $role : '',
                ];
            }, $employees),
            'records' => array_map(fn ($r) => [
                'id'       => $r['id'],
                'personId' => $r['employee_id'],
                'type'     => $r['type'],
                'title'    => $r['title'],
                'date'     => $r['record_date'],
                'notes'    => $r['notes'] ?? '',
            ], $records),
        ]);
    }

    private function rules(): array
    {
        return [
            'type'  => 'required|in:' . implode(',', self::TYPES),
            'title' => 'required|string|max:300',
            'date'  => 'required|date_format:Y-m-d',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function store(Request $request, SupabaseService $supabase): JsonResponse
    {
        if (!$this->loggedIn()) return response()->json(['error' => 'unauthenticated'], 401);
        if (!$this->canEdit()) return response()->json(['error' => 'Only SLT and BTS can add records.'], 403);

        $v = $request->validate($this->rules() + ['personId' => 'required|uuid']);

        // The employee must exist and belong to the caller's own company.
        $emp = $supabase->get('employees', [
            'id'           => 'eq.' . $v['personId'],
            'company_code' => 'eq.' . $this->company(),
            'select'       => 'id',
        ]);
        if (empty($emp)) return response()->json(['error' => 'Staff member not found.'], 422);

        $row = $supabase->insert('talent_records', [
            'company_code'    => $this->company(),
            'employee_id'     => $v['personId'],
            'type'            => $v['type'],
            'title'           => trim($v['title']),
            'record_date'     => $v['date'],
            'notes'           => trim($v['notes'] ?? ''),
            'created_by'      => session('employee_uuid'),
            'created_by_name' => session('full_name') ?? session('short_name'),
        ]);

        return response()->json($row[0] ?? ['ok' => true], 201);
    }

    public function update(Request $request, string $id, SupabaseService $supabase): JsonResponse
    {
        if (!$this->loggedIn()) return response()->json(['error' => 'unauthenticated'], 401);
        if (!$this->canEdit()) return response()->json(['error' => 'Only SLT and BTS can edit records.'], 403);

        $v = $request->validate($this->rules());

        // Scoped to the caller's company so a record id from another company can't be touched.
        $supabase->update('talent_records', ['id' => 'eq.' . $id, 'company_code' => 'eq.' . $this->company()], [
            'type'        => $v['type'],
            'title'       => trim($v['title']),
            'record_date' => $v['date'],
            'notes'       => trim($v['notes'] ?? ''),
            'updated_at'  => now()->toIso8601String(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(string $id, SupabaseService $supabase): JsonResponse
    {
        if (!$this->loggedIn()) return response()->json(['error' => 'unauthenticated'], 401);
        if (!$this->canEdit()) return response()->json(['error' => 'Only SLT and BTS can delete records.'], 403);

        $supabase->delete('talent_records', ['id' => 'eq.' . $id, 'company_code' => 'eq.' . $this->company()]);

        return response()->json(['ok' => true]);
    }
}
