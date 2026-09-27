import { Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, PrimaryButton } from '@/Components/Platform/ui';
import { PlusIcon, UsersIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface Department {
    id: string;
    name: string;
    code: string;
    status: string;
}

interface MemberRow {
    department_id: string;
    user_id: string;
    role: string;
    role_id: string | null;
    users: { name: string; email: string };
}

interface RoleRow {
    id: string;
    department_id: string;
    label: string;
    rank: number;
}

interface MemberStatusRow {
    user_id: string;
    role: string;
    status: string;
}

interface DepartmentsPageProps {
    company: Company;
    departments: Department[];
    members: MemberRow[];
    roles: RoleRow[];
    memberStatus: Record<string, MemberStatusRow>;
    [key: string]: unknown;
}

function SuspendMemberToggle({ companyId, userId, status }: { companyId: string; userId: string; status: string }) {
    const isSuspended = status === 'suspended';

    const toggle = () => {
        const verb = isSuspended ? 'reactivate' : 'suspend';
        if (!confirm(`${isSuspended ? 'Reactivate' : 'Suspend'} this member's access to this company?`)) {
            return;
        }
        router.post(`/platform/companies/${companyId}/users/${userId}/${verb}`);
    };

    return (
        <button onClick={toggle} className={`text-xs font-semibold hover:underline ${isSuspended ? 'text-emerald-600' : 'text-slate-400'}`}>
            {isSuspended ? 'Reactivate' : 'Suspend'}
        </button>
    );
}

function CreateDepartmentForm({ companyId }: { companyId: string }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ name: '', code: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/departments`, {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    if (!open) {
        return (
            <PrimaryButton onClick={() => setOpen(true)} className="mb-5 inline-flex items-center gap-1.5">
                <PlusIcon className="w-4 h-4" /> New department
            </PrimaryButton>
        );
    }

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-3 mb-5 bg-slate-50 rounded-xl p-4">
            <div className="flex-1 min-w-40">
                <label className="block text-xs font-medium text-slate-600 mb-1">Department name</label>
                <input
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Finance"
                    required
                    autoFocus
                />
            </div>
            <div className="w-32">
                <label className="block text-xs font-medium text-slate-600 mb-1">Code</label>
                <input
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="FIN"
                    required
                />
            </div>
            <PrimaryButton type="submit" disabled={processing}>
                Create
            </PrimaryButton>
            <button type="button" onClick={() => setOpen(false)} className="text-sm text-slate-400 pb-2.5">
                Cancel
            </button>
        </form>
    );
}

function InviteDepartmentUserForm({ companyId, departmentId, roles }: { companyId: string; departmentId: string; roles: RoleRow[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        name: '',
        email: '',
        role: 'employee' as 'employee' | 'executive',
        role_id: roles[0]?.id ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/departments/${departmentId}/users`, {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    // A role added via RolesManager after this form already mounted (e.g.
    // the department's very first role) wouldn't otherwise be picked up,
    // since useForm only sets its initial value once.
    useEffect(() => {
        if (!roles.some((r) => r.id === data.role_id)) {
            setData('role_id', roles[0]?.id ?? '');
        }
    }, [roles]);

    if (!open) {
        return (
            <button onClick={() => setOpen(true)} className="text-xs font-semibold text-brand-800 hover:underline">
                + Add person
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="flex items-end gap-2 mt-2 flex-wrap">
            <input
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                placeholder="Full name"
                className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                required
            />
            <input
                type="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                placeholder="Email"
                className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                required
            />
            <select
                value={data.role_id}
                onChange={(e) => setData('role_id', e.target.value)}
                className="rounded-lg border border-slate-300 px-2 py-1.5 text-xs"
                title="Position — this company's own department role"
                required
            >
                {roles.length === 0 && <option value="">No roles yet — add one below first</option>}
                {roles.map((r) => (
                    <option key={r.id} value={r.id}>
                        {r.label}
                    </option>
                ))}
            </select>
            <select
                value={data.role}
                onChange={(e) => setData('role', e.target.value as 'employee' | 'executive')}
                className="rounded-lg border border-slate-300 px-2 py-1.5 text-xs"
                title="Access level — what they can do in this admin console"
            >
                <option value="employee">Employee</option>
                <option value="executive">Executive</option>
            </select>
            <button type="submit" disabled={processing || roles.length === 0} className="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60">
                Send invite
            </button>
            <button type="button" onClick={() => setOpen(false)} className="text-xs text-slate-400">
                Cancel
            </button>
        </form>
    );
}

function RolesManager({ companyId, departmentId, roles }: { companyId: string; departmentId: string; roles: RoleRow[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ label: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/departments/${departmentId}/roles`, {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    const removeRole = (roleId: string, label: string) => {
        if (!confirm(`Remove the "${label}" role? This only works if no one currently holds it.`)) {
            return;
        }
        router.delete(`/platform/companies/${companyId}/departments/${departmentId}/roles/${roleId}`);
    };

    return (
        <div className="mt-3 flex flex-wrap items-center gap-1.5">
            <span className="text-xs text-slate-400 mr-1">Job levels:</span>
            {roles.map((r) => (
                <span key={r.id} className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-600">
                    {r.label}
                    <button onClick={() => removeRole(r.id, r.label)} className="text-slate-400 hover:text-red-500" title={`Remove ${r.label}`}>
                        ×
                    </button>
                </span>
            ))}
            {open ? (
                <form onSubmit={submit} className="inline-flex items-center gap-1.5">
                    <input
                        value={data.label}
                        onChange={(e) => setData('label', e.target.value)}
                        placeholder="e.g. Lead"
                        className="rounded-full border border-slate-300 px-2.5 py-0.5 text-xs w-24"
                        autoFocus
                        required
                    />
                    <button type="submit" disabled={processing} className="text-xs font-semibold text-brand-800">
                        Add
                    </button>
                    <button type="button" onClick={() => setOpen(false)} className="text-xs text-slate-400">
                        ✕
                    </button>
                </form>
            ) : (
                <button onClick={() => setOpen(true)} className="text-xs font-semibold text-brand-800 hover:underline">
                    + Add job level
                </button>
            )}
        </div>
    );
}

export default function DepartmentsIndex({ company, departments, members, roles, memberStatus }: DepartmentsPageProps) {
    const membersByDepartment = members.reduce<Record<string, MemberRow[]>>((acc, row) => {
        (acc[row.department_id] ??= []).push(row);
        return acc;
    }, {});

    const rolesByDepartment = roles.reduce<Record<string, RoleRow[]>>((acc, row) => {
        (acc[row.department_id] ??= []).push(row);
        return acc;
    }, {});

    return (
        <PlatformLayout title="Departments & People" description="Who works where, and what they're allowed to do." company={company}>
            <Card>
                <CreateDepartmentForm companyId={company.id} />

                {departments.length === 0 ? (
                    <EmptyState icon={<UsersIcon className="w-10 h-10" />} title="No departments yet" description="Create your first department above to start adding people." />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {departments.map((department) => (
                            <li key={department.id} className="py-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-bold text-slate-800">{department.name}</p>
                                        <p className="text-xs text-slate-400">{department.code}</p>
                                    </div>
                                    <div className="flex items-center gap-4">
                                        <Link href={`/platform/companies/${company.id}/departments/${department.id}/submissions`} className="text-xs font-semibold text-brand-800 hover:underline">
                                            KPI submissions
                                        </Link>
                                        <Badge tone={department.status === 'active' ? 'success' : 'neutral'}>{department.status}</Badge>
                                    </div>
                                </div>

                                <RolesManager companyId={company.id} departmentId={department.id} roles={rolesByDepartment[department.id] ?? []} />

                                <div className="mt-3 space-y-1.5">
                                    {(membersByDepartment[department.id] ?? []).map((row, i) => {
                                        const status = memberStatus[row.user_id]?.status ?? 'active';
                                        return (
                                            <div key={i} className="flex items-center gap-2 text-xs">
                                                <span className="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-brand-100 text-brand-900 font-bold text-[10px] uppercase">
                                                    {row.users.name.slice(0, 1)}
                                                </span>
                                                <span className="text-slate-600">
                                                    {row.users.name} <span className="text-slate-400">· {row.users.email}</span>
                                                </span>
                                                <Badge tone={row.role === 'executive' ? 'brand' : 'neutral'}>
                                                    {row.role === 'executive' ? 'Executive' : 'Employee'}
                                                </Badge>
                                                {status === 'suspended' && <Badge tone="danger">Suspended</Badge>}
                                                <SuspendMemberToggle companyId={company.id} userId={row.user_id} status={status} />
                                            </div>
                                        );
                                    })}
                                    <div className="pt-1">
                                        <InviteDepartmentUserForm companyId={company.id} departmentId={department.id} roles={rolesByDepartment[department.id] ?? []} />
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
