import { router } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, FilterBar } from '@/Components/Platform/ui';
import { LifebuoyIcon } from '@/Components/Platform/Icons';

type TicketStatus = 'open' | 'in_progress' | 'resolved' | 'closed';

interface Ticket {
    id: string;
    subject: string;
    description: string | null;
    status: TicketStatus;
    created_at: string;
    companies: { name: string; code: string } | null;
}

interface SupportPageProps {
    tickets: Ticket[];
    filters: { status: string };
    [key: string]: unknown;
}

const STATUS_TONE: Record<TicketStatus, 'neutral' | 'info' | 'success' | 'danger'> = {
    open: 'neutral',
    in_progress: 'info',
    resolved: 'success',
    closed: 'danger',
};

export default function SupportIndex({ tickets, filters }: SupportPageProps) {
    function updateFilter(_key: string, value: string) {
        const url = new URL(window.location.href);
        if (value) url.searchParams.set('status', value);
        else url.searchParams.delete('status');
        router.get(url.pathname + url.search, {}, { preserveScroll: true, preserveState: true });
    }

    function setStatus(id: string, status: TicketStatus) {
        router.patch(`/platform/hq/support/${id}`, { status }, { preserveScroll: true });
    }

    return (
        <PlatformLayout title="Support" description="Every open support ticket, across every company.">
            <div className="mb-4">
                <FilterBar
                    filters={[
                        {
                            key: 'status',
                            label: 'Status',
                            value: filters.status,
                            options: [
                                { value: 'open', label: 'Open' },
                                { value: 'in_progress', label: 'In Progress' },
                                { value: 'resolved', label: 'Resolved' },
                                { value: 'closed', label: 'Closed' },
                            ],
                        },
                    ]}
                    onChange={updateFilter}
                />
            </div>

            <Card>
                {tickets.length === 0 ? (
                    <EmptyState icon={<LifebuoyIcon className="w-10 h-10" />} title="No support tickets" />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {tickets.map((t) => (
                            <li key={t.id} className="py-3.5 flex items-start justify-between gap-4">
                                <div>
                                    <p className="text-sm font-semibold text-slate-800">{t.subject}</p>
                                    {t.description && <p className="text-xs text-slate-500 mt-0.5">{t.description}</p>}
                                    <p className="text-[11px] text-slate-400 mt-1">
                                        {t.companies?.name} · {new Date(t.created_at).toLocaleDateString()}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2 flex-none">
                                    <Badge tone={STATUS_TONE[t.status]}>{t.status.replace('_', ' ')}</Badge>
                                    <select
                                        value={t.status}
                                        onChange={(e) => setStatus(t.id, e.target.value as TicketStatus)}
                                        className="text-xs rounded-lg border border-slate-300 px-2 py-1"
                                    >
                                        <option value="open">Open</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="resolved">Resolved</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
