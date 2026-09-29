import { Link, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * RESEARCH_ARTICLES_PLAN R4: the website's news, as the office manages it —
 * drafts, scheduled and live, with featured and pinned, a CSV, and the
 * doors to write, preview and edit. Every string is a key in the admin tranche.
 */
const STATE_TONE = { live: 'bg-green-100 text-green-800', scheduled: 'bg-blue-100 text-blue-800', draft: 'bg-gray-100 text-gray-800' };

export default function News({ posts = [], t = {} }) {
    const { flash = {} } = usePage().props;
    const stateLabel = (state) => ({ live: t.news_state_live || 'Published', scheduled: t.news_state_scheduled || 'Scheduled', draft: t.news_state_draft || 'Draft' })[state] || state;

    return (
        <AppShell title={t.news_title || 'News'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <a href="/admin/public-site/news/export" className="underline" data-testid="export-csv">{t.pages_export || 'Export CSV'}</a>
                <Link href="/admin/public-site/news/categories" className="underline" data-testid="news-categories-link">{t.news_categories || 'Categories'}</Link>
                <p className="text-gray-600">{(t.news_total || ':count news items').replace(':count', posts.length)}</p>
                <Link href="/admin/public-site/news/create" className="btn-primary ms-auto" data-testid="news-new">{t.news_new || 'Write news'}</Link>
            </div>
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700">✓ {flash.success}</p>}

            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="news-table">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.pages_col_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.pages_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.news_col_date || 'Published'}</th>
                            <th className="px-3 py-2">{t.news_col_category || 'Category'}</th>
                            <th className="px-3 py-2 text-end">{t.pages_col_actions || 'Actions'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {posts.length === 0 && (
                            <tr>
                                <td className="px-3 py-10 text-center text-gray-500" colSpan="5">
                                    <p className="mb-1 font-medium">{t.news_none || 'No news yet'}</p>
                                    <Link href="/admin/public-site/news/create" className="btn-primary mt-3 inline-block text-sm">{t.news_new || 'Write news'}</Link>
                                </td>
                            </tr>
                        )}
                        {posts.map((post) => (
                            <tr key={post.id} className="border-t align-top" data-testid="news-row">
                                <td className="px-3 py-2">
                                    <p className="font-medium text-gray-900">
                                        {post.is_pinned && <span className="me-1" title={t.news_pinned || 'Pinned'}>📌</span>}
                                        {post.is_featured && <span className="me-1" title={t.news_featured || 'Featured'}>★</span>}
                                        {post.title}
                                    </p>
                                    {post.summary && <p className="text-xs text-gray-500">{post.summary.slice(0, 90)}</p>}
                                </td>
                                <td className="px-3 py-2"><span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${STATE_TONE[post.state] || ''}`} data-testid="news-state">{stateLabel(post.state)}</span></td>
                                <td className="whitespace-nowrap px-3 py-2 text-gray-500">{post.published_at || '—'}</td>
                                <td className="px-3 py-2 text-gray-600">{post.category || '—'}</td>
                                <td className="whitespace-nowrap px-3 py-2 text-end">
                                    {post.state === 'live' && <a href={post.public_url} target="_blank" rel="noopener noreferrer" className="me-3 text-xs font-semibold text-[#1D4E89] underline">{t.pages_view || 'View'}</a>}
                                    <Link href={`/admin/public-site/news/${post.id}`} className="me-3 text-xs font-semibold text-[#1D4E89] underline" data-testid="news-preview">{t.pages_preview || 'Preview'}</Link>
                                    <Link href={`/admin/public-site/news/${post.id}/edit`} className="text-xs font-semibold text-[#1D4E89] underline" data-testid="news-edit">{t.research_edit || 'Edit'}</Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
