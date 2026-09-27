import { Link, router, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * Manage Pages (the website CMS; docs/ADMIN_PANEL.md; C9 slice 10, STATUS
 * §5jl): the public site's pages by title, published or draft, with the
 * doors to view, edit and delete, a CSV and pages. Every string is a key in
 * the admin tranche.
 */
export default function Pages({ pages = [], pagination, total = 0, t = {} }) {
    const { flash = {} } = usePage().props;
    const remove = (page) => {
        if (!window.confirm(t.pages_delete_confirm || 'Are you sure you want to delete this page?')) return;
        router.delete(`/admin/public-site/pages/${page.id}`, { preserveScroll: true });
    };

    return (
        <AppShell title={t.pages_title || 'Manage Pages'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <a href="/admin/public-site/pages/export" className="underline" data-testid="export-csv">{t.pages_export || 'Export CSV'}</a>
                <Link href="/admin/public-site/courses" className="underline">{t.leads_link_courses || 'Manage Courses →'}</Link>
                <p className="text-gray-600" data-testid="pages-total">{(t.pages_total || ':count pages').replace(':count', total)}</p>
                <Link href="/admin/public-site/pages/create" className="btn-primary ms-auto" data-testid="pages-new">{t.pages_new || 'Add New Page'}</Link>
            </div>
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="pages-flash">✓ {flash.success}</p>}

            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="pages-table">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.pages_col_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.pages_col_slug || 'Slug'}</th>
                            <th className="px-3 py-2">{t.pages_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.pages_col_updated || 'Updated'}</th>
                            <th className="px-3 py-2 text-end">{t.pages_col_actions || 'Actions'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {pages.length === 0 && (
                            <tr>
                                <td className="px-3 py-10 text-center text-gray-500" colSpan="5">
                                    <p className="mb-1 font-medium">{t.pages_none || 'No pages found'}</p>
                                    <p className="text-xs">{t.pages_none_hint || 'Get started by creating your first page.'}</p>
                                    <Link href="/admin/public-site/pages/create" className="btn-primary mt-3 inline-block text-sm">{t.pages_create || 'Create Page'}</Link>
                                </td>
                            </tr>
                        )}
                        {pages.map((page) => (
                            <tr key={page.id} className="border-t align-top" data-testid="page-row">
                                <td className="px-3 py-2">
                                    <p className="font-medium text-gray-900">{page.title}</p>
                                    {page.excerpt && <p className="text-xs text-gray-500">{page.excerpt}</p>}
                                </td>
                                <td className="px-3 py-2"><code className="rounded bg-gray-100 px-2 py-0.5 text-xs" dir="ltr">{page.slug}</code></td>
                                <td className="px-3 py-2"><span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${page.is_published ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`} data-testid="page-status">{page.is_published ? (t.pages_published || 'Published') : (t.pages_draft || 'Draft')}</span></td>
                                <td className="whitespace-nowrap px-3 py-2 text-gray-500">{page.updated_at}</td>
                                <td className="whitespace-nowrap px-3 py-2 text-end">
                                    <a href={page.public_url} target="_blank" rel="noopener noreferrer" className="me-3 text-xs font-semibold text-[#1D4E89] underline">{t.pages_view || 'View'}</a>
                                    <Link href={`/admin/public-site/pages/${page.id}`} className="me-3 text-xs font-semibold text-[#1D4E89] underline" data-testid="page-preview">{t.pages_preview || 'Preview'}</Link>
                                    <Link href={`/admin/public-site/pages/${page.id}/edit`} className="me-3 text-xs font-semibold text-[#1D4E89] underline" data-testid="page-edit">{t.research_edit || 'Edit'}</Link>
                                    <button type="button" className="text-xs font-semibold text-red-700 underline" onClick={() => remove(page)} data-testid="page-delete">{t.pages_delete || 'Delete'}</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {pagination && pagination.last_page > 1 && (
                <nav className="mt-4 flex items-center gap-3 text-sm" aria-label={t.enrolments_pages || 'Pages'} data-testid="pages-pagination">
                    {pagination.prev ? <Link href={pagination.prev} className="btn-secondary">{t.enrolments_page_prev || '‹ Previous'}</Link> : <span className="btn-secondary opacity-50">{t.enrolments_page_prev || '‹ Previous'}</span>}
                    <span className="text-gray-600">{(t.enrolments_page_of || 'Page :page of :pages').replace(':page', pagination.current_page).replace(':pages', pagination.last_page)}</span>
                    {pagination.next ? <Link href={pagination.next} className="btn-secondary">{t.enrolments_page_next || 'Next ›'}</Link> : <span className="btn-secondary opacity-50">{t.enrolments_page_next || 'Next ›'}</span>}
                </nav>
            )}
        </AppShell>
    );
}
