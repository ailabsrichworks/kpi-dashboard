import { router, useForm } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, PrimaryButton, SecondaryButton } from '@/Components/Platform/ui';
import { LinkIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface DirectReport {
    user_id: string;
    users: { name: string; email: string };
}

interface Category {
    id: string;
    name: string;
}

interface Linkage {
    id: string;
    category_id: string;
    unit: string | null;
    assigned_target: number;
    covered: number;
    gap: number;
    pct: number;
    met: boolean;
    kpi_categories: { name: string } | null;
    assigner?: { name: string } | null;
    assignee?: { name: string } | null;
}

interface TargetLinkagesPageProps {
    company: Company;
    financialYear: string;
    directReports: DirectReport[];
    categories: Category[];
    incoming: Linkage[];
    outgoing: Linkage[];
    canAssignTarget: boolean;
    [key: string]: unknown;
}

function fmt(value: number, unit: string | null): string {
    const n = Math.round(value).toLocaleString('en-US');
    return unit ? `${n} ${unit}` : n;
}

function CoverageBar({ pct, met }: { pct: number; met: boolean }) {
    return (
        <div className="mt-1.5 h-1.5 rounded-full bg-slate-100 overflow-hidden">
            <div className={`h-1.5 rounded-full transition-all ${met ? 'bg-emerald-500' : 'bg-amber-400'}`} style={{ width: `${Math.max(0, Math.min(100, pct))}%` }} />
        </div>
    );
}

function LinkageCard({ linkage, nameLabel, name, onDelete }: { linkage: Linkage; nameLabel: string; name: string; onDelete?: () => void }) {
    return (
        <div className="rounded-xl border border-slate-200 p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs text-slate-400">{nameLabel}</p>
                    <p className="text-sm font-bold text-slate-800">{name}</p>
                    <p className="text-xs text-slate-500 mt-0.5">{linkage.kpi_categories?.name ?? 'Uncategorized'}</p>
                </div>
                <div className="flex items-center gap-2">
                    <Badge tone={linkage.met ? 'success' : 'warning'}>{linkage.met ? 'Covered' : 'Gap'}</Badge>
                    {onDelete && (
                        <button onClick={onDelete} className="text-xs font-bold text-red-500 hover:text-red-700">
                            Remove
                        </button>
                    )}
                </div>
            </div>
            <div className="mt-3 flex items-center justify-between text-xs">
                <span className="text-slate-500">
                    {fmt(linkage.covered, linkage.unit)} / {fmt(linkage.assigned_target, linkage.unit)}
                </span>
                <span className={`font-bold ${linkage.met ? 'text-emerald-600' : 'text-amber-600'}`}>{linkage.pct}%</span>
            </div>
            <CoverageBar pct={linkage.pct} met={linkage.met} />
            {!linkage.met && <p className="text-[11px] text-amber-600 mt-1.5">Gap: {fmt(linkage.gap, linkage.unit)}</p>}
        </div>
    );
}

function AssignForm({ companyId, directReports, categories }: { companyId: string; directReports: DirectReport[]; categories: Category[] }) {
    const { data, setData, post, processing, reset } = useForm({
        assignee_user_id: directReports[0]?.user_id ?? '',
        category_id: categories[0]?.id ?? '',
        unit: '',
        assigned_target: '',
    });

    const submit = () => {
        post(`/platform/companies/${companyId}/target-linkages`, { preserveScroll: true, onSuccess: () => reset('assigned_target') });
    };

    if (directReports.length === 0 || categories.length === 0) return null;

    return (
        <Card title="Assign a target" description="Cascade part of your own target down to a direct report.">
            <div className="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label className="block text-[10px] font-bold uppercase text-slate-400 mb-1">Direct report</label>
                    <select value={data.assignee_user_id} onChange={(e) => setData('assignee_user_id', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs">
                        {directReports.map((r) => (
                            <option key={r.user_id} value={r.user_id}>{r.users.name}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-[10px] font-bold uppercase text-slate-400 mb-1">Category</label>
                    <select value={data.category_id} onChange={(e) => setData('category_id', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs">
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>{c.name}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-[10px] font-bold uppercase text-slate-400 mb-1">Unit (optional)</label>
                    <input value={data.unit} onChange={(e) => setData('unit', e.target.value)} placeholder="%, RM, calls…" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs" />
                </div>
                <div>
                    <label className="block text-[10px] font-bold uppercase text-slate-400 mb-1">Target</label>
                    <input type="number" min={0} value={data.assigned_target} onChange={(e) => setData('assigned_target', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs" />
                </div>
            </div>
            <div className="mt-3">
                <PrimaryButton type="button" onClick={submit} disabled={processing || !data.assigned_target}>Save linkage</PrimaryButton>
            </div>
        </Card>
    );
}

export default function TargetLinkagesIndex({ company, financialYear, directReports, categories, incoming, outgoing, canAssignTarget }: TargetLinkagesPageProps) {
    const removeLinkage = (id: string) => {
        if (!confirm('Remove this target linkage?')) return;
        router.delete(`/platform/companies/${company.id}/target-linkages/${id}`, { preserveScroll: true });
    };

    return (
        <PlatformLayout
            title="Target Linkages"
            description={`${financialYear} — cascade a target from you to your direct reports, and see how much of it their own KPIs actually cover.`}
            company={company}
            maxWidth="max-w-4xl"
        >
            <div className="space-y-6">
                <Card title="Assigned to me" description="Targets your own manager assigned to you, measured against your own KPIs.">
                    {incoming.length === 0 ? (
                        <EmptyState icon={<LinkIcon className="w-8 h-8" />} title="Nothing assigned to you yet" />
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {incoming.map((lnk) => (
                                <LinkageCard key={lnk.id} linkage={lnk} nameLabel="Assigned by" name={lnk.assigner?.name ?? 'Unknown'} />
                            ))}
                        </div>
                    )}
                </Card>

                {canAssignTarget && <AssignForm companyId={company.id} directReports={directReports} categories={categories} />}

                {outgoing.length > 0 && (
                    <Card title="Assigned by me" description="Targets you've cascaded to your direct reports.">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {outgoing.map((lnk) => (
                                <LinkageCard key={lnk.id} linkage={lnk} nameLabel="Assigned to" name={lnk.assignee?.name ?? 'Unknown'} onDelete={() => removeLinkage(lnk.id)} />
                            ))}
                        </div>
                    </Card>
                )}

                {!canAssignTarget && directReports.length === 0 && incoming.length === 0 && (
                    <Card>
                        <EmptyState
                            icon={<LinkIcon className="w-8 h-8" />}
                            title="No target linkages yet"
                            description="You have no direct reports assigned to you, and nobody has cascaded a target to you either. Ask your Company Admin to set who reports to whom on the Departments page."
                        />
                    </Card>
                )}
            </div>
        </PlatformLayout>
    );
}
