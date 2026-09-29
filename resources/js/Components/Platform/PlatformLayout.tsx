import { Head, Link, router, usePage } from '@inertiajs/react';
import { CSSProperties, KeyboardEvent, ReactNode, useEffect, useRef, useState } from 'react';
import Icon from '@/Components/Icon';

/**
 * The Platform shell, visually matched 1:1 to the legacy app's
 * Layouts/AppLayout + Components/Sidebar + Components/TopBar (black sidebar
 * with gold accents, fixed white top bar with search + profile chip, floating
 * ANIRA button) — same Tailwind classes, same default theme colours — but
 * driven entirely by Platform data (`platformUser`, the page's `company`) and
 * Platform routes. The legacy components themselves can't be reused directly:
 * they read the legacy-only `layout` shared prop (session employee, legacy
 * theme settings) that Platform requests never carry.
 */

interface PlatformUser {
    id: string;
    name: string;
    email: string;
    platform_role: string;
    is_super_admin: boolean;
    is_platform_admin: boolean;
    assigned_company_ids: string[];
    company_memberships: Array<{ company_id: string; role: string; companies?: { name: string; code: string } }>;
}

interface CompanyRef {
    id: string;
    name: string;
    code?: string;
}

interface PlatformLayoutProps {
    title: string;
    description?: ReactNode;
    company?: CompanyRef | null;
    actions?: ReactNode;
    /** Tailwind max-width class for the content column. Defaults to full width, like the legacy app. */
    maxWidth?: string;
    children: ReactNode;
}

interface SharedProps {
    platformUser: PlatformUser | null;
    platformUnreadNotificationCount: number;
    platformImpersonating: boolean;
    flash: { error?: string | null; success?: string | null };
    [key: string]: unknown;
}

interface NavItem {
    label: string;
    href: string;
    icon: string;
    badge?: number;
}

interface NavSection {
    title: string;
    items: NavItem[];
}

// Legacy defaults (HandleInertiaRequests' themeSidebarBg/themeSidebarAccent/
// themeSidebarText/themeBg) — the look in the legacy screenshots.
const SIDEBAR_BG = '#111111';
const SIDEBAR_ACCENT = '#D4AF37';
const SIDEBAR_TEXT = '#FFFFFF';
const PAGE_BG = '#F5F5F3';

const COLLAPSE_KEY = 'platformSidebarCollapsed';

const ROLE_LABEL: Record<string, string> = {
    company_admin: 'Company Admin',
    slt: 'SLT',
    executive: 'Executive',
    employee: 'Employee',
};

function canAdminister(user: PlatformUser | null, companyId: string): boolean {
    if (!user) return false;
    if (user.is_super_admin) return true;
    if (user.is_platform_admin && user.assigned_company_ids.includes(companyId)) return true;
    return user.company_memberships.some((m) => m.company_id === companyId && m.role === 'company_admin');
}

function isCompanyMember(user: PlatformUser | null, companyId: string): boolean {
    if (!user) return false;
    if (user.is_super_admin) return true;
    if (user.is_platform_admin && user.assigned_company_ids.includes(companyId)) return true;
    return user.company_memberships.some((m) => m.company_id === companyId);
}

/** Mirrors PlatformAuthorization::ensureCompanyWideViewer() exactly. */
function isCompanyWideViewer(user: PlatformUser | null, companyId: string): boolean {
    if (canAdminister(user, companyId)) return true;
    return !!user?.company_memberships.some((m) => m.company_id === companyId && m.role === 'slt');
}

function isActive(href: string, currentUrl: string): boolean {
    const path = currentUrl.split(/[?#]/)[0];
    const target = href.split(/[?#]/)[0];
    if (target === '/platform/dashboard') return path === target;
    return path === target || path.startsWith(target + '/');
}

function todayInKualaLumpur(): string {
    return new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Kuala_Lumpur',
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    }).format(new Date());
}

function avatarUrl(name: string, size: number): string {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=D4AF37&color=1a1a1a&size=${size}`;
}

function confirmLogout() {
    if (confirm('You are about to logout. Continue?')) {
        router.post('/platform/logout');
    }
}

/** Mirrors legacy's partials/sidebar.blade.php impersonation banner (same copy, same purple gradient) exactly — the one thing that has to stay reachable regardless of what the impersonated user's own role can see in the sidebar. */
function ImpersonationBanner({ collapsed, name }: { collapsed: boolean; name: string }) {
    return (
        <div
            className="no-print fixed top-0 z-9997 h-9 flex items-center justify-center gap-3 text-white text-[12px] font-bold px-6 shadow-[0_2px_12px_rgba(124,58,237,0.35)] transition-all duration-300"
            style={{ left: collapsed ? 64 : 230, right: 0, backgroundImage: 'linear-gradient(90deg, #7c3aed, #a78bfa)' }}
        >
            <span>
                👁 Viewing as <strong>{name}</strong> — Super Admin session
            </span>
            <button
                type="button"
                onClick={() => router.post('/platform/admin/view-as/stop')}
                className="bg-white/20 border border-white/40 px-3 py-1 rounded-lg text-[11px] font-black hover:bg-white/30 transition"
            >
                Return to my account
            </button>
        </div>
    );
}

/**
 * `companies` on a membership comes from an RLS-scoped embed, so it resolves
 * to null for a company whose parent row the caller can no longer read (e.g.
 * since suspended/archived). Prefer the first membership that actually
 * resolved a company rather than blindly taking [0].
 */
function resolveContextCompany(platformUser: PlatformUser | null, company?: CompanyRef | null): CompanyRef | null {
    if (company) return company;
    if (!platformUser || platformUser.is_super_admin) return null;
    const best = platformUser.company_memberships.find((m) => m.companies) ?? platformUser.company_memberships[0];
    return best ? { id: best.company_id, name: best.companies?.name ?? 'Your company', code: best.companies?.code ?? '' } : null;
}

function buildSections(platformUser: PlatformUser | null, contextCompany: CompanyRef | null, unread: number): NavSection[] {
    const sections: NavSection[] = [];
    const c = contextCompany;
    const isMember = c ? isCompanyMember(platformUser, c.id) : false;
    const isAdmin = c ? canAdminister(platformUser, c.id) : false;
    const isSltViewer = c ? isCompanyWideViewer(platformUser, c.id) : false;
    const base = c ? `/platform/companies/${c.id}` : '';

    const overview: NavItem[] = [{ label: 'Main Dashboard', href: '/platform/dashboard', icon: 'dashboard' }];
    if (c && isMember) overview.push({ label: 'Things To Do', href: `${base}/tasks`, icon: 'task' });
    overview.push({ label: 'Notifications', href: '/platform/notifications', icon: 'bell', badge: unread });
    if (c && isMember) overview.push({ label: 'Job Description', href: `${base}/job-description`, icon: 'jobdesc' });
    if (c && isSltViewer) overview.push({ label: 'SLT Dashboard', href: `${base}/slt-dashboard`, icon: 'analytics' });
    sections.push({ title: 'Overview', items: overview });

    if (c && isMember) {
        const kpiWork: NavItem[] = [{ label: 'Create New KPI', href: `${base}/kpis/create`, icon: 'plus' }];
        kpiWork.push(
            { label: 'View My KPI', href: `${base}/kpis`, icon: 'list' },
            { label: 'Manage Weightage', href: `${base}/weightage`, icon: 'weightage' },
            { label: 'My Department KPI', href: `${base}/my-department-kpi`, icon: 'department' },
            { label: 'Quarterly Progress', href: `${base}/quarterly`, icon: 'calendar' },
            { label: 'Target Linkages', href: `${base}/target-linkages`, icon: 'linkage' },
        );
        sections.push({ title: 'KPI Work', items: kpiWork });

        sections.push({
            title: 'Monitoring',
            items: [{ label: 'User Activity Log', href: `${base}/activity-log`, icon: 'activity' }],
        });

        sections.push({
            title: 'Performance Evaluation',
            items: [
                { label: 'Q1 Evaluation', href: `${base}/performance/q1`, icon: 'eval-kpi' },
                { label: 'Q2 Evaluation', href: `${base}/performance/q2`, icon: 'eval-kpi' },
                { label: 'Q3 Evaluation', href: `${base}/performance/q3`, icon: 'eval-kpi' },
                { label: 'Q4 Evaluation', href: `${base}/performance/q4`, icon: 'eval-kpi' },
            ],
        });
    }

    if (c && isAdmin) {
        const admin: NavItem[] = [
            { label: 'Departments & People', href: `${base}/departments`, icon: 'department' },
            { label: 'Onboarding', href: `${base}/onboarding`, icon: 'approval' },
        ];
        if (platformUser?.is_super_admin) admin.push({ label: 'Import Data', href: `${base}/import`, icon: 'attendance' });
        admin.push(
            { label: 'Attendance', href: `${base}/attendance`, icon: 'attendance' },
            { label: 'Approval Center', href: `${base}/approvals`, icon: 'approval' },
            { label: 'Audit Log', href: `${base}/audit-log`, icon: 'report' },
            { label: 'Quarter Control', href: `${base}/quarter-control`, icon: 'calendar' },
            { label: 'Company Settings', href: `${base}/settings`, icon: 'settings' },
        );
        sections.push({ title: 'Admin Setup', items: admin });
    }

    if (platformUser?.is_super_admin) {
        sections.push({
            title: 'Richworks Center',
            items: [
                { label: 'Companies', href: '/platform/companies', icon: 'department' },
                { label: 'KPI Templates', href: '/platform/kpi-templates', icon: 'list' },
                { label: 'Platform Admins', href: '/platform/admins', icon: 'users' },
                { label: 'Audit Log', href: '/platform/audit-log', icon: 'report' },
                { label: 'View As', href: '/platform/admin/view-as', icon: 'users' },
            ],
        });
    }

    return sections;
}

function Sidebar({
    collapsed,
    setCollapsed,
    sections,
    contextCompany,
    currentUrl,
}: {
    collapsed: boolean;
    setCollapsed: (v: boolean) => void;
    sections: NavSection[];
    contextCompany: CompanyRef | null;
    currentUrl: string;
}) {
    const accentBarStyle: CSSProperties = { backgroundImage: `linear-gradient(90deg, ${SIDEBAR_ACCENT}, ${SIDEBAR_ACCENT}, transparent)` };
    const accentLineStyle: CSSProperties = { backgroundImage: `linear-gradient(90deg, ${SIDEBAR_ACCENT}, transparent)` };
    const activeItemStyle: CSSProperties = {
        backgroundImage: `linear-gradient(135deg, ${SIDEBAR_ACCENT}, color-mix(in srgb, ${SIDEBAR_ACCENT} 35%, black))`,
        borderLeftColor: SIDEBAR_ACCENT,
    };
    const inactiveLinkStyle: CSSProperties = { color: SIDEBAR_TEXT, opacity: 0.85 };
    const closeBtnStyle = { '--sidebar-close-accent': SIDEBAR_ACCENT, '--sidebar-close-bg': SIDEBAR_BG } as CSSProperties;

    const brandName = (contextCompany?.name ?? 'Performix').toUpperCase();
    const brandInitial = brandName.charAt(0);
    const profileActive = isActive('/platform/profile', currentUrl);
    const helpActive = isActive('/platform/help', currentUrl);

    return (
        <aside
            id="sidebar"
            className={`fixed left-0 top-0 z-40 h-screen text-white border-r border-white/10 shadow-[4px_0_24px_rgba(0,0,0,0.30)]
            px-3 py-4 flex flex-col overflow-visible shrink-0 transition-all duration-300
            ${collapsed ? 'collapsed w-[64px] min-w-[64px] max-w-[64px]' : 'w-[230px] min-w-[230px] max-w-[230px]'}`}
            style={{ backgroundColor: SIDEBAR_BG }}
        >
            <div className="absolute top-0 left-0 right-0 h-[2px]" style={accentBarStyle} />

            <button
                type="button"
                onClick={() => setCollapsed(true)}
                className={`sidebar-close-btn absolute top-4 right-3 z-[9999] w-7 h-7 flex items-center justify-center border rounded-full transition text-sm ${collapsed ? 'hidden' : ''}`}
                style={closeBtnStyle}
                aria-label="Close Sidebar"
            >
                ×
            </button>

            <button
                type="button"
                onClick={() => collapsed && setCollapsed(false)}
                className="group w-full flex items-center gap-2 mb-3 shrink-0 pr-8 text-left hover:bg-white/10 rounded-xl p-1.5 transition relative"
                aria-label="Open Sidebar"
            >
                <div
                    className="w-10 h-10 rounded-xl border-2 flex items-center justify-center shrink-0 overflow-hidden p-1"
                    style={{ backgroundColor: SIDEBAR_BG, borderColor: SIDEBAR_ACCENT }}
                >
                    <span className="text-white font-bold text-base flex items-center justify-center">{collapsed ? '☰' : brandInitial}</span>
                </div>

                <div className={`leading-tight text-left min-w-0 ${collapsed ? 'hidden' : ''}`}>
                    <h1 className="text-[12px] font-bold tracking-wide text-white leading-tight break-words">{brandName}</h1>
                    <p className="text-[9px] uppercase tracking-[0.14em] mt-1 font-semibold" style={{ color: SIDEBAR_ACCENT }}>
                        Performance System
                    </p>
                </div>
            </button>

            <div className="h-px w-full shrink-0 mb-3" style={accentLineStyle} />

            <div className="relative flex-1 min-h-0 flex flex-col">
                <nav className="flex-1 overflow-y-auto text-[12px] space-y-5 pr-1 min-h-0 custom-scroll">
                    {sections.map((section) => (
                        <div key={section.title}>
                            <div className={`flex items-center gap-2 mb-1 px-2 ${collapsed ? 'hidden' : ''}`}>
                                <p className="text-[9px] font-semibold uppercase tracking-widest shrink-0" style={{ color: SIDEBAR_ACCENT }}>
                                    {section.title}
                                </p>
                                <div className="h-px flex-1" style={accentLineStyle} />
                            </div>

                            <div className="space-y-1">
                                {section.items.map((item) => {
                                    const active = isActive(item.href, currentUrl);
                                    return (
                                        <Link
                                            key={item.label}
                                            href={item.href}
                                            title={collapsed ? item.label : undefined}
                                            className={`group relative flex items-center gap-3 px-3 py-2 rounded-xl transition ${
                                                active ? 'border-l-[3px] text-white font-black shadow-md' : 'font-medium hover:bg-white/10 hover:opacity-100!'
                                            }`}
                                            style={active ? activeItemStyle : inactiveLinkStyle}
                                        >
                                            <span className="w-5 h-5 flex items-center justify-center shrink-0">
                                                <Icon name={item.icon} className="w-4 h-4" />
                                            </span>
                                            <div className="flex items-center justify-between w-full min-w-0 gap-2">
                                                <span className={`truncate ${collapsed ? 'hidden' : ''}`}>{item.label}</span>
                                                {!collapsed && !!item.badge && item.badge > 0 && (
                                                    <span className="min-w-[20px] h-[20px] rounded-full bg-red-500 text-white text-[10px] font-black flex items-center justify-center px-1 shadow-lg shadow-red-500/30">
                                                        {item.badge > 99 ? '99+' : item.badge}
                                                    </span>
                                                )}
                                            </div>
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    ))}

                    <Link
                        href="/platform/profile"
                        title={collapsed ? 'Account Settings' : undefined}
                        className={`group relative flex items-center gap-2 px-3 py-1.5 rounded-lg transition mt-1 ${
                            profileActive ? 'border-l-[3px] text-white font-bold shadow-md' : 'font-medium hover:bg-white/10 hover:opacity-100!'
                        }`}
                        style={profileActive ? activeItemStyle : inactiveLinkStyle}
                    >
                        <span className="w-4 h-4 flex items-center justify-center shrink-0">
                            <Icon name="settings" />
                        </span>
                        <span className={`truncate text-[11px] ${collapsed ? 'hidden' : ''}`}>Account Settings</span>
                    </Link>

                    <Link
                        href="/platform/help"
                        title={collapsed ? 'Help & Guide' : undefined}
                        className={`group relative flex items-center gap-2 px-3 py-1.5 rounded-lg transition mt-1 ${
                            helpActive ? 'border-l-[3px] text-white font-bold shadow-md' : 'font-medium hover:bg-white/10 hover:opacity-100!'
                        }`}
                        style={helpActive ? activeItemStyle : inactiveLinkStyle}
                    >
                        <span className="w-4 h-4 flex items-center justify-center shrink-0">
                            <Icon name="help" />
                        </span>
                        <span className={`truncate text-[11px] ${collapsed ? 'hidden' : ''}`}>Help &amp; Guide</span>
                    </Link>
                </nav>
                <div
                    className="pointer-events-none absolute bottom-0 left-0 right-0 h-6"
                    style={{ backgroundImage: `linear-gradient(to top, ${SIDEBAR_BG}, transparent)` }}
                />
            </div>

            <div className="mt-3 pt-3 border-t border-white/10 shrink-0">
                <button
                    type="button"
                    onClick={confirmLogout}
                    title={collapsed ? 'Logout' : undefined}
                    className="group relative w-full flex items-center gap-3 px-3 py-2 rounded-xl text-[11px] font-semibold bg-red-600 text-white border border-red-500 hover:bg-red-700 hover:border-red-600 transition shadow-lg shadow-red-900/40"
                >
                    <span className="w-5 h-5 flex items-center justify-center shrink-0">
                        <Icon name="logout" />
                    </span>
                    <span className={collapsed ? 'hidden' : ''}>Logout</span>
                </button>
            </div>
        </aside>
    );
}

function TopBar({
    collapsed,
    bannerOffset,
    title,
    platformUser,
    contextCompany,
    unread,
    actions,
}: {
    collapsed: boolean;
    bannerOffset: number;
    title: string;
    platformUser: PlatformUser | null;
    contextCompany: CompanyRef | null;
    unread: number;
    actions?: ReactNode;
}) {
    const [profileOpen, setProfileOpen] = useState(false);
    const profileRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (profileRef.current && !profileRef.current.contains(e.target as Node)) {
                setProfileOpen(false);
            }
        }
        document.addEventListener('click', handleClickOutside);
        return () => document.removeEventListener('click', handleClickOutside);
    }, []);

    const handleSearch = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key !== 'Enter' || !contextCompany) return;
        const value = (e.target as HTMLInputElement).value.trim();
        router.get(`/platform/companies/${contextCompany.id}/kpis`, value ? { q: value } : {});
    };

    const displayName = platformUser?.name ?? 'User';
    const membership = contextCompany ? platformUser?.company_memberships.find((m) => m.company_id === contextCompany.id) : undefined;
    const roleLabel = platformUser?.is_super_admin
        ? 'Super Admin'
        : membership
          ? (ROLE_LABEL[membership.role] ?? membership.role)
          : platformUser?.is_platform_admin
            ? 'Platform Admin'
            : 'My Profile';

    const subtitleParts = [contextCompany?.code || contextCompany?.name, membership ? (ROLE_LABEL[membership.role] ?? membership.role).toUpperCase() : null, todayInKualaLumpur()].filter(Boolean);

    return (
        <div
            id="topBar"
            style={{ position: 'fixed', top: bannerOffset, left: collapsed ? 64 : 230, right: 0, zIndex: 45 }}
            className="h-14 bg-white border-b border-slate-200 px-5 grid grid-cols-3 items-center gap-3 transition-all duration-300"
        >
            <div className="leading-tight min-w-0 justify-self-start">
                <p className="text-sm font-black text-slate-800 truncate">{title}</p>
                <p className="text-[10px] text-slate-400 truncate">{subtitleParts.join(' · ')}</p>
            </div>

            <div className="w-full max-w-2xl justify-self-center flex items-center gap-2">
                <div className="relative flex-1 min-w-0">
                    <svg className="w-3.5 h-3.5 text-slate-300 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        type="text"
                        placeholder="Search KPIs..."
                        onKeyDown={handleSearch}
                        className="w-full bg-slate-50 border border-slate-200 rounded-full pl-9 pr-4 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40"
                    />
                </div>
                {actions && <div className="flex items-center gap-2 shrink-0">{actions}</div>}
            </div>

            <div className="flex items-center gap-1.5 justify-self-end">
                <Link
                    href="/platform/notifications"
                    className="relative w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-100 text-slate-400 transition"
                    aria-label="Notifications"
                >
                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 2a6 6 0 00-6 6c0 3.5-1.5 4.5-1.5 5.5S3.5 15 5 15h10c1.5 0 2.5-.5 2.5-1.5S16 11.5 16 8a6 6 0 00-6-6zM10 18a2 2 0 002-2H8a2 2 0 002 2z" />
                    </svg>
                    {unread > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-1 rounded-full bg-[#D4AF37] text-[#1a1a1a] text-[9px] font-black flex items-center justify-center">
                            {Math.min(9, unread)}
                            {unread > 9 ? '+' : ''}
                        </span>
                    )}
                </Link>

                <div className="relative" ref={profileRef}>
                    <button
                        type="button"
                        onClick={() => setProfileOpen((v) => !v)}
                        className="flex items-center gap-2 bg-white border border-slate-200 rounded-full pl-1.5 pr-2.5 py-1 shadow-sm hover:shadow-md transition"
                    >
                        <div className="w-7 h-7 rounded-full overflow-hidden shrink-0 ring-2 ring-[#D4AF37]/60">
                            <img src={avatarUrl(displayName, 36)} className="w-full h-full object-cover" alt="Profile" />
                        </div>
                        <div className="leading-tight text-left hidden sm:block">
                            <p className="text-[12px] font-bold text-slate-800 truncate max-w-[140px]">{displayName}</p>
                            <p className="text-[9px] text-slate-400 truncate max-w-[140px] uppercase">{roleLabel}</p>
                        </div>
                        <svg className={`w-3.5 h-3.5 text-slate-400 shrink-0 transition-transform ${profileOpen ? 'rotate-180' : ''}`} viewBox="0 0 20 20" fill="currentColor">
                            <path
                                fillRule="evenodd"
                                d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                clipRule="evenodd"
                            />
                        </svg>
                    </button>

                    {profileOpen && (
                        <div className="absolute right-0 mt-2 w-44 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden py-1">
                            <Link href="/platform/profile" className="flex items-center gap-2 px-3 py-2 text-[12px] font-semibold text-slate-700 hover:bg-slate-50 transition">
                                My Profile
                            </Link>
                            <div className="border-t border-slate-100 my-1" />
                            <button
                                type="button"
                                onClick={confirmLogout}
                                className="w-full text-left flex items-center gap-2 px-3 py-2 text-[12px] font-semibold text-red-600 hover:bg-red-50 transition"
                            >
                                Logout
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function PlatformLayout({ title, description, company, actions, maxWidth = 'max-w-none', children }: PlatformLayoutProps) {
    const { platformUser, platformUnreadNotificationCount, platformImpersonating, flash } = usePage<SharedProps>().props;
    const currentUrl = usePage().url;
    const unread = platformUnreadNotificationCount ?? 0;

    const [collapsed, setCollapsedState] = useState<boolean>(() => {
        try {
            return localStorage.getItem(COLLAPSE_KEY) === 'true';
        } catch {
            return false;
        }
    });

    const setCollapsed = (value: boolean) => {
        setCollapsedState(value);
        try {
            localStorage.setItem(COLLAPSE_KEY, value ? 'true' : 'false');
        } catch {
            // Blocked storage — the toggle still works for this page view.
        }
    };

    const contextCompany = resolveContextCompany(platformUser, company);
    const sections = buildSections(platformUser, contextCompany, unread);
    const bannerOffset = platformImpersonating ? 36 : 0;

    return (
        <>
            <Head title={title} />

            <div className="min-h-screen" style={{ backgroundColor: PAGE_BG }}>
                {platformImpersonating && <ImpersonationBanner collapsed={collapsed} name={platformUser?.name ?? 'this user'} />}
                <TopBar
                    collapsed={collapsed}
                    bannerOffset={bannerOffset}
                    title={title}
                    platformUser={platformUser}
                    contextCompany={contextCompany}
                    unread={unread}
                    actions={actions}
                />
                <Sidebar collapsed={collapsed} setCollapsed={setCollapsed} sections={sections} contextCompany={contextCompany} currentUrl={currentUrl} />

                <main
                    className={`min-h-screen transition-all duration-300 ${collapsed ? 'ml-[64px]' : 'ml-[230px]'}`}
                    style={{ paddingTop: 56 + bannerOffset }}
                >
                    <div className={`px-4 py-4 space-y-3 ${maxWidth} mx-auto w-full`}>
                        {flash.success && (
                            <div className="bg-emerald-50 text-emerald-700 px-3 py-2 rounded-xl text-xs border border-emerald-200">{flash.success}</div>
                        )}
                        {flash.error && <div className="bg-red-50 text-red-700 px-3 py-2 rounded-xl text-xs border border-red-200">{flash.error}</div>}
                        {description && <p className="text-xs text-slate-500">{description}</p>}
                        <div>{children}</div>
                    </div>
                </main>

                <Link
                    href="/platform/anira"
                    className="no-print fixed bottom-6 right-6 z-[9999] w-14 h-14 rounded-full bg-violet-600 hover:bg-violet-700 shadow-xl flex items-center justify-center transition"
                    title="ANIRA - KPI AI Assistant"
                >
                    <svg className="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z" />
                    </svg>
                </Link>
            </div>
        </>
    );
}
