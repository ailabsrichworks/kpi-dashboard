<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Controller;
use App\Services\SupabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private SupabaseService $supabase)
    {
    }

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
            ],
            // Set by PlatformAuth on every /platform/* request, null
            // everywhere else — shared globally so the Platform's nav shell
            // (PlatformLayout) can render a consistent, role-aware sidebar
            // on every page without each controller remembering to pass its
            // own copy of "who is this and what can they do."
            'platformUser' => fn () => $request->attributes->get('platformUser'),
            'layout' => fn () => [
                'companyCode' => session('company_code'),
                'companyDisplayName' => session('company_display_name'),
                'departmentCode' => session('department_code'),
                'role' => session('role'),
                'hrAccess' => session('hr_access', false),
                'hasSubordinates' => session('has_subordinates', false),
                'shortName' => session('short_name'),
                'fullName' => session('full_name'),
                'employeeName' => session('employee_name'),
                'salutation' => session('salutation'),
                'position' => session('position'),
                'adminImpersonating' => session('admin_impersonating'),
                // BTS itself is covered by departmentCode === 'BTS' above; this
                // additionally covers the named-individual "Quarter Control"
                // grant (see Controller::QUARTER_CONTROL_EXTRA_ACCESS_IDS) for
                // the React sidebar, which otherwise only knows btsOnly.
                'quarterControlAccess' => Controller::sessionHasQuarterControlAccess(),
                'unreadNotificationCount' => $this->unreadNotificationCount(),
                'themeAccent2' => session('theme_accent2') ?: '#6B9080',
                // Sidebar-specific appearance theme (Settings.tsx's "sidebar"
                // group, independent from the "main" dashboard group above).
                // Mirrors app.blade.php's own --sidebar-* CSS vars, but as
                // real props: those vars only get (re-)embedded in <head> on
                // a full page load, so an SPA navigation after saving a new
                // theme would keep showing the stale one until a hard
                // refresh. Sidebar.tsx applies these directly as inline
                // styles instead, so it's always current.
                'themeSidebarBg' => session('theme_sidebar_bg') ?: '#111111',
                'themeSidebarAccent' => session('theme_sidebar_accent') ?: (session('theme_accent') ?: '#D4AF37'),
                'themeSidebarText' => session('theme_sidebar_text') ?: '#FFFFFF',
            ],
        ];
    }

    /**
     * Mirrors AppServiceProvider's `partials.sidebar` view composer — that
     * composer never fires for Inertia responses, so React needs the same
     * count delivered as a shared prop instead.
     */
    private function unreadNotificationCount(): int
    {
        $employeeId = session('employee_uuid');

        if (!$employeeId) {
            return 0;
        }

        try {
            $rows = $this->supabase->get('notifications', [
                'recipient_employee_id' => 'eq.' . $employeeId,
                'is_read' => 'eq.false',
                'select' => 'id',
            ]) ?? [];

            return count($rows);
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch unread notification count for layout props: ' . $e->getMessage());

            return 0;
        }
    }
}
