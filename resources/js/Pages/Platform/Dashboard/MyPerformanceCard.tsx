import { Link } from '@inertiajs/react';
import { scoreStyle } from '@/lib/scoreStyle';
import { Card, StatCard } from '@/Components/Platform/ui';
import { ExclamationTriangleIcon } from '@/Components/Platform/Icons';

export interface MyPerformance {
    overall_score: number | null;
    kpi_count: number;
    on_track: number;
    at_risk: number;
    needs_attention: Array<{ name: string; achievement: number }>;
}

/**
 * Ports the legacy Dashboard/MyPerformanceCard.tsx's overall shape (score +
 * status badge, On Track/At Risk tiles, "Needs Attention" list) onto the
 * Platform's own data — see WeightedScoreService's docblock for how the
 * score is computed from each assigned KPI's latest submission instead of a
 * quarterly rollup (the Platform has no per-period tracking yet). Reuses
 * `scoreStyle()` as-is: it's a pure function with no legacy-specific
 * assumptions.
 */
export default function MyPerformanceCard({ companyId, performance }: { companyId: string; performance: MyPerformance }) {
    const score = performance.overall_score;
    const style = score !== null ? scoreStyle(score) : null;

    return (
        <Card title="My performance" description={`${performance.kpi_count} KPI assigned to you in this company`}>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div className="rounded-xl bg-slate-50 p-4">
                    <p className="text-[11px] font-semibold text-slate-400 uppercase tracking-wide mb-1">Overall score</p>
                    {score !== null && style ? (
                        <>
                            <p className={`text-3xl font-bold tabular-nums ${style.text}`}>{score.toFixed(1)}%</p>
                            <span className={`inline-flex mt-1.5 items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${style.badge}`}>
                                {style.label}
                            </span>
                        </>
                    ) : (
                        <p className="text-sm text-slate-400 mt-1">No submissions yet</p>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-2">
                    <StatCard label="On track" value={performance.on_track} tone="success" />
                    <StatCard label="At risk" value={performance.at_risk} tone={performance.at_risk > 0 ? 'danger' : 'default'} />
                </div>
            </div>

            {performance.needs_attention.length > 0 && (
                <div className="rounded-xl border border-red-100 bg-red-50 p-4">
                    <div className="flex items-center gap-1.5 mb-2">
                        <ExclamationTriangleIcon className="w-4 h-4 text-red-500" />
                        <p className="text-xs font-bold text-red-700 uppercase tracking-wide">Needs attention</p>
                    </div>
                    <ul className="space-y-1">
                        {performance.needs_attention.map((kpi, i) => (
                            <li key={i} className="text-sm text-red-800">
                                {kpi.name} <span className="text-red-500">— {kpi.achievement.toFixed(0)}%</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between">
                <p className="text-xs text-slate-400">
                    Quarterly target tracking isn't built on the Platform yet — scores here reflect your latest reported value per KPI.
                </p>
                <Link href={`/platform/companies/${companyId}/weightage`} className="text-xs font-semibold text-brand-800 hover:underline flex-none ml-3">
                    View my KPIs →
                </Link>
            </div>
        </Card>
    );
}
