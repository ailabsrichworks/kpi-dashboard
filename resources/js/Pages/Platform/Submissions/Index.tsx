import { useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Card, EmptyState, InfoTooltip, PrimaryButton } from '@/Components/Platform/ui';
import { ClipboardCheckIcon } from '@/Components/Platform/Icons';

interface Department {
    id: string;
    name: string;
    code: string;
    company_id: string;
}

interface Kpi {
    id: string;
    name: string;
    target: number | null;
    unit: string | null;
    frequency: string;
}

interface Submission {
    id: string;
    value: number;
    submission_date: string;
    notes: string | null;
    kpis: { name: string; unit: string | null; target: number | null };
    users: { name: string };
}

interface PlatformUser {
    company_memberships: Array<{ company_id: string; companies?: { name: string; code: string } }>;
}

interface SubmissionsPageProps {
    department: Department;
    kpis: Kpi[];
    submissions: Submission[];
    canSubmit: boolean;
    [key: string]: unknown;
}

function SubmitForm({ companyId, departmentId, kpis }: { companyId: string; departmentId: string; kpis: Kpi[] }) {
    const today = new Date().toISOString().slice(0, 10);
    const { data, setData, post, processing, reset } = useForm({
        kpi_id: kpis[0]?.id ?? '',
        value: '',
        submission_date: today,
        notes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/departments/${departmentId}/submissions`, {
            onSuccess: () => setData('value', ''),
        });
    };

    if (kpis.length === 0) {
        return <EmptyState title="Nothing to report yet" description="Your admin hasn't set up any active KPIs for this department yet." />;
    }

    return (
        <form onSubmit={submit} className="grid grid-cols-2 gap-3 mb-6 bg-slate-50 rounded-xl p-4">
            <div className="col-span-2">
                <label className="block text-xs font-medium text-slate-600 mb-1">Which KPI are you reporting?</label>
                <select value={data.kpi_id} onChange={(e) => setData('kpi_id', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    {kpis.map((k) => (
                        <option key={k.id} value={k.id}>
                            {k.name} {k.target !== null ? `(target ${k.target}${k.unit ?? ''})` : ''}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Your value</label>
                <input value={data.value} onChange={(e) => setData('value', e.target.value)} type="number" step="any" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Date</label>
                <input value={data.submission_date} onChange={(e) => setData('submission_date', e.target.value)} type="date" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
            </div>
            <div className="col-span-2">
                <label className="block text-xs font-medium text-slate-600 mb-1">Notes (optional)</label>
                <input value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Any context worth adding?" />
            </div>
            <div className="col-span-2">
                <PrimaryButton type="submit" disabled={processing}>
                    Submit
                </PrimaryButton>
            </div>
        </form>
    );
}

/** Matches the legacy app's own achievement formula and the company_kpi_summary view: value/target*100, excluding submissions with no (or zero) target rather than showing null/Infinity. */
function achievementPct(submission: Submission): number | null {
    const target = submission.kpis.target;
    if (target === null || target === 0) {
        return null;
    }
    return (submission.value / target) * 100;
}

function AchievementBadge({ pct }: { pct: number | null }) {
    if (pct === null) {
        return null;
    }
    const color = pct >= 100 ? 'text-emerald-600' : pct >= 75 ? 'text-amber-600' : 'text-red-600';
    return <span className={`text-xs font-bold tabular-nums ${color}`}>{Math.round(pct)}%</span>;
}

function AchievementBar({ pct }: { pct: number | null }) {
    if (pct === null) {
        return null;
    }
    const color = pct >= 100 ? 'bg-emerald-500' : pct >= 75 ? 'bg-amber-500' : 'bg-red-500';
    return (
        <div className="h-1.5 w-24 rounded-full bg-slate-100 overflow-hidden">
            <div className={`h-full ${color}`} style={{ width: `${Math.min(pct, 100)}%` }} />
        </div>
    );
}

export default function SubmissionsIndex({ department, kpis, submissions, canSubmit }: SubmissionsPageProps) {
    const { platformUser } = usePage<{ platformUser: PlatformUser | null }>().props;
    const membership = platformUser?.company_memberships.find((m) => m.company_id === department.company_id);
    const company = {
        id: department.company_id,
        name: membership?.companies?.name ?? 'Company',
        code: membership?.companies?.code ?? '',
    };

    const scored = submissions.map((s) => achievementPct(s)).filter((p): p is number => p !== null);
    const avgAchievement = scored.length > 0 ? Math.round(scored.reduce((a, b) => a + b, 0) / scored.length) : null;

    return (
        <PlatformLayout
            title={`${department.name} — KPI Submissions`}
            description={avgAchievement !== null ? `Average achievement so far: ${avgAchievement}%` : 'Report your KPI values here as often as required.'}
            company={company}
        >
            <Card
                title={
                    <span className="inline-flex items-center gap-1.5">
                        Report a value
                        <InfoTooltip text="Achievement % = your value ÷ the KPI's target, shown so you can see at a glance whether you're on track." />
                    </span>
                }
            >
                {canSubmit ? (
                    <SubmitForm companyId={department.company_id} departmentId={department.id} kpis={kpis} />
                ) : (
                    <p className="text-xs text-slate-400 mb-4">You can view this department's submissions but aren't assigned to it, so you can't submit here.</p>
                )}

                {submissions.length === 0 ? (
                    <EmptyState icon={<ClipboardCheckIcon className="w-10 h-10" />} title="No submissions yet" description="Once values are reported, they'll show up here with achievement against target." />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {submissions.map((s) => (
                            <li key={s.id} className="py-3.5 flex items-center justify-between gap-4">
                                <div className="min-w-0">
                                    <p className="text-sm font-semibold text-slate-800">
                                        {s.kpis.name}: {s.value}
                                        {s.kpis.unit ?? ''}
                                        {s.kpis.target !== null && (
                                            <span className="text-xs text-slate-400 ml-2">
                                                (target {s.kpis.target}
                                                {s.kpis.unit ?? ''})
                                            </span>
                                        )}
                                    </p>
                                    <p className="text-xs text-slate-400">
                                        {s.submission_date} · by {s.users.name}
                                        {s.notes ? ` · ${s.notes}` : ''}
                                    </p>
                                </div>
                                <div className="flex-none flex items-center gap-2">
                                    <AchievementBar pct={achievementPct(s)} />
                                    <AchievementBadge pct={achievementPct(s)} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
