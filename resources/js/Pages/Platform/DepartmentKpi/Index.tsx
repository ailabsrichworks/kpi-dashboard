import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Card, EmptyState, StatCard } from '@/Components/Platform/ui';
import { scoreStyle } from '@/lib/scoreStyle';
import { BuildingIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface Department {
    id: string;
    name: string;
}

interface KpiRow {
    id: string;
    name: string;
    target: number | null;
    weight: number | null;
    owner_name: string;
    owner_email: string;
    achievement: number | null;
}

interface DepartmentKpiPageProps {
    company: Company;
    department: Department | null;
    members: Array<{ name: string }>;
    kpis: KpiRow[];
    avgAchievement: number | null;
    [key: string]: unknown;
}

export default function DepartmentKpiIndex({ company, department, members, kpis, avgAchievement }: DepartmentKpiPageProps) {
    const avgStyle = avgAchievement !== null ? scoreStyle(avgAchievement) : null;

    return (
        <PlatformLayout
            title="My Department KPI"
            description="Every KPI assigned to a member of your own department."
            company={company}
            maxWidth="max-w-5xl"
        >
            {!department ? (
                <Card>
                    <EmptyState
                        icon={<BuildingIcon className="w-8 h-8" />}
                        title="You are not assigned to a department yet"
                        description="Ask your Company Admin to add you to a department on the Departments & People page."
                    />
                </Card>
            ) : (
                <div className="space-y-6">
                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <StatCard label="Department" value={department.name} />
                        <StatCard label="Members" value={members.length} />
                        <StatCard
                            label="Avg. achievement"
                            value={avgAchievement !== null ? `${avgAchievement}%` : '—'}
                            tone={avgStyle && avgAchievement !== null && avgAchievement >= 75 ? 'success' : avgStyle && avgAchievement !== null && avgAchievement <= 50 ? 'danger' : 'default'}
                        />
                    </div>

                    <Card title={`${department.name} KPIs`} description="Target, weightage, and current achievement for every KPI owned by this department.">
                        {kpis.length === 0 ? (
                            <EmptyState title="No KPIs assigned to this department yet" />
                        ) : (
                            <div className="overflow-x-auto -mx-6 -mb-2">
                                <table className="w-full min-w-[620px]">
                                    <thead>
                                        <tr className="text-[10px] uppercase tracking-wide text-slate-400 font-bold border-b border-slate-100">
                                            <th className="px-6 py-2 text-left">KPI</th>
                                            <th className="px-3 py-2 text-left">Owner</th>
                                            <th className="px-3 py-2 text-right">Target</th>
                                            <th className="px-3 py-2 text-right">Weight</th>
                                            <th className="px-6 py-2 text-right">Achievement</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-50">
                                        {kpis.map((kpi) => {
                                            const style = kpi.achievement !== null ? scoreStyle(kpi.achievement) : null;
                                            return (
                                                <tr key={kpi.id} className="hover:bg-slate-50/60">
                                                    <td className="px-6 py-2.5 text-sm font-bold text-slate-800">{kpi.name}</td>
                                                    <td className="px-3 py-2.5">
                                                        <p className="text-sm text-slate-700">{kpi.owner_name}</p>
                                                        <p className="text-xs text-slate-400">{kpi.owner_email}</p>
                                                    </td>
                                                    <td className="px-3 py-2.5 text-right text-sm text-slate-600 tabular-nums">{kpi.target ?? '—'}</td>
                                                    <td className="px-3 py-2.5 text-right text-sm text-slate-600 tabular-nums">{kpi.weight !== null ? `${kpi.weight}%` : '—'}</td>
                                                    <td className="px-6 py-2.5 text-right">
                                                        {kpi.achievement !== null && style ? (
                                                            <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-bold ${style.badge}`}>
                                                                {kpi.achievement.toFixed(1)}%
                                                            </span>
                                                        ) : (
                                                            <span className="text-xs text-slate-400">No data yet</span>
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
            )}
        </PlatformLayout>
    );
}
