import { router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, PrimaryButton } from '@/Components/Platform/ui';

interface PlatformUserRow {
    id: string;
    name: string;
    email: string;
    role: string;
    status: string;
}

interface ViewAsPageProps {
    users: PlatformUserRow[];
    search: string;
    [key: string]: unknown;
}

const ROLE_LABEL: Record<string, string> = {
    richworks_super_admin: 'Super Admin',
    platform_admin: 'Platform Admin',
    member: 'Member',
};

export default function ViewAsIndex({ users, search }: ViewAsPageProps) {
    const [q, setQ] = useState(search);

    const handleSearch = (e: FormEvent) => {
        e.preventDefault();
        router.get('/platform/admin/view-as', q ? { q } : {}, { preserveState: true });
    };

    const handleViewAs = (user: PlatformUserRow) => {
        if (confirm(`View the Platform as ${user.name}? This will be logged, and ends whenever you return to your own account.`)) {
            router.post(`/platform/admin/view-as/${user.id}/start`);
        }
    };

    return (
        <PlatformLayout
            title="View As"
            description="Richworks Super Admin only. Opens the Platform exactly as this user sees it, under their own real access — no password needed. Every use is logged with your name, theirs, and the time."
            maxWidth="max-w-3xl"
        >
            <Card>
                <form onSubmit={handleSearch} className="mb-4 flex gap-2">
                    <input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Search by name or email…"
                        className="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                    <PrimaryButton type="submit">Search</PrimaryButton>
                </form>

                <div className="overflow-x-auto rounded-xl border border-slate-200">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500 font-black border-b border-slate-200">
                                <th className="px-4 py-2.5">User</th>
                                <th className="px-4 py-2.5">Platform Role</th>
                                <th className="px-4 py-2.5">Status</th>
                                <th className="px-4 py-2.5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {users.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-4 py-8 text-center text-slate-400">
                                        No users found.
                                    </td>
                                </tr>
                            ) : (
                                users.map((user) => (
                                    <tr key={user.id} className="hover:bg-slate-50/60 transition">
                                        <td className="px-4 py-3">
                                            <p className="font-bold text-slate-800">{user.name}</p>
                                            <p className="text-slate-400 text-[11px]">{user.email}</p>
                                        </td>
                                        <td className="px-4 py-3 text-slate-500">{ROLE_LABEL[user.role] ?? user.role}</td>
                                        <td className="px-4 py-3">
                                            <Badge tone={user.status === 'active' ? 'success' : 'neutral'}>{user.status}</Badge>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                onClick={() => handleViewAs(user)}
                                                disabled={user.status !== 'active'}
                                                className="text-[11px] font-black px-3 py-1.5 rounded-lg bg-brand-900 text-white hover:bg-brand-800 transition disabled:opacity-40 disabled:cursor-not-allowed"
                                            >
                                                View As →
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </Card>
        </PlatformLayout>
    );
}
