<?php

namespace App\Console\Commands;

use App\Services\AppraiserDelegationService;
use App\Services\SupabaseService;
use Illuminate\Console\Command;

/**
 * One-time data fix: before the "manager signs Section 7 without ticking
 * Confirmation/Salary Review/Promotion => nothing required from VP/SLT"
 * rule existed, every manager submission notified the VP/SLT chain with
 * type 'appraisal_appraised' regardless of whether anything was actually
 * ticked. Once that rule shipped, NEW submissions correctly send
 * 'appraisal_completed' instead — but rows created before the fix are stuck
 * showing under "Ready to Sign" forever, even though the report itself
 * already reads "Not required — already complete" everywhere else on the
 * page.
 *
 * Deliberately does NOT filter by matching on the notification's stored
 * title text — the wording has changed across code revisions, and
 * `subject_employee_id` is reused for a differently-shaped notification
 * (the appraisee's own "ready for your signature" prompt, whose subject is
 * the MANAGER, not the appraisee) that can coincidentally share an
 * appraisee's id as someone else's manager. The only reliable test is
 * recomputing each report's real Section 7 chain (the same
 * AppraiserDelegationService::resolveSection7Chain() production code uses)
 * and checking whether the notification's recipient is actually in it.
 */
class ReclassifyCompletedAppraisalNotifications extends Command
{
    protected $signature = 'performance:reclassify-completed-notifications {--dry-run : Report what would change without writing anything}';

    protected $description = 'Reclassifies stale "ready for your remarks" VP/SLT notifications to "completed" for reports the manager already settled with nothing ticked';

    public function handle(SupabaseService $supabase, AppraiserDelegationService $delegations): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $reports = $supabase->get('performance_reports', [
            'select' => 'employee_id,financial_year,quarter,form_data',
        ]) ?? [];

        $notRequiredReports = [];
        foreach ($reports as $report) {
            $formData = $report['form_data'] ?? [];
            $settledWithNothingTicked = !empty($formData['s7_manager_sig'])
                && empty($formData['s7_manager_confirmation'])
                && empty($formData['s7_manager_salary_review'])
                && empty($formData['s7_manager_promotion']);

            if ($settledWithNothingTicked) {
                $notRequiredReports[] = $report;
            }
        }

        if (empty($notRequiredReports)) {
            $this->info('No reports found where the manager settled Section 7 with nothing ticked. Nothing to do.');
            return self::SUCCESS;
        }

        $employeeCache = [];
        $getEmployee = function (string $id) use ($supabase, &$employeeCache) {
            if (!array_key_exists($id, $employeeCache)) {
                $employeeCache[$id] = $supabase->first('employees', ['id' => 'eq.' . $id, 'select' => '*']);
            }
            return $employeeCache[$id];
        };

        $fixed = 0;

        foreach ($notRequiredReports as $report) {
            $employeeId = $report['employee_id'];
            $quarter = $report['quarter'];
            $employee = $getEmployee($employeeId);

            if (empty($employee)) {
                continue;
            }

            $chain = $delegations->resolveSection7Chain($employee, $getEmployee);
            // Index 0 is the manager (Part A, who just submitted) — VP/SLT
            // (the only ones who'd have gotten an escalation notification)
            // are everything after that.
            $chainRecipients = array_column(array_slice($chain, 1), 'id');

            if (empty($chainRecipients)) {
                continue;
            }

            $staleRows = $supabase->get('notifications', [
                'subject_employee_id'   => 'eq.' . $employeeId,
                'quarter'               => 'eq.' . $quarter,
                'type'                  => 'eq.appraisal_appraised',
                'recipient_employee_id' => 'in.(' . implode(',', $chainRecipients) . ')',
                'select'                => 'id,recipient_employee_id,title',
            ]) ?? [];

            foreach ($staleRows as $row) {
                $this->line(($dryRun ? '[dry-run] ' : '') . "Reclassifying notification {$row['id']} ({$row['title']})");

                if (!$dryRun) {
                    $supabase->update('notifications', ['id' => 'eq.' . $row['id']], [
                        'type'    => 'appraisal_completed',
                        'title'   => str_replace(
                            [' appraisal is ready for your remarks', ' appraisal needs your Section 7 remarks'],
                            ' appraisal is complete',
                            $row['title'] ?? ''
                        ),
                        'message' => 'Section 7 was signed with nothing flagged — no action needed from you.',
                    ]);
                }

                $fixed++;
            }
        }

        $this->info(($dryRun ? 'Would reclassify ' : 'Reclassified ') . "{$fixed} stale notification(s).");

        return self::SUCCESS;
    }
}
