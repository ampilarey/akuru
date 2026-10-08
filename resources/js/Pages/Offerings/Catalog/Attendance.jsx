import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

export default function Attendance({ session, roster, statuses, modes, t = {} }) {
    // Codes the server sends, named in the page's language (slice CT8).
    const statusName = (status) => t[`attendance_status_${status}`] || status;
    const modeName = (mode) => t[`attendance_mode_${mode}`] || mode;
    const form = useForm({
        enrollment_id: roster[0]?.enrollment_id || '',
        status: 'present',
        attendance_mode: 'physical',
        notes: '',
    });

    return (
        <AppShell title={(t.attendance_title || 'Attendance — :session').replace(':session', () => session.title)}>
            <p className="mb-4 text-sm text-gray-600">{session.offering_title} · {session.starts_at}</p>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/catalog/offerings/${session.course_offering_id}/sessions/${session.id}/attendance`, { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" aria-label={t.attendance_learner || 'Learner'} value={form.data.enrollment_id} onChange={(e) => form.setData('enrollment_id', e.target.value)}>
                    {roster.map((row) => <option key={row.enrollment_id} value={row.enrollment_id}>{row.student_name || (t.attendance_enrollment || 'Enrollment :id').replace(':id', row.enrollment_id)}</option>)}
                </select>
                <select className="form-input" aria-label={t.attendance_status || 'Attendance status'} value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    {statuses.map((status) => <option key={status} value={status}>{statusName(status)}</option>)}
                </select>
                <select className="form-input" aria-label={t.attendance_mode || 'How they attended'} value={form.data.attendance_mode} onChange={(e) => form.setData('attendance_mode', e.target.value)}>
                    {modes.map((mode) => <option key={mode} value={mode}>{modeName(mode)}</option>)}
                </select>
                <button type="submit" className="btn-primary" disabled={form.processing || roster.length === 0}>{t.attendance_mark || 'Mark'}</button>
                <button
                    type="button"
                    className="btn-secondary"
                    disabled={roster.length === 0}
                    onClick={() => form.post(`/catalog/offerings/${session.course_offering_id}/sessions/${session.id}/attendance/bulk`, { preserveScroll: true })}
                >
                    {(t.attendance_mark_all || 'Mark all :status').replace(':status', statusName(form.data.status))}
                </button>
                <FormErrors errors={form.errors} />
            </form>
            <ul className="space-y-2 rounded-lg border bg-white p-4 text-sm">
                {roster.length === 0 && <li className="text-gray-500">{t.attendance_none || 'No enrollments on this offering.'}</li>}
                {roster.map((row) => (
                    <li key={row.enrollment_id} className="flex justify-between gap-3 border-t pt-2 first:border-t-0 first:pt-0">
                        <span>
                            {row.student_name || (t.attendance_student || 'Student :id').replace(':id', row.student_id)}
                            {` · ${(t.attendance_enrollment_line || 'enrollment :id').replace(':id', row.enrollment_id)}`}
                        </span>
                        <span className="uppercase text-gray-500">{statusName(row.status)}{row.attendance_mode ? ` · ${modeName(row.attendance_mode)}` : ''}</span>
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}
