import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, PrimaryButton, SecondaryButton } from '@/Components/Platform/ui';
import { ClipboardCheckIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface AssessmentArea {
    no: number;
    title: string;
    description: string;
}

type Status = 'draft' | 'submitted' | 'appraised' | 'completed';

interface KpiScoreItem {
    kpi_id: string;
    name: string;
    self_score?: number;
    score?: number;
    weight: number;
}

interface SelfScores {
    kpi: { total: number; items: KpiScoreItem[] };
    attitude: { ratings: number[]; total: number };
    culture?: { ratings: number[]; total: number };
}

interface AppraiserScores {
    kpi: { total: number; items: KpiScoreItem[] };
    attitude: { total: number; items: Array<{ rating: number; comment: string | null }> };
    attendance: { total: number; items: Array<{ category: string; count: number; score: number }> };
    culture?: { total: number; ratings: number[] };
    manager_remarks: string | null;
    strengths: string | null;
    ethics: string | null;
    improvement: string | null;
    training: string | null;
    recommendation: { confirmation: boolean; salary_review: boolean; promotion: boolean };
}

interface Review {
    id: string;
    status: Status;
    self_scores: SelfScores | null;
    appraiser_scores: AppraiserScores | null;
    appraisee_acknowledgment: string | null;
    final_score: number | null;
    band: string | null;
}

interface ReviewQueueItem {
    user_id: string;
    name: string;
    email: string;
    review: Review | null;
    kpis: Array<{ id: string; name: string; weight: number | null }>;
}

interface AdminQueueItem extends Review {
    user_id: string;
    users: { name: string; email: string } | null;
}

interface ManagerOption {
    user_id: string;
    name: string;
    email: string;
    delegate_candidate: string | null;
}

interface Delegation {
    id: string;
    manager_user_id: string;
    delegate_user_id: string;
    reason: string | null;
    created_at: string;
}

interface PerformancePageProps {
    company: Company;
    quarter: 'Q1' | 'Q2' | 'Q3' | 'Q4';
    financialYear: string;
    isQ4: boolean;
    assessmentAreas: AssessmentArea[];
    cultureValues: string[];
    attendanceLabels: Record<string, string>;
    mine: Review | null;
    reviewQueue: ReviewQueueItem[];
    adminQueue: AdminQueueItem[] | null;
    managers: ManagerOption[] | null;
    delegations: Delegation[] | null;
    [key: string]: unknown;
}

const STATUS_TONE: Record<Status, 'neutral' | 'info' | 'warning' | 'success'> = {
    draft: 'neutral',
    submitted: 'info',
    appraised: 'warning',
    completed: 'success',
};

const STATUS_LABEL: Record<Status, string> = {
    draft: 'Draft',
    submitted: 'Submitted — awaiting appraisal',
    appraised: 'Appraised — awaiting your sign-off',
    completed: 'Completed',
};

const BAND_TONE: Record<string, 'success' | 'warning' | 'danger' | 'info'> = {
    Outstanding: 'success',
    'Meets Expectations': 'info',
    'Below Average': 'warning',
    Unsatisfactory: 'danger',
};

function RatingRadios({ value, onChange, disabled }: { value: number; onChange: (v: number) => void; disabled?: boolean }) {
    return (
        <div className="flex gap-1.5">
            {[1, 2, 3, 4, 5].map((n) => (
                <button
                    key={n}
                    type="button"
                    disabled={disabled}
                    onClick={() => onChange(n)}
                    className={`w-8 h-8 rounded-lg text-xs font-black border transition ${
                        value === n ? 'bg-brand-900 text-white border-brand-900' : 'bg-white text-slate-500 border-slate-300 hover:border-slate-400'
                    } disabled:opacity-40 disabled:cursor-not-allowed`}
                >
                    {n}
                </button>
            ))}
        </div>
    );
}

function SelfAssessmentSection({ company, quarter, isQ4, assessmentAreas, cultureValues, mine }: {
    company: Company;
    quarter: string;
    isQ4: boolean;
    assessmentAreas: AssessmentArea[];
    cultureValues: string[];
    mine: Review | null;
}) {
    const editable = !mine || mine.status === 'draft';

    const { data, setData, post, processing } = useForm({
        attitude_ratings: mine?.self_scores?.attitude.ratings ?? Array(12).fill(3),
        culture_ratings: mine?.self_scores?.culture?.ratings ?? Array(6).fill(3),
        action: 'save' as 'save' | 'submit',
    });

    const [ackText, setAckText] = useState('');

    const submit = (action: 'save' | 'submit') => {
        if (action === 'submit' && !confirm('Submit your self-assessment? You won\'t be able to edit it until your manager appraises it.')) return;
        router.post(`/platform/companies/${company.id}/performance/${quarter.toLowerCase()}/save`, { ...data, action }, { preserveScroll: true });
    };

    const signOff = () => {
        if (ackText.trim().length < 5) {
            alert('Add a short acknowledgment note before signing off.');
            return;
        }
        if (!confirm('Sign off on this review? This completes the appraisal cycle.')) return;
        router.post(`/platform/companies/${company.id}/performance/${quarter.toLowerCase()}/acknowledge`, { acknowledgment: ackText }, { preserveScroll: true });
    };

    return (
        <Card title="My Review" actions={mine && <Badge tone={STATUS_TONE[mine.status]}>{STATUS_LABEL[mine.status]}</Badge>}>
            {mine?.status === 'completed' ? (
                <div className="space-y-3">
                    <div className="flex items-center gap-3">
                        <p className="text-3xl font-black text-slate-900">{mine.final_score?.toFixed(2)}</p>
                        {mine.band && <Badge tone={BAND_TONE[mine.band] ?? 'neutral'}>{mine.band}</Badge>}
                    </div>
                    <p className="text-xs text-slate-500">Your acknowledgment: {mine.appraisee_acknowledgment}</p>
                </div>
            ) : mine?.status === 'appraised' ? (
                <div className="space-y-4">
                    <p className="text-sm text-slate-600">Your manager has scored this review. Add a short acknowledgment and sign off to complete it.</p>
                    <textarea
                        value={ackText}
                        onChange={(e) => setAckText(e.target.value)}
                        rows={3}
                        placeholder="I acknowledge this review..."
                        className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm"
                    />
                    <PrimaryButton type="button" onClick={signOff}>Sign off &amp; complete</PrimaryButton>
                </div>
            ) : mine?.status === 'submitted' ? (
                <p className="text-sm text-slate-500">Submitted — waiting for your manager to appraise it.</p>
            ) : (
                <div className="space-y-5">
                    <div>
                        <h3 className="text-xs font-black uppercase text-slate-500 mb-3">Attitude &amp; Competency Self-Assessment</h3>
                        <div className="space-y-3">
                            {assessmentAreas.map((area, i) => (
                                <div key={area.no} className="flex flex-col sm:flex-row sm:items-center gap-3 border-b border-slate-100 pb-3">
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-bold text-slate-800">{area.no}. {area.title}</p>
                                        <p className="text-xs text-slate-400 mt-0.5">{area.description}</p>
                                    </div>
                                    <RatingRadios
                                        value={data.attitude_ratings[i]}
                                        disabled={!editable}
                                        onChange={(v) => setData('attitude_ratings', data.attitude_ratings.map((r: number, idx: number) => (idx === i ? v : r)))}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>

                    {isQ4 && (
                        <div>
                            <h3 className="text-xs font-black uppercase text-slate-500 mb-3">Culture &amp; Values Self-Assessment</h3>
                            <div className="space-y-3">
                                {cultureValues.map((value, i) => (
                                    <div key={value} className="flex flex-col sm:flex-row sm:items-center gap-3 border-b border-slate-100 pb-3">
                                        <p className="flex-1 text-sm font-bold text-slate-800">{value}</p>
                                        <RatingRadios
                                            value={data.culture_ratings[i]}
                                            disabled={!editable}
                                            onChange={(v) => setData('culture_ratings', data.culture_ratings.map((r: number, idx: number) => (idx === i ? v : r)))}
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <p className="text-xs text-slate-400">Your KPI score (70% of the total) is computed automatically from your assigned KPIs' quarterly actuals — nothing to fill in here.</p>

                    {editable && (
                        <div className="flex items-center gap-3">
                            <SecondaryButton type="button" onClick={() => submit('save')} disabled={processing}>Save draft</SecondaryButton>
                            <PrimaryButton type="button" onClick={() => submit('submit')} disabled={processing}>Submit</PrimaryButton>
                        </div>
                    )}
                </div>
            )}
        </Card>
    );
}

function AppraiseForm({ company, quarter, isQ4, assessmentAreas, cultureValues, attendanceLabels, report, onDone }: {
    company: Company;
    quarter: string;
    isQ4: boolean;
    assessmentAreas: AssessmentArea[];
    cultureValues: string[];
    attendanceLabels: Record<string, string>;
    report: ReviewQueueItem;
    onDone: () => void;
}) {
    const { data, setData, post, processing } = useForm({
        kpi_scores: Object.fromEntries(report.kpis.map((k) => [k.id, '0'])) as Record<string, string>,
        attitude_ratings: Array.from({ length: 12 }, () => ({ rating: 3, comment: '' })),
        attendance_counts: Object.fromEntries(Object.keys(attendanceLabels).map((k) => [k, '0'])) as Record<string, string>,
        culture_ratings: Array(6).fill(3),
        manager_remarks: '',
        strengths: '',
        ethics: '',
        improvement: '',
        training: '',
        recommendation: { confirmation: false, salary_review: false, promotion: false },
        action: 'save' as 'save' | 'submit',
    });

    const submit = (action: 'save' | 'submit') => {
        if (action === 'submit' && !confirm(`Submit your appraisal of ${report.name}? This finalizes their score.`)) return;
        router.post(
            `/platform/companies/${company.id}/performance/${quarter.toLowerCase()}/appraise/${report.user_id}`,
            { ...data, action },
            { preserveScroll: true, onSuccess: onDone },
        );
    };

    return (
        <div className="mt-3 space-y-5 bg-slate-50 rounded-xl p-4">
            {report.kpis.length > 0 && (
                <div>
                    <h4 className="text-xs font-black uppercase text-slate-500 mb-2">KPI Scores (0–5 each)</h4>
                    <div className="space-y-2">
                        {report.kpis.map((k) => (
                            <div key={k.id} className="flex items-center gap-3">
                                <span className="flex-1 text-sm text-slate-700">{k.name} <span className="text-slate-400 text-xs">(weight {k.weight ?? 0}%)</span></span>
                                <input
                                    type="number" min={0} max={5} step={0.1}
                                    value={data.kpi_scores[k.id]}
                                    onChange={(e) => setData('kpi_scores', { ...data.kpi_scores, [k.id]: e.target.value })}
                                    className="w-20 rounded-lg border border-slate-300 px-2 py-1 text-sm text-center"
                                />
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div>
                <h4 className="text-xs font-black uppercase text-slate-500 mb-2">Attitude &amp; Competency</h4>
                <div className="space-y-2">
                    {assessmentAreas.map((area, i) => (
                        <div key={area.no} className="border-b border-slate-200 pb-2">
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-sm font-semibold text-slate-800">{area.no}. {area.title}</p>
                                <RatingRadios
                                    value={data.attitude_ratings[i].rating}
                                    onChange={(v) => setData('attitude_ratings', data.attitude_ratings.map((r, idx) => (idx === i ? { ...r, rating: v } : r)))}
                                />
                            </div>
                            <input
                                value={data.attitude_ratings[i].comment}
                                onChange={(e) => setData('attitude_ratings', data.attitude_ratings.map((r, idx) => (idx === i ? { ...r, comment: e.target.value } : r)))}
                                placeholder="Comment (optional)"
                                className="w-full mt-1 rounded-lg border border-slate-300 px-2 py-1 text-xs"
                            />
                        </div>
                    ))}
                </div>
            </div>

            <div>
                <h4 className="text-xs font-black uppercase text-slate-500 mb-2">Attendance (this quarter)</h4>
                <div className="grid grid-cols-2 sm:grid-cols-5 gap-2">
                    {Object.entries(attendanceLabels).map(([key, label]) => (
                        <div key={key}>
                            <label className="block text-[10px] font-semibold text-slate-500 mb-0.5">{label}</label>
                            <input
                                type="number" min={0}
                                value={data.attendance_counts[key]}
                                onChange={(e) => setData('attendance_counts', { ...data.attendance_counts, [key]: e.target.value })}
                                className="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm text-center"
                            />
                        </div>
                    ))}
                </div>
            </div>

            {isQ4 && (
                <div>
                    <h4 className="text-xs font-black uppercase text-slate-500 mb-2">Culture &amp; Values</h4>
                    <div className="space-y-2">
                        {cultureValues.map((value, i) => (
                            <div key={value} className="flex items-center justify-between gap-3">
                                <p className="text-sm text-slate-700">{value}</p>
                                <RatingRadios value={data.culture_ratings[i]} onChange={(v) => setData('culture_ratings', data.culture_ratings.map((r: number, idx: number) => (idx === i ? v : r)))} />
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {(
                    [
                        ['strengths', 'Strengths'], ['ethics', 'Work Ethics / Attitude'],
                        ['improvement', 'Areas Needing Improvement'], ['training', 'Training Required'],
                    ] as const
                ).map(([key, label]) => (
                    <div key={key}>
                        <label className="block text-xs font-bold text-slate-600 mb-1">{label}</label>
                        <textarea
                            value={data[key]}
                            onChange={(e) => setData(key, e.target.value)}
                            rows={2}
                            className="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs"
                        />
                    </div>
                ))}
            </div>

            <div>
                <label className="block text-xs font-bold text-slate-600 mb-1">Overall Remarks</label>
                <textarea value={data.manager_remarks} onChange={(e) => setData('manager_remarks', e.target.value)} rows={2} className="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs" />
            </div>

            <div className="flex flex-wrap gap-4">
                {(['confirmation', 'salary_review', 'promotion'] as const).map((key) => (
                    <label key={key} className="flex items-center gap-2 text-xs font-semibold text-slate-600 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={data.recommendation[key]}
                            onChange={(e) => setData('recommendation', { ...data.recommendation, [key]: e.target.checked })}
                            className="rounded border-slate-300"
                        />
                        {key === 'confirmation' ? 'Confirmation' : key === 'salary_review' ? 'Salary Review' : 'Promotion'}
                    </label>
                ))}
            </div>

            <div className="flex items-center gap-2">
                <SecondaryButton type="button" onClick={() => submit('save')} disabled={processing}>Save draft</SecondaryButton>
                <PrimaryButton type="button" onClick={() => submit('submit')} disabled={processing}>Submit appraisal</PrimaryButton>
            </div>
        </div>
    );
}

function TeamReviewSection(props: {
    company: Company; quarter: string; isQ4: boolean; assessmentAreas: AssessmentArea[];
    cultureValues: string[]; attendanceLabels: Record<string, string>; reviewQueue: ReviewQueueItem[];
}) {
    const [open, setOpen] = useState<string | null>(null);

    if (props.reviewQueue.length === 0) return null;

    return (
        <Card title="My Team" description="Employees who report to you.">
            <div className="divide-y divide-slate-100 -mx-6 -mb-2">
                {props.reviewQueue.map((r) => (
                    <div key={r.user_id} className="px-6 py-4">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-sm font-bold text-slate-900">{r.name}</p>
                                <p className="text-xs text-slate-400">{r.email}</p>
                            </div>
                            <div className="flex items-center gap-3">
                                <Badge tone={r.review ? STATUS_TONE[r.review.status] : 'neutral'}>{r.review ? STATUS_LABEL[r.review.status] : 'Not started'}</Badge>
                                {r.review?.status === 'submitted' && (
                                    <button onClick={() => setOpen(open === r.user_id ? null : r.user_id)} className="text-xs font-bold text-brand-800 hover:underline">
                                        {open === r.user_id ? 'Close' : 'Appraise'}
                                    </button>
                                )}
                            </div>
                        </div>
                        {open === r.user_id && (
                            <AppraiseForm {...props} report={r} onDone={() => setOpen(null)} />
                        )}
                    </div>
                ))}
            </div>
        </Card>
    );
}

function CompanyReviewsSection({ adminQueue }: { adminQueue: AdminQueueItem[] | null }) {
    if (adminQueue === null) return null;

    return (
        <Card title="Company Reviews" description="Every performance review this quarter.">
            {adminQueue.length === 0 ? (
                <EmptyState icon={<ClipboardCheckIcon className="w-8 h-8" />} title="No reviews started yet this quarter" />
            ) : (
                <div className="overflow-x-auto -mx-6">
                    <table className="w-full text-left text-xs min-w-140">
                        <thead>
                            <tr className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500 font-black border-y border-slate-200">
                                <th className="px-4 py-2">Employee</th>
                                <th className="px-4 py-2">Status</th>
                                <th className="px-4 py-2">Score</th>
                                <th className="px-4 py-2">Band</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {adminQueue.map((row) => (
                                <tr key={row.id}>
                                    <td className="px-4 py-2">
                                        <p className="font-bold text-slate-800">{row.users?.name ?? 'Unknown'}</p>
                                        <p className="text-slate-400 text-[10px]">{row.users?.email}</p>
                                    </td>
                                    <td className="px-4 py-2"><Badge tone={STATUS_TONE[row.status]}>{STATUS_LABEL[row.status]}</Badge></td>
                                    <td className="px-4 py-2 font-bold">{row.final_score !== null ? row.final_score.toFixed(2) : '—'}</td>
                                    <td className="px-4 py-2">{row.band ? <Badge tone={BAND_TONE[row.band] ?? 'neutral'}>{row.band}</Badge> : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Card>
    );
}

// Ports legacy's Appraiser Delegation (see appraiser_delegations migration's
// own docblock for the design adaptation) — Company-Admin-only, embedded
// here rather than on a dedicated page, mirroring legacy's own choice to put
// it on an existing admin screen (Quarter Control) instead of a standalone
// one. The delegate is always computed server-side from the manager's own
// manager_user_id (shown here only as a preview, `delegate_candidate`) —
// never picked by the admin.
function AppraiserDelegationPanel({ company, managers, delegations }: { company: Company; managers: ManagerOption[] | null; delegations: Delegation[] | null }) {
    const [reasons, setReasons] = useState<Record<string, string>>({});

    if (managers === null || delegations === null) return null;

    const delegationsByManager = Object.fromEntries(delegations.map((d) => [d.manager_user_id, d]));
    const managerById = Object.fromEntries(managers.map((m) => [m.user_id, m]));

    const delegate = (managerId: string) => {
        router.post(
            `/platform/companies/${company.id}/appraiser-delegations`,
            { manager_user_id: managerId, reason: reasons[managerId] || undefined },
            { preserveScroll: true, onSuccess: () => setReasons((prev) => ({ ...prev, [managerId]: '' })) },
        );
    };

    const endDelegation = (managerId: string) => {
        if (!confirm('End this delegation? The manager resumes appraising their own reports immediately.')) return;
        router.delete(`/platform/companies/${company.id}/appraiser-delegations/${managerId}`, { preserveScroll: true });
    };

    return (
        <Card title="Appraiser Delegation" description="Let a manager's own manager stand in as appraiser for their reports while they're away.">
            {managers.length === 0 ? (
                <EmptyState icon={<ClipboardCheckIcon className="w-8 h-8" />} title="No one currently manages anyone yet" description="Set up the reporting hierarchy on the Departments page first." />
            ) : (
                <div className="space-y-3">
                    {managers.map((m) => {
                        const delegation = delegationsByManager[m.user_id];
                        return (
                            <div key={m.user_id} className="rounded-xl border border-slate-200 p-4">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-sm font-bold text-slate-900">{m.name}</p>
                                        <p className="text-xs text-slate-400">{m.email}</p>
                                    </div>
                                    {delegation ? (
                                        <Badge tone="warning">Delegated to {managerById[delegation.delegate_user_id]?.name ?? 'someone'}</Badge>
                                    ) : m.delegate_candidate ? (
                                        <Badge tone="neutral">Delegate: {m.delegate_candidate}</Badge>
                                    ) : (
                                        <Badge tone="danger">No one above them to delegate to</Badge>
                                    )}
                                </div>

                                {delegation ? (
                                    <div className="mt-2 flex items-center justify-between gap-3">
                                        {delegation.reason && <p className="text-xs text-slate-500">{delegation.reason}</p>}
                                        <SecondaryButton onClick={() => endDelegation(m.user_id)}>End delegation</SecondaryButton>
                                    </div>
                                ) : m.delegate_candidate ? (
                                    <div className="mt-2 flex items-center gap-2">
                                        <input
                                            value={reasons[m.user_id] ?? ''}
                                            onChange={(e) => setReasons((prev) => ({ ...prev, [m.user_id]: e.target.value }))}
                                            placeholder="Reason (optional)"
                                            className="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                                        />
                                        <PrimaryButton onClick={() => delegate(m.user_id)}>Delegate</PrimaryButton>
                                    </div>
                                ) : null}
                            </div>
                        );
                    })}
                </div>
            )}
        </Card>
    );
}

export default function PerformanceIndex(props: PerformancePageProps) {
    const { company, quarter, financialYear, isQ4, assessmentAreas, cultureValues, attendanceLabels, mine, reviewQueue, adminQueue, managers, delegations } = props;

    return (
        <PlatformLayout
            title={`${quarter} Evaluation`}
            description={`${financialYear} performance appraisal — self-assessment, manager scoring, and sign-off.`}
            company={company}
            maxWidth="max-w-4xl"
        >
            <div className="flex gap-2 mb-5">
                {(['Q1', 'Q2', 'Q3', 'Q4'] as const).map((q) => (
                    <Link
                        key={q}
                        href={`/platform/companies/${company.id}/performance/${q.toLowerCase()}`}
                        className={`px-3 py-1.5 rounded-lg text-xs font-black transition ${q === quarter ? 'bg-brand-900 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'}`}
                    >
                        {q}
                    </Link>
                ))}
            </div>

            <div className="space-y-6">
                <SelfAssessmentSection company={company} quarter={quarter} isQ4={isQ4} assessmentAreas={assessmentAreas} cultureValues={cultureValues} mine={mine} />
                <TeamReviewSection company={company} quarter={quarter} isQ4={isQ4} assessmentAreas={assessmentAreas} cultureValues={cultureValues} attendanceLabels={attendanceLabels} reviewQueue={reviewQueue} />
                <CompanyReviewsSection adminQueue={adminQueue} />
                <AppraiserDelegationPanel company={company} managers={managers} delegations={delegations} />
            </div>
        </PlatformLayout>
    );
}
