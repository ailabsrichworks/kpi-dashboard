import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { scoreStyle } from '../../lib/scoreStyle';
import { CATEGORY_ORDER, categoryThemeFor } from '../../config/staffKpiCategories';

interface QuarterDetail {
    label: string;
    quarter_title: string | null;
    target: number;
    actual: number;
    progress_pct: number;
    has_data: boolean;
}

interface StaffKpi {
    id: string;
    kpi_title?: string;
    category?: string;
    sub_category?: string;
    weightage?: number;
    display_target: number;
    display_actual: number;
    progress_pct: number;
    quarters: QuarterDetail[];
}

interface Staff {
    id: string;
    short_name?: string;
    full_name?: string;
    position?: string;
    role?: string;
}

interface StaffKpisPageProps {
    staff: Staff;
    kpis: StaffKpi[];
    departmentName: string;
    currentFinancialYear: string;
    totalWeight: number;
    weightedScore: number;
}

function groupByOrderedCategory<T>(items: T[], categoryOf: (item: T) => string): [string, T[]][] {
    const groups = new Map<string, T[]>();
    for (const item of items) {
        const cat = categoryOf(item) || 'Uncategorized';
        if (!groups.has(cat)) groups.set(cat, []);
        groups.get(cat)!.push(item);
    }
    const ordered = CATEGORY_ORDER.filter((c) => groups.has(c));
    const rest = [...groups.keys()].filter((c) => !CATEGORY_ORDER.includes(c)).sort();
    return [...ordered, ...rest].map((cat) => [cat, groups.get(cat)!]);
}

function groupBySubCategory(kpis: StaffKpi[]): [string, StaffKpi[]][] {
    const groups = new Map<string, StaffKpi[]>();
    for (const kpi of kpis) {
        const sub = kpi.sub_category || 'General';
        if (!groups.has(sub)) groups.set(sub, []);
        groups.get(sub)!.push(kpi);
    }
    return [...groups.entries()].sort(([a], [b]) => a.localeCompare(b));
}

export default function StaffKpis({ staff, kpis, departmentName, currentFinancialYear, totalWeight, weightedScore }: StaffKpisPageProps) {
    const overallStyle = scoreStyle(weightedScore);
    const orderedCategories = groupByOrderedCategory(kpis, (k) => k.category ?? '');

    return (
        <AppLayout pageTitle={staff.short_name ?? staff.full_name ?? 'Staff'}>
            <Head title={`${staff.short_name ?? staff.full_name ?? 'Staff'} — KPI Overview`} />

            <div className="px-4 pt-4 pb-10">
                <Link href="/dashboard" className="inline-flex items-center gap-1.5 text-[11px] font-bold text-slate-400 hover:text-[#6B9080] transition mb-3">
                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to Dashboard
                </Link>

                {/* Staff header card */}
                <div className="bg-white rounded-2xl overflow-hidden shadow-sm border border-[#6B9080] mb-4">
                    <div className="h-1 theme-header-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019]" />
                    <div className="p-5 flex flex-wrap items-center gap-5">
                        <div className="w-14 h-14 rounded-full overflow-hidden bg-slate-200 shrink-0">
                            <img src={`https://ui-avatars.com/api/?name=${encodeURIComponent(staff.short_name ?? staff.full_name ?? 'U')}&background=1a3d34&color=fff&size=56`} className="w-full h-full" />
                        </div>
                        <div className="flex-1 min-w-[200px]">
                            <h1 className="text-lg font-black text-slate-900">{staff.full_name ?? staff.short_name ?? 'Unknown'}</h1>
                            <p className="text-xs text-slate-500 mt-0.5">
                                {staff.position ?? staff.role ?? '-'} · {departmentName} · {currentFinancialYear}
                            </p>
                            <div className="flex items-center gap-2 mt-2">
                                <span className="px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-700 text-[10px] font-black border border-indigo-100">{(staff.role ?? '-').toUpperCase()}</span>
                                <span className="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 text-[10px] font-black">{kpis.length} KPIs</span>
                            </div>
                        </div>
                        <div className={`text-center px-6 py-3 rounded-2xl ${overallStyle.badge} border`}>
                            <p className="text-[9px] font-black uppercase tracking-widest opacity-70">Overall Score</p>
                            <p className={`text-2xl font-black ${overallStyle.text}`}>{weightedScore.toFixed(1)}%</p>
                            <p className="text-[9px] font-bold opacity-60 mt-0.5">{totalWeight}% weightage assigned</p>
                        </div>
                    </div>
                </div>

                {kpis.length === 0 ? (
                    <div className="bg-white rounded-2xl border border-[#6B9080] shadow-sm p-10 text-center">
                        <p className="text-sm font-black text-slate-400">No KPIs found for this staff in {currentFinancialYear}.</p>
                    </div>
                ) : (
                    <>
                        {/* Legend */}
                        <div className="flex items-center flex-wrap gap-2 mb-4">
                            {orderedCategories.map(([cat]) => {
                                const theme = categoryThemeFor(cat);
                                return (
                                    <span key={cat} className={`inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black ${theme.catPill}`}>
                                        {theme.icon} {cat}
                                    </span>
                                );
                            })}
                        </div>

                        <div className="space-y-6">
                            {orderedCategories.map(([cat, catKpis]) => {
                                const theme = categoryThemeFor(cat);
                                const subGroups = groupBySubCategory(catKpis);
                                return (
                                    <div key={cat} className="rounded-2xl overflow-hidden shadow-sm border border-[#6B9080]">
                                        <div className={`px-4 py-2.5 bg-gradient-to-r ${theme.headerBg} flex items-center justify-between`}>
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm">{theme.icon}</span>
                                                <h2 className="text-xs font-black text-white uppercase tracking-wide">{cat}</h2>
                                            </div>
                                            <span className="text-[9px] font-black text-white/80">
                                                {catKpis.length} KPI{catKpis.length === 1 ? '' : 's'}
                                            </span>
                                        </div>

                                        <div className="bg-white p-4 space-y-5">
                                            {subGroups.map(([subCat, subKpis]) => (
                                                <div key={subCat}>
                                                    <div className="flex items-center gap-2 mb-2.5">
                                                        <span className={`px-2 py-0.5 rounded-lg text-[9px] font-black ${theme.subPill}`}>{subCat}</span>
                                                        <span className="text-[9px] text-slate-400 font-bold">
                                                            {subKpis.length} KPI{subKpis.length === 1 ? '' : 's'}
                                                        </span>
                                                    </div>

                                                    <div className="space-y-2">
                                                        {subKpis.map((kpi) => {
                                                            const kstyle = scoreStyle(kpi.progress_pct);
                                                            return (
                                                                <Link
                                                                    key={kpi.id}
                                                                    href={`/dashboard/staff/${staff.id}/kpi/${kpi.id}`}
                                                                    className={`block rounded-xl border ${theme.border} border-l-4 border-slate-100 hover:bg-slate-50/70 hover:shadow-sm transition-all p-3`}
                                                                >
                                                                    <div className="flex items-start justify-between gap-3 mb-2.5">
                                                                        <h3 className="text-xs font-black text-slate-900 leading-snug flex-1">{kpi.kpi_title ?? 'Untitled KPI'}</h3>
                                                                        <div className="flex items-center gap-1.5 shrink-0">
                                                                            <span className="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 text-[9px] font-black">
                                                                                W {(kpi.weightage ?? 0).toFixed(1)}%
                                                                            </span>
                                                                            <span className={`px-2 py-0.5 rounded-lg text-[9px] font-black ${kstyle.badge} border`}>{kstyle.label}</span>
                                                                        </div>
                                                                    </div>

                                                                    <div className="flex items-center gap-2 mb-3">
                                                                        <span className="text-[9px] font-black text-slate-400 uppercase shrink-0">Overall</span>
                                                                        <div className="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                                                            <div className={`h-1.5 rounded-full ${kstyle.bar}`} style={{ width: `${Math.min(kpi.progress_pct, 100)}%` }} />
                                                                        </div>
                                                                        <span className={`text-[10px] font-black ${kstyle.text} shrink-0 w-10 text-right`}>{kpi.progress_pct.toFixed(1)}%</span>
                                                                        <span className="text-[9px] text-slate-400 shrink-0">
                                                                            ({kpi.display_actual.toFixed(1)} / {kpi.display_target.toFixed(1)})
                                                                        </span>
                                                                    </div>

                                                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                                                                        {kpi.quarters.map((q) => {
                                                                            const qstyle = scoreStyle(q.progress_pct);
                                                                            return (
                                                                                <div key={q.label} className="rounded-lg bg-slate-50 border border-slate-100 px-2 py-1.5">
                                                                                    <div className="flex items-center justify-between mb-1">
                                                                                        <span className="text-[9px] font-black text-slate-500">{q.label}</span>
                                                                                        {q.has_data ? (
                                                                                            <span className={`text-[9px] font-black ${qstyle.text}`}>{q.progress_pct.toFixed(1)}%</span>
                                                                                        ) : (
                                                                                            <span className="text-[9px] font-bold text-slate-300">—</span>
                                                                                        )}
                                                                                    </div>
                                                                                    {q.quarter_title && (
                                                                                        <p className="text-[9px] text-slate-500 truncate mb-1" title={q.quarter_title}>
                                                                                            {q.quarter_title}
                                                                                        </p>
                                                                                    )}
                                                                                    {q.has_data ? (
                                                                                        <>
                                                                                            <div className="h-1 bg-slate-200 rounded-full overflow-hidden mb-1">
                                                                                                <div className={`h-1 rounded-full ${qstyle.bar}`} style={{ width: `${Math.min(q.progress_pct, 100)}%` }} />
                                                                                            </div>
                                                                                            <p className="text-[8px] text-slate-400">
                                                                                                {q.actual.toFixed(1)} / {q.target.toFixed(1)}
                                                                                            </p>
                                                                                        </>
                                                                                    ) : (
                                                                                        <p className="text-[8px] text-slate-300 italic">Not planned</p>
                                                                                    )}
                                                                                </div>
                                                                            );
                                                                        })}
                                                                    </div>
                                                                </Link>
                                                            );
                                                        })}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
