import { FormEventHandler, useState } from 'react';
import { useForm } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Card, EmptyState, PrimaryButton, SecondaryButton } from '@/Components/Platform/ui';
import { IdentificationIcon } from '@/Components/Platform/Icons';

interface UserRef {
    id: string;
    name: string;
    email: string;
}

interface CompanyRow {
    id: string;
    name: string;
    code: string;
    cam: { cam_user_id: string; users: { name: string } } | null;
}

interface RosterRow {
    cam_user_id: string;
    cam_name: string;
    account_count: number;
}

interface CamPageProps {
    companies: CompanyRow[];
    roster: RosterRow[];
    users: UserRef[];
    [key: string]: unknown;
}

function AssignForm({ company, users, onDone }: { company: CompanyRow; users: UserRef[]; onDone: () => void }) {
    const { data, setData, post, processing } = useForm({ cam_user_id: company.cam?.cam_user_id ?? '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/hq/companies/${company.id}/cam`, { onSuccess: onDone, preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="flex items-center gap-2">
            <select value={data.cam_user_id} onChange={(e) => setData('cam_user_id', e.target.value)} className="rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                <option value="">Select CAM…</option>
                {users.map((u) => (
                    <option key={u.id} value={u.id}>
                        {u.name}
                    </option>
                ))}
            </select>
            <PrimaryButton type="submit" disabled={processing || !data.cam_user_id} className="text-xs px-3 py-1.5">
                Assign
            </PrimaryButton>
            <SecondaryButton type="button" onClick={onDone} className="text-xs px-3 py-1.5">
                Cancel
            </SecondaryButton>
        </form>
    );
}

export default function CamIndex({ companies, roster, users }: CamPageProps) {
    const [editingId, setEditingId] = useState<string | null>(null);

    return (
        <PlatformLayout title="CAM Team" description="Client Account Manager assignment and workload.">
            {roster.length > 0 && (
                <Card title="Workload" className="mb-5">
                    <ul className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        {roster.map((r) => (
                            <li key={r.cam_user_id} className="text-center">
                                <p className="text-2xl font-bold tabular-nums text-slate-800">{r.account_count}</p>
                                <p className="text-xs text-slate-400">{r.cam_name}</p>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            <Card title="Accounts">
                {companies.length === 0 ? (
                    <EmptyState icon={<IdentificationIcon className="w-10 h-10" />} title="No companies yet" />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {companies.map((c) => (
                            <li key={c.id} className="flex items-center justify-between gap-4 py-3.5">
                                <div>
                                    <p className="text-sm font-semibold text-slate-800">{c.name}</p>
                                    <p className="text-xs text-slate-400">{c.code}</p>
                                </div>
                                {editingId === c.id ? (
                                    <AssignForm company={c} users={users} onDone={() => setEditingId(null)} />
                                ) : (
                                    <button onClick={() => setEditingId(c.id)} className="text-xs font-semibold text-brand-800 hover:underline">
                                        {c.cam ? `CAM: ${c.cam.users.name}` : 'Assign a CAM'}
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
