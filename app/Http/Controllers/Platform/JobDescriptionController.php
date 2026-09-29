<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Ports legacy's "Job Description" page — one own-employee form (summary /
 * responsibilities / requirements / competencies), draft-then-submit, with a
 * review decision. See the migration's own docblock
 * (2026_09_29_020000_create_job_descriptions.php) for why this uses a
 * Company-Admin decision instead of legacy's manager-hierarchy sign-off and
 * drops the e-signature capture — both forced by real, already-documented
 * Platform schema gaps, not convenience.
 */
class JobDescriptionController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function index(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name,code']);
        abort_if(!$companyRow, 404);

        $meId = $request->attributes->get('platformUser')['id'];

        $mine = $supabase->first('job_descriptions', [
            'company_id' => 'eq.' . $company,
            'user_id' => 'eq.' . $meId,
            'select' => '*',
        ]);

        $isAdmin = $this->canAdministerCompany($request, $company);

        $team = [];
        if ($isAdmin) {
            // The review queue — every job description in this company, not
            // just the caller's own, same "admin sees everyone, member sees
            // only themselves" split every other self-service feature this
            // session built (Weightage, Quarterly) already uses.
            $team = $supabase->get('job_descriptions', [
                'company_id' => 'eq.' . $company,
                'select' => '*,users!job_descriptions_user_id_foreign(name,email)',
                'order' => 'updated_at.desc',
            ]);
        }

        return Inertia::render('Platform/JobDescription/Index', [
            'company' => $companyRow,
            'mine' => $mine,
            'team' => $team,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Save-as-draft or submit-for-review, both through the same form —
     * matches legacy's single form having both actions. Owner-only; the
     * `restrict_job_description_owner_update` trigger is the real boundary
     * (refuses to touch `status` beyond draft/submitted, or any review
     * field), this just turns that into a clean redirect and picks
     * insert-vs-update since there is no upsert-by-unique-key without an
     * `on_conflict` param this client doesn't build (same reasoning as
     * `KpiController::syncQuarterTargets()`).
     */
    public function save(Request $request, string $company)
    {
        $this->ensureCompanyMember($request, $company);

        $request->validate([
            'summary' => 'nullable|string',
            'responsibilities' => 'nullable|string',
            'requirements' => 'nullable|string',
            'competencies' => 'nullable|string',
            'action' => 'required|in:save,submit',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');
        $meId = $request->attributes->get('platformUser')['id'];

        $existing = $supabase->first('job_descriptions', [
            'company_id' => 'eq.' . $company,
            'user_id' => 'eq.' . $meId,
            'select' => 'id,status',
        ]);

        if ($existing && !in_array($existing['status'], ['draft', 'changes_requested'], true)) {
            return back()->with('error', 'Your job description is awaiting review — it can\'t be edited right now.');
        }

        $isSubmit = $request->input('action') === 'submit';

        $payload = [
            'summary' => $request->input('summary'),
            'responsibilities' => $request->input('responsibilities'),
            'requirements' => $request->input('requirements'),
            'competencies' => $request->input('competencies'),
            'status' => $isSubmit ? 'submitted' : 'draft',
        ];

        if ($isSubmit) {
            $payload['submitted_at'] = now()->toIso8601String();
        }

        try {
            if ($existing) {
                $supabase->update('job_descriptions', ['id' => 'eq.' . $existing['id']], $payload, false);
            } else {
                $supabase->insert('job_descriptions', $payload + [
                    'company_id' => $company,
                    'user_id' => $meId,
                ], false);
            }
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not save your job description: ' . $e->getMessage());
        }

        return back()->with('success', $isSubmit ? 'Job description submitted for review.' : 'Draft saved.');
    }

    /**
     * Company Admin's review decision — the Platform's replacement for
     * legacy's manager sign-off (see this feature's own migration docblock).
     */
    public function decide(Request $request, string $company, string $jobDescription)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'decision' => 'required|in:approved,changes_requested',
            'decision_note' => 'nullable|string',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $before = $supabase->first('job_descriptions', [
            'id' => 'eq.' . $jobDescription,
            'company_id' => 'eq.' . $company,
            'select' => 'id,user_id,status',
        ]);

        if (!$before) {
            abort(404, 'That job description does not belong to this company.');
        }

        if ($before['status'] !== 'submitted') {
            return back()->with('error', 'Only a submitted job description can be reviewed.');
        }

        $after = [
            'status' => $request->decision,
            'reviewed_at' => now()->toIso8601String(),
            'reviewed_by' => $request->attributes->get('platformUser')['id'],
            'decision_note' => $request->input('decision_note'),
        ];

        try {
            $supabase->update('job_descriptions', ['id' => 'eq.' . $jobDescription], $after, false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not record the decision: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'decide_job_description', $company, $before['user_id'], [
                'decision' => $request->decision,
            ], 'job_description', $jobDescription, ['status' => $before['status']], ['status' => $after['status']]);
        } catch (\Throwable) {
            return back()->with('error', 'Decision was recorded, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Decision recorded.');
    }
}
