import { Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useMemo, useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, InfoTooltip, PrimaryButton, SecondaryButton } from '@/Components/Platform/ui';
import { PlusIcon, TargetIcon } from '@/Components/Platform/Icons';
import { scoreStyle } from '@/lib/scoreStyle';
import { categoryStyleFor } from '@/config/dashboardCategories';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface Category {
    id: string;
    name: string;
}

interface Kpi {
    id: string;
    name: string;
    description: string | null;
    target: number | null;
    unit: string | null;
    weight: number | null;
    assigned_user_id: string | null;
    frequency: string;
    status: string;
    visibility: 'company' | 'department' | 'restricted';
    kpi_categories: { name: string } | null;
    quarter_targets: Partial<Record<'Q1' | 'Q2' | 'Q3' | 'Q4', number | string>>;
}

interface Template {
    id: string;
    name: string;
    description: string | null;
}

interface TemplateItem {
    id: string;
    template_id: string;
    category_name: string | null;
    name: string;
}

interface Grant {
    id: string;
    kpi_id: string;
    user_id: string | null;
    department_id: string | null;
    users: { name: string; email: string } | null;
    departments: { name: string } | null;
}

interface Department {
    id: string;
    name: string;
}

interface Member {
    user_id: string;
    users: { name: string; email: string };
}

interface MyScore {
    overall_score: number | null;
    kpi_count: number;
    total_weight: number;
    quarterly: Record<'Q1' | 'Q2' | 'Q3' | 'Q4', { completed: number; total: number; progress: number }>;
}

interface KpisPageProps {
    company: Company;
    categories: Category[];
    kpis: Kpi[];
    templates: Template[];
    templateItems: TemplateItem[];
    grants: Grant[];
    departments: Department[];
    members: Member[];
    myScore: MyScore;
    financialYear: string;
    [key: string]: unknown;
}

// Small gold-top-bordered summary card — same shell Card uses
// (bg-white rounded-2xl border-t-[3px] border-t-[#D4AF37]) but tighter
// padding, matching legacy's KPI List header strip exactly.
function MiniStatCard({ label, children, className = '' }: { label: string; children: React.ReactNode; className?: string }) {
    return (
        <div className={`px-3.5 py-2.5 rounded-2xl bg-white border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] shadow-sm ${className}`}>
            <p className="text-slate-400 text-[9px] font-semibold uppercase">{label}</p>
            {children}
        </div>
    );
}

function KpiSummaryStrip({ myScore, financialYear }: { myScore: MyScore; financialYear: string }) {
    const score = myScore.overall_score;
    const style = score !== null ? scoreStyle(score) : null;
    const weightTone =
        myScore.total_weight === 100 ? 'text-emerald-700' : myScore.total_weight > 100 ? 'text-red-700' : 'text-[#B8860B]';

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-2.5 mb-4">
            <MiniStatCard label="KPI Score" className="md:col-span-2 xl:col-span-1">
                <div className="flex items-center justify-between gap-3 mt-0.5">
                    <h3 className={`text-lg font-black ${style?.text ?? 'text-slate-400'}`}>{score !== null ? `${score.toFixed(1)}%` : '—'}</h3>
                    {style && <span className={`text-[9px] font-black px-2 py-0.5 rounded-full border ${style.badge}`}>{style.label}</span>}
                </div>
                <div className="mt-1.5 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div className={`h-1.5 rounded-full transition-all duration-300 ${style?.bar ?? ''}`} style={{ width: `${Math.max(0, Math.min(100, score ?? 0))}%` }} />
                </div>
            </MiniStatCard>

            <MiniStatCard label="Financial Year">
                <h3 className="text-base font-black text-[#7A0019] mt-0.5">{financialYear}</h3>
            </MiniStatCard>

            <MiniStatCard label="Total KPI">
                <h3 className="text-base font-black text-slate-900 mt-0.5">{myScore.kpi_count}</h3>
            </MiniStatCard>

            <MiniStatCard label="Weightage">
                <h3 className={`text-base font-black mt-0.5 ${weightTone}`}>{myScore.total_weight.toFixed(2)}%</h3>
            </MiniStatCard>

            <MiniStatCard label="Quarter Score">
                <div className="space-y-0.5 mt-1">
                    {(['Q1', 'Q2', 'Q3', 'Q4'] as const).map((q) => {
                        const progress = myScore.quarterly?.[q]?.progress ?? 0;
                        const qStyle = progress <= 0 ? 'text-slate-400' : scoreStyle(progress).text;
                        return (
                            <div key={q} className="flex items-center justify-between">
                                <span className="text-[9px] font-semibold text-slate-400">{q}</span>
                                <span className={`text-[11px] font-black ${qStyle}`}>{progress > 0 ? `${progress.toFixed(1)}%` : '—'}</span>
                            </div>
                        );
                    })}
                </div>
            </MiniStatCard>
        </div>
    );
}

const VISIBILITY_LABEL: Record<Kpi['visibility'], string> = {
    company: 'Company-wide',
    department: 'Submitting departments',
    restricted: 'Restricted',
};

const VISIBILITY_TONE: Record<Kpi['visibility'], 'success' | 'info' | 'warning'> = {
    company: 'success',
    department: 'info',
    restricted: 'warning',
};

function ApplyTemplateForm({ companyId, templates, templateItems }: { companyId: string; templates: Template[]; templateItems: TemplateItem[] }) {
    const { data, setData, post, processing } = useForm({ template_id: templates[0]?.id ?? '' });

    if (templates.length === 0) {
        return null;
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!confirm('Add every item from this template as a new KPI for this company?')) {
            return;
        }
        post(`/platform/companies/${companyId}/kpis/apply-template`);
    };

    const itemCount = templateItems.filter((i) => i.template_id === data.template_id).length;

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-3 mb-4 bg-slate-50 rounded-xl p-4">
            <div className="flex-1 min-w-[200px]">
                <label className="block text-xs font-medium text-slate-600 mb-1">Start from a shared template instead</label>
                <select
                    value={data.template_id}
                    onChange={(e) => setData('template_id', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    {templates.map((t) => (
                        <option key={t.id} value={t.id}>
                            {t.name}
                        </option>
                    ))}
                </select>
                <p className="text-xs text-slate-400 mt-1">{itemCount} KPI(s) will be added.</p>
            </div>
            <SecondaryButton type="submit" disabled={processing || itemCount === 0}>
                Apply template
            </SecondaryButton>
        </form>
    );
}

function CreateCategoryForm({ companyId }: { companyId: string }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ name: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/kpi-categories`, {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    if (!open) {
        return (
            <button onClick={() => setOpen(true)} className="text-xs font-semibold text-brand-800 hover:underline mb-4">
                + Add a category
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="flex items-end gap-2 mb-4">
            <input
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                placeholder="Category name, e.g. Sales"
                className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                required
                autoFocus
            />
            <PrimaryButton type="submit" disabled={processing}>
                Save
            </PrimaryButton>
            <button type="button" onClick={() => setOpen(false)} className="text-xs text-slate-400">
                Cancel
            </button>
        </form>
    );
}

const QUARTERS: Array<'Q1' | 'Q2' | 'Q3' | 'Q4'> = ['Q1', 'Q2', 'Q3', 'Q4'];

function KpiFormFields({
    data,
    setData,
    categories,
    members,
    quarterTargets,
    setQuarterTarget,
}: {
    data: { category_id: string; name: string; description: string; target: string; unit: string; weight: string; assigned_user_id: string; frequency: string; visibility: string };
    setData: (key: string, value: string) => void;
    categories: Category[];
    members: Member[];
    quarterTargets: Record<'Q1' | 'Q2' | 'Q3' | 'Q4', string>;
    setQuarterTarget: (quarter: 'Q1' | 'Q2' | 'Q3' | 'Q4', value: string) => void;
}) {
    return (
        <>
            <div className="col-span-2">
                <label className="block text-xs font-medium text-slate-600 mb-1">KPI name</label>
                <input
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Customer Satisfaction Score"
                    required
                />
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Category</label>
                <select value={data.category_id} onChange={(e) => setData('category_id', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">None</option>
                    {categories.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">How often is it reported?</label>
                <select value={data.frequency} onChange={(e) => setData('frequency', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            <div>
                <label className="text-xs font-medium text-slate-600 mb-1 inline-flex items-center gap-1">
                    Target
                    <InfoTooltip text="The number someone needs to reach for 100% achievement. Leave blank if this KPI isn't measured against a fixed number." />
                </label>
                <input
                    value={data.target}
                    onChange={(e) => setData('target', e.target.value)}
                    type="number"
                    step="any"
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="90"
                />
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Unit</label>
                <input
                    value={data.unit}
                    onChange={(e) => setData('unit', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="%, $, calls…"
                />
            </div>
            <div>
                <label className="text-xs font-medium text-slate-600 mb-1 inline-flex items-center gap-1">
                    Weight
                    <InfoTooltip text="How much this KPI counts toward the overall weighted score, as a share of 100 across all of this company's KPIs. Leave blank if weighting isn't used yet." />
                </label>
                <input
                    value={data.weight}
                    onChange={(e) => setData('weight', e.target.value)}
                    type="number"
                    step="any"
                    min="0"
                    max="100"
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="e.g. 20"
                />
            </div>
            {data.frequency === 'quarterly' && (
                <div className="col-span-2">
                    <label className="text-xs font-medium text-slate-600 mb-1 inline-flex items-center gap-1">
                        Quarterly targets
                        <InfoTooltip text="Set independently per quarter — they don't have to add up to the overall Target above. Leave a quarter blank to keep it at 0 for now; you can fill it in later." />
                    </label>
                    <div className="grid grid-cols-4 gap-2">
                        {QUARTERS.map((q) => (
                            <div key={q}>
                                <label className="block text-[10px] font-semibold text-slate-400 mb-0.5">{q}</label>
                                <input
                                    value={quarterTargets[q] ?? ''}
                                    onChange={(e) => setQuarterTarget(q, e.target.value)}
                                    type="number"
                                    step="any"
                                    min="0"
                                    className="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                                    placeholder="0"
                                />
                            </div>
                        ))}
                    </div>
                </div>
            )}
            <div className="col-span-2">
                <label className="text-xs font-medium text-slate-600 mb-1 inline-flex items-center gap-1">
                    Assign to
                    <InfoTooltip text="Optional. Makes this one person's KPI on their own Weightage page, where they allocate its weight themselves (new allocations save directly; changing an existing weight needs your approval)." />
                </label>
                <select value={data.assigned_user_id} onChange={(e) => setData('assigned_user_id', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Not assigned to anyone</option>
                    {members.map((m) => (
                        <option key={m.user_id} value={m.user_id}>
                            {m.users.name} ({m.users.email})
                        </option>
                    ))}
                </select>
            </div>
            <div className="col-span-2">
                <label className="text-xs font-medium text-slate-600 mb-1 inline-flex items-center gap-1">
                    Who can see this?
                    <InfoTooltip text="Company-wide: any signed-in member. Submitting departments: only the departments that report against it, plus admins. Restricted: nobody, until you grant access explicitly below." />
                </label>
                <select value={data.visibility} onChange={(e) => setData('visibility', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="company">Company-wide — any member can see it</option>
                    <option value="department">Submitting departments — plus admins/SLT</option>
                    <option value="restricted">Restricted — nobody by default, grant access explicitly</option>
                </select>
            </div>
        </>
    );
}

// A real, separate page now — see Platform/Kpis/Create.tsx — matching
// legacy's own dedicated `kpi.create` view instead of a panel bolted onto
// this list/view page.
function CreateKpiLink({ companyId }: { companyId: string }) {
    return (
        <Link href={`/platform/companies/${companyId}/kpis/create`} className="mb-5 inline-flex">
            <PrimaryButton className="inline-flex items-center gap-1.5">
                <PlusIcon className="w-4 h-4" /> New KPI
            </PrimaryButton>
        </Link>
    );
}

function EditKpiForm({ companyId, kpi, categories, members, onDone }: { companyId: string; kpi: Kpi; categories: Category[]; members: Member[]; onDone: () => void }) {
    const { data, setData, patch, processing } = useForm({
        category_id: kpi.kpi_categories ? categories.find((c) => c.name === kpi.kpi_categories?.name)?.id ?? '' : '',
        name: kpi.name,
        description: kpi.description ?? '',
        target: kpi.target !== null ? String(kpi.target) : '',
        unit: kpi.unit ?? '',
        weight: kpi.weight !== null ? String(kpi.weight) : '',
        assigned_user_id: kpi.assigned_user_id ?? '',
        frequency: kpi.frequency,
        visibility: kpi.visibility,
        quarter_targets: {
            Q1: kpi.quarter_targets.Q1 !== undefined ? String(kpi.quarter_targets.Q1) : '',
            Q2: kpi.quarter_targets.Q2 !== undefined ? String(kpi.quarter_targets.Q2) : '',
            Q3: kpi.quarter_targets.Q3 !== undefined ? String(kpi.quarter_targets.Q3) : '',
            Q4: kpi.quarter_targets.Q4 !== undefined ? String(kpi.quarter_targets.Q4) : '',
        } as Record<'Q1' | 'Q2' | 'Q3' | 'Q4', string>,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(`/platform/companies/${companyId}/kpis/${kpi.id}`, { onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="grid grid-cols-2 gap-3 mt-3 mb-2 bg-slate-50 rounded-xl p-4">
            <KpiFormFields
                data={data}
                setData={setData}
                categories={categories}
                members={members}
                quarterTargets={data.quarter_targets}
                setQuarterTarget={(q, v) => setData('quarter_targets', { ...data.quarter_targets, [q]: v })}
            />
            <div className="col-span-2 flex items-center gap-2">
                <PrimaryButton type="submit" disabled={processing}>
                    Save changes
                </PrimaryButton>
                <button type="button" onClick={onDone} className="text-sm text-slate-400">
                    Cancel
                </button>
            </div>
        </form>
    );
}

function GrantAccessForm({ companyId, kpiId, departments, members }: { companyId: string; kpiId: string; departments: Department[]; members: Member[] }) {
    const [mode, setMode] = useState<'department' | 'user'>('department');
    const { data, setData, post, processing, reset } = useForm({ department_id: '', user_id: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/kpis/${kpiId}/grants`, { onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="flex items-end gap-2 mt-2">
            <select value={mode} onChange={(e) => setMode(e.target.value as 'department' | 'user')} className="rounded-lg border border-slate-300 px-2 py-1 text-xs">
                <option value="department">Department</option>
                <option value="user">Person</option>
            </select>

            {mode === 'department' ? (
                <select value={data.department_id} onChange={(e) => setData('department_id', e.target.value)} className="rounded-lg border border-slate-300 px-2 py-1 text-xs" required>
                    <option value="">Choose a department…</option>
                    {departments.map((d) => (
                        <option key={d.id} value={d.id}>
                            {d.name}
                        </option>
                    ))}
                </select>
            ) : (
                <select value={data.user_id} onChange={(e) => setData('user_id', e.target.value)} className="rounded-lg border border-slate-300 px-2 py-1 text-xs" required>
                    <option value="">Choose a person…</option>
                    {members.map((m) => (
                        <option key={m.user_id} value={m.user_id}>
                            {m.users.name} ({m.users.email})
                        </option>
                    ))}
                </select>
            )}

            <PrimaryButton type="submit" disabled={processing}>
                Grant
            </PrimaryButton>
        </form>
    );
}

function KpiVisibilityGrants({ kpi, companyId, grants, departments, members }: { kpi: Kpi; companyId: string; grants: Grant[]; departments: Department[]; members: Member[] }) {
    if (kpi.visibility === 'company') {
        return null;
    }

    const revoke = (grantId: string) => router.delete(`/platform/companies/${companyId}/kpis/${kpi.id}/grants/${grantId}`);

    return (
        <div className="mt-3 pl-4 border-l-2 border-slate-100">
            {grants.length > 0 && (
                <ul className="space-y-1 mb-1">
                    {grants.map((g) => (
                        <li key={g.id} className="flex items-center justify-between text-xs text-slate-500">
                            <span>{g.departments ? `Dept: ${g.departments.name}` : `${g.users?.name} (${g.users?.email})`}</span>
                            <button onClick={() => revoke(g.id)} className="font-semibold text-red-600 hover:underline">
                                Revoke
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {kpi.visibility === 'restricted' && <GrantAccessForm companyId={companyId} kpiId={kpi.id} departments={departments} members={members} />}
        </div>
    );
}

function KpiRow({ kpi, company, categories, grants, departments, members }: { kpi: Kpi; company: Company; categories: Category[]; grants: Grant[]; departments: Department[]; members: Member[] }) {
    const [editing, setEditing] = useState(false);

    return (
        <li className="py-4">
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0">
                    <p className="text-sm font-bold text-slate-800">{kpi.name}</p>
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1">
                        <span className="text-xs text-slate-400">{kpi.kpi_categories?.name ?? 'Uncategorized'}</span>
                        <span className="text-xs text-slate-400 capitalize">{kpi.frequency}</span>
                        {kpi.target !== null && (
                            <span className="inline-flex items-center gap-1 text-xs font-semibold text-slate-600">
                                <TargetIcon className="w-3.5 h-3.5 text-slate-400" />
                                Target: {kpi.target}
                                {kpi.unit ?? ''}
                            </span>
                        )}
                        {kpi.weight !== null && <Badge tone="neutral">Weight: {kpi.weight}</Badge>}
                        {kpi.assigned_user_id && (
                            <Badge tone="brand">
                                Assigned: {members.find((m) => m.user_id === kpi.assigned_user_id)?.users.name ?? 'Unknown'}
                            </Badge>
                        )}
                        <Badge tone={VISIBILITY_TONE[kpi.visibility]}>{VISIBILITY_LABEL[kpi.visibility]}</Badge>
                    </div>
                </div>
                <div className="flex-none flex items-center gap-3">
                    <button onClick={() => setEditing((v) => !v)} className="text-xs font-semibold text-brand-800 hover:underline">
                        {editing ? 'Close' : 'Edit'}
                    </button>
                    <Badge tone={kpi.status === 'active' ? 'success' : 'neutral'}>{kpi.status}</Badge>
                </div>
            </div>
            {editing && <EditKpiForm companyId={company.id} kpi={kpi} categories={categories} members={members} onDone={() => setEditing(false)} />}
            <KpiVisibilityGrants kpi={kpi} companyId={company.id} grants={grants} departments={departments} members={members} />
        </li>
    );
}

interface PlatformUserShape {
    id: string;
    name: string;
}

function KpiFilterBar({
    kpis,
    categories,
    search,
    setSearch,
    categoryFilter,
    setCategoryFilter,
    statusFilter,
    setStatusFilter,
    assignedOnly,
    setAssignedOnly,
    visibleCount,
}: {
    kpis: Kpi[];
    categories: Category[];
    search: string;
    setSearch: (v: string) => void;
    categoryFilter: string;
    setCategoryFilter: (v: string) => void;
    statusFilter: string;
    setStatusFilter: (v: string) => void;
    assignedOnly: boolean;
    setAssignedOnly: (v: boolean) => void;
    visibleCount: number;
}) {
    const presentCategoryNames = useMemo(
        () => Array.from(new Set(kpis.map((k) => k.kpi_categories?.name).filter((n): n is string => !!n))).sort(),
        [kpis],
    );
    const hasFilters = !!search || !!categoryFilter || !!statusFilter || assignedOnly;

    const clearAll = () => {
        setSearch('');
        setCategoryFilter('');
        setStatusFilter('');
        setAssignedOnly(false);
    };

    return (
        <Card>
            <div className="flex items-center justify-between gap-3 mb-4">
                <p className="text-xs font-bold text-slate-500">
                    {visibleCount} of {kpis.length} KPIs shown
                </p>
                {hasFilters && (
                    <button type="button" onClick={clearAll} className="text-[11px] font-black text-slate-400 hover:text-slate-700 transition">
                        ✕ Clear Filters
                    </button>
                )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label className="text-xs font-bold text-slate-500 uppercase">Search</label>
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        type="text"
                        placeholder="Title, staff, category..."
                        className="w-full mt-2 border border-[#E5E7EB] bg-white rounded-xl px-4 py-2.5 text-xs"
                    />
                </div>

                <div>
                    <label className="text-xs font-bold text-slate-500 uppercase">Category</label>
                    <select value={categoryFilter} onChange={(e) => setCategoryFilter(e.target.value)} className="w-full mt-2 border border-[#E5E7EB] bg-white rounded-xl px-4 py-2.5 text-xs">
                        <option value="">All</option>
                        {presentCategoryNames.map((name) => (
                            <option key={name} value={name}>
                                {name}
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="text-xs font-bold text-slate-500 uppercase">Status</label>
                    <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="w-full mt-2 border border-[#E5E7EB] bg-white rounded-xl px-4 py-2.5 text-xs">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <label className="flex items-center gap-2 pb-2.5 cursor-pointer select-none">
                    <input type="checkbox" checked={assignedOnly} onChange={(e) => setAssignedOnly(e.target.checked)} className="rounded border-slate-300" />
                    <span className="text-xs font-bold text-slate-600">📩 Assigned to Me</span>
                </label>
            </div>

            {presentCategoryNames.length > 0 && (
                <div className="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center gap-4">
                    <p className="text-[9px] font-black text-slate-400 uppercase tracking-widest shrink-0">Colour Guide</p>
                    <div className="flex flex-wrap gap-1.5">
                        {presentCategoryNames.map((name) => {
                            const dot = categoryStyleFor(name).bg.split(' ')[0];
                            return (
                                <span key={name} className="flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-700 text-[9px] font-black">
                                    <span className={`w-1.5 h-1.5 rounded-full shrink-0 ${dot}`} />
                                    {name}
                                </span>
                            );
                        })}
                    </div>
                </div>
            )}
        </Card>
    );
}

export default function KpisIndex({ company, categories, kpis, templates, templateItems, grants, departments, members, myScore, financialYear }: KpisPageProps) {
    const { platformUser } = usePage<{ platformUser: PlatformUserShape | null }>().props;
    const [search, setSearch] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [assignedOnly, setAssignedOnly] = useState(false);

    const visibleKpis = useMemo(() => {
        const q = search.trim().toLowerCase();
        return kpis.filter((kpi) => {
            if (categoryFilter && kpi.kpi_categories?.name !== categoryFilter) return false;
            if (statusFilter && kpi.status !== statusFilter) return false;
            if (assignedOnly && kpi.assigned_user_id !== platformUser?.id) return false;
            if (q) {
                const ownerName = members.find((m) => m.user_id === kpi.assigned_user_id)?.users.name ?? '';
                const haystack = `${kpi.name} ${kpi.kpi_categories?.name ?? ''} ${ownerName}`.toLowerCase();
                if (!haystack.includes(q)) return false;
            }
            return true;
        });
    }, [kpis, search, categoryFilter, statusFilter, assignedOnly, members, platformUser]);

    return (
        <PlatformLayout
            title="KPI List"
            description="The metrics this company tracks — what's measured, how often, and who's expected to report against it."
            company={company}
        >
            {kpis.length > 0 && (
                <div className="flex justify-end mb-3">
                    <CreateKpiLink companyId={company.id} />
                </div>
            )}

            <KpiSummaryStrip myScore={myScore} financialYear={financialYear} />

            <KpiFilterBar
                kpis={kpis}
                categories={categories}
                search={search}
                setSearch={setSearch}
                categoryFilter={categoryFilter}
                setCategoryFilter={setCategoryFilter}
                statusFilter={statusFilter}
                setStatusFilter={setStatusFilter}
                assignedOnly={assignedOnly}
                setAssignedOnly={setAssignedOnly}
                visibleCount={visibleKpis.length}
            />

            <Card className="mt-4">
                <ApplyTemplateForm companyId={company.id} templates={templates} templateItems={templateItems} />
                <CreateCategoryForm companyId={company.id} />

                {kpis.length === 0 ? (
                    <div className="bg-white border border-dashed border-slate-300 rounded-2xl p-10 text-center">
                        <h3 className="text-xl font-black text-slate-900">No KPI Created Yet</h3>
                        <p className="text-sm text-slate-500 mt-2">Start creating KPI for your yearly execution tracking.</p>
                        <div className="mt-4 flex justify-center">
                            <CreateKpiLink companyId={company.id} />
                        </div>
                    </div>
                ) : visibleKpis.length === 0 ? (
                    <EmptyState icon={<TargetIcon className="w-10 h-10" />} title="No KPIs match your filters" description="Try clearing search, category, status, or the assigned-to-me filter." />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {visibleKpis.map((kpi) => (
                            <KpiRow
                                key={kpi.id}
                                kpi={kpi}
                                company={company}
                                categories={categories}
                                grants={grants.filter((g) => g.kpi_id === kpi.id)}
                                departments={departments}
                                members={members}
                            />
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
