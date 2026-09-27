<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Spec §10's "Renewal: 43 days" — reads the existing `companies.contract_end_date`
 * (added by 2026_08_28_000000's onboarding intake) rather than a new table;
 * nothing else about a renewal (stage, reminders) exists yet, so this is
 * deliberately just a sorted, filterable list, not a renewal pipeline.
 */
class RenewalController extends Controller
{
    use PlatformAuthorization;

    public function index(Request $request)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $companies = $supabase->get('companies', [
            'contract_end_date' => 'not.is.null',
            'select' => 'id,name,code,status,contract_end_date,subscription_status',
            'order' => 'contract_end_date.asc',
            'limit' => 200,
        ]);

        $now = Carbon::now();
        $rows = collect($companies)->map(function ($c) use ($now) {
            $days = $now->diffInDays(Carbon::parse($c['contract_end_date']), false);
            return $c + ['days_until_renewal' => (int) round($days)];
        })->values();

        return Inertia::render('Platform/Hq/Renewals/Index', ['companies' => $rows]);
    }
}
