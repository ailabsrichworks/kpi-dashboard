import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, InfoTooltip, PrimaryButton, SecondaryButton, StatCard } from '@/Components/Platform/ui';
import { ChecklistIcon, TargetIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface PendingRequest {
    id: string;
    old_weight: string | number;
    new_weight: string | number;
    reason: string;
    created_at: string;
}

interface Kpi {
    id: string;
    name: string;
    description: string | null;
    weight: string | number | null;
    category_id: string | null;
    kpi_categories: { name: string } | null;
    pending_request?: PendingRequest | null;
}

interface ReviewQueueItem {
    id: string;
    kpi_id: string;
    old_weight: string | number;
    new_weight: string | number;
    reason: string;
    created_at: string;
    kpis: { name: string } | null;
    users: { name: string; email: string } | null;
}

interface WeightagePageProps {
    company: Company;
    kpis: Kpi[];
    isAdmin: boolean;
    reviewQueue: ReviewQueueItem[];
    [key: string]: unknown;
}

function num(v: string | number | null | undefined): number {
    if (v === null || v === undefined) return 0;
    const n = typeof v === 'number' ? v : parseFloat(v);
    return Number.isFinite(n) ? n : 0;
}

function pct(n: number): string {
    return n.toFixed(2) + '%';
}

export default function WeightageIndex({ company, kpis, isAdmin, reviewQueue }: WeightagePageProps) {
    const [values, setValues] = useState<Record<string, string>>(() =>
        Object.fromEntries(kpis.map((k) => [k.id, num(k.weight).toFixed(2)]))
    );
    const [reviewOpen, setReviewOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const originals = useMemo(() => Object.fromEntries(kpis.map((k) => [k.id, num(k.weight)])), [kpis]);
    const pendingByKpi = useMemo(() => Object.fromEntries(kpis.map((k) => [k.id, k.pending_request ?? null])), [kpis]);

    const total = useMemo(
        () => Object.entries(values).reduce((sum, [id, v]) => (pendingByKpi[id] ? sum : sum + (parseFloat(v) || 0)), 0),
        [values, pendingByKpi]
    );
    const remaining = Math.max(0, 100 - total);
    const over = total > 100;

    const directItems = useMemo(
        () =>
            kpis.filter((k) => {
                if (pendingByKpi[k.id]) return false;
                const original = originals[k.id];
                const v = parseFloat(values[k.id] ?? '0') || 0;
                return original <= 0 && Math.abs(v - original) > 0.001;
            }),
        [kpis, values, originals, pendingByKpi]
    );

    const approvalItems = useMemo(
        () =>
            kpis.filter((k) => {
                if (pendingByKpi[k.id]) return false;
                const original = originals[k.id];
                const v = parseFloat(values[k.id] ?? '0') || 0;
                return original > 0 && Math.abs(v - original) > 0.001;
            }),
        [kpis, values, originals, pendingByKpi]
    );

    const totalChanges = directItems.length + approvalItems.length;

    const grouped = useMemo(() => {
        const groups = new Map<string, Kpi[]>();
        kpis.forEach((k) => {
            const cat = k.kpi_categories?.name ?? 'General';
            if (!groups.has(cat)) groups.set(cat, []);
            groups.get(cat)!.push(k);
        });
        return Array.from(groups.entries());
    }, [kpis]);

    function setValue(id: string, v: string) {
        setValues((prev) => ({ ...prev, [id]: v }));
    }

    function equalizeAll() {
        if (!confirm('Equalize all KPI weightage? This will overwrite current allocation.')) return;
        const editable = kpis.filter((k) => !pendingByKpi[k.id]);
        if (!editable.length) return;
        const equal = Math.floor((100 / editable.length) * 100) / 100;
        let assigned = 0;
        const next: Record<string, string> = { ...values };
        editable.forEach((k, i) => {
            if (i === editable.length - 1) {
                next[k.id] = (100 - assigned).toFixed(2);
            } else {
                next[k.id] = equal.toFixed(2);
                assigned += equal;
            }
        });
        setValues(next);
    }

    function balanceEmpty() {
        const editable = kpis.filter((k) => !pendingByKpi[k.id]);
        const empties = editable.filter((k) => (parseFloat(values[k.id] ?? '0') || 0) <= 0);
        if (!empties.length) return;
        const used = editable.reduce((s, k) => (empties.includes(k) ? s : s + (parseFloat(values[k.id] ?? '0') || 0)), 0);
        const balance = Math.max(0, 100 - used);
        const share = Math.floor((balance / empties.length) * 100) / 100;
        const next: Record<string, string> = { ...values };
        empties.forEach((k, i) => {
            next[k.id] = i === empties.length - 1 ? (balance - share * (empties.length - 1)).toFixed(2) : share.toFixed(2);
        });
        setValues(next);
    }

    function resetAll() {
        if (!confirm('Reset all changes back to saved values?')) return;
        setValues(Object.fromEntries(kpis.map((k) => [k.id, num(k.weight).toFixed(2)])));
    }

    function confirmSaveAll() {
        if (approvalItems.length > 0 && reason.trim().length < 20) {
            alert('Please enter a reason with at least 20 characters for the approval requests.');
            return;
        }

        setSubmitting(true);

        const runApprovals = (index: number) => {
            if (index >= approvalItems.length) {
                setSubmitting(false);
                setReviewOpen(false);
                router.reload();
                return;
            }
            const item = approvalItems[index];
            router.post(
                `/platform/companies/${company.id}/kpis/${item.id}/weight-change-requests`,
                {
                    old_weight: originals[item.id],
                    new_weight: parseFloat(values[item.id] ?? '0') || 0,
                    reason,
                },
                {
                    preserveScroll: true,
                    onFinish: () => runApprovals(index + 1),
                }
            );
        };

        if (directItems.length > 0) {
            const weights = Object.fromEntries(directItems.map((k) => [k.id, parseFloat(values[k.id] ?? '0') || 0]));
            router.post(
                `/platform/companies/${company.id}/weightage/allocate`,
                { weights },
                {
                    preserveScroll: true,
                    onFinish: () => runApprovals(0),
                }
            );
        } else {
            runApprovals(0);
        }
    }

    function decide(requestId: string, verb: 'approve' | 'reject') {
        if (!confirm(`${verb === 'approve' ? 'Approve' : 'Reject'} this weight change request?`)) return;
        router.post(`/platform/companies/${company.id}/weight-change-requests/${requestId}/${verb}`, {}, { preserveScroll: true });
    }

    return (
        <PlatformLayout
            title="Weightage"
            description="Allocate how much each of your KPIs counts toward your overall score — must add up to 100%."
            company={company}
            maxWidth="max-w-4xl"
        >
            <div className="space-y-6 pb-28">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <StatCard label="My KPIs" value={kpis.length} />
                    <StatCard
                        label="Total weightage"
                        value={pct(total)}
                        tone={over ? 'danger' : total === 100 ? 'success' : 'warning'}
                    />
                    <StatCard
                        label="Remaining"
                        value={pct(remaining)}
                        tone={over ? 'danger' : remaining === 0 ? 'success' : 'warning'}
                    />
                    <StatCard
                        label="Changes ready"
                        value={totalChanges}
                        hint={totalChanges > 0 ? `${directItems.length} direct · ${approvalItems.length} need approval` : undefined}
                    />
                </div>

                {kpis.length > 0 && (
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 flex flex-wrap items-center gap-3">
                        <SecondaryButton onClick={balanceEmpty}>Balance empty</SecondaryButton>
                        <SecondaryButton onClick={equalizeAll}>Equalize all</SecondaryButton>
                        {totalChanges > 0 && (
                            <SecondaryButton onClick={resetAll}>Reset changes</SecondaryButton>
                        )}
                        <span className="ml-auto text-xs font-semibold text-slate-500">
                            {over
                                ? `Over by ${pct(total - 100)}`
                                : total === 100
                                  ? 'Total = 100% ✓'
                                  : `${pct(remaining)} left to allocate`}
                        </span>
                    </div>
                )}

                {kpis.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon={<TargetIcon className="w-8 h-8" />}
                            title="No KPIs are assigned to you yet"
                            description="Ask your Company Admin to assign one or more KPIs to you from the KPIs page before you can allocate weightage."
                        />
                    </Card>
                ) : (
                    grouped.map(([category, categoryKpis]) => {
                        const categoryTotal = categoryKpis.reduce(
                            (s, k) => s + (pendingByKpi[k.id] ? num(k.weight) : parseFloat(values[k.id] ?? '0') || 0),
                            0
                        );

                        return (
                            <Card
                                key={category}
                                title={category}
                                description={`${categoryKpis.length} KPI`}
                                actions={<span className="text-sm font-bold text-slate-700">{pct(categoryTotal)}</span>}
                            >
                                <div className="divide-y divide-slate-100 -mx-6 -mb-2">
                                    {categoryKpis.map((kpi) => {
                                        const original = originals[kpi.id];
                                        const value = parseFloat(values[kpi.id] ?? '0') || 0;
                                        const isNew = original <= 0;
                                        const changed = Math.abs(value - original) > 0.001;
                                        const pending = pendingByKpi[kpi.id];

                                        return (
                                            <div key={kpi.id} className={`px-6 py-4 ${pending ? 'bg-amber-50' : ''}`}>
                                                <div className="flex flex-col sm:flex-row sm:items-start gap-4">
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex flex-wrap gap-2 mb-1.5">
                                                            {isNew && !pending && <Badge tone="success">New allocation</Badge>}
                                                            {!isNew && !pending && <Badge tone="neutral">Current: {pct(original)}</Badge>}
                                                            {pending && <Badge tone="warning">Pending approval</Badge>}
                                                        </div>
                                                        <p className="text-sm font-bold text-slate-900">{kpi.name}</p>
                                                        {kpi.description && (
                                                            <p className="text-xs text-slate-500 mt-0.5 line-clamp-2">{kpi.description}</p>
                                                        )}
                                                        {pending && (
                                                            <div className="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-[11px]">
                                                                <p className="font-bold text-amber-700">
                                                                    Requested: {pct(num(pending.old_weight))} → {pct(num(pending.new_weight))}
                                                                </p>
                                                                <p className="text-amber-600 mt-0.5">{pending.reason}</p>
                                                            </div>
                                                        )}
                                                    </div>

                                                    <div className="w-full sm:w-40 flex-none">
                                                        <label className="text-[10px] font-semibold uppercase text-slate-400 inline-flex items-center gap-1">
                                                            Weight %
                                                            <InfoTooltip text="A new (0%) KPI saves immediately. Changing a KPI that already has a weight needs your Company Admin's approval." />
                                                        </label>
                                                        <input
                                                            type="number"
                                                            min={0}
                                                            max={100}
                                                            step={0.01}
                                                            value={values[kpi.id] ?? '0'}
                                                            disabled={!!pending}
                                                            onChange={(e) => setValue(kpi.id, e.target.value)}
                                                            className="w-full mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-900 disabled:opacity-50 disabled:cursor-not-allowed"
                                                        />
                                                        {!pending && changed && (
                                                            <p className={`mt-1 text-[10px] font-semibold ${isNew ? 'text-indigo-600' : 'text-amber-600'}`}>
                                                                {isNew ? 'Ready to save' : 'Needs approval'}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </Card>
                        );
                    })
                )}

                {isAdmin && (
                    <Card
                        title="Weight change requests"
                        description="Approving applies the new weight immediately; rejecting leaves the current weight unchanged."
                    >
                        {reviewQueue.length === 0 ? (
                            <EmptyState icon={<ChecklistIcon className="w-8 h-8" />} title="Nothing waiting for review" />
                        ) : (
                            <div className="space-y-3">
                                {reviewQueue.map((item) => (
                                    <div key={item.id} className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="min-w-0">
                                                <p className="text-sm font-bold text-slate-900">{item.kpis?.name ?? 'KPI'}</p>
                                                <p className="text-xs text-slate-500 mt-0.5">
                                                    {item.users?.name ?? 'A member'} · {pct(num(item.old_weight))} → {pct(num(item.new_weight))}
                                                </p>
                                                <p className="text-xs text-slate-600 mt-1.5">{item.reason}</p>
                                            </div>
                                            <div className="flex-none flex items-center gap-2">
                                                <SecondaryButton onClick={() => decide(item.id, 'reject')}>Reject</SecondaryButton>
                                                <PrimaryButton onClick={() => decide(item.id, 'approve')}>Approve</PrimaryButton>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                )}
            </div>

            {totalChanges > 0 && (
                <div className="fixed bottom-0 left-0 right-0 lg:left-64 z-40 bg-white/95 backdrop-blur-sm border-t border-slate-200 px-6 py-4">
                    <div className="max-w-4xl mx-auto flex items-center justify-between gap-4">
                        <div>
                            <p className="text-sm font-bold text-slate-800">
                                {totalChanges} change{totalChanges !== 1 ? 's' : ''} ready
                            </p>
                            <p className="text-[11px] text-slate-500">
                                {directItems.length > 0 && `${directItems.length} save directly`}
                                {directItems.length > 0 && approvalItems.length > 0 && ' · '}
                                {approvalItems.length > 0 && `${approvalItems.length} need approval`}
                            </p>
                        </div>
                        <PrimaryButton disabled={over} onClick={() => setReviewOpen(true)}>
                            {over ? 'Total over 100%' : 'Save all weightages'}
                        </PrimaryButton>
                    </div>
                </div>
            )}

            {reviewOpen && (
                <div className="fixed inset-0 z-50 bg-black/50 flex items-start justify-center p-4 overflow-y-auto">
                    <div className="bg-white rounded-2xl w-full max-w-lg my-8 shadow-2xl">
                        <div className="px-6 py-5 border-b border-slate-100">
                            <h2 className="text-lg font-bold text-slate-900">Review changes</h2>
                            <p className="text-xs text-slate-500 mt-0.5">Check everything before confirming.</p>
                        </div>

                        <div className="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                            {directItems.length > 0 && (
                                <div>
                                    <p className="text-xs font-bold text-emerald-700 mb-2">{directItems.length} will save immediately</p>
                                    <div className="space-y-1.5">
                                        {directItems.map((k) => (
                                            <div key={k.id} className="flex items-center justify-between bg-emerald-50 rounded-lg px-3 py-2 text-xs">
                                                <span className="font-semibold text-slate-700 truncate mr-3">{k.name}</span>
                                                <span className="font-bold text-emerald-700 flex-none">
                                                    0% → {pct(parseFloat(values[k.id] ?? '0') || 0)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {approvalItems.length > 0 && (
                                <div>
                                    <p className="text-xs font-bold text-amber-700 mb-2">{approvalItems.length} need Company Admin approval</p>
                                    <div className="space-y-1.5 mb-3">
                                        {approvalItems.map((k) => (
                                            <div key={k.id} className="flex items-center justify-between bg-amber-50 rounded-lg px-3 py-2 text-xs">
                                                <span className="font-semibold text-slate-700 truncate mr-3">{k.name}</span>
                                                <span className="font-bold text-amber-700 flex-none">
                                                    {pct(originals[k.id])} → {pct(parseFloat(values[k.id] ?? '0') || 0)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                    <label className="text-xs font-bold uppercase text-slate-600">Reason for these changes</label>
                                    <p className="text-[11px] text-slate-400 mt-0.5 mb-1.5">Minimum 20 characters — covers every approval request above.</p>
                                    <textarea
                                        value={reason}
                                        onChange={(e) => setReason(e.target.value)}
                                        rows={3}
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        placeholder="e.g. Shifting focus this quarter due to board priorities..."
                                    />
                                    <p className="text-[10px] text-slate-400 mt-1 text-right">{reason.length} / min 20 chars</p>
                                </div>
                            )}
                        </div>

                        <div className="px-6 py-4 border-t border-slate-100 flex justify-end gap-3">
                            <SecondaryButton onClick={() => setReviewOpen(false)} disabled={submitting}>
                                Cancel
                            </SecondaryButton>
                            <PrimaryButton onClick={confirmSaveAll} disabled={submitting}>
                                {submitting ? 'Saving...' : 'Confirm & save'}
                            </PrimaryButton>
                        </div>
                    </div>
                </div>
            )}
        </PlatformLayout>
    );
}
