import { usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Performance({ students = [] }) {
    const t = usePage().props.i18n?.learn || {};

    return (
        <AppShell title="Performance">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/portal/performance/export">Export CSV</a>
            </div>
            {students.length === 0 && (
                <p className="text-sm text-gray-600">{t.no_children || 'No student or linked children.'}</p>
            )}
            <div className="space-y-4">
                {students.map((student) => (
                    <section key={student.id} className="rounded-lg border bg-white p-4">
                        <h2 className="mb-1 font-medium">{student.name}</h2>
                        <p className="mb-3 text-xs uppercase text-gray-500">{student.relationship}</p>
                        {student.rows.length === 0 && <p className="text-sm text-gray-500">No enrollments.</p>}
                        {student.rows.length > 0 && (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-[#F3EBE0] text-start">
                                        <tr>
                                            <th className="px-3 py-2">Course</th>
                                            <th className="px-3 py-2">Offering</th>
                                            <th className="px-3 py-2">Progress</th>
                                            <th className="px-3 py-2">Attendance</th>
                                            <th className="px-3 py-2">Lessons</th>
                                            <th className="px-3 py-2">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {student.rows.map((row) => (
                                            <tr key={row.enrollment_id} className="border-t">
                                                <td className="px-3 py-2">{row.course_title}</td>
                                                <td className="px-3 py-2">{row.offering_title || '—'}</td>
                                                <td className="px-3 py-2">{row.progress_percentage}%</td>
                                                <td className="px-3 py-2">{row.attendance_percent == null ? '—' : `${row.attendance_percent}%`}</td>
                                                <td className="px-3 py-2">{row.lessons_completed}/{row.lessons_required}</td>
                                                <td className="px-3 py-2">{row.status}</td>
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
