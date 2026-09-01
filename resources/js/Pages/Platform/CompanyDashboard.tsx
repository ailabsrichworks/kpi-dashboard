import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Bar } from 'react-chartjs-2';
import '@/lib/chartSetup';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, InfoTooltip, PrimaryButton, SecondaryButton, StatCard } from '@/Components/Platform/ui';
import { ClipboardCheckIcon, RocketIcon, TargetIcon, UsersIcon } from '@/Components/Platform/Icons';

interface CompanyOverviewData {
    department_count: number;
    user_count: number;
    kpi_count: number;
    submission_count: number;
    avg_achievement_pct: number | null;
}

interface PendingApprovalsData {
    count: number;
    items: Array<{
        id: string;
        created_at: string;
        approval_requests: { workflow_type: string; object_type: string; submitted_at: string } | null;
    }>;
}

interface RecentSubmission {
    id: string;
    value: number;
    submission_date: string;
    kpis: { name: string; unit: string | null; target: number | null } | null;
}

interface PeriodStatusData {
    financial_year: number;
    quarter: number;
    status: string;
    start: string;
    end: string;
}

interface DepartmentAchievementRow {
    department_id: string;
    department_name: string;
    kpi_count: number;
    avg_achievement_pct: number | null;
}

interface TrendPoint {
    financial_year: number;
    period_number: number;
    avg_achievement_pct: number | null;
    kpi_count: number;
}

interface WidgetData {
    company_overview?: CompanyOverviewData | null;
    pending_approvals?: PendingApprovalsData;
    recent_submissions?: RecentSubmission[];
    period_status?: PeriodStatusData | null;
    department_achievement?: DepartmentAchievementRow[];
    achievement_trend?: TrendPoint[];
    [key: string]: unknown;
}

const WIDGET_LABELS: Record<string, string> = {
    company_overview: 'Company Overview',
    pending_approvals: 'Pending Approvals',
    recent_submissions: 'Recent Submissions',
    period_status: 'Period Status',
    department_achievement: 'Department Achievement',
    achievement_trend: 'Achievement Trend',
};

const PERIOD_STATUS_TONE: Record<string, 'neutral' | 'danger' | 'warning' | 'success' | 'info'> = {
    upcoming: 'neutral',
    open: 'success',
    submission_due: 'warning',
    under_review: 'info',
    closed: 'danger',
    locked: 'danger',
};

interface CompanyDashboardPageProps {
    company: { id: string; name: string; code: string };
    layout: string[];
    widgetData: WidgetData;
    availableWidgets: string[];
    canEditLayout: boolean;
    [key: string]: unknown;
}

function CompanyOverviewWidget({ data }: { data: CompanyOverviewData | null | undefined }) {
    if (!data) {
        return (
            <Card title={WIDGET_LABELS.company_overview}>
                <EmptyState title="No data yet" description="This will fill in once departments and KPIs exist." />
            </Card>
        );
    }

    return (
        <Card title={WIDGET_LABELS.company_overview}>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <StatCard label="Departments" value={data.department_count} icon={<UsersIcon className="w-3.5 h-3.5" />} />
                <StatCard label="People" value={data.user_count} icon={<UsersIcon className="w-3.5 h-3.5" />} />
                <StatCard label="KPIs" value={data.kpi_count} icon={<TargetIcon className="w-3.5 h-3.5" />} />
                <StatCard
                    label="Avg. achievement"
                    value={data.avg_achievement_pct !== null ? `${data.avg_achievement_pct}%` : '—'}
                    tone={data.avg_achievement_pct !== null && data.avg_achievement_pct >= 100 ? 'success' : 'default'}
                    icon={<RocketIcon className="w-3.5 h-3.5" />}
                />
            </div>
        </Card>
    );
}

function PendingApprovalsWidget({ data, companyId }: { data: PendingApprovalsData | undefined; companyId: string }) {
    return (
        <Card
            title={WIDGET_LABELS.pending_approvals}
            actions={
                (data?.count ?? 0) > 0 ? (
                    <a href={`/platform/companies/${companyId}/approvals`} className="text-xs font-semibold text-brand-800 hover:underline">
                        View all
                    </a>
                ) : undefined
            }
        >
            {!data || data.count === 0 ? (
                <EmptyState icon={<ClipboardCheckIcon className="w-8 h-8" />} title="Nothing waiting on you" />
            ) : (
                <ul className="divide-y divide-slate-100">
                    {data.items.map((item) => (
                        <li key={item.id} className="py-2.5 flex items-center justify-between">
                            <span className="text-sm text-slate-700">
                                {item.approval_requests?.workflow_type.replace(/_/g, ' ') ?? 'Request'}
                            </span>
                            <Badge tone="warning">Pending</Badge>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

function RecentSubmissionsWidget({ data }: { data: RecentSubmission[] | undefined }) {
    return (
        <Card title={WIDGET_LABELS.recent_submissions}>
            {!data || data.length === 0 ? (
                <EmptyState title="No approved submissions yet" />
            ) : (
                <ul className="divide-y divide-slate-100">
                    {data.map((s) => (
                        <li key={s.id} className="py-2.5 flex items-center justify-between">
                            <div>
                                <p className="text-sm text-slate-700">{s.kpis?.name ?? 'KPI'}</p>
                                <p className="text-[11px] text-slate-400">{s.submission_date}</p>
                            </div>
                            <span className="text-sm font-semibold text-slate-800 tabular-nums">
                                {s.value}
                                {s.kpis?.unit ?? ''}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

function PeriodStatusWidget({ data }: { data: PeriodStatusData | null | undefined }) {
    if (!data) {
        return (
            <Card title={WIDGET_LABELS.period_status}>
                <EmptyState title="No period data yet" />
            </Card>
        );
    }

    return (
        <Card title={WIDGET_LABELS.period_status}>
            <div className="flex items-center justify-between mb-2">
                <p className="text-sm font-bold text-slate-800">
                    Q{data.quarter} FY{data.financial_year}
                </p>
                <Badge tone={PERIOD_STATUS_TONE[data.status] ?? 'neutral'}>{data.status.replace(/_/g, ' ')}</Badge>
            </div>
            <p className="text-xs text-slate-400">
                {data.start} – {data.end}
            </p>
        </Card>
    );
}

function DepartmentAchievementWidget({ data }: { data: DepartmentAchievementRow[] | undefined }) {
    if (!data || data.length === 0) {
        return (
            <Card title={WIDGET_LABELS.department_achievement}>
                <EmptyState title="No departments yet" />
            </Card>
        );
    }

    return (
        <Card title={WIDGET_LABELS.department_achievement}>
            <ul className="divide-y divide-slate-100">
                {data.map((row) => (
                    <li key={row.department_id} className="py-2.5 flex items-center justify-between">
                        <div>
                            <p className="text-sm text-slate-700">{row.department_name}</p>
                            <p className="text-[11px] text-slate-400">
                                {row.kpi_count} {row.kpi_count === 1 ? 'KPI' : 'KPIs'}
                            </p>
                        </div>
                        <span className={`text-sm font-semibold tabular-nums ${row.avg_achievement_pct !== null && row.avg_achievement_pct >= 100 ? 'text-emerald-600' : 'text-slate-800'}`}>
                            {row.avg_achievement_pct !== null ? `${row.avg_achievement_pct}%` : '—'}
                        </span>
                    </li>
                ))}
            </ul>
        </Card>
    );
}

function AchievementTrendWidget({ data }: { data: TrendPoint[] | undefined }) {
    const points = (data ?? []).filter((p) => p.avg_achievement_pct !== null);

    if (points.length === 0) {
        return (
            <Card title={WIDGET_LABELS.achievement_trend}>
                <EmptyState title="No approved submissions in recent quarters yet" />
            </Card>
        );
    }

    return (
        <Card title={WIDGET_LABELS.achievement_trend} description="Company-wide average achievement, most recent quarters">
            <div style={{ height: 160, position: 'relative' }}>
                <Bar
                    data={{
                        labels: points.map((p) => `Q${p.period_number} FY${p.financial_year}`),
                        datasets: [
                            {
                                label: 'Avg. achievement',
                                data: points.map((p) => p.avg_achievement_pct as number),
                                backgroundColor: '#6366f1bb',
                                borderColor: '#6366f1',
                                borderWidth: 1.5,
                                borderRadius: 4,
                            },
                        ],
                    }}
                    options={{
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (c) => ` ${(c.parsed as { y: number }).y.toFixed(1)}%` } },
                        },
                        scales: {
                            x: { ticks: { font: { size: 11, weight: 'bold' } }, grid: { display: false } },
                            y: { min: 0, ticks: { callback: (v) => v + '%', font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                        },
                    }}
                />
            </div>
        </Card>
    );
}

function WidgetRenderer({ type, widgetData, companyId }: { type: string; widgetData: WidgetData; companyId: string }) {
    switch (type) {
        case 'company_overview':
            return <CompanyOverviewWidget data={widgetData.company_overview} />;
        case 'pending_approvals':
            return <PendingApprovalsWidget data={widgetData.pending_approvals} companyId={companyId} />;
        case 'recent_submissions':
            return <RecentSubmissionsWidget data={widgetData.recent_submissions} />;
        case 'period_status':
            return <PeriodStatusWidget data={widgetData.period_status} />;
        case 'department_achievement':
            return <DepartmentAchievementWidget data={widgetData.department_achievement} />;
        case 'achievement_trend':
            return <AchievementTrendWidget data={widgetData.achievement_trend} />;
        default:
            return null;
    }
}

function EditLayoutPanel({
    companyId,
    layout,
    availableWidgets,
    onDone,
}: {
    companyId: string;
    layout: string[];
    availableWidgets: string[];
    onDone: () => void;
}) {
    const [widgets, setWidgets] = useState<string[]>(layout);
    const [saving, setSaving] = useState(false);

    const move = (index: number, direction: -1 | 1) => {
        const next = [...widgets];
        const target = index + direction;
        if (target < 0 || target >= next.length) return;
        [next[index], next[target]] = [next[target], next[index]];
        setWidgets(next);
    };

    const remove = (index: number) => setWidgets(widgets.filter((_, i) => i !== index));
    const add = (type: string) => setWidgets([...widgets, type]);

    const save = () => {
        setSaving(true);
        router.post(
            `/platform/companies/${companyId}/dashboard-layout`,
            { widgets },
            { onFinish: () => setSaving(false), onSuccess: onDone },
        );
    };

    const addable = availableWidgets.filter((w) => !widgets.includes(w));

    return (
        <Card title="Edit dashboard" description="Choose which widgets show on this company's dashboard, and in what order.">
            <ul className="divide-y divide-slate-100 mb-4">
                {widgets.map((type, i) => (
                    <li key={type} className="py-2.5 flex items-center justify-between">
                        <span className="text-sm text-slate-700">{WIDGET_LABELS[type] ?? type}</span>
                        <div className="flex items-center gap-3">
                            <button onClick={() => move(i, -1)} disabled={i === 0} className="text-xs text-slate-400 hover:text-slate-700 disabled:opacity-30">
                                Up
                            </button>
                            <button
                                onClick={() => move(i, 1)}
                                disabled={i === widgets.length - 1}
                                className="text-xs text-slate-400 hover:text-slate-700 disabled:opacity-30"
                            >
                                Down
                            </button>
                            <button onClick={() => remove(i)} className="text-xs font-semibold text-red-600 hover:underline">
                                Remove
                            </button>
                        </div>
                    </li>
                ))}
                {widgets.length === 0 && <li className="py-2.5 text-xs text-slate-400">No widgets — add one below.</li>}
            </ul>

            {addable.length > 0 && (
                <div className="flex items-center gap-2 mb-4">
                    {addable.map((type) => (
                        <button
                            key={type}
                            onClick={() => add(type)}
                            className="text-xs font-semibold text-brand-800 hover:underline"
                        >
                            + {WIDGET_LABELS[type] ?? type}
                        </button>
                    ))}
                </div>
            )}

            <div className="flex items-center gap-3">
                <PrimaryButton onClick={save} disabled={saving}>
                    Save layout
                </PrimaryButton>
                <SecondaryButton onClick={onDone}>Cancel</SecondaryButton>
            </div>
        </Card>
    );
}

export default function CompanyDashboard({ company, layout, widgetData, availableWidgets, canEditLayout }: CompanyDashboardPageProps) {
    const [editing, setEditing] = useState(false);

    return (
        <PlatformLayout
            title={company.name}
            description={
                <span className="inline-flex items-center gap-1.5">
                    {'Your dashboard'}
                    <InfoTooltip text="A Company Admin can customize which widgets appear here and in what order — everyone in this company sees the same arrangement." />
                </span>
            }
            company={company}
            actions={
                canEditLayout && !editing ? (
                    <SecondaryButton onClick={() => setEditing(true)}>Edit dashboard</SecondaryButton>
                ) : undefined
            }
        >
            {editing ? (
                <EditLayoutPanel companyId={company.id} layout={layout} availableWidgets={availableWidgets} onDone={() => setEditing(false)} />
            ) : layout.length === 0 ? (
                <Card>
                    <EmptyState
                        title="No widgets configured"
                        description={canEditLayout ? 'Click "Edit dashboard" above to add some.' : 'Ask your Company Admin to set up this dashboard.'}
                    />
                </Card>
            ) : (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    {layout.map((type) => (
                        <WidgetRenderer key={type} type={type} widgetData={widgetData} companyId={company.id} />
                    ))}
                </div>
            )}
        </PlatformLayout>
    );
}
