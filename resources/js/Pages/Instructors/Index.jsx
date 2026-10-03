import { Link, router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * Instructors (docs/ADMIN_PANEL.md; C9 slice 3, STATUS §5je): the people the
 * public website shows as teaching here — portrait, qualification, what they
 * teach, how many courses name them, whether they are shown — with the add,
 * edit and delete doors, a CSV and pages. Every string is a key in the admin
 * tranche, so the screen reads in Dhivehi and Arabic too.
 */
export default function Index({ instructors = [], pagination, total = 0, t = {} }) {
        const remove = (row) => {
        if (!window.confirm((t.instructors_delete_confirm || 'Delete :name?').replace(':name', row.name))) return;
        router.delete(`/admin/instructors/${row.id}`, { preserveScroll: true });
    };

    return (
        <AppShell title={t.instructors_title || 'Instructors'}>
            <p className="mb-4 text-sm text-gray-600">{t.instructors_intro || 'The instructors shown on the public website.'}</p>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <p className="text-gray-600" data-testid="instructors-total">{(t.instructors_total || ':count instructors').replace(':count', total)}</p>
                <a href="/admin/instructors/export" className="ms-auto underline" data-testid="export-csv">{t.instructors_export || 'Export CSV'}</a>
                <Link href="/admin/instructors/create" className="btn-primary" data-testid="instructors-add">{t.instructors_add || '+ Add instructor'}</Link>
            </div>
            {/* overflow-x-auto: on a phone the action column stays reachable with a swipe (docs/ADMIN_PANEL.md L12). */}
            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="instructors-table">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.instructors_col_name || 'Name'}</th>
                            <th className="px-3 py-2">{t.instructors_col_specialization || 'Specialization'}</th>
                            <th className="px-3 py-2">{t.instructors_col_courses || 'Courses'}</th>
                            <th className="px-3 py-2">{t.instructors_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.instructors_col_actions || 'Actions'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {instructors.length === 0 && (
                            <tr>
                                <td className="px-3 py-8 text-center text-gray-500" colSpan="5">
                                    <p>{t.instructors_none || 'No instructors yet.'}</p>
                                    <Link href="/admin/instructors/create" className="btn-primary mt-3 inline-block text-sm">{t.instructors_add_first || 'Add your first instructor'}</Link>
                                </td>
                            </tr>
                        )}
                        {instructors.map((row) => (
                            <tr key={row.id} className="border-t align-top" data-testid="instructor-row">
                                <td data-label={t.instructors_col_name || 'Name'} className="px-3 py-2">
                                    <div className="flex items-center gap-3">
                                        {row.photo_url
                                            ? <img src={row.photo_url} alt={row.name} className="h-9 w-9 shrink-0 rounded-full object-cover" data-testid="instructor-photo" />
                                            : <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#F3EBE0] text-sm font-semibold text-[#7C2D37]">{(row.name || '?').slice(0, 1).toUpperCase()}</div>}
                                        <div>
                                            <p className="font-medium text-gray-900">{row.name}</p>
                                            {row.qualification && <p className="text-xs text-gray-500">{row.qualification}</p>}
                                            {row.user_name && <p className="text-xs text-[#1D4E89]" data-testid="instructor-login">{(t.instructors_linked_to || 'Signs in as :name').replace(':name', row.user_name)}</p>}
                                        </div>
                                    </div>
                                </td>
                                <td data-label={t.instructors_col_specialization || 'Specialization'} className="px-3 py-2 text-gray-700">{row.specialization || '—'}</td>
                                <td data-label={t.instructors_col_courses || 'Courses'} className="px-3 py-2 text-gray-700">{row.courses_count}</td>
                                <td data-label={t.instructors_col_status || 'Status'} className="px-3 py-2">
                                    <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${row.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'}`} data-testid="instructor-status">
                                        {row.is_active ? (t.instructors_active || 'Active') : (t.instructors_inactive || 'Inactive')}
                                    </span>
                                </td>
                                <td className="table-actions whitespace-nowrap px-3 py-2">
                                    <Link href={`/admin/instructors/${row.id}/edit`} className="me-3 text-xs font-semibold text-[#1D4E89] underline" data-testid="instructor-edit">{t.instructors_edit || 'Edit'}</Link>
                                    <button type="button" className="text-xs font-semibold text-red-700 underline" onClick={() => remove(row)} data-testid="instructor-delete">{t.instructors_delete || 'Delete'}</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {pagination && pagination.last_page > 1 && (
                <nav className="mt-4 flex items-center gap-3 text-sm" aria-label={t.instructors_pages || 'Pages'} data-testid="instructors-pagination">
                    {pagination.prev ? <Link href={pagination.prev} className="btn-secondary">{t.instructors_page_prev || '‹ Previous'}</Link> : <span className="btn-secondary opacity-50">{t.instructors_page_prev || '‹ Previous'}</span>}
                    <span className="text-gray-600">{(t.instructors_page_of || 'Page :page of :pages').replace(':page', pagination.current_page).replace(':pages', pagination.last_page)}</span>
                    {pagination.next ? <Link href={pagination.next} className="btn-secondary">{t.instructors_page_next || 'Next ›'}</Link> : <span className="btn-secondary opacity-50">{t.instructors_page_next || 'Next ›'}</span>}
                </nav>
            )}
        </AppShell>
    );
}
