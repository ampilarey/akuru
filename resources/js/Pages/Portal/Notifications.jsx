import { router, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import { dateStamp as when } from '../../Components/dateStamp';

export default function Notifications({ notifications = [], categories = {}, preferences = {}, devices = [], t = {} }) {
    const unread = notifications.filter((n) => !n.is_read).length;
    const prefs = useForm({ preferences });

    const markRead = (id) => router.post('/portal/notifications/read', id ? { id } : {}, {
        preserveScroll: true,
    });

    // A category is a code: the server sends its English label as the
    // fallback, and the page names it in its own language (BACKLOG C21, slice
    // PT1b). A notification's own title and text are written when it is sent,
    // in English (C21 notes why).
    return (
        <AppShell title={t.notifications_title || 'Notifications'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">
                    {unread === 0 ? (t.notifications_nothing_unread || 'Nothing unread.') : (t.notifications_unread || ':count unread.').replace(':count', unread)}
                </p>
                {unread > 0 && (
                    <button type="button" className="btn-secondary" onClick={() => markRead(null)}>
                        {t.notifications_mark_all || 'Mark all read'}
                    </button>
                )}
            </div>

            {Object.keys(categories).length > 0 && (
                <details className="mb-4 rounded-lg border bg-white p-4">
                    <summary className="cursor-pointer text-sm font-medium">{t.notifications_reach_title || 'What reaches me'}</summary>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            prefs.post('/portal/notifications/preferences', { preserveScroll: true });
                        }}
                        className="mt-3 space-y-2"
                    >
                        {Object.entries(categories).map(([key, label]) => (
                            <label key={key} className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={prefs.data.preferences[key] !== false}
                                    onChange={(e) => prefs.setData('preferences', {
                                        ...prefs.data.preferences,
                                        [key]: e.target.checked,
                                    })}
                                />
                                {t[`notify_pref_${key}`] || label}
                            </label>
                        ))}
                        <button type="submit" className="btn-secondary mt-2" disabled={prefs.processing}>
                            {t.notifications_save || 'Save'}
                        </button>
                    </form>
                </details>
            )}

            {/* SPEC §50 (STATUS §5jr): the phones the mobile app registered for push. */}
            <details className="mb-4 rounded-lg border bg-white p-4" data-testid="devices">
                <summary className="cursor-pointer text-sm font-medium">{t.devices_title || 'Your phones'}</summary>
                <p className="mt-2 text-xs text-gray-500">{t.devices_hint || 'The Akuru app registers each phone you sign in on, so notifications reach it. Remove a phone you no longer use.'}</p>
                {devices.length === 0 && <p className="mt-2 text-sm text-gray-600" data-testid="devices-none">{t.devices_none || 'No phone has registered yet. Sign in on the Akuru app and it will appear here.'}</p>}
                <ul className="mt-2 divide-y">
                    {devices.map((device) => (
                        <li key={device.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm" data-testid="device-row">
                            <span>
                                <span className="font-medium">{t[`devices_platform_${device.platform}`] || device.platform}</span>
                                {device.name && <span className="ms-2 text-xs text-gray-500">{device.name.slice(0, 40)}</span>}
                                <span className="block text-xs text-gray-500">
                                    {device.active ? (t.devices_active || 'Receiving') : (t.devices_inactive || 'Signed out')}
                                    {' · '}{device.last_seen_at ? (t.devices_last_seen || 'Last seen :when').replace(':when', when(device.last_seen_at)) : (t.devices_never_seen || 'Never seen')}
                                </span>
                            </span>
                            <button type="button" className="text-xs text-red-700 hover:underline" onClick={() => router.delete(`/account/devices/${device.id}`, { preserveScroll: true })} data-testid="device-remove">{t.devices_remove || 'Remove'}</button>
                        </li>
                    ))}
                </ul>
            </details>

            {notifications.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    {t.notifications_none || 'No notifications yet.'}
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
                            {t[`notify_category_${item.category}`] || item.category}
                        </p>
                        <p className="mt-1 text-sm text-gray-800">{item.message}</p>
                        {!item.is_read && (
                            <button
                                type="button"
                                className="mt-2 text-xs text-[#7C2D37] hover:underline"
                                onClick={() => markRead(item.id)}
                            >
                                {t.notifications_mark_read || 'Mark read'}
                            </button>
                        )}
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}
