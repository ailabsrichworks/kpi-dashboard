<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\ExcelImportService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Performix Platform Blueprint, Phase 7: Upload -> Read -> Validate ->
 * Preview -> Confirm -> Import (spec section 14). preview() never writes to
 * the database — it parses and validates only, and stashes the *validated*
 * result in the session under a random token, which confirm() re-reads
 * rather than trusting anything the client could have sent back on its own.
 * The organization is never read from the file: $company always comes from
 * the URL the Center is already authenticated against, exactly as spec
 * section 15 requires.
 *
 * Super-Admin-only throughout, matching `import_batches`'s own RLS insert
 * policy (`import_batches_insert` requires `auth_is_richworks_super_admin()`)
 * — this controller's check is defense-in-depth, not the real boundary.
 *
 * Each `import_batches` row is stamped with the specific type it actually
 * imported (`departments`/`employees`/`kpis`) even when the upload was one
 * combined workbook — a workbook produces one row per section it contained,
 * never a single row mixing three different result sets together.
 *
 * Employees never reach a real `users`/`company_users` row here — there is
 * no `employees` staging table in this schema (see the Blueprint's §1
 * reconciliation), and turning a validated row into a real Supabase Auth
 * account is deliberately a separate, selectable step (spec section 17,
 * Phase 8 — not built yet). Validated employee rows are staged on
 * `import_batches.rows` instead, with `status = 'validated'` rather than
 * `'completed'`.
 */
class ImportController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function show(Request $request, string $company)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', [
            'id' => 'eq.' . $company,
            'select' => 'id,name,code',
        ]);

        abort_if(!$companyRow, 404);

        $batches = $supabase->get('import_batches', [
            'company_id' => 'eq.' . $company,
            'select' => 'id,filename,type,status,total_rows,successful_rows,failed_rows,created_at,completed_at',
            'order' => 'created_at.desc',
            'limit' => 20,
        ]);

        return Inertia::render('Platform/Import/Show', [
            'company' => $companyRow,
            'batches' => $batches,
        ]);
    }

    public function preview(Request $request, string $company, ExcelImportService $importer)
    {
        $this->ensureSuperAdmin($request);

        $request->validate([
            'type' => 'required|in:departments,employees,kpis,workbook',
            'file' => 'required|file|mimes:xlsx,csv|max:10240',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            $parsed = $importer->parse($request->file('file'), $request->type);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not read that file: ' . $e->getMessage());
        }

        // Only fetched when the upload actually contains that sheet — a
        // kpis-only upload has no use for either query, but both used to run
        // unconditionally on every preview() call regardless of $type.
        $existingDepartmentCodes = isset($parsed['departments']) || isset($parsed['employees'])
            ? collect($supabase->get('departments', [
                'company_id' => 'eq.' . $company,
                'select' => 'code',
            ]))->pluck('code')->all()
            : [];

        $existingEmails = isset($parsed['employees'])
            ? collect($supabase->get('company_users', [
                'company_id' => 'eq.' . $company,
                'select' => 'users!company_users_user_id_foreign(email)',
            ]))->pluck('users.email')->filter()->map(fn ($e) => strtolower($e))->all()
            : [];

        $result = [];

        if (isset($parsed['departments'])) {
            $result['departments'] = $importer->validateDepartments($parsed['departments'], $existingDepartmentCodes);
        }

        if (isset($parsed['employees'])) {
            // A combined workbook's own Departments sheet counts as
            // "available" for its Employees sheet to reference, even though
            // those departments don't exist in the database yet — they'll
            // be created together in the same confirm() call.
            $availableCodes = $existingDepartmentCodes;
            if (isset($result['departments'])) {
                $availableCodes = array_merge($availableCodes, collect($result['departments']['valid'])->pluck('code')->all());
            }

            $result['employees'] = $importer->validateEmployees($parsed['employees'], $availableCodes, $existingEmails);
        }

        if (isset($parsed['kpis'])) {
            $result['kpis'] = $importer->validateKpis($parsed['kpis']);
        }

        if (empty($result)) {
            return back()->with('error', 'No recognizable data found in that file for the selected type.');
        }

        $token = (string) Str::uuid();

        // $company is bound into the stashed payload and re-checked in
        // confirm() -- without this, a preview validated against Company A
        // (its dedup checks run against A's existing codes/emails) could be
        // confirmed against a completely different {company} in the URL
        // (stale tab, resubmitted form, copy-pasted token), landing rows in
        // the wrong tenant with no RLS layer to catch it (this controller is
        // Super-Admin-only, and Super Admin bypasses per-company scoping).
        session()->put("import_preview.{$token}", [
            'company_id' => $company,
            'filename' => $request->file('file')->getClientOriginalName(),
            'result' => $result,
        ]);

        return Inertia::render('Platform/Import/Preview', [
            'company' => $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name']),
            'token' => $token,
            'filename' => $request->file('file')->getClientOriginalName(),
            'result' => $result,
        ]);
    }

    public function confirm(Request $request, string $company)
    {
        $this->ensureSuperAdmin($request);

        $request->validate(['token' => 'required|string']);

        $staged = session("import_preview.{$request->token}");

        if (!$staged) {
            return redirect()
                ->route('platform.import.show', ['company' => $company])
                ->with('error', 'This import preview has expired — upload the file again.');
        }

        if ($staged['company_id'] !== $company) {
            return redirect()
                ->route('platform.import.show', ['company' => $company])
                ->with('error', 'This preview was validated against a different company — upload the file again from this company\'s import page.');
        }

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $platformUser = $request->attributes->get('platformUser');
        $filename = $staged['filename'];
        $result = $staged['result'];
        $summaryParts = [];
        $batchRows = [];

        if (isset($result['departments'])) {
            $batchRows[] = $this->commitDepartments($supabase, $company, $result['departments'], $filename, $platformUser['id']);
            $summaryParts[] = count($result['departments']['valid']) . ' departments';
        }

        if (isset($result['kpis'])) {
            $batchRows[] = $this->commitKpis($supabase, $company, $result['kpis'], $filename, $platformUser['id']);
            $summaryParts[] = count($result['kpis']['valid']) . ' KPIs';
        }

        if (isset($result['employees'])) {
            $batchRows[] = $this->stageEmployees($company, $result['employees'], $filename, $platformUser['id']);
            $summaryParts[] = count($result['employees']['valid']) . ' employees staged for account creation';
        }

        try {
            $supabase->insert('import_batches', $batchRows);
        } catch (\Throwable $e) {
            // Logged, not just flashed: by this point commitDepartments()/
            // commitKpis() may already have committed real rows (or
            // stageEmployees() staged real employee data) with no other
            // durable record if this write is the only thing that failed —
            // the flash message alone would only reach whoever happened to
            // be looking at the screen right now.
            Log::error('import_batches history write failed after import already committed', [
                'company_id' => $company,
                'filename' => $filename,
                'summary' => $summaryParts,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Import history could not be recorded: ' . $e->getMessage());
        }

        session()->forget("import_preview.{$request->token}");

        try {
            $this->logAdminAction($request, 'import_data', $company, null, [
                'filename' => $filename,
                'summary' => $summaryParts,
            ]);
        } catch (\Throwable) {
            // Non-fatal — the import itself already committed above.
        }

        return redirect()
            ->route('platform.import.show', ['company' => $company])
            ->with('success', 'Imported ' . implode(', ', $summaryParts) . '.');
    }

    private function commitDepartments(SupabaseUserService $supabase, string $company, array $deptResult, string $filename, string $uploadedBy): array
    {
        $valid = $deptResult['valid'];
        $successfulRows = 0;

        if (!empty($valid)) {
            try {
                $inserted = $supabase->insert('departments', array_map(
                    fn ($d) => ['company_id' => $company, 'name' => $d['name'], 'code' => $d['code'], 'status' => $d['status']],
                    $valid,
                ));

                // Guardrail 3 (2026_08_13_130000) requires every department
                // to keep at least one role — matching DepartmentController::store()'s
                // own seeded "Member" role for a manually-created department.
                $supabase->insert('roles', array_map(
                    fn ($d) => ['department_id' => $d['id'], 'label' => 'Member', 'rank' => 0],
                    $inserted,
                ));

                $successfulRows = count($valid);
            } catch (\Throwable $e) {
                $deptResult['errors'][] = ['row' => null, 'message' => 'Import failed: ' . $e->getMessage()];
            }
        }

        // Not just "did the insert throw" — a sheet where every row failed
        // pre-insert validation ($valid empty) used to fall through with no
        // status change at all and get recorded as 'completed' with
        // successful_rows = 0, which read as a no-op success on the history
        // page rather than the total failure it was.
        $status = $successfulRows > 0 || empty($deptResult['errors']) ? 'completed' : 'failed';

        return [
            'company_id' => $company,
            'filename' => $filename,
            'type' => 'departments',
            'status' => $status,
            'total_rows' => count($valid) + count($deptResult['errors']),
            'successful_rows' => $successfulRows,
            'failed_rows' => count($deptResult['errors']),
            'errors' => $deptResult['errors'],
            'rows' => null,
            'uploaded_by' => $uploadedBy,
            'completed_at' => now()->toISOString(),
        ];
    }

    private function commitKpis(SupabaseUserService $supabase, string $company, array $kpiResult, string $filename, string $uploadedBy): array
    {
        $valid = $kpiResult['valid'];
        $successfulRows = 0;

        if (!empty($valid)) {
            try {
                $supabase->insert('kpis', array_map(fn ($k) => [
                    'company_id' => $company,
                    'name' => $k['name'],
                    'description' => $k['description'],
                    'unit' => $k['unit'],
                    'frequency' => $k['frequency'],
                    'status' => $k['status'],
                ], $valid));

                $successfulRows = count($valid);
            } catch (\Throwable $e) {
                $kpiResult['errors'][] = ['row' => null, 'message' => 'Import failed: ' . $e->getMessage()];
            }
        }

        // See commitDepartments()'s identical comment: "all rows failed
        // validation, nothing to insert" must not read as 'completed'.
        $status = $successfulRows > 0 || empty($kpiResult['errors']) ? 'completed' : 'failed';

        return [
            'company_id' => $company,
            'filename' => $filename,
            'type' => 'kpis',
            'status' => $status,
            'total_rows' => count($valid) + count($kpiResult['errors']),
            'successful_rows' => $successfulRows,
            'failed_rows' => count($kpiResult['errors']),
            'errors' => $kpiResult['errors'],
            'rows' => null,
            'uploaded_by' => $uploadedBy,
            'completed_at' => now()->toISOString(),
        ];
    }

    private function stageEmployees(string $company, array $empResult, string $filename, string $uploadedBy): array
    {
        $valid = $empResult['valid'];

        return [
            'company_id' => $company,
            'filename' => $filename,
            'type' => 'employees',
            'status' => 'validated',
            'total_rows' => count($valid) + count($empResult['errors']),
            'successful_rows' => count($valid),
            'failed_rows' => count($empResult['errors']),
            'errors' => $empResult['errors'],
            'rows' => $valid,
            'uploaded_by' => $uploadedBy,
            'completed_at' => null,
        ];
    }
}
