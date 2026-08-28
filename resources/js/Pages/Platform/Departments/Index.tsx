import { Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useMemo, useState } from 'react';
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
    unit_type: 'business_unit' | 'branch' | 'department' | 'team';
    parent_department_id: string | null;
}

interface MemberRow {
    department_id: string;
    user_id: string;
    role: string;
    role_id: string | null;
    manager_user_id: string | null;
    position_title: string | null;
    join_date: string | null;
    employment_status: string;
    employment_type: string | null;
    employee_grade: string | null;
    location: string | null;
    mobile_number: string | null;
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
    users?: { name: string; email: string };
}

interface DepartmentsPageProps {
    company: Company;
    departments: Department[];
    members: MemberRow[];
    roles: RoleRow[];
    memberStatus: Record<string, MemberStatusRow>;
    [key: string]: unknown;
}

const UNIT_TYPE_LABELS: Record<Department['unit_type'], string> = {
    business_unit: 'Business Unit',
    branch: 'Branch',
    department: 'Department',
    team: 'Team',
};

const COMPANY_ROLE_LABELS: Record<string, string> = {
    executive: 'Executive',
    employee: 'Employee',
    hr: 'HR',
    hod: 'HOD',
    manager: 'Manager',
};

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

function CreateDepartmentForm({ companyId, departments }: { companyId: string; departments: Department[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ name: '', code: '', unit_type: 'department', parent_department_id: '' });

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
                <PlusIcon className="w-4 h-4" /> New organisation unit
            </PrimaryButton>
        );
    }

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-3 mb-5 bg-slate-50 rounded-xl p-4">
            <div className="flex-1 min-w-40">
                <label className="block text-xs font-medium text-slate-600 mb-1">Name</label>
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
            <div className="w-40">
                <label className="block text-xs font-medium text-slate-600 mb-1">Type</label>
                <select
                    value={data.unit_type}
                    onChange={(e) => setData('unit_type', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    {Object.entries(UNIT_TYPE_LABELS).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
            </div>
            <div className="w-48">
                <label className="block text-xs font-medium text-slate-600 mb-1">Parent unit</label>
                <select
                    value={data.parent_department_id}
                    onChange={(e) => setData('parent_department_id', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="">None (top level)</option>
                    {departments.map((d) => (
                        <option key={d.id} value={d.id}>
                            {d.name}
                        </option>
                    ))}
                </select>
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

function InviteDepartmentUserForm({
    companyId,
    departmentId,
    roles,
    companyMembers,
}: {
    companyId: string;
    departmentId: string;
    roles: RoleRow[];
    companyMembers: { user_id: string; name: string }[];
}) {
    const [open, setOpen] = useState(false);
    const [showMoreDetails, setShowMoreDetails] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        name: '',
        email: '',
        role: 'employee',
        role_id: roles[0]?.id ?? '',
        manager_user_id: '',
        position_title: '',
        join_date: '',
        employment_type: '',
        employee_grade: '',
        location: '',
        mobile_number: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/departments/${departmentId}/users`, {
            onSuccess: () => {
                reset();
                setOpen(false);
                setShowMoreDetails(false);
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
        <form onSubmit={submit} className="mt-2 space-y-2">
            <div className="flex items-end gap-2 flex-wrap">
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
                    onChange={(e) => setData('role', e.target.value)}
                    className="rounded-lg border border-slate-300 px-2 py-1.5 text-xs"
                    title="Access level — what they can do in this admin console"
                >
                    {Object.entries(COMPANY_ROLE_LABELS).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
                <select
                    value={data.manager_user_id}
                    onChange={(e) => setData('manager_user_id', e.target.value)}
                    className="rounded-lg border border-slate-300 px-2 py-1.5 text-xs"
                    title="Reports to"
                >
                    <option value="">No manager</option>
                    {companyMembers.map((m) => (
                        <option key={m.user_id} value={m.user_id}>
                            Reports to {m.name}
                        </option>
                    ))}
                </select>
                <button type="submit" disabled={processing || roles.length === 0} className="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60">
                    Send invite
                </button>
                <button type="button" onClick={() => setOpen(false)} className="text-xs text-slate-400">
                    Cancel
                </button>
            </div>

            <button type="button" onClick={() => setShowMoreDetails((v) => !v)} className="text-xs text-slate-400 hover:underline">
                {showMoreDetails ? '− Fewer details' : '+ Position, join date, grade…'}
            </button>

            {showMoreDetails && (
                <div className="flex items-end gap-2 flex-wrap">
                    <input
                        value={data.position_title}
                        onChange={(e) => setData('position_title', e.target.value)}
                        placeholder="Position title"
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                    />
                    <input
                        type="date"
                        value={data.join_date}
                        onChange={(e) => setData('join_date', e.target.value)}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                        title="Join date"
                    />
                    <input
                        value={data.employment_type}
                        onChange={(e) => setData('employment_type', e.target.value)}
                        placeholder="Employment type"
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs w-32"
                    />
                    <input
                        value={data.employee_grade}
                        onChange={(e) => setData('employee_grade', e.target.value)}
                        placeholder="Grade"
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs w-24"
                    />
                    <input
                        value={data.location}
                        onChange={(e) => setData('location', e.target.value)}
                        placeholder="Location"
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                    />
                    <input
                        value={data.mobile_number}
                        onChange={(e) => setData('mobile_number', e.target.value)}
                        placeholder="Mobile number"
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                    />
                </div>
            )}
        </form>
    );
}

function EditReportingControl({
    companyId,
    departmentId,
    row,
    companyMembers,
}: {
    companyId: string;
    departmentId: string;
    row: MemberRow;
    companyMembers: { user_id: string; name: string }[];
}) {
    const [open, setOpen] = useState(false);
    const [managerUserId, setManagerUserId] = useState(row.manager_user_id ?? '');

    const save = () => {
        router.patch(
            `/platform/companies/${companyId}/departments/${departmentId}/users/${row.user_id}/reporting`,
            { manager_user_id: managerUserId || null },
            { onSuccess: () => setOpen(false), preserveScroll: true },
        );
    };

    const managerName = companyMembers.find((m) => m.user_id === row.manager_user_id)?.name;

    if (!open) {
        return (
            <button onClick={() => setOpen(true)} className="text-slate-400 hover:underline hover:text-slate-600">
                {managerName ? `Reports to ${managerName}` : 'No manager set'}
            </button>
        );
    }

    return (
        <span className="inline-flex items-center gap-1">
            <select value={managerUserId} onChange={(e) => setManagerUserId(e.target.value)} className="rounded-lg border border-slate-300 px-1.5 py-0.5 text-xs">
                <option value="">No manager</option>
                {companyMembers
                    .filter((m) => m.user_id !== row.user_id)
                    .map((m) => (
                        <option key={m.user_id} value={m.user_id}>
                            {m.name}
                        </option>
                    ))}
            </select>
            <button onClick={save} className="text-brand-800 font-semibold hover:underline">
                Save
            </button>
            <button onClick={() => setOpen(false)} className="text-slate-400">
                ✕
            </button>
        </span>
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

    const childrenByParent = useMemo(() => {
        const map: Record<string, Department[]> = {};
        departments.forEach((d) => {
            const key = d.parent_department_id ?? '__root__';
            (map[key] ??= []).push(d);
        });
        return map;
    }, [departments]);

    // A company-wide, name-sorted list for the manager pickers — a manager
    // may sit in a different organisation unit than the person reporting to
    // them, so this deliberately isn't scoped to one department.
    const companyMembers = useMemo(
        () =>
            Object.values(memberStatus)
                .filter((m) => m.users)
                .map((m) => ({ user_id: m.user_id, name: m.users!.name }))
                .sort((a, b) => a.name.localeCompare(b.name)),
        [memberStatus],
    );

    const renderDepartmentNode = (department: Department, depth: number) => {
        const children = childrenByParent[department.id] ?? [];

        return (
            <li key={department.id} className="py-4" style={{ marginLeft: depth * 24 }}>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        {depth > 0 && <span className="text-slate-300">└</span>}
                        <div>
                            <p className="text-sm font-bold text-slate-800">{department.name}</p>
                            <p className="text-xs text-slate-400">
                                {department.code} · {UNIT_TYPE_LABELS[department.unit_type] ?? department.unit_type}
                            </p>
                        </div>
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
                            <div key={i} className="flex flex-wrap items-center gap-2 text-xs">
                                <span className="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-brand-100 text-brand-900 font-bold text-[10px] uppercase">
                                    {row.users.name.slice(0, 1)}
                                </span>
                                <span className="text-slate-600">
                                    {row.users.name} <span className="text-slate-400">· {row.users.email}</span>
                                    {row.position_title && <span className="text-slate-400"> · {row.position_title}</span>}
                                </span>
                                <Badge tone={row.role === 'executive' || row.role === 'hod' ? 'brand' : 'neutral'}>{COMPANY_ROLE_LABELS[row.role] ?? row.role}</Badge>
                                {status === 'suspended' && <Badge tone="danger">Suspended</Badge>}
                                <EditReportingControl companyId={company.id} departmentId={department.id} row={row} companyMembers={companyMembers} />
                                <SuspendMemberToggle companyId={company.id} userId={row.user_id} status={status} />
                            </div>
                        );
                    })}
                    <div className="pt-1">
                        <InviteDepartmentUserForm companyId={company.id} departmentId={department.id} roles={rolesByDepartment[department.id] ?? []} companyMembers={companyMembers} />
                    </div>
                </div>

                {children.length > 0 && (
                    <ul className="mt-2 divide-y divide-slate-100 border-l border-slate-100 pl-2">
                        {children.map((child) => renderDepartmentNode(child, depth + 1))}
                    </ul>
                )}
            </li>
        );
    };

    const rootDepartments = childrenByParent['__root__'] ?? [];

    return (
        <PlatformLayout title="Organisation & People" description="Your organisation structure, reporting lines, and who works where." company={company}>
            <Card>
                <CreateDepartmentForm companyId={company.id} departments={departments} />

                {departments.length === 0 ? (
                    <EmptyState icon={<UsersIcon className="w-10 h-10" />} title="No organisation units yet" description="Create your first department above to start adding people." />
                ) : (
                    <ul className="divide-y divide-slate-100">{rootDepartments.map((department) => renderDepartmentNode(department, 0))}</ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
