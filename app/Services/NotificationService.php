<?php

namespace App\Services;

use App\Mail\AppNotificationMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Creates in-app notifications (notifications table) and, when the recipient
 * has a linked Telegram account or a known email, pushes the same message
 * there too. Used when a subordinate submits their Job Description or a
 * quarterly appraisal self-assessment, to tell their manager/VP/SLT chain
 * it's ready for review.
 */
class NotificationService
{
    public function __construct(
        private SupabaseService $supabase,
        private TelegramService $telegram,
        private AppraiserDelegationService $delegations,
    ) {
    }

    /**
     * Walks up to 3 hops from the given employee — Manager, then that
     * Manager's own approver (VP), then that VP's own approver (SLT) —
     * using the exact same per-role field priority (manager_id/vp_id, with
     * reports_to_id as fallback) and BTS appraiser-delegation substitution
     * as PerformanceController::resolveAppraiserLevel(), via the shared
     * AppraiserDelegationService::nextParentId(). Both used to walk
     * reports_to_id only, which disagreed with resolveAppraiserLevel
     * whenever manager_id/vp_id was set but reports_to_id wasn't pointing
     * at the same person — notifying one person while access control
     * authorized a different one. Stops early if the chain is shorter or
     * loops back on itself.
     */
    public function appraiserChainFor(string $employeeId): array
    {
        $chain = [];
        $currentId = $employeeId;

        for ($i = 0; $i < 3; $i++) {
            $current = $this->supabase->first('employees', [
                'id'     => 'eq.' . $currentId,
                'select' => 'role,manager_id,vp_id,reports_to_id',
            ]);

            if (empty($current)) {
                break;
            }

            $parentId = $this->delegations->nextParentId($current);
            if (empty($parentId) || in_array($parentId, $chain, true)) {
                break;
            }

            $chain[]   = $parentId;
            $currentId = $parentId;
        }

        return $chain;
    }

    /**
     * Creates a notification row per recipient and, if linked, sends them a
     * Telegram message. Each recipient is handled independently so one
     * failure (bad row, Telegram down) doesn't block the rest.
     */
    public function notify(
        array $recipientEmployeeIds,
        string $type,
        array $subject,
        string $title,
        ?string $message = null,
        ?string $link = null,
        ?string $quarter = null,
        ?string $financialYear = null
    ): void {
        $recipientEmployeeIds = array_values(array_unique(array_filter($recipientEmployeeIds)));

        foreach ($recipientEmployeeIds as $recipientId) {
            try {
                $this->supabase->insert('notifications', [
                    'recipient_employee_id' => $recipientId,
                    'type'                  => $type,
                    'subject_employee_id'   => $subject['id'] ?? null,
                    'subject_name'          => $subject['name'] ?? 'Someone',
                    'quarter'               => $quarter,
                    'financial_year'        => $financialYear,
                    'title'                 => $title,
                    'message'               => $message,
                    'link'                  => $link,
                    // Explicit, not left to the column default — a default of
                    // NULL (as opposed to false) is what made "Mark all as
                    // read" and the unread badge disagree forever, since
                    // Postgres/PostgREST's `eq.false` filter never matches
                    // NULL. See NotificationController::markAllRead().
                    'is_read'               => false,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to create notification', ['recipient' => $recipientId, 'error' => $e->getMessage()]);
            }

            $this->sendTelegram($recipientId, $title, $message, $link);
            $this->sendEmail($recipientId, $title, $message, $link);
        }
    }

    /**
     * Marks a specific approval request's notification(s) as read for the
     * approver who just acted on it — approving/rejecting from the Approval
     * Center is a different path than clicking the notification row itself,
     * and until now only clicking the row (or "Mark all as read") ever set
     * `is_read`. That left the Approvals badge stuck showing requests the
     * approver had already resolved, which looked exactly like the earlier
     * "Mark all as read doesn't work" bug even though the count itself was
     * otherwise accurate.
     *
     * There's no `approval_id` column on `notifications` to join on — every
     * approval notification's `link` embeds the request id as
     * `?highlight=<id>` (see KpiController::notifyApprover()), so that's
     * what this matches against instead of adding a new column.
     */
    public function markApprovalResolved(string $recipientId, string $approvalId): void
    {
        try {
            $this->supabase->update('notifications', [
                'recipient_employee_id' => 'eq.' . $recipientId,
                'link'                  => 'ilike.*highlight=' . $approvalId . '*',
            ], ['is_read' => true]);
        } catch (\Throwable $e) {
            Log::error('Failed to mark approval notification as read', ['recipient' => $recipientId, 'approval' => $approvalId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Marks an appraiser's own incoming notification(s) about one employee's
     * quarter as read the moment they actually submit their part — same
     * reasoning as markApprovalResolved() above: a manager/VP/SLT can reach
     * the report from the Sign-off Status box, a bookmark, or Approvals-style
     * navigation just as easily as by clicking the notification itself, and
     * submitting their section is unambiguous proof they've dealt with it.
     * Matched on recipient + subject + quarter rather than a row id, since
     * more than one notification type can be the one that's now resolved
     * (e.g. a VP might hold both an old 'appraisal_appraised' and, if
     * re-notified, wouldn't need two separate calls to clear both).
     */
    public function markAppraisalResolved(string $recipientId, string $subjectEmployeeId, string $quarter, array $types): void
    {
        if (empty($types)) {
            return;
        }

        try {
            $this->supabase->update('notifications', [
                'recipient_employee_id' => 'eq.' . $recipientId,
                'subject_employee_id'   => 'eq.' . $subjectEmployeeId,
                'quarter'               => 'eq.' . $quarter,
                'type'                  => 'in.(' . implode(',', $types) . ')',
            ], ['is_read' => true]);
        } catch (\Throwable $e) {
            Log::error('Failed to mark appraisal notification as read', ['recipient' => $recipientId, 'subject' => $subjectEmployeeId, 'quarter' => $quarter, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Best-effort email twin of the in-app row — looks the recipient's email
     * straight up on `employees` (the same field ProfileController lets them
     * edit themselves), so there's no separate opt-in/linking step the way
     * Telegram needs. Silently skipped when the employee has no email on
     * file, and never lets a mail failure affect the other recipients or the
     * in-app notification itself.
     */
    private function sendEmail(string $recipientId, string $title, ?string $message, ?string $link): void
    {
        try {
            $employee = $this->supabase->first('employees', [
                'id'     => 'eq.' . $recipientId,
                'select' => 'email,short_name,full_name',
            ]);

            if (empty($employee['email'])) {
                return;
            }

            Mail::to($employee['email'])->send(new AppNotificationMail(
                $employee['short_name'] ?? $employee['full_name'] ?? 'there',
                $title,
                $message,
                $link,
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to send notification email', ['recipient' => $recipientId, 'error' => $e->getMessage()]);
        }
    }

    private function sendTelegram(string $recipientId, string $title, ?string $message, ?string $link): void
    {
        // The whole body is wrapped, not just sendMessage() -- telegramChatIdFor()
        // itself makes two Supabase calls, and a failure there (a transient
        // Supabase hiccup, a timeout) must never crash the primary action any
        // more than a failure inside sendMessage() would. Mirrors sendEmail()'s
        // own full-body try/catch just above.
        try {
            $chatId = $this->telegramChatIdFor($recipientId);
            if (!$chatId) {
                return;
            }

            $text = "<b>{$title}</b>";
            if ($message) {
                $text .= "\n" . $message;
            }

            $keyboard = $link ? [['text' => 'Open', 'url' => $link]] : null;

            $this->telegram->sendMessage($chatId, $text, $keyboard);
        } catch (\Throwable $e) {
            Log::error('Failed to send Telegram notification', ['recipient' => $recipientId, 'error' => $e->getMessage()]);
        }
    }

    private function telegramChatIdFor(string $employeeId): ?int
    {
        $role = $this->supabase->first('user_company_roles', [
            'employee_id' => 'eq.' . $employeeId,
            'is_active'   => 'eq.true',
            'select'      => 'user_id',
        ]);

        if (empty($role['user_id'])) {
            return null;
        }

        $user = $this->supabase->first('users', [
            'id'     => 'eq.' . $role['user_id'],
            'select' => 'telegram_chat_id',
        ]);

        return !empty($user['telegram_chat_id']) ? (int) $user['telegram_chat_id'] : null;
    }
}
