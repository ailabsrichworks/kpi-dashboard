<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Ports legacy's Attendance page (see the migration's own docblock,
 * 2026_09_29_030000_create_attendance_tables.php, for the schema and the one
 * deliberate scope departure: no CSV-employee-to-Platform-account enrichment
 * yet, since nothing links an external clock-in id to a Platform user
 * today). Everything else — the public-CSV Google Sheet fetch, the working-
 * day/late/insufficient-hours computation, the preview-then-save flow — is
 * ported directly; there was no schema-forced reason to change any of it.
 *
 * import() -> save() mirrors ImportController::preview()/confirm()'s
 * session-token stash exactly, and for the identical reason: don't trust a
 * client-submitted `present_days`/`late_count`/etc. wholesale on save — only
 * the admin's own MC/AL/Other edits are read from the request; every other
 * figure comes back from what THIS SERVER already computed and stashed.
 */
class AttendanceController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    private const WORK_START = '08:30';
    private const WORK_END = '17:30';

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name,code']);
        abort_if(!$companyRow, 404);

        $year = (int) $request->query('year', now()->year);

        return Inertia::render('Platform/Attendance/Index', [
            'company' => $companyRow,
            'monthStatus' => $this->monthStatus($supabase, $company, $year),
            'statusYear' => $year,
            'holidays' => $supabase->get('public_holidays', [
                'company_id' => 'eq.' . $company,
                'select' => 'id,holiday_date,label',
                'order' => 'holiday_date.asc',
            ]),
            'defaultMonth' => now()->month,
            'defaultYear' => now()->year,
        ]);
    }

    /**
     * Which months already have a saved summary this year, and when each was
     * last updated — the same "status grid" legacy's own index() builds,
     * just keyed by month number for the frontend to render directly.
     */
    private function monthStatus(SupabaseUserService $supabase, string $company, int $year): array
    {
        $rows = $supabase->get('attendance_summary', [
            'company_id' => 'eq.' . $company,
            'year' => 'eq.' . $year,
            'select' => 'month,updated_at',
        ]);

        $status = [];
        foreach ($rows as $row) {
            $m = (int) $row['month'];
            if (!isset($status[$m]) || $row['updated_at'] > $status[$m]) {
                $status[$m] = $row['updated_at'];
            }
        }

        return $status;
    }

    public function storeHoliday(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'holiday_date' => 'required|date',
            'label' => 'nullable|string|max:255',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            $supabase->insert('public_holidays', [
                'company_id' => $company,
                'holiday_date' => $request->holiday_date,
                'label' => $request->label,
            ], false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not add that holiday: ' . $e->getMessage());
        }

        return back()->with('success', 'Holiday added.');
    }

    public function destroyHoliday(Request $request, string $company, string $holiday)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $supabase->delete('public_holidays', ['id' => 'eq.' . $holiday, 'company_id' => 'eq.' . $company]);

        return back()->with('success', 'Holiday removed.');
    }

    public function import(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'sheet_url' => 'required|url',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name,code']);
        abort_if(!$companyRow, 404);

        $month = (int) $request->month;
        $year = (int) $request->year;
        $monthLabels = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/', $request->sheet_url, $m);
        $sheetId = $m[1] ?? null;

        if (!$sheetId) {
            return back()->with('error', 'Invalid Google Sheet URL.');
        }

        $holidayDates = collect($supabase->get('public_holidays', [
            'company_id' => 'eq.' . $company,
            'select' => 'holiday_date',
        ]))->pluck('holiday_date')->filter(
            fn ($d) => Carbon::parse($d)->year === $year && Carbon::parse($d)->month === $month
        )->values()->all();

        $workingDays = $this->workingDaysFor($year, $month, $holidayDates);

        $results = null;
        foreach ($this->monthTabNames($month) as $tabName) {
            $csvUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($tabName);
            $response = Http::timeout(30)->get($csvUrl);

            if (!$response->successful()) {
                continue;
            }

            $parsed = $this->parseAttendanceCsv($response->body(), $month, $year, $workingDays);
            if (!empty($parsed)) {
                $results = $parsed;
                break;
            }
        }

        if ($results === null) {
            return back()->with('error', "No clock-in records found for {$monthLabels[$month - 1]} {$year}. Check: (1) the sheet is shared as \"Anyone with the link can view\", (2) a tab for {$monthLabels[$month - 1]} exists, (3) the year selected matches the dates in the sheet.");
        }

        // Pre-fill MC/AL/Other from whatever was already saved for this
        // month, same as legacy — re-importing to tweak the CSV data
        // shouldn't wipe leave days an admin already entered.
        $savedByEid = collect($supabase->get('attendance_summary', [
            'company_id' => 'eq.' . $company,
            'month' => 'eq.' . $month,
            'year' => 'eq.' . $year,
            'select' => 'external_employee_id,mc_days,al_days,other_leave_days',
        ]))->keyBy('external_employee_id');

        foreach ($results as $eid => &$row) {
            if ($saved = $savedByEid->get($eid)) {
                $row['mc_days'] = (int) $saved['mc_days'];
                $row['al_days'] = (int) $saved['al_days'];
                $row['other_leave_days'] = (int) $saved['other_leave_days'];
            }
        }
        unset($row);

        $token = (string) Str::uuid();

        session()->put("attendance_preview.{$token}", [
            'company_id' => $company,
            'month' => $month,
            'year' => $year,
            'sheet_url' => $request->sheet_url,
            'results' => $results,
        ]);

        return Inertia::render('Platform/Attendance/Index', [
            'company' => $companyRow,
            'monthStatus' => $this->monthStatus($supabase, $company, $year),
            'statusYear' => $year,
            'holidays' => $supabase->get('public_holidays', [
                'company_id' => 'eq.' . $company,
                'select' => 'id,holiday_date,label',
                'order' => 'holiday_date.asc',
            ]),
            'defaultMonth' => $month,
            'defaultYear' => $year,
            'preview' => [
                'token' => $token,
                'month' => $month,
                'year' => $year,
                'sheetUrl' => $request->sheet_url,
                'workingDaysCount' => count($workingDays),
                'results' => array_values($results),
            ],
        ]);
    }

    public function save(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'token' => 'required|string',
            'leave' => 'nullable|array',
        ]);

        $staged = session("attendance_preview.{$request->token}");

        if (!$staged || $staged['company_id'] !== $company) {
            return back()->with('error', 'This preview has expired — import the sheet again.');
        }

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        // Only MC/AL/Other are taken from the request, keyed by
        // external_employee_id -- everything else (present/absent/late/
        // insufficient counts) is exactly what this server already computed
        // in import() and stashed, never re-trusted from the client.
        $leaveOverrides = $request->input('leave', []);

        $saved = 0;
        foreach ($staged['results'] as $eid => $row) {
            $mc = (int) ($leaveOverrides[$eid]['mc_days'] ?? $row['mc_days'] ?? 0);
            $al = (int) ($leaveOverrides[$eid]['al_days'] ?? $row['al_days'] ?? 0);
            $other = (int) ($leaveOverrides[$eid]['other_leave_days'] ?? $row['other_leave_days'] ?? 0);

            $payload = [
                'external_employee_id' => $eid,
                'name' => $row['name'],
                'email' => $row['email'] ?: null,
                'department' => $row['department'] ?: null,
                'month' => $staged['month'],
                'year' => $staged['year'],
                'working_days' => $row['working_days'],
                'present_days' => $row['present_days'],
                'absent_days' => max(0, $row['absent_days'] - $mc - $al - $other),
                'late_count' => $row['late_count'],
                'total_late_minutes' => $row['total_late_minutes'],
                'insufficient_count' => $row['insufficient_count'],
                'mc_days' => $mc,
                'al_days' => $al,
                'other_leave_days' => $other,
                'sheet_url' => $staged['sheet_url'],
                'updated_at' => now()->toIso8601String(),
            ];

            $existing = $supabase->first('attendance_summary', [
                'company_id' => 'eq.' . $company,
                'external_employee_id' => 'eq.' . $eid,
                'month' => 'eq.' . $staged['month'],
                'year' => 'eq.' . $staged['year'],
                'select' => 'id',
            ]);

            try {
                if ($existing) {
                    $supabase->update('attendance_summary', ['id' => 'eq.' . $existing['id']], $payload, false);
                } else {
                    $supabase->insert('attendance_summary', $payload + ['company_id' => $company], false);
                }
                $saved++;
            } catch (\Throwable) {
                // One employee's row failing to save shouldn't lose every
                // other row already committed this loop -- surfaced in the
                // final count instead.
            }
        }

        session()->forget("attendance_preview.{$request->token}");

        try {
            $this->logCompanyAction($request, 'save_attendance_summary', $company, null, [
                'month' => $staged['month'],
                'year' => $staged['year'],
                'employee_count' => $saved,
            ], 'attendance_summary');
        } catch (\Throwable) {
            return back()->with('error', "Saved {$saved} employee(s), but the action could not be logged — contact support before continuing.");
        }

        return redirect()
            ->route('platform.attendance.index', ['company' => $company, 'year' => $staged['year']])
            ->with('success', "Saved attendance for {$saved} employee(s).");
    }

    /** @return string[] ISO dates, Mon-Fri, excluding this company's own public holidays for this month */
    private function workingDaysFor(int $year, int $month, array $holidayDates): array
    {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();
        $days = [];

        for ($d = $start->copy(); $d <= $end; $d->addDay()) {
            $ds = $d->toDateString();
            if (!in_array($d->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true) && !in_array($ds, $holidayDates, true)) {
                $days[] = $ds;
            }
        }

        return $days;
    }

    /**
     * Parses the public CSV export and returns present/absent/late/
     * insufficient-hours stats per employee found IN THE CSV — an employee
     * with no clock-in rows this month is simply absent from the result, not
     * synthesized with zeroed stats (matches legacy's own documented
     * behavior: "only employees who have clock-in records in this month").
     *
     * @return array<string, array<string, mixed>> keyed by external_employee_id
     */
    private function parseAttendanceCsv(string $csvText, int $month, int $year, array $workingDays): array
    {
        $cutoff = self::WORK_START;
        $totalWD = count($workingDays);
        $lines = array_filter(explode("\n", str_replace("\r", '', $csvText)));
        $rows = array_map('str_getcsv', $lines);
        array_shift($rows);

        $employees = [];
        foreach ($rows as $row) {
            if (count($row) < 7) {
                continue;
            }

            $internalId = trim($row[0] ?? '');
            $firstName = trim($row[1] ?? '');
            $lastName = trim($row[2] ?? '');
            $preferred = trim($row[3] ?? '');
            $email = trim($row[4] ?? '');
            $clockIn = trim($row[5] ?? '');
            $clockInDate = trim($row[6] ?? '');

            if ($internalId === '' || $clockInDate === '' || $clockIn === '') {
                continue;
            }

            $dt = null;
            foreach (['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'Y/m/d', 'n/j/Y'] as $fmt) {
                try {
                    $candidate = Carbon::createFromFormat($fmt, $clockInDate);
                    if ($candidate !== false) {
                        $dt = $candidate;
                        break;
                    }
                } catch (\Exception) {
                    continue;
                }
            }
            if ($dt === null) {
                try {
                    $dt = Carbon::parse($clockInDate);
                } catch (\Exception) {
                    continue;
                }
            }

            if ($dt->month !== $month || $dt->year !== $year) {
                continue;
            }

            $dateKey = $dt->toDateString();

            if (!isset($employees[$internalId])) {
                $employees[$internalId] = [
                    'name' => trim("{$firstName} {$lastName}"),
                    'preferred_name' => $preferred ?: $firstName,
                    'email' => $email,
                    'dates' => [],
                ];
            }

            $employees[$internalId]['dates'][$dateKey][] = $clockIn;
        }

        $results = [];
        foreach ($employees as $eid => $emp) {
            $presentDays = $lateCount = $totalLateMinutes = $insufficientCount = 0;

            foreach ($workingDays as $wd) {
                $punches = $emp['dates'][$wd] ?? [];
                if (empty($punches)) {
                    continue;
                }

                sort($punches);
                $ci = $punches[0];
                $co = count($punches) > 1 ? end($punches) : null;
                $presentDays++;

                $ciNorm = strlen($ci) === 4 ? "0{$ci}" : $ci;
                $coNorm = $co ? (strlen($co) === 4 ? "0{$co}" : $co) : null;

                if ($ciNorm > $cutoff) {
                    $lateCount++;
                    $totalLateMinutes += (int) Carbon::parse($wd . ' ' . $ciNorm)->diffInMinutes(Carbon::parse($wd . ' ' . $cutoff));
                }

                if ($coNorm === null || $coNorm < self::WORK_END) {
                    $insufficientCount++;
                }
            }

            $results[$eid] = [
                'external_employee_id' => $eid,
                'name' => $emp['name'],
                'email' => $emp['email'],
                'department' => null,
                'working_days' => $totalWD,
                'present_days' => $presentDays,
                'absent_days' => $totalWD - $presentDays,
                'late_count' => $lateCount,
                'total_late_minutes' => $totalLateMinutes,
                'insufficient_count' => $insufficientCount,
                'mc_days' => 0,
                'al_days' => 0,
                'other_leave_days' => 0,
            ];
        }

        uasort($results, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $results;
    }

    /** @return string[] tab-name candidates for a month, in priority order */
    private function monthTabNames(int $month): array
    {
        $en = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $ms = ['Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember'];
        $ab = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $mab = ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogs', 'Sep', 'Okt', 'Nov', 'Dis'];
        $i = $month - 1;

        return array_unique([
            $en[$i], $ms[$i], $ab[$i], $mab[$i],
            strtoupper($en[$i]), strtolower($en[$i]),
            (string) $month, str_pad((string) $month, 2, '0', STR_PAD_LEFT),
        ]);
    }
}
