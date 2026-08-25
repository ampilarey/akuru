import { useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Attendance({ session, roster, statuses, modes }) {
    const t = usePage().props.i18n?.learn || {};
    const form = useForm({
        enrollment_id: roster[0]?.enrollment_id || '',
        status: 'present',
        attendance_mode: 'physical',
        notes: '',
    });

    return (
        <AppShell title={`Attendance — ${session.title}`}>
            <p className="mb-4 text-sm text-gray-600">{session.offering_title} · {session.starts_at}</p>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/catalog/offerings/${session.course_offering_id}/sessions/${session.id}/attendance`, { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" value={form.data.enrollment_id} onChange={(e) => form.setData('enrollment_id', e.target.value)}>
                    {roster.map((row) => <option key={row.enrollment_id} value={row.enrollment_id}>Enrollment {row.enrollment_id}</option>)}
                </select>
                <select className="form-input" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                </select>
                <select className="form-input" value={form.data.attendance_mode} onChange={(e) => form.setData('attendance_mode', e.target.value)}>
                    {modes.map((mode) => <option key={mode} value={mode}>{mode}</option>)}
                </select>
                <button type="submit" className="btn-primary" disabled={form.processing || roster.length === 0}>Mark</button>
            </form>
            <ul className="space-y-2 rounded-lg border bg-white p-4 text-sm">
                {roster.length === 0 && <li className="text-gray-500">No enrollments on this offering.</li>}
                {roster.map((row) => (
                    <li key={row.enrollment_id} className="flex justify-between gap-3 border-t pt-2 first:border-t-0 first:pt-0">
                        <span>Student {row.student_id} · enrollment {row.enrollment_id}</span>
                        <span className="uppercase text-gray-500">{row.status}{row.attendance_mode ? ` · ${row.attendance_mode}` : ''}</span>
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}
