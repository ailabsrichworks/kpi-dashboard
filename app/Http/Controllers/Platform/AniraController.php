<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Services\AiService;
use App\Services\AuthorizedDataScope;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The Platform's ANIRA chat endpoint — the tenant-aware permission
 * resolution this class exists for is exactly: WHO is asking (the caller's
 * own Supabase Auth token from their session — never trusted from the
 * request body), WHICH company (an optional filter, but only ever honoured
 * if it's in the caller's OWN RLS-filtered company list — a `company_id`
 * the caller isn't authorized for is rejected outright, not silently
 * dropped), WHAT ROLE / WHAT DATA (`AuthorizedDataScope::assistantContext()`
 * — every row in it already passed through Postgres RLS as this specific
 * user; an SLT gets a different answer than an Executive to the identical
 * question, and neither ever sees another company's data). Only that
 * already-authorized dataset is handed to `AiService::chatForPlatform()`.
 *
 * There is no code path here that looks up "this employee's KPI" by id for
 * the model to see directly — every read goes through `AuthorizedDataScope`,
 * which is constructed from nothing but the caller's own token and can only
 * ever return what RLS already decided this caller may read.
 */
class AniraController extends Controller
{
    use LogsAdminActions;

    public function index(Request $request)
    {
        $scope = AuthorizedDataScope::fromSession();

        abort_if(!$scope, 401, 'Please log in first.');

        return Inertia::render('Platform/Anira/Chat', [
            'me' => $scope->me(),
            'companies' => $scope->companies(),
        ]);
    }

    public function chat(Request $request, AiService $ai)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'company_id' => 'nullable|uuid',
        ]);

        $scope = AuthorizedDataScope::fromSession();

        if (!$scope) {
            return response()->json(['success' => false, 'message' => 'Please log in first.'], 401);
        }

        // A company_id the caller supplies is never trusted as-is — it must
        // appear in their OWN RLS-filtered company list, or the request is
        // rejected outright. Silently ignoring an unauthorized company_id
        // (falling back to "no filter") would still leak "this id exists and
        // is a company" through the response's absence of an error; an
        // explicit 403 is the honest answer.
        if ($request->company_id) {
            $authorizedCompanyIds = array_column($scope->companies(), 'id');

            if (!in_array($request->company_id, $authorizedCompanyIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to ask about that company.',
                ], 403);
            }
        }

        $context = $scope->assistantContext($request->company_id);

        try {
            $reply = $ai->chatForPlatform($context, [
                ['role' => 'user', 'content' => $request->message],
            ]);
        } catch (\Throwable) {
            return response()->json(['success' => false, 'message' => 'ANIRA is unavailable. Please try again.'], 500);
        }

        // "AI/ANIRA data access" (requirement #8) — best-effort: chat is a
        // hot path and must never fail on a logging hiccup. Doesn't record
        // the message/reply text itself (a plain audit log is the wrong
        // place for chat content); what matters for audit purposes is WHO
        // accessed WHICH company's data through ANIRA, not what they asked —
        // every row of data the reply could possibly draw on already passed
        // through `AuthorizedDataScope`/RLS before it got here.
        $this->logBestEffort($request, 'anira_chat', $request->company_id, null, [
            'message_length' => strlen($request->message),
        ], 'ai_chat');

        return response()->json(['success' => true, 'reply' => $reply]);
    }
}
