<?php

namespace App\Http\Middleware;

use App\Services\SupabaseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KpiAuth
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {

        /*
        |--------------------------------------------------------------------------
        | SIMPLE LOGIN CHECK
        |--------------------------------------------------------------------------
        */

        if(
            !session()->has('employee_uuid')
        ){

            return redirect()
                ->route('login')
                ->with(
                    'error',
                    'Please login terlebih dahulu.'
                );
        }

        /*
        |--------------------------------------------------------------------------
        | OPTIONAL AUTO FIX
        |--------------------------------------------------------------------------
        */

        if(
            !session()->has('employee')
        ){

            session([
                'employee' => [
                    'id' => session('employee_uuid'),
                    'role' => session('role'),
                    'short_name' => session('short_name'),
                    'department_code' => session('department_code'),
                    'company_code' => session('company_code'),
                ]
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | APPEARANCE THEME + DISPLAY TITLE (Account Settings)
        |--------------------------------------------------------------------------
        | Cached as flat session keys so every page (via partials/sidebar.blade.php)
        | can read it without its own query. ProfileController::updateTheme()/
        | updateSalutation() overwrite these same keys immediately on save, so a
        | change takes effect without needing to log out/in.
        |
        | Keyed to the CURRENTLY SELECTED employee_uuid (settings_synced_for),
        | not a bare "have we ever synced this session" flag — this used to be
        | settings_synced_v2 => true, which meant switching to a different
        | company dashboard (AuthController::setDashboardSession(), a different
        | employees row with its own theme columns) never re-synced at all:
        | the session just kept whatever theme happened to be cached from
        | whichever employee row was selected FIRST that session, silently
        | wrong for every dashboard switched to afterwards. Comparing against
        | the live employee_uuid instead means a dashboard switch is itself
        | enough to trigger a fresh sync, with no separate reset needed.
        |
        | A failed fetch (Supabase hiccup) deliberately leaves this unset
        | rather than marking it synced anyway — the old code did the latter,
        | which meant one transient failure permanently stranded that session
        | on defaults; leaving it unset means the very next request just
        | retries instead.
        */

        $currentEmployeeUuid = session('employee_uuid');

        if (session('settings_synced_for') !== $currentEmployeeUuid) {
            try {
                $employee = app(SupabaseService::class)->first('employees', [
                    'id'     => 'eq.' . $currentEmployeeUuid,
                    'select' => 'salutation,theme_bg,theme_card,theme_accent,theme_accent2,theme_border,theme_text,theme_sidebar_bg,theme_sidebar_accent,theme_sidebar_text,theme_font_family,theme_font_size',
                ]);

                session([
                    'settings_synced_for'  => $currentEmployeeUuid,
                    'salutation'            => $employee['salutation']            ?? null,
                    'theme_bg'              => $employee['theme_bg']              ?? null,
                    'theme_card'            => $employee['theme_card']            ?? null,
                    'theme_accent'          => $employee['theme_accent']          ?? null,
                    'theme_accent2'         => $employee['theme_accent2']         ?? null,
                    'theme_border'          => $employee['theme_border']          ?? null,
                    'theme_text'            => $employee['theme_text']            ?? null,
                    'theme_sidebar_bg'      => $employee['theme_sidebar_bg']      ?? null,
                    'theme_sidebar_accent'  => $employee['theme_sidebar_accent']  ?? null,
                    'theme_sidebar_text'    => $employee['theme_sidebar_text']    ?? null,
                    'theme_font_family'     => $employee['theme_font_family']     ?? null,
                    'theme_font_size'       => $employee['theme_font_size']       ?? null,
                ]);
            } catch (\Throwable) {
                // Don't mark this employee as synced — retry on the next request.
            }
        }

        /*
        |--------------------------------------------------------------------------
        | COMPANY LOGO (sidebar brand tile)
        |--------------------------------------------------------------------------
        | companies.logo_url wins when the database has one set for this
        | company; CompanyLogoService falls back to a public/images file
        | otherwise. Cached the same way as the theme sync above and for the
        | same reason — keyed to the live company_code so switching company
        | dashboards re-fetches instead of keeping whichever logo happened to
        | be cached from the first company selected this session.
        */

        $currentCompanyCode = session('company_code');

        if (session('company_logo_synced_for') !== $currentCompanyCode) {
            $logoUrl = null;
            try {
                $company = app(SupabaseService::class)->first('companies', [
                    'code'   => 'eq.' . $currentCompanyCode,
                    'select' => 'logo_url',
                ]);
                $logoUrl = $company['logo_url'] ?? null;
            } catch (\Throwable) {
                // Unlike the theme sync above, a failure here is marked
                // synced anyway (with no logo_url) rather than retried —
                // this column doesn't exist on every deployment yet, and
                // CompanyLogoService's public/images fallback covers that
                // case fine on its own, so retrying every request forever
                // against a column that will never appear would just be
                // wasted latency, not a real chance at self-healing.
            }

            session([
                'company_logo_synced_for' => $currentCompanyCode,
                'company_logo_url'        => $logoUrl,
            ]);
        }

        return $next($request);
    }
}
