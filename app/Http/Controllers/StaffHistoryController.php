<?php

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffHistoryController extends Controller
{
    // Same gate as OrgChartController/StaffEvaluationSummaryController — HR
    // and BTS specifically, not the wider SLT/cross-company-monitor set.
    private function requireHrBtsAccess(): void
    {
        if (!$this->isCrossCompanyMonitorSession()) {
            abort(403, 'This page is only accessible to BTS and Corporate Governance & HR.');
        }
    }

    // Bilingual labels throughout (per the request this page be designed so
    // HR understands it) plus a badge colour per event, used by both the
    // "Add Record" form and the timeline list.
    public const EVENT_TYPES = [
        'resigned'    => ['label' => 'Resigned / Berhenti Kerja',          'bg' => '#FEE2E2', 'text' => '#B91C1C', 'suggest_status' => 'inactive'],
        'terminated'  => ['label' => 'Terminated / Diberhentikan',          'bg' => '#FEE2E2', 'text' => '#991B1B', 'suggest_status' => 'inactive'],
        'suspended'   => ['label' => 'Suspended / Digantung Kerja',        'bg' => '#FFEDD5', 'text' => '#C2410C', 'suggest_status' => null],
        'rejoined'    => ['label' => 'Rejoined / Kembali Bekerja',         'bg' => '#DCFCE7', 'text' => '#15803D', 'suggest_status' => 'active'],
        'transferred' => ['label' => 'Transferred / Pertukaran Jabatan',  'bg' => '#DBEAFE', 'text' => '#1D4ED8', 'suggest_status' => null],
        'promoted'    => ['label' => 'Promoted / Kenaikan Pangkat',        'bg' => '#EDE9FE', 'text' => '#6D28D9', 'suggest_status' => null],
        'other'       => ['label' => 'Other / Lain-lain',                  'bg' => '#F1F5F9', 'text' => '#475569', 'suggest_status' => null],
    ];

    private function companies(SupabaseService $supabase): array
    {
        return $supabase->get('companies', ['select' => 'code,name', 'order' => 'name.asc']) ?? [];
    }

    private function resolveCompanyCode(Request $request, array $companies): string
    {
        $requested = strtoupper(trim($request->query('company', $request->input('company', ''))));
        $valid     = collect($companies)->pluck('code')->map(fn ($c) => strtoupper($c))->all();

        return in_array($requested, $valid, true) ? $requested : strtoupper(session('company_code'));
    }

    // All staff for this company -- active AND inactive, unlike almost every
    // other HR page -- a resigned/terminated staff member is exactly who
    // this page needs to be able to find and record against.
    private function employeesForCompany(SupabaseService $supabase, string $companyCode): array
    {
        $employees = $supabase->get('employees', [
            'company_code' => 'eq.' . $companyCode,
            'select'       => 'id,employee_id,short_name,full_name,department_code,role,position,is_active',
            'order'        => 'short_name.asc',
        ]) ?? [];

        // Same cross-company-monitor roster exclusion as Org Chart/Staff
        // Summary -- a BTS/HR viewer's own row only counts as THIS
        // company's own staff when it's their default membership, so this
        // page never lets them log a history record against their own
        // foreign row by accident.
        $empIds = collect($employees)->pluck('id')->filter()->values()->toArray();
        if (!empty($empIds)) {
            $roles = $supabase->get('user_company_roles', [
                'company_code' => 'eq.' . $companyCode,
                'employee_id'  => 'in.(' . implode(',', $empIds) . ')',
                'select'       => 'employee_id,is_default',
            ]);
            $isDefaultByEmployeeId = collect($roles)->pluck('is_default', 'employee_id');
            $employees = array_values(array_filter($employees, function ($emp) use ($isDefaultByEmployeeId) {
                return $isDefaultByEmployeeId->get($emp['id'], true) !== false;
            }));
        }

        return $employees;
    }

    public function index(Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login')->with('error', 'Sila login terlebih dahulu.');
        }

        $this->requireHrBtsAccess();

        $companies   = $this->companies($supabase);
        $companyCode = $this->resolveCompanyCode($request, $companies);
        $employees   = $this->employeesForCompany($supabase, $companyCode);

        $departments    = $supabase->get('departments', [
            'company_code' => 'eq.' . $companyCode,
            'select'       => 'code,name',
        ]) ?? [];
        $deptNameByCode = collect($departments)->pluck('name', 'code')->map(fn ($name, $code) => $name ?: $code);

        $employeesById = collect($employees)->keyBy('id');

        // Edit Staff Data now only lists Active staff -- an Inactive one's
        // only home in this app is here, so every Inactive staff member
        // must show up somewhere in this page's own Rekod Sejarah Staff
        // list, even one HR never got around to logging a real event for
        // yet. Tracked per employee_id so that case can be detected below.
        $hasRecordByEmployeeId = collect($supabase->get('staff_history', [
            'company_code' => 'eq.' . $companyCode,
            'select'       => 'employee_id',
        ]) ?? [])->pluck('employee_id')->flip();

        $eventFilter = strtoupper(trim($request->query('event_type', 'ALL')));
        $eventFilter = array_key_exists(strtolower($eventFilter), self::EVENT_TYPES) ? strtolower($eventFilter) : 'ALL';

        $historyFilters = [
            'company_code' => 'eq.' . $companyCode,
            'select'       => '*',
            'order'        => 'event_date.desc,created_at.desc',
        ];
        if ($eventFilter !== 'ALL') {
            $historyFilters['event_type'] = 'eq.' . $eventFilter;
        }

        $records = $supabase->get('staff_history', $historyFilters) ?? [];

        $rows = [];
        foreach ($records as $r) {
            $emp = $employeesById->get($r['employee_id']);
            $rows[] = [
                'id'              => $r['id'],
                'employee_name'   => $emp['short_name'] ?? $emp['full_name'] ?? 'Unknown / Staff Removed',
                'employee_code'   => $emp['employee_id'] ?? '-',
                'department'      => $emp ? $deptNameByCode->get($emp['department_code'] ?? '', $emp['department_code'] ?? '-') : '-',
                'currently_active'=> $emp['is_active'] ?? null,
                'event_type'      => $r['event_type'],
                'event_meta'      => self::EVENT_TYPES[$r['event_type']] ?? self::EVENT_TYPES['other'],
                'event_date'      => $r['event_date'],
                'reason'          => $r['reason'],
                'notes'           => $r['notes'],
                'recorded_by_name'=> $r['recorded_by_name'] ?? '-',
                'created_at'      => $r['created_at'],
            ];
        }

        // Any Inactive staff member with zero staff_history rows at all
        // would otherwise never appear anywhere on this page -- surfaced
        // here as a placeholder row (no real staff_history id, so Edit/
        // Delete aren't offered on it) pointing HR at the Add Record form
        // instead. Only under "All Events", since it isn't really any one
        // event type a filter could match.
        if ($eventFilter === 'ALL') {
            foreach ($employees as $emp) {
                if ($emp['is_active'] || $hasRecordByEmployeeId->has($emp['id'])) {
                    continue;
                }
                $rows[] = [
                    'id'              => null,
                    'employee_name'   => $emp['short_name'] ?? $emp['full_name'],
                    'employee_code'   => $emp['employee_id'],
                    'department'      => $deptNameByCode->get($emp['department_code'] ?? '', $emp['department_code'] ?? '-'),
                    'currently_active'=> false,
                    'event_type'      => null,
                    'event_meta'      => ['label' => 'Inactive — Tiada Rekod', 'bg' => '#F1F5F9', 'text' => '#64748B'],
                    'event_date'      => null,
                    'reason'          => null,
                    'notes'           => 'Staff ini Inactive tetapi belum ada rekod sejarah. Sila tambah rekod di atas.',
                    'recorded_by_name'=> '-',
                    'created_at'      => null,
                ];
            }
        }

        return view('staff-history.index', [
            'today'        => now()->timezone('Asia/Kuala_Lumpur')->format('d M Y'),
            'companyCode'  => $companyCode,
            'companies'    => $companies,
            'employees'    => $employees,
            'eventTypes'   => self::EVENT_TYPES,
            'eventFilter'  => $eventFilter,
            'rows'         => $rows,
        ]);
    }

    public function store(Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login')->with('error', 'Sila login terlebih dahulu.');
        }

        $this->requireHrBtsAccess();

        $validator = Validator::make($request->all(), [
            'employee_id'  => ['required', 'uuid'],
            'event_type'   => ['required', 'string', 'in:' . implode(',', array_keys(self::EVENT_TYPES))],
            'event_date'   => ['required', 'date'],
            'reason'       => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string', 'max:2000'],
            'status_action'=> ['nullable', 'in:no_change,active,inactive'],
            'company'      => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $data = $validator->validated();

        // The employee picked in the form must genuinely belong to the
        // company this page is currently scoped to -- the dropdown is only
        // ever populated from that company's own roster, so a mismatch here
        // means tampered input, not a legitimate request.
        $employee = $supabase->get('employees', [
            'id'     => 'eq.' . $data['employee_id'],
            'select' => 'id,company_code',
        ]);
        $employee = $employee[0] ?? null;

        if (!$employee) {
            return back()->withInput()->with('error', 'Staff tidak dijumpai.');
        }

        $companyCode = strtoupper($employee['company_code']);

        $supabase->insert('staff_history', [
            'employee_id'      => $data['employee_id'],
            'company_code'     => $companyCode,
            'event_type'       => $data['event_type'],
            'event_date'       => $data['event_date'],
            'reason'           => $data['reason'] ?? null,
            'notes'            => $data['notes'] ?? null,
            'recorded_by_id'   => session('employee_uuid'),
            'recorded_by_name' => session('short_name') ?: session('full_name') ?: session('employee_name') ?: 'HR',
        ]);

        $statusAction = $data['status_action'] ?? 'no_change';
        if ($statusAction !== 'no_change') {
            $supabase->patch('employees', ['id' => 'eq.' . $data['employee_id']], [
                'is_active' => $statusAction === 'active',
            ]);
        }

        return redirect()->route('staff-history', ['company' => $companyCode])
            ->with('success', 'Rekod sejarah staff berjaya disimpan.');
    }

    public function update(string $id, Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login')->with('error', 'Sila login terlebih dahulu.');
        }

        $this->requireHrBtsAccess();

        $validator = Validator::make($request->all(), [
            'event_type'    => ['required', 'string', 'in:' . implode(',', array_keys(self::EVENT_TYPES))],
            'event_date'    => ['required', 'date'],
            'reason'        => ['nullable', 'string', 'max:255'],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'status_action' => ['nullable', 'in:no_change,active,inactive'],
            'company'       => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $data = $validator->validated();

        $existing = $supabase->get('staff_history', ['id' => 'eq.' . $id, 'select' => 'id,employee_id,company_code']);
        $existing = $existing[0] ?? null;

        if (!$existing) {
            return back()->with('error', 'Rekod tidak dijumpai.');
        }

        $supabase->patch('staff_history', ['id' => 'eq.' . $id], [
            'event_type' => $data['event_type'],
            'event_date' => $data['event_date'],
            'reason'     => $data['reason'] ?? null,
            'notes'      => $data['notes'] ?? null,
            'updated_at' => now()->toIso8601String(),
        ]);

        $statusAction = $data['status_action'] ?? 'no_change';
        if ($statusAction !== 'no_change') {
            $supabase->patch('employees', ['id' => 'eq.' . $existing['employee_id']], [
                'is_active' => $statusAction === 'active',
            ]);
        }

        return redirect()->route('staff-history', ['company' => $existing['company_code']])
            ->with('success', 'Rekod sejarah staff berjaya dikemaskini.');
    }

    public function destroy(string $id, Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login')->with('error', 'Sila login terlebih dahulu.');
        }

        $this->requireHrBtsAccess();

        $existing = $supabase->get('staff_history', ['id' => 'eq.' . $id, 'select' => 'id,company_code']);
        $existing = $existing[0] ?? null;
        $companyCode = $existing['company_code'] ?? strtoupper(session('company_code'));

        $supabase->delete('staff_history', ['id' => 'eq.' . $id]);

        return redirect()->route('staff-history', ['company' => $companyCode])
            ->with('success', 'Rekod sejarah staff dipadam.');
    }
}
