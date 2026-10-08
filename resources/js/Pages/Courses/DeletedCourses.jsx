import { Link, router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

// What keeps a deleted course: the table each count comes from, named in the
// page's language (slice CT4). An unknown table is printed as it is.
const holdName = (t, table) => t[`courses_deleted_hold_${table}`] || table;

export default function DeletedCourses({ courses = [], t = {} }) {
    const restore = (course) => {
        if (!window.confirm((t.courses_deleted_restore_confirm || 'Restore ":title"? It comes back as a draft, not on the public site.').replace(':title', course.title))) {
            return;
        }
        router.post(`/admin/public-site/courses/${course.id}/restore`, {}, { preserveScroll: true });
    };

    return (
        <AppShell title={t.courses_deleted_title || 'Deleted courses'}>
            {/* A way back to the list these came from — Inertia since C9 slice 11 (the page-by-page sweep, STATUS §5hw, first put it here). */}
            <p className="mb-3 text-sm"><Link href="/admin/public-site/courses" className="text-[#7C2D37] hover:underline" data-testid="back-link">{t.courses_deleted_back || '← Manage Courses'}</Link></p>
            <p className="mb-2 text-sm text-gray-600">
                {t.courses_deleted_intro || 'Courses that were deleted but kept, because something of somebody’s is attached to them. Nothing listed below was removed — the enrolments, attempts, progress and payment records are why each course survived deletion, and they are all still on the record.'}
            </p>
            <p className="mb-4 text-sm text-gray-500">
                {t.courses_deleted_not_archive || 'This is not the catalogue’s Archive, which is a status on an ordinary visible course and is changed from the Catalog screen. These courses are gone from every other list.'}
            </p>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.courses_deleted_col_course || 'Course'}</th>
                            <th className="px-3 py-2">{t.courses_col_category || 'Category'}</th>
                            <th className="px-3 py-2">{t.courses_deleted_col_holds || 'What it holds'}</th>
                            <th className="px-3 py-2">{t.courses_deleted_col_deleted || 'Deleted'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {courses.length === 0 && (
                            <tr>
                                <td className="px-3 py-6 text-center text-gray-500" colSpan={5}>
                                    {t.courses_deleted_none || 'Nothing has been deleted.'}
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
                                    <p className="mt-1 font-mono text-xs text-gray-500" dir="ltr">/{course.slug}</p>
                                </td>
                                <td className="px-3 py-2">{course.category ?? '—'}</td>
                                <td className="px-3 py-2">
                                    {Object.keys(course.holds).length === 0 ? (
                                        <span className="text-gray-400">{t.courses_deleted_nothing || 'nothing'}</span>
                                    ) : (
                                        Object.entries(course.holds).map(([table, count]) => (
                                            <span
                                                key={table}
                                                className="me-1 inline-block rounded bg-gray-100 px-2 py-0.5 text-xs"
                                            >
                                                {count} {holdName(t, table)}
                                            </span>
                                        ))
                                    )}
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap">{course.deleted_at}</td>
                                <td className="px-3 py-2 whitespace-nowrap">
                                    <button type="button" className="btn-secondary text-xs" onClick={() => restore(course)}>
                                        {t.courses_deleted_restore || 'Restore'}
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <p className="mt-3 text-xs text-gray-500">
                {t.courses_deleted_footnote || 'Restoring brings a course back as a draft. Deleting is usually a withdrawal, and a restore that re-listed it publicly would put content back on the website as a side effect of clicking a button.'}
            </p>
        </AppShell>
    );
}
