<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * The Platform's real notification write path — see
 * 2026_09_25_000000_add_notifications_insert_policy's docblock for why this
 * has to go through the caller's own RLS-respecting SupabaseUserService
 * (notifications is a guarded, tenant-owned table; no service-role bypass
 * exists or should exist here).
 *
 * Best-effort like AuditLogService::recordBestEffort() — a notification
 * failing to send must never block the real action it's attached to (e.g.
 * WeightageController::approve() has already applied the weight change by
 * the time this runs; a notification hiccup shouldn't turn that into a user-
 * facing error).
 */
class PlatformNotificationService
{
    public function notify(SupabaseUserService $supabase, string $companyId, string $userId, string $title, string $message): void
    {
        try {
            $supabase->insert('notifications', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
            ], false);
        } catch (\Throwable $e) {
            Log::warning('Platform notification failed to send', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
