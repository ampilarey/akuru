import { Link, router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

const STATUS_CLASS = { open: 'bg-green-100 text-green-800', upcoming: 'bg-blue-100 text-blue-800' };

/**
 * Manage Courses (the website CMS; docs/ADMIN_PANEL.md; C9 slice 11, STATUS
 * §5jm): the public site's courses by title with their category and status,
 * the doors to edit and delete, a CSV, the deleted list and pages. Every
 * string is a key in the admin tranche.
 */
export default function Courses({ courses = [], pagination, total = 0, t = {} }) {
        const remove = (course) => {
        if (!window.confirm(t.courses_delete_confirm || 'Are you sure you want to delete this course?')) return;
        router.delete(`/admin/public-site/courses/${course.slug}`, { preserveScroll: true });
    };
    const statusLabel = (status) => t[`courses_status_${status}`] || status;

    return (
        <AppShell title={t.courses_title || 'Manage Courses'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <a href="/admin/public-site/courses/export" className="underline" data-testid="export-csv">{t.courses_export || 'Export CSV'}</a>
                <Link href="/admin/public-site/pages" className="underline">{t.courses_link_pages || 'Manage Pages →'}</Link>
                <Link href="/admin/public-site/leads" className="underline">{t.courses_link_leads || 'Leads →'}</Link>
                <Link href="/admin/public-site/funnel" className="underline">{t.leads_link_funnel || 'Funnel →'}</Link>
                <Link href="/admin/public-site/daily-content" className="underline">{t.leads_link_daily || 'Daily content →'}</Link>
                {/* #272 made Delete safe but not reversible. This is where a removed course is seen and restored. */}
                <Link href="/admin/public-site/courses/deleted" className="text-gray-600 underline" data-testid="courses-deleted">{t.courses_link_deleted || 'Deleted courses →'}</Link>
                <p className="text-gray-600" data-testid="courses-total">{(t.courses_total || ':count courses').replace(':count', total)}</p>
                <Link href="/admin/public-site/courses/create" className="btn-primary ms-auto" data-testid="courses-new">{t.courses_new || 'Add New Course'}</Link>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="courses-table">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.pages_col_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.courses_col_category || 'Category'}</th>
                            <th className="px-3 py-2">{t.pages_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.pages_col_updated || 'Updated'}</th>
                            <th className="px-3 py-2 text-end">{t.pages_col_actions || 'Actions'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {courses.length === 0 && (
                            <tr>
                                <td className="px-3 py-10 text-center text-gray-500" colSpan="5">
                                    <p className="mb-1 font-medium">{t.courses_none || 'No courses found'}</p>
                                    <Link href="/admin/public-site/courses/create" className="btn-primary mt-3 inline-block text-sm">{t.courses_create || 'Create Course'}</Link>
                                </td>
                            </tr>
                        )}
                        {courses.map((course) => (
                            <tr key={course.id} className="border-t align-top" data-testid="course-row">
                                <td className="px-3 py-2">
                                    <p className="font-medium text-gray-900">{course.title}</p>
                                    {course.short_desc && <p className="text-xs text-gray-500">{course.short_desc}</p>}
                                </td>
                                <td className="px-3 py-2 text-gray-900">{course.category || (t.courses_no_category || 'N/A')}</td>
                                <td className="px-3 py-2"><span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${STATUS_CLASS[course.status] || 'bg-gray-100 text-gray-800'}`} data-testid="course-status">{statusLabel(course.status)}</span></td>
                                <td className="whitespace-nowrap px-3 py-2 text-gray-500">{course.updated_at}</td>
                                <td className="whitespace-nowrap px-3 py-2 text-end">
                                    <Link href={`/admin/public-site/courses/${course.slug}/edit`} className="me-3 text-xs font-semibold text-[#1D4E89] underline" data-testid="course-edit">{t.research_edit || 'Edit'}</Link>
                                    <button type="button" className="text-xs font-semibold text-red-700 underline" onClick={() => remove(course)} data-testid="course-delete">{t.pages_delete || 'Delete'}</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {pagination && pagination.last_page > 1 && (
                <nav className="mt-4 flex items-center gap-3 text-sm" aria-label={t.enrolments_pages || 'Pages'} data-testid="courses-pagination">
                    {pagination.prev ? <Link href={pagination.prev} className="btn-secondary">{t.enrolments_page_prev || '‹ Previous'}</Link> : <span className="btn-secondary opacity-50">{t.enrolments_page_prev || '‹ Previous'}</span>}
                    <span className="text-gray-600">{(t.enrolments_page_of || 'Page :page of :pages').replace(':page', pagination.current_page).replace(':pages', pagination.last_page)}</span>
                    {pagination.next ? <Link href={pagination.next} className="btn-secondary">{t.enrolments_page_next || 'Next ›'}</Link> : <span className="btn-secondary opacity-50">{t.enrolments_page_next || 'Next ›'}</span>}
                </nav>
            )}
        </AppShell>
    );
}
