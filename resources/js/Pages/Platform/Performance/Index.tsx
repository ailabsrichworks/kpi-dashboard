import { Link } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import PeriodSwitcher from '@/Components/Platform/PeriodSwitcher';
import PerformanceTrend from '@/Components/Platform/PerformanceTrend';
import { Card, GoalProgressCard, InsightCard, NeedsAttentionCard, PrimaryButton, StatCard } from '@/Components/Platform/ui';
import { FlagIcon } from '@/Components/Platform/Icons';
import { performanceStatusFor } from '@/lib/performanceStatus';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface Goal {
    id: string;
    title: string;
    category: string | null;
    status: string;
    users: { name: string } | null;
}

interface KpiAchievementRow {
    kpi_id: string;
    name: string;
    department_name: string | null;
    achievement_pct: number | null;
}

interface DepartmentRow {
    department_id: string;
    department_name: string;
    avg_achievement_pct: number | null;
    kpi_count: number;
}

interface PerformancePageProps {
    company: Company;
    period: { financial_year: number; period_type: 'quarter' | 'month'; period_number: number };
    summary: { avg_achievement_pct: number | null } | null;
    goals: Goal[];
    goalStatusCounts: { on_track: number; at_risk: number; critical: number };
    categoryBreakdown: Record<string, number>;
    needsAttention: KpiAchievementRow[];
    departments: DepartmentRow[];
    trend: Array<{ financial_year: number; period_number: number; avg_achievement_pct: number | null }>;
    insight: string | null;
    [key: string]: unknown;
}

export default function PerformanceIndex({
    company,
    period,
    summary,
    goals,
    goalStatusCounts,
    categoryBreakdown,
    needsAttention,
    departments,
    trend,
    insight,
}: PerformancePageProps) {
    const overall = summary?.avg_achievement_pct ?? null;
    const overallStatus = performanceStatusFor(overall);

    return (
        <PlatformLayout
            title="Company Performance"
            description="Is the company achieving its strategy, where are the gaps, and what needs action."
            company={company}
            actions={<PeriodSwitcher financialYear={period.financial_year} periodType={period.period_type} periodNumber={period.period_number} />}
        >
            <Card className="mb-5">
                <div className="flex flex-wrap items-end justify-between gap-6">
                    <div>
                        <p className="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Company Performance</p>
                        <p className="text-5xl font-bold tabular-nums text-slate-800">{overall !== null ? `${overall.toFixed(0)}%` : '—'}</p>
                        <p className="text-sm text-slate-500 mt-1 capitalize">
                            {overallStatus.replace('_', ' ')} · Q{period.period_number} FY{period.financial_year}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-4">
                        {Object.entries(categoryBreakdown).map(([category, pct]) => (
                            <div key={category} className="text-center">
                                <p className="text-lg font-bold tabular-nums text-slate-700">{pct.toFixed(0)}%</p>
                                <p className="text-[11px] text-slate-400">{category}</p>
                            </div>
                        ))}
                        <div className="text-center">
                            <p className="text-lg font-bold tabular-nums text-emerald-600">{goalStatusCounts.on_track}</p>
                            <p className="text-[11px] text-slate-400">Goals on track</p>
                        </div>
                        <div className="text-center">
                            <p className="text-lg font-bold tabular-nums text-amber-600">{goalStatusCounts.at_risk}</p>
                            <p className="text-[11px] text-slate-400">Goals at risk</p>
                        </div>
                    </div>
                </div>
            </Card>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
                <div className="lg:col-span-2">
                    <Card title="Needs Attention" description="The KPIs furthest below where they should be">
                        {needsAttention.length === 0 ? (
                            <p className="text-sm text-slate-500 py-6 text-center">Nothing needs attention right now — every KPI is at or above 85%.</p>
                        ) : (
                            needsAttention.map((k) => (
                                <NeedsAttentionCard
                                    key={k.kpi_id}
                                    title={k.name}
                                    pct={k.achievement_pct}
                                    status={performanceStatusFor(k.achievement_pct)}
                                    owner={k.department_name}
                                    actions={
                                        <Link href={`/platform/companies/${company.id}/kpis`} className="text-xs font-semibold text-brand-800 hover:underline">
                                            View KPI
                                        </Link>
                                    }
                                />
                            ))
                        )}
                    </Card>
                </div>

                <Card title="Department Performance">
                    {departments.length === 0 ? (
                        <p className="text-sm text-slate-500 py-6 text-center">No department-owned KPIs yet.</p>
                    ) : (
                        <ul className="space-y-3">
                            {departments.map((d) => {
                                const pct = d.avg_achievement_pct ?? 0;
                                return (
                                    <li key={d.department_id}>
                                        <div className="flex items-center justify-between mb-1">
                                            <span className="text-sm font-medium text-slate-700">{d.department_name}</span>
                                            <span className="text-sm font-bold tabular-nums text-slate-600">
                                                {d.avg_achievement_pct !== null ? `${pct.toFixed(0)}%` : '—'}
                                            </span>
                                        </div>
                                        <div className="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                            <div
                                                className={`h-full rounded-full ${pct >= 85 ? 'bg-emerald-500' : pct >= 60 ? 'bg-amber-500' : 'bg-red-500'}`}
                                                style={{ width: `${Math.min(100, pct)}%` }}
                                            />
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </Card>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
                <div className="lg:col-span-2">
                    <PerformanceTrend
                        description="Company-wide average achievement, most recent periods"
                        points={trend.map((t) => ({ label: `Q${t.period_number} FY${t.financial_year}`, value: t.avg_achievement_pct }))}
                    />
                </div>
                <InsightCard
                    actions={
                        <>
                            <PrimaryButton onClick={() => (window.location.href = '/platform/anira')} className="text-xs px-3 py-1.5">
                                Ask ANIRA
                            </PrimaryButton>
                        </>
                    }
                >
                    {insight ?? 'Not enough approved data across recent periods yet to surface a trend.'}
                </InsightCard>
            </div>

            <Card title="Company Goals" description="What the company's KPIs ultimately roll up into">
                {goals.length === 0 ? (
                    <p className="text-sm text-slate-500 py-6 text-center flex flex-col items-center gap-2">
                        <FlagIcon className="w-8 h-8 text-slate-300" />
                        No company goals have been created yet.
                    </p>
                ) : (
                    goals.map((g) => (
                        <GoalProgressCard
                            key={g.id}
                            title={g.title}
                            status={g.status}
                            owner={g.users?.name}
                            onClick={() => (window.location.href = `/platform/companies/${company.id}/performance/goals/${g.id}`)}
                        />
                    ))
                )}
            </Card>
        </PlatformLayout>
    );
}
