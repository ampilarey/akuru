import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

/**
 * Leads from the public website (W14; docs/ADMIN_PANEL.md; C9 slice 6,
 * STATUS §5jh): who asked for a syllabus, a callback or a waiting-list
 * place, on which course, and where the office has got to with them. A
 * source and status filter as an Inertia visit, and a CSV carrying them.
 * Every string is a key in the admin tranche.
 */
const STATUS_TONES = { new: 'bg-amber-100 text-amber-800', contacted: 'bg-blue-50 text-blue-800', converted: 'bg-green-100 text-green-800', closed: 'bg-gray-100 text-gray-600' };
const humanize = (value) => (value || '').replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());

export default function Leads({ leads = [], filters = {}, sources = [], statuses = [], t = {} }) {
    const [source, setSource] = useState(filters.source || '');
    const [status, setStatus] = useState(filters.status || '');
    const active = { ...(source ? { source } : {}), ...(status ? { status } : {}), ...(filters.course_id ? { course_id: filters.course_id } : {}) };
    const query = new URLSearchParams(active).toString();
    const submit = (e) => {
        e.preventDefault();
        router.get('/admin/public-site/leads', active, { preserveState: true, preserveScroll: true });
    };
    const sourceLabel = (s) => t[`leads_source_${s}`] || humanize(s);
    const statusLabel = (s) => t[`leads_status_${s}`] || humanize(s);

    return (
        <AppShell title={t.leads_title || 'Leads'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <Link href="/admin/public-site/courses" className="underline">{t.leads_link_courses || 'Manage Courses →'}</Link>
                <Link href="/admin/public-site/funnel" className="underline" data-testid="leads-funnel-link">{t.leads_link_funnel || 'Funnel →'}</Link>
                <Link href="/admin/public-site/daily-content" className="underline">{t.leads_link_daily || 'Daily content →'}</Link>
                <p className="text-gray-600" data-testid="leads-total">{(t.leads_total || ':count leads').replace(':count', leads.length)}</p>
                <a href={`/admin/public-site/leads/export${query ? `?${query}` : ''}`} className="ms-auto underline" data-testid="export-csv">{t.leads_export || 'Export CSV'}</a>
            </div>

            <form onSubmit={submit} className="mb-4 flex flex-wrap items-end gap-2 rounded-lg border bg-white p-3" data-testid="leads-filter">
                <label className="text-xs text-gray-600">
                    {t.leads_source || 'Source'}
                    <select className="form-input mt-1 block" name="source" value={source} onChange={(e) => setSource(e.target.value)}>
                        <option value="">{t.leads_all || 'All'}</option>
                        {sources.map((s) => <option key={s} value={s}>{sourceLabel(s)}</option>)}
                    </select>
                </label>
                <label className="text-xs text-gray-600">
                    {t.leads_status || 'Status'}
                    <select className="form-input mt-1 block" name="status" value={status} onChange={(e) => setStatus(e.target.value)}>
                        <option value="">{t.leads_all || 'All'}</option>
                        {statuses.map((s) => <option key={s} value={s}>{statusLabel(s)}</option>)}
                    </select>
                </label>
                <button type="submit" className="btn-primary">{t.leads_filter || 'Filter'}</button>
                {(filters.source || filters.status || filters.course_id) && <Link href="/admin/public-site/leads" className="btn-secondary">{t.leads_clear || 'Clear'}</Link>}
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="leads-table">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.leads_col_when || 'When'}</th>
                            <th className="px-3 py-2">{t.leads_col_course || 'Course'}</th>
                            <th className="px-3 py-2">{t.leads_col_name || 'Name'}</th>
                            <th className="px-3 py-2">{t.leads_col_mobile || 'Mobile'}</th>
                            <th className="px-3 py-2">{t.leads_col_email || 'Email'}</th>
                            <th className="px-3 py-2">{t.leads_source || 'Source'}</th>
                            <th className="px-3 py-2">{t.leads_status || 'Status'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {leads.length === 0 && <tr><td className="px-3 py-8 text-center text-gray-500" colSpan="7">{t.leads_none || 'No leads yet.'}</td></tr>}
                        {leads.map((lead) => (
                            <tr key={lead.id} className="border-t align-top" data-testid="lead-row">
                                <td className="whitespace-nowrap px-3 py-2 text-gray-500">{lead.created_at}</td>
                                <td className="px-3 py-2 text-gray-900">{lead.course_title}</td>
                                <td className="px-3 py-2 font-medium text-gray-900">{lead.name}</td>
                                <td className="whitespace-nowrap px-3 py-2 text-gray-900" dir="ltr">{lead.mobile}</td>
                                <td className="px-3 py-2 text-gray-500" dir="ltr">{lead.email || '—'}</td>
                                <td className="px-3 py-2 text-gray-700">{sourceLabel(lead.source)}</td>
                                <td className="px-3 py-2"><span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${STATUS_TONES[lead.status] || 'bg-gray-100 text-gray-700'}`} data-testid="lead-status">{statusLabel(lead.status)}</span></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
