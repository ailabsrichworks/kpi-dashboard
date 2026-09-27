import { router } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Card, EmptyState, FilterBar, PerformanceStatusBadge } from '@/Components/Platform/ui';
import { UsersIcon } from '@/Components/Platform/Icons';
import { PerformanceStatus } from '@/lib/performanceStatus';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface Department {
    id: string;
    name: string;
}

interface EmployeeRow {
    user_id: string;
    name: string;
    email: string | null;
    position_title: string | null;
    department_name: string | null;
    manager_name: string | null;
    employment_status: string | null;
    score: number | null;
    status: PerformanceStatus;
}

interface EmployeesPageProps {
    company: Company;
    departments: Department[];
    employees: EmployeeRow[];
    filters: { department_id: string; status: string };
    [key: string]: unknown;
}

export default function PeopleEmployees({ company, departments, employees, filters }: EmployeesPageProps) {
    function updateFilter(key: string, value: string) {
        const url = new URL(window.location.href);
        if (value) url.searchParams.set(key, value);
        else url.searchParams.delete(key);
        router.get(url.pathname + url.search, {}, { preserveScroll: true, preserveState: true });
    }

    return (
        <PlatformLayout title="Employee Performance" description="Filterable view of everyone's current standing." company={company} maxWidth="max-w-6xl">
            <div className="mb-4">
                <FilterBar
                    filters={[
                        { key: 'department_id', label: 'Department', value: filters.department_id, options: departments.map((d) => ({ value: d.id, label: d.name })) },
                        {
                            key: 'status',
                            label: 'Performance',
                            value: filters.status,
                            options: [
                                { value: 'exceeded', label: 'Exceeded' },
                                { value: 'on_track', label: 'On Track' },
                                { value: 'at_risk', label: 'At Risk' },
                                { value: 'critical', label: 'Critical' },
                                { value: 'no_data', label: 'No data yet' },
                            ],
                        },
                    ]}
                    onChange={updateFilter}
                />
            </div>

            <Card>
                {employees.length === 0 ? (
                    <EmptyState icon={<UsersIcon className="w-10 h-10" />} title="No employees match these filters" />
                ) : (
                    <div className="overflow-x-auto -mx-6">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-[11px] uppercase tracking-wide text-slate-400 border-b border-slate-100">
                                    <th className="px-6 py-2 font-semibold">Employee</th>
                                    <th className="px-3 py-2 font-semibold">Position</th>
                                    <th className="px-3 py-2 font-semibold">Department</th>
                                    <th className="px-3 py-2 font-semibold">Manager</th>
                                    <th className="px-3 py-2 font-semibold">Performance</th>
                                    <th className="px-6 py-2 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {employees.map((e) => (
                                    <tr key={e.user_id} className="border-b border-slate-50 last:border-0">
                                        <td className="px-6 py-2.5">
                                            <p className="font-medium text-slate-800">{e.name}</p>
                                            {e.email && <p className="text-xs text-slate-400">{e.email}</p>}
                                        </td>
                                        <td className="px-3 py-2.5 text-slate-600">{e.position_title ?? '—'}</td>
                                        <td className="px-3 py-2.5 text-slate-600">{e.department_name ?? '—'}</td>
                                        <td className="px-3 py-2.5 text-slate-600">{e.manager_name ?? '—'}</td>
                                        <td className="px-3 py-2.5 font-bold tabular-nums text-slate-700">{e.score !== null ? `${e.score.toFixed(0)}%` : '—'}</td>
                                        <td className="px-6 py-2.5">
                                            <PerformanceStatusBadge status={e.status} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </PlatformLayout>
    );
}
