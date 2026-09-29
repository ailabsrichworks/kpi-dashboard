import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, PrimaryButton, SecondaryButton } from '@/Components/Platform/ui';
import { ClockIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface Holiday {
    id: string;
    holiday_date: string;
    label: string | null;
}

interface AttendanceRow {
    external_employee_id: string;
    name: string;
    email: string;
    department: string | null;
    working_days: number;
    present_days: number;
    absent_days: number;
    late_count: number;
    total_late_minutes: number;
    insufficient_count: number;
    mc_days: number;
    al_days: number;
    other_leave_days: number;
}

interface Preview {
    token: string;
    month: number;
    year: number;
    sheetUrl: string;
    workingDaysCount: number;
    results: AttendanceRow[];
}

interface AttendancePageProps {
    company: Company;
    monthStatus: Record<string, string>;
    statusYear: number;
    holidays: Holiday[];
    defaultMonth: number;
    defaultYear: number;
    preview?: Preview;
    [key: string]: unknown;
}

const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function HolidayManager({ companyId, holidays }: { companyId: string; holidays: Holiday[] }) {
    const { data, setData, post, processing, reset } = useForm({ holiday_date: '', label: '' });

    const submit = () => {
        post(`/platform/companies/${companyId}/attendance/holidays`, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const remove = (id: string) => {
        if (!confirm('Remove this holiday?')) return;
        router.delete(`/platform/companies/${companyId}/attendance/holidays/${id}`, { preserveScroll: true });
    };

    return (
        <Card title="Public holidays" description="Excluded from working-day counts when importing attendance.">
            <div className="flex flex-wrap items-end gap-2 mb-4">
                <div>
                    <label className="block text-[10px] font-bold uppercase text-slate-400 mb-1">Date</label>
                    <input
                        type="date"
                        value={data.holiday_date}
                        onChange={(e) => setData('holiday_date', e.target.value)}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                    />
                </div>
                <div className="flex-1 min-w-40">
                    <label className="block text-[10px] font-bold uppercase text-slate-400 mb-1">Label (optional)</label>
                    <input
                        value={data.label}
                        onChange={(e) => setData('label', e.target.value)}
                        placeholder="e.g. Merdeka Day"
                        className="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                    />
                </div>
                <SecondaryButton type="button" onClick={submit} disabled={processing || !data.holiday_date}>
                    Add
                </SecondaryButton>
            </div>

            {holidays.length === 0 ? (
                <p className="text-xs text-slate-400">No public holidays recorded yet.</p>
            ) : (
                <div className="flex flex-wrap gap-2">
                    {holidays.map((h) => (
                        <span key={h.id} className="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs">
                            <span className="font-semibold text-slate-700">{h.holiday_date}</span>
                            {h.label && <span className="text-slate-400">{h.label}</span>}
                            <button onClick={() => remove(h.id)} className="text-red-500 font-bold hover:text-red-700">
                                ×
                            </button>
                        </span>
                    ))}
                </div>
            )}
        </Card>
    );
}

export default function AttendanceIndex({ company, monthStatus, statusYear, holidays, defaultMonth, defaultYear, preview }: AttendancePageProps) {
    const importForm = useForm({
        sheet_url: preview?.sheetUrl ?? '',
        month: preview?.month ?? defaultMonth,
        year: preview?.year ?? defaultYear,
    });

    const [leave, setLeave] = useState<Record<string, { mc_days: number; al_days: number; other_leave_days: number }>>(() =>
        Object.fromEntries(
            (preview?.results ?? []).map((r) => [
                r.external_employee_id,
                { mc_days: r.mc_days, al_days: r.al_days, other_leave_days: r.other_leave_days },
            ]),
        ),
    );

    const runImport = () => {
        importForm.post(`/platform/companies/${company.id}/attendance/import`, { preserveScroll: true });
    };

    const saveResults = () => {
        if (!preview) return;
        if (!confirm(`Save attendance for ${preview.results.length} employee(s)?`)) return;
        router.post(`/platform/companies/${company.id}/attendance/save`, { token: preview.token, leave }, { preserveScroll: true });
    };

    const setLeaveValue = (eid: string, field: 'mc_days' | 'al_days' | 'other_leave_days', value: string) => {
        setLeave((prev) => ({
            ...prev,
            [eid]: { ...(prev[eid] ?? { mc_days: 0, al_days: 0, other_leave_days: 0 }), [field]: parseInt(value, 10) || 0 },
        }));
    };

    return (
        <PlatformLayout
            title="Attendance"
            description="Import monthly clock-in records from a Google Sheet, review present/late/absent stats, then save."
            company={company}
            maxWidth="max-w-5xl"
        >
            <div className="space-y-6">
                <Card title={`${statusYear} at a glance`} description="Months with a saved attendance summary.">
                    <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
                        {MONTH_LABELS.map((label, i) => {
                            const month = i + 1;
                            const saved = monthStatus[String(month)];
                            return (
                                <div key={month} className={`rounded-xl border p-3 text-center ${saved ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50'}`}>
                                    <p className="text-xs font-black text-slate-700">{label}</p>
                                    <p className={`text-[10px] mt-1 ${saved ? 'text-emerald-600 font-bold' : 'text-slate-400'}`}>
                                        {saved ? 'Saved' : 'No data'}
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                </Card>

                <HolidayManager companyId={company.id} holidays={holidays} />

                <Card title="Import attendance" description="Paste a public Google Sheet link (view access for anyone with the link).">
                    <div className="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                        <div className="sm:col-span-2">
                            <label className="block text-[10px] font-bold uppercase text-slate-400 mb-1">Google Sheet URL</label>
                            <input
                                value={importForm.data.sheet_url}
                                onChange={(e) => importForm.setData('sheet_url', e.target.value)}
                                placeholder="https://docs.google.com/spreadsheets/d/..."
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                            />
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold uppercase text-slate-400 mb-1">Month</label>
                            <select
                                value={importForm.data.month}
                                onChange={(e) => importForm.setData('month', parseInt(e.target.value, 10))}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                            >
                                {MONTH_LABELS.map((label, i) => (
                                    <option key={i} value={i + 1}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold uppercase text-slate-400 mb-1">Year</label>
                            <input
                                type="number"
                                value={importForm.data.year}
                                onChange={(e) => importForm.setData('year', parseInt(e.target.value, 10) || defaultYear)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                            />
                        </div>
                    </div>
                    <div className="mt-4">
                        <PrimaryButton type="button" onClick={runImport} disabled={importForm.processing || !importForm.data.sheet_url}>
                            {importForm.processing ? 'Importing…' : 'Fetch & preview'}
                        </PrimaryButton>
                    </div>
                </Card>

                {preview && (
                    <Card
                        title={`Preview — ${MONTH_LABELS[preview.month - 1]} ${preview.year}`}
                        description={`${preview.workingDaysCount} working day(s) this month. Review MC/AL/Other, then save.`}
                        actions={<PrimaryButton type="button" onClick={saveResults}>Save {preview.results.length} employee(s)</PrimaryButton>}
                    >
                        {preview.results.length === 0 ? (
                            <EmptyState icon={<ClockIcon className="w-8 h-8" />} title="No clock-in records found for this month" />
                        ) : (
                            <div className="overflow-x-auto -mx-6">
                                <table className="w-full text-left text-xs min-w-180">
                                    <thead>
                                        <tr className="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500 font-black border-y border-slate-200">
                                            <th className="px-4 py-2">Employee</th>
                                            <th className="px-3 py-2 text-center">Present</th>
                                            <th className="px-3 py-2 text-center">Absent</th>
                                            <th className="px-3 py-2 text-center">Late</th>
                                            <th className="px-3 py-2 text-center">Insufficient</th>
                                            <th className="px-3 py-2 text-center">MC</th>
                                            <th className="px-3 py-2 text-center">AL</th>
                                            <th className="px-3 py-2 text-center">Other</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {preview.results.map((row) => {
                                            const l = leave[row.external_employee_id] ?? { mc_days: 0, al_days: 0, other_leave_days: 0 };
                                            return (
                                                <tr key={row.external_employee_id}>
                                                    <td className="px-4 py-2">
                                                        <p className="font-bold text-slate-800">{row.name}</p>
                                                        <p className="text-slate-400 text-[10px]">{row.email}</p>
                                                    </td>
                                                    <td className="px-3 py-2 text-center font-semibold text-emerald-600">{row.present_days}</td>
                                                    <td className="px-3 py-2 text-center font-semibold text-red-600">{row.absent_days}</td>
                                                    <td className="px-3 py-2 text-center">
                                                        {row.late_count > 0 ? <Badge tone="warning">{row.late_count}</Badge> : '—'}
                                                    </td>
                                                    <td className="px-3 py-2 text-center">
                                                        {row.insufficient_count > 0 ? <Badge tone="warning">{row.insufficient_count}</Badge> : '—'}
                                                    </td>
                                                    {(['mc_days', 'al_days', 'other_leave_days'] as const).map((field) => (
                                                        <td key={field} className="px-3 py-2 text-center">
                                                            <input
                                                                type="number"
                                                                min={0}
                                                                value={l[field]}
                                                                onChange={(e) => setLeaveValue(row.external_employee_id, field, e.target.value)}
                                                                className="w-14 rounded-lg border border-slate-300 px-2 py-1 text-center text-xs"
                                                            />
                                                        </td>
                                                    ))}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                )}
            </div>
        </PlatformLayout>
    );
}
