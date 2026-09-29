<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Honest "not built yet" pages for legacy-dashboard features still missing
 * on the Platform — matching the exact pattern
 * OnboardingController::comingSoon() already established for "Configure
 * reporting hierarchy"/"Configure ANIRA"/"Configure Telegram".
 *
 * Job Description, Performance Evaluation, and Target Linkages were all here
 * too until they got real implementations — see
 * Platform\JobDescriptionController, Platform\PerformanceController, and
 * Platform\TargetLinkageController.
 */
class PlaceholderController extends Controller
{
    use PlatformAuthorization;

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
}
