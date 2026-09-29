import { Link } from '@inertiajs/react';
import { useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { scoreHex, scoreStyle } from '@/lib/scoreStyle';
import { categoryStyleFor } from '@/config/dashboardCategories';
import type { MyPerformance } from './Dashboard/MyPerformanceCard';
import type { DepartmentOverviewRow } from './Dashboard/DepartmentOverview';

/**
 * The Platform's Main Dashboard, laid out 1:1 like the legacy app's
 * dashboard.blade.php / Pages/Dashboard.tsx (greeting, gold-bordered "My
 * Performance" card, collapsible Company Overview row, Target Linkages row,
 * "My KPIs" strip) — same markup and classes — but fed by the Platform's own
 * per-company data from DashboardController::companyLanding().
 */

interface Company {
    id: string;
    name: string;
    code: string;
    status: string;
    department_count: number;
    user_count: number;
    kpi_count: number;
    submission_count: number;
    avg_achievement_pct: number | null;
    my_performance: MyPerformance | null;
    department_overview: DepartmentOverviewRow[];
}

interface PlatformUser {
    id: string;
    name: string;
    email: string;
    is_super_admin?: boolean;
    is_platform_admin: boolean;
    assigned_company_ids: string[];
    company_memberships: Array<{
        company_id: string;
        role: string;
        companies: { name: string; code: string };
    }>;
}

interface DashboardPageProps {
    me: PlatformUser;
    visibleCompanies: Company[];
    greeting: string;
    [key: string]: unknown;
}

const QUARTERS = ['Q1', 'Q2', 'Q3', 'Q4'] as const;

const ROLE_LABEL: Record<string, string> = {
    company_admin: 'Company Admin',
    slt: 'SLT',
    executive: 'Executive',
    employee: 'Employee',
};

function canAdminister(me: PlatformUser, companyId: string): boolean {
    if (me.is_super_admin) return true;
    if (me.is_platform_admin && me.assigned_company_ids.includes(companyId)) return true;
    return me.company_memberships.some((m) => m.company_id === companyId && m.role === 'company_admin');
}

function avatarUrl(name: string, size: number): string {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=D4AF37&color=1a1a1a&size=${size}`;
}

function MyPerformanceSection({ company, me, fy }: { company: Company; me: PlatformUser; fy: string }) {
    const perf = company.my_performance;
    const base = `/platform/companies/${company.id}`;
    const kpiCount = perf?.kpi_count ?? 0;
    const role = me.company_memberships.find((m) => m.company_id === company.id)?.role;
    const position = role ? (ROLE_LABEL[role] ?? role) : 'Member';

    if (!perf || kpiCount === 0) {
        return (
            <div className="bg-white rounded-2xl overflow-hidden shadow-sm border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37]">
                <div className="p-6 sm:p-7 flex flex-col sm:flex-row items-center gap-5">
                    <div className="w-14 h-14 rounded-2xl bg-[#D4AF37]/10 flex items-center justify-center shrink-0">
                        <svg className="w-7 h-7 text-[#B8860B]" fill="none" stroke="currentColor" strokeWidth="1.7" viewBox="0 0 24 24">
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M9 12h6m-6 4h4m-7 5h10a2 2 0 002-2V7.828a2 2 0 00-.586-1.414l-3.828-3.828A2 2 0 0011.172 2H6a2 2 0 00-2 2v14a2 2 0 002 2Z"
                            />
                        </svg>
                    </div>
                    <div className="flex-1 text-center sm:text-left">
                        <p className="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-1">My Performance · {fy}</p>
                        <h2 className="text-base font-black text-slate-800">No KPIs set for {fy} yet</h2>
                        <p className="text-xs text-slate-500 mt-1">Your score, quarterly progress and at-risk alerts will appear here as soon as your KPIs are created.</p>
                    </div>
                    <div className="flex gap-2 shrink-0">
                        <Link href={`${base}/kpis`} className="bg-[#D4AF37] hover:bg-[#c19c2f] text-[#1a1a1a] px-4 py-2.5 rounded-xl text-xs font-black transition">
                            My KPIs
                        </Link>
                        <Link href={`${base}/weightage`} className="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl text-xs font-black transition">
                            Weightage
                        </Link>
                    </div>
                </div>
            </div>
        );
    }

    const score = perf.overall_score;
    const style = scoreStyle(score ?? 0);
    const atRiskNames = perf.needs_attention.map((k) => k.name);

    return (
        <div className="bg-white rounded-2xl overflow-hidden shadow-sm border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37]">
            <div className="flex flex-col lg:flex-row">
                <div className="theme-perf-card p-5 lg:min-w-[240px] xl:min-w-[260px] flex flex-col justify-between">
                    <div>
                        <p className="theme-perf-accent-text text-[9px] uppercase tracking-widest font-black mb-3">My Performance · {fy}</p>
                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-10 h-10 rounded-full overflow-hidden shrink-0 ring-2 ring-[#D4AF37]/60">
                                <img src={avatarUrl(me.name, 40)} className="w-full h-full object-cover" alt="" />
                            </div>
                            <div>
                                <h2 className="text-sm font-black text-slate-800 leading-tight">{me.name}</h2>
                                <p className="text-[9px] text-slate-500 mt-0.5">
                                    {position} · {company.name}
                                </p>
                            </div>
                        </div>
                        {perf.total_weight <= 0 ? (
                            <div className="bg-white rounded-xl p-3">
                                <p className="text-3xl font-black text-slate-300 mb-1">—</p>
                                <p className="text-xs text-slate-400">{kpiCount} KPIs · weightage not set</p>
                                <Link href={`${base}/weightage`} className="inline-block mt-2 text-xs font-black text-[#7A0019] underline">
                                    Set weightage →
                                </Link>
                            </div>
                        ) : score === null ? (
                            <div className="bg-white rounded-xl p-3">
                                <p className="text-3xl font-black text-slate-300 mb-1">—</p>
                                <p className="text-xs text-slate-400">
                                    {kpiCount} KPIs · no values reported yet
                                </p>
                            </div>
                        ) : (
                            <div className="bg-white rounded-xl p-3">
                                <div className="flex items-end gap-1.5 mb-2">
                                    <span className={`text-4xl font-black leading-none ${style.text}`}>{score.toFixed(1)}</span>
                                    <span className="text-lg font-black text-slate-300 mb-0.5">%</span>
                                </div>
                                <div className="h-1.5 bg-slate-100 rounded-full overflow-hidden mb-2">
                                    <div className={`h-1.5 rounded-full ${style.bar}`} style={{ width: `${Math.min(score, 100)}%` }} />
                                </div>
                                <span className={`inline-block px-2.5 py-0.5 rounded-full text-[9px] font-black border ${style.badge}`}>{style.label}</span>
                                <p className="text-[9px] text-slate-400 mt-1.5">
                                    {kpiCount} KPIs · {perf.total_weight.toFixed(0)}% weightage
                                </p>
                            </div>
                        )}
                    </div>
                </div>

                <div className="flex-1 p-5 flex flex-col gap-5">
                    <div className="grid grid-cols-3 gap-3">
                        <div className="bg-slate-50 rounded-2xl p-4 text-center border border-slate-100">
                            <p className="text-3xl font-black text-slate-900">{kpiCount}</p>
                            <p className="text-[9px] text-slate-400 uppercase tracking-wide mt-1.5">Total KPIs</p>
                        </div>
                        <div className="bg-emerald-50 rounded-2xl p-4 text-center border border-emerald-100">
                            <p className="text-3xl font-black text-emerald-600">{perf.on_track}</p>
                            <p className="text-[9px] text-emerald-500 uppercase tracking-wide mt-1.5">On Track</p>
                        </div>
                        {perf.at_risk > 0 ? (
                            <div className="bg-red-50 rounded-2xl p-4 text-center border border-red-100">
                                <p className="text-3xl font-black text-red-600">{perf.at_risk}</p>
                                <p className="text-[9px] text-red-400 uppercase tracking-wide mt-1.5">At Risk</p>
                            </div>
                        ) : (
                            <div className="bg-slate-50 rounded-2xl p-4 text-center border border-slate-100">
                                <p className="text-3xl font-black text-slate-300">0</p>
                                <p className="text-[9px] text-slate-400 uppercase tracking-wide mt-1.5">At Risk</p>
                            </div>
                        )}
                    </div>

                    {atRiskNames.length > 0 && (
                        <div className="bg-red-50 border border-red-100 rounded-2xl p-3">
                            <p className="text-[9px] font-black text-red-500 uppercase tracking-widest mb-1.5">⚠ Needs Attention</p>
                            <ul className="space-y-0.5">
                                {atRiskNames.map((title, i) => (
                                    <li key={i} className="text-[11px] text-red-700 font-semibold truncate">
                                        · {title}
                                    </li>
                                ))}
                            </ul>
                            {perf.at_risk > atRiskNames.length && (
                                <Link href={`${base}/kpis`} className="text-[9px] text-red-500 underline font-bold">
                                    +{perf.at_risk - atRiskNames.length} more →
                                </Link>
                            )}
                        </div>
                    )}

                    <div>
                        <p className="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3">My Quarterly Progress</p>
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            {QUARTERS.map((qi) => {
                                const q = perf.quarterly?.[qi] ?? { completed: 0, total: 0, progress: 0 };
                                const qPending = q.completed === 0;
                                const pstyle = qPending ? { bar: 'bg-slate-200', text: 'text-slate-400' } : scoreStyle(q.progress);
                                return (
                                    <div key={qi} className="bg-slate-50 rounded-xl p-2.5 border border-slate-100">
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="text-[10px] font-black text-slate-700">{qi}</span>
                                            <span className={`text-[10px] font-black ${pstyle.text}`}>{qPending ? '—' : `${q.progress}%`}</span>
                                        </div>
                                        <div className="h-1.5 bg-slate-200 rounded-full overflow-hidden mb-1.5">
                                            <div className={`h-1.5 rounded-full ${pstyle.bar}`} style={{ width: `${qPending ? 100 : Math.min(q.progress, 100)}%` }} />
                                        </div>
                                        <p className="text-[8px] text-slate-400">
                                            {q.total === 0 ? 'No KPIs' : qPending ? 'Pending' : `${q.progress.toFixed(0)}% of target`}
                                            {q.total > 0 && <> · {q.completed === q.total ? '✓ Signed off' : `${q.completed}/${q.total} signed off`}</>}
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function CompanyOverviewSection({ company, fy }: { company: Company; fy: string }) {
    const storageKey = `platformCompanyOverviewOpen:${company.id}`;
    const [open, setOpen] = useState(() => {
        try {
            return localStorage.getItem(storageKey) === 'true';
        } catch {
            return false;
        }
    });

    const toggle = () => {
        const next = !open;
        setOpen(next);
        try {
            localStorage.setItem(storageKey, next ? 'true' : 'false');
        } catch {
            // Blocked storage — the toggle still works for this page view.
        }
    };

    const ranked = company.department_overview
        .filter((r) => r.avg_achievement_pct !== null)
        .slice()
        .sort((a, b) => (b.avg_achievement_pct ?? 0) - (a.avg_achievement_pct ?? 0));
    const maxScore = Math.max(0, ...ranked.map((r) => r.avg_achievement_pct ?? 0));
    const axisMax = Math.max(10, Math.ceil((maxScore * 1.15) / 10) * 10);

    return (
        <div>
            <button
                type="button"
                onClick={toggle}
                className="w-full flex items-center justify-between bg-white rounded-2xl px-5 py-4 border border-[#E5E7EB] border-l-[4px] border-l-[#D4AF37] shadow-sm hover:bg-slate-50/60 transition"
            >
                <div className="flex items-center gap-3">
                    <div className="w-9 h-9 rounded-xl bg-[#D4AF37]/10 flex items-center justify-center shrink-0">
                        <svg className="w-5 h-5 text-[#B8860B]" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                            />
                        </svg>
                    </div>
                    <div className="text-left">
                        <p className="text-sm font-black text-slate-800">Company Overview</p>
                        <p className="text-[9px] text-slate-400 mt-0.5">Department ranking · team performance · quarterly trends</p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <span className="text-[9px] font-black text-[#B8860B] bg-[#D4AF37]/10 px-2.5 py-1 rounded-full">{open ? 'Hide' : 'Show'}</span>
                    <svg
                        className="w-4 h-4 text-slate-400 transition-transform duration-300"
                        style={{ transform: open ? 'rotate(0deg)' : 'rotate(-90deg)' }}
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>

            {open && (
                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 items-start mt-3">
                    <div className="sm:col-span-2 xl:col-span-3 bg-white rounded-2xl overflow-hidden shadow-sm border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37]">
                        <div className="p-4">
                            <div className="flex items-start justify-between mb-3">
                                <div>
                                    <h3 className="text-[11px] font-black text-slate-800 leading-tight">Department Annual Ranking</h3>
                                    <p className="text-[9px] text-slate-400 mt-0.5">{ranked.length} departments · by achievement</p>
                                </div>
                                <span className="text-[9px] font-bold text-[#B8860B] bg-[#D4AF37]/10 px-2 py-0.5 rounded-full">{fy}</span>
                            </div>
                            {ranked.length === 0 ? (
                                <p className="text-[11px] text-slate-400 py-4 text-center">No KPI values reported yet.</p>
                            ) : (
                                <div className="space-y-2.5">
                                    {ranked.map((row) => {
                                        const pct = row.avg_achievement_pct ?? 0;
                                        return (
                                            <div key={row.department} className="flex items-center gap-3">
                                                <span className="w-28 shrink-0 text-[11px] font-bold text-slate-700 truncate">{row.department}</span>
                                                <div className="flex-1 h-4 bg-slate-100 rounded-md overflow-hidden">
                                                    <div className="h-full rounded-md" style={{ width: `${(pct / axisMax) * 100}%`, backgroundColor: scoreHex(pct) }} />
                                                </div>
                                                <span className="w-12 shrink-0 text-right text-[10px] font-black text-slate-600 tabular-nums">{pct.toFixed(1)}%</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl overflow-hidden shadow-sm border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] flex flex-col">
                        <div className="p-4 flex flex-col items-center text-center flex-1 justify-between">
                            <p className="text-[9px] font-black text-slate-400 uppercase tracking-widest w-full text-left mb-4">Total Staff</p>
                            <div className="flex flex-col items-center flex-1 justify-center">
                                <div className="w-12 h-12 rounded-2xl bg-[#D4AF37]/10 flex items-center justify-center mb-3">
                                    <svg className="w-6 h-6 text-[#B8860B]" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.768-.231-1.48-.634-2.072M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.768.231-1.48.634-2.072m9.732 0A6.001 6.001 0 0012 6a6 6 0 00-4.366 9.928"
                                        />
                                    </svg>
                                </div>
                                <p className="text-5xl font-black text-slate-900 leading-none">{company.user_count}</p>
                                <p className="text-[10px] text-slate-400 mt-2">staff members</p>
                            </div>
                            <div className="w-full mt-4 pt-3 border-t border-slate-100 flex items-center justify-center gap-1.5">
                                <span className="text-[10px] font-bold text-slate-500">{company.department_count} Departments</span>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl overflow-hidden shadow-sm border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] flex flex-col">
                        <div className="p-4 flex flex-col items-center text-center flex-1 justify-between">
                            <p className="text-[9px] font-black text-slate-400 uppercase tracking-widest w-full text-left mb-4">Company Achievement</p>
                            <div className="flex flex-col items-center flex-1 justify-center">
                                {company.avg_achievement_pct !== null ? (
                                    <>
                                        <p className={`text-4xl font-black leading-none ${scoreStyle(company.avg_achievement_pct).text}`}>
                                            {Number(company.avg_achievement_pct).toFixed(1)}%
                                        </p>
                                        <span
                                            className={`inline-block mt-3 text-[9px] font-black px-3 py-1 rounded-full border ${scoreStyle(company.avg_achievement_pct).badge}`}
                                        >
                                            {scoreStyle(company.avg_achievement_pct).label}
                                        </span>
                                    </>
                                ) : (
                                    <p className="text-4xl font-black text-slate-300 leading-none">—</p>
                                )}
                            </div>
                            <div className="w-full mt-4 pt-3 border-t border-slate-100 flex items-center justify-center">
                                <span className="text-[10px] font-bold text-slate-500">
                                    {company.kpi_count} KPIs · {company.submission_count} submissions
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function TargetLinkagesRow({ company }: { company: Company }) {
    return (
        <Link
            href={`/platform/companies/${company.id}/target-linkages`}
            className="flex items-center justify-between gap-3 bg-white rounded-2xl px-5 py-4 border border-[#E5E7EB] border-l-[4px] border-l-[#D4AF37] shadow-sm hover:bg-slate-50/60 transition"
        >
            <div className="flex items-center gap-3 min-w-0">
                <div className="w-9 h-9 rounded-xl bg-[#D4AF37]/10 flex items-center justify-center shrink-0">
                    <svg className="w-5 h-5 text-[#B8860B]" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M9 17H7A5 5 0 017 7h2M15 7h2a5 5 0 010 10h-2M8 12h8" />
                    </svg>
                </div>
                <div className="text-left min-w-0">
                    <p className="text-sm font-black text-slate-800">Target Linkages</p>
                    <p className="text-[9px] text-slate-400 mt-0.5">No cascading targets yet — assign one to your team</p>
                </div>
            </div>
            <span className="text-[9px] font-black text-[#B8860B] bg-[#D4AF37]/10 px-2.5 py-1 rounded-full shrink-0">View →</span>
        </Link>
    );
}

function MyKpisSection({ company, me, fy }: { company: Company; me: PlatformUser; fy: string }) {
    const perf = company.my_performance;
    const kpiCount = perf?.kpi_count ?? 0;
    const base = `/platform/companies/${company.id}`;
    const isAdmin = canAdminister(me, company.id);

    return (
        <div>
            <div className="flex items-center justify-between mb-3">
                <div>
                    <h2 className="text-sm font-black text-slate-900 inline-block border-b-2 border-[#D4AF37] pb-1">
                        My KPIs <span className="font-normal text-slate-400 text-xs">· {fy}</span>
                    </h2>
                    {kpiCount > 0 && perf && (
                        <p className="text-[9px] text-slate-400 mt-0.5">
                            {kpiCount} KPIs · {perf.total_weight.toFixed(0)}% total weightage
                        </p>
                    )}
                </div>
                {isAdmin && (
                    <Link href={`${base}/kpis?create=1`} className="px-3 py-1.5 theme-soft-btn rounded-xl text-xs font-black transition">
                        + Add KPI
                    </Link>
                )}
            </div>

            {kpiCount === 0 || !perf ? (
                <div className="bg-white rounded-2xl border border-dashed border-[#E5E7EB] p-10 shadow-sm text-center">
                    <p className="text-slate-400 text-sm font-bold">No KPIs yet for {fy}</p>
                    <p className="text-slate-300 text-xs mt-1">
                        {isAdmin ? 'Create your first KPI to start tracking performance' : 'Your Company Admin will assign KPIs to you'}
                    </p>
                    <Link
                        href={isAdmin ? `${base}/kpis?create=1` : `${base}/kpis`}
                        className="inline-block mt-4 px-4 py-2 theme-soft-btn rounded-xl text-xs font-black transition"
                    >
                        {isAdmin ? '+ Create KPI' : 'View KPIs'}
                    </Link>
                </div>
            ) : (
                <Link href={`${base}/kpis`} className="flex flex-wrap items-center gap-2 bg-white rounded-2xl border border-[#E5E7EB] shadow-sm p-4 hover:bg-slate-50/60 transition">
                    {perf.category_counts.map(({ category, count }) => (
                        <span key={category} className={`px-2.5 py-1 rounded-lg text-xs font-black shadow-sm ${categoryStyleFor(category).bg}`}>
                            {category} · {count}
                        </span>
                    ))}
                    <span className="ml-auto text-xs font-black text-[#B8860B] shrink-0">View All KPIs →</span>
                </Link>
            )}
        </div>
    );
}

export default function PlatformDashboard({ me, visibleCompanies, greeting }: DashboardPageProps) {
    const fy = `FY${new Date().getFullYear()}`;
    const first = visibleCompanies[0];
    const multiple = visibleCompanies.length > 1;

    return (
        <PlatformLayout title="Main Dashboard" company={first ? { id: first.id, name: first.name, code: first.code } : null}>
            <div className="space-y-3">
                <h1 className="text-2xl font-black tracking-tight text-slate-900 leading-tight">
                    Hi, {greeting} {me.name} 👋
                </h1>

                {visibleCompanies.length === 0 ? (
                    <div className="bg-white rounded-2xl border border-dashed border-[#E5E7EB] p-10 shadow-sm text-center">
                        <p className="text-slate-400 text-sm font-bold">No companies yet</p>
                        <p className="text-slate-300 text-xs mt-1">Once you're added to a company, it will show up here automatically.</p>
                    </div>
                ) : (
                    visibleCompanies.map((company) => (
                        <div key={company.id} className="space-y-3">
                            {multiple && (
                                <p className="text-[10px] font-black uppercase tracking-widest text-[#B8860B] pt-2">
                                    {company.name} · {company.code}
                                </p>
                            )}
                            <MyPerformanceSection company={company} me={me} fy={fy} />
                            <CompanyOverviewSection company={company} fy={fy} />
                            <TargetLinkagesRow company={company} />
                            <MyKpisSection company={company} me={me} fy={fy} />
                        </div>
                    ))
                )}
            </div>
        </PlatformLayout>
    );
}
