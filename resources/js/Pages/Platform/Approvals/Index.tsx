import { router } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, PrimaryButton, SecondaryButton, StatCard } from '@/Components/Platform/ui';
import { ChecklistIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface PendingItem {
    id: string;
    type: 'target_change' | 'delete_request' | 'weightage_change' | 'quarter_update' | 'completion';
    kpi_id?: string;
    kpis?: { name: string } | null;
    users?: { name: string; email: string } | null;
    old_target?: string | number | null;
    new_target?: string | number | null;
    old_weight?: string | number | null;
    new_weight?: string | number | null;
    old_actual?: string | number | null;
    requested_actual?: string | number | null;
    reason?: string | null;
    quarter?: string;
    financial_year?: string;
    target?: string | number | null;
    actual?: string | number | null;
    completion_note?: string | null;
    kpi_quarters?: { quarter: string } | null;
    created_at?: string;
    completion_submitted_at?: string;
}

interface Counts {
    target_change: number;
    delete_request: number;
    weightage_change: number;
    quarter_update: number;
    completion: number;
}

interface ApprovalsPageProps {
    company: Company;
    pending: PendingItem[];
    counts: Counts;
    [key: string]: unknown;
}

const TYPE_LABEL: Record<PendingItem['type'], string> = {
    target_change: 'Target Change',
    delete_request: 'Delete Request',
    weightage_change: 'Weightage Change',
    quarter_update: 'Quarter Actual Change',
    completion: 'Quarter Completion',
};

const TYPE_TONE: Record<PendingItem['type'], 'warning' | 'danger' | 'neutral'> = {
    target_change: 'warning',
    delete_request: 'danger',
    weightage_change: 'warning',
    quarter_update: 'warning',
    completion: 'neutral',
};

function num(v: string | number | null | undefined): number {
    if (v === null || v === undefined) return 0;
    const n = typeof v === 'number' ? v : parseFloat(v);
    return Number.isFinite(n) ? n : 0;
}

function decisionRoute(company: string, item: PendingItem, verb: 'approve' | 'reject'): string {
    const base = `/platform/companies/${company}`;
    switch (item.type) {
        case 'target_change':
            return `${base}/target-change-requests/${item.id}/${verb}`;
        case 'delete_request':
            return `${base}/delete-requests/${item.id}/${verb}`;
        case 'weightage_change':
            return `${base}/weight-change-requests/${item.id}/${verb}`;
        case 'quarter_update':
            return `${base}/quarterly/change-requests/${item.id}/${verb}`;
        case 'completion':
            return `${base}/quarterly/${item.id}/${verb}-completion`;
    }
}

function ItemDetail({ item }: { item: PendingItem }) {
    switch (item.type) {
        case 'target_change':
            return (
                <p className="text-xs text-slate-500 mt-0.5">
                    {item.users?.name ?? 'A member'} · {num(item.old_target)} → {num(item.new_target)}
                </p>
            );
        case 'delete_request':
            return <p className="text-xs text-slate-500 mt-0.5">{item.users?.name ?? 'A member'} wants this KPI removed entirely.</p>;
        case 'weightage_change':
            return (
                <p className="text-xs text-slate-500 mt-0.5">
                    {item.users?.name ?? 'A member'} · {num(item.old_weight).toFixed(2)}% → {num(item.new_weight).toFixed(2)}%
                </p>
            );
        case 'quarter_update':
            return (
                <p className="text-xs text-slate-500 mt-0.5">
                    {item.users?.name ?? 'A member'} · {item.kpi_quarters?.quarter} · {num(item.old_actual)} → {num(item.requested_actual)}
                </p>
            );
        case 'completion':
            return (
                <p className="text-xs text-slate-500 mt-0.5">
                    {item.users?.name ?? 'A member'} · {item.quarter} {item.financial_year} · target {num(item.target)}, actual {num(item.actual)}
                </p>
            );
    }
}

export default function ApprovalsIndex({ company, pending, counts }: ApprovalsPageProps) {
    function decide(item: PendingItem, verb: 'approve' | 'reject') {
        const label = TYPE_LABEL[item.type];
        if (!confirm(`${verb === 'approve' ? 'Approve' : 'Reject'} this ${label.toLowerCase()} request?`)) return;
        router.post(decisionRoute(company.id, item, verb), {}, { preserveScroll: true });
    }

    return (
        <PlatformLayout
            title="Approval Center"
            description="Every pending KPI request across the company — target changes, deletions, weightage, and quarter sign-offs — in one inbox."
            company={company}
            maxWidth="max-w-4xl"
        >
            <div className="space-y-6">
                <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <StatCard label="Target Change" value={counts.target_change} />
                    <StatCard label="Delete Request" value={counts.delete_request} tone={counts.delete_request > 0 ? 'danger' : undefined} />
                    <StatCard label="Weightage" value={counts.weightage_change} />
                    <StatCard label="Quarter Update" value={counts.quarter_update} />
                    <StatCard label="Completion" value={counts.completion} />
                </div>

                <Card title="Pending" description={`${pending.length} request${pending.length !== 1 ? 's' : ''} waiting for a decision`}>
                    {pending.length === 0 ? (
                        <EmptyState icon={<ChecklistIcon className="w-8 h-8" />} title="Nothing waiting for review" description="You're all caught up." />
                    ) : (
                        <div className="space-y-3">
                            {pending.map((item) => (
                                <div key={`${item.type}-${item.id}`} className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2 mb-1">
                                                <Badge tone={TYPE_TONE[item.type]}>{TYPE_LABEL[item.type]}</Badge>
                                                <p className="text-sm font-bold text-slate-900">{item.kpis?.name ?? 'A KPI'}</p>
                                            </div>
                                            <ItemDetail item={item} />
                                            {(item.reason || item.completion_note) && (
                                                <p className="text-xs text-slate-600 mt-1.5">{item.reason ?? item.completion_note}</p>
                                            )}
                                        </div>
                                        <div className="flex-none flex items-center gap-2">
                                            <SecondaryButton onClick={() => decide(item, 'reject')}>Reject</SecondaryButton>
                                            <PrimaryButton onClick={() => decide(item, 'approve')}>Approve</PrimaryButton>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>
        </PlatformLayout>
    );
}
