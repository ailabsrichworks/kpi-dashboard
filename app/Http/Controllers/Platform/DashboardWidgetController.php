<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\DashboardWidgetService;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;

/**
 * Saves a company's own customizable dashboard layout —
 * `ensureCompanyAdmin()` matches this feature's own design decision: one
 * shared layout per company, only the Company Admin edits it, everyone in
 * that company sees the same arrangement (see
 * 2026_09_01_010000_create_company_dashboard_widgets.php's own docblock).
 *
 * Replace-wholesale (delete then insert) rather than diffing against the
 * existing rows — the whole layout is always sent as one ordered list from
 * the "Edit dashboard" UI, so there's no partial-update case to reconcile.
 */
class DashboardWidgetController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function updateLayout(Request $request, string $company)
    {
        $this->ensureCompanyAdmin($request, $company);

        $request->validate([
            'widgets' => 'present|array|max:' . count(DashboardWidgetService::AVAILABLE_WIDGETS),
            'widgets.*' => 'string|in:' . implode(',', DashboardWidgetService::AVAILABLE_WIDGETS),
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            $supabase->delete('company_dashboard_widgets', ['company_id' => 'eq.' . $company]);

            foreach (array_values($request->widgets) as $position => $widgetType) {
                $supabase->insert('company_dashboard_widgets', [
                    'company_id' => $company,
                    'widget_type' => $widgetType,
                    'position' => $position,
                ]);
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not save dashboard layout: ' . $e->getMessage());
        }

        try {
            $this->logCompanyAction($request, 'update_dashboard_layout', $company, null, ['widgets' => $request->widgets], 'company_dashboard_widgets', $company);
        } catch (\Throwable) {
            return back()->with('error', 'Layout was saved, but the action could not be logged — contact support before continuing.');
        }

        return back()->with('success', 'Dashboard layout updated.');
    }
}
