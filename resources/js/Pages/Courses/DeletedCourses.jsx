import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

const HOLD_LABELS = {
    course_enrollments: 'enrolments',
    attendance_records: 'attendance records',
    activity_attempts: 'activity attempts',
    assessment_attempts: 'assessment attempts',
    student_lesson_progress: 'progress records',
    issued_certificates: 'issued certificates',
    payment_items: 'payment records',
};

export default function DeletedCourses({ courses = [] }) {
    const restore = (course) => {
        if (!window.confirm(`Restore "${course.title}"? It comes back as a draft, not on the public site.`)) {
            return;
        }
        router.post(`/admin/public-site/courses/${course.id}/restore`, {}, { preserveScroll: true });
    };

    return (
        <AppShell title="Deleted courses">
            <p className="mb-2 text-sm text-gray-600">
                Courses that were deleted but kept, because something of somebody&apos;s is attached to them.
                Nothing listed below was removed — the enrolments, attempts, progress and payment records are
                <strong> why</strong> each course survived deletion, and they are all still on the record.
            </p>
            <p className="mb-4 text-sm text-gray-500">
                This is not the catalogue&apos;s <em>Archive</em>, which is a status on an ordinary visible
                course and is changed from the Catalog screen. These courses are gone from every other list.
            </p>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Course</th>
                            <th className="px-3 py-2">Category</th>
                            <th className="px-3 py-2">What it holds</th>
                            <th className="px-3 py-2">Deleted</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {courses.length === 0 && (
                            <tr>
                                <td className="px-3 py-6 text-center text-gray-500" colSpan={5}>
                                    Nothing has been deleted.
                                </td>
                            </tr>
                        )}
                        {courses.map((course) => (
                            <tr key={course.id} className="border-t align-top">
                                <td className="px-3 py-2">
                                    {course.title}
                                    {/* Shown because a deleted course keeps its public address:
                                        creating a new course at the same one is refused, and the
                                        refusal points here. */}
                                    <p className="mt-1 font-mono text-xs text-gray-500">/{course.slug}</p>
                                </td>
                                <td className="px-3 py-2">{course.category ?? '—'}</td>
                                <td className="px-3 py-2">
                                    {Object.keys(course.holds).length === 0 ? (
                                        <span className="text-gray-400">nothing</span>
                                    ) : (
                                        Object.entries(course.holds).map(([table, count]) => (
                                            <span
                                                key={table}
                                                className="me-1 inline-block rounded bg-gray-100 px-2 py-0.5 text-xs"
                                            >
                                                {count} {HOLD_LABELS[table] ?? table}
                                            </span>
                                        ))
                                    )}
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap">{course.deleted_at}</td>
                                <td className="px-3 py-2 whitespace-nowrap">
                                    <button type="button" className="btn-secondary text-xs" onClick={() => restore(course)}>
                                        Restore
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <p className="mt-3 text-xs text-gray-500">
                Restoring brings a course back as a <strong>draft</strong>. Deleting is usually a withdrawal,
                and a restore that re-listed it publicly would put content back on the website as a side
                effect of clicking a button.
            </p>
        </AppShell>
    );
}
