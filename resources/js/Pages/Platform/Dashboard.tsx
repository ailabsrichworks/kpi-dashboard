import { Link } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { BuildingIcon, ClipboardCheckIcon, RocketIcon, TargetIcon, UsersIcon } from '@/Components/Platform/Icons';
import { Card, EmptyState, StatCard, StatusBadge } from '@/Components/Platform/ui';

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
    [key: string]: unknown;
}

export default function PlatformDashboard({ me, visibleCompanies }: DashboardPageProps) {
    return (
        <PlatformLayout
            title={`Welcome back, ${me.name.split(' ')[0]}`}
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
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
