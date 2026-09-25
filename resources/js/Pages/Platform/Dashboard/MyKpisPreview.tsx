import { Link } from '@inertiajs/react';
import { categoryStyleFor } from '@/config/dashboardCategories';

export interface CategoryCount {
    category: string;
    count: number;
}

/**
 * "My KPIs" preview strip — ports dashboard.blade.php's category-badge row
 * (a link into the full KPI list, not a second copy of it). Reuses the same
 * `dashboardCategories` palette the legacy Dashboard.tsx already uses; it's a
 * plain lookup with no legacy-specific assumptions baked in.
 */
export default function MyKpisPreview({
    companyId,
    kpiCount,
    totalWeight,
    categoryCounts,
}: {
    companyId: string;
    kpiCount: number;
    totalWeight: number;
    categoryCounts: CategoryCount[];
}) {
    return (
        <div>
            <div className="flex items-center justify-between mb-2">
                <div>
                    <h3 className="text-xs font-bold text-slate-700">My KPIs</h3>
                    <p className="text-[11px] text-slate-400 mt-0.5">
                        {kpiCount} KPI{kpiCount === 1 ? '' : 's'} · {totalWeight.toFixed(0)}% total weightage
                    </p>
                </div>
                <Link href={`/platform/companies/${companyId}/weightage`} className="text-xs font-semibold text-brand-800 hover:underline flex-none">
                    Manage weightage →
                </Link>
            </div>
            <Link
                href={`/platform/companies/${companyId}/kpis`}
                className="flex flex-wrap items-center gap-2 bg-slate-50 rounded-xl border border-slate-100 p-3 hover:bg-slate-100/70 transition"
            >
                {categoryCounts.map(({ category, count }) => (
                    <span key={category} className={`px-2.5 py-1 rounded-lg text-xs font-bold ${categoryStyleFor(category).bg}`}>
                        {category} · {count}
                    </span>
                ))}
                <span className="ml-auto text-xs font-bold text-brand-800 shrink-0">View all KPIs →</span>
            </Link>
        </div>
    );
}
