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
