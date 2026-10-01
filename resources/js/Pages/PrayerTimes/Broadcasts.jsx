import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

/**
 * Prayer broadcasts (C9 slice 12, STATUS §5jn): the broadcasts by id with
 * mode, status, island, the sent and failed counts and the estimated cost,
 * filtered by mode and status, a CSV of the same filter, pages, and the
 * door to a new draft. Every string is a key in the admin tranche.
 */
export default function Broadcasts({ broadcasts = [], pagination, filters = {}, modes = [], statuses = [], t = {} }) {
        const [mode, setMode] = useState(filters.mode || '');
    const [status, setStatus] = useState(filters.status || '');
    const query = new URLSearchParams(Object.fromEntries(Object.entries({ mode, status }).filter(([, v]) => v))).toString();
    const filter = (e) => {
        e.preventDefault();
        router.get('/admin/prayer-times/broadcasts', { ...(mode ? { mode } : {}), ...(status ? { status } : {}) }, { preserveState: true, replace: true });
    };
    const modeLabel = (value) => t[`prayer_mode_${value}`] || value;
    const statusLabel = (value) => t[`prayer_status_${value}`] || value;

    return (
        <AppShell title={t.prayer_broadcasts_title || 'Prayer broadcasts'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <Link href="/admin/prayer-times/islands" className="underline" data-testid="prayer-islands-link">{t.prayer_link_islands || 'Islands →'}</Link>
                <a href={`/admin/prayer-times/broadcasts/export${query ? `?${query}` : ''}`} className="btn-secondary ms-auto text-sm" data-testid="export-csv">{t.prayer_export || 'Export CSV'}</a>
                <Link href="/admin/prayer-times/broadcasts/create" className="btn-primary" data-testid="broadcast-new">{t.prayer_broadcast_new || 'New broadcast'}</Link>
            </div>
            <form onSubmit={filter} action="/admin/prayer-times/broadcasts" method="get" className="mb-4 flex flex-wrap gap-3 text-sm" data-testid="broadcasts-filter">
                <select name="mode" className="form-input" value={mode} onChange={(e) => setMode(e.target.value)} aria-label={t.prayer_mode || 'Mode'}>
                    <option value="">{t.prayer_all_modes || 'All modes'}</option>
                    {modes.map((value) => <option key={value} value={value}>{modeLabel(value)}</option>)}
                </select>
                <select name="status" className="form-input" value={status} onChange={(e) => setStatus(e.target.value)} aria-label={t.prayer_col_status || 'Status'}>
                    <option value="">{t.prayer_all_statuses || 'All statuses'}</option>
                    {statuses.map((value) => <option key={value} value={value}>{statusLabel(value)}</option>)}
                </select>
                <button type="submit" className="btn-secondary">{t.prayer_filter || 'Filter'}</button>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="broadcasts-table">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.prayer_col_id || 'ID'}</th>
                            <th className="px-3 py-2">{t.prayer_col_mode || 'Mode'}</th>
                            <th className="px-3 py-2">{t.prayer_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.prayer_col_island || 'Island'}</th>
                            <th className="px-3 py-2">{t.prayer_col_sent || 'Sent / failed'}</th>
                            <th className="px-3 py-2">{t.prayer_col_cost || 'Cost'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {broadcasts.length === 0 && (
                            <tr><td className="px-3 py-6 text-center text-gray-500" colSpan="6">{t.prayer_broadcasts_none || 'No broadcasts.'}</td></tr>
                        )}
                        {broadcasts.map((row) => (
                            <tr key={row.id} className="border-t" data-testid="broadcast-row">
                                <td className="px-3 py-2"><Link href={`/admin/prayer-times/broadcasts/${row.id}/edit`} className="text-[#1D4E89] underline" data-testid="broadcast-edit">{row.id}</Link></td>
                                <td className="px-3 py-2">{modeLabel(row.mode)}</td>
                                <td className="px-3 py-2" data-testid="broadcast-status">{statusLabel(row.status)}</td>
                                <td className="px-3 py-2">{row.island}</td>
                                <td className="px-3 py-2" dir="ltr">{row.sent_count}/{row.failed_count}</td>
                                <td className="px-3 py-2" dir="ltr">{row.estimated_cost}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {pagination && pagination.last_page > 1 && (
                <nav className="mt-4 flex items-center gap-3 text-sm" aria-label={t.enrolments_pages || 'Pages'}>
                    {pagination.prev ? <Link href={pagination.prev} className="btn-secondary">{t.enrolments_page_prev || '‹ Previous'}</Link> : <span className="btn-secondary opacity-50">{t.enrolments_page_prev || '‹ Previous'}</span>}
                    <span className="text-gray-600">{(t.enrolments_page_of || 'Page :page of :pages').replace(':page', pagination.current_page).replace(':pages', pagination.last_page)}</span>
                    {pagination.next ? <Link href={pagination.next} className="btn-secondary">{t.enrolments_page_next || 'Next ›'}</Link> : <span className="btn-secondary opacity-50">{t.enrolments_page_next || 'Next ›'}</span>}
                </nav>
            )}
        </AppShell>
    );
}
