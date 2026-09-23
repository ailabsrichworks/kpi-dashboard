import { Head, router } from '@inertiajs/react';
import { Fragment, useMemo, useState } from 'react';
import { Bar } from 'react-chartjs-2';
import AppLayout from '../Layouts/AppLayout';
import '../lib/chartSetup';
import { BAND_META, ROLE_BADGE_STYLE, ROLE_BADGE_STYLE_DEFAULT, ROLE_GROUPS, STAGE_META, STATUS_META } from '../config/sltDashboard';

interface Department {
    code: string;
    name?: string;
}

interface AverageBand {
    key: string;
    label: string;
    bg: string;
    text: string;
}

interface StaffRow {
    employee_id: string;
    name: string;
    department: string;
    manager: string;
    role: string;
    role_priority: number;
    score: number | null;
    status_key: string;
}

interface SltDashboardPageProps {
    today: string;
    currentFinancialYear: string;
    quarter: string;
    departments: Department[];
    deptFilter: string;
    totalStaff: number;
    participationRate: number;
    completedCount: number;
    notSubmittedCount: number;
    pendingCount: number;
    awaitingSignoffCount: number;
    averageScore: number;
    averageBand: AverageBand;
    bandCounts: Record<string, number>;
    staffRows: StaffRow[];
}

const QUARTERS = ['Q1', 'Q2', 'Q3', 'Q4'];
const SCORE_BAND_KEYS = Object.keys(BAND_META);

function metaFor(statusKey: string) {
    return BAND_META[statusKey] ?? STATUS_META[statusKey] ?? { label: statusKey, bg: '#F1F5F9', text: '#64748B' };
}

function swatchColor(bg: string): string {
    return bg === '#FFD700' ? '#8a6d00' : bg;
}

export default function SltDashboard({
    today,
    currentFinancialYear,
    quarter,
    departments,
    deptFilter,
    totalStaff,
    participationRate,
    completedCount,
    notSubmittedCount,
    pendingCount,
    awaitingSignoffCount,
    averageScore,
    averageBand,
    bandCounts,
    staffRows,
}: SltDashboardPageProps) {
    const [activeFilter, setActiveFilter] = useState('all');

    function reload(next: Partial<{ quarter: string; department: string }>) {
        router.get('/slt-dashboard', { quarter, department: deptFilter, ...next }, { preserveState: true, preserveScroll: true });
    }

    function matchesFilter(rowBand: string): boolean {
        if (activeFilter === 'all') return true;
        if (activeFilter === 'completed') return SCORE_BAND_KEYS.includes(rowBand);
        return rowBand === activeFilter;
    }

    const groups = useMemo(() => {
        const segments: { groupLabel: string; rows: StaffRow[] }[] = [];
        let currentLabel: string | null = null;
        for (const row of staffRows) {
            const label = ROLE_GROUPS[row.role] ?? 'Other Staff';
            if (label !== currentLabel) {
                segments.push({ groupLabel: label, rows: [] });
                currentLabel = label;
            }
            segments[segments.length - 1].rows.push(row);
        }
        return segments;
    }, [staffRows]);

    const subtitle = activeFilter === 'all' ? 'All staff, grouped by seniority (SLT → VP → Manager → Executive)' : `Showing: ${metaFor(activeFilter).label}`;

    const stageCounts: Record<string, number> = {
        not_submitted: notSubmittedCount,
        pending: pendingCount,
        awaiting_signoff: awaitingSignoffCount,
        completed: completedCount,
    };

    return (
        <AppLayout>
            <Head title="SLT Dashboard" />

            <div className="px-4 pb-4 space-y-3">
                <div className="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#F5F5F3]">
                    <div className="relative overflow-hidden rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-5 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div className="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10" />
                        <div className="relative">
                            <h1 className="text-xl font-black tracking-tight leading-tight">
                                SLT Dashboard | {quarter} {currentFinancialYear}
                            </h1>
                            <p className="text-[11px] text-white/60 mt-1">{today} · Who has completed their quarterly appraisal, and how the team scored</p>
                        </div>

                        <div className="relative flex flex-wrap items-center gap-2">
                            <div className="flex flex-col">
                                <label className="text-[9px] text-white/60 uppercase tracking-wide mb-0.5">Quarter</label>
                                <select
                                    value={quarter}
                                    onChange={(e) => reload({ quarter: e.target.value })}
                                    className="text-xs font-bold rounded-lg px-2.5 py-1.5 text-[#1a1a1a] bg-white border border-white/20"
                                >
                                    {QUARTERS.map((q) => (
                                        <option key={q} value={q}>
                                            {q}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex flex-col">
                                <label className="text-[9px] text-white/60 uppercase tracking-wide mb-0.5">Department</label>
                                <select
                                    value={deptFilter}
                                    onChange={(e) => reload({ department: e.target.value })}
                                    className="text-xs font-bold rounded-lg px-2.5 py-1.5 text-[#1a1a1a] bg-white border border-white/20"
                                >
                                    <option value="ALL">All Departments</option>
                                    {departments.map((d) => (
                                        <option key={d.code} value={d.code}>
                                            {d.name ?? d.code}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                    <div className="bg-white rounded-2xl shadow-[0_8px_30px_rgba(15,23,42,.07)] border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] p-4">
                        <div className="flex items-center gap-2 mb-3">
                            <span className="w-7 h-7 rounded-lg bg-[#D4AF37]/10 flex items-center justify-center shrink-0">
                                <svg className="w-4 h-4 text-[#B8860B]" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.768-.231-1.48-.634-2.072M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.768.231-1.48.634-2.072m9.732 0A6.001 6.001 0 0012 6a6 6 0 00-4.366 9.928"
                                    />
                                </svg>
                            </span>
                            <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Staff Engagement</p>
                        </div>
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-xs text-slate-500 font-semibold">Total Staff</span>
                            <span className="text-2xl font-black text-slate-900">{totalStaff}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-xs text-slate-500 font-semibold">Have Submitted</span>
                            <span className="text-2xl font-black text-[#B8860B]">{participationRate}%</span>
                        </div>
                        <p className="text-[9px] text-slate-400 mt-2">% of staff who have submitted their self-assessment for {quarter}</p>
                    </div>

                    <div className="md:col-span-2 bg-white rounded-2xl shadow-[0_8px_30px_rgba(15,23,42,.07)] border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] p-4">
                        <div className="flex items-center gap-2 mb-3">
                            <span className="w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
                                <svg className="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Appraisal Progress</p>
                        </div>

                        <div className="h-3 rounded-full overflow-hidden flex bg-slate-100">
                            {STAGE_META.map(
                                (s) =>
                                    stageCounts[s.key] > 0 && (
                                        <div
                                            key={s.key}
                                            title={`${s.label}: ${stageCounts[s.key]}`}
                                            style={{ width: `${totalStaff > 0 ? Math.max(2, Math.round((stageCounts[s.key] / totalStaff) * 1000) / 10) : 0}%`, background: s.bg }}
                                        />
                                    ),
                            )}
                        </div>

                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-3">
                            {STAGE_META.map((s) => (
                                <button
                                    key={s.key}
                                    type="button"
                                    onClick={() => setActiveFilter(s.key)}
                                    className={`text-left rounded-xl px-2.5 py-2 border transition hover:-translate-y-0.5 ${activeFilter === s.key ? 'outline-2 outline-offset-1 outline-slate-800' : ''}`}
                                    style={{ background: s.soft, borderColor: s.bg + '33' }}
                                >
                                    <div className="flex items-center gap-1.5">
                                        <span className="w-1.5 h-1.5 rounded-full shrink-0" style={{ background: s.bg }} />
                                        <span className="text-[9px] font-black uppercase tracking-wide truncate" style={{ color: s.text }}>
                                            {s.label}
                                        </span>
                                    </div>
                                    <div className="flex items-baseline justify-between mt-0.5">
                                        <span className="text-lg font-black" style={{ color: s.text }}>
                                            {stageCounts[s.key]}
                                        </span>
                                        <span className="text-[8px] text-slate-400">{totalStaff > 0 ? Math.round((stageCounts[s.key] / totalStaff) * 100) : 0}%</span>
                                    </div>
                                </button>
                            ))}
                        </div>
                        <p className="text-[9px] text-slate-400 mt-2.5">Click a stage to filter the staff list · a name only counts as "Completed" once staff has signed the appraiser's review back</p>
                    </div>

                    <div className="bg-white rounded-2xl shadow-[0_8px_30px_rgba(15,23,42,.07)] border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] p-4 flex items-center justify-between">
                        <div>
                            <div className="flex items-center gap-2 mb-3">
                                <span className="w-7 h-7 rounded-lg bg-[#D4AF37]/10 flex items-center justify-center shrink-0">
                                    <svg className="w-4 h-4 text-[#B8860B]" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </span>
                                <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Average Score</p>
                            </div>
                            <p className="text-4xl font-black leading-none" style={{ color: swatchColor(averageBand.bg) }}>
                                {averageScore.toFixed(1)}
                            </p>
                            <p className="text-[10px] text-slate-400 mt-1">out of 100 · across {completedCount} completed appraisals</p>
                        </div>
                        <span className="text-[10px] font-black px-3 py-1.5 rounded-full" style={{ background: averageBand.bg, color: averageBand.text }}>
                            {averageBand.label}
                        </span>
                    </div>
                </div>

                <div className="grid grid-cols-1 xl:grid-cols-5 gap-3">
                    <div className="xl:col-span-2 bg-white rounded-2xl shadow-[0_8px_30px_rgba(15,23,42,.07)] border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] p-4">
                        <p className="text-[11px] font-black text-slate-800 mb-1">Performance Score Distribution</p>
                        <p className="text-[9px] text-slate-400 mb-3">How many staff landed in each rating band this quarter</p>
                        <div style={{ height: 180, position: 'relative' }}>
                            <Bar
                                data={{
                                    labels: Object.keys(BAND_META).map((k) => `${BAND_META[k].label} (${BAND_META[k].range})`),
                                    datasets: [
                                        {
                                            data: Object.keys(BAND_META).map((k) => bandCounts[k] ?? 0),
                                            backgroundColor: Object.keys(BAND_META).map((k) => BAND_META[k].bg),
                                            borderRadius: 6,
                                        },
                                    ],
                                }}
                                options={{
                                    indexAxis: 'y' as const,
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: { legend: { display: false } },
                                    scales: {
                                        x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                                        y: { ticks: { font: { size: 10, weight: 'bold' } }, grid: { display: false } },
                                    },
                                }}
                            />
                        </div>
                        <div className="mt-4 space-y-1.5">
                            {Object.entries(BAND_META).map(([key, b]) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => setActiveFilter(key)}
                                    className={`w-full flex items-center justify-between px-3 py-1.5 rounded-lg text-[10px] font-black transition ${activeFilter === key ? 'outline-2 outline-offset-1 outline-slate-800' : ''}`}
                                    style={{ background: b.bg + '22', color: swatchColor(b.bg) }}
                                >
                                    <span>
                                        {b.label} <span className="font-normal opacity-70">({b.range})</span>
                                    </span>
                                    <span>{bandCounts[key] ?? 0} staff</span>
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="xl:col-span-3 bg-white rounded-2xl shadow-[0_8px_30px_rgba(15,23,42,.07)] border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] overflow-hidden flex flex-col">
                        <div className="p-4 pb-2 flex items-center justify-between flex-wrap gap-2">
                            <div>
                                <p className="text-[11px] font-black text-slate-800">Staff List</p>
                                <p className="text-[9px] text-slate-400 mt-0.5">{subtitle}</p>
                            </div>
                            <div className="flex gap-1.5 flex-wrap justify-end">
                                <button
                                    type="button"
                                    onClick={() => setActiveFilter('all')}
                                    className={`px-2.5 py-1 rounded-lg text-[9px] font-black bg-slate-100 text-slate-600 ${activeFilter === 'all' ? 'outline-2 outline-offset-1 outline-slate-800' : ''}`}
                                >
                                    All ({totalStaff})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setActiveFilter('not_submitted')}
                                    className={`px-2.5 py-1 rounded-lg text-[9px] font-black bg-red-50 text-red-600 ${activeFilter === 'not_submitted' ? 'outline-2 outline-offset-1 outline-slate-800' : ''}`}
                                >
                                    Not Submitted ({notSubmittedCount})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setActiveFilter('pending')}
                                    className={`px-2.5 py-1 rounded-lg text-[9px] font-black bg-slate-100 text-slate-500 ${activeFilter === 'pending' ? 'outline-2 outline-offset-1 outline-slate-800' : ''}`}
                                >
                                    Awaiting Appraisal ({pendingCount})
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setActiveFilter('awaiting_signoff')}
                                    className={`px-2.5 py-1 rounded-lg text-[9px] font-black bg-amber-50 text-amber-700 ${activeFilter === 'awaiting_signoff' ? 'outline-2 outline-offset-1 outline-slate-800' : ''}`}
                                >
                                    Awaiting Sign-off ({awaitingSignoffCount})
                                </button>
                            </div>
                        </div>
                        <div className="overflow-y-auto overflow-x-auto thin-scroll flex-1" style={{ maxHeight: 460 }}>
                            <table className="w-full min-w-[620px] text-left">
                                <thead className="sticky top-0 bg-white z-10">
                                    <tr className="bg-slate-50 text-[9px] uppercase tracking-wider text-slate-500 font-black border-b border-[#E5E7EB]">
                                        <th className="px-3 py-2">ID</th>
                                        <th className="px-3 py-2">Name</th>
                                        <th className="px-3 py-2">Role</th>
                                        <th className="px-3 py-2">Department</th>
                                        <th className="px-3 py-2">Manager</th>
                                        <th className="px-3 py-2">Status</th>
                                        <th className="px-3 py-2 text-right">Score</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {groups.every((g) => g.rows.filter((r) => matchesFilter(r.status_key)).length === 0) ? (
                                        <tr>
                                            <td colSpan={7} className="px-3 py-8 text-center text-[11px] text-slate-400">
                                                No staff match this filter.
                                            </td>
                                        </tr>
                                    ) : (
                                        groups.map((group) => {
                                            const visibleRows = group.rows.filter((r) => matchesFilter(r.status_key));
                                            if (visibleRows.length === 0) return null;
                                            return (
                                                <Fragment key={group.groupLabel}>
                                                    <tr style={{ background: '#F8FAFC' }}>
                                                        <td colSpan={7} className="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400 border-t border-[#E5E7EB]">
                                                            {group.groupLabel}
                                                        </td>
                                                    </tr>
                                                    {visibleRows.map((row) => {
                                                        const meta = metaFor(row.status_key);
                                                        const isBand = Boolean(BAND_META[row.status_key]);
                                                        const roleStyle = ROLE_BADGE_STYLE[row.role] ?? ROLE_BADGE_STYLE_DEFAULT;
                                                        return (
                                                            <tr key={row.employee_id + row.name} className="hover:bg-slate-50">
                                                                <td className="px-3 py-2 text-[10px] font-bold text-slate-500">{row.employee_id}</td>
                                                                <td className="px-3 py-2 text-[11px] font-black text-slate-900">{row.name}</td>
                                                                <td className="px-3 py-2">
                                                                    <span className="text-[9px] font-black px-2 py-0.5 rounded" style={roleStyle}>
                                                                        {row.role}
                                                                    </span>
                                                                </td>
                                                                <td className="px-3 py-2 text-[10px] text-slate-500">{row.department}</td>
                                                                <td className="px-3 py-2 text-[10px] text-slate-500">{row.manager}</td>
                                                                <td className="px-3 py-2">
                                                                    <span
                                                                        className="text-[9px] font-black px-2 py-0.5 rounded-full"
                                                                        style={{ background: isBand ? meta.bg + '22' : meta.bg, color: isBand && meta.bg === '#FFD700' ? '#8a6d00' : meta.text }}
                                                                    >
                                                                        {meta.label}
                                                                    </span>
                                                                </td>
                                                                <td
                                                                    className="px-3 py-2 text-right text-[11px] font-black"
                                                                    style={{ color: row.score !== null ? swatchColor(meta.bg) : '#cbd5e1' }}
                                                                >
                                                                    {row.score !== null ? row.score.toFixed(1) : '—'}
                                                                </td>
                                                            </tr>
                                                        );
                                                    })}
                                                </Fragment>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <style>{`
                .thin-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
                .thin-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
            `}</style>
        </AppLayout>
    );
}
