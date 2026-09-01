import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState } from '@/Components/Platform/ui';
import { UsersIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface ManagerRow {
    manager_id: string;
    manager_name: string;
    staff_count: number;
    team_performance: number | null;
    kpi_update_completion_pct: number | null;
}

interface ManagersPageProps {
    company: Company;
    managers: ManagerRow[];
    [key: string]: unknown;
}

/** Spec §41: evaluate managers by what their team actually does, not meeting/click counts. */
export default function PeopleManagers({ company, managers }: ManagersPageProps) {
    const needsAttention = (m: ManagerRow) => (m.team_performance !== null && m.team_performance < 75) || (m.kpi_update_completion_pct !== null && m.kpi_update_completion_pct < 75);

    return (
        <PlatformLayout title="Manager Effectiveness" description="How each manager's own team is actually performing." company={company}>
            <Card>
                {managers.length === 0 ? (
                    <EmptyState icon={<UsersIcon className="w-10 h-10" />} title="No one in this company has direct reports yet" />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {managers.map((m) => (
                            <li key={m.manager_id} className="py-4 flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-sm font-bold text-slate-800">{m.manager_name}</p>
                                    <p className="text-xs text-slate-400">{m.staff_count} staff</p>
                                </div>
                                <div className="flex items-center gap-6">
                                    <div className="text-center">
                                        <p className="text-sm font-bold tabular-nums text-slate-700">{m.team_performance !== null ? `${m.team_performance.toFixed(0)}%` : '—'}</p>
                                        <p className="text-[11px] text-slate-400">Team Performance</p>
                                    </div>
                                    <div className="text-center">
                                        <p className="text-sm font-bold tabular-nums text-slate-700">
                                            {m.kpi_update_completion_pct !== null ? `${m.kpi_update_completion_pct.toFixed(0)}%` : '—'}
                                        </p>
                                        <p className="text-[11px] text-slate-400">KPI Update Completion</p>
                                    </div>
                                    {needsAttention(m) && <Badge tone="warning">Needs Attention</Badge>}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
