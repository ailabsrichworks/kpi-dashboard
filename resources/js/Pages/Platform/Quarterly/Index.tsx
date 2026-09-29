import { router } from '@inertiajs/react';
import { useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, PrimaryButton, SecondaryButton, StatCard } from '@/Components/Platform/ui';
import { ChecklistIcon, TargetIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface ChangeRequest {
    id: string;
    old_actual: string | number | null;
    requested_actual: string | number;
    reason: string;
    created_at: string;
}

interface Quarter {
    id: string;
    quarter: 'Q1' | 'Q2' | 'Q3' | 'Q4';
    target: string | number;
    actual: string | number | null;
    status: 'not_started' | 'on_track' | 'at_risk' | 'pending_completion' | 'completed';
    completion_note: string | null;
    completion_submitted_at: string | null;
    pending_change_request?: ChangeRequest | null;
}

interface Kpi {
    id: string;
    name: string;
    description: string | null;
    kpi_categories: { name: string } | null;
    quarters: Quarter[];
}

interface CompletionQueueItem {
    id: string;
    quarter: string;
    target: string | number;
    actual: string | number | null;
    completion_note: string | null;
    completion_submitted_at: string;
    kpis: { name: string } | null;
    users: { name: string; email: string } | null;
}

interface ChangeRequestQueueItem {
    id: string;
    old_actual: string | number | null;
    requested_actual: string | number;
    reason: string;
    created_at: string;
    kpis: { name: string } | null;
    kpi_quarters: { quarter: string } | null;
    users: { name: string; email: string } | null;
}

interface QuarterlyPageProps {
    company: Company;
    financialYear: string;
    kpis: Kpi[];
    isAdmin: boolean;
    completionQueue: CompletionQueueItem[];
    changeRequestQueue: ChangeRequestQueueItem[];
    [key: string]: unknown;
}

function num(v: string | number | null | undefined): number {
    if (v === null || v === undefined) return 0;
    const n = typeof v === 'number' ? v : parseFloat(v);
    return Number.isFinite(n) ? n : 0;
}

const STATUS_LABEL: Record<Quarter['status'], string> = {
    not_started: 'Not started',
    on_track: 'On track',
    at_risk: 'At risk',
    pending_completion: 'Awaiting sign-off',
    completed: 'Signed off',
};

const STATUS_TONE: Record<Quarter['status'], 'neutral' | 'success' | 'warning' | 'info'> = {
    not_started: 'neutral',
    on_track: 'success',
    at_risk: 'warning',
    pending_completion: 'info',
    completed: 'success',
};

function QuarterTile({ companyId, quarter }: { companyId: string; quarter: Quarter }) {
    const [actual, setActual] = useState(quarter.actual !== null ? String(quarter.actual) : '');
    const [note, setNote] = useState('');
    const [showSubmit, setShowSubmit] = useState(false);
    const [showChangeRequest, setShowChangeRequest] = useState(false);
    const [requestedActual, setRequestedActual] = useState(quarter.actual !== null ? String(quarter.actual) : '');
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);

    const locked = quarter.status === 'pending_completion' || quarter.status === 'completed';
    const achievement = num(quarter.target) > 0 && quarter.actual !== null ? (num(quarter.actual) / num(quarter.target)) * 100 : null;

    function saveActual() {
        setBusy(true);
        router.post(
            `/platform/companies/${companyId}/quarterly/${quarter.id}/actual`,
            { actual: parseFloat(actual) || 0 },
            { preserveScroll: true, onFinish: () => setBusy(false) }
        );
    }

    function submitCompletion() {
        setBusy(true);
        router.post(
            `/platform/companies/${companyId}/quarterly/${quarter.id}/submit-completion`,
            { completion_note: note },
            { preserveScroll: true, onFinish: () => setBusy(false) }
        );
    }

    function requestChange() {
        if (reason.trim().length < 20) {
            alert('Please enter a reason with at least 20 characters.');
            return;
        }
        setBusy(true);
        router.post(
            `/platform/companies/${companyId}/quarterly/${quarter.id}/request-change`,
            { requested_actual: parseFloat(requestedActual) || 0, reason },
            { preserveScroll: true, onFinish: () => setBusy(false) }
        );
    }

    return (
        <div className="rounded-xl border border-slate-200 p-3">
            <div className="flex items-center justify-between mb-2">
                <span className="text-xs font-black text-slate-700">{quarter.quarter}</span>
                <Badge tone={STATUS_TONE[quarter.status]}>{STATUS_LABEL[quarter.status]}</Badge>
            </div>

            <p className="text-[11px] text-slate-400 mb-2">Target: {num(quarter.target).toLocaleString()}</p>

            {!locked ? (
                <>
                    <input
                        type="number"
                        min={0}
                        step={0.01}
                        value={actual}
                        onChange={(e) => setActual(e.target.value)}
                        placeholder="Actual"
                        className="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm font-bold text-slate-900"
                    />
                    <div className="flex items-center gap-2 mt-2">
                        <SecondaryButton className="!px-2.5 !py-1 !text-[11px]" onClick={saveActual} disabled={busy}>
                            Save
                        </SecondaryButton>
                        {quarter.actual !== null && (
                            <button
                                type="button"
                                onClick={() => setShowSubmit((v) => !v)}
                                className="text-[11px] font-semibold text-brand-800 hover:underline"
                            >
                                Submit for sign-off
                            </button>
                        )}
                    </div>
                    {showSubmit && (
                        <div className="mt-2 space-y-1.5">
                            <textarea
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                rows={2}
                                placeholder="Optional note for the reviewer"
                                className="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs"
                            />
                            <PrimaryButton className="!px-2.5 !py-1 !text-[11px]" onClick={submitCompletion} disabled={busy}>
                                Confirm submit
                            </PrimaryButton>
                        </div>
                    )}
                </>
            ) : quarter.status === 'pending_completion' ? (
                <p className="text-[11px] text-slate-500">
                    Actual: <span className="font-bold text-slate-800">{num(quarter.actual).toLocaleString()}</span>
                    {achievement !== null && ` · ${achievement.toFixed(0)}%`}
                    <br />
                    Waiting on your Company Admin.
                </p>
            ) : (
                <>
                    <p className="text-[11px] text-slate-500">
                        Actual: <span className="font-bold text-slate-800">{num(quarter.actual).toLocaleString()}</span>
                        {achievement !== null && ` · ${achievement.toFixed(0)}%`}
                    </p>
                    {quarter.pending_change_request ? (
                        <div className="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-2 text-[11px]">
                            <p className="font-bold text-amber-700">
                                Requested: {num(quarter.pending_change_request.requested_actual).toLocaleString()}
                            </p>
                            <p className="text-amber-600 mt-0.5">{quarter.pending_change_request.reason}</p>
                        </div>
                    ) : (
                        <>
                            <button
                                type="button"
                                onClick={() => setShowChangeRequest((v) => !v)}
                                className="mt-1.5 text-[11px] font-semibold text-brand-800 hover:underline"
                            >
                                Request a change
                            </button>
                            {showChangeRequest && (
                                <div className="mt-2 space-y-1.5">
                                    <input
                                        type="number"
                                        min={0}
                                        step={0.01}
                                        value={requestedActual}
                                        onChange={(e) => setRequestedActual(e.target.value)}
                                        className="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm"
                                    />
                                    <textarea
                                        value={reason}
                                        onChange={(e) => setReason(e.target.value)}
                                        rows={2}
                                        placeholder="Why does this need to change? (min 20 characters)"
                                        className="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs"
                                    />
                                    <PrimaryButton className="!px-2.5 !py-1 !text-[11px]" onClick={requestChange} disabled={busy}>
                                        Submit request
                                    </PrimaryButton>
                                </div>
                            )}
                        </>
                    )}
                </>
            )}
        </div>
    );
}

export default function QuarterlyIndex({ company, financialYear, kpis, isAdmin, completionQueue, changeRequestQueue }: QuarterlyPageProps) {
    function decideCompletion(quarterId: string, verb: 'approve' | 'reject') {
        if (!confirm(`${verb === 'approve' ? 'Approve' : 'Reject'} this sign-off?`)) return;
        router.post(`/platform/companies/${company.id}/quarterly/${quarterId}/${verb}-completion`, {}, { preserveScroll: true });
    }

    function decideChange(requestId: string, verb: 'approve' | 'reject') {
        if (!confirm(`${verb === 'approve' ? 'Approve' : 'Reject'} this change request?`)) return;
        router.post(`/platform/companies/${company.id}/quarterly/change-requests/${requestId}/${verb}`, {}, { preserveScroll: true });
    }

    const totalCompleted = kpis.reduce((s, k) => s + k.quarters.filter((q) => q.status === 'completed').length, 0);
    const totalQuarters = kpis.reduce((s, k) => s + k.quarters.length, 0);

    return (
        <PlatformLayout
            title="Quarterly Progress"
            description={`Track target vs. actual per quarter and submit for sign-off — ${financialYear}.`}
            company={company}
            maxWidth="max-w-5xl"
        >
            <div className="space-y-6">
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <StatCard label="Quarterly KPIs" value={kpis.length} />
                    <StatCard label="Quarters signed off" value={`${totalCompleted} / ${totalQuarters}`} tone={totalCompleted > 0 ? 'success' : 'default'} />
                    <StatCard label="Financial year" value={financialYear} />
                </div>

                {kpis.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon={<TargetIcon className="w-8 h-8" />}
                            title="No quarterly KPIs are assigned to you yet"
                            description="Quarterly tracking only applies to KPIs your Company Admin has set to a 'quarterly' frequency and assigned to you."
                        />
                    </Card>
                ) : (
                    kpis.map((kpi) => (
                        <Card key={kpi.id} title={kpi.name} description={kpi.kpi_categories?.name ?? 'General'}>
                            {kpi.description && <p className="text-xs text-slate-500 -mt-2 mb-3">{kpi.description}</p>}
                            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                {kpi.quarters.map((quarter) => (
                                    <QuarterTile key={quarter.id} companyId={company.id} quarter={quarter} />
                                ))}
                            </div>
                        </Card>
                    ))
                )}

                {isAdmin && (
                    <>
                        <Card title="Sign-off requests" description="Approving locks the quarter's actual value; rejecting sends it back for more work.">
                            {completionQueue.length === 0 ? (
                                <EmptyState icon={<ChecklistIcon className="w-8 h-8" />} title="Nothing waiting for sign-off" />
                            ) : (
                                <div className="space-y-3">
                                    {completionQueue.map((item) => (
                                        <div key={item.id} className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                            <div className="flex items-start justify-between gap-4">
                                                <div className="min-w-0">
                                                    <p className="text-sm font-bold text-slate-900">
                                                        {item.kpis?.name ?? 'KPI'} · {item.quarter}
                                                    </p>
                                                    <p className="text-xs text-slate-500 mt-0.5">
                                                        {item.users?.name ?? 'A member'} · Actual {num(item.actual).toLocaleString()} / Target {num(item.target).toLocaleString()}
                                                    </p>
                                                    {item.completion_note && <p className="text-xs text-slate-600 mt-1.5">{item.completion_note}</p>}
                                                </div>
                                                <div className="flex-none flex items-center gap-2">
                                                    <SecondaryButton onClick={() => decideCompletion(item.id, 'reject')}>Reject</SecondaryButton>
                                                    <PrimaryButton onClick={() => decideCompletion(item.id, 'approve')}>Approve</PrimaryButton>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Card>

                        <Card title="Change requests" description="For a quarter that's already signed off — approving applies the new actual immediately.">
                            {changeRequestQueue.length === 0 ? (
                                <EmptyState icon={<ChecklistIcon className="w-8 h-8" />} title="Nothing waiting for review" />
                            ) : (
                                <div className="space-y-3">
                                    {changeRequestQueue.map((item) => (
                                        <div key={item.id} className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                            <div className="flex items-start justify-between gap-4">
                                                <div className="min-w-0">
                                                    <p className="text-sm font-bold text-slate-900">
                                                        {item.kpis?.name ?? 'KPI'} · {item.kpi_quarters?.quarter ?? ''}
                                                    </p>
                                                    <p className="text-xs text-slate-500 mt-0.5">
                                                        {item.users?.name ?? 'A member'} · {num(item.old_actual).toLocaleString()} → {num(item.requested_actual).toLocaleString()}
                                                    </p>
                                                    <p className="text-xs text-slate-600 mt-1.5">{item.reason}</p>
                                                </div>
                                                <div className="flex-none flex items-center gap-2">
                                                    <SecondaryButton onClick={() => decideChange(item.id, 'reject')}>Reject</SecondaryButton>
                                                    <PrimaryButton onClick={() => decideChange(item.id, 'approve')}>Approve</PrimaryButton>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Card>
                    </>
                )}
            </div>
        </PlatformLayout>
    );
}
