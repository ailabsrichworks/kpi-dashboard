import { router } from '@inertiajs/react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Card, EmptyState, SecondaryButton } from '@/Components/Platform/ui';
import { BellIcon } from '@/Components/Platform/Icons';

interface Notification {
    id: string;
    title: string;
    message: string;
    is_read: boolean;
    created_at: string;
}

interface NotificationsPageProps {
    notifications: Notification[];
    [key: string]: unknown;
}

export default function NotificationsIndex({ notifications }: NotificationsPageProps) {
    const unreadCount = notifications.filter((n) => !n.is_read).length;

    return (
        <PlatformLayout
            title="Notifications"
            description="Updates about your own KPIs, weight-change requests, and account activity."
            maxWidth="max-w-2xl"
            actions={
                unreadCount > 0 && (
                    <SecondaryButton onClick={() => router.post('/platform/notifications/read-all')}>
                        Mark all as read
                    </SecondaryButton>
                )
            }
        >
            <Card>
                {notifications.length === 0 ? (
                    <EmptyState
                        icon={<BellIcon className="w-10 h-10" />}
                        title="No notifications yet"
                        description="You'll see updates here as things happen on your own KPIs and requests."
                    />
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {notifications.map((n) => (
                            <li key={n.id} className={`py-4 flex items-start justify-between gap-4 ${n.is_read ? '' : 'bg-brand-50/40 -mx-6 px-6'}`}>
                                <div className="min-w-0">
                                    <p className="text-sm font-bold text-slate-800">{n.title}</p>
                                    <p className="text-xs text-slate-500 mt-0.5">{n.message}</p>
                                    <p className="text-[11px] text-slate-400 mt-1">{new Date(n.created_at).toLocaleString()}</p>
                                </div>
                                {!n.is_read && (
                                    <button
                                        onClick={() => router.post(`/platform/notifications/${n.id}/read`, {}, { preserveScroll: true })}
                                        className="flex-none text-xs font-semibold text-brand-800 hover:underline"
                                    >
                                        Mark read
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PlatformLayout>
    );
}
