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
    /**
     * `type`/`link`/`quarter`/`financialYear` mirror legacy's own
     * notifications columns exactly (see
     * 2026_09_29_010000_add_type_link_quarter_to_notifications.php) — `type`
     * is one of the keys in the shared `resources/js/config/
     * notificationMeta.ts` (the same file legacy's own Notifications page
     * imports), which is what drives the Notifications page's icon/label/tab
     * for this row. All four are optional and default to null: a caller with
     * nothing meaningful to put in them (e.g. AppraiserDelegationController,
     * which has no matching legacy notification type) still gets a working
     * notification — the frontend falls back to DEFAULT_TYPE_META exactly
     * like legacy does for an unrecognized type, never a broken row.
     */
    public function notify(
        SupabaseUserService $supabase,
        string $companyId,
        string $userId,
        string $title,
        string $message,
        ?string $type = null,
        ?string $link = null,
        ?string $quarter = null,
        ?string $financialYear = null,
    ): void {
        try {
            $supabase->insert('notifications', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'link' => $link,
                'quarter' => $quarter,
                'financial_year' => $financialYear,
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
