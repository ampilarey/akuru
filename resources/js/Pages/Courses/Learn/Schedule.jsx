import { usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Schedule({ student, sessions = [] }) {
    const t = usePage().props.i18n?.learn || {};

    return (
        <AppShell title={t.schedule || 'My schedule'}>
            {!student && <p className="text-sm text-gray-600">{t.no_profile || 'No student profile is linked to this account.'}</p>}
            {student && sessions.length === 0 && <p className="text-sm text-gray-600">{t.no_sessions || 'No scheduled sessions yet.'}</p>}
            <ul className="space-y-3">
                {sessions.map((row) => (
                    <li key={row.id} className="rounded-lg border bg-white p-4">
                        <h2 className="font-medium">{row.title}</h2>
                        <p className="text-sm text-gray-600">
                            {row.course_title} · {row.starts_at}
                            {row.location_name ? ` · ${row.location_name}` : ''}
                            {row.online_meeting_url ? ` · ${row.online_meeting_url}` : ''}
                        </p>
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}
