import { Link, router, usePage } from '@inertiajs/react';
import { KeyboardEvent, ReactNode, useEffect, useRef, useState } from 'react';
import { useSidebar } from '../Layouts/SidebarContext';
import { SharedPageProps } from '../types';

function useDocumentTitle(): string {
    const { url } = usePage();
    const [title, setTitle] = useState('Dashboard');

    useEffect(() => {
        const id = requestAnimationFrame(() => {
            const first = (document.title || 'Dashboard').split(/[·|—]/)[0].trim();
            setTitle(first || 'Dashboard');
        });
        return () => cancelAnimationFrame(id);
    }, [url]);

    return title;
}

function todayInKualaLumpur(): string {
    return new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Kuala_Lumpur',
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    }).format(new Date());
}

export default function TopBar({
    pageTitle,
    pageSubtitle,
    pageActions,
}: {
    pageTitle?: string;
    pageSubtitle?: ReactNode;
    /** Extra controls (filters, etc.) rendered right next to the search box. */
    pageActions?: ReactNode;
}) {
    const { collapsed } = useSidebar();
    const { props } = usePage<SharedPageProps>();
    const { layout } = props;
    const documentTitle = useDocumentTitle();
    const title = pageTitle ?? documentTitle;

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
        if (e.key !== 'Enter') return;
        const value = (e.target as HTMLInputElement).value.trim();
        window.location.href = '/kpi' + (value ? '?q=' + encodeURIComponent(value) : '');
    };

    const handleLogout = () => {
        if (confirm('You are about to logout. Continue?')) {
            router.post('/logout');
        }
    };

    const displayName = layout.shortName || layout.fullName || layout.employeeName || 'User';

    return (
        <>
            {layout.adminImpersonating && (
                <>
                    <div
                        className="no-print fixed top-0 left-0 right-0 z-[9997] flex items-center justify-center gap-3 px-6 py-2 text-xs font-bold text-white shadow-md"
                        style={{ background: 'linear-gradient(90deg,#7c3aed,#a78bfa)' }}
                    >
                        <span>
                            👁 Viewing as <strong>{displayName}</strong> — BTS Admin session
                        </span>
                        <button
                            type="button"
                            onClick={() => router.post('/admin/view-as/stop')}
                            className="rounded-lg border border-white/40 bg-white/20 px-3 py-1 text-[11px] font-extrabold"
                        >
                            Return to my account
                        </button>
                    </div>
                    <div style={{ height: 36 }} />
                </>
            )}

            <div
                id="topBar"
                style={{ position: 'fixed', top: layout.adminImpersonating ? 36 : 0, left: collapsed ? 64 : 230, right: 0, zIndex: 45 }}
                className="h-14 bg-white border-b border-slate-200 px-5 grid grid-cols-3 items-center gap-3 transition-all duration-300"
            >
                <div className="leading-tight min-w-0 justify-self-start">
                    <p className="text-sm font-black text-slate-800 truncate">{title}</p>
                    <p className="text-[10px] text-slate-400 truncate">
                        {pageSubtitle ?? (
                            <>
                                {layout.companyCode}
                                {layout.departmentCode ? ` · ${layout.departmentCode}` : ''} · {todayInKualaLumpur()}
                            </>
                        )}
                    </p>
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
                    {pageActions && <div className="flex items-center gap-2 shrink-0">{pageActions}</div>}
                </div>

                <div className="flex items-center gap-1.5 justify-self-end">
                    <Link
                        href="/notifications"
                        className="relative w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-100 text-slate-400 transition"
                        aria-label="Notifications"
                    >
                        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 2a6 6 0 00-6 6c0 3.5-1.5 4.5-1.5 5.5S3.5 15 5 15h10c1.5 0 2.5-.5 2.5-1.5S16 11.5 16 8a6 6 0 00-6-6zM10 18a2 2 0 002-2H8a2 2 0 002 2z" />
                        </svg>
                        {layout.unreadNotificationCount > 0 && (
                            <span className="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-1 rounded-full bg-[#D4AF37] text-[#1a1a1a] text-[9px] font-black flex items-center justify-center">
                                {Math.min(9, layout.unreadNotificationCount)}
                                {layout.unreadNotificationCount > 9 ? '+' : ''}
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
                                <img
                                    src={`https://ui-avatars.com/api/?name=${encodeURIComponent(displayName)}&background=D4AF37&color=1a1a1a&size=36`}
                                    className="w-full h-full object-cover"
                                    alt="Profile"
                                />
                            </div>
                            <div className="leading-tight text-left hidden sm:block">
                                <p className="text-[12px] font-bold text-slate-800 truncate max-w-[140px]">
                                    {layout.salutation ? `${layout.salutation} ` : ''}
                                    {displayName}
                                </p>
                                <p className="text-[9px] text-slate-400 truncate max-w-[140px]">{layout.position || 'My Profile'}</p>
                            </div>
                            <svg
                                className={`w-3.5 h-3.5 text-slate-400 shrink-0 transition-transform ${profileOpen ? 'rotate-180' : ''}`}
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fillRule="evenodd"
                                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                    clipRule="evenodd"
                                />
                            </svg>
                        </button>

                        {profileOpen && (
                            <div className="absolute right-0 mt-2 w-44 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden py-1">
                                <Link href="/profile" className="flex items-center gap-2 px-3 py-2 text-[12px] font-semibold text-slate-700 hover:bg-slate-50 transition">
                                    My Profile
                                </Link>
                                <Link href="/settings" className="flex items-center gap-2 px-3 py-2 text-[12px] font-semibold text-slate-700 hover:bg-slate-50 transition">
                                    Settings
                                </Link>
                                <div className="border-t border-slate-100 my-1" />
                                <button
                                    type="button"
                                    onClick={handleLogout}
                                    className="w-full text-left flex items-center gap-2 px-3 py-2 text-[12px] font-semibold text-red-600 hover:bg-red-50 transition"
                                >
                                    Logout
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
