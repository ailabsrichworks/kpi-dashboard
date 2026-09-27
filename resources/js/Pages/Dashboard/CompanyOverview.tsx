import { Bar, Doughnut } from 'react-chartjs-2';
import '../../lib/chartSetup';
import { scoreStyle } from '../../lib/scoreStyle';

interface DeptRankingRow {
    code: string;
    score: number;
    staff: number;
}

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

interface CompanyOverviewProps {
    currentFinancialYear: string;
    companyDeptRanking: DeptRankingRow[];
    deptChartData: DeptChartRow[];
    deptRowsCount: number;
    isManager: boolean;
    currentDepartment: string;
    myDeptPerformance: number;
    myDeptBands: number[];
    totalStaffCount: number;
    companyTotalStaff: number;
    companyDeptCount: number;
    totalCompletedByQ: Record<string, number>;
    totalByQ: Record<string, number>;
    totalCompletedAnnual: number;
    totalKpisVisible: number;
    themeAccent2: string;
}

const QUARTERS = ['Q1', 'Q2', 'Q3', 'Q4'];
const BAND_COLORS = ['#059669', '#D4AF37', '#F97316', '#EF4444'];
const BAND_LIST: [string, string][] = [
    ['#059669', 'Excellent'],
    ['#D4AF37', 'Good'],
    ['#F97316', 'Watch'],
    ['#EF4444', 'Critical'],
];

export default function CompanyOverview({
    currentFinancialYear,
    companyDeptRanking,
    deptChartData,
    deptRowsCount,
    isManager,
    currentDepartment,
    myDeptPerformance,
    myDeptBands,
    totalStaffCount,
    companyTotalStaff,
    companyDeptCount,
    totalCompletedByQ,
    totalByQ,
    totalCompletedAnnual,
    totalKpisVisible,
    themeAccent2,
}: CompanyOverviewProps) {
    const rankingCount = companyDeptRanking.length;
    if (rankingCount === 0 && deptRowsCount === 0) return null;

    const src = rankingCount ? companyDeptRanking : deptChartData.map((d) => ({ code: d.code, score: d.annual, staff: d.staff }));
    const sorted = [...src].sort((a, b) => b.score - a.score);
    const maxScore = Math.max(0, ...sorted.map((d) => d.score));
    const axisMax = Math.max(10, Math.ceil((maxScore * 1.15) / 10) * 10);
    const myDeptScoreStyle = scoreStyle(myDeptPerformance);
    const annualPct = totalKpisVisible > 0 ? Math.round((totalCompletedAnnual / totalKpisVisible) * 100) : 0;

    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 items-start">
            {/* Card 1: Department Annual Ranking */}
            <div
                className={`${isManager ? 'xl:col-span-2' : 'sm:col-span-2 xl:col-span-5'} bg-white rounded-2xl overflow-hidden shadow-sm border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37]`}
            >
                <div className="p-4">
                    <div className="flex items-start justify-between mb-3">
                        <div>
                            <h3 className="text-[11px] font-black text-slate-800 leading-tight">Department Annual Ranking</h3>
                            <p className="text-[9px] text-slate-400 mt-0.5">{rankingCount} departments · by achievement</p>
                        </div>
                        <span className="text-[9px] font-bold text-[#B8860B] bg-[#D4AF37]/10 px-2 py-0.5 rounded-full">{currentFinancialYear}</span>
                    </div>
                    <div style={{ height: Math.max(80, rankingCount * 28), position: 'relative' }}>
                        {sorted.length > 0 && (
                            <Bar
                                data={{
                                    labels: sorted.map((d) => d.code),
                                    datasets: [
                                        {
                                            label: 'Annual Score (%)',
                                            data: sorted.map((d) => d.score),
                                            backgroundColor: themeAccent2 + 'cc',
                                            borderColor: themeAccent2,
                                            borderWidth: 1.5,
                                            borderRadius: 6,
                                        },
                                    ],
                                }}
                                options={{
                                    indexAxis: 'y' as const,
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            callbacks: {
                                                label: (c) => ` ${(c.parsed as { x: number }).x.toFixed(1)}%  ·  ${src.find((d) => d.code === c.label)?.staff || 0} staff`,
                                            },
                                        },
                                    },
                                    scales: {
                                        x: { min: 0, max: axisMax, ticks: { callback: (v) => v + '%', font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                                        y: { ticks: { font: { size: 11, weight: 'bold' } }, grid: { display: false } },
                                    },
                                }}
                            />
                        )}
                    </div>
                </div>
            </div>

            {isManager && (
                <>
                    {/* Card 2: Department Achievement */}
                    <div className="bg-white rounded-2xl overflow-hidden shadow-sm border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] flex flex-col">
                        <div className="p-4 flex flex-col items-center text-center flex-1">
                            <p className="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3">{currentDepartment} Achievement</p>
                            <div className="relative mb-3" style={{ width: 88, height: 88 }}>
                                <Doughnut
                                    data={{ datasets: [{ data: myDeptBands, backgroundColor: BAND_COLORS, borderWidth: 2, borderColor: '#fff' }] }}
                                    options={{
                                        cutout: '70%',
                                        responsive: false,
                                        plugins: {
                                            legend: { display: false },
                                            tooltip: { callbacks: { label: (c) => ` ${['Excellent ≥90%', 'Good 75–89%', 'Watch 50–74%', 'Critical <50%'][c.dataIndex]}: ${c.parsed} staff` } },
                                        },
                                    }}
                                    width={88}
                                    height={88}
                                />
                                <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                    <p className={`text-[15px] font-black leading-none ${myDeptScoreStyle.text}`}>{myDeptPerformance.toFixed(1)}%</p>
                                </div>
                            </div>
                            <span className={`inline-block text-[9px] font-black px-3 py-1 rounded-full border ${myDeptScoreStyle.badge} mb-1`}>{myDeptScoreStyle.label}</span>
                            <p className="text-[8px] text-slate-400 mb-3">
                                {totalStaffCount} staff · {currentFinancialYear}
                            </p>
                            <div className="w-full grid grid-cols-2 gap-x-3 gap-y-1.5 pt-3 border-t border-slate-100">
                                {BAND_LIST.map(([color, label], bi) => (
                                    <div key={bi} className="flex items-center gap-1.5">
                                        <span className="w-2 h-2 rounded-full shrink-0" style={{ background: color }} />
                                        <span className="text-[9px] font-bold text-slate-700">{myDeptBands[bi]}</span>
                                        <span className="text-[8px] text-slate-400 ml-0.5">{label}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Card 3: Total Staff */}
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
                                <p className="text-5xl font-black text-slate-900 leading-none">{companyTotalStaff || totalStaffCount}</p>
                                <p className="text-[10px] text-slate-400 mt-2">staff members</p>
                            </div>
                            <div className="w-full mt-4 pt-3 border-t border-slate-100 flex items-center justify-center gap-1.5">
                                <svg className="w-3.5 h-3.5 text-[#B8860B]" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                                    />
                                </svg>
                                <span className="text-[10px] font-bold text-slate-500">{companyDeptCount || deptRowsCount} Departments</span>
                            </div>
                        </div>
                    </div>

                    {/* Card 4: Completed Quarters */}
                    <div className="bg-white rounded-2xl overflow-hidden shadow-sm border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37]">
                        <div className="p-4">
                            <p className="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-4">Completed Quarters</p>
                            {QUARTERS.map((qi) => {
                                const qc = totalCompletedByQ[qi];
                                const qt = totalByQ[qi];
                                const pct = qt > 0 ? Math.round((qc / qt) * 100) : 0;
                                return (
                                    <div key={qi} className="mb-3">
                                        <div className="flex items-center justify-between mb-1.5">
                                            <div className="flex items-center gap-2">
                                                <span className="text-[10px] font-black text-slate-700">{qi}</span>
                                                <span className="text-[8px] text-slate-400">
                                                    {qc}/{qt} KPIs
                                                </span>
                                            </div>
                                            <span className={`text-[10px] font-black ${pct >= 100 ? 'text-[#B8860B]' : pct > 0 ? 'text-amber-500' : 'text-slate-300'}`}>{pct}%</span>
                                        </div>
                                        <div className="h-2 bg-slate-100 rounded-full overflow-hidden">
                                            <div className={`h-2 rounded-full transition-all ${qc > 0 ? 'bg-[#D4AF37]' : 'bg-slate-200'}`} style={{ width: `${pct}%` }} />
                                        </div>
                                    </div>
                                );
                            })}
                            <div className="mt-3 pt-3 border-t border-slate-100">
                                <div className="flex items-center justify-between mb-1.5">
                                    <span className="text-[9px] font-black text-slate-500">Annual Total</span>
                                    <span className={`text-[10px] font-black ${annualPct > 0 ? 'text-[#B8860B]' : 'text-slate-300'}`}>{annualPct}%</span>
                                </div>
                                <div className="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div
                                        className={`h-2.5 rounded-full ${totalCompletedAnnual > 0 ? 'theme-header-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019]' : 'bg-slate-200'}`}
                                        style={{ width: `${annualPct}%` }}
                                    />
                                </div>
                                <p className="text-[8px] text-slate-400 mt-1 text-right">
                                    {totalCompletedAnnual}/{totalKpisVisible} KPIs done
                                </p>
                            </div>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
