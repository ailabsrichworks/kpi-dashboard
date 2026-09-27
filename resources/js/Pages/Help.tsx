import { Head } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

export default function Help() {
    return (
        <AppLayout pageTitle="Help & Guide" pageSubtitle="Understand what every score, colour, and status means">
            <Head title="Help & Guide" />

            <div className="px-4 pt-4 pb-6 space-y-4">
                {/* SCORE BANDS */}
                <div className="bg-white rounded-[20px] border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm p-5">
                    <div className="flex items-center gap-2 mb-4">
                        <div className="w-8 h-8 rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center shrink-0">
                            <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <h2 className="text-sm font-black text-slate-800 uppercase tracking-wider">Score Bands · Performance Achievement Guide</h2>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                        <div className="flex items-start gap-3 p-3 rounded-2xl bg-emerald-50 border border-emerald-100">
                            <div className="mt-1 w-10 h-2 rounded-full bg-gradient-to-r from-emerald-400 to-green-500 shrink-0" />
                            <div className="min-w-0">
                                <p className="text-xs font-black text-emerald-700">Outstanding &nbsp;·&nbsp; 90% – 100%</p>
                                <p className="text-[10px] text-slate-500 mt-0.5 leading-relaxed">On or above target. Outstanding performance.</p>
                            </div>
                        </div>
                        <div className="flex items-start gap-3 p-3 rounded-2xl bg-yellow-50 border border-yellow-100">
                            <div className="mt-1 w-10 h-2 rounded-full bg-gradient-to-r from-yellow-400 to-amber-500 shrink-0" />
                            <div className="min-w-0">
                                <p className="text-xs font-black text-yellow-700">Meets Expectations &nbsp;·&nbsp; 70% – 89%</p>
                                <p className="text-[10px] text-slate-500 mt-0.5 leading-relaxed">Solid progress. Small gaps to close.</p>
                            </div>
                        </div>
                        <div className="flex items-start gap-3 p-3 rounded-2xl bg-orange-50 border border-orange-100">
                            <div className="mt-1 w-10 h-2 rounded-full bg-gradient-to-r from-orange-400 to-orange-600 shrink-0" />
                            <div className="min-w-0">
                                <p className="text-xs font-black text-orange-700">Below Average &nbsp;·&nbsp; 50% – 69%</p>
                                <p className="text-[10px] text-slate-500 mt-0.5 leading-relaxed">Below expectation. Needs focused attention.</p>
                            </div>
                        </div>
                        <div className="flex items-start gap-3 p-3 rounded-2xl bg-red-50 border border-red-100">
                            <div className="mt-1 w-10 h-2 rounded-full bg-gradient-to-r from-red-500 to-red-600 shrink-0" />
                            <div className="min-w-0">
                                <p className="text-xs font-black text-red-700">Unsatisfactory &nbsp;·&nbsp; 1% – 49%</p>
                                <p className="text-[10px] text-slate-500 mt-0.5 leading-relaxed">Significantly off target. Urgent action required.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* QUARTER STATUS */}
                <div className="bg-white rounded-[20px] border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm p-5">
                    <div className="flex items-center gap-2 mb-4">
                        <div className="w-8 h-8 rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center shrink-0">
                            <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h2 className="text-sm font-black text-slate-800 uppercase tracking-wider">Quarter Status</h2>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
                        <div className="flex items-start gap-3 p-3 rounded-2xl bg-slate-50 border border-slate-200">
                            <span className="mt-1 w-3 h-3 rounded-full bg-slate-400 shrink-0 ring-2 ring-slate-200" />
                            <div>
                                <p className="text-xs font-black text-slate-600">Not Started</p>
                                <p className="text-[10px] text-slate-400 mt-0.5 leading-relaxed">Quarter has not begun or no progress entered yet.</p>
                            </div>
                        </div>
                        <div className="flex items-start gap-3 p-3 rounded-2xl bg-emerald-50 border border-emerald-100">
                            <span className="mt-1 w-3 h-3 rounded-full bg-emerald-500 shrink-0 ring-2 ring-emerald-200" />
                            <div>
                                <p className="text-xs font-black text-emerald-700">On Track</p>
                                <p className="text-[10px] text-slate-400 mt-0.5 leading-relaxed">Progressing as planned. No issues foreseen.</p>
                            </div>
                        </div>
                        <div className="flex items-start gap-3 p-3 rounded-2xl bg-yellow-50 border border-yellow-100">
                            <span className="mt-1 w-3 h-3 rounded-full bg-yellow-500 shrink-0 ring-2 ring-yellow-200" />
                            <div>
                                <p className="text-xs font-black text-yellow-700">At Risk</p>
                                <p className="text-[10px] text-slate-400 mt-0.5 leading-relaxed">Some concern. Could miss target if not acted on soon.</p>
                            </div>
                        </div>
                        <div className="flex items-start gap-3 p-3 rounded-2xl bg-red-50 border border-red-100">
                            <span className="mt-1 w-3 h-3 rounded-full bg-red-500 shrink-0 ring-2 ring-red-200" />
                            <div>
                                <p className="text-xs font-black text-red-700">In Trouble</p>
                                <p className="text-[10px] text-slate-400 mt-0.5 leading-relaxed">Significantly behind plan. Immediate action needed.</p>
                            </div>
                        </div>
                        <div className="flex items-start gap-3 p-3 rounded-2xl bg-[#FBF5EF] border border-[#6B3F2A]/20">
                            <span className="mt-1 w-3 h-3 rounded-full bg-[#6B3F2A] shrink-0 ring-2 ring-[#6B3F2A]/30" />
                            <div>
                                <p className="text-xs font-black text-[#6B3F2A]">Completed</p>
                                <p className="text-[10px] text-slate-400 mt-0.5 leading-relaxed">Quarter target achieved and officially closed.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    {/* HOW SCORE IS CALCULATED */}
                    <div className="bg-white rounded-[20px] border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm p-5">
                        <div className="flex items-center gap-2 mb-4">
                            <div className="w-8 h-8 rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center shrink-0">
                                <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <h2 className="text-sm font-black text-slate-800 uppercase tracking-wider">How Score Works</h2>
                        </div>
                        <div className="space-y-2.5">
                            <div className="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                                <div className="flex items-center gap-2 mb-1.5">
                                    <span className="w-5 h-5 rounded-full bg-slate-700 text-white text-[9px] font-black flex items-center justify-center shrink-0">1</span>
                                    <p className="text-[11px] font-black text-slate-600 uppercase">Quarter Score</p>
                                </div>
                                <p className="text-[11px] text-slate-500 leading-relaxed pl-7">
                                    For each quarter:
                                    <br />
                                    <span className="font-semibold text-slate-700">Actual ÷ Base Target × 100</span>
                                    <br />
                                    Scores are added across all 4 quarters.
                                </p>
                            </div>
                            <div className="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                                <div className="flex items-center gap-2 mb-1.5">
                                    <span className="w-5 h-5 rounded-full bg-slate-700 text-white text-[9px] font-black flex items-center justify-center shrink-0">2</span>
                                    <p className="text-[11px] font-black text-slate-600 uppercase">Weighted Score</p>
                                </div>
                                <p className="text-[11px] text-slate-500 leading-relaxed pl-7">
                                    <span className="font-semibold text-slate-700">KPI Score × Weightage ÷ 100</span>
                                    <br />
                                    Each KPI contributes proportionally based on its importance.
                                </p>
                            </div>
                            <div className="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                                <div className="flex items-center gap-2 mb-1.5">
                                    <span className="w-5 h-5 rounded-full bg-slate-700 text-white text-[9px] font-black flex items-center justify-center shrink-0">3</span>
                                    <p className="text-[11px] font-black text-slate-600 uppercase">Total KPI Score</p>
                                </div>
                                <p className="text-[11px] text-slate-500 leading-relaxed pl-7">
                                    Sum of all <span className="font-semibold text-slate-700">Weighted Scores</span>.
                                    <br />
                                    All KPI weightages <strong>must total 100%</strong>.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* QUICK REFERENCE */}
                    <div className="bg-white rounded-[20px] border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm p-5">
                        <div className="flex items-center gap-2 mb-4">
                            <div className="w-8 h-8 rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center shrink-0">
                                <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <h2 className="text-sm font-black text-slate-800 uppercase tracking-wider">Quick Reference</h2>
                        </div>
                        <div className="space-y-2.5">
                            <div className="p-3 rounded-2xl bg-amber-50 border border-amber-100">
                                <p className="text-[11px] font-black text-amber-700 mb-1">What is Weightage?</p>
                                <p className="text-[11px] text-slate-500 leading-relaxed">
                                    A % value showing <span className="font-semibold">how much this KPI counts</span> towards your total score. Example: a KPI with 30% weightage contributes 3× more than one at 10%.
                                </p>
                            </div>
                            <div className="p-3 rounded-2xl bg-violet-50 border border-violet-100">
                                <p className="text-[11px] font-black text-violet-700 mb-1">What is Base vs Stretch Target?</p>
                                <p className="text-[11px] text-slate-500 leading-relaxed">
                                    <span className="font-semibold">Base Target</span> = the minimum expected result.
                                    <br />
                                    <span className="font-semibold">Stretch Target</span> = an ambitious goal beyond base.
                                </p>
                            </div>
                            <div className="p-3 rounded-2xl bg-sky-50 border border-sky-100">
                                <p className="text-[11px] font-black text-sky-700 mb-1">What are Q1, Q2, Q3, Q4?</p>
                                <p className="text-[11px] text-slate-500 leading-relaxed">
                                    The year is split into 4 quarters:
                                    <br />
                                    Q1 Jan–Mar · Q2 Apr–Jun
                                    <br />
                                    Q3 Jul–Sep · Q4 Oct–Dec
                                </p>
                            </div>
                            <div className="p-3 rounded-2xl bg-rose-50 border border-rose-100">
                                <p className="text-[11px] font-black text-rose-700 mb-1">Why does my score show 0%?</p>
                                <p className="text-[11px] text-slate-500 leading-relaxed">
                                    Either no actuals have been entered yet, or your total weightage is not set to 100%. Check the Weightage page.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <p className="text-[10px] text-slate-400 text-center pb-2">
                    Score is calculated in real-time from your actual vs target values across all quarters. Contact your manager if you see unexpected results.
                </p>
            </div>
        </AppLayout>
    );
}
