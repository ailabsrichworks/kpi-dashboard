import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Card, EmptyState, PrimaryButton } from '@/Components/Platform/ui';
import { BellIcon } from '@/Components/Platform/Icons';
import { CATEGORY_META, DEFAULT_TYPE_META, NotificationCategory, TYPE_META, typeMetaFor } from '@/config/notificationMeta';

interface Notification {
    id: string;
    company_id: string;
    title: string;
    message: string;
    is_read: boolean;
    created_at: string;
}

interface NotificationsPageProps {
    notifications: Notification[];
    [key: string]: unknown;
}

function timeAgo(iso: string): string {
    const seconds = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
    const units: [string, number][] = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    for (const [label, secondsPerUnit] of units) {
        const value = Math.floor(seconds / secondsPerUnit);
        if (value >= 1) return `${value} ${label}${value > 1 ? 's' : ''} ago`;
    }
    return 'just now';
}

function isToday(iso: string): boolean {
    const d = new Date(iso);
    const now = new Date();
    return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate();
}

/**
 * `notifications` still has no `type`/`link` column (see the legacy,
 * pre-Platform `notifications` table this mirrors — `resources/js/Pages/
 * Notifications.tsx` + `resources/js/config/notificationMeta.ts`, which
 * *does* have real `type`/`link`/`quarter`/`financial_year` columns behind
 * it) — a migration adding the same 4 columns here is prepared but not yet
 * applied (schema changes to the shared production database need sign-off
 * this session couldn't self-grant; see the accompanying message). Until
 * then, every real Platform notification title is inferred back to one of
 * `notificationMeta.ts`'s own real `type` keys — the same shared config
 * legacy's page imports, not a second, drifting copy of it — so the
 * category/color/icon/label language is identical, and an unrecognized
 * title honestly falls to the same DEFAULT_TYPE_META ("Update", 🔔) legacy
 * itself uses for an unrecognized type, rather than inventing a new bucket.
 */
function inferType(title: string): string {
    if (title.startsWith('Quarter sign-off')) return 'kpi_completion_approval';
    if (title.startsWith('Quarter change')) return 'kpi_actual_approval';
    if (title.startsWith('Weight change')) return 'kpi_weightage_approval';
    return '__unknown__';
}

/** Company-scoped destinations for the 3 real Platform approval types — the closest thing to legacy's stored `link` until that column exists here too. */
function linkFor(type: string, companyId: string): string | null {
    if (type === 'kpi_completion_approval' || type === 'kpi_actual_approval') return `/platform/companies/${companyId}/quarterly`;
    if (type === 'kpi_weightage_approval') return `/platform/companies/${companyId}/weightage`;
    return null;
}

type FilterKey = 'all' | NotificationCategory | 'appraisal_needed' | 'appraisal_ready' | 'appraisal_completed';

const APPRAISAL_FILTER_TYPES: Partial<Record<FilterKey, string>> = {
    appraisal_needed: 'appraisal_submitted',
    appraisal_ready: 'appraisal_appraised',
    appraisal_completed: 'appraisal_completed',
};

function NotificationRow({ notification }: { notification: Notification }) {
    const unread = !notification.is_read;
    const type = inferType(notification.title);
    const meta = type === '__unknown__' ? DEFAULT_TYPE_META : typeMetaFor(type);
    const cat = CATEGORY_META[meta.category];
    const catColor = cat.bg === '#D4AF37' ? '#8a6d00' : cat.bg;
    const href = linkFor(type, notification.company_id);

    const markRead = () => {
        if (unread) router.post(`/platform/notifications/${notification.id}/read`, {}, { preserveScroll: true });
    };

    return (
        <div
            onClick={markRead}
            className={`bg-white rounded-2xl border border-[#E5E7EB] shadow-sm hover:shadow-md hover:-translate-y-px transition p-4 flex items-start gap-3 cursor-pointer ${unread ? 'border-l-4' : ''}`}
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
                    {unread && <span className="text-[9px] font-black uppercase tracking-wide px-2 py-0.5 rounded-full bg-red-50 text-red-600">New</span>}
                </div>
            </div>
            {href && (
                <Link
                    href={href}
                    onClick={(e) => e.stopPropagation()}
                    className="shrink-0 self-center text-[10px] font-black px-2.5 py-1.5 rounded-lg"
                    style={{ background: `${cat.bg}18`, color: catColor }}
                >
                    Open →
                </Link>
            )}
        </div>
    );
}

export default function NotificationsIndex({ notifications }: NotificationsPageProps) {
    const [filter, setFilter] = useState<FilterKey>('all');

    const rows = notifications.map((n) => {
        const type = inferType(n.title);
        return { ...n, type, meta: type === '__unknown__' ? DEFAULT_TYPE_META : typeMetaFor(type) };
    });

    // Unread-only counts on every chip — matches legacy's own Notifications.tsx exactly (a badge counting yesterday's already-seen total no matter how many times "Mark all as read" is pressed looks broken, even when it's technically still correct).
    const unread = rows.filter((n) => !n.is_read);
    const unreadCount = unread.length;
    const approvalCount = unread.filter((n) => n.meta.category === 'approval').length;
    const appraisalNeededCount = unread.filter((n) => n.type === 'appraisal_submitted').length;
    const appraisalReadyCount = unread.filter((n) => n.type === 'appraisal_appraised').length;
    const appraisalCompletedCount = unread.filter((n) => n.type === 'appraisal_completed').length;
    const updateCount = unread.filter((n) => n.meta.category === 'update').length;

    const appraisalFilterType = APPRAISAL_FILTER_TYPES[filter];
    const visible =
        filter === 'all' ? rows : appraisalFilterType ? rows.filter((n) => n.type === appraisalFilterType) : rows.filter((n) => n.meta.category === filter);
    const today = visible.filter((n) => isToday(n.created_at));
    const earlier = visible.filter((n) => !isToday(n.created_at));

    return (
        <PlatformLayout title="Notifications">
            <div className="flex items-center justify-between gap-3 mb-4">
                <div className="flex items-center gap-2">
                    {unreadCount > 0 && <span className="text-[11px] font-black bg-[#D4AF37] text-[#1a1a1a] px-2 py-0.5 rounded-full">{unreadCount} new</span>}
                </div>
                {unreadCount > 0 && (
                    <PrimaryButton onClick={() => router.post('/platform/notifications/read-all')}>Mark all as read</PrimaryButton>
                )}
            </div>

            {notifications.length === 0 ? (
                <Card>
                    <EmptyState
                        icon={<BellIcon className="w-10 h-10" />}
                        title="No notifications yet"
                        description="You'll see updates here as things happen on your own KPIs, weight-change requests, and quarterly sign-offs."
                    />
                </Card>
            ) : (
                <div className="space-y-3">
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() => setFilter('all')}
                            className={`px-3 py-1.5 rounded-xl text-[11px] font-black bg-white border border-[#E5E7EB] text-slate-700 transition ${filter === 'all' ? 'outline outline-2 outline-offset-1 outline-slate-800' : ''}`}
                        >
                            All <span className="opacity-50">({unreadCount})</span>
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
                            onClick={() => setFilter('appraisal_needed')}
                            style={{ background: `${CATEGORY_META.appraisal.bg}18`, color: CATEGORY_META.appraisal.bg }}
                            className={`px-3 py-1.5 rounded-xl text-[11px] font-black transition ${filter === 'appraisal_needed' ? 'outline outline-2 outline-offset-1 outline-slate-800' : ''}`}
                        >
                            📝 Needs Appraisal <span className="opacity-60">({appraisalNeededCount})</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setFilter('appraisal_ready')}
                            style={{ background: `${CATEGORY_META.appraisal.bg}18`, color: CATEGORY_META.appraisal.bg }}
                            className={`px-3 py-1.5 rounded-xl text-[11px] font-black transition ${filter === 'appraisal_ready' ? 'outline outline-2 outline-offset-1 outline-slate-800' : ''}`}
                        >
                            ✅ Ready to Sign <span className="opacity-60">({appraisalReadyCount})</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setFilter('appraisal_completed')}
                            style={{ background: `${CATEGORY_META.appraisal.bg}18`, color: CATEGORY_META.appraisal.bg }}
                            className={`px-3 py-1.5 rounded-xl text-[11px] font-black transition ${filter === 'appraisal_completed' ? 'outline outline-2 outline-offset-1 outline-slate-800' : ''}`}
                        >
                            🎉 Completed <span className="opacity-60">({appraisalCompletedCount})</span>
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
