import { router } from '@inertiajs/react';
import { useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { CATEGORY_META, DEFAULT_TYPE_META, NotificationCategory, typeMetaFor } from '@/config/notificationMeta';

interface Notification {
    id: string;
    company_id: string;
    title: string;
    message: string;
    is_read: boolean;
    created_at: string;
    type: string | null;
    link: string | null;
    quarter: string | null;
    financial_year: string | null;
}

interface NotificationsPageProps {
    notifications: Notification[];
    [key: string]: unknown;
}

/**
 * Matches legacy's own `diffForHumans()` output closely enough for this
 * page's purposes: abbreviated units (`6d ago`, not `6 days ago`), no
 * week/month/year buckets.
 *
 * Filter/count logic below matches resources/views/notifications.blade.php's
 * own `$categoryMeta`/`$typeMeta`/count computation exactly, per that file
 * (confirmed byte-identical to what's checked into this repo, handed over
 * directly rather than inferred from a screenshot): 4 chips (All / Approvals
 * / Appraisals / Job Descriptions — appraisal_submitted/appraised/completed
 * all bucket into one "Appraisals" chip, not three), and every chip counts
 * ALL notifications in that category, not just unread ones — only the top
 * banner's "X new" pill and "Mark all as read" button are unread-scoped.
 *
 * The banner's flat, card-less look is real but comes from a different
 * source than this file's own inline classes: a global CSS override in
 * resources/views/partials/sidebar.blade.php
 * (`.theme-header-banner.theme-page-banner`) strips every full-width
 * page-top banner's background/border/shadow down to plain text on the page
 * background across the whole legacy app, confirmed directly against a live
 * screenshot. Platform doesn't load that stylesheet, so that end result is
 * baked in directly here instead of via a class name that would do nothing.
 */
function timeAgo(iso: string): string {
    const diffMs = Date.now() - new Date(iso).getTime();
    const minutes = Math.round(diffMs / 60000);
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.round(hours / 24);
    return `${days}d ago`;
}

function isToday(iso: string): boolean {
    const d = new Date(iso);
    const now = new Date();
    return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate();
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

type FilterKey = 'all' | NotificationCategory;

/**
 * Matches legacy's `handleRowClick()` exactly: the WHOLE row is the click
 * target, not just the "Open →" badge (that badge is an inert `<span>` in
 * legacy, not its own link) — clicking anywhere marks the notification read
 * first, then navigates only if a link exists. Marking read fires on every
 * click, read or unread already, same as legacy (idempotent on the backend).
 */
function handleRowClick(notification: Notification) {
    fetch(`/platform/notifications/${notification.id}/read`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
    }).finally(() => {
        if (notification.link) window.location.href = notification.link;
    });
}

function NotificationRow({ notification }: { notification: Notification }) {
    const unread = !notification.is_read;
    const meta = notification.type ? typeMetaFor(notification.type) : DEFAULT_TYPE_META;
    const cat = CATEGORY_META[meta.category];
    const catColor = cat.bg === '#D4AF37' ? '#8a6d00' : cat.bg;

    return (
        <div
            onClick={() => handleRowClick(notification)}
            className={`bg-white rounded-2xl border border-[#E5E7EB] shadow-[0_8px_30px_rgba(15,23,42,0.07)] hover:shadow-[0_10px_30px_rgba(15,23,42,0.10)] hover:-translate-y-px transition p-4 flex items-start gap-3 cursor-pointer ${unread ? 'border-l-4' : ''}`}
            style={unread ? { borderLeftColor: cat.bg } : undefined}
        >
            <div className="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0" style={{ background: unread ? `${cat.bg}18` : '#F8FAFC' }}>
                {meta.icon}
            </div>
            <div className="flex-1 min-w-0">
                <div className="flex items-start justify-between gap-2">
                    <p className={`text-[13px] leading-snug ${unread ? 'font-black text-slate-900' : 'font-bold text-slate-600'}`}>{notification.title}</p>
                    <span className="text-[10px] text-slate-400 shrink-0 whitespace-nowrap">{timeAgo(notification.created_at)}</span>
                </div>
                {notification.message && <p className="text-[11px] text-slate-500 mt-0.5">{notification.message}</p>}
                <div className="flex items-center gap-1.5 mt-2 flex-wrap">
                    <span className="text-[9px] font-black uppercase tracking-wide px-2 py-0.5 rounded-full" style={{ background: `${cat.bg}18`, color: catColor }}>
                        {meta.label}
                    </span>
                    {notification.quarter && (
                        <span className="text-[9px] font-black uppercase tracking-wide px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">
                            {notification.quarter} {notification.financial_year}
                        </span>
                    )}
                    {unread && <span className="text-[9px] font-black uppercase tracking-wide px-2 py-0.5 rounded-full bg-red-50 text-red-600">New</span>}
                </div>
            </div>
            {notification.link && (
                <span
                    className="shrink-0 self-center text-[10px] font-black px-2.5 py-1.5 rounded-lg"
                    style={{ background: `${cat.bg}18`, color: catColor }}
                >
                    Open →
                </span>
            )}
        </div>
    );
}

export default function NotificationsIndex({ notifications }: NotificationsPageProps) {
    const [filter, setFilter] = useState<FilterKey>('all');

    const rows = notifications.map((n) => ({ ...n, meta: n.type ? typeMetaFor(n.type) : DEFAULT_TYPE_META }));

    // Matches notifications.blade.php exactly: the top banner's "X new" pill
    // and "Mark all as read" button are unread-only, but every filter chip
    // counts ALL notifications in that category (read or not).
    const unreadCount = rows.filter((n) => !n.is_read).length;
    const approvalCount = rows.filter((n) => n.meta.category === 'approval').length;
    const appraisalCount = rows.filter((n) => n.meta.category === 'appraisal').length;
    const updateCount = rows.filter((n) => n.meta.category === 'update').length;

    const visible = filter === 'all' ? rows : rows.filter((n) => n.meta.category === filter);
    const today = visible.filter((n) => isToday(n.created_at));
    const earlier = visible.filter((n) => !isToday(n.created_at));

    return (
        <PlatformLayout title="Notifications">
            {/* Flat, card-less banner — legacy's global `.theme-header-banner.theme-page-banner` CSS override (partials/sidebar.blade.php) strips every full-width page-top banner's background/border/shadow entirely ("no card at all — plain text/icons directly on the page background, like a school-management-app top bar") and remaps white/translucent-white elements to dark-on-transparent. Platform doesn't load that stylesheet, so the same end result is baked in directly here instead of via a class name that would do nothing. */}
            <div className="sticky top-14 z-30 -mt-4 py-1 flex flex-col md:flex-row md:items-center justify-between gap-3 bg-[#F5F5F3]">
                <div>
                    {unreadCount > 0 && (
                        <span className="text-[11px] font-black bg-[#D4AF37] text-[#1a1a1a] px-2 py-0.5 rounded-full">{unreadCount} new</span>
                    )}
                </div>
                {unreadCount > 0 && (
                    <button
                        type="button"
                        onClick={() => router.post('/platform/notifications/read-all')}
                        className="text-xs font-black bg-[rgba(15,23,42,0.05)] hover:bg-[rgba(15,23,42,0.1)] text-[#1e293b] px-3.5 py-2 rounded-xl border border-[rgba(15,23,42,0.08)] transition"
                    >
                        Mark all as read
                    </button>
                )}
            </div>

            {notifications.length === 0 ? (
                <div className="bg-white rounded-2xl shadow-sm border border-[#E5E7EB] p-12 text-center">
                    <div className="text-4xl mb-3">🔔</div>
                    <p className="text-slate-500 font-bold text-sm">No notifications yet</p>
                    <p className="text-slate-400 text-xs mt-1 max-w-sm mx-auto">
                        You'll see something here as soon as someone who reports to you submits a Job Description, an appraisal, or requests your approval on a KPI.
                    </p>
                </div>
            ) : (
                <div className="space-y-3">
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() => setFilter('all')}
                            className={`px-3 py-1.5 rounded-xl text-[11px] font-black bg-white border border-[#E5E7EB] text-slate-700 transition ${filter === 'all' ? 'outline outline-2 outline-offset-1 outline-slate-800' : ''}`}
                        >
                            All <span className="opacity-50">({rows.length})</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setFilter('approval')}
                            style={{ background: `${CATEGORY_META.approval.bg}22`, color: '#8a6d00' }}
                            className={`px-3 py-1.5 rounded-xl text-[11px] font-black transition ${filter === 'approval' ? 'outline outline-2 outline-offset-1 outline-slate-800' : ''}`}
                        >
                            ⚖️ Approvals <span className="opacity-60">({approvalCount})</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setFilter('appraisal')}
                            style={{ background: `${CATEGORY_META.appraisal.bg}18`, color: CATEGORY_META.appraisal.bg }}
                            className={`px-3 py-1.5 rounded-xl text-[11px] font-black transition ${filter === 'appraisal' ? 'outline outline-2 outline-offset-1 outline-slate-800' : ''}`}
                        >
                            📝 Appraisals <span className="opacity-60">({appraisalCount})</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setFilter('update')}
                            className={`px-3 py-1.5 rounded-xl text-[11px] font-black bg-slate-100 text-slate-600 transition ${filter === 'update' ? 'outline outline-2 outline-offset-1 outline-slate-800' : ''}`}
                        >
                            📋 Job Descriptions <span className="opacity-60">({updateCount})</span>
                        </button>
                    </div>

                    {([
                        ['Today', today],
                        ['Earlier', earlier],
                    ] as const).map(([label, items]) => {
                        if (items.length === 0) return null;
                        return (
                            <div key={label}>
                                <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">{label}</p>
                                <div className="space-y-2">
                                    {items.map((n) => (
                                        <NotificationRow key={n.id} notification={n} />
                                    ))}
                                </div>
                            </div>
                        );
                    })}

                    {visible.length === 0 && <p className="text-center text-[11px] text-slate-400 py-8">Nothing in this category yet.</p>}
                </div>
            )}
        </PlatformLayout>
    );
}
