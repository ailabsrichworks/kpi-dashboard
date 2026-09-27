import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../Layouts/AppLayout';
import { dateKey, dateDividerLabel, formatDate, formatTime } from '../lib/dates';

interface ActivityLogEntry {
    type: string;
    label: string;
    color: string;
    who: string;
    kpi_title: string;
    detail: string;
    at: string;
    actor_id: string | null;
}

interface ActivityLogPageProps {
    user: { short_name?: string; full_name?: string; role?: string; department_code?: string };
    logs: ActivityLogEntry[];
    typeFilter: string;
    fy: string;
}

const TYPES: Record<string, string> = {
    '': 'All Activities',
    kpi_created: 'KPI Created',
    kpi_edited: 'KPI Edited',
    update_submitted: 'Update Submitted',
    update_approved: 'Update Approved',
    update_rejected: 'Update Rejected',
    completion_submitted: 'Completion Submitted',
    delete_requested: 'Delete Requested',
    appraisal_submitted: 'Appraisal Submitted',
    appraisal_signed: 'Appraisal Signed',
    appraisal_reviewed: 'Appraisal Reviewed',
};

const DOT_COLORS: Record<string, string> = {
    blue: 'bg-[#6B3F2A]',
    indigo: 'bg-indigo-500',
    amber: 'bg-amber-500',
    green: 'bg-emerald-500',
    red: 'bg-red-500',
    purple: 'bg-purple-500',
    rose: 'bg-rose-500',
    teal: 'bg-teal-500',
    cyan: 'bg-cyan-500',
    orange: 'bg-orange-500',
};

const BADGE_BG: Record<string, string> = {
    blue: 'bg-[#F5EAE0] text-[#6B3F2A]',
    indigo: 'bg-indigo-100 text-indigo-700',
    amber: 'bg-amber-100 text-amber-800',
    green: 'bg-emerald-100 text-emerald-700',
    red: 'bg-red-100 text-red-700',
    purple: 'bg-purple-100 text-purple-700',
    rose: 'bg-rose-100 text-rose-700',
    teal: 'bg-teal-100 text-teal-700',
    cyan: 'bg-cyan-100 text-cyan-700',
    orange: 'bg-orange-100 text-orange-700',
};

const STAT_CARDS: [string, string, string, string][] = [
    ['kpi_created', 'KPIs Created', 'blue', '📝'],
    ['kpi_edited', 'KPIs Edited', 'indigo', '✏️'],
    ['update_submitted', 'Updates Sent', 'amber', '📤'],
    ['update_approved', 'Approved', 'green', '✅'],
    ['update_rejected', 'Rejected', 'red', '❌'],
    ['completion_submitted', 'Completions', 'purple', '🏁'],
    ['delete_requested', 'Delete Requests', 'rose', '🗑️'],
    ['appraisal_submitted', 'Appraisals Submitted', 'teal', '🧾'],
    ['appraisal_signed', 'Appraisals Signed', 'cyan', '✒️'],
    ['appraisal_reviewed', 'Appraisals Reviewed', 'orange', '📋'],
];

const TYPE_META: Record<string, { label: string; bg: string; text: string }> = {
    kpi_created: { label: 'KPI Created', bg: '#FBF5EF', text: '#6B3F2A' },
    kpi_edited: { label: 'KPI Edited', bg: '#EEF2FF', text: '#4338CA' },
    update_submitted: { label: 'Update Submitted', bg: '#FEF3C7', text: '#B45309' },
    update_approved: { label: 'Update Approved', bg: '#D1FAE5', text: '#047857' },
    update_rejected: { label: 'Update Rejected', bg: '#FEE2E2', text: '#DC2626' },
    completion_submitted: { label: 'Completion Submitted', bg: '#F3E8FF', text: '#7C3AED' },
    delete_requested: { label: 'Delete Requested', bg: '#FFE4E6', text: '#BE123C' },
    appraisal_submitted: { label: 'Appraisal Submitted', bg: '#CCFBF1', text: '#0F766E' },
    appraisal_signed: { label: 'Appraisal Signed', bg: '#CFFAFE', text: '#0E7490' },
    appraisal_reviewed: { label: 'Appraisal Reviewed', bg: '#FFEDD5', text: '#C2410C' },
};

function groupConsecutiveByDate(logs: ActivityLogEntry[]): [string, ActivityLogEntry[]][] {
    const groups: [string, ActivityLogEntry[]][] = [];
    for (const log of logs) {
        const key = dateKey(log.at);
        const last = groups[groups.length - 1];
        if (last && last[0] === key) {
            last[1].push(log);
        } else {
            groups.push([key, [log]]);
        }
    }
    return groups;
}

export default function ActivityLog({ user, logs, typeFilter, fy }: ActivityLogPageProps) {
    const [viewMode, setViewMode] = useState<'timeline' | 'report'>('timeline');

    const typeCounts = logs.reduce<Record<string, number>>((acc, l) => {
        acc[l.type] = (acc[l.type] ?? 0) + 1;
        return acc;
    }, {});

    const nonZeroStats = STAT_CARDS.filter(([type]) => (typeCounts[type] ?? 0) > 0);
    const grouped = groupConsecutiveByDate(logs);

    const pageActions = (
        <>
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
        </>
    );

    return (
        <AppLayout
            pageTitle="User Activity Log"
            pageSubtitle={`${user.short_name ?? user.full_name ?? '-'} · ${user.role} · ${user.department_code} · ${fy}`}
            pageActions={pageActions}
        >
            <Head title="User Activity Log" />

            <div className="p-4 space-y-4">
                {/* FILTER BAR */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm px-4 py-3">
                    <div className="flex flex-wrap gap-2 items-center">
                        <span className="text-[10px] text-slate-400 uppercase tracking-wider font-semibold mr-1">Filter:</span>
                        {Object.entries(TYPES).map(([typeKey, typeLabel]) => (
                            <Link
                                key={typeKey || 'all'}
                                href={`/activity-log${typeKey ? '?type=' + typeKey : ''}`}
                                className={`text-[11px] px-3 py-1 rounded-full border font-semibold transition ${
                                    typeFilter === typeKey ? 'bg-[#6B3F2A] text-white border-[#6B3F2A]' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'
                                }`}
                            >
                                {typeLabel}
                            </Link>
                        ))}
                    </div>
                </div>

                {logs.length === 0 ? (
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm px-6 py-16 text-center">
                        <div className="text-4xl mb-3">📋</div>
                        <p className="text-slate-500 font-semibold">No activity found</p>
                        <p className="text-slate-400 text-xs mt-1">{typeFilter ? 'No events of this type yet.' : `No activity recorded yet for ${fy}.`}</p>
                    </div>
                ) : viewMode === 'timeline' ? (
                    <div>
                        <div className="space-y-4">
                            {grouped.map(([key, dayLogs]) => (
                                <div key={key}>
                                    <div className="flex items-center gap-3 mb-3">
                                        <div className="h-px flex-1 bg-slate-200" />
                                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider px-2">{dateDividerLabel(dayLogs[0].at)}</span>
                                        <div className="h-px flex-1 bg-slate-200" />
                                    </div>

                                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden divide-y divide-slate-100">
                                        {dayLogs.map((log, i) => (
                                            <div key={i} className="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 transition">
                                                <div className={`mt-1.5 shrink-0 w-2.5 h-2.5 rounded-full ${DOT_COLORS[log.color] ?? 'bg-slate-400'}`} />
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2 mb-0.5">
                                                        <span className={`text-[10px] font-black px-2 py-0.5 rounded-full ${BADGE_BG[log.color] ?? 'bg-slate-100 text-slate-600'}`}>{log.label}</span>
                                                        <span className="text-[11px] font-semibold text-slate-800 truncate">{log.kpi_title}</span>
                                                    </div>
                                                    <p className="text-[11px] text-slate-500 truncate">{log.detail}</p>
                                                </div>
                                                <div className="shrink-0 text-right">
                                                    <p className="text-[11px] font-semibold text-slate-700">{log.who}</p>
                                                    <p className="text-[10px] text-slate-400">{formatTime(log.at)}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                            {nonZeroStats.map(([sType, sLabel, sColor, sIcon]) => (
                                <Link
                                    key={sType}
                                    href={`/activity-log?type=${sType}`}
                                    className="bg-white rounded-2xl border border-slate-200 shadow-sm p-3 hover:shadow-md transition flex items-center gap-3"
                                >
                                    <span className="text-xl">{sIcon}</span>
                                    <div>
                                        <p className="text-[10px] text-slate-500">{sLabel}</p>
                                        <p className="text-lg font-black text-slate-800">{typeCounts[sType] ?? 0}</p>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <div className="bg-white rounded-2xl border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm p-5">
                            <div className="flex items-center justify-between mb-4">
                                <div>
                                    <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Summary</p>
                                    <p className="text-sm text-slate-600 mt-0.5">
                                        Total {logs.length} activities recorded for {fy}
                                    </p>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                                {Object.entries(TYPE_META).map(([key, meta]) => (
                                    <div key={key} className="rounded-xl px-3 py-2.5 text-center" style={{ background: meta.bg }}>
                                        <p className="text-lg font-black" style={{ color: meta.text }}>
                                            {typeCounts[key] ?? 0}
                                        </p>
                                        <p className="text-[9px] font-bold uppercase tracking-wide mt-0.5" style={{ color: meta.text }}>
                                            {meta.label}
                                        </p>
                                    </div>
                                ))}
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
                                            <th className="px-4 py-2.5">KPI / Appraisal</th>
                                            <th className="px-4 py-2.5">Detail</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {logs.map((log, i) => {
                                            const meta = TYPE_META[log.type] ?? { label: log.label, bg: '#F1F5F9', text: '#64748B' };
                                            return (
                                                <tr key={i}>
                                                    <td className="px-4 py-2.5 text-[11px] text-slate-500 whitespace-nowrap">{formatDate(log.at)}</td>
                                                    <td className="px-4 py-2.5 text-[11px] text-slate-500 whitespace-nowrap">{formatTime(log.at)}</td>
                                                    <td className="px-4 py-2.5">
                                                        <span className="text-[9px] font-black px-2 py-0.5 rounded-full" style={{ background: meta.bg, color: meta.text }}>
                                                            {meta.label}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-2.5 text-[11px] font-semibold text-slate-800 max-w-[220px] truncate">{log.kpi_title}</td>
                                                    <td className="px-4 py-2.5 text-[11px] text-slate-500">{log.detail}</td>
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
        </AppLayout>
    );
}
