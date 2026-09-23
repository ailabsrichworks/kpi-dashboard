import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../Layouts/AppLayout';
import { isToday } from '../lib/dates';
import { CATEGORY_META, NotificationCategory, typeMetaFor } from '../config/notificationMeta';

interface NotificationRow {
    id: string;
    type: string;
    title: string;
    message?: string | null;
    link?: string | null;
    quarter?: string | null;
    financial_year?: string | null;
    is_read: boolean;
    created_at: string;
}

interface NotificationsPageProps {
    user: { short_name?: string; full_name?: string };
    notifications: NotificationRow[];
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

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

// The three notification types making up the 'appraisal' category are three
// different points in the same handoff — 'appraisal_submitted' means someone
// is waiting on YOU to score/appraise them, 'appraisal_appraised' means it's
// scored and waiting on YOUR signature, 'appraisal_completed' means it's
// fully settled (nothing ticked, nothing needed from VP/SLT). Lumping them
// into one "Appraisals" bucket made that distinction invisible; split into
// their own filters so it's obvious at a glance which of your people need
// which action, and which need none at all.
type FilterKey = 'all' | NotificationCategory | 'appraisal_needed' | 'appraisal_ready' | 'appraisal_completed';

const APPRAISAL_FILTER_TYPES: Partial<Record<FilterKey, string>> = {
    appraisal_needed: 'appraisal_submitted',
    appraisal_ready: 'appraisal_appraised',
    appraisal_completed: 'appraisal_completed',
};

export default function Notifications({ notifications }: NotificationsPageProps) {
    const [filter, setFilter] = useState<FilterKey>('all');

    const rows = notifications.map((n) => ({ ...n, meta: typeMetaFor(n.type) }));

    // Every tab badge counts UNREAD items only, same as the top "X new" pill
    // — a badge that kept showing yesterday's already-seen total no matter
    // how many times "Mark all as read" was pressed looked broken, even
    // though it was technically counting correctly (just the wrong thing).
    const unread = rows.filter((n) => !n.is_read);
    const unreadCount = unread.length;
    const approvalCount = unread.filter((n) => n.meta.category === 'approval').length;
    const appraisalNeededCount = unread.filter((n) => n.type === 'appraisal_submitted').length;
    const appraisalReadyCount = unread.filter((n) => n.type === 'appraisal_appraised').length;
    const appraisalCompletedCount = unread.filter((n) => n.type === 'appraisal_completed').length;
    const updateCount = unread.filter((n) => n.meta.category === 'update').length;

    const appraisalFilterType = APPRAISAL_FILTER_TYPES[filter];
    const visible =
        filter === 'all'
            ? rows
            : appraisalFilterType
                ? rows.filter((n) => n.type === appraisalFilterType)
                : rows.filter((n) => n.meta.category === filter);
    const today = visible.filter((n) => isToday(n.created_at));
    const earlier = visible.filter((n) => !isToday(n.created_at));

    function handleRowClick(n: NotificationRow) {
        fetch(`/notifications/${n.id}/read`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
        }).finally(() => {
            if (n.link) window.location.href = n.link;
        });
    }

    function markAllRead() {
        router.post('/notifications/read-all');
    }

    return (
        <AppLayout pageTitle="Notifications">
            <Head title="Notifications" />

            <main className="px-4 pb-4">
                <div className="sticky top-0 z-30 pt-4 pb-2 bg-[#F5F5F3] -mx-4 px-4">
                    <div className="relative overflow-hidden rounded-[18px] theme-header-banner theme-page-banner bg-gradient-to-r from-[#1A0A0A] to-[#7A0019] text-white px-6 py-5 shadow-[0_10px_35px_rgba(122,0,25,0.45)] flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div className="absolute top-0 left-0 right-0 h-[2px] theme-header-hairline bg-gradient-to-r from-[#D4AF37] via-[#D4AF37] to-[#D4AF37]/10" />
                        <div className="relative">
                            {unreadCount > 0 && (
                                <span className="text-[11px] font-black bg-[#D4AF37] text-[#1a1a1a] px-2 py-0.5 rounded-full">{unreadCount} new</span>
                            )}
                        </div>
                        {unreadCount > 0 && (
                            <button
                                type="button"
                                onClick={markAllRead}
                                className="relative text-xs font-black bg-white/10 hover:bg-white/20 text-white px-3.5 py-2 rounded-xl border border-white/20 transition"
                            >
                                Mark all as read
                            </button>
                        )}
                    </div>
                </div>

                <div className="space-y-3 mt-2">
                    {rows.length === 0 ? (
                        <div className="bg-white rounded-2xl shadow-sm border border-[#E5E7EB] p-12 text-center">
                            <div className="text-4xl mb-3">🔔</div>
                            <p className="text-slate-500 font-bold text-sm">No notifications yet</p>
                            <p className="text-slate-400 text-xs mt-1 max-w-sm mx-auto">
                                You'll see something here as soon as someone who reports to you submits a Job Description, an appraisal, or requests your approval on a KPI.
                            </p>
                        </div>
                    ) : (
                        <>
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

                            {(
                                [
                                    ['Today', today],
                                    ['Earlier', earlier],
                                ] as const
                            ).map(([label, items]) => {
                                if (items.length === 0) return null;
                                return (
                                    <div key={label}>
                                        <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">{label}</p>
                                        <div className="space-y-2">
                                            {items.map((n) => {
                                                const cat = CATEGORY_META[n.meta.category];
                                                const unread = !n.is_read;
                                                const catColor = cat.bg === '#D4AF37' ? '#8a6d00' : cat.bg;
                                                return (
                                                    <div
                                                        key={n.id}
                                                        onClick={() => handleRowClick(n)}
                                                        className={`bg-white rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-px transition border border-[#E5E7EB] ${unread ? 'border-l-4' : ''} p-4 flex items-start gap-3 cursor-pointer`}
                                                        style={unread ? { borderLeftColor: cat.bg } : undefined}
                                                    >
                                                        <div
                                                            className="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0"
                                                            style={{ background: unread ? `${cat.bg}18` : '#F8FAFC' }}
                                                        >
                                                            {n.meta.icon}
                                                        </div>
                                                        <div className="flex-1 min-w-0">
                                                            <div className="flex items-start justify-between gap-2">
                                                                <p className={`text-[13px] ${unread ? 'font-black text-slate-900' : 'font-bold text-slate-600'} leading-snug`}>
                                                                    {n.title}
                                                                </p>
                                                                <span className="text-[10px] text-slate-400 shrink-0 whitespace-nowrap">{timeAgo(n.created_at)}</span>
                                                            </div>
                                                            {n.message && <p className="text-[11px] text-slate-500 mt-0.5">{n.message}</p>}
                                                            <div className="flex items-center gap-1.5 mt-2 flex-wrap">
                                                                <span
                                                                    className="text-[9px] font-black uppercase tracking-wide px-2 py-0.5 rounded-full"
                                                                    style={{ background: `${cat.bg}18`, color: catColor }}
                                                                >
                                                                    {n.meta.label}
                                                                </span>
                                                                {n.quarter && (
                                                                    <span className="text-[9px] font-black uppercase tracking-wide px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">
                                                                        {n.quarter} {n.financial_year}
                                                                    </span>
                                                                )}
                                                                {unread && (
                                                                    <span className="text-[9px] font-black uppercase tracking-wide px-2 py-0.5 rounded-full bg-red-50 text-red-600">New</span>
                                                                )}
                                                            </div>
                                                        </div>
                                                        {n.link && (
                                                            <span
                                                                className="shrink-0 self-center text-[10px] font-black px-2.5 py-1.5 rounded-lg"
                                                                style={{ background: `${cat.bg}18`, color: catColor }}
                                                            >
                                                                Open →
                                                            </span>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                );
                            })}

                            {visible.length === 0 && (
                                <p className="text-center text-[11px] text-slate-400 py-8">Nothing in this category yet.</p>
                            )}
                        </>
                    )}
                </div>
            </main>
        </AppLayout>
    );
}
