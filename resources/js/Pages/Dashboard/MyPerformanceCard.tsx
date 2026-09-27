import { scoreStyle } from '../../lib/scoreStyle';

interface MyPerformanceCardProps {
    currentFinancialYear: string;
    currentUserName: string;
    userPosition: string;
    currentDepartment: string;
    individualKpiCount: number;
    individualWeightage: number;
    individualPerformance: number;
    myOnTrack: number;
    myAtRisk: number;
    myAtRiskKpis: string[];
    myCompletedByQ: Record<string, number>;
    myTotalByQ: Record<string, number>;
    myProgressByQ: Record<string, number>;
}

const QUARTERS = ['Q1', 'Q2', 'Q3', 'Q4'];

export default function MyPerformanceCard({
    currentFinancialYear,
    currentUserName,
    userPosition,
    currentDepartment,
    individualKpiCount,
    individualWeightage,
    individualPerformance,
    myOnTrack,
    myAtRisk,
    myAtRiskKpis,
    myCompletedByQ,
    myTotalByQ,
    myProgressByQ,
}: MyPerformanceCardProps) {
    const individualScoreStyle = scoreStyle(individualPerformance);

    return (
        <div className="bg-white rounded-2xl overflow-hidden shadow-sm border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37]">
            {individualKpiCount === 0 ? (
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
                        <p className="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-1">My Performance · {currentFinancialYear}</p>
                        <h2 className="text-base font-black text-slate-800">No KPIs set for {currentFinancialYear} yet</h2>
                        <p className="text-xs text-slate-500 mt-1">Your score, quarterly progress and at-risk alerts will appear here as soon as your KPIs are created.</p>
                    </div>
                    {/* /kpi and /weightage are still plain Blade view() responses, not
                        Inertia pages -- real <a> tags, not Inertia's <Link>, or Inertia's
                        non-Inertia-response handling renders them inside a sandboxed
                        iframe instead of navigating. See NavItem['legacy'] in config/navigation.ts. */}
                    <div className="flex gap-2 shrink-0">
                        <a href="/kpi" className="bg-[#D4AF37] hover:bg-[#c19c2f] text-[#1a1a1a] px-4 py-2.5 rounded-xl text-xs font-black transition">
                            My KPIs
                        </a>
                        <a href="/weightage" className="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl text-xs font-black transition">
                            Weightage
                        </a>
                    </div>
                </div>
            ) : (
                <div className="flex flex-col lg:flex-row">
                    {/* Left: score panel */}
                    <div className="theme-perf-card p-5 lg:min-w-[240px] xl:min-w-[260px] flex flex-col justify-between">
                        <div>
                            <p className="theme-perf-accent-text text-[9px] uppercase tracking-widest font-black mb-3">My Performance · {currentFinancialYear}</p>
                            <div className="flex items-center gap-3 mb-4">
                                <div className="theme-header-accent-ring w-10 h-10 rounded-full overflow-hidden shrink-0 ring-2 ring-[#D4AF37]/60">
                                    <img
                                        src={`https://ui-avatars.com/api/?name=${encodeURIComponent(currentUserName)}&background=D4AF37&color=1a1a1a&size=40`}
                                        className="w-full h-full object-cover"
                                    />
                                </div>
                                <div>
                                    <h2 className="text-sm font-black text-slate-800 leading-tight">{currentUserName}</h2>
                                    <p className="text-[9px] text-slate-500 mt-0.5">
                                        {userPosition} · {currentDepartment}
                                    </p>
                                </div>
                            </div>
                            {individualWeightage <= 0 ? (
                                <div className="bg-white rounded-xl p-3">
                                    <p className="text-3xl font-black text-slate-300 mb-1">—</p>
                                    <p className="text-xs text-slate-400">{individualKpiCount} KPIs · weightage not set</p>
                                    <a href="/weightage" className="theme-header-dark-text inline-block mt-2 text-xs font-black text-[#7A0019] underline">
                                        Set weightage →
                                    </a>
                                </div>
                            ) : (
                                <div className="bg-white rounded-xl p-3">
                                    <div className="flex items-end gap-1.5 mb-2">
                                        <span className={`text-4xl font-black leading-none ${individualScoreStyle.text}`}>{individualPerformance.toFixed(1)}</span>
                                        <span className="text-lg font-black text-slate-300 mb-0.5">%</span>
                                    </div>
                                    <div className="h-1.5 bg-slate-100 rounded-full overflow-hidden mb-2">
                                        <div className={`h-1.5 rounded-full ${individualScoreStyle.bar}`} style={{ width: `${Math.min(individualPerformance, 100)}%` }} />
                                    </div>
                                    <span className={`inline-block px-2.5 py-0.5 rounded-full text-[9px] font-black border ${individualScoreStyle.badge}`}>
                                        {individualScoreStyle.label}
                                    </span>
                                    <p className="text-[9px] text-slate-400 mt-1.5">
                                        {individualKpiCount} KPIs · {individualWeightage.toFixed(0)}% weightage
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Right: Stats + quarterly completion */}
                    <div className="flex-1 p-5 flex flex-col gap-5">
                        <div className="grid grid-cols-3 gap-3">
                            <div className="bg-slate-50 rounded-2xl p-4 text-center border border-slate-100">
                                <p className="text-3xl font-black text-slate-900">{individualKpiCount}</p>
                                <p className="text-[9px] text-slate-400 uppercase tracking-wide mt-1.5">Total KPIs</p>
                            </div>
                            <div className="bg-emerald-50 rounded-2xl p-4 text-center border border-emerald-100">
                                <p className="text-3xl font-black text-emerald-600">{myOnTrack}</p>
                                <p className="text-[9px] text-emerald-500 uppercase tracking-wide mt-1.5">On Track</p>
                            </div>
                            {myAtRisk > 0 ? (
                                <div className="bg-red-50 rounded-2xl p-4 text-center border border-red-100">
                                    <p className="text-3xl font-black text-red-600">{myAtRisk}</p>
                                    <p className="text-[9px] text-red-400 uppercase tracking-wide mt-1.5">At Risk</p>
                                </div>
                            ) : (
                                <div className="bg-slate-50 rounded-2xl p-4 text-center border border-slate-100">
                                    <p className="text-3xl font-black text-slate-300">0</p>
                                    <p className="text-[9px] text-slate-400 uppercase tracking-wide mt-1.5">At Risk</p>
                                </div>
                            )}
                        </div>

                        {myAtRiskKpis.length > 0 && (
                            <div className="bg-red-50 border border-red-100 rounded-2xl p-3">
                                <p className="text-[9px] font-black text-red-500 uppercase tracking-widest mb-1.5">⚠ Needs Attention</p>
                                <ul className="space-y-0.5">
                                    {myAtRiskKpis.map((title, i) => (
                                        <li key={i} className="text-[11px] text-red-700 font-semibold truncate">
                                            · {title}
                                        </li>
                                    ))}
                                </ul>
                                {myAtRisk > myAtRiskKpis.length && (
                                    <a href="/kpi" className="text-[9px] text-red-500 underline font-bold">
                                        +{myAtRisk - myAtRiskKpis.length} more →
                                    </a>
                                )}
                            </div>
                        )}

                        <div>
                            <p className="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3">My Quarterly Progress</p>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                {QUARTERS.map((qi) => {
                                    const qc = myCompletedByQ[qi];
                                    const qt = myTotalByQ[qi];
                                    const ppct = myProgressByQ[qi];
                                    const qPending = qc === 0;
                                    const pstyle = qPending ? { bar: 'bg-slate-200', text: 'text-slate-400' } : scoreStyle(ppct);
                                    return (
                                        <div key={qi} className="bg-slate-50 rounded-xl p-2.5 border border-slate-100">
                                            <div className="flex items-center justify-between mb-2">
                                                <span className="text-[10px] font-black text-slate-700">{qi}</span>
                                                <span className={`text-[10px] font-black ${pstyle.text}`}>{qPending ? '—' : `${ppct}%`}</span>
                                            </div>
                                            <div className="h-1.5 bg-slate-200 rounded-full overflow-hidden mb-1.5">
                                                <div className={`h-1.5 rounded-full ${pstyle.bar}`} style={{ width: `${qPending ? 100 : Math.min(ppct, 100)}%` }} />
                                            </div>
                                            <p className="text-[8px] text-slate-400">
                                                {qt === 0 ? 'No KPIs' : qPending ? 'Pending' : `${ppct.toFixed(0)}% of target`}
                                                {qt > 0 && <> · {qc === qt ? '✓ Signed off' : `${qc}/${qt} signed off`}</>}
                                            </p>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
