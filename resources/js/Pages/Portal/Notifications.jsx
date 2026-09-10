import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

const CATEGORY_LABELS = {
    message: 'Message',
    registers: 'Registers',
    academics: 'Academics',
    hr: 'Staff',
    finance: 'Finance',
    system: 'System',
};

function when(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleString();
}

export default function Notifications({ notifications = [] }) {
    const unread = notifications.filter((n) => !n.is_read).length;

    const markRead = (id) => router.post('/portal/notifications/read', id ? { id } : {}, {
        preserveScroll: true,
    });

    return (
        <AppShell title="Notifications">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">
                    {unread === 0 ? 'Nothing unread.' : `${unread} unread.`}
                </p>
                {unread > 0 && (
                    <button type="button" className="btn-secondary" onClick={() => markRead(null)}>
                        Mark all read
                    </button>
                )}
            </div>

            {notifications.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    No notifications yet.
                </p>
            )}

            <ul className="grid gap-2">
                {notifications.map((item) => (
                    <li
                        key={item.id}
                        className={`rounded-lg border p-3 ${item.is_read ? 'bg-white' : 'border-[#7C2D37] bg-[#FDFBF8]'}`}
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <p className={`text-sm ${item.is_read ? 'font-medium' : 'font-bold'}`}>
                                {/* A notification that points somewhere is worth
                                    far more than one that only announces. */}
                                {item.href
                                    ? <a className="text-[#7C2D37] hover:underline" href={item.href}>{item.title}</a>
                                    : item.title}
                            </p>
                            <span className="text-xs text-gray-500">{when(item.created_at)}</span>
                        </div>
                        <p className="mt-0.5 text-xs uppercase tracking-wide text-gray-500">
                            {CATEGORY_LABELS[item.category] || item.category}
                        </p>
                        <p className="mt-1 text-sm text-gray-800">{item.message}</p>
                        {!item.is_read && (
                            <button
                                type="button"
                                className="mt-2 text-xs text-[#7C2D37] hover:underline"
                                onClick={() => markRead(item.id)}
                            >
                                Mark read
                            </button>
                        )}
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}
