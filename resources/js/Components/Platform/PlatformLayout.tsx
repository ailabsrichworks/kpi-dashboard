import { Head, Link, router, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';
import {
    AdjustmentsIcon,
    BuildingIcon,
    ChecklistIcon,
    ClipboardCheckIcon,
    DocumentDuplicateIcon,
    HomeIcon,
    LogoutIcon,
    MenuIcon,
    RocketIcon,
    ShieldCheckIcon,
    SparklesIcon,
    TargetIcon,
    UploadIcon,
    UserCircleIcon,
    UsersIcon,
    XMarkIcon,
} from './Icons';

/**
 * The one thing every Platform page was missing (see the UI/UX pass this
 * replaces): a persistent, predictable place to be. Before this, each of the
 * 19 Platform pages built its own header from scratch — a different set of
 * "quick links" depending on which page you happened to load, no indication
 * of where you were in the system, no way to get anywhere without either a
 * link on the CURRENT page pointing there or typing a URL by hand. That's
 * the opposite of "make everyone understand what's going on" for someone who
 * isn't a developer reading the route list.
 *
 * `platformUser` comes from the globally-shared Inertia prop
 * (HandleInertiaRequests) — every /platform/* page gets it automatically,
 * no controller changes needed. `company` is passed explicitly by whichever
 * page is company-scoped (it already has that data in hand); when absent,
 * the sidebar shows only platform-wide navigation.
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
    /** Tailwind max-width class for the content column — most pages read better a bit narrower than the full sidebar-adjusted width. */
    maxWidth?: string;
    children: ReactNode;
}

interface SharedProps {
    platformUser: PlatformUser | null;
    flash: { error?: string | null; success?: string | null };
    [key: string]: unknown;
}

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

function NavLink({
    href,
    icon,
    children,
    currentUrl,
}: {
    href: string;
    icon: ReactNode;
    children: ReactNode;
    currentUrl: string;
}) {
    const active = currentUrl === href || (href !== '/platform/dashboard' && currentUrl.startsWith(href));

    return (
        <Link
            href={href}
            className={`flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                active ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'
            }`}
        >
            <span className="flex-none">{icon}</span>
            {children}
        </Link>
    );
}

function NavSectionLabel({ children }: { children: ReactNode }) {
    return <p className="px-3 mt-5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-500">{children}</p>;
}

function SidebarContent({ platformUser, company, currentUrl }: { platformUser: PlatformUser | null; company?: CompanyRef | null; currentUrl: string }) {
    const contextCompany =
        company ??
        (platformUser && !platformUser.is_super_admin && platformUser.company_memberships[0]
            ? {
                  id: platformUser.company_memberships[0].company_id,
                  name: platformUser.company_memberships[0].companies?.name ?? 'Your company',
                  code: platformUser.company_memberships[0].companies?.code ?? '',
              }
            : null);

    const isAdminHere = contextCompany ? canAdminister(platformUser, contextCompany.id) : false;
    const isMemberHere = contextCompany ? isCompanyMember(platformUser, contextCompany.id) : false;

    return (
        <div className="flex h-full flex-col">
            <div className="px-4 py-5">
                <Link href="/platform/dashboard" className="flex items-center gap-2.5">
                    <span className="flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-white/10 text-sm font-bold text-white">
                        P
                    </span>
                    <span>
                        <span className="block text-sm font-bold text-white leading-none">Performix</span>
                        <span className="block text-[11px] text-slate-400 leading-none mt-0.5">KPI Platform</span>
                    </span>
                </Link>
            </div>

            <nav className="flex-1 overflow-y-auto px-3 pb-4">
                <NavLink href="/platform/dashboard" icon={<HomeIcon className="w-[18px] h-[18px]" />} currentUrl={currentUrl}>
                    Dashboard
                </NavLink>
                <NavLink href="/platform/anira" icon={<SparklesIcon className="w-[18px] h-[18px]" />} currentUrl={currentUrl}>
                    Ask ANIRA
                </NavLink>

                {contextCompany && isMemberHere && (
                    <>
                        <NavSectionLabel>{contextCompany.name}</NavSectionLabel>
                        {isAdminHere && (
                            <NavLink
                                href={`/platform/companies/${contextCompany.id}/departments`}
                                icon={<UsersIcon className="w-[18px] h-[18px]" />}
                                currentUrl={currentUrl}
                            >
                                Departments &amp; People
                            </NavLink>
                        )}
                        <NavLink
                            href={`/platform/companies/${contextCompany.id}/kpis`}
                            icon={<TargetIcon className="w-[18px] h-[18px]" />}
                            currentUrl={currentUrl}
                        >
                            KPIs
                        </NavLink>
                        <NavLink
                            href={`/platform/companies/${contextCompany.id}/tasks`}
                            icon={<ChecklistIcon className="w-[18px] h-[18px]" />}
                            currentUrl={currentUrl}
                        >
                            Tasks
                        </NavLink>
                        {isAdminHere && (
                            <NavLink
                                href={`/platform/companies/${contextCompany.id}/onboarding`}
                                icon={<RocketIcon className="w-[18px] h-[18px]" />}
                                currentUrl={currentUrl}
                            >
                                Onboarding
                            </NavLink>
                        )}
                        {platformUser?.is_super_admin && (
                            <NavLink
                                href={`/platform/companies/${contextCompany.id}/import`}
                                icon={<UploadIcon className="w-[18px] h-[18px]" />}
                                currentUrl={currentUrl}
                            >
                                Import data
                            </NavLink>
                        )}
                        {isAdminHere && (
                            <NavLink
                                href={`/platform/companies/${contextCompany.id}/audit-log`}
                                icon={<ShieldCheckIcon className="w-[18px] h-[18px]" />}
                                currentUrl={currentUrl}
                            >
                                Audit log
                            </NavLink>
                        )}
                    </>
                )}

                {platformUser?.is_super_admin && (
                    <>
                        <NavSectionLabel>Richworks Center</NavSectionLabel>
                        <NavLink href="/platform/companies" icon={<BuildingIcon className="w-[18px] h-[18px]" />} currentUrl={currentUrl}>
                            Companies
                        </NavLink>
                        <NavLink href="/platform/kpi-templates" icon={<DocumentDuplicateIcon className="w-[18px] h-[18px]" />} currentUrl={currentUrl}>
                            KPI templates
                        </NavLink>
                        <NavLink href="/platform/admins" icon={<AdjustmentsIcon className="w-[18px] h-[18px]" />} currentUrl={currentUrl}>
                            Platform admins
                        </NavLink>
                        <NavLink href="/platform/audit-log" icon={<ClipboardCheckIcon className="w-[18px] h-[18px]" />} currentUrl={currentUrl}>
                            Audit log
                        </NavLink>
                    </>
                )}
            </nav>

            <div className="border-t border-white/10 px-3 py-3">
                <NavLink href="/platform/profile" icon={<UserCircleIcon className="w-[18px] h-[18px]" />} currentUrl={currentUrl}>
                    {platformUser?.name ?? 'My profile'}
                </NavLink>
                <button
                    onClick={() => router.post('/platform/logout')}
                    className="mt-0.5 flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white transition-colors"
                >
                    <LogoutIcon className="w-[18px] h-[18px] flex-none" />
                    Sign out
                </button>
            </div>
        </div>
    );
}

function FlashBanner({ tone, children }: { tone: 'success' | 'error'; children: ReactNode }) {
    const styles =
        tone === 'success'
            ? 'bg-emerald-50 border-emerald-200 text-emerald-700'
            : 'bg-red-50 border-red-200 text-red-700';

    return <div className={`mb-5 rounded-xl border px-4 py-3 text-sm ${styles}`}>{children}</div>;
}

export default function PlatformLayout({ title, description, company, actions, maxWidth = 'max-w-5xl', children }: PlatformLayoutProps) {
    const { platformUser, flash } = usePage<SharedProps>().props;
    const currentUrl = usePage().url;
    const [mobileNavOpen, setMobileNavOpen] = useState(false);

    return (
        <>
            <Head title={title} />

            <div className="min-h-screen bg-slate-50 lg:flex">
                <aside className="hidden lg:flex lg:w-64 lg:flex-none lg:flex-col bg-brand-900">
                    <SidebarContent platformUser={platformUser} company={company} currentUrl={currentUrl} />
                </aside>

                {mobileNavOpen && (
                    <div className="fixed inset-0 z-40 lg:hidden">
                        <div className="absolute inset-0 bg-black/40" onClick={() => setMobileNavOpen(false)} />
                        <aside className="absolute inset-y-0 left-0 w-72 bg-brand-900">
                            <div className="flex justify-end p-3">
                                <button onClick={() => setMobileNavOpen(false)} className="text-slate-300 hover:text-white">
                                    <XMarkIcon className="w-5 h-5" />
                                </button>
                            </div>
                            <SidebarContent platformUser={platformUser} company={company} currentUrl={currentUrl} />
                        </aside>
                    </div>
                )}

                <div className="flex-1 min-w-0">
                    <header className="sticky top-0 z-30 bg-white border-b border-slate-200 px-4 py-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between gap-4">
                            <div className="flex items-center gap-3 min-w-0">
                                <button
                                    onClick={() => setMobileNavOpen(true)}
                                    className="lg:hidden flex-none text-slate-500 hover:text-slate-700"
                                    aria-label="Open menu"
                                >
                                    <MenuIcon className="w-5 h-5" />
                                </button>
                                <div className="min-w-0">
                                    {company && (
                                        <p className="text-[11px] font-semibold text-brand-800 uppercase tracking-wide truncate">
                                            {company.name}
                                            {company.code ? ` · ${company.code}` : ''}
                                        </p>
                                    )}
                                    <h1 className="text-lg font-bold text-slate-900 truncate">{title}</h1>
                                    {description && <p className="text-xs text-slate-500 mt-0.5">{description}</p>}
                                </div>
                            </div>
                            {actions && <div className="flex-none flex items-center gap-3">{actions}</div>}
                        </div>
                    </header>

                    <main className={`p-4 sm:p-6 lg:p-8 ${maxWidth} mx-auto w-full`}>
                        {flash.error && <FlashBanner tone="error">{flash.error}</FlashBanner>}
                        {flash.success && <FlashBanner tone="success">{flash.success}</FlashBanner>}
                        {children}
                    </main>
                </div>
            </div>
        </>
    );
}
