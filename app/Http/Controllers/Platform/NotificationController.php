<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\SupabaseUserService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The read side of the Platform's `notifications` table — see
 * 2026_09_25_000000_add_notifications_insert_policy for the write side.
 * Inherently personal (RLS already scopes `notifications_select` to
 * `user_id = auth_current_user_id()`), so there's no company-scoping or
 * admin/member split to make here, unlike AuditLogController.
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $notifications = $supabase->get('notifications', [
            'select' => 'id,company_id,title,message,is_read,created_at,type,link,quarter,financial_year',
            'order' => 'created_at.desc',
            'limit' => '50',
        ]);

        return Inertia::render('Platform/Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, string $notification)
    {
        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $supabase->update('notifications', ['id' => 'eq.' . $notification], ['is_read' => true], false);

        return back();
    }

    public function markAllRead(Request $request)
    {
        /** @var SupabaseUserService $supabase */
        $supabase = $request->attributes->get('platformSupabase');

        $supabase->update('notifications', ['is_read' => 'eq.false'], ['is_read' => true], false);

        return back();
    }
}
