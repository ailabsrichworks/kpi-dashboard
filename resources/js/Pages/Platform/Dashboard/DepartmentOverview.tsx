import { scoreHex } from '@/lib/scoreStyle';
import { Card, EmptyState } from '@/Components/Platform/ui';
import { BuildingIcon } from '@/Components/Platform/Icons';

export interface DepartmentOverviewRow {
    department: string;
    avg_achievement_pct: number | null;
    submission_count: number;
}

/**
 * "Company Overview" lite — see DashboardController::departmentOverview()'s
 * docblock. A simplified version of the legacy CompanyOverview.tsx's
 * department-ranking bar chart (no manager-only doughnut/quarterly-trend
 * cards, which need per-period data the Platform doesn't have yet): a plain
 * horizontal bar per department, colored by the same achievement-band scale
 * (`scoreHex`) the rest of the Platform's scoring UI already uses.
 */
export default function DepartmentOverview({ rows }: { rows: DepartmentOverviewRow[] }) {
    const withData = rows.filter((r) => r.avg_achievement_pct !== null);

    return (
        <Card title="Company overview" description="Average achievement per department, from what you're authorized to see.">
            {withData.length === 0 ? (
                <EmptyState icon={<BuildingIcon className="w-8 h-8" />} title="No submissions yet" description="Department averages will appear here once KPI values are reported." />
            ) : (
                <div className="space-y-3">
                    {withData
                        .slice()
                        .sort((a, b) => (b.avg_achievement_pct ?? 0) - (a.avg_achievement_pct ?? 0))
                        .map((row) => {
                            const pct = Math.min(100, row.avg_achievement_pct ?? 0);
                            return (
                                <div key={row.department}>
                                    <div className="flex items-center justify-between text-xs mb-1">
                                        <span className="font-semibold text-slate-700">{row.department}</span>
                                        <span className="font-bold text-slate-600 tabular-nums">{row.avg_achievement_pct?.toFixed(1)}%</span>
                                    </div>
                                    <div className="h-2 rounded-full bg-slate-100 overflow-hidden">
                                        <div
                                            className="h-full rounded-full"
                                            style={{ width: `${pct}%`, backgroundColor: scoreHex(row.avg_achievement_pct ?? 0) }}
                                        />
                                    </div>
                                </div>
                            );
                        })}
                </div>
            )}
        </Card>
    );
}
