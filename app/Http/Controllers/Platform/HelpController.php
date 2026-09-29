<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Ports legacy's static help.blade.php (score bands, quarter statuses, how
 * score is calculated, quick reference) to the Platform — content only,
 * rewritten to describe the Platform's OWN actual band scheme and quarter
 * statuses (resources/js/lib/scoreStyle.ts's 5 bands; kpi_quarters' 5
 * statuses) rather than copying legacy's different 4-band language verbatim,
 * since this page's whole purpose is to correctly explain what a user will
 * actually see elsewhere in the Platform.
 */
class HelpController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Platform/Help/Index');
    }
}
