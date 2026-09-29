import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, StatCard } from '@/Components/Platform/ui';
import { scoreStyle } from '@/lib/scoreStyle';
import { UsersIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface StaffRow {
    user_id: string;
    name: string;
    email: string;
    department: string | null;
    role: string;
    kpi_count: number;
    score: number | null;
}

interface SltDashboardPageProps {
    company: Company;
    totalStaff: number;
    departmentCount: number;
    avgScore: number | null;
    bandCounts: Record<string, number>;
    staffRows: StaffRow[];
    [key: string]: unknown;
}

// Same order/colors as resources/js/lib/scoreStyle.ts's own bands -- this
// page's one distribution widget, not a second band scheme.
const BAND_ORDER: Array<{ label: string; hex: string }> = [
    { label: 'Exceeded', hex: '#059669' },
    { label: 'Good', hex: '#10b981' },
    { label: 'Watch', hex: '#f59e0b' },
    { label: 'Risk', hex: '#f97316' },
    { label: 'Critical', hex: '#ef4444' },
];

const ROLE_LABEL: Record<string, string> = {
    company_admin: 'Company Admin',
    slt: 'SLT',
    executive: 'Executive',
    employee: 'Employee',
};

const ROLE_BADGE_TONE: Record<string, 'brand' | 'info' | 'neutral'> = {
    company_admin: 'brand',
    slt: 'info',
    executive: 'neutral',
    employee: 'neutral',
};

export default function SltDashboard({ company, totalStaff, departmentCount, avgScore, bandCounts, staffRows }: SltDashboardPageProps) {
    const scoredCount = staffRows.filter((r) => r.score !== null).length;
    const avgStyle = avgScore !== null ? scoreStyle(avgScore) : null;
    const maxBandCount = Math.max(1, ...BAND_ORDER.map((b) => bandCounts[b.label] ?? 0));

    return (
        <PlatformLayout
            title="SLT Dashboard"
            description="Company-wide KPI achievement across every department and employee — visible to SLT and Company Admins."
            company={company}
            maxWidth="max-w-5xl"
        >
            <div className="space-y-6">
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <StatCard label="Total staff" value={totalStaff} icon={<UsersIcon className="w-3.5 h-3.5" />} />
                    <StatCard label="Departments" value={departmentCount} />
                    <StatCard
                        label="Avg. achievement"
                        value={avgScore !== null ? `${avgScore}%` : '—'}
                        hint={avgScore !== null ? `Across ${scoredCount} scored ${scoredCount === 1 ? 'employee' : 'employees'}` : 'No assigned KPIs with a score yet'}
                        tone={avgStyle && avgScore !== null && avgScore >= 75 ? 'success' : avgStyle && avgScore !== null && avgScore <= 50 ? 'danger' : 'default'}
                    />
                </div>

                <Card title="Score distribution" description="How many staff fall into each achievement band, company-wide.">
                    {scoredCount === 0 ? (
                        <EmptyState title="No scored staff yet" description="Assign KPIs to employees and report a value to see a distribution here." />
                    ) : (
                        <div className="space-y-3">
                            {BAND_ORDER.map((band) => {
                                const count = bandCounts[band.label] ?? 0;
                                return (
                                    <div key={band.label}>
                                        <div className="flex items-center justify-between text-xs mb-1">
                                            <span className="font-semibold text-slate-700">{band.label}</span>
                                            <span className="font-bold text-slate-600 tabular-nums">{count}</span>
                                        </div>
                                        <div className="h-2 rounded-full bg-slate-100 overflow-hidden">
                                            <div
                                                className="h-full rounded-full"
                                                style={{ width: `${(count / maxBandCount) * 100}%`, backgroundColor: band.hex }}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </Card>

                <Card title="Staff roster" description="Every active member of this company, with their current KPI achievement.">
                    {staffRows.length === 0 ? (
                        <EmptyState icon={<UsersIcon className="w-8 h-8" />} title="No active staff yet" />
                    ) : (
                        <div className="overflow-x-auto -mx-6 -mb-2">
                            <table className="w-full min-w-[560px]">
                                <thead>
                                    <tr className="text-[10px] uppercase tracking-wide text-slate-400 font-bold border-b border-slate-100">
                                        <th className="px-6 py-2 text-left">Name</th>
                                        <th className="px-3 py-2 text-left">Department</th>
                                        <th className="px-3 py-2 text-left">Role</th>
                                        <th className="px-3 py-2 text-center">KPIs</th>
                                        <th className="px-6 py-2 text-right">Score</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {staffRows.map((row) => {
                                        const style = row.score !== null ? scoreStyle(row.score) : null;
                                        return (
                                            <tr key={row.user_id} className="hover:bg-slate-50/60">
                                                <td className="px-6 py-2.5">
                                                    <p className="text-sm font-bold text-slate-800">{row.name}</p>
                                                    <p className="text-xs text-slate-400">{row.email}</p>
                                                </td>
                                                <td className="px-3 py-2.5 text-sm text-slate-600">{row.department ?? '—'}</td>
                                                <td className="px-3 py-2.5">
                                                    <Badge tone={ROLE_BADGE_TONE[row.role] ?? 'neutral'}>{ROLE_LABEL[row.role] ?? row.role}</Badge>
                                                </td>
                                                <td className="px-3 py-2.5 text-center text-sm font-semibold text-slate-600">{row.kpi_count}</td>
                                                <td className="px-6 py-2.5 text-right">
                                                    {row.score !== null && style ? (
                                                        <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-bold ${style.badge}`}>
                                                            {row.score.toFixed(1)}%
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs text-slate-400">No KPIs assigned</span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            </div>
        </PlatformLayout>
    );
}
