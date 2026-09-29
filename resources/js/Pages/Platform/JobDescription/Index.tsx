import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, PrimaryButton, SecondaryButton } from '@/Components/Platform/ui';
import { DocumentIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
}

type Status = 'draft' | 'submitted' | 'approved' | 'changes_requested';

interface MyJobDescription {
    id: string;
    summary: string | null;
    responsibilities: string | null;
    requirements: string | null;
    competencies: string | null;
    status: Status;
    decision_note: string | null;
}

interface TeamRow {
    id: string;
    status: Status;
    updated_at: string;
    users: { name: string; email: string } | null;
}

interface JobDescriptionPageProps {
    company: Company;
    mine: MyJobDescription | null;
    team: TeamRow[];
    isAdmin: boolean;
    [key: string]: unknown;
}

const STATUS_TONE: Record<Status, 'neutral' | 'info' | 'success' | 'warning'> = {
    draft: 'neutral',
    submitted: 'info',
    approved: 'success',
    changes_requested: 'warning',
};

const STATUS_LABEL: Record<Status, string> = {
    draft: 'Draft',
    submitted: 'Submitted — awaiting review',
    approved: 'Approved',
    changes_requested: 'Changes requested',
};

export default function JobDescriptionIndex({ company, mine, team, isAdmin }: JobDescriptionPageProps) {
    const editable = !mine || mine.status === 'draft' || mine.status === 'changes_requested';

    const { data, setData, post, transform, processing } = useForm({
        summary: mine?.summary ?? '',
        responsibilities: mine?.responsibilities ?? '',
        requirements: mine?.requirements ?? '',
        competencies: mine?.competencies ?? '',
    });

    const submit = (action: 'save' | 'submit') => {
        if (action === 'submit' && !confirm('Submit your job description for review? You won\'t be able to edit it until changes are requested.')) {
            return;
        }
        // `transform` (not `setData`) so this specific request's payload is
        // correct immediately — `setData` is React state, which wouldn't be
        // committed yet by the time `post()` reads `data` in this same tick.
        transform((current) => ({ ...current, action }));
        post(`/platform/companies/${company.id}/job-description`, { preserveScroll: true });
    };

    const [decisionNotes, setDecisionNotes] = useState<Record<string, string>>({});

    const decide = (jobDescriptionId: string, decision: 'approved' | 'changes_requested') => {
        if (decision === 'changes_requested' && !(decisionNotes[jobDescriptionId] ?? '').trim()) {
            alert('Add a note explaining what needs to change.');
            return;
        }
        router.post(
            `/platform/companies/${company.id}/job-descriptions/${jobDescriptionId}/decision`,
            { decision, decision_note: decisionNotes[jobDescriptionId] ?? '' },
            { preserveScroll: true },
        );
    };

    return (
        <PlatformLayout
            title="Job Description"
            description="Your role's summary, responsibilities, requirements, and competencies — save a draft any time, submit when ready for your Company Admin to review."
            company={company}
            maxWidth="max-w-4xl"
        >
            <div className="space-y-6">
                <Card
                    title="My Job Description"
                    actions={mine && <Badge tone={STATUS_TONE[mine.status]}>{STATUS_LABEL[mine.status]}</Badge>}
                >
                    {mine?.status === 'changes_requested' && mine.decision_note && (
                        <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs">
                            <p className="font-bold text-amber-700">Changes requested:</p>
                            <p className="text-amber-700 mt-0.5">{mine.decision_note}</p>
                        </div>
                    )}

                    <div className="space-y-4">
                        <div>
                            <label className="block text-xs font-bold text-slate-600 mb-1">Summary</label>
                            <textarea
                                value={data.summary}
                                onChange={(e) => setData('summary', e.target.value)}
                                disabled={!editable}
                                rows={3}
                                className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm disabled:bg-slate-50 disabled:text-slate-500"
                                placeholder="A short overview of this role's purpose."
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-bold text-slate-600 mb-1">Responsibilities</label>
                            <textarea
                                value={data.responsibilities}
                                onChange={(e) => setData('responsibilities', e.target.value)}
                                disabled={!editable}
                                rows={5}
                                className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm disabled:bg-slate-50 disabled:text-slate-500"
                                placeholder="What this role is expected to do, day to day."
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-bold text-slate-600 mb-1">Requirements</label>
                            <textarea
                                value={data.requirements}
                                onChange={(e) => setData('requirements', e.target.value)}
                                disabled={!editable}
                                rows={4}
                                className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm disabled:bg-slate-50 disabled:text-slate-500"
                                placeholder="Qualifications, experience, or skills needed."
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-bold text-slate-600 mb-1">Competencies</label>
                            <textarea
                                value={data.competencies}
                                onChange={(e) => setData('competencies', e.target.value)}
                                disabled={!editable}
                                rows={4}
                                className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm disabled:bg-slate-50 disabled:text-slate-500"
                                placeholder="Behaviors and abilities that make someone successful in this role."
                            />
                        </div>

                        {editable && (
                            <div className="flex items-center gap-3">
                                <SecondaryButton type="button" onClick={() => submit('save')} disabled={processing}>
                                    Save draft
                                </SecondaryButton>
                                <PrimaryButton type="button" onClick={() => submit('submit')} disabled={processing}>
                                    Submit for review
                                </PrimaryButton>
                            </div>
                        )}
                    </div>
                </Card>

                {isAdmin && (
                    <Card title="Team job descriptions" description="Review submitted job descriptions for this company.">
                        {team.length === 0 ? (
                            <EmptyState icon={<DocumentIcon className="w-8 h-8" />} title="No job descriptions yet" />
                        ) : (
                            <div className="divide-y divide-slate-100 -mx-6 -mb-2">
                                {team.map((row) => (
                                    <div key={row.id} className="px-6 py-4">
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="min-w-0">
                                                <p className="text-sm font-bold text-slate-900">{row.users?.name ?? 'Unknown'}</p>
                                                <p className="text-xs text-slate-400">{row.users?.email}</p>
                                            </div>
                                            <Badge tone={STATUS_TONE[row.status]}>{STATUS_LABEL[row.status]}</Badge>
                                        </div>

                                        {row.status === 'submitted' && (
                                            <div className="mt-3 flex flex-col sm:flex-row items-start sm:items-end gap-2">
                                                <input
                                                    value={decisionNotes[row.id] ?? ''}
                                                    onChange={(e) => setDecisionNotes((prev) => ({ ...prev, [row.id]: e.target.value }))}
                                                    placeholder="Note (required if requesting changes)"
                                                    className="flex-1 min-w-55 rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                                                />
                                                <SecondaryButton type="button" onClick={() => decide(row.id, 'changes_requested')}>
                                                    Request changes
                                                </SecondaryButton>
                                                <PrimaryButton type="button" onClick={() => decide(row.id, 'approved')}>Approve</PrimaryButton>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                )}
            </div>
        </PlatformLayout>
    );
}
