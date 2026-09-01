import { Link } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Card, EmptyState, PerformanceStatusBadge } from '@/Components/Platform/ui';
import { HeartIcon } from '@/Components/Platform/Icons';

interface HealthScore {
    overall_score: number;
    status: 'healthy' | 'at_risk' | 'critical';
    period_date: string;
}

interface CompanyRow {
    id: string;
    name: string;
    code: string;
    status: string;
    health: HealthScore | null;
}

interface ClientHealthPageProps {
    companies: CompanyRow[];
    [key: string]: unknown;
}

const STATUS_TO_PERFORMANCE = { healthy: 'on_track', at_risk: 'at_risk', critical: 'critical' } as const;

export default function ClientHealthIndex({ companies }: ClientHealthPageProps) {
    return (
        <PlatformLayout title="Client Health" description="Every subscribed company's health score, at a glance.">
            <Card>
                {companies.length === 0 ? (
                    <EmptyState icon={<HeartIcon className="w-10 h-10" />} title="No companies yet" />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {companies.map((c) => (
                            <li key={c.id} className="flex items-center justify-between gap-4 py-3.5">
                                <div>
                                    <Link href={`/platform/hq/client-health/${c.id}`} className="text-sm font-semibold text-brand-900 hover:underline">
                                        {c.name}
                                    </Link>
                                    <p className="text-xs text-slate-400">{c.code}</p>
                                </div>
                                <div className="flex items-center gap-3">
                                    {c.health ? (
                                        <>
                                            <span className="text-lg font-bold tabular-nums text-slate-700">{c.health.overall_score.toFixed(0)}</span>
                                            <PerformanceStatusBadge status={STATUS_TO_PERFORMANCE[c.health.status]} />
                                        </>
                                    ) : (
                                        <span className="text-xs text-slate-400">No score recorded yet</span>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
