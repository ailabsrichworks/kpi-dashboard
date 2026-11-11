<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\SupabaseService;

class TitanKpiController extends Controller
{
    const SHEET_ID = '1BjDBkjIXwvoDTfe2bjAtgqWRbIIjYDyOp-i6hSju-IQ';

    const MONTHS = [
        1  => 'January',   2  => 'February',  3  => 'March',
        4  => 'April',     5  => 'May',        6  => 'June',
        7  => 'July',      8  => 'August',     9  => 'September',
        10 => 'October',   11 => 'November',   12 => 'December',
    ];

    const KPIS = [
        'revenue'   => ['no' => '1.1', 'title' => 'Total Collection Achievement (Revenue)', 'desc' => 'To achieve targeted individual monthly revenue collection.',  'unit' => 'RM'],
        'retention' => ['no' => '1.2', 'title' => 'Client Retention',                       'desc' => 'To retain client.',                                           'unit' => 'clients'],
    ];

    // Which regular-KPI title keyword identifies the matching Financial KPI
    // for each Titan KPI key — titles vary per employee ("Total Collection
    // (Revenue)" vs "Total Collection Achievement (Revenue)"), so match on
    // a keyword rather than an exact string.
    const KPI_TITLE_KEYWORD = [
        'revenue'   => 'Collection',
        'retention' => 'Retention',
    ];

    const QUARTER_MONTHS = [
        'Q1' => [1, 2, 3],
        'Q2' => [4, 5, 6],
        'Q3' => [7, 8, 9],
        'Q4' => [10, 11, 12],
    ];

    private function currentUser(SupabaseService $supabase): array
    {
        $uuid = session('employee_uuid');
        if (!$uuid) abort(403, 'Not logged in.');
        $rows = $supabase->get('employees', ['id' => 'eq.' . $uuid, 'is_active' => 'eq.true', 'select' => '*']);
        if (empty($rows)) { session()->flush(); abort(403); }
        return $rows[0];
    }

    private function currentFY(): string { return 'FY' . now()->year; }

    private function isTitanUser(array $user): bool
    {
        // BTS has cross-company admin/support access (same as the "BTS Admin —
        // View As" feature elsewhere), so it's checked via isBtsSession() —
        // not tied to company_code or the VP exclusion the way TITAN is, and
        // still true while impersonating someone outside TITAN via View As.
        return ($user['role'] !== 'VP' && $user['company_code'] === 'RCG' && $user['department_code'] === 'TITAN')
            || $this->isBtsSession();
    }

    private function isTitanManager(array $user): bool
    {
        // BTS has cross-company admin/support access, same level as SLT —
        // manager-equivalent regardless of the employee's actual role, and
        // still true while impersonating someone via View As.
        return in_array($user['role'], ['MANAGER', 'SLT']) || $this->isBtsSession();
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX — Titan KPI dashboard (auto-sync from Google Sheet on every load)
    |--------------------------------------------------------------------------
    */

    public function index(SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);
        if (!$this->isTitanUser($user)) abort(403, 'Access restricted to RCG Titan staff.');

        $fy = $this->currentFY();

        // All active Titan staff (non-VP), sorted A–Z
        $allStaff = array_values(array_filter(
            $supabase->get('employees', [
                'company_code'    => 'eq.RCG',
                'department_code' => 'eq.TITAN',
                'is_active'       => 'eq.true',
                'select'          => 'id,employee_id,short_name,full_name,role',
            ]) ?? [],
            fn($e) => $e['role'] !== 'VP'
        ));
        usort($allStaff, fn($a, $b) => strcasecmp($a['short_name'] ?? '', $b['short_name'] ?? ''));

        $isManager = $this->isTitanManager($user);
        $viewStaff = $isManager ? $allStaff
            : array_values(array_filter($allStaff, fn($e) => $e['id'] === $user['id']));

        // Auto-sync: fetch from Google Sheet and bulk-upsert all rows in ONE API call
        $sheetData = $this->fetchSheetData();
        if (!empty($sheetData)) {
            $now  = now()->toDateTimeString();
            $rows = [];
            foreach ($allStaff as $emp) {
                $camKey  = strtolower(trim($emp['short_name'] ?? ''));
                $camData = $sheetData[$camKey] ?? [];
                foreach (self::MONTHS as $monthNum => $monthName) {
                    foreach (array_keys(self::KPIS) as $kpiKey) {
                        $rows[] = [
                            'employee_id'    => $emp['id'],
                            'company_code'   => 'RCG',
                            'financial_year' => $fy,
                            'kpi_key'        => $kpiKey,
                            'month_number'   => $monthNum,
                            'month_name'     => $monthName,
                            'actual'         => $camData[$monthNum][$kpiKey]['actual']      ?? 0,
                            'base_target'    => $camData[$monthNum][$kpiKey]['base_target'] ?? 0,
                            'weightage'      => 10,
                            'synced_at'      => $now,
                            'updated_at'     => $now,
                        ];
                    }
                }
            }
            // Single bulk API call instead of 240 individual ones
            $supabase->upsert('titan_monthly_kpi', $rows, 'employee_id,financial_year,kpi_key,month_number');

            // Mirror the same monthly data into each CAM's real appraisal KPI
            // (category "Financial"), summed per quarter, so the Titan Sheet
            // stays the single source of truth instead of needing manual re-entry.
            $this->syncToRegularKpi($supabase, $allStaff, $sheetData, $fy);
        }

        // Read back from DB (preserves any manual weightage edits)
        $monthlyData = [];
        foreach ($viewStaff as $emp) {
            $rows = $supabase->get('titan_monthly_kpi', [
                'employee_id'    => 'eq.' . $emp['id'],
                'financial_year' => 'eq.' . $fy,
                'select'         => 'kpi_key,month_number,actual,base_target,weightage',
            ]) ?? [];
            foreach ($rows as $r) {
                $monthlyData[$emp['id']][$r['kpi_key']][$r['month_number']] = $r;
            }
        }

        return view('titan.kpi', compact('user', 'fy', 'allStaff', 'viewStaff', 'monthlyData', 'isManager'));
    }

    /*
    |--------------------------------------------------------------------------
    | SYNC TO REGULAR KPI — push Titan Sheet monthly data (summed per quarter)
    |    into each CAM's own "Financial" category KPI, so managers/appraisers
    |    see the same numbers without the CAM re-typing them into /kpi.
    |--------------------------------------------------------------------------
    */

    private function syncToRegularKpi(SupabaseService $supabase, array $allStaff, array $sheetData, string $fy): void
    {
        $employeeIds = collect($allStaff)->pluck('id')->filter()->values()->toArray();
        if (empty($employeeIds)) return;

        // One batch read of every "Financial" KPI these employees own, instead
        // of a per-employee/per-kpi-key query (9 staff x 2 keys = 18 round
        // trips) — matching is then just string search done in memory.
        $financialKpis = $supabase->get('kpis', [
            'employee_id'    => 'in.(' . implode(',', $employeeIds) . ')',
            'financial_year' => 'eq.' . $fy,
            'category'       => 'eq.Financial',
            'select'         => 'id,employee_id,kpi_title',
        ]) ?? [];
        $kpisByEmployee = collect($financialKpis)->groupBy('employee_id');

        $now  = now()->toDateTimeString();
        $rows = [];

        foreach ($allStaff as $emp) {
            $camKey  = strtolower(trim($emp['short_name'] ?? ''));
            $camData = $sheetData[$camKey] ?? [];
            if (empty($camData)) continue;

            $candidateKpis = $kpisByEmployee->get($emp['id'], collect());

            foreach (self::KPI_TITLE_KEYWORD as $kpiKey => $keyword) {
                $matched = $candidateKpis->first(
                    fn ($k) => stripos($k['kpi_title'] ?? '', $keyword) !== false
                );

                if (!$matched) {
                    \Log::info("Titan KPI sync: no matching Financial KPI for {$emp['short_name']} ({$kpiKey}), skipped.");
                    continue;
                }

                foreach (self::QUARTER_MONTHS as $quarter => $months) {
                    $qActual = 0;
                    $qTarget = 0;
                    foreach ($months as $mn) {
                        $qActual += (float) ($camData[$mn][$kpiKey]['actual']      ?? 0);
                        $qTarget += (float) ($camData[$mn][$kpiKey]['base_target'] ?? 0);
                    }

                    $rows[] = [
                        'kpi_id'         => $matched['id'],
                        'quarter'        => $quarter,
                        'quarter_actual' => $qActual,
                        'quarter_target' => $qTarget,
                        'updated_at'     => $now,
                    ];
                }
            }
        }

        if (!empty($rows)) {
            // Single bulk upsert instead of one PATCH per kpi/quarter combo
            // (up to 72 calls for 9 staff x 2 KPIs x 4 quarters).
            $supabase->upsert('kpi_quarters', $rows, 'kpi_id,quarter');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH SHEET DATA — download XLSX, read each CAM's sheet tab directly
    |    revenue:   actual      = "Total Collected" row per month
    |               base_target = "Potential Collection" row per month
    |    retention: actual      = clients with Settle/Reactive status per month
    |               base_target = clients with any active status per month
    |--------------------------------------------------------------------------
    */

    // Column letter → month number (same layout in every CAM sheet tab)
    private const COL_MONTH = [
        'H' => 1, 'I' => 2, 'J' => 3,  'K' => 4,
        'L' => 5, 'M' => 6, 'N' => 7,  'O' => 8,
        'P' => 9, 'Q' => 10, 'R' => 11, 'S' => 12,
    ];

    // Statuses that count as "paid / retained"
    private const PAID_STATUSES = ['Settle', 'Reactive'];

    // Statuses that remove a client from Potential Collection
    private const DROPOUT_STATUSES = ['Terminated', 'N/A', 'Request to Stop'];

    private function fetchSheetData(): array
    {
        if (!class_exists('ZipArchive')) return [];

        $xlsxUrl  = 'https://docs.google.com/spreadsheets/d/' . self::SHEET_ID . '/export?format=xlsx';

        try {
            $response = Http::timeout(45)->get($xlsxUrl);
            if (!$response->successful()) return [];

            // ZipArchive needs a real file on disk
            $tmp = sys_get_temp_dir() . '/titan_kpi_' . uniqid() . '.xlsx';
            file_put_contents($tmp, $response->body());

            $zip = new \ZipArchive();
            if ($zip->open($tmp) !== true) { @unlink($tmp); return []; }

            try {
                $ss         = $this->xlsxSharedStrings($zip->getFromName('xl/sharedStrings.xml') ?: '');
                $sheetFiles = $this->xlsxSheetFileMap(
                    $zip->getFromName('xl/workbook.xml') ?: '',
                    $zip->getFromName('xl/_rels/workbook.xml.rels') ?: ''
                );

                $result = [];
                foreach ($sheetFiles as $name => $path) {
                    if (in_array($name, ['MASTER', 'Sheet2'])) continue;
                    $camKey = strtolower($name);
                    $data   = $this->xlsxParseCamSheet($zip->getFromName($path) ?: '', $ss);
                    if (!empty($data)) $result[$camKey] = $data;
                }
            } finally {
                $zip->close();
                @unlink($tmp);
            }

            return $result;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Titan KPI sheet sync failed: ' . $e->getMessage());
            return [];
        }
    }

    private function xlsxSharedStrings(string $xml): array
    {
        $strings = [];
        preg_match_all('/<si>(.*?)<\/si>/s', $xml, $m);
        foreach ($m[1] as $entry) {
            $text = preg_replace('/<[^>]+>/', '', $entry);
            $strings[] = trim(html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'));
        }
        return $strings;
    }

    private function xlsxSheetFileMap(string $wbXml, string $relsXml): array
    {
        // rId → target file
        $relMap = [];
        preg_match_all('/Id="(rId\d+)"[^>]*Target="(worksheets\/sheet\d+\.xml)"/', $relsXml, $rm);
        foreach ($rm[1] as $i => $rid) {
            $relMap[$rid] = 'xl/' . $rm[2][$i];
        }

        // sheet name → file path
        $map = [];
        preg_match_all('/name="([^"]+)"[^>]*r:id="(rId\d+)"/', $wbXml, $sm);
        foreach ($sm[1] as $i => $name) {
            $rid = $sm[2][$i];
            if (isset($relMap[$rid])) $map[$name] = $relMap[$rid];
        }
        return $map;
    }

    private function xlsxParseCamSheet(string $xml, array $ss): array
    {
        // Build lookup: string value → shared-string index (for status matching)
        $ssIndex = array_flip($ss);
        $paidIdx    = array_values(array_filter(array_map(
            fn($s) => ($ssIndex[$s] ?? null), self::PAID_STATUSES
        )));
        $dropoutIdx = array_values(array_filter(array_map(
            fn($s) => ($ssIndex[$s] ?? null), self::DROPOUT_STATUSES
        )));
        $potentialIdx    = $ssIndex['Potential Collection'] ?? -1;
        $totalCollectIdx = $ssIndex['Total Collected']      ?? -1;

        $result          = [];
        $retentionBase   = [];
        $retentionActual = [];

        preg_match_all('/<row\b[^>]*>(.*?)<\/row>/s', $xml, $rows);
        foreach ($rows[1] as $rowContent) {

            // ── Summary rows (label in column A as shared string) ──────────
            if (preg_match('/<c r="A\d+"[^>]*t="s"[^>]*><v>(\d+)<\/v>/', $rowContent, $lm)) {
                $labelIdx = (int)$lm[1];

                if ($labelIdx === $potentialIdx || $labelIdx === $totalCollectIdx) {
                    $field = ($labelIdx === $potentialIdx) ? 'base_target' : 'actual';
                    // These month cells are array formulas (IFERROR/SUM/SUMIF), so the
                    // <v> value sits after an <f>...</f> block, not right after the
                    // cell's opening tag — the (?:<f>...</f>)? makes that optional.
                    // Some formulas (e.g. the multi-line IFERROR/FILTER ones) contain
                    // literal newlines, hence the /s (dotall) modifier.
                    preg_match_all('/<c r="([H-S])\d+"[^>]*>(?:<f\b[^>]*>.*?<\/f>)?<v>([^<]+)<\/v>/s', $rowContent, $cm);
                    foreach ($cm[1] as $j => $col) {
                        $mn = self::COL_MONTH[$col] ?? null;
                        if ($mn) $result[$mn]['revenue'][$field] = (float)$cm[2][$j];
                    }
                    continue;
                }
            }

            // ── Client rows — count retention from payment statuses ─────────
            // Client rows have a numeric Amount in column G
            if (!preg_match('/<c r="G\d+"[^>]*><v>([0-9.]+)<\/v>/', $rowContent)) continue;

            preg_match_all('/<c r="([H-S])\d+"[^>]*t="s"[^>]*><v>(\d+)<\/v>/', $rowContent, $sm);
            foreach ($sm[1] as $j => $col) {
                $mn        = self::COL_MONTH[$col] ?? null;
                if (!$mn) continue;
                $statusIdx = (int)$sm[2][$j];

                if (!in_array($statusIdx, $dropoutIdx)) {
                    $retentionBase[$mn] = ($retentionBase[$mn] ?? 0) + 1;
                }
                if (in_array($statusIdx, $paidIdx)) {
                    $retentionActual[$mn] = ($retentionActual[$mn] ?? 0) + 1;
                }
            }
        }

        foreach (array_keys(self::MONTHS) as $mn) {
            $result[$mn]['retention']['actual']      = $retentionActual[$mn] ?? 0;
            $result[$mn]['retention']['base_target'] = $retentionBase[$mn]   ?? 0;
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | SYNC (kept for route compatibility — index() already auto-syncs)
    |--------------------------------------------------------------------------
    */

    public function sync(Request $request, SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);
        if (!$this->isTitanUser($user) || !$this->isTitanManager($user)) {
            return response()->json(['error' => 'Access denied.'], 403);
        }
        return response()->json(['success' => true, 'message' => 'Auto-sync runs on every page load.']);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE WEIGHTAGE — manager can adjust per month/KPI
    |--------------------------------------------------------------------------
    */

    public function updateWeightage(Request $request, SupabaseService $supabase)
    {
        $user = $this->currentUser($supabase);
        if (!$this->isTitanUser($user) || !$this->isTitanManager($user)) {
            return response()->json(['error' => 'Access denied.'], 403);
        }

        $request->validate([
            'employee_id'  => 'required|uuid',
            'kpi_key'      => 'required|in:revenue,retention',
            'month_number' => 'required|integer|min:1|max:12',
            'weightage'    => 'required|numeric|min:0|max:100',
        ]);

        $fy = $this->currentFY();
        $supabase->patch('titan_monthly_kpi', [
            'employee_id'    => 'eq.' . $request->employee_id,
            'financial_year' => 'eq.' . $fy,
            'kpi_key'        => 'eq.' . $request->kpi_key,
            'month_number'   => 'eq.' . $request->month_number,
        ], ['weightage' => $request->weightage, 'updated_at' => now()->toDateTimeString()]);

        return response()->json(['success' => true]);
    }
}
