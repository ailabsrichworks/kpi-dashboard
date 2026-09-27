import { FormEventHandler, useState } from 'react';
import { useForm } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, InfoTooltip, PrimaryButton } from '@/Components/Platform/ui';
import { FlagIcon, PlusIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
}

type GoalStatus = 'draft' | 'active' | 'at_risk' | 'completed' | 'cancelled' | 'archived';

interface Goal {
    id: string;
    title: string;
    description: string | null;
    category: string | null;
    subcategory: string | null;
    owner_user_id: string | null;
    weightage: number | null;
    start_date: string | null;
    end_date: string | null;
    status: GoalStatus;
    users: { name: string } | null;
}

interface LinkedKpi {
    id: string;
    name: string;
    company_goal_id: string;
    target: number | null;
    stretch_target: number | null;
    measurement_direction: string;
}

interface Member {
    user_id: string;
    users: { name: string; email: string };
}

interface GoalsPageProps {
    company: Company;
    goals: Goal[];
    linkedKpis: LinkedKpi[];
    members: Member[];
    [key: string]: unknown;
}

const STATUS_LABELS: Record<GoalStatus, string> = {
    draft: 'Draft',
    active: 'Active',
    at_risk: 'At risk',
    completed: 'Completed',
    cancelled: 'Cancelled',
    archived: 'Archived',
};

const STATUS_TONE: Record<GoalStatus, 'neutral' | 'success' | 'warning' | 'danger'> = {
    draft: 'neutral',
    active: 'success',
    at_risk: 'warning',
    completed: 'success',
    cancelled: 'danger',
    archived: 'neutral',
};

interface GoalFormData {
    title: string;
    description: string;
    category: string;
    subcategory: string;
    owner_user_id: string;
    weightage: string;
    start_date: string;
    end_date: string;
    status: string;
}

function GoalFormFields({ data, setData, members }: { data: GoalFormData; setData: (key: string, value: string) => void; members: Member[] }) {
    return (
        <>
            <div className="col-span-2">
                <label className="block text-xs font-medium text-slate-600 mb-1">Goal title</label>
                <input
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Increase Annual Revenue to RM200 Million"
                    required
                    autoFocus
                />
            </div>
            <div className="col-span-2">
                <label className="block text-xs font-medium text-slate-600 mb-1">Description</label>
                <textarea
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    rows={2}
                    placeholder="What does achieving this actually mean?"
                />
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Category</label>
                <input
                    value={data.category}
                    onChange={(e) => setData('category', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Financial, Growth, Initiatives, People…"
                />
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Subcategory</label>
                <input
                    value={data.subcategory}
                    onChange={(e) => setData('subcategory', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Revenue, Retention…"
                />
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
            <div>
                <label className="text-xs font-medium text-slate-600 mb-1 inline-flex items-center gap-1">
                    Weightage
                    <InfoTooltip text="This goal's share of the company's overall direction. The system rejects a total across active/draft goals over 100%." />
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
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Starts</label>
                <input type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Ends</label>
                <input type="date" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div className="col-span-2">
                <label className="block text-xs font-medium text-slate-600 mb-1">Status</label>
                <select value={data.status} onChange={(e) => setData('status', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    {Object.entries(STATUS_LABELS).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
            </div>
        </>
    );
}

function CreateGoalPanel({ companyId, members }: { companyId: string; members: Member[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm<GoalFormData>({
        title: '',
        description: '',
        category: '',
        subcategory: '',
        owner_user_id: '',
        weightage: '',
        start_date: '',
        end_date: '',
        status: 'draft',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/goals`, {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    if (!open) {
        return (
            <PrimaryButton onClick={() => setOpen(true)} className="mb-5 inline-flex items-center gap-1.5">
                <PlusIcon className="w-4 h-4" /> New company goal
            </PrimaryButton>
        );
    }

    return (
        <form onSubmit={submit} className="grid grid-cols-2 gap-3 mb-5 bg-slate-50 rounded-xl p-4">
            <GoalFormFields data={data} setData={setData} members={members} />
            <div className="col-span-2 flex items-center gap-2">
                <PrimaryButton type="submit" disabled={processing}>
                    Create goal
                </PrimaryButton>
                <button type="button" onClick={() => setOpen(false)} className="text-sm text-slate-400">
                    Cancel
                </button>
            </div>
        </form>
    );
}

function EditGoalForm({ companyId, goal, members, onDone }: { companyId: string; goal: Goal; members: Member[]; onDone: () => void }) {
    const { data, setData, patch, processing } = useForm<GoalFormData>({
        title: goal.title,
        description: goal.description ?? '',
        category: goal.category ?? '',
        subcategory: goal.subcategory ?? '',
        owner_user_id: goal.owner_user_id ?? '',
        weightage: goal.weightage !== null ? String(goal.weightage) : '',
        start_date: goal.start_date ?? '',
        end_date: goal.end_date ?? '',
        status: goal.status,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(`/platform/companies/${companyId}/goals/${goal.id}`, { onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="grid grid-cols-2 gap-3 mt-3 mb-2 bg-slate-50 rounded-xl p-4">
            <GoalFormFields data={data} setData={setData} members={members} />
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

function GoalRow({ goal, companyId, linkedKpis, members }: { goal: Goal; companyId: string; linkedKpis: LinkedKpi[]; members: Member[] }) {
    const [editing, setEditing] = useState(false);
    const contributingKpis = linkedKpis.filter((k) => k.company_goal_id === goal.id);

    return (
        <li className="py-4">
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0">
                    <p className="text-sm font-bold text-slate-800">{goal.title}</p>
                    {goal.description && <p className="text-xs text-slate-500 mt-0.5">{goal.description}</p>}
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5">
                        {goal.category && <span className="text-xs text-slate-400">{goal.category}{goal.subcategory ? ` · ${goal.subcategory}` : ''}</span>}
                        {goal.users && <span className="text-xs text-slate-400">Owner: {goal.users.name}</span>}
                        {goal.weightage !== null && <Badge tone="neutral">{goal.weightage}% weight</Badge>}
                        {contributingKpis.length > 0 && (
                            <span className="text-xs text-slate-400">
                                {contributingKpis.length} contributing KPI{contributingKpis.length === 1 ? '' : 's'}
                            </span>
                        )}
                    </div>
                </div>
                <div className="flex-none flex items-center gap-3">
                    <button onClick={() => setEditing((v) => !v)} className="text-xs font-semibold text-brand-800 hover:underline">
                        {editing ? 'Close' : 'Edit'}
                    </button>
                    <Badge tone={STATUS_TONE[goal.status]}>{STATUS_LABELS[goal.status]}</Badge>
                </div>
            </div>
            {editing && <EditGoalForm companyId={companyId} goal={goal} members={members} onDone={() => setEditing(false)} />}
        </li>
    );
}

export default function GoalsIndex({ company, goals, linkedKpis, members }: GoalsPageProps) {
    return (
        <PlatformLayout
            title="Company Goals"
            description="The company's direction — what everyone's KPIs ultimately roll up into."
            company={company}
        >
            <Card>
                <CreateGoalPanel companyId={company.id} members={members} />

                {goals.length === 0 ? (
                    <EmptyState
                        icon={<FlagIcon className="w-10 h-10" />}
                        title="No company goals yet"
                        description="Create your first goal above — Company KPIs can then be linked to it from the KPIs page."
                    />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {goals.map((goal) => (
                            <GoalRow key={goal.id} goal={goal} companyId={company.id} linkedKpis={linkedKpis} members={members} />
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
