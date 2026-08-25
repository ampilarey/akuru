import AppShell from '../../../Layouts/AppShell';

export default function Schedule({ sessions = [] }) {
    return (
        <AppShell title="Teacher schedule">
            {sessions.length === 0 && <p className="text-sm text-gray-600">No sessions assigned to you yet.</p>}
            <ul className="space-y-3">
                {sessions.map((row) => (
                    <li key={row.id} className="rounded-lg border bg-white p-4">
                        <h2 className="font-medium">{row.title}</h2>
                        <p className="text-sm text-gray-600">
                            {row.course_title} · {row.starts_at}
                            {row.location_name ? ` · ${row.location_name}` : ''}
                        </p>
                        <a className="text-sm text-[#7C2D37] hover:underline" href={`/catalog/offerings/${row.course_offering_id}/sessions/${row.id}/attendance`}>
                            Attendance
                        </a>
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}
