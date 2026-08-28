import { useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, InfoTooltip, PrimaryButton, SecondaryButton } from '@/Components/Platform/ui';

interface Company {
    id: string;
    name: string;
}

type PeriodStatus = 'upcoming' | 'open' | 'submission_due' | 'under_review' | 'closed' | 'locked';

interface Quarter {
    period_type: 'quarter';
    period_number: number;
    start: string;
    end: string;
    status: PeriodStatus;
    reason: string | null;
    set_at: string | null;
}

interface PeriodsPageProps {
    company: Company;
    financialYear: number;
    quarters: Quarter[];
    canManage: boolean;
    [key: string]: unknown;
}

const STATUS_TONE: Record<PeriodStatus, 'neutral' | 'danger' | 'warning' | 'success' | 'info'> = {
    upcoming: 'neutral',
    open: 'success',
    submission_due: 'warning',
    under_review: 'info',
    closed: 'danger',
    locked: 'danger',
};

const NEXT_ACTIONS: Record<PeriodStatus, Array<{ label: string; status: PeriodStatus; needsReason: boolean }>> = {
    upcoming: [],
    open: [
        { label: 'Mark under review', status: 'under_review', needsReason: true },
        { label: 'Close', status: 'closed', needsReason: true },
    ],
    submission_due: [
        { label: 'Mark under review', status: 'under_review', needsReason: true },
        { label: 'Close', status: 'closed', needsReason: true },
    ],
    under_review: [
        { label: 'Reopen', status: 'open', needsReason: true },
        { label: 'Close', status: 'closed', needsReason: true },
    ],
    closed: [
        { label: 'Reopen', status: 'open', needsReason: true },
        { label: 'Lock', status: 'locked', needsReason: true },
    ],
    locked: [{ label: 'Reopen', status: 'open', needsReason: true }],
};

function QuarterCard({ companyId, financialYear, quarter, canManage }: { companyId: string; financialYear: number; quarter: Quarter; canManage: boolean }) {
    const [actioning, setActioning] = useState<PeriodStatus | null>(null);
    const { data, setData, post, processing, reset } = useForm({
        financial_year: financialYear,
        period_type: 'quarter',
        period_number: quarter.period_number,
        status: '',
        reason: '',
    });

    const startAction = (status: PeriodStatus) => {
        setActioning(status);
        setData('status', status);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/periods`, {
            onSuccess: () => {
                setActioning(null);
                reset();
            },
        });
    };

    return (
        <div className="rounded-xl border border-slate-200 p-4">
            <div className="flex items-center justify-between mb-2">
                <p className="text-sm font-bold text-slate-800">
                    Q{quarter.period_number} FY{financialYear}
                </p>
                <Badge tone={STATUS_TONE[quarter.status]}>{quarter.status.replace(/_/g, ' ')}</Badge>
            </div>
            <p className="text-xs text-slate-400 mb-3">
                {quarter.start} – {quarter.end}
            </p>
            {quarter.reason && <p className="text-xs text-slate-500 italic mb-3">"{quarter.reason}"</p>}

            {canManage && (
                <>
                    {actioning === null ? (
                        <div className="flex flex-wrap gap-2">
                            {NEXT_ACTIONS[quarter.status].map((action) => (
                                <SecondaryButton key={action.status} type="button" onClick={() => startAction(action.status)} className="text-xs px-3 py-1.5">
                                    {action.label}
                                </SecondaryButton>
                            ))}
                        </div>
                    ) : (
                        <form onSubmit={submit}>
                            <textarea
                                value={data.reason}
                                onChange={(e) => setData('reason', e.target.value)}
                                placeholder="Reason (required — this is audited)"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs mb-2"
                                rows={2}
                                required
                            />
                            <div className="flex items-center gap-2">
                                <PrimaryButton type="submit" disabled={processing} className="text-xs px-3 py-1.5">
                                    Confirm
                                </PrimaryButton>
                                <SecondaryButton type="button" onClick={() => setActioning(null)} className="text-xs px-3 py-1.5">
                                    Cancel
                                </SecondaryButton>
                            </div>
                        </form>
                    )}
                </>
            )}
        </div>
    );
}

export default function PeriodsIndex({ company, financialYear, quarters, canManage }: PeriodsPageProps) {
    return (
        <PlatformLayout
            title="Performance Periods"
            description={
                <span className="inline-flex items-center gap-1.5">
                    {`FY${financialYear}'s quarters and their submission lifecycle`}
                    <InfoTooltip text="Open periods accept KPI actual submissions. Closing or locking a period stops new submissions from being accepted until it's reopened — every change here is audited." />
                </span>
            }
            company={company}
        >
            <Card>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {quarters.map((q) => (
                        <QuarterCard key={q.period_number} companyId={company.id} financialYear={financialYear} quarter={q} canManage={canManage} />
                    ))}
                </div>
            </Card>
        </PlatformLayout>
    );
}
