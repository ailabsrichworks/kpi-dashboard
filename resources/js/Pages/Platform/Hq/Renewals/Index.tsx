import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState } from '@/Components/Platform/ui';
import { CalendarIcon } from '@/Components/Platform/Icons';

interface CompanyRow {
    id: string;
    name: string;
    code: string;
    status: string;
    contract_end_date: string;
    subscription_status: string | null;
    days_until_renewal: number;
}

interface RenewalsPageProps {
    companies: CompanyRow[];
    [key: string]: unknown;
}

function urgencyTone(days: number): 'danger' | 'warning' | 'neutral' {
    if (days < 0) return 'danger';
    if (days <= 60) return 'warning';
    return 'neutral';
}

export default function RenewalsIndex({ companies }: RenewalsPageProps) {
    return (
        <PlatformLayout title="Renewals" description="Every company's contract end date, soonest first.">
            <Card>
                {companies.length === 0 ? (
                    <EmptyState icon={<CalendarIcon className="w-10 h-10" />} title="No contract end dates on file yet" />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {companies.map((c) => (
                            <li key={c.id} className="flex items-center justify-between gap-4 py-3.5">
                                <div>
                                    <p className="text-sm font-semibold text-slate-800">{c.name}</p>
                                    <p className="text-xs text-slate-400">{c.code} · ends {c.contract_end_date}</p>
                                </div>
                                <div className="flex items-center gap-3">
                                    {c.subscription_status && <Badge tone="neutral">{c.subscription_status}</Badge>}
                                    <Badge tone={urgencyTone(c.days_until_renewal)}>
                                        {c.days_until_renewal < 0 ? `${Math.abs(c.days_until_renewal)} days overdue` : `${c.days_until_renewal} days`}
                                    </Badge>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
