import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, InfoTooltip, PrimaryButton, SecondaryButton } from '@/Components/Platform/ui';
import { PlusIcon, TargetIcon } from '@/Components/Platform/Icons';

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
    frequency: string;
    status: string;
    visibility: 'company' | 'department' | 'restricted';
    kpi_categories: { name: string } | null;
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

interface KpisPageProps {
    company: Company;
    categories: Category[];
    kpis: Kpi[];
    templates: Template[];
    templateItems: TemplateItem[];
    grants: Grant[];
    departments: Department[];
    members: Member[];
    [key: string]: unknown;
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
            <button type="submit" disabled={processing} className="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60">
                Save
            </button>
            <button type="button" onClick={() => setOpen(false)} className="text-xs text-slate-400">
                Cancel
            </button>
        </form>
    );
}

function KpiFormFields({
    data,
    setData,
    categories,
}: {
    data: { category_id: string; name: string; description: string; target: string; unit: string; frequency: string; visibility: string };
    setData: (key: string, value: string) => void;
    categories: Category[];
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

function CreateKpiPanel({ companyId, categories }: { companyId: string; categories: Category[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        category_id: '',
        name: '',
        description: '',
        target: '',
        unit: '',
        frequency: 'monthly',
        visibility: 'company',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/kpis`, {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    if (!open) {
        return (
            <PrimaryButton onClick={() => setOpen(true)} className="mb-5 inline-flex items-center gap-1.5">
                <PlusIcon className="w-4 h-4" /> New KPI
            </PrimaryButton>
        );
    }

    return (
        <form onSubmit={submit} className="grid grid-cols-2 gap-3 mb-5 bg-slate-50 rounded-xl p-4">
            <KpiFormFields data={data} setData={setData} categories={categories} />
            <div className="col-span-2 flex items-center gap-2">
                <PrimaryButton type="submit" disabled={processing}>
                    Create KPI
                </PrimaryButton>
                <button type="button" onClick={() => setOpen(false)} className="text-sm text-slate-400">
                    Cancel
                </button>
            </div>
        </form>
    );
}

function EditKpiForm({ companyId, kpi, categories, onDone }: { companyId: string; kpi: Kpi; categories: Category[]; onDone: () => void }) {
    const { data, setData, patch, processing } = useForm({
        category_id: kpi.kpi_categories ? categories.find((c) => c.name === kpi.kpi_categories?.name)?.id ?? '' : '',
        name: kpi.name,
        description: kpi.description ?? '',
        target: kpi.target !== null ? String(kpi.target) : '',
        unit: kpi.unit ?? '',
        frequency: kpi.frequency,
        visibility: kpi.visibility,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(`/platform/companies/${companyId}/kpis/${kpi.id}`, { onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="grid grid-cols-2 gap-3 mt-3 mb-2 bg-slate-50 rounded-xl p-4">
            <KpiFormFields data={data} setData={setData} categories={categories} />
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

            <button type="submit" disabled={processing} className="rounded-lg bg-slate-800 px-3 py-1 text-xs font-semibold text-white disabled:opacity-60">
                Grant
            </button>
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
            {editing && <EditKpiForm companyId={company.id} kpi={kpi} categories={categories} onDone={() => setEditing(false)} />}
            <KpiVisibilityGrants kpi={kpi} companyId={company.id} grants={grants} departments={departments} members={members} />
        </li>
    );
}

export default function KpisIndex({ company, categories, kpis, templates, templateItems, grants, departments, members }: KpisPageProps) {
    return (
        <PlatformLayout
            title="KPIs"
            description="The metrics this company tracks — what's measured, how often, and who's expected to report against it."
            company={company}
        >
            <Card>
                <div className="flex flex-wrap items-center justify-between gap-3 mb-2">
                    <CreateKpiPanel companyId={company.id} categories={categories} />
                </div>
                <ApplyTemplateForm companyId={company.id} templates={templates} templateItems={templateItems} />
                <CreateCategoryForm companyId={company.id} />

                {kpis.length === 0 ? (
                    <EmptyState
                        icon={<TargetIcon className="w-10 h-10" />}
                        title="No KPIs yet"
                        description="Create one by hand above, or apply a shared template to get started quickly."
                    />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {kpis.map((kpi) => (
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
