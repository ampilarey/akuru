import { usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Performance({ students = [] }) {
    const t = usePage().props.i18n?.learn || {};
    // The column names, also the captions a phone shows beside each value
    // (`data-label`), in the page's language (slice CT8).
    const col = {
        course: t.perf_col_course || 'Course',
        offering: t.perf_col_offering || 'Offering',
        progress: t.perf_col_progress || 'Progress',
        attendance: t.perf_col_attendance || 'Attendance',
        lessons: t.perf_col_lessons || 'Lessons',
        status: t.perf_col_status || 'Status',
    };

    return (
        <AppShell title={t.performance_title || 'Performance'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/portal/performance/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            {students.length === 0 && (
                <p className="text-sm text-gray-600">{t.performance_none || 'No student or linked children.'}</p>
            )}
            <div className="space-y-4">
                {students.map((student) => (
                    <section key={student.id} className="rounded-lg border bg-white p-4">
                        <h2 className="mb-1 font-medium">{student.name}</h2>
                        <p className="mb-3 text-xs uppercase text-gray-500">{t[`relationship_${student.relationship}`] || student.relationship}</p>
                        {student.rows.length === 0 && <p className="text-sm text-gray-500">{t.performance_no_enrollments || 'No enrollments.'}</p>}
                        {student.rows.length > 0 && (
                            <div className="overflow-x-auto">
                                <table className="table-stack min-w-full text-sm">
                                    <thead className="bg-[#F3EBE0] text-start">
                                        <tr>
                                            <th className="px-3 py-2">{col.course}</th>
                                            <th className="px-3 py-2">{col.offering}</th>
                                            <th className="px-3 py-2">{col.progress}</th>
                                            <th className="px-3 py-2">{col.attendance}</th>
                                            <th className="px-3 py-2">{col.lessons}</th>
                                            <th className="px-3 py-2">{col.status}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {student.rows.map((row) => (
                                            <tr key={row.enrollment_id} className="border-t">
                                                <td className="px-3 py-2" data-label={col.course}>{row.course_title}</td>
                                                <td className="px-3 py-2" data-label={col.offering}>{row.offering_title || '—'}</td>
                                                <td className="px-3 py-2" data-label={col.progress}>{row.progress_percentage}%</td>
                                                <td className="px-3 py-2" data-label={col.attendance}>{row.attendance_percent == null ? '—' : `${row.attendance_percent}%`}</td>
                                                <td className="px-3 py-2" data-label={col.lessons}>{row.lessons_completed}/{row.lessons_required}</td>
                                                <td className="px-3 py-2" data-label={col.status}>{t[`enrol_status_${row.status}`] || row.status}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                ))}
            </div>
        </AppShell>
    );
}
