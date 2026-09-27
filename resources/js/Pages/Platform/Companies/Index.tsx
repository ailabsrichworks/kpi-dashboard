import { Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Card, EmptyState, PrimaryButton, StatusBadge } from '@/Components/Platform/ui';
import { BuildingIcon, PlusIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
    status: string;
    onboarding_status: string;
    display_name: string | null;
    primary_color: string | null;
    secondary_color: string | null;
}

interface AdminRow {
    company_id: string;
    users: { name: string; email: string };
}

interface CompaniesPageProps {
    companies: Company[];
    admins: AdminRow[];
    [key: string]: unknown;
}

function CreateCompanyForm() {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ name: '', code: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/platform/companies', {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    if (!open) {
        return (
            <PrimaryButton onClick={() => setOpen(true)} className="mb-5 inline-flex items-center gap-1.5">
                <PlusIcon className="w-4 h-4" /> New company
            </PrimaryButton>
        );
    }

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-3 mb-5 bg-slate-50 rounded-xl p-4">
            <div className="flex-1 min-w-48">
                <label className="block text-xs font-medium text-slate-600 mb-1">Company name</label>
                <input
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Andalusia"
                    required
                    autoFocus
                />
            </div>
            <div className="w-40">
                <label className="block text-xs font-medium text-slate-600 mb-1">Code</label>
                <input
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="ANDALUSIA"
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

function InviteAdminForm({ companyId }: { companyId: string }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ name: '', email: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/admins`, {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    if (!open) {
        return (
            <button onClick={() => setOpen(true)} className="text-xs font-semibold text-brand-800 hover:underline">
                + Invite Company Admin
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="flex items-end gap-2 mt-2">
            <input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Full name" className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs" required />
            <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} placeholder="Email" className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs" required />
            <button type="submit" disabled={processing} className="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60">
                Send
            </button>
            <button type="button" onClick={() => setOpen(false)} className="text-xs text-slate-400">
                Cancel
            </button>
        </form>
    );
}

function StatusActions({ company }: { company: Company }) {
    const activate = () => router.post(`/platform/companies/${company.id}/activate`);
    const suspend = () => {
        if (confirm(`Suspend ${company.name}? Its users will immediately lose access.`)) {
            router.post(`/platform/companies/${company.id}/suspend`);
        }
    };
    const reactivate = () => router.post(`/platform/companies/${company.id}/reactivate`);
    const archive = () => {
        if (confirm(`Archive ${company.name}? Its users will immediately lose access, same as suspending.`)) {
            router.post(`/platform/companies/${company.id}/archive`);
        }
    };
    const unarchive = () => router.post(`/platform/companies/${company.id}/unarchive`);

    if (company.status === 'archived') {
        return (
            <button onClick={unarchive} className="text-xs font-semibold text-emerald-600 hover:underline">
                Unarchive
            </button>
        );
    }

    if (company.status === 'suspended') {
        return (
            <div className="flex items-center gap-3">
                <button onClick={reactivate} className="text-xs font-semibold text-emerald-600 hover:underline">
                    Reactivate
                </button>
                <button onClick={archive} className="text-xs font-semibold text-slate-500 hover:underline">
                    Archive
                </button>
            </div>
        );
    }

    if (company.status === 'active') {
        return (
            <div className="flex items-center gap-3">
                <button onClick={suspend} className="text-xs font-semibold text-red-600 hover:underline">
                    Suspend
                </button>
                <button onClick={archive} className="text-xs font-semibold text-slate-500 hover:underline">
                    Archive
                </button>
            </div>
        );
    }

    return (
        <div className="flex items-center gap-3">
            <button onClick={activate} className="text-xs font-semibold text-emerald-600 hover:underline">
                Activate
            </button>
            <button onClick={suspend} className="text-xs font-semibold text-red-600 hover:underline">
                Suspend
            </button>
        </div>
    );
}

function BrandingForm({ company }: { company: Company }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing } = useForm({
        display_name: company.display_name ?? '',
        primary_color: company.primary_color ?? '',
        secondary_color: company.secondary_color ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${company.id}/branding`, { onSuccess: () => setOpen(false) });
    };

    if (!open) {
        return (
            <button onClick={() => setOpen(true)} className="text-xs font-semibold text-brand-800 hover:underline">
                Edit branding
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="flex items-end gap-2 mt-2 flex-wrap">
            <input value={data.display_name} onChange={(e) => setData('display_name', e.target.value)} placeholder="Display name" className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs" />
            <input value={data.primary_color} onChange={(e) => setData('primary_color', e.target.value)} placeholder="Primary color (#06142f)" className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs" />
            <input value={data.secondary_color} onChange={(e) => setData('secondary_color', e.target.value)} placeholder="Secondary color (#D4AF37)" className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs" />
            <button type="submit" disabled={processing} className="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60">
                Save
            </button>
            <button type="button" onClick={() => setOpen(false)} className="text-xs text-slate-400">
                Cancel
            </button>
        </form>
    );
}

export default function CompaniesIndex({ companies, admins }: CompaniesPageProps) {
    const adminsByCompany = admins.reduce<Record<string, AdminRow[]>>((acc, row) => {
        (acc[row.company_id] ??= []).push(row);
        return acc;
    }, {});

    return (
        <PlatformLayout title="Companies" description="Every client on the Platform — create, onboard, and manage their lifecycle here.">
            <Card>
                <CreateCompanyForm />

                {companies.length === 0 ? (
                    <EmptyState icon={<BuildingIcon className="w-10 h-10" />} title="No companies yet" description="Create your first company above to get started." />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {companies.map((company) => (
                            <li key={company.id} className="py-4">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-bold text-slate-800">{company.name}</p>
                                        <p className="text-xs text-slate-400">{company.code}</p>
                                    </div>
                                    <div className="flex items-center gap-4">
                                        <Link href={`/platform/companies/${company.id}/onboarding`} className="text-xs font-semibold text-brand-800 hover:underline">
                                            Onboarding
                                        </Link>
                                        <Link href={`/platform/companies/${company.id}/import`} className="text-xs font-semibold text-brand-800 hover:underline">
                                            Import
                                        </Link>
                                        <Link href={`/platform/companies/${company.id}/departments`} className="text-xs font-semibold text-brand-800 hover:underline">
                                            Departments
                                        </Link>
                                        <Link href={`/platform/companies/${company.id}/kpis`} className="text-xs font-semibold text-brand-800 hover:underline">
                                            KPIs
                                        </Link>
                                        <StatusBadge status={company.status} />
                                    </div>
                                </div>

                                <div className="mt-2.5 flex items-center gap-3">
                                    <StatusActions company={company} />
                                    <BrandingForm company={company} />
                                </div>

                                <div className="mt-2.5 space-y-1">
                                    {(adminsByCompany[company.id] ?? []).map((row, i) => (
                                        <p key={i} className="text-xs text-slate-500">
                                            Admin: {row.users.name} ({row.users.email})
                                        </p>
                                    ))}
                                    <InviteAdminForm companyId={company.id} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
