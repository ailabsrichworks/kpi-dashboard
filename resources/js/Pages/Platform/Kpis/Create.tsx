import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler, ReactNode, useMemo, useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { InfoTooltip, PrimaryButton } from '@/Components/Platform/ui';
import { ArrowLeftIcon, CheckCircleIcon, ExclamationTriangleIcon, LinkIcon, SparklesIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface Category {
    id: string;
    name: string;
}

interface Member {
    user_id: string;
    users: { name: string; email: string };
}

interface IncomingLinkage {
    id: string;
    category_id: string;
    unit: string | null;
    assigned_target: number;
    covered: number;
    gap: number;
    pct: number;
    met: boolean;
    kpi_categories?: { name: string };
    assigner?: { name: string };
}

interface CreateKpiPageProps {
    company: Company;
    categories: Category[];
    members: Member[];
    isAdmin: boolean;
    incomingLinkages: IncomingLinkage[];
    [key: string]: unknown;
}

const QUARTERS: Array<'Q1' | 'Q2' | 'Q3' | 'Q4'> = ['Q1', 'Q2', 'Q3', 'Q4'];

/**
 * One numbered step, matching legacy `kpi/create.blade.php`'s own
 * section-card pattern (a numbered badge + title + description, a colored
 * accent bar, content below) — adapted to the Platform's own design tokens
 * (Card/brand-900/gold accent) rather than reintroducing legacy's per-tenant
 * theme CSS variables, which the Platform's UI redesign deliberately never
 * adopted.
 */
function Step({ number, title, description, children }: { number: number; title: string; description: string; children: ReactNode }) {
    return (
        <section className="relative bg-white rounded-2xl shadow-sm border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] p-5">
            <div className="flex items-center gap-4">
                <div className="w-10 h-10 rounded-2xl bg-brand-900 text-white flex items-center justify-center font-black shadow-lg shrink-0">
                    {number}
                </div>
                <div>
                    <h3 className="font-black text-slate-900 text-base">{title}</h3>
                    <p className="text-xs text-slate-500 mt-0.5">{description}</p>
                </div>
            </div>
            <div className="mt-5">{children}</div>
        </section>
    );
}

function Field({ label, tooltip, className = '', children }: { label: string; tooltip?: string; className?: string; children: ReactNode }) {
    return (
        <div className={className}>
            <label className="text-xs font-bold text-slate-600 mb-1 inline-flex items-center gap-1">
                {label}
                {tooltip && <InfoTooltip text={tooltip} />}
            </label>
            {children}
        </div>
    );
}

const inputClass = 'w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm mt-1';

function AddCategoryForm({ companyId }: { companyId: string }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ name: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/kpi-categories`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    if (!open) {
        return (
            <button type="button" onClick={() => setOpen(true)} className="text-xs font-semibold text-brand-800 hover:underline mt-3">
                + Add a category
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="flex items-end gap-2 mt-3">
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

/**
 * Matches legacy's own category-as-cards selector (`kpi/create.blade.php`
 * §2) visually — hidden radio semantics, clickable card, accent border when
 * selected — but sourced from the company's own real, admin-configurable
 * `kpi_categories` instead of legacy's four hard-coded names. Legacy's fixed
 * two-level category/sub_category taxonomy has no Platform equivalent and
 * isn't ported: the Platform deliberately replaced it with a flexible,
 * per-company category list in an earlier pass, and reintroducing a second,
 * hard-coded taxonomy on top of it would undo that.
 */
function CategoryCards({ categories, value, onChange }: { categories: Category[]; value: string; onChange: (id: string) => void }) {
    if (categories.length === 0) {
        return <p className="text-xs text-slate-400">No categories yet — add one below.</p>;
    }

    return (
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
            {categories.map((c) => {
                const active = value === c.id;
                return (
                    <button
                        key={c.id}
                        type="button"
                        onClick={() => onChange(c.id)}
                        className={`text-left rounded-xl border px-3 py-2.5 text-xs font-bold transition ${
                            active ? 'border-[#D4AF37] bg-[#D4AF37]/10 text-brand-900 shadow-sm' : 'border-slate-200 text-slate-600 hover:border-slate-300'
                        }`}
                    >
                        {c.name}
                    </button>
                );
            })}
        </div>
    );
}

function fmtLinkVal(value: number, unit: string | null): string {
    const u = (unit ?? '').toLowerCase();
    if (u === 'currency' || u === 'rm' || u === '$') return `RM ${value.toLocaleString()}`;
    if (u === 'percentage' || u === '%') return `${value}%`;
    return value.toLocaleString();
}

/**
 * The cascading-target warning banner — matches legacy's own
 * `#linkageWarning` exactly (coverage progress bar, target/covered/gap
 * stats, "still OK to create" footer either way, never blocking submit).
 * Only shown when the currently-selected category has an incoming linkage
 * (`kpi_target_linkages`, keyed by `category_id`+`unit` — see
 * ComputesLinkageCoverage, which fixes a real legacy bug where coverage was
 * computed without the unit, silently showing 0 for non-number linkages).
 */
function LinkageBanner({ linkage }: { linkage: IncomingLinkage }) {
    return (
        <div className={`mt-4 rounded-2xl border p-4 ${linkage.met ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'}`}>
            <div className="flex items-start gap-2">
                <LinkIcon className={`w-4 h-4 shrink-0 mt-0.5 ${linkage.met ? 'text-emerald-600' : 'text-amber-600'}`} />
                <div className="flex-1 min-w-0">
                    <p className="text-xs font-black text-slate-800">
                        Cascading Target from {linkage.assigner?.name ?? 'your manager'}
                    </p>
                    <p className="text-[11px] text-slate-500 mt-0.5">
                        {linkage.kpi_categories?.name ?? 'This category'} — Annual target: {fmtLinkVal(linkage.assigned_target, linkage.unit)}
                    </p>

                    <div className="h-1.5 rounded-full bg-white border border-slate-200 overflow-hidden mt-2.5">
                        <div
                            className={`h-full rounded-full ${linkage.met ? 'bg-emerald-500' : 'bg-amber-500'}`}
                            style={{ width: `${Math.min(100, linkage.pct)}%` }}
                        />
                    </div>
                    <p className="text-[10px] text-slate-500 mt-1">Coverage Progress — {linkage.pct}%</p>

                    <div className="grid grid-cols-3 gap-2 mt-2.5">
                        <div className="rounded-lg bg-white border border-slate-200 px-2 py-1.5">
                            <p className="text-[9px] text-slate-400">Target from Manager</p>
                            <p className="text-xs font-black text-slate-800">{fmtLinkVal(linkage.assigned_target, linkage.unit)}</p>
                        </div>
                        <div className="rounded-lg bg-white border border-slate-200 px-2 py-1.5">
                            <p className="text-[9px] text-slate-400">Covered by Your KPIs</p>
                            <p className="text-xs font-black text-slate-800">{fmtLinkVal(linkage.covered, linkage.unit)}</p>
                        </div>
                        {!linkage.met && (
                            <div className="rounded-lg bg-white border border-slate-200 px-2 py-1.5">
                                <p className="text-[9px] text-slate-400">Still Short</p>
                                <p className="text-xs font-black text-amber-700">{fmtLinkVal(linkage.gap, linkage.unit)}</p>
                            </div>
                        )}
                    </div>

                    <p className="text-[10px] text-slate-500 mt-2.5">
                        {linkage.met
                            ? '✓ Target has been fully covered by your existing KPIs.'
                            : '💡 This KPI will count toward covering the gap above — you can still create it either way.'}
                    </p>
                </div>
            </div>
        </div>
    );
}

type AiScoreState = { status: 'idle' } | { status: 'loading' } | { status: 'error'; message: string } | { status: 'done'; score: number; feedback: string };

/**
 * The ANIRA score card — matches legacy's own advisory-only "Score" button
 * exactly: button-triggered (never automatic/on-blur), requires title +
 * description first, never stored and never required to submit. Reuses the
 * same `AiService::scoreKpiDescription()` legacy's own AiController calls,
 * via the Platform's own `scoreDescription` endpoint.
 */
function AniraScoreCard({
    companyId,
    title,
    description,
    target,
    unit,
    weight,
    categoryName,
    quarterTargets,
}: {
    companyId: string;
    title: string;
    description: string;
    target: string;
    unit: string;
    weight: string;
    categoryName: string | undefined;
    quarterTargets: Record<'Q1' | 'Q2' | 'Q3' | 'Q4', string>;
}) {
    const [state, setState] = useState<AiScoreState>({ status: 'idle' });

    const score = async () => {
        if (!title.trim() || !description.trim()) {
            setState({ status: 'error', message: 'Fill in at least the KPI title and description first.' });
            return;
        }

        setState({ status: 'loading' });

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch(`/platform/companies/${companyId}/kpis/score-description`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                body: JSON.stringify({
                    kpi_title: title,
                    kpi_description: description,
                    target: target || null,
                    unit: unit || null,
                    weight: weight || null,
                    category: categoryName ?? null,
                    quarter_targets: QUARTERS.map((q) => quarterTargets[q] || 0),
                }),
            });
            const json = await res.json();
            if (!res.ok || !json.success) {
                setState({ status: 'error', message: json.message ?? 'Scoring failed. Please try again.' });
                return;
            }
            setState({ status: 'done', score: json.score, feedback: json.feedback });
        } catch {
            setState({ status: 'error', message: 'Scoring failed. Please try again.' });
        }
    };

    const tileColor =
        state.status === 'done' ? (state.score >= 8 ? 'bg-violet-600' : state.score >= 5 ? 'bg-amber-500' : 'bg-red-500') : 'bg-slate-200';

    return (
        <div className="mt-2.5 rounded-xl border border-slate-200 px-3 py-2.5">
            <div className="flex items-center justify-between gap-2">
                <p className="text-[10px] font-black text-slate-500 inline-flex items-center gap-1">
                    <SparklesIcon className="w-3.5 h-3.5 text-violet-500" /> ANIRA Score
                </p>
                <button
                    type="button"
                    onClick={score}
                    disabled={state.status === 'loading'}
                    className="text-[10px] font-black text-violet-700 bg-violet-50 border border-violet-200 px-2 py-1 rounded-lg hover:bg-violet-100 transition disabled:opacity-50"
                >
                    {state.status === 'loading' ? 'Scoring...' : 'Score'}
                </button>
            </div>

            {state.status === 'error' && <p className="text-[10px] text-red-600 mt-2">{state.message}</p>}

            {state.status === 'done' && (
                <div className="flex items-center gap-2 mt-2">
                    <div className={`w-9 h-9 rounded-xl ${tileColor} text-white flex items-center justify-center font-black text-sm shrink-0`}>
                        {state.score}
                    </div>
                    <p className="text-[11px] text-slate-600 leading-snug">{state.feedback}</p>
                </div>
            )}
        </div>
    );
}

export default function CreateKpi({ company, categories, members, isAdmin, incomingLinkages }: CreateKpiPageProps) {
    const { data, setData, post, processing, errors } = useForm({
        category_id: '',
        name: '',
        description: '',
        target: '',
        unit: '',
        weight: '',
        assigned_user_id: '',
        frequency: 'monthly',
        visibility: 'company',
        quarter_targets: { Q1: '', Q2: '', Q3: '', Q4: '' } as Record<'Q1' | 'Q2' | 'Q3' | 'Q4', string>,
    });

    const setQuarterTarget = (quarter: 'Q1' | 'Q2' | 'Q3' | 'Q4', value: string) => {
        setData('quarter_targets', { ...data.quarter_targets, [quarter]: value });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${company.id}/kpis`);
    };

    const isPercentageUnit = /%|percent/i.test(data.unit);

    const quarterTotal = useMemo(() => {
        const values = QUARTERS.map((q) => parseFloat(data.quarter_targets[q]) || 0);
        return isPercentageUnit ? values.reduce((a, b) => a + b, 0) / 4 : values.reduce((a, b) => a + b, 0);
    }, [data.quarter_targets, isPercentageUnit]);

    const targetNum = parseFloat(data.target) || 0;
    const quarterTotalMatches = targetNum === 0 || Math.abs(quarterTotal - targetNum) < 0.01;

    const autoFillAnnual = () => {
        if (!data.target) return;
        const perQuarter = isPercentageUnit ? data.target : (parseFloat(data.target) / 4).toString();
        setData('quarter_targets', { Q1: perQuarter, Q2: perQuarter, Q3: perQuarter, Q4: perQuarter });
    };

    const owner = members.find((m) => m.user_id === data.assigned_user_id);
    const categoryName = categories.find((c) => c.id === data.category_id)?.name;
    const activeLinkage = incomingLinkages.find((l) => l.category_id === data.category_id);

    return (
        <PlatformLayout
            title="Create KPI"
            description="Define a new metric this company tracks — what's measured, how often, who owns it, and who can see it."
            company={company}
        >
            <Link
                href={`/platform/companies/${company.id}/kpis`}
                className="inline-flex items-center gap-1.5 text-xs font-bold text-brand-800 hover:underline mb-4"
            >
                <ArrowLeftIcon className="w-3.5 h-3.5" /> Back to KPI List
            </Link>

            <form onSubmit={submit}>
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                    <div className="lg:col-span-8 space-y-5">
                        {isAdmin ? (
                            <Step number={1} title="Ownership &amp; Visibility" description="Who is responsible for this KPI, and who else is allowed to see it.">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <Field label="Assign to" tooltip="Optional. Makes this one person's KPI on their own Weightage page, where they allocate its weight themselves.">
                                        <select value={data.assigned_user_id} onChange={(e) => setData('assigned_user_id', e.target.value)} className={inputClass}>
                                            <option value="">Not assigned to anyone</option>
                                            {members.map((m) => (
                                                <option key={m.user_id} value={m.user_id}>
                                                    {m.users.name} ({m.users.email})
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Field label="Who can see this?" tooltip="Company-wide: any signed-in member. Submitting departments: only the departments that report against it, plus admins/SLT. Restricted: nobody, until access is granted explicitly after creation.">
                                        <select value={data.visibility} onChange={(e) => setData('visibility', e.target.value)} className={inputClass}>
                                            <option value="company">Company-wide — any member can see it</option>
                                            <option value="department">Submitting departments — plus admins/SLT</option>
                                            <option value="restricted">Restricted — grant access explicitly after creating</option>
                                        </select>
                                    </Field>
                                </div>
                            </Step>
                        ) : (
                            <Step number={1} title="Ownership" description="This KPI will be created under your name and visible to everyone in your company.">
                                <p className="text-xs text-slate-500">
                                    Only a Company Admin can assign a KPI to someone else or restrict who can see it. To allocate weight or share ownership later, ask your admin.
                                </p>
                            </Step>
                        )}

                        <Step number={2} title="Category" description="Groups this KPI with related ones on the KPI List and dashboards.">
                            <CategoryCards categories={categories} value={data.category_id} onChange={(id) => setData('category_id', id)} />
                            {isAdmin && <AddCategoryForm companyId={company.id} />}
                            {activeLinkage && <LinkageBanner linkage={activeLinkage} />}
                        </Step>

                        <Step number={3} title="KPI Details" description="What this KPI is called, and what it actually measures.">
                            <div className="space-y-4">
                                <Field label="KPI name">
                                    <input
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className={inputClass}
                                        placeholder="Customer Satisfaction Score"
                                        required
                                    />
                                    {errors.name && <p className="text-xs text-red-600 mt-1">{errors.name}</p>}
                                </Field>
                                <Field label="Description">
                                    <textarea
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        className={inputClass}
                                        rows={3}
                                        placeholder="What does hitting this target actually mean?"
                                    />
                                </Field>
                            </div>
                        </Step>

                        <Step number={4} title="Target &amp; Frequency" description="The number to reach, how it's weighted, and how often it's reported.">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <Field label="Target" tooltip="The number someone needs to reach for 100% achievement. Leave blank if this KPI isn't measured against a fixed number.">
                                    <input value={data.target} onChange={(e) => setData('target', e.target.value)} type="number" step="any" className={inputClass} placeholder="90" />
                                </Field>
                                <Field label="Unit">
                                    <input value={data.unit} onChange={(e) => setData('unit', e.target.value)} className={inputClass} placeholder="%, $, calls…" />
                                </Field>
                                <Field label="Weight" tooltip="How much this KPI counts toward the overall weighted score, as a share of 100 across all of this company's KPIs.">
                                    <input
                                        value={data.weight}
                                        onChange={(e) => setData('weight', e.target.value)}
                                        type="number"
                                        step="any"
                                        min="0"
                                        max="100"
                                        className={inputClass}
                                        placeholder="e.g. 20"
                                    />
                                </Field>
                            </div>
                            <Field label="How often is it reported?" className="mt-4">
                                <select value={data.frequency} onChange={(e) => setData('frequency', e.target.value)} className={`${inputClass} md:w-1/3`}>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </Field>
                            {data.frequency === 'quarterly' && (
                                <Field
                                    label="Quarterly targets"
                                    tooltip="Set independently per quarter — they don't have to add up to the overall Target above. Leave a quarter blank to keep it at 0 for now."
                                    className="mt-4"
                                >
                                    <div className="flex items-center justify-between mb-1.5">
                                        <span />
                                        <button
                                            type="button"
                                            onClick={autoFillAnnual}
                                            disabled={!data.target}
                                            className="text-[10px] font-black text-brand-800 hover:underline disabled:opacity-40"
                                        >
                                            Auto Fill from Target
                                        </button>
                                    </div>
                                    <div className="grid grid-cols-4 gap-2">
                                        {QUARTERS.map((q) => (
                                            <div key={q}>
                                                <label className="block text-[10px] font-semibold text-slate-400 mb-0.5">{q}</label>
                                                <input
                                                    value={data.quarter_targets[q]}
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
                                    {data.target && (
                                        <div className="flex items-center gap-1.5 mt-2">
                                            {quarterTotalMatches ? (
                                                <CheckCircleIcon className="w-3.5 h-3.5 text-emerald-600" />
                                            ) : (
                                                <ExclamationTriangleIcon className="w-3.5 h-3.5 text-amber-500" />
                                            )}
                                            <p className={`text-[10px] font-bold ${quarterTotalMatches ? 'text-emerald-700' : 'text-amber-600'}`}>
                                                {isPercentageUnit ? 'Average' : 'Total'}: {quarterTotal.toFixed(2)}
                                                {quarterTotalMatches ? ' — matches Target' : ` — Target is ${data.target}`}
                                            </p>
                                        </div>
                                    )}
                                </Field>
                            )}
                        </Step>
                    </div>

                    <aside className="lg:col-span-4">
                        <div className="sticky top-20 rounded-[28px] border border-[#E5E7EB] bg-white p-4 shadow-[0_20px_50px_rgba(15,23,42,0.08)]">
                            <div className="flex items-center gap-3">
                                <div className="w-9 h-9 rounded-xl bg-brand-900 text-white flex items-center justify-center font-black shadow-lg shrink-0">
                                    <CheckCircleIcon className="w-5 h-5" />
                                </div>
                                <div>
                                    <h2 className="font-black text-sm text-slate-900">KPI Summary</h2>
                                    <p className="text-[11px] text-slate-500">Review before you submit.</p>
                                </div>
                            </div>

                            <div className="h-px bg-slate-200 my-3" />

                            <div className="rounded-xl bg-slate-50 border border-slate-200 px-3 py-2">
                                <p className="text-[10px] text-slate-400">Owner</p>
                                <p className="font-black text-sm text-slate-800 mt-0.5">{isAdmin ? (owner ? owner.users.name : 'Not assigned yet') : 'You'}</p>
                            </div>

                            <div className="mt-2.5">
                                <p className="text-[10px] text-slate-400">KPI Title</p>
                                <p className="font-black text-sm text-slate-800 mt-0.5 line-clamp-2">{data.name || 'Not entered yet'}</p>
                            </div>

                            <div className="grid grid-cols-2 gap-2 mt-2.5">
                                <div className="rounded-xl border border-slate-200 px-2.5 py-2">
                                    <p className="text-[10px] text-slate-400">Category</p>
                                    <p className="font-bold text-sm text-slate-800 mt-0.5">{categoryName ?? '—'}</p>
                                </div>
                                <div className="rounded-xl border border-slate-200 px-2.5 py-2">
                                    <p className="text-[10px] text-slate-400">Frequency</p>
                                    <p className="font-bold text-sm text-slate-800 mt-0.5 capitalize">{data.frequency}</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-2 mt-2.5">
                                <div className="rounded-xl border border-slate-200 bg-white px-3 py-2">
                                    <p className="text-[10px] text-slate-400">Target</p>
                                    <p className="font-black text-base text-[#7A0019] mt-0.5">
                                        {data.target ? `${data.target}${data.unit}` : '—'}
                                    </p>
                                </div>
                                <div className="rounded-xl border border-slate-200 bg-white px-3 py-2">
                                    <p className="text-[10px] text-slate-400">Weight</p>
                                    <p className="font-black text-base text-[#7A0019] mt-0.5">{data.weight ? `${data.weight}%` : '—'}</p>
                                </div>
                            </div>

                            {data.frequency === 'quarterly' && (
                                <div className="mt-2.5 rounded-xl border border-slate-200 px-3 py-2">
                                    <p className="text-[10px] text-slate-400">{isPercentageUnit ? 'Quarter Target Average' : 'Quarter Target Total'}</p>
                                    <p className={`text-base font-black mt-0.5 ${quarterTotalMatches ? 'text-[#7A0019]' : 'text-amber-600'}`}>
                                        {quarterTotal.toFixed(2)}
                                    </p>
                                </div>
                            )}

                            {activeLinkage && (
                                <div className="mt-2.5 rounded-xl border border-slate-200 px-3 py-2">
                                    <p className="text-[10px] text-slate-400 inline-flex items-center gap-1">
                                        <LinkIcon className="w-3 h-3" /> Linked Target
                                    </p>
                                    <p className="text-xs font-bold text-slate-700 mt-0.5">
                                        {fmtLinkVal(activeLinkage.covered, activeLinkage.unit)} / {fmtLinkVal(activeLinkage.assigned_target, activeLinkage.unit)}
                                    </p>
                                    <div className="h-1 rounded-full bg-slate-100 overflow-hidden mt-1">
                                        <div
                                            className={`h-full rounded-full ${activeLinkage.met ? 'bg-emerald-500' : 'bg-amber-500'}`}
                                            style={{ width: `${Math.min(100, activeLinkage.pct)}%` }}
                                        />
                                    </div>
                                    <p className="text-[9px] text-slate-400 mt-1">{activeLinkage.pct}% covered{!activeLinkage.met ? ` · Gap ${fmtLinkVal(activeLinkage.gap, activeLinkage.unit)}` : ''}</p>
                                </div>
                            )}

                            <AniraScoreCard
                                companyId={company.id}
                                title={data.name}
                                description={data.description}
                                target={data.target}
                                unit={data.unit}
                                weight={data.weight}
                                categoryName={categoryName}
                                quarterTargets={data.quarter_targets}
                            />

                            <div className="mt-3">
                                <PrimaryButton type="submit" disabled={processing} className="w-full py-3 rounded-xl text-center">
                                    Create KPI
                                </PrimaryButton>
                                <Link
                                    href={`/platform/companies/${company.id}/kpis`}
                                    className="block text-center text-xs text-slate-400 hover:text-slate-600 mt-2"
                                >
                                    Cancel
                                </Link>
                            </div>
                        </div>
                    </aside>
                </div>
            </form>
        </PlatformLayout>
    );
}
