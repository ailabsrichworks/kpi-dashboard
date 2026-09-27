import { useState } from 'react';
import { Doughnut } from 'react-chartjs-2';
import '../../lib/chartSetup';
import { scoreHex, scoreStyle } from '../../lib/scoreStyle';

interface StaffRow {
    employee_id: string;
    name: string;
    role: string;
    kpi_count: number;
    performance: number;
    q1: number;
    q2: number;
    q3: number;
    q4: number;
}

interface DeptRow {
    department_code: string;
    staff_count: number;
    kpi_count: number;
    performance: number;
    risk_count: number;
    q1: number;
    q2: number;
    q3: number;
    q4: number;
    staff_list: StaffRow[];
}

interface DepartmentAccordionProps {
    deptRows: DeptRow[];
    isSltOffice: boolean;
    currentUserName: string;
}

const ROLE_COLORS: Record<string, string> = {
    SLT: 'bg-purple-100 text-purple-700',
    VP: 'bg-[#F5EAE0] text-[#6B3F2A]',
    MANAGER: 'bg-indigo-100 text-indigo-700',
    EXECUTIVE: 'bg-slate-100 text-slate-600',
};

function safeCode(code: string): string {
    return code.replace(/[^A-Za-z0-9]/g, '_');
}

export default function DepartmentAccordion({ deptRows, isSltOffice, currentUserName }: DepartmentAccordionProps) {
    const [openDepts, setOpenDepts] = useState<Set<string>>(new Set());
    const [allOpen, setAllOpen] = useState(false);

    if (deptRows.length === 0) return null;

    function toggleDept(code: string) {
        setOpenDepts((prev) => {
            const next = new Set(prev);
            if (next.has(code)) next.delete(code);
            else next.add(code);
            return next;
        });
    }

    function toggleAll() {
        const next = !allOpen;
        setAllOpen(next);
        setOpenDepts(next ? new Set(deptRows.map((d) => d.department_code)) : new Set());
    }

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-sm font-black text-slate-900">Department Staff Breakdown</h2>
                    <p className="text-[10px] text-slate-400 mt-0.5">
                        All staff · quarterly scores · sorted by annual achievement{isSltOffice && ' · click a staff row for full KPI breakdown'}
                    </p>
                </div>
                <button type="button" onClick={toggleAll} className="px-3 py-1.5 bg-slate-100 text-slate-700 rounded-xl text-xs font-black hover:bg-slate-200 transition">
                    {allOpen ? 'Collapse All' : 'Expand All'}
                </button>
            </div>

            {deptRows.map((dept) => {
                const dstyle = scoreStyle(dept.performance);
                const code = safeCode(dept.department_code);
                const isOpen = openDepts.has(dept.department_code);

                return (
                    <div key={dept.department_code} className="bg-white rounded-2xl border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37] overflow-hidden shadow-sm">
                        <div
                            className="flex items-center justify-between px-4 py-3 cursor-pointer select-none hover:bg-slate-50/60 transition"
                            onClick={() => toggleDept(dept.department_code)}
                        >
                            <div className="flex items-center gap-3">
                                <div className="relative w-10 h-10 shrink-0">
                                    <Doughnut
                                        data={{
                                            datasets: [
                                                {
                                                    data: [dept.performance, Math.max(0, 100 - dept.performance)],
                                                    backgroundColor: [scoreHex(dept.performance), '#f1f5f9'],
                                                    borderWidth: 0,
                                                },
                                            ],
                                        }}
                                        options={{ cutout: '68%', responsive: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, events: [] }}
                                        width={40}
                                        height={40}
                                    />
                                    <div className="absolute inset-0 flex items-center justify-center">
                                        <span className={`text-[8px] font-black ${dstyle.text} leading-tight text-center`}>{dept.performance.toFixed(1)}%</span>
                                    </div>
                                </div>
                                <div>
                                    <h3 className="text-xs font-black text-slate-900">{dept.department_code}</h3>
                                    <p className="text-[9px] text-slate-400">
                                        {dept.staff_count} staff · {dept.kpi_count} KPIs
                                    </p>
                                </div>
                                <div className="hidden md:flex items-center gap-3 ml-1">
                                    {(['q1', 'q2', 'q3', 'q4'] as const).map((qk, i) => {
                                        const qst = scoreStyle(dept[qk]);
                                        return (
                                            <div key={qk} className="text-center">
                                                <p className="text-[8px] text-slate-400">Q{i + 1}</p>
                                                <p className={`text-[9px] font-black ${qst.text}`}>{dept[qk] > 0 ? `${dept[qk].toFixed(1)}%` : '—'}</p>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                <span className={`text-[9px] px-2 py-0.5 rounded-lg border font-black ${dstyle.badge}`}>{dstyle.label}</span>
                                {dept.risk_count > 0 && (
                                    <span className="text-[9px] px-2 py-0.5 rounded-lg bg-red-50 text-red-600 font-black border border-red-100">{dept.risk_count} risk</span>
                                )}
                                <svg
                                    className="w-4 h-4 text-slate-400 transition-transform duration-300"
                                    style={{ transform: isOpen ? 'rotate(0deg)' : 'rotate(-90deg)' }}
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>

                        {isOpen && (
                            <div className="border-t border-[#E5E7EB]">
                                <div className="p-4">
                                    <div className="overflow-x-auto thin-scroll">
                                        <table className="w-full min-w-[540px]">
                                            <thead>
                                                <tr className="bg-slate-50 text-[9px] uppercase tracking-wider text-slate-500 font-black border-b border-[#E5E7EB]">
                                                    <th className="px-2 py-1.5 text-left">#</th>
                                                    <th className="px-2 py-1.5 text-left">Name</th>
                                                    <th className="px-2 py-1.5 text-left">Role</th>
                                                    <th className="px-2 py-1.5 text-center">KPIs</th>
                                                    <th className="px-2 py-1.5 text-center">Q1</th>
                                                    <th className="px-2 py-1.5 text-center">Q2</th>
                                                    <th className="px-2 py-1.5 text-center">Q3</th>
                                                    <th className="px-2 py-1.5 text-center">Q4</th>
                                                    <th className="px-2 py-1.5 text-left">Annual</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-50">
                                                {dept.staff_list.map((staff, si) => {
                                                    const sstyle = scoreStyle(staff.performance);
                                                    const isMe = (staff.name ?? '').toLowerCase().trim() === currentUserName.toLowerCase().trim();
                                                    const roleUpper = (staff.role ?? '-').toUpperCase().trim();
                                                    const roleColor = ROLE_COLORS[roleUpper] ?? 'bg-slate-100 text-slate-500';
                                                    return (
                                                        <tr
                                                            key={staff.employee_id || si}
                                                            className={`${isMe ? 'bg-indigo-50/70' : 'hover:bg-slate-50'} transition${isSltOffice ? ' cursor-pointer' : ''}`}
                                                            onClick={isSltOffice ? () => (window.location.href = `/dashboard/staff/${staff.employee_id}`) : undefined}
                                                        >
                                                            <td className="px-2 py-2 text-[9px] text-slate-400 font-bold">{si + 1}</td>
                                                            <td className="px-2 py-2">
                                                                <div className="flex items-center gap-1.5">
                                                                    <div className="w-5 h-5 rounded-full overflow-hidden bg-slate-200 shrink-0">
                                                                        <img src={`https://ui-avatars.com/api/?name=${encodeURIComponent(staff.name ?? 'U')}&background=0f172a&color=fff&size=20`} className="w-full h-full" />
                                                                    </div>
                                                                    <span className="text-[10px] font-black text-slate-900">
                                                                        {staff.name ?? 'Unknown'}
                                                                        {isMe && <span className="text-indigo-400 font-normal"> (you)</span>}
                                                                    </span>
                                                                    {isSltOffice && (
                                                                        <svg className="w-3 h-3 text-slate-300 shrink-0" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                                                        </svg>
                                                                    )}
                                                                </div>
                                                            </td>
                                                            <td className="px-2 py-2">
                                                                <span className={`px-1.5 py-0.5 rounded text-[9px] font-black ${roleColor}`}>{roleUpper !== '-' ? roleUpper : '—'}</span>
                                                            </td>
                                                            <td className="px-2 py-2 text-center text-[9px] font-bold text-slate-600">{staff.kpi_count}</td>
                                                            {(['q1', 'q2', 'q3', 'q4'] as const).map((qk) => {
                                                                const qst2 = scoreStyle(staff[qk]);
                                                                return (
                                                                    <td key={qk} className="px-2 py-2 text-center">
                                                                        <span className={`text-[9px] font-black ${qst2.text}`}>{staff[qk] > 0 ? `${staff[qk].toFixed(1)}%` : '—'}</span>
                                                                    </td>
                                                                );
                                                            })}
                                                            <td className="px-2 py-2">
                                                                <div className="flex items-center gap-1">
                                                                    <div className="w-10 h-1 bg-slate-100 rounded-full overflow-hidden">
                                                                        <div className={`h-1 rounded-full ${sstyle.bar}`} style={{ width: `${Math.min(staff.performance, 100)}%` }} />
                                                                    </div>
                                                                    <span className={`text-[9px] font-black ${sstyle.text}`}>{staff.performance.toFixed(1)}%</span>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
