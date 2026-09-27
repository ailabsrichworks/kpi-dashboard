import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';
import { SharedPageProps } from '../types';
import { formatDate } from '../lib/dates';

interface Employee {
    short_name?: string;
    full_name?: string;
    position?: string;
    role?: string;
    department_code?: string;
    company_code?: string;
    salutation?: string;
    employee_id?: string;
    email?: string;
    join_date?: string;
}

interface Manager {
    short_name?: string;
    full_name?: string;
    position?: string;
}

interface Department {
    name?: string;
}

interface ProfilePageProps {
    user: Employee;
    manager: Manager | null;
    department: Department | null;
}

export default function Profile({ user, manager, department }: ProfilePageProps) {
    const { flash, layout } = usePage<SharedPageProps>().props;

    const details: { label: string; value: string; hint?: string | null }[] = [
        { label: 'Employee ID', value: user.employee_id ?? '-' },
        { label: 'Email', value: user.email ?? '-' },
        { label: 'Department', value: department?.name ?? user.department_code ?? '-' },
        { label: 'Company', value: layout.companyDisplayName ?? user.company_code ?? '-' },
        {
            label: 'Reports To',
            value: manager?.short_name ?? manager?.full_name ?? '-',
            hint: manager?.position ?? null,
        },
        { label: 'Join Date', value: user.join_date ? formatDate(user.join_date) : '-' },
    ];

    return (
        <AppLayout pageTitle="My Profile">
            <Head title="My Profile" />

            <div className="sticky top-0 z-30 px-4 pt-4 pb-2 bg-[#F5F5F3]">
                <div className="relative overflow-hidden rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-5 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex items-center justify-between gap-4">
                    <div className="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10" />
                    <div className="pointer-events-none absolute -top-10 -right-10 w-48 h-48 rounded-full bg-[#D4AF37]/10 blur-3xl" />

                    <div className="relative">
                        <Link href="/dashboard" className="text-[11px] text-[#D4AF37] hover:text-white transition">
                            ← Dashboard
                        </Link>
                        <h1 className="text-2xl font-black tracking-tight mt-1">My Profile</h1>
                        <p className="text-white/70 text-xs mt-1">Who you are on the system</p>
                    </div>
                </div>
            </div>

            <div className="px-4 pb-6 max-w-3xl mx-auto space-y-4">
                {flash.success && (
                    <div className="rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-[12px] font-semibold text-emerald-700">
                        ✓ {flash.success}
                    </div>
                )}
                {flash.error && (
                    <div className="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-[12px] font-semibold text-red-700">
                        {flash.error}
                    </div>
                )}

                {/* IDENTITY CARD */}
                <div className="bg-white rounded-2xl overflow-hidden shadow-sm border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37]">
                    <div className="p-6 flex items-center gap-5">
                        <div className="w-20 h-20 rounded-full overflow-hidden shrink-0 ring-4 ring-[#D4AF37]/25">
                            <img
                                src={`https://ui-avatars.com/api/?name=${encodeURIComponent(user.short_name ?? user.full_name ?? 'User')}&background=7A0019&color=fff&size=80`}
                                className="w-full h-full object-cover"
                                alt="Avatar"
                            />
                        </div>
                        <div className="min-w-0">
                            <h2 className="text-xl font-black text-slate-900 leading-tight truncate">
                                {user.salutation ? `${user.salutation} ` : ''}
                                {user.full_name ?? user.short_name ?? '-'}
                            </h2>
                            <p className="text-sm text-slate-500 mt-0.5">{user.position ?? '-'}</p>
                            <div className="flex flex-wrap gap-1.5 mt-3">
                                <span className="text-[10px] font-black uppercase tracking-wide px-2.5 py-1 rounded-full bg-[#FBF5EF] text-[#6B3F2A] border border-[#6B3F2A]/20">
                                    {user.role ?? '-'}
                                </span>
                                <span className="text-[10px] font-black uppercase tracking-wide px-2.5 py-1 rounded-full bg-slate-100 text-slate-600">
                                    {department?.name ?? user.department_code ?? '-'}
                                </span>
                                <span className="text-[10px] font-black uppercase tracking-wide px-2.5 py-1 rounded-full bg-slate-100 text-slate-600">
                                    {layout.companyDisplayName ?? user.company_code ?? '-'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* DETAILS */}
                <div className="bg-white rounded-2xl shadow-sm border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] p-6">
                    <p className="text-[10px] uppercase tracking-widest font-black text-slate-400 mb-4">Employee Details</p>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {details.map((d) => (
                            <div key={d.label} className="rounded-2xl bg-slate-50 border border-slate-100 px-4 py-3">
                                <p className="text-[10px] uppercase tracking-wide text-slate-400 font-bold">{d.label}</p>
                                <p className="text-[13px] font-black text-slate-800 mt-0.5 truncate">
                                    {d.value}
                                    {d.hint && <span className="text-slate-400 font-semibold"> · {d.hint}</span>}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
