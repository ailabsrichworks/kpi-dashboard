import { FormEventHandler, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Card, EmptyState, PrimaryButton } from '@/Components/Platform/ui';
import { ChecklistIcon, PlusIcon } from '@/Components/Platform/Icons';

interface CompanyRef {
    id: string;
    name: string;
    code: string;
}

interface CamUserRef {
    id: string;
    name: string;
}

type ActionStatus = 'open' | 'in_progress' | 'done' | 'cancelled';

interface ActionRow {
    id: string;
    title: string;
    description: string | null;
    due_date: string | null;
    status: ActionStatus;
    companies: { name: string; code: string } | null;
    cam_name: string;
}

interface ActionsPageProps {
    actions: ActionRow[];
    companies: CompanyRef[];
    camUsers: CamUserRef[];
    [key: string]: unknown;
}

function CreateActionForm({ companies, camUsers, onDone }: { companies: CompanyRef[]; camUsers: CamUserRef[]; onDone: () => void }) {
    const { data, setData, post, processing, reset } = useForm({
        company_id: '',
        cam_user_id: '',
        title: '',
        description: '',
        due_date: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/platform/hq/actions', { onSuccess: () => { reset(); onDone(); } });
    };

    return (
        <form onSubmit={submit} className="grid grid-cols-2 gap-3 mb-5 bg-slate-50 rounded-xl p-4">
            <select value={data.company_id} onChange={(e) => setData('company_id', e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                <option value="">Company…</option>
                {companies.map((c) => (
                    <option key={c.id} value={c.id}>
                        {c.name}
                    </option>
                ))}
            </select>
            <select value={data.cam_user_id} onChange={(e) => setData('cam_user_id', e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                <option value="">Owner (CAM)…</option>
                {camUsers.map((u) => (
                    <option key={u.id} value={u.id}>
                        {u.name}
                    </option>
                ))}
            </select>
            <input
                value={data.title}
                onChange={(e) => setData('title', e.target.value)}
                placeholder="Review Corporate Sales Pipeline"
                className="col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                required
            />
            <textarea
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                placeholder="Details (optional)"
                className="col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                rows={2}
            />
            <input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            <div className="flex items-center gap-2">
                <PrimaryButton type="submit" disabled={processing}>
                    Create action
                </PrimaryButton>
                <button type="button" onClick={onDone} className="text-sm text-slate-400">
                    Cancel
                </button>
            </div>
        </form>
    );
}

export default function CamActions({ actions, companies, camUsers }: ActionsPageProps) {
    const [creating, setCreating] = useState(false);

    function setStatus(id: string, status: ActionStatus) {
        router.patch(`/platform/hq/actions/${id}`, { status }, { preserveScroll: true });
    }

    return (
        <PlatformLayout title="Actions" description="CAM action items across every account.">
            <Card>
                {creating ? (
                    <CreateActionForm companies={companies} camUsers={camUsers} onDone={() => setCreating(false)} />
                ) : (
                    <PrimaryButton onClick={() => setCreating(true)} className="mb-5 inline-flex items-center gap-1.5">
                        <PlusIcon className="w-4 h-4" /> New action
                    </PrimaryButton>
                )}

                {actions.length === 0 ? (
                    <EmptyState icon={<ChecklistIcon className="w-10 h-10" />} title="No CAM actions yet" />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {actions.map((a) => (
                            <li key={a.id} className="py-3.5">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-sm font-semibold text-slate-800">{a.title}</p>
                                        {a.description && <p className="text-xs text-slate-500 mt-0.5">{a.description}</p>}
                                        <p className="text-[11px] text-slate-400 mt-1">
                                            {a.companies?.name} · Owner: {a.cam_name}
                                            {a.due_date && <> · Due {a.due_date}</>}
                                        </p>
                                    </div>
                                    <select
                                        value={a.status}
                                        onChange={(e) => setStatus(a.id, e.target.value as ActionStatus)}
                                        className="text-xs rounded-lg border border-slate-300 px-2 py-1"
                                    >
                                        <option value="open">Open</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="done">Done</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
