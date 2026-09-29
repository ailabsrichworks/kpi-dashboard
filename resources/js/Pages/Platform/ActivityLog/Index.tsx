import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { EmptyState } from '@/Components/Platform/ui';
import { ChecklistIcon } from '@/Components/Platform/Icons';
import { dateKey, dateDividerLabel, formatDate, formatTime } from '@/lib/dates';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface LogRow {
    id: string;
    action: string;
    target_type: string | null;
    target_id: string | null;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    occurred_at: string;
}

interface ActivityLogPageProps {
    company: Company;
    logs: LogRow[];
    [key: string]: unknown;
}

/**
 * Ports legacy's ActivityLog.tsx design (date-divider timeline, colored
 * dot+badge per action, Timeline/Report view toggle, non-zero stat cards) —
 * the exact "make sure same... design clone like Richworks" ask — onto the
 * Platform's own, real action vocabulary. Legacy hand-reconstructs its
 * ~10 event types from `kpis`/`kpi_histories`/`kpi_update_approvals`/etc
 * because the legacy schema has no unified audit log; the Platform doesn't
 * need that — every one of these ~35 actions already comes from one real
 * table, `admin_action_logs` (AuditLogService/LogsAdminActions), which is
 * what ActivityLogController already reads. This file only adds the visual
 * language on top of data that was already correct.
 *
 * There's no separate "who" column, unlike legacy's — this page is already
 * scoped to the caller's own actions (ActivityLogController filters
 * actor_user_id = me), so a per-row "who" would always read the same name;
 * legacy shows it too, but it's a leftover, not a distinction that carries
 * information here.
 */
const ACTION_META: Record<string, { label: string; emoji: string; bg: string; text: string; dot: string }> = {
    login: { label: 'Logged In', emoji: '🔓', bg: '#D1FAE5', text: '#047857', dot: 'bg-emerald-500' },
    logout: { label: 'Logged Out', emoji: '🔒', bg: '#F1F5F9', text: '#64748B', dot: 'bg-slate-400' },
    login_failed: { label: 'Login Failed', emoji: '⚠️', bg: '#FEE2E2', text: '#DC2626', dot: 'bg-red-500' },

    create_kpi: { label: 'KPI Created', emoji: '📝', bg: '#FBF5EF', text: '#6B3F2A', dot: 'bg-[#6B3F2A]' },
    update_kpi: { label: 'KPI Edited', emoji: '✏️', bg: '#EEF2FF', text: '#4338CA', dot: 'bg-indigo-500' },
    change_kpi_target: { label: 'Target Changed', emoji: '🎯', bg: '#EEF2FF', text: '#4338CA', dot: 'bg-indigo-500' },
    create_kpi_category: { label: 'Category Created', emoji: '🗂️', bg: '#FBF5EF', text: '#6B3F2A', dot: 'bg-[#6B3F2A]' },
    apply_kpi_template: { label: 'Template Applied', emoji: '📋', bg: '#FBF5EF', text: '#6B3F2A', dot: 'bg-[#6B3F2A]' },
    grant_kpi_access: { label: 'KPI Shared', emoji: '🔗', bg: '#F3E8FF', text: '#7C3AED', dot: 'bg-purple-500' },
    revoke_kpi_access: { label: 'KPI Share Removed', emoji: '🔗', bg: '#FFE4E6', text: '#BE123C', dot: 'bg-rose-500' },

    submit_kpi_quarter_completion: { label: 'Completion Submitted', emoji: '📤', bg: '#FEF3C7', text: '#B45309', dot: 'bg-amber-500' },
    approve_kpi_quarter_completion: { label: 'Quarter Approved', emoji: '✅', bg: '#D1FAE5', text: '#047857', dot: 'bg-emerald-500' },
    reject_kpi_quarter_completion: { label: 'Quarter Rejected', emoji: '❌', bg: '#FEE2E2', text: '#DC2626', dot: 'bg-red-500' },
    request_kpi_quarter_actual_change: { label: 'Change Requested', emoji: '🔄', bg: '#F3E8FF', text: '#7C3AED', dot: 'bg-purple-500' },
    approve_kpi_quarter_actual_change: { label: 'Change Approved', emoji: '✅', bg: '#D1FAE5', text: '#047857', dot: 'bg-emerald-500' },
    reject_kpi_quarter_actual_change: { label: 'Change Rejected', emoji: '❌', bg: '#FEE2E2', text: '#DC2626', dot: 'bg-red-500' },

    allocate_kpi_weight: { label: 'Weight Allocated', emoji: '⚖️', bg: '#FBF5EF', text: '#6B3F2A', dot: 'bg-[#6B3F2A]' },
    request_kpi_weight_change: { label: 'Weight Change Requested', emoji: '📤', bg: '#FEF3C7', text: '#B45309', dot: 'bg-amber-500' },
    approve_kpi_weight_change: { label: 'Weight Change Approved', emoji: '✅', bg: '#D1FAE5', text: '#047857', dot: 'bg-emerald-500' },
    reject_kpi_weight_change: { label: 'Weight Change Rejected', emoji: '❌', bg: '#FEE2E2', text: '#DC2626', dot: 'bg-red-500' },

    create_task: { label: 'Task Created', emoji: '🗒️', bg: '#CCFBF1', text: '#0F766E', dot: 'bg-teal-500' },
    update_task: { label: 'Task Edited', emoji: '✏️', bg: '#CFFAFE', text: '#0E7490', dot: 'bg-cyan-500' },
    delete_task: { label: 'Task Deleted', emoji: '🗑️', bg: '#FFE4E6', text: '#BE123C', dot: 'bg-rose-500' },
    link_task_kpis: { label: 'Task Linked to KPI', emoji: '🔗', bg: '#CCFBF1', text: '#0F766E', dot: 'bg-teal-500' },
    generate_task_ai_summary: { label: 'AI Summary Generated', emoji: '✨', bg: '#CFFAFE', text: '#0E7490', dot: 'bg-cyan-500' },

    create_department: { label: 'Department Created', emoji: '🏢', bg: '#FFEDD5', text: '#C2410C', dot: 'bg-orange-500' },
    invite_department_user: { label: 'User Invited', emoji: '📧', bg: '#FFEDD5', text: '#C2410C', dot: 'bg-orange-500' },
    invite_company_admin: { label: 'Admin Invited', emoji: '📧', bg: '#FFEDD5', text: '#C2410C', dot: 'bg-orange-500' },
    update_user_role: { label: 'Role Changed', emoji: '🔧', bg: '#EEF2FF', text: '#4338CA', dot: 'bg-indigo-500' },
    suspend_user: { label: 'User Suspended', emoji: '⛔', bg: '#FEE2E2', text: '#DC2626', dot: 'bg-red-500' },
    reactivate_user: { label: 'User Reactivated', emoji: '✅', bg: '#D1FAE5', text: '#047857', dot: 'bg-emerald-500' },
    create_user_accounts: { label: 'Accounts Created', emoji: '👥', bg: '#FFEDD5', text: '#C2410C', dot: 'bg-orange-500' },
    import_data: { label: 'Data Imported', emoji: '📥', bg: '#FFEDD5', text: '#C2410C', dot: 'bg-orange-500' },
    create_role: { label: 'Role Created', emoji: '🏷️', bg: '#EEF2FF', text: '#4338CA', dot: 'bg-indigo-500' },
    delete_role: { label: 'Role Deleted', emoji: '🏷️', bg: '#FFE4E6', text: '#BE123C', dot: 'bg-rose-500' },

    export_audit_log: { label: 'Log Exported', emoji: '📄', bg: '#F1F5F9', text: '#64748B', dot: 'bg-slate-400' },
    anira_chat: { label: 'Asked ANIRA', emoji: '✨', bg: '#CFFAFE', text: '#0E7490', dot: 'bg-cyan-500' },
    telegram_link_code_generated: { label: 'Telegram Link Started', emoji: '🔗', bg: '#F1F5F9', text: '#64748B', dot: 'bg-slate-400' },
    telegram_disconnected: { label: 'Telegram Disconnected', emoji: '🔗', bg: '#F1F5F9', text: '#64748B', dot: 'bg-slate-400' },
};

const DEFAULT_META = { label: '', emoji: '🔔', bg: '#F1F5F9', text: '#64748B', dot: 'bg-slate-400' };

function metaFor(action: string): { label: string; emoji: string; bg: string; text: string; dot: string } {
    const meta = ACTION_META[action];
    if (meta) return meta;
    return { ...DEFAULT_META, label: action.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) };
}

function diffKeys(before: LogRow['before'], after: LogRow['after']): string[] {
    if (!before || !after) return [];
    return Object.keys(after).filter((k) => JSON.stringify(before[k]) !== JSON.stringify(after[k]));
}

function detailFor(log: LogRow): string {
    const changed = diffKeys(log.before, log.after);
    if (changed.length > 0) return `Changed: ${changed.join(', ')}`;
    if (log.target_type) return log.target_type.replace(/_/g, ' ');
    return '';
}

/**
 * Legacy's row always shows a bold subject line (the KPI's title) next to
 * the action badge — the Platform's `admin_action_logs` has no dedicated
 * "subject name" column, but the same information is already sitting in the
 * `before`/`after` JSON every write already logs (KpiController::store()'s
 * own `after` payload includes `name`; TaskController::store()'s includes
 * `title`) — read from real data already there, not fabricated.
 */
function subjectOf(log: LogRow): string | null {
    const name = (log.after?.name ?? log.before?.name ?? log.after?.title ?? log.before?.title) as string | undefined;
    return name ?? null;
}

function groupConsecutiveByDate(logs: LogRow[]): [string, LogRow[]][] {
    const groups: [string, LogRow[]][] = [];
    for (const log of logs) {
        const key = dateKey(log.occurred_at);
        const last = groups[groups.length - 1];
        if (last && last[0] === key) {
            last[1].push(log);
        } else {
            groups.push([key, [log]]);
        }
    }
    return groups;
}

export default function ActivityLogIndex({ company, logs }: ActivityLogPageProps) {
    const [viewMode, setViewMode] = useState<'timeline' | 'report'>('timeline');
    const [filter, setFilter] = useState<string>('');
    const { platformUser } = usePage<{ platformUser: { name: string } }>().props;

    const actionCounts = logs.reduce<Record<string, number>>((acc, l) => {
        acc[l.action] = (acc[l.action] ?? 0) + 1;
        return acc;
    }, {});

    const presentActions = Object.keys(actionCounts).sort((a, b) => actionCounts[b] - actionCounts[a]);
    const visible = filter ? logs.filter((l) => l.action === filter) : logs;
    const grouped = groupConsecutiveByDate(visible);

    return (
        <PlatformLayout
            title="User Activity Log"
            company={company}
            actions={
                <div className="flex items-center gap-3">
                    <div className="text-right shrink-0">
                        <p className="text-[9px] text-slate-400 uppercase tracking-wide">Total Events</p>
                        <p className="text-lg font-black text-slate-800 leading-none">{logs.length}</p>
                    </div>
                    <select
                        value={viewMode}
                        onChange={(e) => setViewMode(e.target.value as 'timeline' | 'report')}
                        className="bg-[#D4AF37] hover:bg-[#c19c2f] text-[#1a1a1a] px-3 py-2 rounded-xl shadow font-black text-[11px] transition cursor-pointer border-none"
                    >
                        <option value="timeline">🕒 Timeline View</option>
                        <option value="report">📄 Report View</option>
                    </select>
                </div>
            }
        >
            {logs.length === 0 ? (
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm px-6 py-16 text-center">
                    <EmptyState
                        icon={<ChecklistIcon className="w-8 h-8" />}
                        title="No activity yet"
                        description="Actions you take on this company's KPIs, tasks, and quarters — creating, editing, submitting, requesting changes — will show up here."
                    />
                </div>
            ) : (
                <div className="space-y-4">
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm px-4 py-3">
                        <div className="flex flex-wrap gap-2 items-center">
                            <span className="text-[10px] text-slate-400 uppercase tracking-wider font-semibold mr-1">Filter:</span>
                            <button
                                onClick={() => setFilter('')}
                                className={`text-[11px] px-3 py-1 rounded-full border font-semibold transition ${
                                    filter === '' ? 'bg-[#6B3F2A] text-white border-[#6B3F2A]' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'
                                }`}
                            >
                                All Activities
                            </button>
                            {presentActions.map((action) => (
                                <button
                                    key={action}
                                    onClick={() => setFilter(action)}
                                    className={`text-[11px] px-3 py-1 rounded-full border font-semibold transition ${
                                        filter === action ? 'bg-[#6B3F2A] text-white border-[#6B3F2A]' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'
                                    }`}
                                >
                                    {metaFor(action).label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {viewMode === 'timeline' ? (
                        <div>
                            <div className="space-y-4">
                                {grouped.map(([key, dayLogs]) => (
                                    <div key={key}>
                                        <div className="flex items-center gap-3 mb-3">
                                            <div className="h-px flex-1 bg-slate-200" />
                                            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider px-2">
                                                {dateDividerLabel(dayLogs[0].occurred_at)}
                                            </span>
                                            <div className="h-px flex-1 bg-slate-200" />
                                        </div>

                                        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden divide-y divide-slate-100">
                                            {dayLogs.map((log) => {
                                                const meta = metaFor(log.action);
                                                const detail = detailFor(log);
                                                const subject = subjectOf(log);
                                                return (
                                                    <div key={log.id} className="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 transition">
                                                        <div className={`mt-1.5 shrink-0 w-2.5 h-2.5 rounded-full ${meta.dot}`} />
                                                        <div className="flex-1 min-w-0">
                                                            <div className="flex flex-wrap items-center gap-2 mb-0.5">
                                                                <span
                                                                    className="text-[10px] font-black px-2 py-0.5 rounded-full"
                                                                    style={{ background: meta.bg, color: meta.text }}
                                                                >
                                                                    {meta.emoji} {meta.label}
                                                                </span>
                                                                {subject && <span className="text-[11px] font-semibold text-slate-800 truncate">{subject}</span>}
                                                            </div>
                                                            {detail && <p className="text-[11px] text-slate-500 truncate">{detail}</p>}
                                                        </div>
                                                        <div className="shrink-0 text-right">
                                                            <p className="text-[11px] font-semibold text-slate-700">{platformUser.name}</p>
                                                            <p className="text-[10px] text-slate-400">{formatTime(log.occurred_at)}</p>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                                {grouped.length === 0 && <p className="text-center text-[11px] text-slate-400 py-8">Nothing in this category yet.</p>}
                            </div>

                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                                {presentActions.map((action) => {
                                    const meta = metaFor(action);
                                    return (
                                        <button
                                            key={action}
                                            onClick={() => setFilter(action)}
                                            className="bg-white rounded-2xl border border-slate-200 shadow-sm p-3 hover:shadow-md transition flex items-center gap-3 text-left"
                                        >
                                            <span className="text-xl">{meta.emoji}</span>
                                            <div>
                                                <p className="text-[10px] text-slate-500">{meta.label}</p>
                                                <p className="text-lg font-black text-slate-800">{actionCounts[action]}</p>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div className="bg-white rounded-2xl border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm p-5">
                                <div className="mb-4">
                                    <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Summary</p>
                                    <p className="text-sm text-slate-600 mt-0.5">Total {logs.length} activities recorded in {company.name}</p>
                                </div>
                                <div className="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                                    {presentActions.map((action) => {
                                        const meta = metaFor(action);
                                        return (
                                            <div key={action} className="rounded-xl px-3 py-2.5 text-center" style={{ background: meta.bg }}>
                                                <p className="text-lg font-black" style={{ color: meta.text }}>
                                                    {actionCounts[action]}
                                                </p>
                                                <p className="text-[9px] font-bold uppercase tracking-wide mt-0.5" style={{ color: meta.text }}>
                                                    {meta.label}
                                                </p>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="bg-white rounded-2xl border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm overflow-hidden">
                                <div className="px-5 py-3 border-b border-slate-100">
                                    <p className="text-[11px] font-black text-slate-800">Full Activity Record</p>
                                    <p className="text-[9px] text-slate-400 mt-0.5">Read-only — every action recorded under your name, newest first</p>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left">
                                        <thead>
                                            <tr className="bg-slate-50 text-[9px] uppercase tracking-wider text-slate-500 font-black border-b border-[#E5E7EB]">
                                                <th className="px-4 py-2.5">Date</th>
                                                <th className="px-4 py-2.5">Time</th>
                                                <th className="px-4 py-2.5">Activity</th>
                                                <th className="px-4 py-2.5">Subject</th>
                                                <th className="px-4 py-2.5">Detail</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {visible.map((log) => {
                                                const meta = metaFor(log.action);
                                                return (
                                                    <tr key={log.id}>
                                                        <td className="px-4 py-2.5 text-[11px] text-slate-500 whitespace-nowrap">{formatDate(log.occurred_at)}</td>
                                                        <td className="px-4 py-2.5 text-[11px] text-slate-500 whitespace-nowrap">{formatTime(log.occurred_at)}</td>
                                                        <td className="px-4 py-2.5">
                                                            <span
                                                                className="text-[9px] font-black px-2 py-0.5 rounded-full"
                                                                style={{ background: meta.bg, color: meta.text }}
                                                            >
                                                                {meta.emoji} {meta.label}
                                                            </span>
                                                        </td>
                                                        <td className="px-4 py-2.5 text-[11px] font-semibold text-slate-800 max-w-55 truncate">{subjectOf(log) ?? '—'}</td>
                                                        <td className="px-4 py-2.5 text-[11px] text-slate-500">{detailFor(log)}</td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </PlatformLayout>
    );
}
