import { FormEventHandler } from 'react';
import { useForm } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import PerformanceTrend from '@/Components/Platform/PerformanceTrend';
import { Card, PerformanceStatusBadge, PrimaryButton } from '@/Components/Platform/ui';
import { PerformanceStatus } from '@/lib/performanceStatus';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface HealthScore {
    period_date: string;
    adoption_score: number;
    activity_score: number;
    kpi_completion_score: number;
    cam_engagement_score: number;
    support_score: number;
    subscription_score: number;
    overall_score: number;
    status: 'healthy' | 'at_risk' | 'critical';
    notes: string | null;
}

interface ShowPageProps {
    company: Company;
    history: HealthScore[];
    [key: string]: unknown;
}

const SUB_SCORES: Array<{ key: keyof HealthScore; label: string }> = [
    { key: 'adoption_score', label: 'Platform Adoption' },
    { key: 'activity_score', label: 'User Activity' },
    { key: 'kpi_completion_score', label: 'KPI Completion' },
    { key: 'cam_engagement_score', label: 'CAM Engagement' },
    { key: 'support_score', label: 'Support Health' },
    { key: 'subscription_score', label: 'Subscription' },
];

const STATUS_TO_PERFORMANCE: Record<HealthScore['status'], PerformanceStatus> = { healthy: 'on_track', at_risk: 'at_risk', critical: 'critical' };

function RecordScoreForm({ companyId }: { companyId: string }) {
    const { data, setData, post, processing, reset } = useForm({
        period_date: new Date().toISOString().slice(0, 10),
        adoption_score: '',
        activity_score: '',
        kpi_completion_score: '',
        cam_engagement_score: '',
        support_score: '',
        subscription_score: '',
        notes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/hq/client-health/${companyId}`, { onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Period date</label>
                <input type="date" value={data.period_date} onChange={(e) => setData('period_date', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
            </div>
            {(['adoption_score', 'activity_score', 'kpi_completion_score', 'cam_engagement_score', 'support_score', 'subscription_score'] as const).map((key) => (
                <div key={key}>
                    <label className="block text-xs font-medium text-slate-600 mb-1">{SUB_SCORES.find((s) => s.key === key)?.label}</label>
                    <input
                        type="number"
                        min={0}
                        max={100}
                        value={data[key]}
                        onChange={(e) => setData(key, e.target.value)}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        placeholder="0-100"
                        required
                    />
                </div>
            ))}
            <div className="col-span-2 sm:col-span-3">
                <label className="block text-xs font-medium text-slate-600 mb-1">Notes</label>
                <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" rows={2} />
            </div>
            <div className="col-span-2 sm:col-span-3">
                <PrimaryButton type="submit" disabled={processing}>
                    Record score
                </PrimaryButton>
            </div>
        </form>
    );
}

export default function ClientHealthShow({ company, history }: ShowPageProps) {
    const latest = history[history.length - 1] ?? null;

    return (
        <PlatformLayout title={company.name} description="Client health breakdown and trend." maxWidth="max-w-4xl">
            {latest && (
                <Card className="mb-5">
                    <div className="flex items-center justify-between mb-4">
                        <div>
                            <p className="text-3xl font-bold tabular-nums text-slate-800">{latest.overall_score.toFixed(0)}</p>
                            <PerformanceStatusBadge status={STATUS_TO_PERFORMANCE[latest.status]} />
                        </div>
                        <p className="text-xs text-slate-400">as of {latest.period_date}</p>
                    </div>
                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        {SUB_SCORES.map((s) => (
                            <div key={s.key}>
                                <p className="text-lg font-bold tabular-nums text-slate-700">{(latest[s.key] as number).toFixed(0)}%</p>
                                <p className="text-[11px] text-slate-400">{s.label}</p>
                            </div>
                        ))}
                    </div>
                </Card>
            )}

            <div className="mb-5">
                <PerformanceTrend title="Health Score Trend" points={history.map((h) => ({ label: h.period_date, value: h.overall_score }))} />
            </div>

            <Card title="Record a new period score">
                <RecordScoreForm companyId={company.id} />
            </Card>
        </PlatformLayout>
    );
}
