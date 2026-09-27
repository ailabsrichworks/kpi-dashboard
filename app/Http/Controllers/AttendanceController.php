<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\SupabaseService;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    const WORK_START = '08:30';
    const WORK_END   = '17:30';

    private function authorise(): bool
    {
        // BTS keeps full access here too, including while impersonating
        // someone via View As whose own hr_access flag is false.
        return session('hr_access') === true || $this->isBtsSession();
    }

    public function index(SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid') || !session()->has('company_code')) {
            return redirect()->route('login');
        }
        if (!$this->authorise()) abort(403, 'Access restricted to SLT and VP only.');

        $company = strtoupper(session('company_code'));
        $year    = now()->year;

        $existingData = $supabase->get('attendance_summary', [
            'company_code' => 'eq.' . $company,
            'year'         => 'eq.' . $year,
            'select'       => 'month,updated_at',
        ]) ?? [];

        $monthStatus = [];
        foreach ($existingData as $row) {
            $m = (int) $row['month'];
            if (!isset($monthStatus[$m]) || $row['updated_at'] > $monthStatus[$m]) {
                $monthStatus[$m] = $row['updated_at'];
            }
        }

        return view('attendance.index', [
            'defaultMonth'   => now()->month,
            'defaultYear'    => $year,
            'monthStatus'    => $monthStatus,
            'statusYear'     => $year,
            'defaultCompany' => $company,
        ]);
    }

    public function import(Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid')) return redirect()->route('login');
        if (!$this->authorise()) abort(403, 'Access restricted to SLT and VP only.');

        $request->validate([
            'sheet_url' => 'required|url',
            'month'     => 'required|integer|min:1|max:12',
            'year'      => 'required|integer|min:2024|max:2030',
            'company'   => 'required|string',
        ]);

        $month    = (int) $request->month;
        $year     = (int) $request->year;
        $company  = strtoupper($request->company);
        $sheetUrl = $request->sheet_url;
        $monthLabels = ['January','February','March','April','May','June','July','August','September','October','November','December'];

        preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/', $sheetUrl, $m);
        $sheetId = $m[1] ?? null;
        if (!$sheetId) return back()->with('error', 'Invalid Google Sheet URL.');

        // Public holidays for this month
        $allPh = $supabase->get('public_holidays', ['select' => 'holiday_date']) ?? [];
        $publicHolidays = array_values(array_filter(
            array_column($allPh, 'holiday_date'),
            fn($d) => Carbon::parse($d)->year === $year && Carbon::parse($d)->month === $month
        ));

        // Working days for this month
        $start       = Carbon::create($year, $month, 1);
        $end         = $start->copy()->endOfMonth();
        $workingDays = [];
        for ($d = $start->copy(); $d <= $end; $d->addDay()) {
            $ds = $d->toDateString();
            if (!in_array($d->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]) && !in_array($ds, $publicHolidays)) {
                $workingDays[] = $ds;
            }
        }
        $totalWorkingDays = count($workingDays);

        // DB employees — used only to enrich department info, NOT to add zero-data employees
        $dbEmps = $supabase->get('employees', [
            'is_active' => 'eq.true',
            'select'    => 'id,employee_id,full_name,short_name,department_code',
        ]) ?? [];

        // Try multiple tab name conventions (English, Malay, abbreviated, numeric)
        // so the import works regardless of how the user named their sheet tabs
        $tabCandidates = $this->monthTabNames($month);
        $results = null;

        foreach ($tabCandidates as $tabName) {
            $csvUrl   = "https://docs.google.com/spreadsheets/d/{$sheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($tabName);
            $response = Http::timeout(30)->get($csvUrl);
            if (!$response->successful()) continue;

            $parsed = $this->parseAttendanceCsv($response->body(), $month, $year, $workingDays, $dbEmps);
            if (!empty($parsed)) {
                $results = $parsed;
                break;
            }
        }

        if ($results === null) {
            return back()->with('error', "No clock-in records found for {$monthLabels[$month-1]} {$year}. Check: (1) sheet is shared as 'Anyone with the link can view', (2) the tab for {$monthLabels[$month-1]} exists (e.g. FEBRUARY, February, or Feb), (3) the Year selected matches the dates in the sheet.");
        }

        // Load any previously saved attendance for this month to pre-fill MC/AL/Other inputs
        $savedRows = $supabase->get('attendance_summary', [
            'company_code' => 'eq.' . $company,
            'month'        => 'eq.' . $month,
            'year'         => 'eq.' . $year,
            'select'       => 'internal_id,mc_days,al_days,other_leave_days',
        ]) ?? [];
        $savedByEid = [];
        foreach ($savedRows as $sr) {
            $savedByEid[$sr['internal_id']] = $sr;
        }
        foreach ($results as $eid => &$emp) {
            if (isset($savedByEid[$eid])) {
                $emp['mc_days']          = (int) $savedByEid[$eid]['mc_days'];
                $emp['al_days']          = (int) $savedByEid[$eid]['al_days'];
                $emp['other_leave_days'] = (int) $savedByEid[$eid]['other_leave_days'];
            }
        }
        unset($emp);
        $hasSavedData = !empty($savedByEid);

        // Load current month status for the grid
        $existingData = $supabase->get('attendance_summary', [
            'company_code' => 'eq.' . $company,
            'year'         => 'eq.' . $year,
            'select'       => 'month,updated_at',
        ]) ?? [];
        $monthStatus = [];
        foreach ($existingData as $row) {
            $mi = (int) $row['month'];
            if (!isset($monthStatus[$mi]) || $row['updated_at'] > $monthStatus[$mi]) {
                $monthStatus[$mi] = $row['updated_at'];
            }
        }

        return view('attendance.index', [
            'results'          => $results,
            'workingDays'      => $workingDays,
            'totalWorkingDays' => $totalWorkingDays,
            'publicHolidays'   => $publicHolidays,
            'month'            => $month,
            'year'             => $year,
            'company'          => $company,
            'sheetUrl'         => $sheetUrl,
            'defaultMonth'     => $month,
            'defaultYear'      => $year,
            'defaultCompany'   => $company,
            'monthStatus'      => $monthStatus,
            'statusYear'       => $year,
            'hasSavedData'     => $hasSavedData,
        ]);
    }

    public function save(Request $request, SupabaseService $supabase)
    {
        if (!session()->has('employee_uuid')) return response()->json(['error' => 'Unauthenticated'], 401);
        if (!$this->authorise()) return response()->json(['error' => 'Access restricted.'], 403);

        $data = $request->validate([
            'records'   => 'required|array',
            'month'     => 'required|integer',
            'year'      => 'required|integer',
            'company'   => 'required|string',
            'sheet_url' => 'nullable|string',
        ]);

        foreach ($data['records'] as $rec) {
            $mc    = (int) $rec['mc_days'];
            $al    = (int) $rec['al_days'];
            $other = (int) $rec['other_leave_days'];
            $payload = [
                'internal_id'        => $rec['internal_id'],
                'employee_id'        => $rec['db_employee_id'] ?: null,
                'company_code'       => $data['company'],
                'month'              => $data['month'],
                'year'               => $data['year'],
                'working_days'       => (int) $rec['working_days'],
                'present_days'       => (int) $rec['present_days'],
                'absent_days'        => max(0, (int) $rec['absent_days'] - $mc - $al - $other),
                'late_count'         => (int) $rec['late_count'],
                'total_late_minutes' => (int) $rec['total_late_minutes'],
                'insufficient_count' => (int) ($rec['insufficient_count'] ?? 0),
                'mc_days'            => $mc,
                'al_days'            => $al,
                'other_leave_days'   => $other,
                'sheet_url'          => $data['sheet_url'] ?? null,
                'finalized'          => true,
                'updated_at'         => now()->toISOString(),
            ];
            $supabase->upsert('attendance_summary', $payload, 'internal_id,month,year');
        }

        return response()->json(['success' => true, 'count' => count($data['records'])]);
    }

    /**
     * Parse CSV and return only employees who have clock-in records in this month.
     * DB employees are used ONLY to enrich department info — not added if absent from CSV.
     */
    private function parseAttendanceCsv(string $csvText, int $month, int $year, array $workingDays, array $dbEmps): array
    {
        $cutoff  = self::WORK_START;
        $totalWD = count($workingDays);
        $lines   = array_filter(explode("\n", str_replace("\r", '', $csvText)));
        $rows    = array_map('str_getcsv', $lines);
        array_shift($rows); // remove header row

        $employees = [];
        foreach ($rows as $row) {
            if (count($row) < 7) continue;
            $internalId  = trim($row[0] ?? '');
            $firstName   = trim($row[1] ?? '');
            $lastName    = trim($row[2] ?? '');
            $preferred   = trim($row[3] ?? '');
            $email       = trim($row[4] ?? '');
            $clockIn     = trim($row[5] ?? '');
            $clockInDate = trim($row[6] ?? '');

            if (empty($internalId) || empty($clockInDate) || empty($clockIn)) continue;

            // Robust date parsing: try DD/MM/YYYY first (Malaysian), then D/M/YYYY without leading zeros,
            // then ISO, then American M/D/Y as last resort. Order matters — j/n/Y must come before n/j/Y
            // so that 1/2/2026 is read as Feb 1 (Malaysian) not Jan 2 (American).
            $dt = null;
            foreach (['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'Y/m/d', 'n/j/Y'] as $fmt) {
                try {
                    $candidate = Carbon::createFromFormat($fmt, $clockInDate);
                    if ($candidate !== false) { $dt = $candidate; break; }
                } catch (\Exception $e) { continue; }
            }
            if ($dt === null) {
                try { $dt = Carbon::parse($clockInDate); } catch (\Exception $e) { continue; }
            }
            if ($dt === null) continue;

            // Only keep dates that belong to the requested month/year
            if ($dt->month !== $month || $dt->year !== $year) continue;

            // Normalise the date key to YYYY-MM-DD so it matches working days array
            $dateKey = $dt->toDateString();

            if (!isset($employees[$internalId])) {
                $employees[$internalId] = [
                    'internal_id'    => $internalId,
                    'name'           => trim("{$firstName} {$lastName}"),
                    'preferred_name' => $preferred ?: $firstName,
                    'email'          => $email,
                    'department'     => '',
                    'db_employee_id' => null,
                    'dates'          => [],
                ];
            }

            // Collect all punches per day (earliest = clock-in, latest = clock-out)
            if (!isset($employees[$internalId]['dates'][$dateKey])) {
                $employees[$internalId]['dates'][$dateKey] = [];
            }
            $employees[$internalId]['dates'][$dateKey][] = $clockIn;
        }

        // Enrich with DB info (department, UUID) — only for employees already found in CSV
        $dbIndex = [];
        foreach ($dbEmps as $emp) {
            $eid = $emp['employee_id'] ?? null;
            if ($eid) $dbIndex[$eid] = $emp;
        }
        foreach ($employees as $eid => &$emp) {
            if (isset($dbIndex[$eid])) {
                $emp['db_employee_id'] = $dbIndex[$eid]['id'];
                $emp['department']     = $dbIndex[$eid]['department_code'] ?? '';
            }
        }
        unset($emp);

        // Calculate stats
        $results = [];
        foreach ($employees as $eid => $emp) {
            $presentDays = $lateCount = $totalLateMinutes = $insufficientCount = 0;
            $dailyRecords = [];

            foreach ($workingDays as $wd) {
                $punches = $emp['dates'][$wd] ?? [];
                if (!empty($punches)) {
                    sort($punches); // chronological order — earliest = clock-in, latest = clock-out
                    $ci = $punches[0];
                    $co = count($punches) > 1 ? end($punches) : null;
                    $presentDays++;
                    $ciNorm = strlen($ci) === 4 ? "0{$ci}" : $ci;
                    $coNorm = $co ? (strlen($co) === 4 ? "0{$co}" : $co) : null;
                    $isLate = $ciNorm > $cutoff;
                    $lateMins = 0;
                    if ($isLate) {
                        $lateCount++;
                        $lateMins = (int) Carbon::parse($wd . ' ' . $ciNorm)
                                        ->diffInMinutes(Carbon::parse($wd . ' ' . $cutoff));
                        $totalLateMinutes += $lateMins;
                    }
                    // Insufficient: no clock-out (1 punch only) OR clock-out before end of work
                    $isInsufficient = ($coNorm === null) || ($coNorm < self::WORK_END);
                    if ($isInsufficient) $insufficientCount++;
                    $dailyRecords[$wd] = [
                        'status'          => 'present',
                        'clock_in'        => $ciNorm,
                        'clock_out'       => $coNorm,
                        'is_late'         => $isLate,
                        'late_minutes'    => $lateMins,
                        'is_insufficient' => $isInsufficient,
                    ];
                } else {
                    $dailyRecords[$wd] = [
                        'status'          => 'absent',
                        'clock_in'        => null,
                        'clock_out'       => null,
                        'is_late'         => false,
                        'late_minutes'    => 0,
                        'is_insufficient' => false,
                    ];
                }
            }

            $results[$eid] = [
                'internal_id'        => $eid,
                'db_employee_id'     => $emp['db_employee_id'],
                'name'               => $emp['name'],
                'preferred_name'     => $emp['preferred_name'],
                'email'              => $emp['email'],
                'department'         => $emp['department'],
                'working_days'       => $totalWD,
                'present_days'       => $presentDays,
                'absent_days'        => $totalWD - $presentDays,
                'late_count'         => $lateCount,
                'total_late_minutes' => $totalLateMinutes,
                'insufficient_count' => $insufficientCount,
                'mc_days'            => 0,
                'al_days'            => 0,
                'other_leave_days'   => 0,
                'daily'              => $dailyRecords,
            ];
        }

        uasort($results, fn($a, $b) => strcmp($a['department'] . $a['name'], $b['department'] . $b['name']));
        return $results;
    }

    /**
     * Returns tab name candidates for a given month number, in priority order.
     * Covers English full, Malay full, English abbreviated, Malay abbreviated, and numeric.
     */
    private function monthTabNames(int $month): array
    {
        $en = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        $ms = ['Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember'];
        $ab = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $mab= ['Jan','Feb','Mac','Apr','Mei','Jun','Jul','Ogs','Sep','Okt','Nov','Dis'];
        $i  = $month - 1;
        return array_unique([
            $en[$i], $ms[$i], $ab[$i], $mab[$i],
            strtoupper($en[$i]), strtolower($en[$i]),
            (string) $month, str_pad((string) $month, 2, '0', STR_PAD_LEFT),
        ]);
    }
}
