<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\LogsAdminActions;
use App\Http\Controllers\Platform\Concerns\PlatformAuthorization;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Spec §8's "Platform > Support" queue. `support_tickets_insert`'s RLS lets
 * a Company Admin/SLT raise a ticket for their own company; only the Center
 * (Super Admin) can change status/assignment, matching the CAM/health-score
 * write pattern used everywhere else on the HQ side.
 */
class SupportController extends Controller
{
    use LogsAdminActions;
    use PlatformAuthorization;

    public function index(Request $request)
    {
        $this->ensureSuperAdmin($request);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $query = ['select' => '*,companies(name,code)', 'order' => 'created_at.desc', 'limit' => 200];
        if ($request->filled('status')) {
            $query['status'] = 'eq.' . $request->query('status');
        }

        $tickets = $supabase->get('support_tickets', $query);

        return Inertia::render('Platform/Hq/Support/Index', [
            'tickets' => $tickets,
            'filters' => ['status' => $request->query('status', '')],
        ]);
    }

    public function store(Request $request, string $company)
    {
        $role = $this->companyRole($request, $company);
        abort_unless($this->canAdministerCompany($request, $company) || $role === 'slt', 403, 'You cannot raise a support ticket for this company.');

        $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            $supabase->insert('support_tickets', [
                'company_id' => $company,
                'subject' => $request->subject,
                'description' => $request->description,
                'raised_by' => $request->attributes->get('platformUser')['id'] ?? null,
            ], false);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not raise ticket: ' . $e->getMessage());
        }

        return back()->with('success', 'Support ticket raised — the Performix team will follow up.');
    }

    public function update(Request $request, string $ticket)
    {
        $this->ensureSuperAdmin($request);

        $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
            'assigned_to' => 'nullable|uuid',
        ]);

        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        try {
            $supabase->update('support_tickets', ['id' => 'eq.' . $ticket], [
                'status' => $request->status,
                'assigned_to' => $request->assigned_to,
                'updated_at' => now()->toIso8601String(),
            ], false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not update ticket: ' . $e->getMessage());
        }

        return back()->with('success', 'Ticket updated.');
    }
}
