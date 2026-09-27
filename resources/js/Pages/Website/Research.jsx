import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

/**
 * Research posts (W25; docs/ADMIN_PANEL.md; C9 slice 8, STATUS §5jj): the
 * institute's papers as the website lists them, with the year, author and
 * search filters as an Inertia visit, a CSV carrying them, and the door to
 * each post's form. Every string is a key in the admin tranche.
 */
export default function Research({ posts = [], years = [], instructors = [], filters = {}, t = {} }) {
    const { flash = {} } = usePage().props;
    const [form, setForm] = useState({ year: filters.year || '', instructor_id: filters.instructor_id || '', q: filters.q || '' });
    const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });
    const active = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
    const query = new URLSearchParams(active).toString();
    const submit = (e) => {
        e.preventDefault();
        router.get('/admin/public-site/research', active, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppShell title={t.research_title || 'Research posts'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                {/* Daily content and prayer times are still Blade: full page loads. Leads and subscribers are Inertia visits. */}
                <a href="/admin/public-site/daily-content" className="underline">{t.leads_link_daily || 'Daily content →'}</a>
                <a href="/admin/prayer-times/islands" className="underline">{t.subs_link_prayer || 'Prayer times →'}</a>
                <Link href="/admin/public-site/leads" className="underline">{t.funnel_link_leads || 'Leads →'}</Link>
                <p className="text-gray-600" data-testid="research-total">{(t.research_total || ':count posts').replace(':count', posts.length)}</p>
                <a href={`/admin/public-site/research/export${query ? `?${query}` : ''}`} className="ms-auto underline" data-testid="export-csv">{t.research_export || 'Export CSV'}</a>
                <Link href="/admin/public-site/research/create" className="btn-primary" data-testid="research-new">{t.research_new || 'New research'}</Link>
            </div>
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="research-flash">✓ {flash.success}</p>}

            <form onSubmit={submit} className="mb-4 flex flex-wrap items-end gap-2 rounded-lg border bg-white p-3" data-testid="research-filter">
                <label className="text-xs text-gray-600">
                    {t.research_year || 'Year'}
                    <select className="form-input mt-1 block" name="year" value={form.year} onChange={set('year')}>
                        <option value="">{t.leads_all || 'All'}</option>
                        {years.map((y) => <option key={y} value={y}>{y}</option>)}
                    </select>
                </label>
                <label className="text-xs text-gray-600">
                    {t.research_author || 'Author'}
                    <select className="form-input mt-1 block" name="instructor_id" value={form.instructor_id} onChange={set('instructor_id')}>
                        <option value="">{t.leads_all || 'All'}</option>
                        {instructors.map((i) => <option key={i.id} value={i.id}>{i.name}</option>)}
                    </select>
                </label>
                <label className="text-xs text-gray-600">
                    {t.research_search || 'Search'}
                    <input className="form-input mt-1 block min-w-[12rem]" name="q" value={form.q} onChange={set('q')} />
                </label>
                <button type="submit" className="btn-primary">{t.leads_filter || 'Filter'}</button>
                {(filters.year || filters.instructor_id || filters.q) && <Link href="/admin/public-site/research" className="btn-secondary">{t.leads_clear || 'Clear'}</Link>}
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="research-table">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.research_col_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.research_year || 'Year'}</th>
                            <th className="px-3 py-2">{t.research_col_authors || 'Authors'}</th>
                            <th className="px-3 py-2">{t.research_col_published || 'Published'}</th>
                            <th className="px-3 py-2"><span className="sr-only">{t.research_edit || 'Edit'}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {posts.length === 0 && <tr><td className="px-3 py-8 text-center text-gray-500" colSpan="5">{t.research_none || 'No research posts yet.'}</td></tr>}
                        {posts.map((post) => (
                            <tr key={post.id} className="border-t align-top" data-testid="research-row">
                                <td className="px-3 py-2 font-medium text-gray-900">{post.title}</td>
                                <td className="px-3 py-2 text-gray-700">{post.year || '—'}</td>
                                <td className="px-3 py-2 text-gray-700">{post.authors_label || '—'}</td>
                                <td className="px-3 py-2 text-gray-700">{post.is_published ? (t.research_yes || 'yes') : (t.research_no || 'no')}</td>
                                <td className="whitespace-nowrap px-3 py-2"><Link href={`/admin/public-site/research/${post.id}/edit`} className="text-xs font-semibold text-[#1D4E89] underline" data-testid="research-edit">{t.research_edit || 'Edit'}</Link></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
