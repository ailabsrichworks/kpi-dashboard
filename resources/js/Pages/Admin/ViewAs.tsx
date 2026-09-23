import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppLayout from '../../Layouts/AppLayout';

interface FlashProps {
    flash: { error?: string | null; success?: string | null };
    [key: string]: unknown;
}

interface Employee {
    id: string;
    employee_id?: string;
    short_name?: string;
    full_name?: string;
    position?: string;
    role?: string;
    department_code?: string;
    company_code?: string;
}

interface ViewAsPageProps {
    employees: Employee[];
    deptNames: Record<string, string>;
    search: string;
}

export default function ViewAs({ employees, deptNames, search }: ViewAsPageProps) {
    const { flash } = usePage<FlashProps>().props;
    const [q, setQ] = useState(search);

    function handleSearch(e: FormEvent) {
        e.preventDefault();
        router.get('/admin/view-as', { q }, { preserveState: true });
    }

    function handleViewAs(emp: Employee) {
        const name = emp.full_name ?? emp.short_name ?? 'this employee';
        if (window.confirm(`Open ${name}'s dashboard? This will be logged.`)) {
            router.post(`/admin/view-as/${emp.id}`);
        }
    }

    return (
        <AppLayout pageTitle="View As (Employee KPI)">
            <Head title="View As · Admin" />

            <main className="p-6 max-w-4xl mx-auto space-y-4">
                <Link href="/profile" className="text-[10px] text-slate-500 hover:text-slate-800">
                    ← Profile
                </Link>

                <div>
                    <h1 className="text-xl font-black text-slate-900">View As — Employee KPI Access</h1>
                    <p className="text-[12px] text-slate-500 mt-1">
                        BTS-only. Opens an employee's dashboard directly — no password needed. Every use is logged with your name, the employee, and the time.
                    </p>
                </div>

                {flash.error && (
                    <div className="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-[12px] font-semibold text-red-700">
                        {flash.error}
                    </div>
                )}

                <form onSubmit={handleSearch} className="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
                    <input
                        type="text"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Search by name or employee ID…"
                        className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-[#6B9080]/40 focus:border-[#6B9080] focus:outline-none"
                    />
                </form>

                <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <table className="w-full text-[12px]">
                        <thead>
                            <tr className="bg-[#1a3d34] text-white text-[10px] uppercase tracking-widest">
                                <th className="text-left px-4 py-3 font-black">Employee</th>
                                <th className="text-left px-4 py-3 font-black">Position</th>
                                <th className="text-left px-4 py-3 font-black">Department</th>
                                <th className="text-left px-4 py-3 font-black">Company</th>
                                <th className="text-center px-4 py-3 font-black">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {employees.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-slate-400">
                                        No employees found.
                                    </td>
                                </tr>
                            ) : (
                                employees.map((emp) => (
                                    <tr key={emp.id} className="hover:bg-slate-50/60 transition">
                                        <td className="px-4 py-3">
                                            <p className="font-bold text-slate-800">{emp.full_name ?? emp.short_name ?? '-'}</p>
                                            <p className="text-slate-400 text-[10px]">{emp.employee_id ?? '-'}</p>
                                        </td>
                                        <td className="px-4 py-3 text-slate-500">{emp.position ?? '-'}</td>
                                        <td className="px-4 py-3 text-slate-500">{deptNames[emp.department_code ?? ''] ?? emp.department_code ?? '-'}</td>
                                        <td className="px-4 py-3 text-slate-500">{emp.company_code ?? '-'}</td>
                                        <td className="px-4 py-3 text-center">
                                            <button
                                                type="button"
                                                onClick={() => handleViewAs(emp)}
                                                className="text-[11px] font-black px-3 py-1.5 rounded-lg bg-[#1a3d34] text-white hover:bg-[#2d5548] transition"
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
            </main>
        </AppLayout>
    );
}
