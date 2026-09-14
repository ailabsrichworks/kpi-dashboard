import { ElementType } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import Icon from './Icon';
import { useSidebar } from '../Layouts/SidebarContext';
import { navSections, isNavItemActive } from '../config/navigation';
import { SharedPageProps } from '../types';

export default function Sidebar() {
    const { collapsed, setCollapsed, toggle } = useSidebar();
    const { url, props } = usePage<SharedPageProps>();
    const { layout } = props;

    const departmentCode = (layout.departmentCode ?? '').trim().toUpperCase();
    const isBts = departmentCode === 'BTS';
    const isSltDept = ['SLT OFFICE', 'BTS'].includes(departmentCode);
    const isQuarterControlAuthorized = isBts || layout.quarterControlAccess === true;
    const hasTitanAccess =
        (layout.role !== 'VP' && layout.companyCode === 'RCG' && layout.departmentCode === 'TITAN') || isBts;

    const companyInitial = (layout.companyCode || 'R').charAt(0).toUpperCase();

    const handleLogout = () => {
        if (confirm('You are about to logout. Continue?')) {
            router.post('/logout');
        }
    };

    return (
        <aside
            id="sidebar"
            className={`fixed left-0 top-0 z-40 h-screen bg-[#111111] text-white
            border-r border-white/10 shadow-[4px_0_24px_rgba(0,0,0,0.30)]
            px-3 py-4 flex flex-col overflow-visible shrink-0 transition-all duration-300
            ${collapsed ? 'collapsed w-[64px] min-w-[64px] max-w-[64px]' : 'w-[230px] min-w-[230px] max-w-[230px]'}`}
        >
            <div className="sidebar-accent-bar absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10" />

            <button
                type="button"
                onClick={() => toggle()}
                className={`absolute top-4 right-3 z-[9999] w-7 h-7 flex items-center justify-center
                text-[#A4C3B2] bg-white/10 border border-white/20 rounded-full
                hover:bg-white/20 hover:text-white transition text-sm ${collapsed ? 'hidden' : ''}`}
                aria-label="Close Sidebar"
            >
                ×
            </button>

            {/* COMPANY AREA */}
            <button
                type="button"
                onClick={() => collapsed && setCollapsed(false)}
                className="group w-full flex items-center gap-2 mb-3 shrink-0 pr-8 text-left hover:bg-white/10 rounded-xl p-1.5 transition relative"
                aria-label="Open Sidebar"
            >
                <div className="sidebar-brand-tile w-10 h-10 rounded-xl bg-[#C8102E] border-2 border-[#D4AF37] flex items-center justify-center shrink-0 overflow-hidden p-1">
                    <span className={`sidebar-logo w-full h-full text-white font-bold text-base flex items-center justify-center ${collapsed ? 'hidden' : ''}`}>
                        {companyInitial}
                    </span>
                    <span className={`sidebar-icon-only text-white font-bold text-lg ${collapsed ? '' : 'hidden'}`}>☰</span>
                </div>

                <div className={`sidebar-text leading-tight text-left min-w-0 ${collapsed ? 'hidden' : ''}`}>
                    <h1 className="text-[12px] font-bold tracking-wide text-white leading-tight break-words whitespace-pre-line">
                        {layout.companyDisplayName || 'RICHWORKS KPI'}
                    </h1>
                    <p className="sidebar-accent-text text-[9px] text-[#D4AF37] uppercase tracking-[0.14em] mt-1 font-semibold">
                        Performance System
                    </p>
                </div>

                <div className="sidebar-tooltip hidden absolute left-[58px] top-1/2 -translate-y-1/2 bg-black text-white text-[10px] px-2 py-1 rounded-md opacity-0 group-hover:opacity-100 pointer-events-none transition whitespace-nowrap z-[9999] shadow-lg">
                    Open Sidebar
                </div>
            </button>

            <div className="sidebar-accent-line h-px w-full shrink-0 mb-3 bg-gradient-to-r from-[#D4AF37] to-transparent" />

            {/* NAVIGATION */}
            <div className="relative flex-1 min-h-0 flex flex-col">
                <nav className="flex-1 overflow-y-auto text-[12px] space-y-5 pr-1 min-h-0 custom-scroll">
                    {navSections.map((section) => {
                        if (section.hrOnly && !layout.hrAccess) return null;
                        if (section.btsOnly && !isBts && !(section.quarterControlOnly && isQuarterControlAuthorized)) return null;

                        return (
                            <div key={section.title}>
                                <div className={`sidebar-text flex items-center gap-2 mb-1 px-2 ${collapsed ? 'hidden' : ''}`}>
                                    <p className="sidebar-accent-text text-[9px] text-[#D4AF37] font-semibold uppercase tracking-widest shrink-0">
                                        {section.title}
                                    </p>
                                    <div className="sidebar-accent-line h-px flex-1 bg-gradient-to-r from-[#D4AF37] to-transparent" />
                                </div>

                                <div className="space-y-1">
                                    {section.items.map((item) => {
                                        if (item.sltOnly && !isSltDept) return null;
                                        if (item.titanOnly && !hasTitanAccess) return null;
                                        if (item.btsOnly && !isBts) return null;

                                        const isActive = isNavItemActive(item, url);
                                        const badgeCount = item.badge === 'unreadNotifications' ? layout.unreadNotificationCount : 0;

                                        // Items still served by a plain Blade view() (not
                                        // Inertia::render()) must use a real <a> tag, not
                                        // Inertia's <Link> -- see NavItem['legacy'] docblock.
                                        const NavTag = (item.legacy ? 'a' : Link) as ElementType;

                                        return (
                                            <NavTag
                                                key={item.label}
                                                href={item.href}
                                                className={`group relative flex items-center gap-3 px-3 py-2 rounded-xl transition ${
                                                    isActive
                                                        ? 'sidebar-active-item bg-gradient-to-r from-[#C8102E] to-[#7A0019] border-l-[3px] border-[#D4AF37] text-white font-black shadow-md'
                                                        : 'text-white/85 font-medium hover:bg-white/10 hover:text-white'
                                                }`}
                                            >
                                                <span className="w-5 h-5 flex items-center justify-center shrink-0">
                                                    <Icon name={item.icon} className="w-4 h-4" />
                                                </span>

                                                <div className="flex items-center justify-between w-full min-w-0 gap-2">
                                                    <span className={`sidebar-text truncate ${collapsed ? 'hidden' : ''}`}>{item.label}</span>

                                                    {badgeCount > 0 && (
                                                        <span className="sidebar-text min-w-[20px] h-[20px] rounded-full bg-red-500 text-white text-[10px] font-black flex items-center justify-center px-1 shadow-lg shadow-red-500/30">
                                                            {badgeCount}
                                                        </span>
                                                    )}
                                                </div>

                                                <div className="sidebar-tooltip hidden absolute left-[58px] top-1/2 -translate-y-1/2 bg-black text-white text-[10px] px-2 py-1 rounded-md opacity-0 group-hover:opacity-100 pointer-events-none transition duration-150 whitespace-nowrap z-[9999] shadow-lg">
                                                    {item.label}
                                                </div>
                                            </NavTag>
                                        );
                                    })}
                                </div>
                            </div>
                        );
                    })}

                    {/* ACCOUNT SETTINGS */}
                    <Link
                        href="/settings"
                        className={`group relative flex items-center gap-2 px-3 py-1.5 rounded-lg transition mt-1 ${
                            url.replace(/^\//, '').startsWith('settings')
                                ? 'sidebar-active-item bg-gradient-to-r from-[#C8102E] to-[#7A0019] border-l-[3px] border-[#D4AF37] text-white font-bold shadow-md'
                                : 'text-white/60 font-medium hover:bg-white/10 hover:text-white'
                        }`}
                    >
                        <span className="w-4 h-4 flex items-center justify-center shrink-0">
                            <Icon name="settings" />
                        </span>
                        <span className={`sidebar-text truncate text-[11px] ${collapsed ? 'hidden' : ''}`}>Account Settings</span>
                        <div className="sidebar-tooltip hidden absolute left-[58px] top-1/2 -translate-y-1/2 bg-black text-white text-[10px] px-2 py-1 rounded-md opacity-0 group-hover:opacity-100 pointer-events-none transition duration-150 whitespace-nowrap z-[9999] shadow-lg">
                            Account Settings
                        </div>
                    </Link>

                    {/* HELP & GUIDE */}
                    <Link
                        href="/help"
                        className={`group relative flex items-center gap-2 px-3 py-1.5 rounded-lg transition mt-1 ${
                            url.replace(/^\//, '').startsWith('help')
                                ? 'sidebar-active-item bg-gradient-to-r from-[#C8102E] to-[#7A0019] border-l-[3px] border-[#D4AF37] text-white font-bold shadow-md'
                                : 'text-white/60 font-medium hover:bg-white/10 hover:text-white'
                        }`}
                    >
                        <span className="w-4 h-4 flex items-center justify-center shrink-0">
                            <Icon name="help" />
                        </span>
                        <span className={`sidebar-text truncate text-[11px] ${collapsed ? 'hidden' : ''}`}>Help &amp; Guide</span>
                        <div className="sidebar-tooltip hidden absolute left-[58px] top-1/2 -translate-y-1/2 bg-black text-white text-[10px] px-2 py-1 rounded-md opacity-0 group-hover:opacity-100 pointer-events-none transition duration-150 whitespace-nowrap z-[9999] shadow-lg">
                            Help &amp; Guide
                        </div>
                    </Link>
                </nav>
                <div className="sidebar-fade pointer-events-none absolute bottom-0 left-0 right-0 h-6 bg-gradient-to-t from-[#111111] to-transparent" />
            </div>

            {/* SYSTEM ZONE */}
            <div className={`sidebar-system mt-3 pt-3 border-t border-white/10 shrink-0`}>
                <button
                    type="button"
                    onClick={handleLogout}
                    className="group relative w-full flex items-center gap-3 px-3 py-2 rounded-xl text-[11px] font-semibold bg-red-600 text-white border border-red-500 hover:bg-red-700 hover:border-red-600 transition shadow-lg shadow-red-900/40"
                >
                    <span className="w-5 h-5 flex items-center justify-center shrink-0">
                        <Icon name="logout" />
                    </span>
                    <span className={`sidebar-text ${collapsed ? 'hidden' : ''}`}>Logout</span>
                    <div className="sidebar-tooltip hidden absolute left-[58px] top-1/2 -translate-y-1/2 bg-black text-white text-[10px] px-2 py-1 rounded-md opacity-0 group-hover:opacity-100 pointer-events-none transition duration-150 whitespace-nowrap z-[9999] shadow-lg">
                        Logout
                    </div>
                </button>
            </div>
        </aside>
    );
}
