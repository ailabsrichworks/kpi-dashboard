import { Link } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Card, InfoTooltip, StatCard } from '@/Components/Platform/ui';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface PeoplePageProps {
    company: Company;
    stats: {
        employees: number;
        overall_performance: number | null;
        kpi_update_completion: number | null;
        managers: number;
        departments: number;
    };
    distribution: { exceeded: number; on_track: number; at_risk: number; critical: number };
    compliance: {
        employees_with_kpi: number;
        employees_without_kpi: number;
        update_completion_pct: number | null;
        updates_overdue: number;
        pending_approval: number;
    };
    attention: { critical_performance: number; no_active_kpi: number; no_reporting_manager: number };
    reviewsTracked: boolean;
    [key: string]: unknown;
}

const DIST_COLOR: Record<string, string> = {
    exceeded: 'bg-indigo-500',
    on_track: 'bg-emerald-500',
    at_risk: 'bg-amber-500',
    critical: 'bg-red-500',
};

const DIST_LABEL: Record<string, string> = {
    exceeded: 'Exceeded',
    on_track: 'On Track',
    at_risk: 'At Risk',
    critical: 'Critical',
};

function AttentionRow({ label, count, href }: { label: string; count: number; href: string }) {
    return (
        <Link href={href} className="flex items-center justify-between py-2.5 border-b border-slate-100 last:border-0 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
            <span className="text-sm text-slate-700">{label}</span>
            <span className={`text-sm font-bold tabular-nums ${count > 0 ? 'text-red-600' : 'text-slate-400'}`}>{count}</span>
        </Link>
    );
}

export default function PeopleIndex({ company, stats, distribution, compliance, attention, reviewsTracked }: PeoplePageProps) {
    const totalDistributed = distribution.exceeded + distribution.on_track + distribution.at_risk + distribution.critical;

    return (
        <PlatformLayout
            title="People Performance Command Centre"
            description="Whether people and managers are managing performance properly — not just the final scores."
            company={company}
        >
            <div className="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">
                <StatCard label="Employees" value={stats.employees} />
                <StatCard
                    label="Overall Performance"
                    value={stats.overall_performance !== null ? `${stats.overall_performance.toFixed(0)}%` : '—'}
                />
                <StatCard
                    label="KPI Update Completion"
                    value={stats.kpi_update_completion !== null ? `${stats.kpi_update_completion.toFixed(0)}%` : '—'}
                />
                <StatCard label="Managers" value={stats.managers} />
                <StatCard label="Departments" value={stats.departments} />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
                <Card
                    title="Performance Distribution"
                    description="Click a group to see who's in it"
                >
                    {totalDistributed === 0 ? (
                        <p className="text-sm text-slate-500 py-6 text-center">No employees have an owned KPI with an approved result yet.</p>
                    ) : (
                        <ul className="space-y-3">
                            {(['exceeded', 'on_track', 'at_risk', 'critical'] as const).map((key) => (
                                <li key={key}>
                                    <Link
                                        href={`/platform/companies/${company.id}/people/employees?status=${key}`}
                                        className="flex items-center justify-between mb-1 hover:opacity-80"
                                    >
                                        <span className="text-sm font-medium text-slate-700">{DIST_LABEL[key]}</span>
                                        <span className="text-sm font-bold tabular-nums text-slate-600">{distribution[key]}</span>
                                    </Link>
                                    <div className="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                        <div
                                            className={`h-full rounded-full ${DIST_COLOR[key]}`}
                                            style={{ width: totalDistributed ? `${(distribution[key] / totalDistributed) * 100}%` : '0%' }}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card title="People Requiring Attention">
                    <AttentionRow label="Critical performance" count={attention.critical_performance} href={`/platform/companies/${company.id}/people/employees?status=critical`} />
                    <AttentionRow label="No active KPI" count={attention.no_active_kpi} href={`/platform/companies/${company.id}/people/employees`} />
                    <AttentionRow label="No reporting manager" count={attention.no_reporting_manager} href={`/platform/companies/${company.id}/departments`} />
                    <AttentionRow label="KPI awaiting approval" count={compliance.pending_approval} href={`/platform/companies/${company.id}/approvals`} />
                </Card>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <Card title="KPI Management" description="Is performance management actually happening, not just the final score">
                    <dl className="grid grid-cols-2 gap-4">
                        <div>
                            <dt className="text-xs text-slate-400">Employees with active KPI</dt>
                            <dd className="text-lg font-bold tabular-nums text-slate-700">{compliance.employees_with_kpi}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-slate-400">Without a KPI</dt>
                            <dd className="text-lg font-bold tabular-nums text-slate-700">{compliance.employees_without_kpi}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-slate-400">Updates complete this period</dt>
                            <dd className="text-lg font-bold tabular-nums text-slate-700">
                                {compliance.update_completion_pct !== null ? `${compliance.update_completion_pct.toFixed(0)}%` : '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-slate-400">Updates overdue</dt>
                            <dd className="text-lg font-bold tabular-nums text-slate-700">{compliance.updates_overdue}</dd>
                        </div>
                    </dl>
                </Card>

                <Card
                    title={
                        <span className="inline-flex items-center gap-1.5">
                            Performance Review Cycle
                            <InfoTooltip text="Performix doesn't yet have a review-tracking feature — this card will show completion once that's built, rather than showing invented numbers." />
                        </span>
                    }
                >
                    {reviewsTracked ? null : (
                        <p className="text-sm text-slate-500 py-6 text-center">
                            Performance reviews aren't tracked in Performix yet — there's no review-cycle data to show here honestly.
                        </p>
                    )}
                </Card>
            </div>
        </PlatformLayout>
    );
}
