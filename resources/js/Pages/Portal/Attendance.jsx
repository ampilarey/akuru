import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

const fill = (phrase, values) => Object.entries(values).reduce((text, [name, value]) => text.replace(`:${name}`, value ?? ''), phrase);

export default function Attendance({ children, studentId, rows, summary, t = {} }) {
    // The column names, also the captions a phone shows beside each value
    // (`data-label`), in the page's language (BACKLOG C21, slice PT1a).
    const col = {
        date: t.col_date || 'Date',
        class: t.col_class || 'Class',
        status: t.col_status || 'Status',
        notified: t.col_parent_notified || 'Parent notified',
    };

    return (
        <AppShell title={t.attendance_title || 'Attendance'}>
            <div className="mb-4">
                <select
                    className="form-input"
                    aria-label={t.attendance_child || 'Child'}
                    value={studentId || ''}
                    onChange={(e) => router.get(`/portal/attendance?student_id=${e.target.value}`)}
                >
                    {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                </select>
            </div>
            {summary && (
                <p className="mb-4 text-sm text-gray-600">
                    {fill(t.attendance_summary || 'Present :present · Late :late · Absent :absent · Excused :excused · :percent%', {
                        present: summary.present,
                        late: summary.late,
                        absent: summary.absent,
                        excused: summary.excused,
                        percent: summary.percent,
                    })}
                </p>
            )}
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.date}</th>
                            <th className="px-3 py-2">{col.class}</th>
                            <th className="px-3 py-2">{col.status}</th>
                            <th className="px-3 py-2">{col.notified}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.attendance_none || 'No attendance recorded yet.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2" data-label={col.date}>{row.date}</td>
                                <td className="px-3 py-2" data-label={col.class}>{row.class_name}</td>
                                <td className="px-3 py-2 uppercase" data-label={col.status}>{t[`attendance_status_${row.status}`] || row.status}</td>
                                {/* KNOWN_ISSUES #17. This was `guardian_notified ? 'Yes' : '—'`,
                                    and the dash stood for four different facts — nothing is
                                    sent for this status, the guardian excused it themselves,
                                    a late message that was sent, and an absence message that
                                    should have gone and did not. Only the last is a problem,
                                    and it looked exactly like the other three. The state is
                                    named in the page's language; the server's English label
                                    is only the fallback. */}
                                <td className="px-3 py-2" data-label={col.notified}>
                                    {row.notification_state === 'not_sent' ? (
                                        <span className="rounded bg-amber-100 px-2 py-0.5 text-amber-900">
                                            {t[`notify_state_${row.notification_state}`] || row.notification_label}
                                        </span>
                                    ) : (
                                        <span className={row.notification_state === 'notified' ? '' : 'text-gray-500'}>
                                            {t[`notify_state_${row.notification_state}`] || row.notification_label}
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
