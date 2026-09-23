import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppLayout from '../Layouts/AppLayout';
import PasswordInput from '../Components/PasswordInput';
import { SharedPageProps } from '../types';
import {
    DEFAULT_FONT_THEME,
    DEFAULT_MAIN_THEME,
    DEFAULT_SIDEBAR_THEME,
    FONT_FAMILIES,
    FONT_SIZES,
    FontTheme,
    MAIN_PALETTES,
    MainTheme,
    PALETTE_CATEGORIES,
    SIDEBAR_PALETTES,
    SidebarTheme,
    swatchGradient,
} from '../config/themePalettes';

interface SettingsUser {
    role?: string | null;
    salutation?: string | null;
    email?: string | null;
    department_code?: string | null;
    short_name?: string | null;
    full_name?: string | null;
    theme_bg?: string | null;
    theme_card?: string | null;
    theme_accent?: string | null;
    theme_accent2?: string | null;
    theme_border?: string | null;
    theme_text?: string | null;
    theme_sidebar_bg?: string | null;
    theme_sidebar_accent?: string | null;
    theme_sidebar_text?: string | null;
    theme_font_family?: string | null;
    theme_font_size?: string | null;
}

interface SettingsPageProps {
    user: SettingsUser;
    companyLogoUrl?: string | null;
}

type TabKey = 'profile' | 'telegram' | 'email' | 'password' | 'appearance';
const VALID_TABS: TabKey[] = ['profile', 'telegram', 'email', 'password', 'appearance'];

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

const PREVIEW_LINKS = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'KPI List', href: '/kpi' },
    { label: 'Job Description', href: '/job-description' },
    { label: 'Performance Report', href: '/performance/report/q1' },
    { label: 'Attendance', href: '/attendance' },
    { label: 'Titan KPI', href: '/my-department-kpi' },
    { label: 'Approval', href: '/approval' },
    { label: 'Notifications', href: '/notifications' },
    { label: 'Profile', href: '/profile' },
];

export default function Settings({ user, companyLogoUrl }: SettingsPageProps) {
    const { flash } = usePage<SharedPageProps>().props;

    const [activeTab, setActiveTab] = useState<TabKey>(() => {
        const saved = localStorage.getItem('settingsActiveTab');
        return (VALID_TABS as string[]).includes(saved ?? '') ? (saved as TabKey) : 'profile';
    });

    useEffect(() => {
        localStorage.setItem('settingsActiveTab', activeTab);
    }, [activeTab]);

    const hasCustomTheme = !!(user.theme_bg || user.theme_card || user.theme_accent || user.theme_border || user.theme_text || user.theme_accent2);
    const hasCustomSidebarTheme = !!(user.theme_sidebar_bg || user.theme_sidebar_accent || user.theme_sidebar_text);
    const hasAnyCustomTheme = hasCustomTheme || hasCustomSidebarTheme;
    const hasCustomFont = !!(user.theme_font_family || user.theme_font_size);
    const isBts = (user.department_code ?? '').toUpperCase().trim() === 'BTS';

    return (
        <AppLayout>
            <Head title="Account Settings" />

            <div className="p-4 space-y-4">
                <div className="flex items-center justify-between">
                    <Link href="/profile" className="text-[10px] text-slate-500 hover:text-slate-800">
                        ← Profile
                    </Link>
                </div>

                <div>
                    <h1 className="text-lg font-black text-slate-900">Account Settings</h1>
                    <p className="text-[12px] text-slate-500 mt-0.5">Notifications, email, password, and appearance — pick a section on the left.</p>
                </div>

                {flash.success && (
                    <div className="rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-[12px] font-semibold text-emerald-700">
                        ✓ {flash.success}
                    </div>
                )}
                {flash.error && (
                    <div className="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-[12px] font-semibold text-red-700">{flash.error}</div>
                )}

                <div className="flex flex-col md:flex-row gap-4 items-start">
                    <nav className="w-full md:w-56 shrink-0 bg-white rounded-2xl shadow-sm border border-slate-200 p-2 flex md:flex-col gap-1 overflow-x-auto md:overflow-visible">
                        <SettingsNavButton active={activeTab === 'profile'} onClick={() => setActiveTab('profile')} label="Profile">
                            <circle cx="12" cy="8" r="4" />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 21c0-4 4-6 8-6s8 2 8 6" />
                        </SettingsNavButton>

                        <p className="hidden md:block text-[9px] uppercase tracking-widest font-black text-slate-400 px-3 pt-3 pb-1">Notifications</p>

                        <button
                            type="button"
                            onClick={() => setActiveTab('telegram')}
                            className={`settings-nav-btn ${activeTab === 'telegram' ? 'active-tab' : ''}`}
                        >
                            <svg className="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="#229ED9">
                                <path d="M21.94 4.53a1.6 1.6 0 0 0-1.63-.27L2.98 10.98a1.53 1.53 0 0 0 .1 2.88l4.54 1.42 1.76 5.5c.14.44.5.72.94.72.03 0 .06 0 .1-.01.34-.03.63-.24.77-.55l2.15-3.9 4.5 3.3c.24.18.53.27.82.27.14 0 .29-.02.43-.07a1.5 1.5 0 0 0 1-1.1l3.03-13.7a1.6 1.6 0 0 0-.62-1.74Zm-3.35 2.68-8.03 7.28-.31 3.35-1.35-4.22 8.6-6.9c.2-.16.42.1.24.28l-6.9 6.24a.5.5 0 0 0-.15.3l-.2 2.13 8.6-9.7c.2-.23.5.03.33.24Z" />
                            </svg>
                            <span className="truncate">Telegram</span>
                        </button>

                        <p className="hidden md:block text-[9px] uppercase tracking-widest font-black text-slate-400 px-3 pt-3 pb-1">Account Security</p>

                        <SettingsNavButton active={activeTab === 'email'} onClick={() => setActiveTab('email')} label="Change Email">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 0 0 2.22 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z" />
                        </SettingsNavButton>
                        <SettingsNavButton active={activeTab === 'password'} onClick={() => setActiveTab('password')} label="Change Password">
                            <rect x="4" y="11" width="16" height="9" rx="2" />
                            <path strokeLinecap="round" strokeLinejoin="round" d="M8 11V7a4 4 0 0 1 8 0v4" />
                        </SettingsNavButton>

                        <p className="hidden md:block text-[9px] uppercase tracking-widest font-black text-slate-400 px-3 pt-3 pb-1">Personalisation</p>

                        <button
                            type="button"
                            onClick={() => setActiveTab('appearance')}
                            className={`settings-nav-btn ${activeTab === 'appearance' ? 'active-tab' : ''}`}
                        >
                            <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M12 3a9 9 0 1 0 0 18c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.4-.5-.8-.5-1.3 0-1.1.9-2 2-2h2c2.2 0 4-1.8 4-4 0-4.4-4-8-9-8Z"
                                />
                                <circle cx="7.5" cy="10.5" r="1" />
                                <circle cx="12" cy="7.5" r="1" />
                                <circle cx="16.5" cy="10.5" r="1" />
                            </svg>
                            <span className="truncate">Appearance</span>
                            {hasAnyCustomTheme && (
                                <span className="ml-auto text-[8px] font-black uppercase tracking-wide px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 shrink-0">
                                    Custom
                                </span>
                            )}
                        </button>

                        {isBts && (
                            <>
                                <p className="hidden md:block text-[9px] uppercase tracking-widest font-black text-slate-400 px-3 pt-3 pb-1">Admin</p>
                                <Link href="/admin/view-as" className="settings-nav-btn">
                                    <svg className="w-4 h-4 shrink-0 text-violet-600" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.46 12C3.73 7.94 7.52 5 12 5s8.27 2.94 9.54 7c-1.27 4.06-5.06 7-9.54 7s-8.27-2.94-9.54-7Z" />
                                    </svg>
                                    <span className="truncate">View As</span>
                                </Link>
                            </>
                        )}
                    </nav>

                    <div className="flex-1 min-w-0 w-full">
                        {activeTab === 'profile' && <ProfileTab user={user} />}
                        {activeTab === 'telegram' && <TelegramTab />}
                        {activeTab === 'email' && <EmailTab user={user} />}
                        {activeTab === 'password' && <PasswordTab />}
                        {activeTab === 'appearance' && (
                            <AppearanceTab
                                user={user}
                                companyLogoUrl={companyLogoUrl}
                                hasCustomTheme={hasCustomTheme}
                                hasCustomSidebarTheme={hasCustomSidebarTheme}
                                hasAnyCustomTheme={hasAnyCustomTheme}
                                hasCustomFont={hasCustomFont}
                            />
                        )}
                    </div>
                </div>
            </div>

            <style>{`
                .settings-nav-btn {
                    width: 100%; display: flex; align-items: center; gap: 10px;
                    padding: 10px 12px; border-radius: 12px; font-size: 12px; font-weight: 700;
                    color: #475569; text-align: left; transition: background .15s, color .15s;
                }
                .settings-nav-btn:hover { background: #f8fafc; }
                .settings-nav-btn.active-tab { background: #eef4f1; color: #1a3d34; }
                .settings-nav-btn.active-tab svg { color: #1a3d34; }
                .palette-cat-btn.active-cat { background: #1a3d34; color: #fff; border-color: #1a3d34; }

                .palette-strip { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px; scrollbar-width: thin; }
                .palette-strip::-webkit-scrollbar { height: 6px; }
                .palette-strip::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 999px; }
                .palette-chip { flex-shrink: 0; width: 104px; border: 2px solid #e2e8f0; border-radius: 12px; overflow: hidden; cursor: pointer; transition: border-color .12s, transform .12s; background: #fff; }
                .palette-chip:hover { border-color: #6B9080; transform: translateY(-1px); }
                .palette-chip .swatch-row { display: flex; height: 44px; }
                .palette-chip .swatch-row span { flex: 1; }
                .palette-chip p { font-size: 11px; font-weight: 700; color: #475569; padding: 5px 6px; line-height: 1.2; }

                .theme-group-tab { padding: 9px 18px; border-radius: 11px; font-size: 12.5px; font-weight: 800; color: #64748b; transition: background .15s, color .15s; }
                .theme-group-tab:hover { color: #1a3d34; }
                .theme-group-tab.active-group-tab { background: #1a3d34; color: #fff; }
                .theme-group-tab.active-group-tab:hover { color: #fff; }

                .font-chip.active-font-chip { border-color: #1a3d34; background: #eef4f1; }
                .font-size-chip { padding: 9px 18px; border-radius: 11px; font-size: 12.5px; font-weight: 800; color: #64748b; transition: background .15s, color .15s; }
                .font-size-chip:hover { color: #1a3d34; }
                .font-size-chip.active-font-size-chip { background: #1a3d34; color: #fff; }
                .font-size-chip.active-font-size-chip:hover { color: #fff; }

                .tv-tag { position: absolute; top: -8px; left: 8px; z-index: 5; font-size: 7.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; padding: 2px 6px; border-radius: 999px; color: #fff; white-space: nowrap; background: #1E7A5F; }
                .tv-legend-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-right: 4px; }

                .tv-demo { display: flex; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0; }
                .tv-demo-side { width: 150px; flex-shrink: 0; padding: 12px 9px; position: relative; color: #fff; }
                .tv-demo-brand { display: flex; gap: 7px; align-items: flex-start; margin: 6px 2px 14px; }
                .tv-demo-brand-tile { width: 22px; height: 22px; border-radius: 6px; flex-shrink: 0; }
                .tv-demo-brand-name { font-size: 7.5px; font-weight: 900; line-height: 1.2; }
                .tv-demo-brand-sub { font-size: 6px; color: rgba(255,255,255,.35); letter-spacing: .08em; margin-top: 2px; }
                .tv-eyebrow { font-size: 7px; font-weight: 800; letter-spacing: .1em; margin: 10px 2px 4px; }
                .tv-accent-line-el { height: 1px; margin-bottom: 7px; }
                .tv-nav-item { display: flex; align-items: center; gap: 6px; padding: 5px 7px; border-radius: 7px; font-size: 8.5px; font-weight: 700; margin-bottom: 2px; color: rgba(255,255,255,.8); }
                .tv-nav-item.active { border-left: 2.5px solid; padding-left: 5px; color: #fff; }
                .tv-nav-dot { width: 4px; height: 4px; border-radius: 50%; background: currentColor; opacity: .5; flex-shrink: 0; }

                .tv-demo-main { flex: 1; min-width: 0; }
                .tv-demo-header { padding: 10px 12px 5px; background: #F5F5F3; }
                .tv-greet { position: relative; overflow: hidden; border-radius: 11px; color: #fff; padding: 11px 13px; }
                .tv-greet-bar { position: absolute; top: 0; left: 0; right: 0; height: 2px; }
                .tv-greet h4 { margin: 0; font-size: 12px; font-weight: 800; }
                .tv-greet-btns { display: flex; gap: 5px; margin-top: 7px; }
                .tv-greet-btns button { font-size: 8px; font-weight: 800; padding: 4px 8px; border-radius: 7px; border: none; }
                .tv-greet-btns .b1 { background: #fff; }
                .tv-greet-btns .b2 { color: #1a1a1a; }

                .tv-demo-body { padding: 8px 12px 12px; }
                .tv-my-perf { display: flex; border-radius: 12px; overflow: hidden; border: 1px solid #E5E7EB; border-top: 3px solid #D4AF37; }
                .tv-perf-score { color: #fff; padding: 10px; width: 120px; flex-shrink: 0; }
                .tv-perf-score .who { font-size: 9px; font-weight: 800; color: #1e293b; }
                .tv-perf-score .sub { font-size: 6.5px; color: #64748b; margin: 2px 0 7px; }
                .tv-perf-score .box { background: #fff; border-radius: 8px; padding: 7px; }
                .tv-perf-score .box .n { font-size: 16px; font-weight: 900; color: #0f172a; }

                .tv-perf-right { flex: 1; background: #fff; padding: 10px; min-width: 0; }
                .tv-stat-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; margin-bottom: 8px; }
                .tv-stat { border-radius: 9px; padding: 6px; text-align: center; border: 1px solid; }
                .tv-stat.grey { background: #f8fafc; border-color: #f1f5f9; }
                .tv-stat.green { background: #ecfdf5; border-color: #d1fae5; }
                .tv-stat .n { font-size: 13px; font-weight: 900; }
                .tv-stat.grey .n { color: #0f172a; } .tv-stat.green .n { color: #059669; }
                .tv-stat .l { font-size: 6px; text-transform: uppercase; color: #94a3b8; letter-spacing: .04em; }
                .tv-stat.green .l { color: #34d399; }

                .tv-qtr-label { font-size: 6.5px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
                .tv-qtr-row { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 5px; }
                .tv-qtr { background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 7px; padding: 5px 6px; }
                .tv-qtr .top { display: flex; justify-content: space-between; font-size: 7px; font-weight: 800; color: #334155; margin-bottom: 3px; }
                .tv-qtr .bar { height: 3px; background: #e2e8f0; border-radius: 2px; overflow: hidden; }
                .tv-qtr .bar i { display: block; height: 100%; background: #cbd5e1; }

                .tv-bar-row { display: flex; align-items: center; justify-content: space-between; border: 1px solid #E5E7EB; border-left: 3px solid; border-radius: 10px; padding: 8px 10px; margin-top: 8px; font-size: 8.5px; font-weight: 800; color: #334155; }
                .tv-bar-row .hint { font-size: 7px; font-weight: 700; color: #D4AF37; background: rgba(212,175,55,.12); padding: 2px 5px; border-radius: 999px; }

                .tv-linkages { margin-top: 8px; border: 1px solid #E5E7EB; border-left: 3px solid; border-radius: 10px; overflow: hidden; }
                .tv-linkages .head { color: #fff; padding: 7px 10px; font-size: 9px; font-weight: 800; }
                .tv-linkages .head .s { font-size: 6.5px; color: rgba(255,255,255,.55); font-weight: 600; margin-top: 1px; }
                .tv-linkages .empty { background: #fff; padding: 10px; font-size: 7.5px; color: #94a3b8; text-align: center; }

                .tv-doc-label { font-size: 7.5px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin: 10px 0 4px; }
                .tv-doc { border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; }
                .tv-doc-bar { font-size: 8px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; padding: 7px 10px; }
                .tv-doc-body { padding: 10px; font-size: 7.5px; color: #94a3b8; }
            `}</style>
        </AppLayout>
    );
}

function SettingsNavButton({
    active,
    onClick,
    label,
    children,
}: {
    active: boolean;
    onClick: () => void;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <button type="button" onClick={onClick} className={`settings-nav-btn ${active ? 'active-tab' : ''}`}>
            <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                {children}
            </svg>
            <span className="truncate">{label}</span>
        </button>
    );
}

function SettingsPanel({ eyebrow, children }: { eyebrow: string; children: React.ReactNode }) {
    return (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <p className="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-3">{eyebrow}</p>
            {children}
        </div>
    );
}

function ProfileTab({ user }: { user: SettingsUser }) {
    const { data, setData, post, processing } = useForm({ salutation: user.salutation ?? '' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/settings/salutation');
    }

    return (
        <SettingsPanel eyebrow="Profile">
            <p className="text-[13px] font-black text-slate-900 flex items-center gap-1.5">
                <svg className="w-3.5 h-3.5 text-[#6B9080]" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="4" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 21c0-4 4-6 8-6s8 2 8 6" />
                </svg>
                Display Title
            </p>
            <p className="text-[11px] text-slate-500 mt-0.5 mb-4">
                Shown before your name in the top bar and sidebar — e.g. "{user.salutation ?? 'Mr.'} {user.short_name ?? user.full_name ?? 'Name'}".
            </p>
            <form onSubmit={submit} className="space-y-2.5 max-w-sm">
                <select
                    value={data.salutation}
                    onChange={(e) => setData('salutation', e.target.value)}
                    className="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-[12px] focus:ring-2 focus:ring-[#6B9080]/40 focus:border-[#6B9080] focus:outline-none"
                >
                    <option value="">None</option>
                    {['Mr.', 'Mrs.', 'Ms.', 'Dr.'].map((title) => (
                        <option key={title} value={title}>
                            {title}
                        </option>
                    ))}
                </select>
                <button
                    type="submit"
                    disabled={processing}
                    className="w-full text-[11px] font-black px-3 py-2.5 rounded-xl bg-[#1a3d34] text-white hover:bg-[#2d5548] transition disabled:opacity-60"
                >
                    Save
                </button>
            </form>
        </SettingsPanel>
    );
}

function TelegramTab() {
    const [linked, setLinked] = useState(false);
    const [statusText, setStatusText] = useState('Checking status…');
    const pollRef = useRef<number | null>(null);

    async function refreshStatus() {
        try {
            const res = await fetch('/settings/telegram/status', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (data.linked) {
                setStatusText('Connected' + (data.username ? ' as @' + data.username : ''));
                setLinked(true);
                if (pollRef.current) {
                    clearInterval(pollRef.current);
                    pollRef.current = null;
                }
            } else {
                setStatusText('Not connected — link your Telegram to get daily KPI reminders.');
                setLinked(false);
            }
        } catch {
            // silent — leave current status text as-is on transient failure
        }
    }

    useEffect(() => {
        refreshStatus();
        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, []);

    async function connect() {
        const res = await fetch('/settings/telegram/connect', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();

        window.open(data.deep_link, '_blank');
        setStatusText('Waiting for confirmation in Telegram…');

        let attempts = 0;
        if (pollRef.current) clearInterval(pollRef.current);
        pollRef.current = window.setInterval(async () => {
            attempts++;
            await refreshStatus();
            if (attempts >= 40 && pollRef.current) {
                clearInterval(pollRef.current);
                pollRef.current = null;
            }
        }, 3000);
    }

    return (
        <SettingsPanel eyebrow="Notifications">
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-3 min-w-0">
                    <div className="w-10 h-10 rounded-full bg-[#229ED9]/10 flex items-center justify-center shrink-0">
                        <svg viewBox="0 0 24 24" className="w-5 h-5" fill="#229ED9">
                            <path d="M21.94 4.53a1.6 1.6 0 0 0-1.63-.27L2.98 10.98a1.53 1.53 0 0 0 .1 2.88l4.54 1.42 1.76 5.5c.14.44.5.72.94.72.03 0 .06 0 .1-.01.34-.03.63-.24.77-.55l2.15-3.9 4.5 3.3c.24.18.53.27.82.27.14 0 .29-.02.43-.07a1.5 1.5 0 0 0 1-1.1l3.03-13.7a1.6 1.6 0 0 0-.62-1.74Zm-3.35 2.68-8.03 7.28-.31 3.35-1.35-4.22 8.6-6.9c.2-.16.42.1.24.28l-6.9 6.24a.5.5 0 0 0-.15.3l-.2 2.13 8.6-9.7c.2-.23.5.03.33.24Z" />
                        </svg>
                    </div>
                    <div className="min-w-0">
                        <p className="text-[13px] font-black text-slate-900">Telegram Notifications</p>
                        <p className={`text-[11px] mt-0.5 ${linked ? 'text-emerald-600 font-semibold' : 'text-slate-500'}`}>{statusText}</p>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={connect}
                    className="text-[11px] font-black px-3 py-2 rounded-xl bg-[#6B9080] text-white hover:bg-[#5a7a6d] transition shrink-0"
                >
                    {linked ? 'Reconnect' : 'Connect Telegram'}
                </button>
            </div>
            <p className="text-[11px] text-slate-500 mt-3">Link your Telegram account to receive daily KPI reminders and approval alerts.</p>
        </SettingsPanel>
    );
}

function EmailTab({ user }: { user: SettingsUser }) {
    const { data, setData, post, processing, reset } = useForm({ email: '', current_password: '' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/settings/email', { onSuccess: () => reset() });
    }

    return (
        <SettingsPanel eyebrow="Account Security">
            <p className="text-[13px] font-black text-slate-900 flex items-center gap-1.5">
                <svg className="w-3.5 h-3.5 text-[#6B9080]" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 0 0 2.22 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z" />
                </svg>
                Change Email
            </p>
            <p className="text-[11px] text-slate-500 mt-0.5 mb-4">Current: {user.email ?? '—'}</p>
            <form onSubmit={submit} className="space-y-2.5 max-w-sm">
                <input
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    placeholder="New email address"
                    required
                    className="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-[12px] focus:ring-2 focus:ring-[#6B9080]/40 focus:border-[#6B9080] focus:outline-none"
                />
                <PasswordInput
                    name="current_password"
                    value={data.current_password}
                    onChange={(v) => setData('current_password', v)}
                    placeholder="Current password (to confirm)"
                />
                <button
                    type="submit"
                    disabled={processing}
                    className="w-full text-[11px] font-black px-3 py-2.5 rounded-xl bg-[#1a3d34] text-white hover:bg-[#2d5548] transition disabled:opacity-60"
                >
                    Update Email
                </button>
            </form>
        </SettingsPanel>
    );
}

function PasswordTab() {
    const { data, setData, post, processing, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/settings/password', { onSuccess: () => reset() });
    }

    return (
        <SettingsPanel eyebrow="Account Security">
            <p className="text-[13px] font-black text-slate-900 flex items-center gap-1.5">
                <svg className="w-3.5 h-3.5 text-[#6B9080]" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <rect x="4" y="11" width="16" height="9" rx="2" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M8 11V7a4 4 0 0 1 8 0v4" />
                </svg>
                Change Password
            </p>
            <p className="text-[11px] text-slate-500 mt-0.5 mb-4">Keep your account secure.</p>
            <form onSubmit={submit} className="space-y-2.5 max-w-sm">
                <PasswordInput name="current_password" value={data.current_password} onChange={(v) => setData('current_password', v)} placeholder="Current password" />
                <PasswordInput
                    name="password"
                    value={data.password}
                    onChange={(v) => setData('password', v)}
                    placeholder="New password (min 8 characters)"
                    minLength={8}
                />
                <PasswordInput
                    name="password_confirmation"
                    value={data.password_confirmation}
                    onChange={(v) => setData('password_confirmation', v)}
                    placeholder="Confirm new password"
                    minLength={8}
                />
                <button
                    type="submit"
                    disabled={processing}
                    className="w-full text-[11px] font-black px-3 py-2.5 rounded-xl bg-[#1a3d34] text-white hover:bg-[#2d5548] transition disabled:opacity-60"
                >
                    Update Password
                </button>
            </form>
            <p className="text-[10px] text-slate-400 mt-3">
                Forgot your current password instead?{' '}
                <a href="/forgot-password" className="font-semibold text-[#4a7c6b] hover:text-[#2d5548]">
                    Reset it via email →
                </a>
            </p>
        </SettingsPanel>
    );
}

function AppearanceTab({
    user,
    companyLogoUrl,
    hasCustomTheme,
    hasCustomSidebarTheme,
    hasAnyCustomTheme,
    hasCustomFont,
}: {
    user: SettingsUser;
    companyLogoUrl?: string | null;
    hasCustomTheme: boolean;
    hasCustomSidebarTheme: boolean;
    hasAnyCustomTheme: boolean;
    hasCustomFont: boolean;
}) {
    const [themeGroup, setThemeGroup] = useState<'sidebar' | 'main'>(() => (localStorage.getItem('themeGroupTab') === 'main' ? 'main' : 'sidebar'));
    const [paletteCategory, setPaletteCategory] = useState('All');

    const [mainTheme, setMainTheme] = useState<MainTheme>({
        bg: user.theme_bg || DEFAULT_MAIN_THEME.bg,
        card: user.theme_card || DEFAULT_MAIN_THEME.card,
        border: user.theme_border || DEFAULT_MAIN_THEME.border,
        accent: user.theme_accent || DEFAULT_MAIN_THEME.accent,
        text: user.theme_text || DEFAULT_MAIN_THEME.text,
        accent2: user.theme_accent2 || DEFAULT_MAIN_THEME.accent2,
    });
    const [sidebarTheme, setSidebarTheme] = useState<SidebarTheme>({
        bg: user.theme_sidebar_bg || DEFAULT_SIDEBAR_THEME.bg,
        accent: user.theme_sidebar_accent || DEFAULT_SIDEBAR_THEME.accent,
        text: user.theme_sidebar_text || DEFAULT_SIDEBAR_THEME.text,
    });
    const [fontTheme, setFontTheme] = useState<FontTheme>({
        family: user.theme_font_family || DEFAULT_FONT_THEME.family,
        size: (user.theme_font_size as FontTheme['size']) || DEFAULT_FONT_THEME.size,
    });

    const [themeMsg, setThemeMsg] = useState<{ text: string; ok: boolean } | null>(null);
    const [fontMsg, setFontMsg] = useState<{ text: string; ok: boolean } | null>(null);
    const [themeSaving, setThemeSaving] = useState(false);
    const [fontSaving, setFontSaving] = useState(false);

    function selectThemeGroup(group: 'sidebar' | 'main') {
        setThemeGroup(group);
        localStorage.setItem('themeGroupTab', group);
    }

    async function persist(payload: Record<string, string | null>, setSaving: (v: boolean) => void, setMsg: (v: { text: string; ok: boolean } | null) => void, successText: string) {
        setSaving(true);
        try {
            const res = await fetch('/settings/theme', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.success) {
                setMsg({ text: successText, ok: true });
                setTimeout(() => window.location.reload(), 800);
            } else {
                setMsg({ text: data.message || 'Could not save.', ok: false });
            }
        } catch {
            setMsg({ text: 'Network error.', ok: false });
        } finally {
            setSaving(false);
        }
    }

    function fullPayload(): Record<string, string | null> {
        return {
            theme_bg: mainTheme.bg,
            theme_card: mainTheme.card,
            theme_border: mainTheme.border,
            theme_accent: mainTheme.accent,
            theme_text: mainTheme.text,
            theme_accent2: mainTheme.accent2,
            theme_sidebar_bg: sidebarTheme.bg,
            theme_sidebar_accent: sidebarTheme.accent,
            theme_sidebar_text: sidebarTheme.text,
            theme_font_family: fontTheme.family,
            theme_font_size: fontTheme.size,
        };
    }

    function saveTheme() {
        persist(fullPayload(), setThemeSaving, setThemeMsg, 'Saved ✓ Applying…');
    }

    function saveFont() {
        persist(fullPayload(), setFontSaving, setFontMsg, 'Saved ✓ Applying…');
    }

    async function resetTheme() {
        setMainTheme(DEFAULT_MAIN_THEME);
        setSidebarTheme(DEFAULT_SIDEBAR_THEME);
        await persist(
            {
                theme_bg: null,
                theme_card: null,
                theme_border: null,
                theme_accent: null,
                theme_text: null,
                theme_accent2: null,
                theme_sidebar_bg: null,
                theme_sidebar_accent: null,
                theme_sidebar_text: null,
            },
            setThemeSaving,
            setThemeMsg,
            'Reset ✓',
        );
    }

    const visiblePalettes = paletteCategory === 'All' ? MAIN_PALETTES : MAIN_PALETTES.filter((p) => p.category === paletteCategory);
    const activeFontSize = FONT_SIZES.find((s) => s.key === fontTheme.size);

    return (
        <>
            <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
                <div className="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <p className="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-1">Personalisation</p>
                        <h2 className="text-[13px] font-black text-slate-900">Pick your own colours — sidebar and dashboard are independent</h2>
                        <p className="text-[11px] text-slate-500 mt-0.5 max-w-lg">
                            Each group below applies on its own — give the sidebar its own palette without touching the dashboard, or vice versa. Never
                            changes the red/amber/green status colours.
                        </p>
                    </div>
                    {hasAnyCustomTheme ? (
                        <span className="text-[9px] font-black uppercase tracking-wide px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 shrink-0">
                            Custom theme active
                        </span>
                    ) : (
                        <span className="text-[9px] font-black uppercase tracking-wide px-2 py-1 rounded-full bg-slate-100 text-slate-500 shrink-0">
                            Default theme
                        </span>
                    )}
                </div>

                <div className="inline-flex items-center gap-1 bg-slate-100 rounded-xl p-1 mb-3">
                    <button
                        type="button"
                        onClick={() => selectThemeGroup('sidebar')}
                        className={`theme-group-tab flex items-center gap-1.5 ${themeGroup === 'sidebar' ? 'active-group-tab' : ''}`}
                    >
                        <span className="w-2 h-2 rounded-full bg-[#111111] shrink-0" /> Sidebar
                        {hasCustomSidebarTheme && <span className="text-[8px] font-black uppercase px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Custom</span>}
                    </button>
                    <button
                        type="button"
                        onClick={() => selectThemeGroup('main')}
                        className={`theme-group-tab flex items-center gap-1.5 ${themeGroup === 'main' ? 'active-group-tab' : ''}`}
                    >
                        <span className="w-2 h-2 rounded-full bg-[#D4AF37] shrink-0" /> Dashboard &amp; Pages
                        {hasCustomTheme && <span className="text-[8px] font-black uppercase px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Custom</span>}
                    </button>
                </div>

                <div className="border border-slate-200 rounded-xl p-4">
                    {themeGroup === 'sidebar' ? (
                        <div>
                            <div className="palette-strip mb-3">
                                {SIDEBAR_PALETTES.map((p, i) => (
                                    <button key={i} type="button" onClick={() => setSidebarTheme({ bg: p.bg, accent: p.accent, text: p.text })} className="palette-chip" title={p.name}>
                                        <div className="swatch-row">
                                            <span style={{ background: p.bg }} />
                                            <span style={{ background: p.accent }} />
                                            <span style={{ background: p.text }} />
                                        </div>
                                        <p className="truncate">{p.name}</p>
                                    </button>
                                ))}
                            </div>

                            <div className="flex items-center gap-5 flex-wrap pt-3 border-t border-slate-100">
                                {(
                                    [
                                        { key: 'bg', label: 'Background' },
                                        { key: 'accent', label: 'Accent' },
                                        { key: 'text', label: 'Text' },
                                    ] as const
                                ).map((slot) => (
                                    <ColorSwatch
                                        key={slot.key}
                                        label={slot.label}
                                        value={sidebarTheme[slot.key]}
                                        onChange={(v) => setSidebarTheme({ ...sidebarTheme, [slot.key]: v })}
                                    />
                                ))}
                            </div>

                            {user.role === 'SLT' && (
                                <div className="pt-4 mt-1 border-t border-slate-100">
                                    <CompanyLogoUploader companyLogoUrl={companyLogoUrl} />
                                </div>
                            )}
                        </div>
                    ) : (
                        <div>
                            <div className="flex items-center gap-1.5 flex-wrap mb-3">
                                {PALETTE_CATEGORIES.map((cat) => (
                                    <button
                                        key={cat}
                                        type="button"
                                        onClick={() => setPaletteCategory(cat)}
                                        className={`palette-cat-btn text-[10.5px] font-bold px-2.5 py-1 rounded-full border border-slate-200 text-slate-500 hover:border-[#6B9080] transition shrink-0 ${
                                            paletteCategory === cat ? 'active-cat' : ''
                                        }`}
                                    >
                                        {cat}
                                    </button>
                                ))}
                            </div>

                            <div className="palette-strip mb-3">
                                {visiblePalettes.map((p, i) => (
                                    <button
                                        key={i}
                                        type="button"
                                        onClick={() => setMainTheme({ ...mainTheme, bg: p.bg, card: p.card, border: p.border, accent: p.accent })}
                                        className="palette-chip"
                                        title={`${p.name} — ${p.category}`}
                                    >
                                        <div className="swatch-row">
                                            <span style={{ background: p.bg }} />
                                            <span style={{ background: p.card }} />
                                            <span style={{ background: p.border }} />
                                        </div>
                                        <p className="truncate">{p.name}</p>
                                    </button>
                                ))}
                            </div>

                            <div className="space-y-4 pt-3 border-t border-slate-100">
                                <div>
                                    <p className="text-[9px] font-black uppercase tracking-widest text-slate-400">Base look</p>
                                    <p className="text-[10px] text-slate-400 mb-2.5">The page itself — background, cards and text.</p>
                                    <div className="flex items-center gap-5 flex-wrap">
                                        <ColorSwatch label="Page Background" hint="The colour behind everything, all pages." value={mainTheme.bg} onChange={(v) => setMainTheme({ ...mainTheme, bg: v })} />
                                        <ColorSwatch label="Card Background" hint="Fill colour of every white card." value={mainTheme.card} onChange={(v) => setMainTheme({ ...mainTheme, card: v })} />
                                        <ColorSwatch label="Card Border" hint="Outline colour around cards." value={mainTheme.border} onChange={(v) => setMainTheme({ ...mainTheme, border: v })} />
                                        <ColorSwatch label="Heading Text" hint="Colour of card titles/headings." value={mainTheme.text} onChange={(v) => setMainTheme({ ...mainTheme, text: v })} />
                                    </div>
                                </div>
                                <div>
                                    <p className="text-[9px] font-black uppercase tracking-widest text-slate-400">Accent combination</p>
                                    <p className="text-[10px] text-slate-400 mb-2.5">The pair to mix and match — used for buttons, badges and charts.</p>
                                    <div className="flex items-center gap-5 flex-wrap">
                                        <ColorSwatch
                                            label="1st Accent"
                                            hint="Buttons, badges, highlights — the main brand colour."
                                            value={mainTheme.accent}
                                            onChange={(v) => setMainTheme({ ...mainTheme, accent: v })}
                                        />
                                        <ColorSwatch
                                            label="2nd Accent"
                                            hint="Chart bars and the My Performance card — pairs with 1st Accent."
                                            value={mainTheme.accent2}
                                            onChange={(v) => setMainTheme({ ...mainTheme, accent2: v })}
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Live preview */}
                <div className="mt-4">
                    <div className="flex items-center justify-between gap-2 mb-1.5 flex-wrap">
                        <p className="text-[9px] uppercase tracking-widest font-black text-slate-400">Visualize — what actually changes</p>
                        <div className="flex items-center gap-1.5 text-[8.5px] font-bold text-emerald-700">
                            <span className="tv-legend-dot" style={{ background: '#1E7A5F' }} />
                            Everything below is customizable
                        </div>
                    </div>

                    <div className="tv-demo">
                        <div className="tv-demo-side relative" style={{ background: sidebarTheme.bg }}>
                            <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 2, background: sidebarTheme.accent }} />
                            <div className="relative">
                                <span className="tv-tag">customizable — background + accent</span>
                                <div className="tv-demo-brand">
                                    <div className="tv-demo-brand-tile" style={{ background: sidebarTheme.bg, border: `1.5px solid ${sidebarTheme.accent}` }} />
                                    <div>
                                        <div className="tv-demo-brand-name">
                                            RICHWORKS
                                            <br />
                                            CONSULTING GROUP
                                        </div>
                                        <div className="tv-demo-brand-sub">PERFORMANCE SYSTEM</div>
                                    </div>
                                </div>
                            </div>
                            <div className="relative">
                                <span className="tv-tag">customizable</span>
                                <p className="tv-eyebrow" style={{ color: sidebarTheme.accent }}>
                                    OVERVIEW
                                </p>
                                <div className="tv-accent-line-el" style={{ background: `linear-gradient(90deg, ${sidebarTheme.accent}, transparent)` }} />
                                <div
                                    className="tv-nav-item active"
                                    style={{
                                        background: `linear-gradient(135deg, ${sidebarTheme.accent}, color-mix(in srgb, ${sidebarTheme.accent} 35%, black))`,
                                        borderLeftColor: sidebarTheme.accent,
                                    }}
                                >
                                    <span className="tv-nav-dot" />
                                    Main Dashboard
                                </div>
                                <div className="tv-nav-item" style={{ color: sidebarTheme.text }}>
                                    <span className="tv-nav-dot" />
                                    Mini App
                                </div>
                                <div className="tv-nav-item" style={{ color: sidebarTheme.text }}>
                                    <span className="tv-nav-dot" />
                                    Notifications
                                </div>
                            </div>
                        </div>

                        <div className="tv-demo-main">
                            <div className="tv-demo-header">
                                <div className="relative">
                                    <span className="tv-tag">customizable</span>
                                    <div className="tv-greet" style={{ background: `linear-gradient(135deg, ${mainTheme.accent}, color-mix(in srgb, ${mainTheme.accent} 35%, black))` }}>
                                        <div className="tv-greet-bar" style={{ background: `linear-gradient(90deg, ${mainTheme.accent}, ${mainTheme.accent}, transparent)` }} />
                                        <h4>
                                            <span style={{ color: mainTheme.text }}>Hi, Good Afternoon</span> <span style={{ color: mainTheme.text }}>ARINA</span> 👋
                                        </h4>
                                        <div className="tv-greet-btns">
                                            <button type="button" className="b1" style={{ color: `color-mix(in srgb, ${mainTheme.accent} 70%, black)` }}>
                                                + Create KPI
                                            </button>
                                            <button type="button" className="b2" style={{ background: mainTheme.accent }}>
                                                My KPIs
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="tv-demo-body" style={{ background: mainTheme.bg }}>
                                <div className="relative">
                                    <span className="tv-tag">customizable — page background</span>
                                </div>
                                <div className="relative mt-2">
                                    <span className="tv-tag">customizable border + Chart accent fill</span>
                                    <div className="tv-my-perf" style={{ borderColor: mainTheme.border }}>
                                        <div className="tv-perf-score" style={{ background: `color-mix(in srgb, ${mainTheme.accent2} 18%, white)` }}>
                                            <p className="who">ARINA</p>
                                            <p className="sub">TESTER · BTS</p>
                                            <div className="box">
                                                <span className="n">82.4</span>
                                                <span style={{ fontSize: 8, color: '#94a3b8' }}>%</span>
                                            </div>
                                        </div>
                                        <div className="tv-perf-right">
                                            <div className="tv-stat-row">
                                                <div className="tv-stat grey">
                                                    <div className="n">12</div>
                                                    <div className="l">Total KPIs</div>
                                                </div>
                                                <div className="tv-stat green">
                                                    <div className="n">9</div>
                                                    <div className="l">On Track</div>
                                                </div>
                                                <div className="tv-stat grey">
                                                    <div className="n">0</div>
                                                    <div className="l">At Risk</div>
                                                </div>
                                            </div>
                                            <div className="tv-qtr-label">My Quarterly Progress</div>
                                            <div className="tv-qtr-row">
                                                {[78, 85, 82, 0].map((pct, i) => (
                                                    <div className="tv-qtr" key={i}>
                                                        <div className="top">
                                                            <span>Q{i + 1}</span>
                                                            <span>{pct}%</span>
                                                        </div>
                                                        <div className="bar">
                                                            <i style={{ width: `${pct}%` }} />
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="relative">
                                    <span className="tv-tag">customizable fill / border / text</span>
                                    <div className="tv-bar-row" style={{ background: mainTheme.card, borderLeftColor: mainTheme.border }}>
                                        <span style={{ color: mainTheme.text }}>Company Overview — department ranking, quarterly trends</span>
                                        <span className="hint">Show ›</span>
                                    </div>
                                </div>

                                <div className="relative">
                                    <span className="tv-tag">border + accent header customizable</span>
                                    <div className="tv-linkages" style={{ borderLeftColor: mainTheme.border }}>
                                        <div className="relative">
                                            <div className="head" style={{ background: `linear-gradient(90deg, ${mainTheme.accent}, color-mix(in srgb, ${mainTheme.accent} 35%, black))` }}>
                                                <span style={{ color: mainTheme.text }}>KPI Target Linkages</span>
                                                <div className="s" style={{ color: `color-mix(in srgb, ${mainTheme.text} 65%, transparent)` }}>
                                                    Cascading targets · FY2026
                                                </div>
                                            </div>
                                        </div>
                                        <div className="empty" style={{ background: mainTheme.card }}>
                                            No linkage targets yet. Use "+ Assign Target" to assign a cascading target to your team.
                                        </div>
                                    </div>
                                </div>

                                <p className="tv-doc-label">Document pages — Job Description, Performance Reports</p>
                                <div className="relative">
                                    <span className="tv-tag">title bar + shadow customizable</span>
                                    <div className="tv-doc" style={{ boxShadow: `0 10px 24px color-mix(in srgb, ${mainTheme.accent} 22%, transparent)` }}>
                                        <div
                                            className="tv-doc-bar"
                                            style={{ background: `linear-gradient(135deg, ${mainTheme.accent}, color-mix(in srgb, ${mainTheme.accent} 35%, black))`, color: mainTheme.text }}
                                        >
                                            Job Description
                                        </div>
                                        <div className="tv-doc-body" style={{ background: mainTheme.card }}>
                                            Position · Department · Reporting To …
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-2 mt-4">
                    <button
                        type="button"
                        onClick={saveTheme}
                        disabled={themeSaving}
                        className="text-[11px] font-black px-4 py-2.5 rounded-xl bg-[#1a3d34] text-white hover:bg-[#2d5548] transition disabled:opacity-60"
                    >
                        Save Theme
                    </button>
                    <button type="button" onClick={resetTheme} className="text-[11px] font-black px-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                        Reset to Default
                    </button>
                    {themeMsg && <span className={`text-[11px] font-semibold ml-1 ${themeMsg.ok ? 'text-emerald-600' : 'text-red-600'}`}>{themeMsg.text}</span>}
                </div>
            </div>

            {/* FONT */}
            <div className="mt-5 pt-5 border-t border-slate-100">
                <div className="flex items-center justify-between gap-2 flex-wrap mb-3">
                    <div>
                        <h2 className="text-[13px] font-black text-slate-900">Font — applies everywhere, not just here</h2>
                        <p className="text-[11px] text-slate-500 mt-0.5 max-w-lg">
                            Typeface changes every page's text. Size scales every page's layout up or down (buttons, cards, spacing included) since most
                            text here is set in fixed pixels, not just the words.
                        </p>
                    </div>
                    {hasCustomFont ? (
                        <span className="text-[9px] font-black uppercase tracking-wide px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 shrink-0">
                            Custom font active
                        </span>
                    ) : (
                        <span className="text-[9px] font-black uppercase tracking-wide px-2 py-1 rounded-full bg-slate-100 text-slate-500 shrink-0">Default font</span>
                    )}
                </div>

                <div className="border border-slate-200 rounded-xl p-4">
                    <p className="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-2">Typeface</p>
                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-4">
                        {FONT_FAMILIES.map((f) => (
                            <button
                                key={f.key}
                                type="button"
                                onClick={() => setFontTheme({ ...fontTheme, family: f.key })}
                                className={`font-chip text-left px-3 py-2.5 rounded-xl border-2 border-slate-200 hover:border-[#6B9080] transition ${
                                    fontTheme.family === f.key ? 'active-font-chip' : ''
                                }`}
                            >
                                <span className="block text-[15px] leading-tight text-slate-800" style={{ fontFamily: `'${f.key}', ${f.fallback}` }}>
                                    Aa — {f.label}
                                </span>
                                <span className="block text-[9px] text-slate-400 mt-0.5" style={{ fontFamily: `'${f.key}', ${f.fallback}` }}>
                                    The quick brown fox jumps
                                </span>
                            </button>
                        ))}
                    </div>

                    <p className="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-2">Size</p>
                    <div className="inline-flex items-center gap-1 bg-slate-100 rounded-xl p-1 mb-2">
                        {FONT_SIZES.map((s) => (
                            <button
                                key={s.key}
                                type="button"
                                onClick={() => setFontTheme({ ...fontTheme, size: s.key })}
                                className={`font-size-chip ${fontTheme.size === s.key ? 'active-font-size-chip' : ''}`}
                            >
                                {s.label}
                            </button>
                        ))}
                    </div>
                    <p className="text-[10px] text-slate-400 mb-3">{activeFontSize?.hint ?? ''}</p>

                    <div className="rounded-xl border border-slate-200 p-4 bg-slate-50">
                        <p className="text-[9px] uppercase tracking-widest font-black text-slate-400 mb-2">Preview</p>
                        <div style={{ fontFamily: `'${fontTheme.family}', sans-serif`, zoom: activeFontSize?.zoom ?? 1 }}>
                            <h3 className="text-lg font-black text-slate-900">KPI Score — 82.4%</h3>
                            <p className="text-xs text-slate-500 mt-1">This is how body text and numbers will look across every page.</p>
                            <button type="button" className="mt-2 text-xs font-bold px-3 py-1.5 rounded-lg bg-[#1a3d34] text-white">
                                + Create KPI
                            </button>
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-2 mt-4">
                    <button
                        type="button"
                        onClick={saveFont}
                        disabled={fontSaving}
                        className="text-[11px] font-black px-4 py-2.5 rounded-xl bg-[#1a3d34] text-white hover:bg-[#2d5548] transition disabled:opacity-60"
                    >
                        Save Font
                    </button>
                    {fontMsg && <span className={`text-[11px] font-semibold ml-1 ${fontMsg.ok ? 'text-emerald-600' : 'text-red-600'}`}>{fontMsg.text}</span>}
                </div>
            </div>

            {/* LIVE PAGE PREVIEW */}
            <div className="mt-5 pt-5 border-t border-slate-100">
                <h2 className="text-[13px] font-black text-slate-900 mb-0.5">Preview any page live</h2>
                <p className="text-[11px] text-slate-500 mb-3 max-w-lg">
                    Opens the real page in a new tab, already wearing your saved theme — not a mockup. Save your changes above first.
                </p>
                <div className="flex flex-wrap gap-1.5">
                    {PREVIEW_LINKS.map((pv) => (
                        <a
                            key={pv.label}
                            href={pv.href}
                            target="_blank"
                            rel="noopener"
                            className="text-[11px] font-bold px-3 py-2 rounded-xl border border-slate-200 text-slate-600 hover:border-[#6B9080] hover:text-[#1a3d34] transition"
                        >
                            {pv.label} ↗
                        </a>
                    ))}
                </div>
            </div>
        </>
    );
}

function ColorSwatch({ label, hint, value, onChange }: { label: string; hint?: string; value: string; onChange: (v: string) => void }) {
    return (
        <label className="flex items-center gap-2.5 cursor-pointer" title={hint}>
            <span
                className="relative w-12 h-12 rounded-full border-[3px] border-slate-200 overflow-hidden shrink-0"
                style={{
                    background: swatchGradient(value),
                    boxShadow: 'inset 0 2px 4px rgba(255,255,255,.5), inset 0 -3px 6px rgba(0,0,0,.25), 0 3px 8px rgba(0,0,0,.15)',
                }}
            >
                <input type="color" value={value} onChange={(e) => onChange(e.target.value)} className="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
            </span>
            <span className="leading-tight">
                <span className="block text-[12px] font-black text-slate-700">{label}</span>
                <span className="block text-[10px] font-mono text-slate-400 uppercase">{value}</span>
            </span>
        </label>
    );
}

function CompanyLogoUploader({ companyLogoUrl }: { companyLogoUrl?: string | null }) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(companyLogoUrl ?? null);
    const [uploading, setUploading] = useState(false);
    const [msg, setMsg] = useState<{ text: string; ok: boolean } | null>(null);

    function pickFile() {
        fileInputRef.current?.click();
    }

    async function onFileChosen(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;

        const localPreview = URL.createObjectURL(file);
        setPreview(localPreview);
        setUploading(true);
        setMsg(null);

        try {
            const formData = new FormData();
            formData.append('logo', file);

            const res = await fetch('/settings/company-logo', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken() },
                body: formData,
            });
            const data = await res.json();

            if (data.success) {
                setPreview(data.logo_url);
                setMsg({ text: 'Logo updated ✓ Applying everywhere…', ok: true });
                setTimeout(() => window.location.reload(), 900);
            } else {
                setMsg({ text: data.message || 'Could not upload the logo.', ok: false });
            }
        } catch {
            setMsg({ text: 'Network error.', ok: false });
        } finally {
            setUploading(false);
            e.target.value = '';
        }
    }

    return (
        <div>
            <p className="text-[9px] font-black uppercase tracking-widest text-slate-400">Company logo</p>
            <p className="text-[10px] text-slate-500 mb-3 max-w-md">
                Shown in the sidebar brand tile for everyone in the company, on every page. Changing it here replaces it everywhere the logo appears — no
                developer needed.
            </p>
            <div className="flex items-center gap-4 flex-wrap">
                <div className="w-16 h-16 rounded-xl bg-[#111111] border-2 border-[#D4AF37] flex items-center justify-center shrink-0 overflow-hidden p-1.5">
                    {preview ? (
                        <img src={preview} alt="Company logo" className="w-full h-full object-contain" />
                    ) : (
                        <span className="text-white text-[10px] font-bold text-center leading-tight">No logo</span>
                    )}
                </div>
                <div>
                    <button
                        type="button"
                        onClick={pickFile}
                        disabled={uploading}
                        className="text-[11px] font-black px-3 py-2 rounded-xl bg-slate-900 text-white hover:bg-slate-700 transition disabled:opacity-50"
                    >
                        {uploading ? 'Uploading…' : 'Upload new logo'}
                    </button>
                    <p className="text-[9px] text-slate-400 mt-1.5">PNG, JPG, WEBP or SVG — up to 2MB.</p>
                    {msg && <p className={`text-[10.5px] font-bold mt-1.5 ${msg.ok ? 'text-emerald-600' : 'text-red-600'}`}>{msg.text}</p>}
                </div>
                <input ref={fileInputRef} type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" onChange={onFileChosen} className="hidden" />
            </div>
        </div>
    );
}
