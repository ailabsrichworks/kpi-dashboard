import { Link } from '@inertiajs/react';
import { useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { BuildingIcon, ClipboardCheckIcon, LinkIcon, RocketIcon, TargetIcon, UsersIcon } from '@/Components/Platform/Icons';
import { Card, EmptyState, StatCard, StatusBadge } from '@/Components/Platform/ui';
import MyPerformanceCard, { MyPerformance } from './Dashboard/MyPerformanceCard';
import DepartmentOverview, { DepartmentOverviewRow } from './Dashboard/DepartmentOverview';
import MyKpisPreview from './Dashboard/MyKpisPreview';

interface Company {
    id: string;
    name: string;
    code: string;
    status: string;
    department_count: number;
    user_count: number;
    kpi_count: number;
    submission_count: number;
    avg_achievement_pct: number | null;
    my_performance: MyPerformance | null;
    department_overview: DepartmentOverviewRow[];
}

interface PlatformUser {
    id: string;
    name: string;
    email: string;
    is_platform_admin: boolean;
    assigned_company_ids: string[];
    company_memberships: Array<{
        company_id: string;
        role: string;
        companies: { name: string; code: string };
    }>;
}

interface DashboardPageProps {
    me: PlatformUser;
    visibleCompanies: Company[];
    greeting: string;
    [key: string]: unknown;
}

/**
 * Ports dashboard.blade.php's "Company Overview" collapsible section — the
 * department-ranking bars are worth having open by default less often than
 * "My performance", so it starts collapsed and remembers the choice per
 * company (a Super Admin/Platform Admin browsing several companies shouldn't
 * have one company's expand state leak into another's).
 */
function CompanyOverviewToggle({ companyId, rows }: { companyId: string; rows: DepartmentOverviewRow[] }) {
    const storageKey = `platformCompanyOverviewOpen:${companyId}`;
    const [open, setOpen] = useState(() => {
        try {
            return localStorage.getItem(storageKey) === 'true';
        } catch {
            return false;
        }
    });

    function toggle() {
        const next = !open;
        setOpen(next);
        try {
            localStorage.setItem(storageKey, next ? 'true' : 'false');
        } catch {
            // Private window / blocked storage — the toggle still works for this page view.
        }
    }

    return (
        <div>
            <button
                type="button"
                onClick={toggle}
                className="w-full flex items-center justify-between bg-white rounded-2xl px-4 py-3 border border-slate-200 shadow-sm hover:bg-slate-50 transition"
            >
                <div className="flex items-center gap-2.5">
                    <BuildingIcon className="w-4 h-4 text-slate-400" />
                    <span className="text-xs font-bold text-slate-700">Company overview</span>
                </div>
                <span className="text-[11px] font-semibold text-brand-800">{open ? 'Hide' : 'Show'}</span>
            </button>
            {open && (
                <div className="mt-2">
                    <DepartmentOverview rows={rows} />
                </div>
            )}
        </div>
    );
}

export default function PlatformDashboard({ me, visibleCompanies, greeting }: DashboardPageProps) {
    return (
        <PlatformLayout
            title={`${greeting}, ${me.name.split(' ')[0]}`}
            description={me.is_platform_admin ? 'Platform Admin' : 'Here are the companies you can access.'}
        >
            <Card
                title={`Your companies (${visibleCompanies.length})`}
                description="You only ever see companies you're actually part of — nothing here is hidden by this page, it simply isn't there for anyone else."
            >
                {visibleCompanies.length === 0 ? (
                    <EmptyState
                        icon={<BuildingIcon className="w-10 h-10" />}
                        title="No companies yet"
                        description="Once you're added to a company, it will show up here automatically."
                    />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {visibleCompanies.map((company) => (
                            <li key={company.id} className="py-5 first:pt-0 last:pb-0">
                                <div className="flex flex-wrap items-center justify-between gap-3 mb-3">
                                    <div>
                                        <p className="text-sm font-bold text-slate-800">{company.name}</p>
                                        <p className="text-xs text-slate-400">{company.code}</p>
                                    </div>
                                    <div className="flex items-center gap-4">
                                        <Link
                                            href={`/platform/companies/${company.id}/onboarding`}
                                            className="text-xs font-semibold text-brand-800 hover:underline"
                                        >
                                            Onboarding
                                        </Link>
                                        <Link
                                            href={`/platform/companies/${company.id}/departments`}
                                            className="text-xs font-semibold text-brand-800 hover:underline"
                                        >
                                            Departments
                                        </Link>
                                        <Link
                                            href={`/platform/companies/${company.id}/kpis`}
                                            className="text-xs font-semibold text-brand-800 hover:underline"
                                        >
                                            KPIs
                                        </Link>
                                        <StatusBadge status={company.status} />
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 sm:grid-cols-5 gap-2">
                                    <StatCard label="Departments" value={company.department_count} icon={<UsersIcon className="w-3.5 h-3.5" />} />
                                    <StatCard label="People" value={company.user_count} icon={<UsersIcon className="w-3.5 h-3.5" />} />
                                    <StatCard label="KPIs" value={company.kpi_count} icon={<TargetIcon className="w-3.5 h-3.5" />} />
                                    <StatCard
                                        label="Submissions"
                                        value={company.submission_count}
                                        icon={<ClipboardCheckIcon className="w-3.5 h-3.5" />}
                                    />
                                    <StatCard
                                        label="Avg. achievement"
                                        value={company.avg_achievement_pct !== null ? `${company.avg_achievement_pct}%` : '—'}
                                        tone={
                                            company.avg_achievement_pct !== null && company.avg_achievement_pct >= 100
                                                ? 'success'
                                                : 'default'
                                        }
                                        icon={<RocketIcon className="w-3.5 h-3.5" />}
                                    />
                                </div>

                                {company.my_performance && company.my_performance.kpi_count > 0 && (
                                    <div className="mt-4 space-y-4">
                                        <MyPerformanceCard companyId={company.id} performance={company.my_performance} />
                                        <MyKpisPreview
                                            companyId={company.id}
                                            kpiCount={company.my_performance.kpi_count}
                                            totalWeight={company.my_performance.total_weight}
                                            categoryCounts={company.my_performance.category_counts}
                                        />
                                    </div>
                                )}

                                {company.department_overview.length > 0 && (
                                    <div className="mt-4">
                                        <CompanyOverviewToggle companyId={company.id} rows={company.department_overview} />
                                    </div>
                                )}

                                <div className="mt-4">
                                    <Link
                                        href={`/platform/companies/${company.id}/target-linkages`}
                                        className="flex items-center justify-between gap-3 bg-white rounded-2xl px-4 py-3 border border-slate-200 shadow-sm hover:bg-slate-50 transition"
                                    >
                                        <div className="flex items-center gap-2.5 min-w-0">
                                            <LinkIcon className="w-4 h-4 text-slate-400 flex-none" />
                                            <div className="min-w-0">
                                                <p className="text-xs font-bold text-slate-700">Target linkages</p>
                                                <p className="text-[11px] text-slate-400">Cascading targets between manager and team — not built on the Platform yet</p>
                                            </div>
                                        </div>
                                        <span className="text-[11px] font-semibold text-brand-800 flex-none">View →</span>
                                    </Link>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
