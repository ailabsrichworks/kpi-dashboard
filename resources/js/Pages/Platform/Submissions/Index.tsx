import { useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { FormEventHandler, useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, InfoTooltip, PrimaryButton, SecondaryButton } from '@/Components/Platform/ui';
import { ClipboardCheckIcon, SparklesIcon } from '@/Components/Platform/Icons';
import { calculateAchievement, MeasurementDirection } from '@/lib/kpiAchievement';
import { formatLinkageValue, LinkageUnit } from '@/lib/linkageFormat';

type ComputedStatus = 'not_scored' | 'critical' | 'at_risk' | 'on_track' | 'achieved' | 'exceeded';

const STATUS_LABELS: Record<ComputedStatus, string> = {
    not_scored: 'Not scored',
    critical: 'Critical',
    at_risk: 'At risk',
    on_track: 'On track',
    achieved: 'Achieved',
    exceeded: 'Exceeded',
};

const STATUS_TONE: Record<ComputedStatus, 'neutral' | 'danger' | 'warning' | 'success'> = {
    not_scored: 'neutral',
    critical: 'danger',
    at_risk: 'warning',
    on_track: 'success',
    achieved: 'success',
    exceeded: 'success',
};

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

type ApprovalStatus = 'pending_review' | 'approved' | 'rejected' | 'returned';

const APPROVAL_LABELS: Record<ApprovalStatus, string> = {
    pending_review: 'Awaiting approval',
    approved: 'Approved',
    rejected: 'Rejected',
    returned: 'Returned for revision',
};

const APPROVAL_TONE: Record<ApprovalStatus, 'neutral' | 'danger' | 'warning' | 'success'> = {
    pending_review: 'warning',
    approved: 'success',
    rejected: 'danger',
    returned: 'danger',
};

interface SubmissionScore {
    score: number;
    comment: string | null;
    users: { name: string };
}

interface Submission {
    id: string;
    value: number;
    submission_date: string;
    notes: string | null;
    evidence_note: string | null;
    status: ApprovalStatus;
    revision_number: number;
    is_current_approved: boolean;
    kpis: { name: string; unit: string | null; target: number | null; stretch_target: number | null; measurement_direction: MeasurementDirection; measurement_unit: LinkageUnit };
    users: { name: string };
    /** Server-computed (KpiCalculationService), not derived from the client-side achievementPct() below. */
    computed_status: ComputedStatus;
    /** Present once the submitter's manager has scored this (approved) submission — read-only for everyone once given. */
    score: SubmissionScore | null;
    /** True only for the caller's own resolved appraiser, on an approved-but-unscored submission. */
    can_score: boolean;
}

interface PlatformUser {
    company_memberships: Array<{ company_id: string; companies?: { name: string; code: string } }>;
}

interface PeriodState {
    allowed: boolean;
    quarter_status: string;
    month_status: string;
}

interface SubmissionsPageProps {
    department: Department;
    kpis: Kpi[];
    submissions: Submission[];
    canSubmit: boolean;
    periodState: PeriodState;
    [key: string]: unknown;
}

function PeriodBanner({ periodState }: { periodState: PeriodState }) {
    if (periodState.allowed) {
        return null;
    }

    return (
        <div className="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800">
            This period isn't currently open for submissions (quarter: <strong>{periodState.quarter_status}</strong>, month:{' '}
            <strong>{periodState.month_status}</strong>). Ask your Company Admin to reopen it if you need to report a value for this date.
        </div>
    );
}

function SubmitForm({ companyId, departmentId, kpis, periodState }: { companyId: string; departmentId: string; kpis: Kpi[]; periodState: PeriodState }) {
    const today = new Date().toISOString().slice(0, 10);
    const { data, setData, post, processing, reset } = useForm({
        kpi_id: kpis[0]?.id ?? '',
        value: '',
        submission_date: today,
        notes: '',
        evidence_note: '',
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
            <PeriodBanner periodState={periodState} />
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
                <label className="block text-xs font-medium text-slate-600 mb-1">Evidence (optional)</label>
                <input
                    value={data.evidence_note}
                    onChange={(e) => setData('evidence_note', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="A link or short description of supporting evidence"
                />
            </div>
            <div className="col-span-2">
                <PrimaryButton type="submit" disabled={processing}>
                    Submit for approval
                </PrimaryButton>
                <p className="text-[11px] text-slate-400 mt-2">
                    This creates a new, versioned revision — it won't replace any earlier value until approved. Dashboards keep using the last
                    approved value until then.
                </p>
            </div>
        </form>
    );
}

function achievementPct(submission: Submission): number | null {
    return calculateAchievement(submission.value, submission.kpis.target, submission.kpis.stretch_target, submission.kpis.measurement_direction);
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

/**
 * Score justification comment — optional. Editable only by the submitter's
 * resolved manager (server-checked; `can_score` is just what tells the UI
 * whether to offer the form at all), read-only for everyone else once given,
 * so the submitter can see why they were given that score. One-shot: there's
 * no edit path once a score exists (kpi_submission_scores has no update
 * policy), matching kpi_submissions' own "never edit, only ever create"
 * posture.
 */
function ScoreForm({ companyId, departmentId, submissionId, kpiName }: { companyId: string; departmentId: string; submissionId: string; kpiName: string }) {
    const [rephrasing, setRephrasing] = useState(false);
    const { data, setData, post, processing } = useForm({ score: '', comment: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/departments/${departmentId}/submissions/${submissionId}/score`);
    };

    const rephrase = async () => {
        if (!data.comment.trim()) {
            return;
        }
        setRephrasing(true);
        try {
            const response = await axios.post('/platform/ai/rephrase-appraiser-comment', {
                kpi_name: kpiName,
                score: data.score || undefined,
                comment: data.comment,
            });
            if (response.data.success) {
                setData('comment', response.data.comment);
            }
        } catch {
            // Best-effort — leave the draft comment untouched on failure.
        } finally {
            setRephrasing(false);
        }
    };

    return (
        <form onSubmit={submit} className="mt-2 w-full max-w-md bg-slate-50 rounded-xl p-3">
            <p className="text-xs font-semibold text-slate-600 mb-1.5">Score this submission</p>
            <div className="flex items-center gap-2 mb-2">
                <input
                    value={data.score}
                    onChange={(e) => setData('score', e.target.value)}
                    type="number"
                    inputMode="decimal"
                    step="any"
                    min="0"
                    max="5"
                    placeholder="0–5"
                    className="w-20 rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
                    required
                />
                <span className="text-xs text-slate-400">out of 5</span>
            </div>
            <textarea
                value={data.comment}
                onChange={(e) => setData('comment', e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm mb-2"
                placeholder="Justification comment (optional) — why did you give this score?"
                rows={2}
            />
            <div className="flex items-center gap-2">
                <PrimaryButton type="submit" disabled={processing}>
                    Save score
                </PrimaryButton>
                <SecondaryButton type="button" onClick={rephrase} disabled={rephrasing || !data.comment.trim()}>
                    <span className="inline-flex items-center gap-1">
                        <SparklesIcon className="w-3.5 h-3.5" /> {rephrasing ? 'Rephrasing…' : 'Rephrase'}
                    </span>
                </SecondaryButton>
            </div>
        </form>
    );
}

function ScoreBlock({ submission, companyId, departmentId }: { submission: Submission; companyId: string; departmentId: string }) {
    if (submission.score) {
        return (
            <div className="mt-1.5 flex items-start gap-2 text-xs text-slate-500">
                <Badge tone="info">Score: {submission.score.score}/5</Badge>
                <p className="italic">
                    {submission.score.comment ? `"${submission.score.comment}"` : 'No comment given'} — {submission.score.users.name}
                </p>
            </div>
        );
    }

    if (submission.can_score) {
        return <ScoreForm companyId={companyId} departmentId={departmentId} submissionId={submission.id} kpiName={submission.kpis.name} />;
    }

    return null;
}

export default function SubmissionsIndex({ department, kpis, submissions, canSubmit, periodState }: SubmissionsPageProps) {
    const { platformUser } = usePage<{ platformUser: PlatformUser | null }>().props;
    const membership = platformUser?.company_memberships.find((m) => m.company_id === department.company_id);
    const company = {
        id: department.company_id,
        name: membership?.companies?.name ?? 'Company',
        code: membership?.companies?.code ?? '',
    };

    const approvedScored = submissions
        .filter((s) => s.status === 'approved')
        .map((s) => achievementPct(s))
        .filter((p): p is number => p !== null);
    const avgAchievement = approvedScored.length > 0 ? Math.round(approvedScored.reduce((a, b) => a + b, 0) / approvedScored.length) : null;

    return (
        <PlatformLayout
            title={`${department.name} — KPI Submissions`}
            description={
                avgAchievement !== null
                    ? `Average achievement (approved values only) so far: ${avgAchievement}%`
                    : 'Report your KPI values here as often as required.'
            }
            company={company}
        >
            <Card
                title={
                    <span className="inline-flex items-center gap-1.5">
                        Report a value
                        <InfoTooltip text="Achievement % compares your value against the KPI's target (and stretch target, if set) — accounting for whether higher or lower is actually better for this KPI." />
                    </span>
                }
            >
                {canSubmit ? (
                    <SubmitForm companyId={department.company_id} departmentId={department.id} kpis={kpis} periodState={periodState} />
                ) : (
                    <p className="text-xs text-slate-400 mb-4">You can view this department's submissions but aren't assigned to it, so you can't submit here.</p>
                )}

                {submissions.length === 0 ? (
                    <EmptyState icon={<ClipboardCheckIcon className="w-10 h-10" />} title="No submissions yet" description="Once values are reported, they'll show up here with achievement against target." />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {submissions.map((s) => (
                            <li key={s.id} className="py-3.5 flex items-center justify-between gap-4 flex-wrap">
                                <div className="min-w-0">
                                    <p className="text-sm font-semibold text-slate-800">
                                        {s.kpis.name}: {formatLinkageValue(s.value, s.kpis.measurement_unit)}
                                        <span className="text-[11px] font-normal text-slate-400 ml-1.5">V{s.revision_number}</span>
                                        {s.kpis.target !== null && (
                                            <span className="text-xs text-slate-400 ml-2">(target {formatLinkageValue(s.kpis.target, s.kpis.measurement_unit)})</span>
                                        )}
                                    </p>
                                    <p className="text-xs text-slate-400">
                                        {s.submission_date} · by {s.users.name}
                                        {s.notes ? ` · ${s.notes}` : ''}
                                        {s.evidence_note ? ` · evidence: ${s.evidence_note}` : ''}
                                    </p>
                                    {s.status === 'approved' && <ScoreBlock submission={s} companyId={department.company_id} departmentId={department.id} />}
                                </div>
                                <div className="flex-none flex items-center gap-2">
                                    <Badge tone={APPROVAL_TONE[s.status]}>{APPROVAL_LABELS[s.status]}</Badge>
                                    {s.status === 'approved' && (
                                        <>
                                            <AchievementBar pct={achievementPct(s)} />
                                            <AchievementBadge pct={achievementPct(s)} />
                                            {s.computed_status !== 'not_scored' && <Badge tone={STATUS_TONE[s.computed_status]}>{STATUS_LABELS[s.computed_status]}</Badge>}
                                            {s.is_current_approved && <Badge tone="info">Current</Badge>}
                                        </>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
