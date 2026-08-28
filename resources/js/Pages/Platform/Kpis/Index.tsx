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

interface Goal {
    id: string;
    title: string;
}

type MeasurementUnit = 'number' | 'currency' | 'percentage' | 'ratio' | 'days' | 'hours' | 'score' | 'binary' | 'custom';
type MeasurementDirection = 'higher_is_better' | 'lower_is_better' | 'target_range' | 'on_or_before' | 'binary_completion';

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
    company_goal_id: string | null;
    parent_kpi_id: string | null;
    department_id: string | null;
    owner_user_id: string | null;
    measurement_unit: MeasurementUnit;
    measurement_direction: MeasurementDirection;
    stretch_target: number | null;
    weightage: number | null;
    /** Server-computed (KpiCalculationService) from this KPI's own most recent submission — null if nothing's been submitted yet or the direction isn't calculable. */
    computed_achievement: number | null;
    computed_expected_progress: number | null;
    computed_status: 'not_scored' | 'critical' | 'at_risk' | 'on_track' | 'achieved' | 'exceeded';
    /** Server-computed weighted roll-up from this KPI's children, one level deep — null if it has no children or none are scored. */
    rollup_achievement: number | null;
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

interface PendingTargetRevision {
    id: string;
    kpi_id: string;
    old_target: number | null;
    new_target: number;
    reason: string;
    effective_financial_year: number;
    requested_at: string;
    users: { name: string } | null;
}

interface KpisPageProps {
    company: Company;
    categories: Category[];
    kpis: Kpi[];
    templates: Template[];
    templateItems: TemplateItem[];
    grants: Grant[];
    goals: Goal[];
    departments: Department[];
    members: Member[];
    pendingTargetRevisions: PendingTargetRevision[];
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

const PERFORMANCE_STATUS_LABELS: Record<Kpi['computed_status'], string> = {
    not_scored: 'Not scored yet',
    critical: 'Critical',
    at_risk: 'At risk',
    on_track: 'On track',
    achieved: 'Achieved',
    exceeded: 'Exceeded',
};

const PERFORMANCE_STATUS_TONE: Record<Kpi['computed_status'], 'neutral' | 'danger' | 'warning' | 'success'> = {
    not_scored: 'neutral',
    critical: 'danger',
    at_risk: 'warning',
    on_track: 'success',
    achieved: 'success',
    exceeded: 'success',
};

const MEASUREMENT_UNIT_LABELS: Record<MeasurementUnit, string> = {
    number: 'Number',
    currency: 'Currency',
    percentage: 'Percentage',
    ratio: 'Ratio',
    days: 'Days',
    hours: 'Hours',
    score: 'Score',
    binary: 'Binary',
    custom: 'Custom',
};

const MEASUREMENT_DIRECTION_LABELS: Record<MeasurementDirection, string> = {
    higher_is_better: 'Higher is better',
    lower_is_better: 'Lower is better',
    target_range: 'Target range',
    on_or_before: 'On or before (deadline)',
    binary_completion: 'Binary completion',
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

interface KpiFormData {
    category_id: string;
    name: string;
    description: string;
    target: string;
    unit: string;
    frequency: string;
    visibility: string;
    company_goal_id: string;
    parent_kpi_id: string;
    department_id: string;
    owner_user_id: string;
    measurement_unit: string;
    measurement_direction: string;
    stretch_target: string;
    weightage: string;
}

function KpiFormFields({
    data,
    setData,
    categories,
    goals,
    departments,
    members,
    kpiOptions,
}: {
    data: KpiFormData;
    setData: (key: string, value: string) => void;
    categories: Category[];
    goals: Goal[];
    departments: Department[];
    members: Member[];
    /** Candidate parents — excludes the KPI being edited itself, if any. */
    kpiOptions: Kpi[];
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
                    Base target
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
                <label className="text-xs font-medium text-slate-600 mb-1 inline-flex items-center gap-1">
                    Stretch target
                    <InfoTooltip text="An optional, more ambitious target. Achievement beyond the base target is scored against this, up to 200%." />
                </label>
                <input
                    value={data.stretch_target}
                    onChange={(e) => setData('stretch_target', e.target.value)}
                    type="number"
                    step="any"
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Optional"
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
                    Measurement unit
                    <InfoTooltip text="What kind of number this is — used to format it consistently and pick a sensible calculation." />
                </label>
                <select value={data.measurement_unit} onChange={(e) => setData('measurement_unit', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    {Object.entries(MEASUREMENT_UNIT_LABELS).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <label className="text-xs font-medium text-slate-600 mb-1 inline-flex items-center gap-1">
                    Direction
                    <InfoTooltip text="Whether hitting the target means going up or down — e.g. revenue is higher-is-better, operating cost is usually lower-is-better." />
                </label>
                <select
                    value={data.measurement_direction}
                    onChange={(e) => setData('measurement_direction', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    {Object.entries(MEASUREMENT_DIRECTION_LABELS).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <label className="text-xs font-medium text-slate-600 mb-1 inline-flex items-center gap-1">
                    Weightage
                    <InfoTooltip text="This KPI's share of its group (its parent KPI, or its company goal if it has no parent). The system rejects a group total over 100%." />
                </label>
                <input
                    value={data.weightage}
                    onChange={(e) => setData('weightage', e.target.value)}
                    type="number"
                    step="any"
                    min={0}
                    max={100}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Optional, %"
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

            <div className="col-span-2 border-t border-slate-200 pt-3 mt-1">
                <p className="text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Cascade — where this fits</p>
            </div>
            <div>
                <label className="text-xs font-medium text-slate-600 mb-1 inline-flex items-center gap-1">
                    Company goal
                    <InfoTooltip text="Links this as a top-level Company KPI contributing to a Company Goal. Leave blank for a KPI without a direct goal link." />
                </label>
                <select value={data.company_goal_id} onChange={(e) => setData('company_goal_id', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">None</option>
                    {goals.map((g) => (
                        <option key={g.id} value={g.id}>
                            {g.title}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <label className="text-xs font-medium text-slate-600 mb-1 inline-flex items-center gap-1">
                    Parent KPI
                    <InfoTooltip text="Makes this a Department or Individual KPI that rolls up into a higher-level KPI — e.g. a Sales team's revenue KPI rolling up into the company-wide revenue KPI." />
                </label>
                <select value={data.parent_kpi_id} onChange={(e) => setData('parent_kpi_id', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">None — top-level KPI</option>
                    {kpiOptions.map((k) => (
                        <option key={k.id} value={k.id}>
                            {k.name}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Owning department</label>
                <select value={data.department_id} onChange={(e) => setData('department_id', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">None</option>
                    {departments.map((d) => (
                        <option key={d.id} value={d.id}>
                            {d.name}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Owner</label>
                <select value={data.owner_user_id} onChange={(e) => setData('owner_user_id', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Unassigned</option>
                    {members.map((m) => (
                        <option key={m.user_id} value={m.user_id}>
                            {m.users.name}
                        </option>
                    ))}
                </select>
            </div>
        </>
    );
}

function CreateKpiPanel({
    companyId,
    categories,
    goals,
    departments,
    members,
    kpis,
}: {
    companyId: string;
    categories: Category[];
    goals: Goal[];
    departments: Department[];
    members: Member[];
    kpis: Kpi[];
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm<KpiFormData>({
        category_id: '',
        name: '',
        description: '',
        target: '',
        unit: '',
        frequency: 'monthly',
        visibility: 'company',
        company_goal_id: '',
        parent_kpi_id: '',
        department_id: '',
        owner_user_id: '',
        measurement_unit: 'number',
        measurement_direction: 'higher_is_better',
        stretch_target: '',
        weightage: '',
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
            <KpiFormFields data={data} setData={setData} categories={categories} goals={goals} departments={departments} members={members} kpiOptions={kpis} />
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

function EditKpiForm({
    companyId,
    kpi,
    categories,
    goals,
    departments,
    members,
    kpis,
    onDone,
}: {
    companyId: string;
    kpi: Kpi;
    categories: Category[];
    goals: Goal[];
    departments: Department[];
    members: Member[];
    kpis: Kpi[];
    onDone: () => void;
}) {
    const { data, setData, patch, processing } = useForm<KpiFormData>({
        category_id: kpi.kpi_categories ? categories.find((c) => c.name === kpi.kpi_categories?.name)?.id ?? '' : '',
        name: kpi.name,
        description: kpi.description ?? '',
        target: kpi.target !== null ? String(kpi.target) : '',
        unit: kpi.unit ?? '',
        frequency: kpi.frequency,
        visibility: kpi.visibility,
        company_goal_id: kpi.company_goal_id ?? '',
        parent_kpi_id: kpi.parent_kpi_id ?? '',
        department_id: kpi.department_id ?? '',
        owner_user_id: kpi.owner_user_id ?? '',
        measurement_unit: kpi.measurement_unit ?? 'number',
        measurement_direction: kpi.measurement_direction ?? 'higher_is_better',
        stretch_target: kpi.stretch_target !== null ? String(kpi.stretch_target) : '',
        weightage: kpi.weightage !== null ? String(kpi.weightage) : '',
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
                goals={goals}
                departments={departments}
                members={members}
                kpiOptions={kpis.filter((k) => k.id !== kpi.id)}
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

function PeriodTargetForm({ companyId, kpi, onDone }: { companyId: string; kpi: Kpi; onDone: () => void }) {
    const currentYear = new Date().getFullYear();
    const [financialYear, setFinancialYear] = useState(currentYear);
    const [values, setValues] = useState<string[]>(['', '', '', '']);
    const [processing, setProcessing] = useState(false);

    const allocated = values.reduce((sum, v) => sum + (parseFloat(v) || 0), 0);
    const remaining = kpi.target !== null ? kpi.target - allocated : null;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.post(
            `/platform/companies/${companyId}/kpis/${kpi.id}/period-targets`,
            {
                financial_year: financialYear,
                period_type: 'quarter',
                targets: { 1: values[0] || '0', 2: values[1] || '0', 3: values[2] || '0', 4: values[3] || '0' },
            },
            { onFinish: () => setProcessing(false), onSuccess: onDone, preserveScroll: true },
        );
    };

    return (
        <form onSubmit={submit} className="mt-3 bg-slate-50 rounded-xl p-4">
            <div className="flex items-center gap-2 mb-2">
                <label className="text-xs font-medium text-slate-600">Financial year</label>
                <input
                    type="number"
                    value={financialYear}
                    onChange={(e) => setFinancialYear(parseInt(e.target.value, 10) || currentYear)}
                    className="w-24 rounded-lg border border-slate-300 px-2 py-1 text-xs"
                />
            </div>
            <div className="grid grid-cols-4 gap-2">
                {['Q1', 'Q2', 'Q3', 'Q4'].map((label, i) => (
                    <div key={label}>
                        <label className="block text-xs font-medium text-slate-600 mb-1">{label}</label>
                        <input
                            type="number"
                            step="any"
                            value={values[i]}
                            onChange={(e) => setValues((v) => v.map((existing, idx) => (idx === i ? e.target.value : existing)))}
                            className="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                            placeholder="0"
                        />
                    </div>
                ))}
            </div>
            {kpi.target !== null && (
                <p className="text-xs text-slate-500 mt-2">
                    Annual target: {kpi.target} · Allocated: {allocated} · Remaining: {remaining}
                </p>
            )}
            <div className="mt-3 flex items-center gap-2">
                <PrimaryButton type="submit" disabled={processing}>
                    Save quarterly targets
                </PrimaryButton>
                <button type="button" onClick={onDone} className="text-sm text-slate-400">
                    Cancel
                </button>
            </div>
        </form>
    );
}

function KpiRow({
    kpi,
    company,
    categories,
    goals,
    grants,
    departments,
    members,
    kpis,
}: {
    kpi: Kpi;
    company: Company;
    categories: Category[];
    goals: Goal[];
    grants: Grant[];
    departments: Department[];
    members: Member[];
    kpis: Kpi[];
}) {
    const [editing, setEditing] = useState(false);
    const [settingPeriodTargets, setSettingPeriodTargets] = useState(false);

    const parentKpi = kpi.parent_kpi_id ? kpis.find((k) => k.id === kpi.parent_kpi_id) : null;
    const goal = kpi.company_goal_id ? goals.find((g) => g.id === kpi.company_goal_id) : null;
    const department = kpi.department_id ? departments.find((d) => d.id === kpi.department_id) : null;
    const owner = kpi.owner_user_id ? members.find((m) => m.user_id === kpi.owner_user_id) : null;
    const childCount = kpis.filter((k) => k.parent_kpi_id === kpi.id).length;

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
                                {kpi.stretch_target !== null && ` (stretch ${kpi.stretch_target})`}
                            </span>
                        )}
                        {kpi.weightage !== null && <Badge tone="neutral">{kpi.weightage}% weight</Badge>}
                        <Badge tone={VISIBILITY_TONE[kpi.visibility]}>{VISIBILITY_LABEL[kpi.visibility]}</Badge>
                    </div>
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5 text-xs text-slate-400">
                        {goal && <span>Contributes to goal: <span className="font-semibold text-slate-600">{goal.title}</span></span>}
                        {parentKpi && <span>Rolls up into: <span className="font-semibold text-slate-600">{parentKpi.name}</span></span>}
                        {childCount > 0 && <span>{childCount} contributing KPI{childCount === 1 ? '' : 's'}</span>}
                        {department && <span>Department: <span className="font-semibold text-slate-600">{department.name}</span></span>}
                        {owner && <span>Owner: <span className="font-semibold text-slate-600">{owner.users.name}</span></span>}
                    </div>
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1.5">
                        {kpi.computed_achievement !== null && (
                            <Badge tone="neutral">{Math.round(kpi.computed_achievement)}% achieved</Badge>
                        )}
                        {kpi.computed_status !== 'not_scored' && (
                            <Badge tone={PERFORMANCE_STATUS_TONE[kpi.computed_status]}>{PERFORMANCE_STATUS_LABELS[kpi.computed_status]}</Badge>
                        )}
                        {kpi.rollup_achievement !== null && (
                            <Badge tone="info">{Math.round(kpi.rollup_achievement)}% roll-up from contributors</Badge>
                        )}
                        <button onClick={() => setSettingPeriodTargets((v) => !v)} className="text-xs font-semibold text-brand-800 hover:underline">
                            {settingPeriodTargets ? 'Close' : 'Set quarterly targets'}
                        </button>
                    </div>
                </div>
                <div className="flex-none flex items-center gap-3">
                    <button onClick={() => setEditing((v) => !v)} className="text-xs font-semibold text-brand-800 hover:underline">
                        {editing ? 'Close' : 'Edit'}
                    </button>
                    <Badge tone={kpi.status === 'active' ? 'success' : 'neutral'}>{kpi.status}</Badge>
                </div>
            </div>
            {settingPeriodTargets && <PeriodTargetForm companyId={company.id} kpi={kpi} onDone={() => setSettingPeriodTargets(false)} />}
            {editing && (
                <EditKpiForm
                    companyId={company.id}
                    kpi={kpi}
                    categories={categories}
                    goals={goals}
                    departments={departments}
                    members={members}
                    kpis={kpis}
                    onDone={() => setEditing(false)}
                />
            )}
            <KpiVisibilityGrants kpi={kpi} companyId={company.id} grants={grants} departments={departments} members={members} />
        </li>
    );
}

function RequestTargetRevisionForm({ companyId, kpis }: { companyId: string; kpis: Kpi[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset, errors } = useForm({
        kpi_id: kpis[0]?.id ?? '',
        new_target: '',
        reason: '',
        effective_financial_year: new Date().getFullYear(),
    });

    if (!open) {
        return (
            <SecondaryButton type="button" onClick={() => setOpen(true)} className="mb-4">
                Request Target Revision
            </SecondaryButton>
        );
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/kpis/${data.kpi_id}/target-revisions`, {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <form onSubmit={submit} className="grid grid-cols-2 gap-3 mb-6 bg-slate-50 rounded-xl p-4">
            <div className="col-span-2">
                <label className="block text-xs font-medium text-slate-600 mb-1">
                    KPI <InfoTooltip text="A target change doesn't take effect immediately — it stays proposed until whoever's assigned to approve it decides. Calculations keep using the current target until then." />
                </label>
                <select value={data.kpi_id} onChange={(e) => setData('kpi_id', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    {kpis.map((k) => (
                        <option key={k.id} value={k.id}>
                            {k.name} (current target {k.target ?? '—'})
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Proposed new target</label>
                <input value={data.new_target} onChange={(e) => setData('new_target', e.target.value)} type="number" step="any" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
                {errors.new_target && <p className="text-xs text-red-600 mt-1">{errors.new_target}</p>}
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Effective financial year</label>
                <input
                    value={data.effective_financial_year}
                    onChange={(e) => setData('effective_financial_year', Number(e.target.value))}
                    type="number"
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    required
                />
            </div>
            <div className="col-span-2">
                <label className="block text-xs font-medium text-slate-600 mb-1">Reason</label>
                <input value={data.reason} onChange={(e) => setData('reason', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
                {errors.reason && <p className="text-xs text-red-600 mt-1">{errors.reason}</p>}
            </div>
            <div className="col-span-2 flex items-center gap-2">
                <PrimaryButton type="submit" disabled={processing}>
                    Submit for approval
                </PrimaryButton>
                <SecondaryButton type="button" onClick={() => setOpen(false)}>
                    Cancel
                </SecondaryButton>
            </div>
        </form>
    );
}

function PendingTargetRevisionsCard({ revisions, kpis }: { revisions: PendingTargetRevision[]; kpis: Kpi[] }) {
    if (revisions.length === 0) {
        return null;
    }

    return (
        <Card title="Pending target revisions" description="Proposed changes awaiting approval — current targets stay in effect until then." className="mb-4">
            <ul className="divide-y divide-slate-100">
                {revisions.map((r) => {
                    const kpi = kpis.find((k) => k.id === r.kpi_id);
                    return (
                        <li key={r.id} className="py-2.5 flex items-center justify-between gap-4">
                            <div>
                                <p className="text-sm font-semibold text-slate-800">
                                    {kpi?.name ?? 'KPI'}: {r.old_target ?? '—'} → {r.new_target}
                                </p>
                                <p className="text-xs text-slate-400">
                                    {r.reason} · requested by {r.users?.name ?? 'someone'} · effective FY{r.effective_financial_year}
                                </p>
                            </div>
                            <Badge tone="warning">Awaiting approval</Badge>
                        </li>
                    );
                })}
            </ul>
        </Card>
    );
}

export default function KpisIndex({ company, categories, kpis, templates, templateItems, grants, goals, departments, members, pendingTargetRevisions }: KpisPageProps) {
    return (
        <PlatformLayout
            title="KPIs"
            description="The metrics this company tracks — what's measured, how often, who's expected to report against it, and how it rolls up into company goals."
            company={company}
        >
            <PendingTargetRevisionsCard revisions={pendingTargetRevisions} kpis={kpis} />
            <Card>
                <div className="flex flex-wrap items-center justify-between gap-3 mb-2">
                    <CreateKpiPanel companyId={company.id} categories={categories} goals={goals} departments={departments} members={members} kpis={kpis} />
                </div>
                <ApplyTemplateForm companyId={company.id} templates={templates} templateItems={templateItems} />
                <CreateCategoryForm companyId={company.id} />
                {kpis.length > 0 && <RequestTargetRevisionForm companyId={company.id} kpis={kpis} />}

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
                                goals={goals}
                                grants={grants.filter((g) => g.kpi_id === kpi.id)}
                                departments={departments}
                                members={members}
                                kpis={kpis}
                            />
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
