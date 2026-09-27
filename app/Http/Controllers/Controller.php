<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * True whenever the acting user is BTS — this app's only cross-
     * department/company admin concept — either in their own normal
     * session, or currently impersonating someone via "View As".
     *
     * While impersonating, session('department_code'), role, hr_access,
     * etc. all become the IMPERSONATED employee's (see
     * AdminController::start()), so any permission gate that checks those
     * fields directly loses BTS's own access the moment View As starts.
     * admin_impersonating is set independently of those swapped fields, so
     * checking it here keeps BTS's full access while viewing as anyone.
     */
    protected function isBtsSession(): bool
    {
        if (session('admin_impersonating') === true) {
            return true;
        }

        return strtoupper(trim(session('department_code') ?? '')) === 'BTS';
    }

    /**
     * Named-individual grant, not a role/department change: Suley (RCG COO /
     * RGHB Group COO) was explicitly given standing access to the BTS-only
     * Quarter Control page (both its quarter-override and appraiser-
     * delegation actions), 2026-08-21, at the user's request. Deliberately
     * NOT done by setting her department_code to BTS -- that would also
     * hand her every OTHER isBtsSession()-gated feature (View As, Titan
     * cross-company access, HR attendance, etc.), none of which were asked
     * for. Both of her ids are listed since which one is active depends on
     * which company she's logged into (RCG vs RGHB).
     */
    private const QUARTER_CONTROL_EXTRA_ACCESS_IDS = [
        '8e322638-49a5-4560-b800-8d846c53e7c8', // Suley -- RCG
        '700c2198-4871-45c6-a63f-e2e1ad80e657', // Suley -- RGHB
    ];

    protected function isQuarterControlAuthorized(): bool
    {
        return self::sessionHasQuarterControlAccess();
    }

    /**
     * Static, session-only version of isQuarterControlAuthorized() so
     * non-controller code (HandleInertiaRequests, sharing this as a Layout
     * prop for the React sidebar) can check the same named-individual grant
     * without duplicating the id list a third time -- the Blade sidebar
     * partial still keeps its own copy (see partials/sidebar.blade.php)
     * since it runs before this Inertia-only middleware would apply.
     */
    public static function sessionHasQuarterControlAccess(): bool
    {
        $isBts = session('admin_impersonating') === true
            || strtoupper(trim(session('department_code') ?? '')) === 'BTS';

        return $isBts || in_array(session('employee_uuid'), self::QUARTER_CONTROL_EXTRA_ACCESS_IDS, true);
    }
}
