<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Honest "not built yet" pages for the two legacy-dashboard features that
 * need genuinely new schema — see this feature's own plan doc for why these
 * are placeholders rather than a faked implementation, matching the exact
 * pattern OnboardingController::comingSoon() already established for
 * "Configure reporting hierarchy"/"Configure ANIRA"/"Configure Telegram".
 * That method is private and ensureCompanyAdmin()-gated (an onboarding-wizard
 * step); these two are reachable from the main sidebar by ANY company
 * member, matching the legacy app where Target Linkages/Job Description are
 * personal or manager features, not admin-only — so they get their own
 * ensureCompanyMember()-gated controller rather than reusing that one.
 */
class PlaceholderController extends Controller
{
    use PlatformAuthorization;

    public function targetLinkages(Request $request, string $company)
    {
        return $this->comingSoon($request, $company, [
            'title' => 'Target Linkages',
            'body' => 'Cascading a target from a manager to their direct reports needs a manager/reports-to relationship, which the Platform schema does not have yet — company_users and department_users record membership and role, but nothing records who reports to whom. This is the same schema gap already tracked under this company\'s Onboarding → "Configure reporting hierarchy" step.',
            'footnote' => 'Not reachable from anywhere else in the Platform yet — nothing here is hidden, it simply is not built.',
        ]);
    }

    public function jobDescription(Request $request, string $company)
    {
        return $this->comingSoon($request, $company, [
            'title' => 'Job Description',
            'body' => 'The legacy app\'s per-employee job description form (summary, responsibilities, requirements, competencies, with HR/jobholder/supervisor sign-off) has no equivalent schema on the Platform yet — there is no job_descriptions table, and building one is a real, separate feature, not a UI on top of an existing column.',
            'footnote' => 'Not reachable from anywhere else in the Platform yet — nothing here is hidden, it simply is not built.',
        ]);
    }

    /**
     * Legacy's Q1-Q4 Evaluation pages are the end-of-year appraisal workflow
     * (self-assessment -> manager scores -> employee sign-off, built on a
     * `performance_reports` table) — the same subsystem the Platform-native
     * SLT Dashboard deliberately did NOT port (see SltDashboardController's
     * own docblock). Building it for real is a whole appraisal feature, not
     * a page; kept honest here rather than faked with static numbers.
     */
    public function performanceEvaluation(Request $request, string $company, string $quarter)
    {
        $label = strtoupper($quarter);

        return $this->comingSoon($request, $company, [
            'title' => $label . ' Evaluation',
            'body' => "The legacy app's end-of-year appraisal workflow (self-assessment, manager scoring, attitude scoring, and employee sign-off per quarter) is built on a performance_reports table the Platform has no equivalent of. This is a real, separate appraisal subsystem to design and build, not a page on top of existing KPI data — the Platform's own Quarterly Progress page already covers per-quarter KPI target/actual tracking, which is a different thing from a formal appraisal.",
            'footnote' => 'Not reachable from anywhere else in the Platform yet — nothing here is hidden, it simply is not built.',
        ]);
    }

    /**
     * Legacy's Quarter Control is a BTS/Richworks-only admin page that locks
     * or opens a quarter platform-wide (bts_only + a named-individual
     * "quarter control" grant, per navigation.ts's quarterControlOnly flag).
     * The Platform's own Quarterly Progress feature already has a per-KPI
     * state machine (not_started -> pending_completion -> completed,
     * Company-Admin-approved) but no company-wide "lock this quarter for
     * everyone" toggle — gated to this company's own admins here rather than
     * Richworks-only, since the Platform has no equivalent of legacy's BTS
     * tier to gate it behind instead.
     */
    public function quarterControl(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name']);
        abort_if(!$companyRow, 404);

        return Inertia::render('Platform/Onboarding/ComingSoon', [
            'company' => $companyRow,
            'title' => 'Quarter Control',
            'body' => 'Legacy\'s Quarter Control locks or opens a financial quarter platform-wide for one specific Richworks-only grant. The Platform\'s own Quarterly Progress feature already has a per-KPI approval state machine (submit for sign-off, Company-Admin approves/rejects), but there is no company-wide "lock this quarter for everyone" switch yet — a real, separate control to design, not a UI on an existing column.',
            'footnote' => 'Not reachable from anywhere else in the Platform yet — nothing here is hidden, it simply is not built.',
        ]);
    }

    private function comingSoon(Request $request, string $company, array $copy)
    {
        $this->ensureCompanyMember($request, $company);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companyRow = $supabase->first('companies', ['id' => 'eq.' . $company, 'select' => 'id,name']);
        abort_if(!$companyRow, 404);

        return Inertia::render('Platform/Onboarding/ComingSoon', [
            'company' => $companyRow,
            'title' => $copy['title'],
            'body' => $copy['body'],
            'footnote' => $copy['footnote'],
        ]);
    }
}
