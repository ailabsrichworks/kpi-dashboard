import { Head, router, usePage } from '@inertiajs/react';
import { SharedPageProps } from '../../types';

interface DashboardOption {
    employee_uuid: string;
    company_code: string;
    company_name?: string;
    company_display_name?: string;
    logo_url?: string | null;
    full_name?: string;
    short_name?: string;
    role: string;
    department_code: string;
    is_default?: boolean;
}

interface ChooseDashboardPageProps {
    dashboards: DashboardOption[];
    userName?: string | null;
}

const ROLE_BADGE: Record<string, string> = {
    SLT: 'bg-purple-50 text-purple-700 border-purple-100',
    VP: 'bg-[#F5EAE0] text-[#6B3F2A] border-[#E8D5C4]',
    MANAGER: 'bg-indigo-50 text-indigo-700 border-indigo-100',
    EXECUTIVE: 'bg-slate-100 text-slate-600 border-slate-200',
};

export default function ChooseDashboard({ dashboards, userName }: ChooseDashboardPageProps) {
    const { flash } = usePage<SharedPageProps>().props;

    function selectDashboard(dashboard: DashboardOption) {
        router.post('/choose-dashboard', {
            employee_uuid: dashboard.employee_uuid,
            company_code: dashboard.company_code,
        });
    }

    function logout() {
        router.post('/logout');
    }

    return (
        <>
            <Head title="Select Company — RCG KPI" />

            <div className="min-h-screen bg-[#F5F5F3] flex items-center justify-center p-4">
                <div className="w-full max-w-md">
                    <div
                        className="relative overflow-hidden rounded-[20px] text-[#3A3128] px-6 py-6 shadow-[0_15px_45px_rgba(0,0,0,0.12)] mb-4"
                        style={{
                            background:
                                'radial-gradient(circle at top left, rgba(196,184,150,.4), transparent 32%), radial-gradient(circle at bottom right, rgba(166,147,116,.3), transparent 38%), linear-gradient(135deg, #F1EBE0 0%, #E9E0D1 50%, #DED2BC 130%)',
                        }}
                    >
                        <div className="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-[#C9B896] via-[#C9B896] to-[#C9B896]/10" />
                        <div className="pointer-events-none absolute -top-10 -right-10 w-40 h-40 rounded-full bg-[#C9B896]/25 blur-3xl" />
                        <div className="relative mb-1">
                            <h1 className="text-base font-black">Select Company</h1>
                            <p className="text-[11px] text-[#8B7355] font-semibold">Choose which company dashboard to access</p>
                        </div>
                        {userName && (
                            <p className="relative text-[10px] text-[#6B5D4F] mt-3">
                                Logged in as <span className="text-[#3A3128] font-bold">{userName}</span>
                            </p>
                        )}
                    </div>

                    {flash.error && <div className="mb-3 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-xs text-red-700">{flash.error}</div>}

                    <div className="space-y-3">
                        {dashboards.length === 0 ? (
                            <div className="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">No company access found for this account.</div>
                        ) : (
                            dashboards.map((dashboard) => {
                                const roleBadge = ROLE_BADGE[dashboard.role?.toUpperCase().trim()] ?? 'bg-slate-100 text-slate-600 border-slate-200';

                                return (
                                    <button
                                        key={`${dashboard.employee_uuid}-${dashboard.company_code}`}
                                        type="button"
                                        onClick={() => selectDashboard(dashboard)}
                                        className="w-full text-left bg-white rounded-2xl border border-[#E5E7EB] border-t-[3px] border-t-[#C9B896] shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all group"
                                    >
                                        <div className="p-4 flex items-center gap-4">
                                            <div className="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center shrink-0 overflow-hidden border border-[#E5E7EB] p-1.5">
                                                {dashboard.logo_url ? (
                                                    <img
                                                        src={dashboard.logo_url}
                                                        alt={`${dashboard.company_display_name ?? dashboard.company_name ?? dashboard.company_code} logo`}
                                                        className="w-full h-full object-contain"
                                                    />
                                                ) : (
                                                    <span className="text-sm font-black text-[#6B5D4F]">{dashboard.company_code.slice(0, 2).toUpperCase()}</span>
                                                )}
                                            </div>

                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-black text-slate-900 truncate">
                                                    {dashboard.company_display_name ?? dashboard.company_name ?? dashboard.company_code}
                                                </p>
                                                <p className="text-[11px] text-slate-500 mt-0.5">{dashboard.full_name ?? dashboard.short_name ?? 'Employee'}</p>
                                                <div className="mt-2 flex flex-wrap gap-1.5">
                                                    <span className={`px-2 py-0.5 rounded-full text-[10px] font-black border ${roleBadge}`}>{dashboard.role}</span>
                                                    <span className="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-bold">{dashboard.department_code}</span>
                                                    {dashboard.is_default && (
                                                        <span className="px-2 py-0.5 rounded-full bg-[#C9B896]/20 text-[#8B7355] text-[10px] font-bold border border-[#C9B896]/40">Default</span>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="text-slate-300 group-hover:text-[#8B7355] transition shrink-0">
                                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </div>
                                        </div>
                                    </button>
                                );
                            })
                        )}
                    </div>

                    <div className="mt-5 text-center">
                        <button type="button" onClick={logout} className="text-xs text-slate-400 hover:text-[#6B5D4F] transition font-semibold">
                            ← Back to login
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
}
