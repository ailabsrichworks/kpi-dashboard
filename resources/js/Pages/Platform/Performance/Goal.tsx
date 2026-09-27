import { Link } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Card, PerformanceStatusBadge, StatusBadge } from '@/Components/Platform/ui';
import { ChevronRightIcon } from '@/Components/Platform/Icons';
import { performanceStatusFor } from '@/lib/performanceStatus';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface Goal {
    id: string;
    title: string;
    description: string | null;
    status: string;
    users: { name: string } | null;
}

interface CascadeNode {
    kpi_id: string;
    name: string;
    department_name: string | null;
    achievement_pct: number | null;
    depth: number;
}

interface GoalPageProps {
    company: Company;
    goal: Goal;
    cascade: CascadeNode[];
    [key: string]: unknown;
}

/** Spec §32: Company Goal -> Company KPI -> Department -> Team -> Individual, made visually obvious via indentation + a status badge per rung. */
export default function PerformanceGoal({ company, goal, cascade }: GoalPageProps) {
    return (
        <PlatformLayout
            title={goal.title}
            description={
                <span className="inline-flex items-center gap-1.5">
                    <Link href={`/platform/companies/${company.id}/performance`} className="hover:underline">
                        Company Performance
                    </Link>
                    <ChevronRightIcon className="w-3 h-3" />
                    <span>{goal.title}</span>
                </span>
            }
            company={company}
        >
            <Card className="mb-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        {goal.description && <p className="text-sm text-slate-600 mb-2">{goal.description}</p>}
                        {goal.users && <p className="text-xs text-slate-400">Owner: {goal.users.name}</p>}
                    </div>
                    <StatusBadge status={goal.status} />
                </div>
            </Card>

            <Card title="Contributing KPI cascade" description="Root-cause drilldown — the deepest rung with a real gap is where to focus">
                {cascade.length === 0 ? (
                    <p className="text-sm text-slate-500 py-6 text-center">No KPIs are linked to this goal yet.</p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {cascade.map((node) => (
                            <li key={node.kpi_id} className="flex items-center justify-between gap-4 py-3" style={{ paddingLeft: node.depth * 24 }}>
                                <div className="flex items-center gap-2 min-w-0">
                                    {node.depth > 0 && <ChevronRightIcon className="w-3.5 h-3.5 text-slate-300 flex-none" />}
                                    <div className="min-w-0">
                                        <p className="text-sm font-semibold text-slate-800 truncate">{node.name}</p>
                                        {node.department_name && <p className="text-xs text-slate-400">{node.department_name}</p>}
                                    </div>
                                </div>
                                <div className="flex-none flex items-center gap-2">
                                    <span className="text-sm font-bold tabular-nums text-slate-700">
                                        {node.achievement_pct !== null ? `${node.achievement_pct.toFixed(0)}%` : '—'}
                                    </span>
                                    <PerformanceStatusBadge status={performanceStatusFor(node.achievement_pct)} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
