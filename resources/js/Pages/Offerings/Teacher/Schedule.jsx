import AppShell from '../../../Layouts/AppShell';

/**
 * The sessions a teacher is to teach. Every word is the `teach` book's
 * (slice AC1, STATUS §5qs), and a session's time reads as its date and hour.
 */
export default function Schedule({ sessions = [], t = {} }) {
    return (
        <AppShell title={t.schedule_title || 'Teacher schedule'}>
            {sessions.length === 0 && <p className="text-sm text-gray-600">{t.schedule_none || 'No sessions assigned to you yet.'}</p>}
            <ul className="space-y-3">
                {sessions.map((row) => (
                    <li key={row.id} className="rounded-lg border bg-white p-4">
                        <h2 className="font-medium">{row.title}</h2>
                        <p className="text-sm text-gray-600">
                            {[row.course_title, when(row.starts_at), row.location_name].filter(Boolean).join(' · ')}
                        </p>
                        <a className="text-sm text-[#7C2D37] hover:underline" href={`/catalog/offerings/${row.course_offering_id}/sessions/${row.id}/attendance`}>
                            {t.sessions_attendance || 'Attendance'}
                        </a>
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}

// A session's time as a teacher reads it — its date and its hour — rather
// than the stamp the server sends.
function when(value) {
    return value ? String(value).replace('T', ' ').slice(0, 16) : '';
}
