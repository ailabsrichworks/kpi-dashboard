import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Card } from '@/Components/Platform/ui';

// Mirrors resources/js/lib/scoreStyle.ts's own 5 bands exactly — this page's
// job is to correctly explain what a user will actually see elsewhere on the
// Platform, not legacy's different 4-band language.
const SCORE_BANDS = [
    { label: 'Exceeded', range: '> 100%', bg: 'bg-emerald-50', border: 'border-emerald-100', text: 'text-emerald-800', bar: 'from-emerald-600 to-emerald-700', desc: 'Beat target. Best possible outcome.' },
    { label: 'Good', range: '76% – 100%', bg: 'bg-emerald-50', border: 'border-emerald-100', text: 'text-emerald-700', bar: 'from-yellow-400 to-emerald-600', desc: 'On or close to target.' },
    { label: 'Watch', range: '51% – 75%', bg: 'bg-amber-50', border: 'border-amber-100', text: 'text-amber-700', bar: 'from-orange-500 to-yellow-400', desc: 'Some concern — could miss target if not acted on soon.' },
    { label: 'Risk', range: '26% – 50%', bg: 'bg-orange-50', border: 'border-orange-100', text: 'text-orange-700', bar: 'from-red-600 to-orange-500', desc: 'Below expectation. Needs focused attention.' },
    { label: 'Critical', range: '0% – 25%', bg: 'bg-red-50', border: 'border-red-100', text: 'text-red-700', bar: 'bg-red-600', desc: 'Significantly off target. Urgent action required.' },
];

// Mirrors kpi_quarters.status's real check constraint exactly.
const QUARTER_STATUSES = [
    { label: 'Not Started', dot: 'bg-slate-400 ring-slate-200', bg: 'bg-slate-50 border-slate-200', text: 'text-slate-600', desc: 'No target/actual reported for this quarter yet.' },
    { label: 'On Track', dot: 'bg-emerald-500 ring-emerald-200', bg: 'bg-emerald-50 border-emerald-100', text: 'text-emerald-700', desc: 'Progressing as planned.' },
    { label: 'At Risk', dot: 'bg-amber-500 ring-amber-200', bg: 'bg-amber-50 border-amber-100', text: 'text-amber-700', desc: 'Some concern — may miss target if not acted on soon.' },
    { label: 'Pending Completion', dot: 'bg-sky-500 ring-sky-200', bg: 'bg-sky-50 border-sky-100', text: 'text-sky-700', desc: 'Submitted for sign-off — waiting on your Company Admin.' },
    { label: 'Completed', dot: 'bg-[#6B3F2A] ring-[#6B3F2A]/30', bg: 'bg-[#FBF5EF] border-[#6B3F2A]/20', text: 'text-[#6B3F2A]', desc: 'Quarter target achieved and officially closed.' },
];

export default function HelpIndex() {
    return (
        <PlatformLayout title="Help & Guide" description="Understand what every score, colour, and status means." maxWidth="max-w-4xl">
            <div className="space-y-4">
                <Card title="Score bands · Performance achievement guide">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        {SCORE_BANDS.map((b) => (
                            <div key={b.label} className={`flex items-start gap-3 p-3 rounded-2xl border ${b.bg} ${b.border}`}>
                                <div className={`mt-1 w-10 h-2 rounded-full shrink-0 bg-gradient-to-r ${b.bar}`} />
                                <div className="min-w-0">
                                    <p className={`text-xs font-black ${b.text}`}>{b.label} &nbsp;·&nbsp; {b.range}</p>
                                    <p className="text-[11px] text-slate-500 mt-0.5 leading-relaxed">{b.desc}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>

                <Card title="Quarter status">
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        {QUARTER_STATUSES.map((s) => (
                            <div key={s.label} className={`flex items-start gap-3 p-3 rounded-2xl border ${s.bg}`}>
                                <span className={`mt-1 w-3 h-3 rounded-full shrink-0 ring-2 ${s.dot}`} />
                                <div>
                                    <p className={`text-xs font-black ${s.text}`}>{s.label}</p>
                                    <p className="text-[11px] text-slate-400 mt-0.5 leading-relaxed">{s.desc}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>

                <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    <Card title="How score works">
                        <div className="space-y-2.5">
                            <div className="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                                <div className="flex items-center gap-2 mb-1.5">
                                    <span className="w-5 h-5 rounded-full bg-slate-700 text-white text-[9px] font-black flex items-center justify-center shrink-0">1</span>
                                    <p className="text-[11px] font-black text-slate-600 uppercase">KPI achievement</p>
                                </div>
                                <p className="text-[11px] text-slate-500 leading-relaxed pl-7">
                                    <span className="font-semibold text-slate-700">Actual ÷ Target × 100</span>, capped at 100% for a single reported value — or, for a
                                    quarterly KPI, the sum of every quarter&apos;s actual divided by the sum of every quarter&apos;s target.
                                </p>
                            </div>
                            <div className="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                                <div className="flex items-center gap-2 mb-1.5">
                                    <span className="w-5 h-5 rounded-full bg-slate-700 text-white text-[9px] font-black flex items-center justify-center shrink-0">2</span>
                                    <p className="text-[11px] font-black text-slate-600 uppercase">Weighted score</p>
                                </div>
                                <p className="text-[11px] text-slate-500 leading-relaxed pl-7">
                                    <span className="font-semibold text-slate-700">Achievement × Weight</span>. Only KPIs your Company Admin has assigned a
                                    weight to count toward your overall score.
                                </p>
                            </div>
                            <div className="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                                <div className="flex items-center gap-2 mb-1.5">
                                    <span className="w-5 h-5 rounded-full bg-slate-700 text-white text-[9px] font-black flex items-center justify-center shrink-0">3</span>
                                    <p className="text-[11px] font-black text-slate-600 uppercase">Overall score</p>
                                </div>
                                <p className="text-[11px] text-slate-500 leading-relaxed pl-7">
                                    Sum of every weighted score, divided by the sum of the weights used. Your weights across all assigned
                                    KPIs should add up to 100% — check the Manage Weightage page.
                                </p>
                            </div>
                        </div>
                    </Card>

                    <Card title="Quick reference">
                        <div className="space-y-2.5">
                            <div className="p-3 rounded-2xl bg-amber-50 border border-amber-100">
                                <p className="text-[11px] font-black text-amber-700 mb-1">What is Weightage?</p>
                                <p className="text-[11px] text-slate-500 leading-relaxed">
                                    A % value showing how much this KPI counts toward your total score. A KPI at 30% weight contributes 3× more than one at 10%.
                                </p>
                            </div>
                            <div className="p-3 rounded-2xl bg-sky-50 border border-sky-100">
                                <p className="text-[11px] font-black text-sky-700 mb-1">What are Q1, Q2, Q3, Q4?</p>
                                <p className="text-[11px] text-slate-500 leading-relaxed">
                                    The financial year is split into 4 calendar quarters: Q1 Jan–Mar · Q2 Apr–Jun · Q3 Jul–Sep · Q4 Oct–Dec.
                                </p>
                            </div>
                            <div className="p-3 rounded-2xl bg-rose-50 border border-rose-100">
                                <p className="text-[11px] font-black text-rose-700 mb-1">Why does my score show &mdash;?</p>
                                <p className="text-[11px] text-slate-500 leading-relaxed">
                                    Either nothing has been reported against this KPI yet, or it has no weight assigned yet. Check Manage Weightage.
                                </p>
                            </div>
                            <div className="p-3 rounded-2xl bg-violet-50 border border-violet-100">
                                <p className="text-[11px] font-black text-violet-700 mb-1">Who approves a completed quarter?</p>
                                <p className="text-[11px] text-slate-500 leading-relaxed">
                                    Your Company Admin — submit it from the Quarterly Progress page once the actual is in, then wait for sign-off.
                                </p>
                            </div>
                        </div>
                    </Card>
                </div>

                <p className="text-[10px] text-slate-400 text-center pb-2">
                    Score is calculated in real time from your actual vs. target values. Contact your Company Admin if you see unexpected results.
                </p>
            </div>
        </PlatformLayout>
    );
}
