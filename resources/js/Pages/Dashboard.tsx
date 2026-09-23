import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../Layouts/AppLayout';
import { SharedPageProps } from '../types';
import { categoryStyleFor } from '../config/dashboardCategories';
import MyPerformanceCard from './Dashboard/MyPerformanceCard';
import CompanyOverview from './Dashboard/CompanyOverview';
import QuarterlyTrendChart from './Dashboard/QuarterlyTrendChart';
import DepartmentAccordion from './Dashboard/DepartmentAccordion';

interface DeptChartRow {
    code: string;
    annual: number;
    q1: number;
    q2: number;
    q3: number;
    q4: number;
    bands: number[];
    staff: number;
    at_risk: number;
}

interface DeptRow {
    department_code: string;
    staff_count: number;
    kpi_count: number;
    performance: number;
    risk_count: number;
    q1: number;
    q2: number;
    q3: number;
    q4: number;
    staff_list: {
        employee_id: string;
        name: string;
        role: string;
        kpi_count: number;
        performance: number;
        q1: number;
        q2: number;
        q3: number;
        q4: number;
    }[];
}

interface DashboardPageProps {
    user: { short_name?: string; full_name?: string };
    greeting: string;
    currentFinancialYear: string;
    currentUserName: string;
    userPosition: string;
    currentDepartment: string;
    isManager: boolean;
    isSltOffice: boolean;

    individualKpiCount: number;
    individualWeightage: number;
    individualPerformance: number;
    myOnTrack: number;
    myAtRisk: number;
    myAtRiskKpis: string[];
    myCompletedByQ: Record<string, number>;
    myTotalByQ: Record<string, number>;
    myProgressByQ: Record<string, number>;
    myCategoryCounts: { category: string; count: number }[];

    companyDeptRanking: { code: string; score: number; staff: number }[];
    companyTotalStaff: number;
    companyDeptCount: number;
    totalStaffCount: number;
    totalCompletedByQ: Record<string, number>;
    totalByQ: Record<string, number>;
    totalCompletedAnnual: number;
    totalKpisVisible: number;

    deptRows: DeptRow[];
    myDeptPerformance: number;
    myDeptBands: number[];
    deptChartData: DeptChartRow[];

    hasAnyLinkage: boolean;
    canAssignTarget: boolean;
    linkageTotalCount: number;
    linkageGapCount: number;
}

export default function Dashboard(props: DashboardPageProps) {
    const { layout, flash } = usePage<SharedPageProps>().props;
    const {
        greeting,
        currentUserName,
        currentFinancialYear,
        userPosition,
        currentDepartment,
        isManager,
        isSltOffice,
        individualKpiCount,
        individualWeightage,
        individualPerformance,
        myOnTrack,
        myAtRisk,
        myAtRiskKpis,
        myCompletedByQ,
        myTotalByQ,
        myProgressByQ,
        myCategoryCounts,
        companyDeptRanking,
        companyTotalStaff,
        companyDeptCount,
        totalStaffCount,
        totalCompletedByQ,
        totalByQ,
        totalCompletedAnnual,
        totalKpisVisible,
        deptRows,
        myDeptPerformance,
        myDeptBands,
        deptChartData,
        hasAnyLinkage,
        canAssignTarget,
        linkageTotalCount,
        linkageGapCount,
    } = props;

    const [companyOverviewOpen, setCompanyOverviewOpen] = useState(() => localStorage.getItem('companyOverviewOpen') === 'true');

    function toggleCompanySection() {
        const next = !companyOverviewOpen;
        setCompanyOverviewOpen(next);
        localStorage.setItem('companyOverviewOpen', next ? 'true' : 'false');
    }

    const showCompanySection = companyDeptRanking.length > 0 || deptRows.length > 0;

    return (
        <AppLayout pageTitle="Main Dashboard">
            <Head title="Main Dashboard" />

            <div className="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#F5F5F3]">
                <div className="relative overflow-hidden rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-6 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div className="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10" />
                    <div className="pointer-events-none absolute -top-10 -right-10 w-48 h-48 rounded-full bg-[#D4AF37]/10 blur-3xl" />
                    <div className="pointer-events-none absolute -bottom-16 left-1/3 w-56 h-56 rounded-full bg-white/10 blur-3xl" />
                    <div className="relative">
                        <h1 className="text-2xl font-black tracking-tight leading-tight">
                            <span className="theme-header-text text-white/90">
                                Hi, {greeting} {currentUserName}
                            </span>{' '}
                            👋
                        </h1>
                    </div>
                </div>
            </div>

            <div className="px-4 pb-4 space-y-3">
                {flash.success && <div className="bg-emerald-50 text-emerald-700 px-3 py-2 rounded-xl text-xs border border-emerald-200">{flash.success}</div>}
                {flash.error && <div className="bg-red-50 text-red-700 px-3 py-2 rounded-xl text-xs border border-red-200">{flash.error}</div>}

                <MyPerformanceCard
                    currentFinancialYear={currentFinancialYear}
                    currentUserName={currentUserName}
                    userPosition={userPosition}
                    currentDepartment={currentDepartment}
                    individualKpiCount={individualKpiCount}
                    individualWeightage={individualWeightage}
                    individualPerformance={individualPerformance}
                    myOnTrack={myOnTrack}
                    myAtRisk={myAtRisk}
                    myAtRiskKpis={myAtRiskKpis}
                    myCompletedByQ={myCompletedByQ}
                    myTotalByQ={myTotalByQ}
                    myProgressByQ={myProgressByQ}
                />

                {showCompanySection && (
                    <div>
                        <button
                            type="button"
                            onClick={toggleCompanySection}
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
                                <span className="text-[9px] font-black text-[#B8860B] bg-[#D4AF37]/10 px-2.5 py-1 rounded-full">{companyOverviewOpen ? 'Hide' : 'Show'}</span>
                                <svg
                                    className="w-4 h-4 text-slate-400 transition-transform duration-300"
                                    style={{ transform: companyOverviewOpen ? 'rotate(0deg)' : 'rotate(-90deg)' }}
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </button>

                        {companyOverviewOpen && (
                            <div className="space-y-3 mt-3">
                                <CompanyOverview
                                    currentFinancialYear={currentFinancialYear}
                                    companyDeptRanking={companyDeptRanking}
                                    deptChartData={deptChartData}
                                    deptRowsCount={deptRows.length}
                                    isManager={isManager}
                                    currentDepartment={currentDepartment}
                                    myDeptPerformance={myDeptPerformance}
                                    myDeptBands={myDeptBands}
                                    totalStaffCount={totalStaffCount}
                                    companyTotalStaff={companyTotalStaff}
                                    companyDeptCount={companyDeptCount}
                                    totalCompletedByQ={totalCompletedByQ}
                                    totalByQ={totalByQ}
                                    totalCompletedAnnual={totalCompletedAnnual}
                                    totalKpisVisible={totalKpisVisible}
                                    themeAccent2={layout.themeAccent2}
                                />

                                {isManager && deptRows.length > 0 && (
                                    <>
                                        <QuarterlyTrendChart currentFinancialYear={currentFinancialYear} deptChartData={deptChartData} />
                                        <DepartmentAccordion deptRows={deptRows} isSltOffice={isSltOffice} currentUserName={currentUserName} />
                                    </>
                                )}
                            </div>
                        )}
                    </div>
                )}

                {(hasAnyLinkage || canAssignTarget) && (
                    <Link
                        href="/linkages"
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
                                <p className="text-[9px] text-slate-400 mt-0.5">
                                    {hasAnyLinkage ? (
                                        <>
                                            {linkageTotalCount} cascading target{linkageTotalCount === 1 ? '' : 's'}
                                            {linkageGapCount > 0 && ` · ${linkageGapCount} gap${linkageGapCount === 1 ? '' : 's'}`}
                                        </>
                                    ) : (
                                        'No cascading targets yet — assign one to your team'
                                    )}
                                </p>
                            </div>
                        </div>
                        <span className="text-[9px] font-black text-[#B8860B] bg-[#D4AF37]/10 px-2.5 py-1 rounded-full shrink-0">View →</span>
                    </Link>
                )}

                <div>
                    <div className="flex items-center justify-between mb-3">
                        <div>
                            <h2 className="text-sm font-black text-slate-900 inline-block border-b-2 border-[#D4AF37] pb-1">
                                My KPIs <span className="font-normal text-slate-400 text-xs">· {currentFinancialYear}</span>
                            </h2>
                            {individualKpiCount > 0 && (
                                <p className="text-[9px] text-slate-400 mt-0.5">
                                    {individualKpiCount} KPIs · {individualWeightage.toFixed(0)}% total weightage
                                </p>
                            )}
                        </div>
                        {/* /kpi/create is still a plain Blade view(), not an Inertia page --
                            a real <a> tag here, not Inertia's <Link>, or Inertia's
                            non-Inertia-response handling renders it inside a sandboxed
                            iframe instead of navigating. See NavItem['legacy'] in config/navigation.ts. */}
                        <a href="/kpi/create" className="px-3 py-1.5 theme-soft-btn rounded-xl text-xs font-black transition">
                            + Add KPI
                        </a>
                    </div>

                    {individualKpiCount === 0 ? (
                        <div className="bg-white rounded-2xl border border-dashed border-[#E5E7EB] p-10 shadow-sm text-center">
                            <p className="text-slate-400 text-sm font-bold">No KPIs yet for {currentFinancialYear}</p>
                            <p className="text-slate-300 text-xs mt-1">Create your first KPI to start tracking performance</p>
                            <a href="/kpi/create" className="inline-block mt-4 px-4 py-2 theme-soft-btn rounded-xl text-xs font-black transition">
                                + Create KPI
                            </a>
                        </div>
                    ) : (
                        <a href="/kpi" className="flex flex-wrap items-center gap-2 bg-white rounded-2xl border border-[#E5E7EB] shadow-sm p-4 hover:bg-slate-50/60 transition">
                            {myCategoryCounts.map(({ category, count }) => (
                                <span key={category || 'General'} className={`px-2.5 py-1 rounded-lg text-xs font-black shadow-sm ${categoryStyleFor(category).bg}`}>
                                    {category || 'General'} · {count}
                                </span>
                            ))}
                            <span className="ml-auto text-xs font-black text-[#B8860B] shrink-0">View All KPIs →</span>
                        </a>
                    )}
                </div>
            </div>

            <style>{`
                .thin-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
                .thin-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
            `}</style>
        </AppLayout>
    );
}
