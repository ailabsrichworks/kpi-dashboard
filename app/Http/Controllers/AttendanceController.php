<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SupabaseService;
use Carbon\Carbon;

/**
 * Attendance now imports from InfoTech/ETMS's "Daily Attendance Report
 * (Individual)" CSV export, uploaded directly as a file -- not the old
 * BrioHR flow (a Google Sheet URL, raw clock punches with no leave code at
 * all, MC/AL/Other hand-typed by HR afterwards). InfoTech gives one row per
 * staff per day with a real reason code (ABS/AL/ALPM/EL/MC/RES/RPLC/etc.)
 * and explicit Normal/Actual hours, so this controller trusts that code
 * directly instead of re-deriving present/absent from punch times.
 *
 * `attendance_daily` (new table, see its own migration) is the real source
 * of truth now -- one row per company+staff+day. `attendance_summary` is
 * recomputed FROM it after every import or delete, never written to by
 * hand; it exists only because PerformanceController and this page's own
 * monthly list still want one cheap row per staff per month rather than
 * summing daily rows on every read.
 */
class AttendanceController extends Controller
{
    const WORK_START = '08:30';
    const WORK_END   = '17:30';

    const VALID_COMPANIES = ['RCG', 'RGHB', 'RCT'];

    private function authorise(): bool
    {
        // BTS keeps full access here too, including while impersonating
        // someone via View As whose own hr_access flag is false.
        return session('hr_access') === true || $this->isBtsSession();
    }

    public function index()
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login');
        }
        if (!$this->authorise()) abort(403, 'Access restricted to BTS and HR.');

        $company = strtoupper(session('company_code'));
        if (!in_array($company, self::VALID_COMPANIES, true)) {
            $company = self::VALID_COMPANIES[0];
        }

        return view('attendance.index', [
            'defaultCompany' => $company,
            'defaultPeriod'  => now()->format('Y-m'),
        ]);
    }

    /**
     * Step 1->2: parse the uploaded CSV, diff it against what's already
     * saved, and stash the parsed+enriched rows in the session under a
     * token (mirrors the Platform ImportController's preview/confirm
     * pattern) -- nothing is written to the database yet.
     */
    public function preview(Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid')) return response()->json(['error' => 'Unauthenticated.'], 401);
        if (!$this->authorise()) return response()->json(['error' => 'Access restricted.'], 403);

        $request->validate([
            'company' => 'required|string|in:' . implode(',', self::VALID_COMPANIES),
            'file'    => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $company = strtoupper($request->company);
        $csvText = file_get_contents($request->file('file')->getRealPath());

        $parsed = $this->parseInfoTechCsv($csvText);
        if ($parsed['error']) {
            return response()->json(['error' => $parsed['error']], 422);
        }
        if (empty($parsed['records'])) {
            return response()->json(['error' => 'No attendance rows found in this file.'], 422);
        }

        $dbEmps = $supabase->get('employees', [
            'company_code' => 'eq.' . $company,
            'select'       => 'id,employee_id,department_code',
        ]) ?? [];
        $dbIndex = [];
        foreach ($dbEmps as $e) {
            if (!empty($e['employee_id'])) $dbIndex[$e['employee_id']] = $e;
        }

        $dates = array_unique(array_column($parsed['records'], 'date'));
        sort($dates);
        $minDate = $dates[0];
        $maxDate = end($dates);

        $existing = $supabase->get('attendance_daily', [
            'company_code' => 'eq.' . $company,
            'date'         => 'gte.' . $minDate,
            'select'       => 'internal_id,date,clock_in,clock_out,status,status_raw',
        ]) ?? [];
        // PostgREST needs a second bound as its own filter, not a combined range.
        $existing = array_values(array_filter($existing, fn($r) => $r['date'] <= $maxDate));
        $existingByKey = [];
        foreach ($existing as $e) {
            $existingByKey[$e['internal_id'] . '|' . $e['date']] = $e;
        }

        $records = [];
        $conflictsByStaff = [];
        foreach ($parsed['records'] as $rec) {
            $rec['employee_id']    = $dbIndex[$rec['internal_id']]['id'] ?? null;
            $key                   = $rec['internal_id'] . '|' . $rec['date'];
            $prev                  = $existingByKey[$key] ?? null;
            $rec['is_conflict']    = false;

            if ($prev !== null) {
                $changed = $prev['clock_in'] !== $rec['clock_in']
                    || $prev['clock_out'] !== $rec['clock_out']
                    || $prev['status'] !== $rec['status'];
                if ($changed) {
                    $rec['is_conflict'] = true;
                    $rec['saved'] = [
                        'clock_in'  => $prev['clock_in'],
                        'clock_out' => $prev['clock_out'],
                        'status'    => $prev['status'],
                    ];
                    $conflictsByStaff[$rec['internal_id']]['name'] = $rec['name'];
                    $conflictsByStaff[$rec['internal_id']]['department'] = $rec['department'];
                    $conflictsByStaff[$rec['internal_id']]['dates'][] = $rec['date'];
                    $conflictsByStaff[$rec['internal_id']]['sample'][] = [
                        'date'  => $rec['date'],
                        'day'   => $rec['day_name'],
                        'saved' => $rec['saved'],
                        'new'   => [
                            'clock_in'  => $rec['clock_in'],
                            'clock_out' => $rec['clock_out'],
                            'status'    => $rec['status'],
                        ],
                    ];
                }
            }
            $records[] = $rec;
        }

        $token = (string) \Illuminate\Support\Str::uuid();
        session(["attendance_import.$token" => [
            'company'    => $company,
            'records'    => $records,
            'created_at' => now()->toISOString(),
        ]]);

        $conflictSummary = [];
        foreach ($conflictsByStaff as $internalId => $c) {
            $conflictSummary[] = [
                'internal_id' => $internalId,
                'name'        => $c['name'],
                'department'  => $c['department'],
                'count'       => count($c['dates']),
                // Capped — this is a review surface for a human, not a full audit dump.
                'sample'      => array_slice($c['sample'], 0, 5),
            ];
        }

        return response()->json([
            'token'           => $token,
            'company'         => $company,
            'totalStaff'      => count(array_unique(array_column($records, 'internal_id'))),
            'totalRecords'    => count($records),
            'dateRangeLabel'  => Carbon::parse($minDate)->format('j M') . ' – ' . Carbon::parse($maxDate)->format('j M Y'),
            'newOrCleanCount' => count($records) - array_sum(array_map(fn($c) => count($c['dates']), $conflictsByStaff)),
            'conflictCount'   => array_sum(array_map(fn($c) => count($c['dates']), $conflictsByStaff)),
            'conflicts'       => $conflictSummary,
        ]);
    }

    /**
     * Step 2->3: apply the staff-level resolutions (keep saved / use
     * upload), save every row to attendance_daily, and recompute
     * attendance_summary for every month that touched.
     *
     * A conflicting day with no explicit resolution defaults to "keep" --
     * never silently overwritten just because HR didn't get to it.
     */
    public function confirmImport(Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid')) return response()->json(['error' => 'Unauthenticated.'], 401);
        if (!$this->authorise()) return response()->json(['error' => 'Access restricted.'], 403);

        $data = $request->validate([
            'token'       => 'required|string',
            'resolutions' => 'nullable|array',
        ]);

        $stash = session("attendance_import.{$data['token']}");
        if (!$stash || now()->diffInHours($stash['created_at']) > 2) {
            return response()->json(['error' => 'This import has expired — please upload the file again.'], 410);
        }

        $resolutions = $data['resolutions'] ?? [];
        $company     = $stash['company'];

        $toUpsert = [];
        foreach ($stash['records'] as $rec) {
            if (!empty($rec['is_conflict'])) {
                $choice = $resolutions[$rec['internal_id']] ?? 'keep';
                if ($choice !== 'new') continue; // "keep saved" — leave the existing row untouched
            }
            $toUpsert[] = [
                'company_code'    => $company,
                'internal_id'     => $rec['internal_id'],
                'employee_id'     => $rec['employee_id'],
                'name'            => $rec['name'],
                'department_raw'  => $rec['department'],
                'date'            => $rec['date'],
                'day_name'        => $rec['day_name'],
                'clock_in'        => $rec['clock_in'],
                'clock_out'       => $rec['clock_out'],
                'status'          => $rec['status'],
                'status_raw'      => $rec['status_raw'],
                'is_late'         => $rec['is_late'],
                'is_early_out'    => $rec['is_early_out'],
                'late_minutes'    => $rec['late_minutes'],
                'normal_hours'    => $rec['normal_hours'],
                'actual_hours'    => $rec['actual_hours'],
                'updated_at'      => now()->toISOString(),
            ];
        }

        foreach (array_chunk($toUpsert, 300) as $chunk) {
            $supabase->upsert('attendance_daily', $chunk, 'company_code,internal_id,date');
        }

        $this->recomputeAffectedMonths($supabase, $company, $toUpsert);

        session()->forget("attendance_import.{$data['token']}");

        return response()->json(['success' => true, 'imported' => count($toUpsert), 'skipped' => count($stash['records']) - count($toUpsert)]);
    }

    /**
     * Monthly staff list for Step 3 — the collapsed rows with
     * Working/Present/Absent/MC/AL/EL/Late counts. Supports a single month
     * ("2026-10") or a quarter ("2026-Q4") as the period.
     */
    public function staffList(Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid')) return response()->json(['error' => 'Unauthenticated.'], 401);
        if (!$this->authorise()) return response()->json(['error' => 'Access restricted.'], 403);

        $company = strtoupper($request->query('company', ''));
        if (!in_array($company, self::VALID_COMPANIES, true)) {
            return response()->json(['error' => 'Invalid company.'], 422);
        }
        [$year, $months] = $this->parsePeriod($request->query('period', now()->format('Y-m')));

        $rows = $supabase->get('attendance_summary', [
            'company_code' => 'eq.' . $company,
            'year'         => 'eq.' . $year,
            'month'        => 'in.(' . implode(',', $months) . ')',
            'select'       => 'internal_id,name,department_raw,working_days,present_days,absent_days,mc_days,al_days,el_days,other_leave_days,late_count,total_late_minutes,insufficient_count',
        ]) ?? [];

        $byStaff = [];
        foreach ($rows as $r) {
            $eid = $r['internal_id'];
            if (!isset($byStaff[$eid])) {
                $byStaff[$eid] = [
                    'internal_id' => $eid,
                    'name'        => $r['name'] ?: $eid,
                    'department'  => $r['department_raw'] ?: '—',
                    'working_days' => 0, 'present_days' => 0, 'absent_days' => 0,
                    'mc_days' => 0, 'al_days' => 0, 'el_days' => 0, 'other_leave_days' => 0,
                    'late_count' => 0, 'total_late_minutes' => 0, 'insufficient_count' => 0,
                ];
            }
            foreach (['working_days','present_days','absent_days','mc_days','al_days','el_days','other_leave_days','late_count','total_late_minutes','insufficient_count'] as $f) {
                $byStaff[$eid][$f] += (int) ($r[$f] ?? 0);
            }
        }

        $search = mb_strtolower(trim($request->query('search', '')));
        $dept   = trim($request->query('department', ''));
        $flag   = $request->query('flag', '');

        $list = array_values(array_filter($byStaff, function ($s) use ($search, $dept, $flag) {
            if ($search && !str_contains(mb_strtolower($s['name']), $search) && !str_contains(mb_strtolower($s['internal_id']), $search)) return false;
            if ($dept && $s['department'] !== $dept) return false;
            if ($flag === 'late' && $s['late_count'] === 0) return false;
            if ($flag === 'absent' && $s['absent_days'] === 0) return false;
            if ($flag === 'clean' && ($s['late_count'] > 0 || $s['absent_days'] > 0)) return false;
            return true;
        }));

        usort($list, fn($a, $b) => strcmp($a['name'], $b['name']));

        return response()->json(['staff' => $list]);
    }

    /**
     * Daily clock in/out drill-down for one staff member, for the "click a
     * row to expand" view. Late-in / early-out flagging is computed once
     * here rather than trusted blindly from storage, so a WORK_START/
     * WORK_END change takes effect on existing data without re-importing.
     */
    public function staffDaily(Request $request, string $internalId, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid')) return response()->json(['error' => 'Unauthenticated.'], 401);
        if (!$this->authorise()) return response()->json(['error' => 'Access restricted.'], 403);

        $company = strtoupper($request->query('company', ''));
        if (!in_array($company, self::VALID_COMPANIES, true)) {
            return response()->json(['error' => 'Invalid company.'], 422);
        }
        [$year, $months] = $this->parsePeriod($request->query('period', now()->format('Y-m')));
        $start = Carbon::create($year, min($months), 1)->toDateString();
        $end   = Carbon::create($year, max($months), 1)->endOfMonth()->toDateString();

        $rows = $supabase->get('attendance_daily', [
            'company_code' => 'eq.' . $company,
            'internal_id'  => 'eq.' . $internalId,
            'date'         => 'gte.' . $start,
            'select'       => 'id,date,day_name,clock_in,clock_out,status,status_raw,is_late,is_early_out',
            'order'        => 'date.asc',
        ]) ?? [];
        $rows = array_values(array_filter($rows, fn($r) => $r['date'] <= $end));

        return response()->json(['daily' => $rows]);
    }

    /**
     * One delete endpoint, every scope the UI offers: specific rows, a
     * whole day (every staff), a week, a month, or one staff member's
     * entire saved record. Always recomputes attendance_summary for
     * whatever it touched afterward so nothing goes stale.
     */
    public function destroy(Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid')) return response()->json(['error' => 'Unauthenticated.'], 401);
        if (!$this->authorise()) return response()->json(['error' => 'Access restricted.'], 403);

        $data = $request->validate([
            'company'     => 'required|string|in:' . implode(',', self::VALID_COMPANIES),
            'scope'       => 'required|in:selected,day,week,month,staff',
            'ids'         => 'nullable|array',
            'date'        => 'nullable|date',
            'internal_id' => 'nullable|string',
            'period'      => 'nullable|string',
        ]);

        $company = strtoupper($data['company']);
        $filters = ['company_code' => 'eq.' . $company];
        $affectedFilter = $filters;

        switch ($data['scope']) {
            case 'selected':
                if (empty($data['ids'])) return response()->json(['error' => 'No records selected.'], 422);
                $filters['id'] = 'in.(' . implode(',', $data['ids']) . ')';
                break;
            case 'day':
                if (empty($data['date'])) return response()->json(['error' => 'Date is required.'], 422);
                $filters['date'] = 'eq.' . $data['date'];
                break;
            case 'week':
                if (empty($data['date'])) return response()->json(['error' => 'A date within the week is required.'], 422);
                $weekStart = Carbon::parse($data['date'])->startOfWeek(Carbon::MONDAY)->toDateString();
                $weekEnd   = Carbon::parse($data['date'])->endOfWeek(Carbon::SUNDAY)->toDateString();
                $rows = $supabase->get('attendance_daily', array_merge($filters, [
                    'date' => 'gte.' . $weekStart, 'select' => 'id,date',
                ])) ?? [];
                $ids = array_column(array_filter($rows, fn($r) => $r['date'] <= $weekEnd), 'id');
                if (empty($ids)) return response()->json(['success' => true, 'deleted' => 0]);
                $filters['id'] = 'in.(' . implode(',', $ids) . ')';
                break;
            case 'month':
                if (empty($data['period'])) return response()->json(['error' => 'Period is required.'], 422);
                [$year, $months] = $this->parsePeriod($data['period']);
                $filters['date'] = 'gte.' . Carbon::create($year, min($months), 1)->toDateString();
                $monthEnd = Carbon::create($year, max($months), 1)->endOfMonth()->toDateString();
                $rows = $supabase->get('attendance_daily', array_merge($filters, ['select' => 'id,date'])) ?? [];
                $ids = array_column(array_filter($rows, fn($r) => $r['date'] <= $monthEnd), 'id');
                if (empty($ids)) return response()->json(['success' => true, 'deleted' => 0]);
                $filters = ['company_code' => 'eq.' . $company, 'id' => 'in.(' . implode(',', $ids) . ')'];
                break;
            case 'staff':
                if (empty($data['internal_id'])) return response()->json(['error' => 'Staff is required.'], 422);
                $filters['internal_id'] = 'eq.' . $data['internal_id'];
                $affectedFilter['internal_id'] = 'eq.' . $data['internal_id'];
                break;
        }

        $toDelete = $supabase->get('attendance_daily', array_merge($filters, ['select' => 'internal_id,date'])) ?? [];
        if (empty($toDelete)) {
            return response()->json(['success' => true, 'deleted' => 0]);
        }

        $supabase->delete('attendance_daily', $filters);

        // Recompute every (staff, month, year) the deletion touched — or
        // remove the summary row entirely if nothing is left for that month.
        $affected = [];
        foreach ($toDelete as $r) {
            $d = Carbon::parse($r['date']);
            $affected[$r['internal_id'] . '|' . $d->year . '|' . $d->month] = [$r['internal_id'], $d->year, $d->month];
        }
        foreach ($affected as [$eid, $year, $month]) {
            $this->recomputeOneMonth($supabase, $company, $eid, (int) $year, (int) $month);
        }

        return response()->json(['success' => true, 'deleted' => count($toDelete)]);
    }

    // ────────────────────────────────────────────────────────────────────

    /**
     * Every row in this export repeats 4 metadata columns (company name,
     * report title, generation date, period) before the real fields start
     * — including the header row itself. Slicing every row the same way
     * means the header never needs special-casing.
     */
    private function parseInfoTechCsv(string $csvText): array
    {
        $lines = array_values(array_filter(
            explode("\n", str_replace("\r", '', $csvText)),
            fn($l) => trim($l) !== ''
        ));
        if (empty($lines)) return ['records' => [], 'error' => 'The file is empty.'];

        $rows = array_map('str_getcsv', $lines);
        $header = array_slice($rows[0], 4);
        $colIndex = [];
        foreach ($header as $i => $name) {
            $colIndex[trim((string) $name)] = $i;
        }

        $required = ['Date', 'Code', 'Name', 'Department Code', 'In', 'Out', 'Normal', 'Actual', 'ReaCodeC'];
        foreach ($required as $col) {
            if (!array_key_exists($col, $colIndex)) {
                return ['records' => [], 'error' => "This doesn't look like the InfoTech Daily Attendance Report export — missing the \"{$col}\" column."];
            }
        }

        $records = [];
        for ($i = 1, $n = count($rows); $i < $n; $i++) {
            $cells = array_slice($rows[$i], 4);
            if (count($cells) < count($header)) continue;
            $get = fn($col) => trim((string) ($cells[$colIndex[$col]] ?? ''));

            $dateRaw = $get('Date');
            if ($dateRaw === '') continue;
            try {
                $date = Carbon::createFromFormat('d/m/Y', $dateRaw)->startOfDay();
            } catch (\Throwable $e) {
                continue;
            }

            $internalId = $get('Code');
            if ($internalId === '') continue;

            $actual   = (float) $get('Actual');
            $normal   = (float) $get('Normal');
            $reason   = strtoupper($get('ReaCodeC'));
            $clockIn  = $this->parseInfoTechTime($get('In'));
            $clockOut = $this->parseInfoTechTime($get('Out'));

            $status      = $this->mapReasonCode($reason, $actual);
            $isLate      = $status === 'present' && $clockIn !== null && $clockIn > self::WORK_START;
            $isEarlyOut  = $status === 'present' && $clockOut !== null && $clockOut < self::WORK_END;
            $lateMinutes = 0;
            if ($isLate) {
                $lateMinutes = (int) Carbon::parse($date->toDateString() . ' ' . self::WORK_START)
                    ->diffInMinutes(Carbon::parse($date->toDateString() . ' ' . $clockIn));
            }

            $records[] = [
                'internal_id'  => $internalId,
                'name'         => $get('Name'),
                'department'   => $get('Department Code'),
                'date'         => $date->toDateString(),
                'day_name'     => $date->format('l'),
                'clock_in'     => $clockIn,
                'clock_out'    => $clockOut,
                'status'       => $status,
                'status_raw'   => $reason,
                'is_late'      => $isLate,
                'is_early_out' => $isEarlyOut,
                'late_minutes' => $lateMinutes,
                'normal_hours' => $normal,
                'actual_hours' => $actual,
            ];
        }

        return ['records' => $records, 'error' => null];
    }

    /**
     * InfoTech encodes clock times as H.MM (e.g. "8.0900" = 8:09 AM,
     * "17.3400" = 5:34 PM) — not a decimal hour count. "0.0000" means no
     * punch at all.
     */
    private function parseInfoTechTime(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '' || (float) $raw === 0.0) return null;
        $parts     = explode('.', $raw, 2);
        $hour      = (int) $parts[0];
        $minuteRaw = str_pad((string) ($parts[1] ?? '00'), 2, '0');
        $minute    = (int) substr($minuteRaw, 0, 2);
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) return null;
        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function mapReasonCode(string $reasonRaw, float $actualHours): string
    {
        $reason = strtoupper(trim($reasonRaw));
        return match (true) {
            $reason === ''                               => $actualHours > 0 ? 'present' : 'absent',
            $reason === 'ABS'                             => 'absent',
            in_array($reason, ['AL', 'ALPM'], true)       => 'al',
            $reason === 'EL'                              => 'el',
            $reason === 'MC'                              => 'mc',
            in_array($reason, ['RES', 'RPLC'], true)      => 'off',
            default                                       => 'other',
        };
    }

    /** "2026-10" -> [2026, [10]]; "2026-Q4" -> [2026, [10,11,12]]. */
    private function parsePeriod(string $period): array
    {
        if (preg_match('/^(\d{4})-Q([1-4])$/', $period, $m)) {
            $year  = (int) $m[1];
            $qStart = (((int) $m[2]) - 1) * 3 + 1;
            return [$year, [$qStart, $qStart + 1, $qStart + 2]];
        }
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $period, $m)) {
            return [(int) $m[1], [(int) $m[2]]];
        }
        return [now()->year, [now()->month]];
    }

    private function recomputeAffectedMonths(SupabaseService $supabase, string $company, array $upsertedRows): void
    {
        $affected = [];
        foreach ($upsertedRows as $r) {
            $d = Carbon::parse($r['date']);
            $affected[$r['internal_id'] . '|' . $d->year . '|' . $d->month] = [$r['internal_id'], $d->year, $d->month];
        }
        foreach ($affected as [$eid, $year, $month]) {
            $this->recomputeOneMonth($supabase, $company, $eid, (int) $year, (int) $month);
        }
    }

    /**
     * Single source of truth for `attendance_summary` — always derived
     * fresh from `attendance_daily`, never hand-edited. If nothing is left
     * for that staff+month, the summary row is removed entirely rather
     * than left behind showing stale counts.
     */
    private function recomputeOneMonth(SupabaseService $supabase, string $company, string $internalId, int $year, int $month): void
    {
        $start = Carbon::create($year, $month, 1)->toDateString();
        $end   = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $rows = $supabase->get('attendance_daily', [
            'company_code' => 'eq.' . $company,
            'internal_id'  => 'eq.' . $internalId,
            'date'         => 'gte.' . $start,
            'select'       => 'employee_id,name,department_raw,date,clock_in,clock_out,status,is_late,is_early_out,late_minutes',
        ]) ?? [];
        $rows = array_values(array_filter($rows, fn($r) => $r['date'] <= $end));

        if (empty($rows)) {
            $supabase->delete('attendance_summary', [
                'company_code' => 'eq.' . $company,
                'internal_id'  => 'eq.' . $internalId,
                'year'         => 'eq.' . $year,
                'month'        => 'eq.' . $month,
            ]);
            return;
        }

        $working = $present = $absent = $mc = $al = $el = $other = $late = $insufficient = 0;
        $lateMinutes = 0;
        foreach ($rows as $r) {
            if ($r['status'] === 'off') continue; // rest day / replacement day off — not a working day
            $working++;
            switch ($r['status']) {
                case 'present': $present++; break;
                case 'absent':  $absent++; break;
                case 'mc':      $mc++; break;
                case 'al':      $al++; break;
                case 'el':      $el++; break;
                case 'other':   $other++; break;
            }
            if (!empty($r['is_late'])) {
                $late++;
                $lateMinutes += (int) ($r['late_minutes'] ?? 0);
            }
            if ($r['status'] === 'present' && empty($r['clock_out'])) $insufficient++;
        }

        $supabase->upsert('attendance_summary', [
            'company_code'       => $company,
            'internal_id'        => $internalId,
            'employee_id'        => $rows[0]['employee_id'] ?? null,
            'name'               => $rows[0]['name'] ?? null,
            'department_raw'     => $rows[0]['department_raw'] ?? null,
            'month'              => $month,
            'year'               => $year,
            'working_days'       => $working,
            'present_days'       => $present,
            'absent_days'        => $absent,
            'late_count'         => $late,
            'total_late_minutes' => $lateMinutes,
            'insufficient_count' => $insufficient,
            'mc_days'            => $mc,
            'al_days'            => $al,
            'el_days'            => $el,
            'other_leave_days'   => $other,
            'finalized'          => true,
            'updated_at'         => now()->toISOString(),
        ], 'internal_id,month,year');
    }
}
