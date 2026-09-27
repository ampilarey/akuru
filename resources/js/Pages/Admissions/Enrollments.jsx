import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

/**
 * The office's enrolment list (docs/ADMIN_PANEL.md; C9 slice 4, STATUS
 * §5jf): every application and enrolment, newest first, with a search, the
 * course, status and payment filters, a CSV carrying the filters, and the
 * door to each enrolment's own page (still Blade, so a full page load).
 * Every string is a key in the admin tranche, so the screen reads in
 * Dhivehi and Arabic too.
 */
const STATUS_TONES = {
    active: 'bg-green-100 text-green-800',
    approved: 'bg-green-100 text-green-800',
    pending: 'bg-amber-100 text-amber-800',
    rejected: 'bg-red-100 text-red-800',
    suspended: 'bg-red-50 text-red-700',
};
const PAYMENT_TONES = {
    confirmed: 'bg-green-100 text-green-800',
    required: 'bg-amber-100 text-amber-800',
    pending: 'bg-amber-50 text-amber-700',
};
const humanize = (value) => (value || '').replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());

export default function Enrollments({ enrollments = [], pagination, total = 0, courses = [], filters = {}, statuses = [], payment_statuses: paymentStatuses = [], t = {} }) {
    const { flash = {} } = usePage().props;
    const [form, setForm] = useState({ search: filters.search || '', course_id: filters.course_id || '', status: filters.status || '', payment_status: filters.payment_status || '' });
    const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });
    const active = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
    const query = new URLSearchParams(active).toString();
    const submit = (e) => {
        e.preventDefault();
        router.get('/admin/enrollments', active, { preserveState: true, preserveScroll: true });
    };
    const filtered = Object.values(filters).some((v) => v);
    const statusLabel = (s) => t[`enrolments_status_${s}`] || humanize(s);
    const paymentLabel = (s) => (s ? t[`enrolments_pay_${s}`] || humanize(s) : t.enrolments_pay_none || 'N/A');

    return (
        <AppShell title={t.enrolments_title || 'Enrolments'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <nav className="flex gap-4" aria-label={t.enrolments_tabs || 'Enrolments and payments'}>
                    <span className="font-semibold text-[#7C2D37]">{t.enrolments_tab_enrolments || 'Enrolments'}</span>
                    <Link href="/admin/enrollments/payments" className="underline" data-testid="enrolments-payments-link">{t.enrolments_tab_payments || 'Payments →'}</Link>
                </nav>
                <p className="text-gray-600" data-testid="enrolments-total">{(t.enrolments_total || ':count enrolments').replace(':count', total)}</p>
                <a href={`/admin/enrollments/export${query ? `?${query}` : ''}`} className="ms-auto underline" data-testid="export-csv">{t.enrolments_export || 'Export CSV'}</a>
            </div>
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="enrolments-flash">✓ {flash.success}</p>}
            {flash.error && <p className="mb-4 rounded bg-red-50 p-3 text-red-700" data-testid="enrolments-error">✗ {flash.error}</p>}

            <form onSubmit={submit} className="mb-4 flex flex-wrap items-end gap-2 rounded-lg border bg-white p-3" data-testid="enrolments-filter">
                <label className="text-xs text-gray-600">
                    {t.enrolments_search || 'Search'}
                    <input className="form-input mt-1 block min-w-[13rem]" name="search" value={form.search} onChange={set('search')} placeholder={t.enrolments_search_placeholder || 'Name, mobile, email…'} />
                </label>
                <label className="text-xs text-gray-600">
                    {t.enrolments_course || 'Course'}
                    <select className="form-input mt-1 block" name="course_id" value={form.course_id} onChange={set('course_id')}>
                        <option value="">{t.enrolments_all_courses || 'All courses'}</option>
                        {courses.map((c) => <option key={c.id} value={c.id}>{c.title}</option>)}
                    </select>
                </label>
                <label className="text-xs text-gray-600">
                    {t.enrolments_status || 'Status'}
                    <select className="form-input mt-1 block" name="status" value={form.status} onChange={set('status')}>
                        <option value="">{t.enrolments_all || 'All'}</option>
                        {statuses.map((s) => <option key={s} value={s}>{statusLabel(s)}</option>)}
                    </select>
                </label>
                <label className="text-xs text-gray-600">
                    {t.enrolments_payment || 'Payment'}
                    <select className="form-input mt-1 block" name="payment_status" value={form.payment_status} onChange={set('payment_status')}>
                        <option value="">{t.enrolments_all || 'All'}</option>
                        {paymentStatuses.map((s) => <option key={s} value={s}>{paymentLabel(s)}</option>)}
                    </select>
                </label>
                <button type="submit" className="btn-primary">{t.enrolments_filter || 'Filter'}</button>
                {filtered && <Link href="/admin/enrollments" className="btn-secondary">{t.enrolments_clear || 'Clear'}</Link>}
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="enrolments-table">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.enrolments_col_student || 'Student'}</th>
                            <th className="px-3 py-2">{t.enrolments_col_course || 'Course'}</th>
                            <th className="px-3 py-2">{t.enrolments_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.enrolments_col_payment || 'Payment'}</th>
                            <th className="px-3 py-2">{t.enrolments_col_date || 'Date'}</th>
                            <th className="px-3 py-2"><span className="sr-only">{t.enrolments_col_action || 'Action'}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {enrollments.length === 0 && <tr><td className="px-3 py-8 text-center text-gray-500" colSpan="6">{t.enrolments_none || 'No enrollments found.'}</td></tr>}
                        {enrollments.map((e) => (
                            <tr key={e.id} className="border-t align-top" data-testid="enrolment-row">
                                <td className="px-3 py-2 font-medium text-gray-900">{e.student || '—'}</td>
                                <td className="px-3 py-2 text-gray-700">{e.course || '—'}</td>
                                <td className="px-3 py-2"><span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${STATUS_TONES[e.status] || 'bg-gray-100 text-gray-700'}`} data-testid="enrolment-status">{statusLabel(e.status)}</span></td>
                                <td className="px-3 py-2"><span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${PAYMENT_TONES[e.payment_status] || 'bg-gray-100 text-gray-600'}`} data-testid="enrolment-payment">{paymentLabel(e.payment_status)}</span></td>
                                <td className="whitespace-nowrap px-3 py-2 text-gray-500">{e.date}</td>
                                <td className="whitespace-nowrap px-3 py-2 text-end">
                                    {/* The one-enrolment page is still Blade (C9 slice 5): a plain link, a full page load. */}
                                    <a href={`/admin/enrollments/${e.id}`} className="text-xs font-semibold text-[#1D4E89] underline" data-testid="enrolment-view">{t.enrolments_view || 'View'}</a>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {pagination && pagination.last_page > 1 && (
                <nav className="mt-4 flex items-center gap-3 text-sm" aria-label={t.enrolments_pages || 'Pages'} data-testid="enrolments-pagination">
                    {pagination.prev ? <Link href={pagination.prev} className="btn-secondary">{t.enrolments_page_prev || '‹ Previous'}</Link> : <span className="btn-secondary opacity-50">{t.enrolments_page_prev || '‹ Previous'}</span>}
                    <span className="text-gray-600">{(t.enrolments_page_of || 'Page :page of :pages').replace(':page', pagination.current_page).replace(':pages', pagination.last_page)}</span>
                    {pagination.next ? <Link href={pagination.next} className="btn-secondary">{t.enrolments_page_next || 'Next ›'}</Link> : <span className="btn-secondary opacity-50">{t.enrolments_page_next || 'Next ›'}</span>}
                </nav>
            )}
        </AppShell>
    );
}
