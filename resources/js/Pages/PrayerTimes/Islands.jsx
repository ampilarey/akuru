import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

/**
 * Prayer islands — the prayer-times hub (C9 slice 12, STATUS §5jn): every
 * island with its atoll, offset, position and whether it is active, the
 * doors to the import, the groups and the broadcasts, and a CSV. Island
 * names are data; every other string is a key in the admin tranche.
 *
 * Twenty-five a page with a search (STATUS §5no): all 205 islands used to
 * arrive and draw at once — 1,709 DOM nodes, 11,885px tall on a phone, a
 * 45 KB prop (docs/ADMIN_PANEL.md §7 P5). The CSV still carries them all.
 */
export default function Islands({ islands = [], pagination = null, filters = {}, cache_version = 1, default_island_id = null, t = {} }) {
    const [q, setQ] = useState(filters.q ?? '');
    const search = (event) => {
        event.preventDefault();
        router.get('/admin/prayer-times/islands', q.trim() ? { q: q.trim() } : {}, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <AppShell title={t.prayer_islands_title || 'Prayer islands'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <Link href="/admin/prayer-times/import" className="underline" data-testid="prayer-import-link">{t.prayer_link_import || 'Import →'}</Link>
                <Link href="/admin/prayer-times/groups" className="underline" data-testid="prayer-groups-link">{t.prayer_link_groups || 'Groups →'}</Link>
                <Link href="/admin/prayer-times/broadcasts" className="underline" data-testid="prayer-broadcasts-link">{t.prayer_link_broadcasts || 'Broadcasts →'}</Link>
                <Link href="/admin/public-site/daily-content" className="underline">{t.leads_link_daily || 'Daily content →'}</Link>
                <a href="/admin/prayer-times/islands/export" className="btn-secondary ms-auto" data-testid="export-csv">{t.prayer_export || 'Export CSV'}</a>
            </div>
            <p className="mb-4 text-sm text-gray-600" data-testid="prayer-cache-line">
                {(t.prayer_cache_line || 'Cache version :version · default island :island').replace(':version', cache_version).replace(':island', default_island_id || (t.prayer_unset || 'unset'))}
            </p>

            <form onSubmit={search} className="mb-3 flex flex-wrap items-center gap-2" role="search">
                <input
                    type="search"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder={t.prayer_search || 'Search island or atoll…'}
                    aria-label={t.prayer_search || 'Search island or atoll…'}
                    className="form-input w-full text-base sm:w-72 sm:text-sm"
                    data-testid="islands-search"
                />
                <button type="submit" className="btn-secondary min-h-[2.75rem] sm:min-h-0">{t.prayer_search_go || t.users_search || 'Search'}</button>
                {pagination && (
                    <span className="text-sm text-gray-600" data-testid="islands-showing">
                        {(t.prayer_showing || ':shown of :total islands').replace(':shown', islands.length).replace(':total', pagination.total)}
                    </span>
                )}
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="islands-table">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.prayer_col_id || 'ID'}</th>
                            <th className="px-3 py-2">{t.prayer_col_island || 'Island'}</th>
                            <th className="px-3 py-2">{t.prayer_col_atoll || 'Atoll'}</th>
                            <th className="px-3 py-2">{t.prayer_col_offset || 'Offset'}</th>
                            <th className="px-3 py-2">{t.prayer_col_latlng || 'Lat/Lng'}</th>
                            <th className="px-3 py-2">{t.prayer_col_active || 'Active'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {islands.length === 0 && (
                            <tr><td className="px-3 py-6 text-center text-gray-500" colSpan="6">{filters.q ? (t.prayer_islands_no_match || 'No island matches.') : (t.prayer_islands_none || 'No islands. Import salat.db or seed the synthetic fixture.')}</td></tr>
                        )}
                        {islands.map((island) => (
                            <tr key={island.id} className="border-t" data-testid="island-row">
                                <td className="px-3 py-2">{island.id}</td>
                                <td className="px-3 py-2">{island.name_en} <span className="text-gray-500" dir="rtl">{island.name_dv}</span></td>
                                <td className="px-3 py-2">{island.atoll_latin}</td>
                                <td className="px-3 py-2" dir="ltr">{island.offset_minutes}</td>
                                <td className="px-3 py-2" dir="ltr">{island.latitude}, {island.longitude}</td>
                                <td className="px-3 py-2">{island.is_active ? (t.prayer_yes || 'yes') : (t.prayer_no || 'no')}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {pagination && pagination.last_page > 1 && (
                <nav className="mt-4 flex items-center gap-3 text-sm" aria-label={t.users_pages || 'Pages'} data-testid="islands-pagination">
                    {pagination.prev ? <Link href={pagination.prev} preserveScroll className="btn-secondary min-h-[2.75rem] sm:min-h-0">{t.page_prev || '‹ Previous'}</Link> : <span className="btn-secondary opacity-50">{t.page_prev || '‹ Previous'}</span>}
                    <span className="text-gray-600">{(t.page_of || 'Page :page of :pages').replace(':page', pagination.current_page).replace(':pages', pagination.last_page)}</span>
                    {pagination.next ? <Link href={pagination.next} preserveScroll className="btn-secondary min-h-[2.75rem] sm:min-h-0">{t.page_next || 'Next ›'}</Link> : <span className="btn-secondary opacity-50">{t.page_next || 'Next ›'}</span>}
                </nav>
            )}
        </AppShell>
    );
}
