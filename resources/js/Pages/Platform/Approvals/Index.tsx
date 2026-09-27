import { useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, PrimaryButton, SecondaryButton } from '@/Components/Platform/ui';
import { ClipboardCheckIcon } from '@/Components/Platform/Icons';
import { formatLinkageValue, LinkageUnit } from '@/lib/linkageFormat';

interface ApprovalRequest {
    id: string;
    company_id: string;
    workflow_type: string;
    object_type: 'kpi_submission' | 'kpi_target_revision';
    object_id: string;
    submitted_by: string;
    submitted_at: string;
    status: string;
}

interface SubmissionObject {
    id: string;
    value: number;
    previous_approved_value: number | null;
    submission_date: string;
    notes: string | null;
    evidence_note: string | null;
    revision_number: number;
    kpis: { name: string; target: number | null; unit: string | null; measurement_unit: LinkageUnit };
    users: { name: string };
}

interface TargetRevisionObject {
    id: string;
    old_target: number | null;
    new_target: number;
    reason: string;
    effective_financial_year: number;
    kpis: { name: string; unit: string | null; measurement_unit: LinkageUnit };
}

interface ApprovalStep {
    id: string;
    request_id: string;
    step_order: number;
    status: string;
    comments: string | null;
    request: ApprovalRequest | null;
    object: SubmissionObject | TargetRevisionObject | null;
}

interface Company {
    id: string;
    name: string;
}

interface ApprovalsPageProps {
    company: Company;
    pending: ApprovalStep[];
    overdue: ApprovalStep[];
    approved: ApprovalStep[];
    rejected: ApprovalStep[];
    returned: ApprovalStep[];
    [key: string]: unknown;
}

type Tab = 'pending' | 'overdue' | 'approved' | 'rejected' | 'returned';

function isSubmission(object: SubmissionObject | TargetRevisionObject): object is SubmissionObject {
    return 'value' in object;
}

function ApprovalSummary({ step }: { step: ApprovalStep }) {
    if (!step.object || !step.request) {
        return <p className="text-sm text-slate-400 italic">This request's underlying record is no longer available.</p>;
    }

    if (isSubmission(step.object)) {
        const o = step.object;
        const unit = o.kpis.measurement_unit;
        return (
            <div>
                <p className="text-sm font-semibold text-slate-800">{o.kpis.name}</p>
                <div className="flex flex-wrap items-baseline gap-x-4 gap-y-0.5 mt-1">
                    {o.previous_approved_value !== null && (
                        <span className="text-xs text-slate-400">
                            Previous: <span className="font-semibold text-slate-600">{formatLinkageValue(o.previous_approved_value, unit)}</span>
                        </span>
                    )}
                    <span className="text-xs text-slate-400">
                        Requested: <span className="font-semibold text-[#8B5E4A]">{formatLinkageValue(o.value, unit)}</span>
                    </span>
                    <span className="text-xs text-slate-400">
                        Target: <span className="font-semibold text-emerald-700">{o.kpis.target !== null ? formatLinkageValue(o.kpis.target, unit) : '—'}</span>
                    </span>
                    <span className="text-[11px] text-slate-400">revision {o.revision_number}</span>
                </div>
                <p className="text-xs text-slate-400 mt-1">
                    {o.submission_date} · submitted by {o.users.name}
                    {o.notes ? ` · ${o.notes}` : ''}
                </p>
                {o.evidence_note && <p className="text-xs text-slate-400 mt-0.5">Evidence: {o.evidence_note}</p>}
            </div>
        );
    }

    const o = step.object;
    const unit = o.kpis.measurement_unit;
    return (
        <div>
            <p className="text-sm font-semibold text-slate-800">
                {o.kpis.name} — target revision: {o.old_target !== null ? formatLinkageValue(o.old_target, unit) : '—'} →{' '}
                {formatLinkageValue(o.new_target, unit)}
                <span className="text-xs font-normal text-slate-400 ml-2">effective FY{o.effective_financial_year}</span>
            </p>
            <p className="text-xs text-slate-400 mt-0.5">Reason: {o.reason}</p>
        </div>
    );
}

function DecideForm({ companyId, step }: { companyId: string; step: ApprovalStep }) {
    const [action, setAction] = useState<'approved' | 'rejected' | 'returned' | null>(null);
    const { data, setData, post, processing, reset } = useForm({ decision: '', comments: '' });

    if (!step.request) {
        return null;
    }

    const startAction = (decision: 'approved' | 'rejected' | 'returned') => {
        setAction(decision);
        setData('decision', decision);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/approvals/${step.request_id}/decide`, {
            onSuccess: () => {
                setAction(null);
                reset();
            },
        });
    };

    if (action === null) {
        return (
            <div className="flex items-center gap-2">
                <PrimaryButton type="button" onClick={() => startAction('approved')}>
                    Approve
                </PrimaryButton>
                <SecondaryButton type="button" onClick={() => startAction('returned')}>
                    Return for Revision
                </SecondaryButton>
                <SecondaryButton type="button" onClick={() => startAction('rejected')} className="text-red-600 border-red-200 hover:bg-red-50">
                    Reject
                </SecondaryButton>
            </div>
        );
    }

    const needsComment = action === 'rejected' || action === 'returned';

    return (
        <form onSubmit={submit} className="w-full max-w-md">
            <p className="text-xs font-semibold text-slate-600 mb-1">
                {action === 'approved' ? 'Approve this request?' : action === 'rejected' ? 'Reject this request' : 'Return this for revision'}
            </p>
            {needsComment && (
                <textarea
                    value={data.comments}
                    onChange={(e) => setData('comments', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm mb-2"
                    placeholder="Explain why (required)"
                    required
                    rows={2}
                />
            )}
            <div className="flex items-center gap-2">
                <PrimaryButton type="submit" disabled={processing}>
                    Confirm
                </PrimaryButton>
                <SecondaryButton type="button" onClick={() => setAction(null)}>
                    Cancel
                </SecondaryButton>
            </div>
        </form>
    );
}

const TAB_LABELS: Record<Tab, string> = {
    pending: 'Pending',
    overdue: 'Overdue',
    approved: 'Approved',
    rejected: 'Rejected',
    returned: 'Returned',
};

export default function ApprovalsIndex({ company, pending, overdue, approved, rejected, returned }: ApprovalsPageProps) {
    const [tab, setTab] = useState<Tab>(pending.length > 0 ? 'pending' : 'overdue');

    const byTab: Record<Tab, ApprovalStep[]> = { pending, overdue, approved, rejected, returned };
    const current = byTab[tab];

    return (
        <PlatformLayout title="My Approvals" description="Requests waiting on your decision, and what you've decided before." company={company}>
            <Card>
                <div className="flex items-center gap-1 mb-5 border-b border-slate-100 pb-3">
                    {(Object.keys(TAB_LABELS) as Tab[]).map((key) => (
                        <button
                            key={key}
                            onClick={() => setTab(key)}
                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors ${
                                tab === key ? 'bg-brand-900 text-white' : 'text-slate-500 hover:bg-slate-100'
                            }`}
                        >
                            {TAB_LABELS[key]} <span className="tabular-nums">({byTab[key].length})</span>
                        </button>
                    ))}
                </div>

                {current.length === 0 ? (
                    <EmptyState icon={<ClipboardCheckIcon className="w-10 h-10" />} title={`No ${TAB_LABELS[tab].toLowerCase()} approvals`} />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {current.map((step) => (
                            <li key={step.id} className="py-4 flex items-start justify-between gap-4 flex-wrap">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2 mb-1">
                                        <Badge tone="neutral">{step.request?.workflow_type.replace(/_/g, ' ')}</Badge>
                                        {step.request && (
                                            <span className="text-[11px] text-slate-400">Submitted {new Date(step.request.submitted_at).toLocaleDateString()}</span>
                                        )}
                                    </div>
                                    <ApprovalSummary step={step} />
                                    {step.comments && <p className="text-xs text-slate-500 mt-1 italic">"{step.comments}"</p>}
                                </div>
                                <div className="flex-none">{tab === 'pending' || tab === 'overdue' ? <DecideForm companyId={company.id} step={step} /> : <Badge tone={step.status === 'approved' ? 'success' : 'danger'}>{step.status}</Badge>}</div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
