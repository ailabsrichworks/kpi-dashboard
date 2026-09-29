import { Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, PrimaryButton, StatCard } from '@/Components/Platform/ui';
import { CheckCircleIcon, ExclamationTriangleIcon, RocketIcon, UsersIcon, TargetIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
    status: string;
    onboarding_status: string;
    display_name: string | null;
    primary_color: string | null;
    secondary_color: string | null;
}

interface Step {
    key: string;
    label: string;
    done: boolean;
    builtYet: boolean;
    href: string | null;
}

interface Counts {
    departments: number;
    users: number;
    kpis: number;
}

interface PendingEmployeeBatch {
    id: string;
    filename: string;
}

interface OnboardingPageProps {
    company: Company;
    steps: Step[];
    currentStepKey: string | null;
    counts: Counts;
    hasActiveAdmin: boolean;
    canActivate: boolean;
    pendingEmployeeBatch: PendingEmployeeBatch | null;
    [key: string]: unknown;
}

const STEP_HINT: Record<string, string> = {
    details: 'Set a display name and brand colors — optional, but shown to every user of this company once branded pages exist.',
    admin: 'Invite a Company Admin from the Companies page.',
    departments: 'Import departments from Excel/CSV, or add them by hand from the Departments page.',
    import_employees: 'Upload an Employees spreadsheet from the Import page.',
    validate_spreadsheet: 'The Import page validates every row before anything commits — fix any flagged rows and re-upload if needed.',
    create_users: 'Turn validated, staged employee rows into real accounts — select all or some.',
    assign_roles: 'Review who should be an Executive vs. a plain Employee, and adjust job-level roles per department.',
    kpi_structure: 'Add KPI categories and KPIs by hand from the KPIs page.',
    apply_kpi_template: "Or skip the manual work entirely — apply one of the Center's shared KPI templates.",
    review: 'Everything above in one place before going live.',
    activate: 'Makes the company LIVE — its users can sign in immediately.',
};

function CompanyDetailsForm({ company }: { company: Company }) {
    const { data, setData, post, processing } = useForm({
        display_name: company.display_name ?? '',
        primary_color: company.primary_color ?? '',
        secondary_color: company.secondary_color ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${company.id}/branding`);
    };

    return (
        <form onSubmit={submit} className="grid grid-cols-3 gap-3">
            <input
                value={data.display_name}
                onChange={(e) => setData('display_name', e.target.value)}
                placeholder="Display name"
                className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
            />
            <input
                value={data.primary_color}
                onChange={(e) => setData('primary_color', e.target.value)}
                placeholder="Primary color (#06142f)"
                className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
            />
            <input
                value={data.secondary_color}
                onChange={(e) => setData('secondary_color', e.target.value)}
                placeholder="Secondary color (#D4AF37)"
                className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
            />
            <PrimaryButton type="submit" disabled={processing} className="col-span-3">
                Save
            </PrimaryButton>
        </form>
    );
}

function StepRow({ step, isCurrent }: { step: Step; isCurrent: boolean }) {
    return (
        <li className={`flex items-start gap-3 py-3.5 ${isCurrent ? 'bg-sky-50 -mx-3 px-3 rounded-lg' : ''}`}>
            <span
                className={`mt-0.5 flex h-6 w-6 flex-none items-center justify-center rounded-full ${
                    step.done ? 'bg-emerald-100 text-emerald-700' : step.builtYet ? 'bg-slate-100 text-slate-400' : 'bg-slate-50 text-slate-300'
                }`}
            >
                {step.done ? <CheckCircleIcon className="w-4 h-4" /> : <span className="text-xs font-bold">·</span>}
            </span>
            <div className="flex-1">
                <p className={`text-sm font-semibold ${step.done ? 'text-slate-800' : step.builtYet ? 'text-slate-500' : 'text-slate-400'}`}>
                    {step.label}
                    {!step.builtYet && (
                        <span className="ml-2">
                            <Badge tone="warning">Not built yet</Badge>
                        </span>
                    )}
                </p>
                {!step.done && STEP_HINT[step.key] && <p className="text-xs text-slate-400 mt-0.5">{STEP_HINT[step.key]}</p>}
            </div>
            {step.href && (
                <Link href={step.href} className="text-xs font-semibold text-brand-800 hover:underline flex-none">
                    {step.builtYet ? (step.done ? 'Manage' : 'Set up') : 'View'}
                </Link>
            )}
        </li>
    );
}

export default function OnboardingShow({ company, steps, currentStepKey, counts, hasActiveAdmin, canActivate, pendingEmployeeBatch }: OnboardingPageProps) {
    const blockers: string[] = [];
    if (!hasActiveAdmin) blockers.push('This company has no active Company Admin yet.');

    const warnings: string[] = [];
    if (counts.departments === 0) warnings.push('No departments have been created yet.');
    if (counts.users === 0) warnings.push('No users besides the Company Admin have been added yet.');
    if (counts.kpis === 0) warnings.push('No KPIs have been configured yet.');

    const activate = () => {
        if (confirm(`Activate ${company.name}? Its users will be able to sign in immediately.`)) {
            router.post(`/platform/companies/${company.id}/activate`);
        }
    };

    const currentStep = steps.find((s) => s.key === currentStepKey);
    const doneCount = steps.filter((s) => s.done).length;

    return (
        <PlatformLayout title="Onboarding" description={`Setting up ${company.name} — step by step, nothing skipped.`} company={company} maxWidth="max-w-3xl">
            <div className="mb-5 flex items-center gap-3">
                <div className="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                    <div className="h-full bg-brand-900 transition-all" style={{ width: `${(doneCount / steps.length) * 100}%` }} />
                </div>
                <span className="text-xs font-semibold text-slate-500 flex-none">
                    {doneCount} of {steps.length} steps
                </span>
            </div>

            {currentStep && (
                <div className="mb-6 rounded-2xl bg-brand-900 text-white p-5 flex items-center justify-between">
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-300">Continue where you left off</p>
                        <p className="text-base font-bold">{currentStep.label}</p>
                    </div>
                    {currentStep.href && (
                        <Link href={currentStep.href} className="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-brand-900 hover:bg-slate-100">
                            Continue →
                        </Link>
                    )}
                </div>
            )}

            <Card title="Onboarding checklist" className="mb-6">
                <ul className="divide-y divide-slate-100">
                    {steps.map((step) => (
                        <li key={step.key}>
                            <StepRow step={step} isCurrent={step.key === currentStepKey} />
                            {step.key === 'details' && !step.done && (
                                <div className="pb-4 pl-9">
                                    <CompanyDetailsForm company={company} />
                                </div>
                            )}
                            {step.key === 'create_users' && pendingEmployeeBatch && !step.done && (
                                <p className="pl-9 pb-2 text-xs text-slate-400">
                                    &quot;{pendingEmployeeBatch.filename}&quot; has employees staged and ready for account creation.
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            </Card>

            <Card title="Review & activate">
                <dl className="grid grid-cols-3 gap-3 mb-4">
                    <StatCard label="Departments" value={counts.departments} icon={<UsersIcon className="w-3.5 h-3.5" />} />
                    <StatCard label="Users" value={counts.users} icon={<UsersIcon className="w-3.5 h-3.5" />} />
                    <StatCard label="KPIs" value={counts.kpis} icon={<TargetIcon className="w-3.5 h-3.5" />} />
                </dl>

                {blockers.length > 0 && (
                    <div className="mb-3 rounded-xl bg-red-50 border border-red-200 px-4 py-3">
                        {blockers.map((b, i) => (
                            <p key={i} className="text-xs text-red-700 flex items-center gap-1.5">
                                <ExclamationTriangleIcon className="w-3.5 h-3.5 flex-none" /> {b}
                            </p>
                        ))}
                    </div>
                )}

                {warnings.length > 0 && (
                    <div className="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3">
                        {warnings.map((w, i) => (
                            <p key={i} className="text-xs text-amber-700 flex items-center gap-1.5">
                                <ExclamationTriangleIcon className="w-3.5 h-3.5 flex-none" /> {w}
                            </p>
                        ))}
                    </div>
                )}

                {company.status === 'active' ? (
                    <p className="text-sm font-semibold text-emerald-600 inline-flex items-center gap-1.5">
                        <CheckCircleIcon className="w-4 h-4" /> This company is LIVE.
                    </p>
                ) : canActivate ? (
                    <PrimaryButton onClick={activate} disabled={blockers.length > 0} className="inline-flex items-center gap-1.5 px-5 py-2.5">
                        <RocketIcon className="w-4 h-4" /> Activate company
                    </PrimaryButton>
                ) : (
                    <p className="text-xs text-slate-400">Only a Richworks Super Admin can activate a company.</p>
                )}
            </Card>
        </PlatformLayout>
    );
}
