export type NotificationCategory = 'approval' | 'appraisal' | 'update';

export const CATEGORY_META: Record<NotificationCategory, { label: string; bg: string }> = {
    approval: { label: 'Approval Needed', bg: '#D4AF37' },
    appraisal: { label: 'Appraisal', bg: '#7A0019' },
    update: { label: 'Team Update', bg: '#475569' },
};

export const TYPE_META: Record<string, { icon: string; label: string; category: NotificationCategory }> = {
    job_description_submitted: { icon: '📋', label: 'Job Description', category: 'update' },
    appraisal_submitted: { icon: '📝', label: 'Appraisal Submitted', category: 'appraisal' },
    appraisal_appraised: { icon: '✅', label: 'Ready to Sign', category: 'appraisal' },
    appraisal_completed: { icon: '🎉', label: 'Completed', category: 'appraisal' },
    kpi_completion_approval: { icon: '✔️', label: 'Completion Approval', category: 'approval' },
    kpi_target_change_approval: { icon: '🎯', label: 'Target Change', category: 'approval' },
    kpi_delete_approval: { icon: '🗑️', label: 'Delete Request', category: 'approval' },
    kpi_actual_approval: { icon: '📊', label: 'Actual Update', category: 'approval' },
    kpi_weightage_approval: { icon: '⚖️', label: 'Weightage Change', category: 'approval' },
};

export const DEFAULT_TYPE_META = { icon: '🔔', label: 'Update', category: 'update' as NotificationCategory };

export function typeMetaFor(type: string) {
    return TYPE_META[type] ?? DEFAULT_TYPE_META;
}
