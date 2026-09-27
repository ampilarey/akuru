import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

/**
 * The office's payments list (SPEC §49; C9 slice 4, STATUS §5jf): every
 * gateway and manual payment, newest first, with a search, the status
 * filter, a CSV carrying the filters, and — for whoever may refund — a
 * refund form on each payment that still has money to give back. Every
 * string is a key in the admin tranche.
 */
const STATUS_TONES = {
    confirmed: 'bg-green-100 text-green-800',
    paid: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
    cancelled: 'bg-red-100 text-red-800',
    expired: 'bg-red-100 text-red-800',
    refunded: 'bg-gray-200 text-gray-700',
};
const humanize = (value) => (value || '').replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());

function RefundForm({ payment, t }) {
    const [amount, setAmount] = useState(String(payment.refundable));
    const [destination, setDestination] = useState('wallet');
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);
    const submit = (e) => {
        e.preventDefault();
        if (!window.confirm(t.payments_refund_confirm || 'Record this refund? A full refund cancels what the payment bought.')) return;
        setBusy(true);
        router.post(`/admin/payments/${payment.id}/refund`, { amount, destination, reason }, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    return (
        <details data-testid="refund-details">
            <summary className="cursor-pointer text-xs text-[#7C2D37] underline">{t.payments_refund_open || 'Refund…'}</summary>
            {/* A real action on the form, so a walk can find it the way it found the Blade one; the submit is an Inertia post. */}
            <form action={`/admin/payments/${payment.id}/refund`} method="post" onSubmit={submit} className="mt-2 flex flex-wrap items-center gap-1" data-testid="refund-form">
                <input type="number" name="amount" step="0.01" min="0.01" max={payment.refundable} value={amount} onChange={(e) => setAmount(e.target.value)} className="form-input w-24 text-xs" aria-label={t.payments_refund_amount || 'Amount'} />
                <select name="destination" value={destination} onChange={(e) => setDestination(e.target.value)} className="form-input max-w-full text-xs" aria-label={t.payments_refund_destination || 'Destination'}>
                    <option value="wallet">{t.payments_refund_to_wallet || 'To wallet'}</option>
                    <option value="manual">{t.payments_refund_manual || 'Manual (returned outside)'}</option>
                </select>
                <input type="text" name="reason" value={reason} onChange={(e) => setReason(e.target.value)} placeholder={t.payments_refund_reason || 'Reason'} maxLength="500" className="form-input w-32 text-xs" />
                <button type="submit" className="btn-primary px-2 py-1 text-xs" disabled={busy}>{t.payments_refund || 'Refund'}</button>
            </form>
        </details>
    );
}

export default function Payments({ payments = [], pagination, total = 0, filters = {}, statuses = [], can_refund: canRefund = false, t = {} }) {
    const { flash = {}, errors = {} } = usePage().props;
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const active = { ...(search ? { search } : {}), ...(status ? { status } : {}) };
    const query = new URLSearchParams(active).toString();
    const submit = (e) => {
        e.preventDefault();
        router.get('/admin/enrollments/payments', active, { preserveState: true, preserveScroll: true });
    };
    const firstError = Object.values(errors)[0];
    const statusLabel = (s) => t[`payments_status_${s}`] || humanize(s);

    return (
        <AppShell title={t.payments_title || 'Payments'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <nav className="flex gap-4" aria-label={t.enrolments_tabs || 'Enrolments and payments'}>
                    <Link href="/admin/enrollments" className="underline" data-testid="payments-enrolments-link">{t.payments_tab_enrolments || '← Enrolments'}</Link>
                    <span className="font-semibold text-[#7C2D37]">{t.payments_tab_payments || 'Payments'}</span>
                </nav>
                <p className="text-gray-600" data-testid="payments-total">{(t.payments_total || ':count payments').replace(':count', total)}</p>
                <a href={`/admin/enrollments/payments/export${query ? `?${query}` : ''}`} className="ms-auto underline" data-testid="export-csv">{t.payments_export || 'Export CSV'}</a>
            </div>
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="payments-flash">✓ {flash.success}</p>}
            {(flash.error || firstError) && <p className="mb-4 rounded bg-red-50 p-3 text-red-700" data-testid="payments-error">✗ {flash.error || firstError}</p>}

            <form onSubmit={submit} className="mb-4 flex flex-wrap items-end gap-2 rounded-lg border bg-white p-3" data-testid="payments-filter">
                <label className="text-xs text-gray-600">
                    {t.payments_search || 'Search'}
                    <input className="form-input mt-1 block min-w-[14rem]" name="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t.payments_search_placeholder || 'Name, mobile, email, ref…'} />
                </label>
                <label className="text-xs text-gray-600">
                    {t.payments_status || 'Status'}
                    <select className="form-input mt-1 block" name="status" value={status} onChange={(e) => setStatus(e.target.value)}>
                        <option value="">{t.enrolments_all || 'All'}</option>
                        {statuses.map((s) => <option key={s} value={s}>{statusLabel(s)}</option>)}
                    </select>
                </label>
                <button type="submit" className="btn-primary">{t.enrolments_filter || 'Filter'}</button>
                {(filters.search || filters.status) && <Link href="/admin/enrollments/payments" className="btn-secondary">{t.enrolments_clear || 'Clear'}</Link>}
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="payments-table">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.payments_col_reference || 'Reference'}</th>
                            <th className="px-3 py-2">{t.payments_col_payer || 'Payer'}</th>
                            <th className="px-3 py-2">{t.payments_col_student || 'Student'}</th>
                            <th className="px-3 py-2">{t.payments_col_amount || 'Amount'}</th>
                            <th className="px-3 py-2">{t.payments_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.payments_col_date || 'Date'}</th>
                            <th className="px-3 py-2">{t.payments_col_refund || 'Refund'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {payments.length === 0 && <tr><td className="px-3 py-8 text-center text-gray-500" colSpan="7">{t.payments_none || 'No payments found.'}</td></tr>}
                        {payments.map((p) => (
                            <tr key={p.id} className="border-t align-top" data-testid="payment-row">
                                <td className="max-w-xs break-all px-3 py-2 font-mono text-xs text-gray-600">{p.reference}</td>
                                <td className="px-3 py-2 text-gray-800">{p.payer || '—'}</td>
                                <td className="px-3 py-2 text-gray-800">{p.student || '—'}</td>
                                <td className="whitespace-nowrap px-3 py-2 font-semibold text-gray-900">{p.amount} {p.currency}</td>
                                <td className="px-3 py-2"><span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${STATUS_TONES[p.status] || 'bg-amber-100 text-amber-800'}`} data-testid="payment-status">{statusLabel(p.status)}</span></td>
                                <td className="whitespace-nowrap px-3 py-2 text-gray-500">{p.date}</td>
                                <td className="px-3 py-2">
                                    {p.refunded && <p className="mb-1 text-xs text-gray-500">{(t.payments_refunded || 'Refunded: :amount').replace(':amount', `${p.refunded} ${p.currency}`)}</p>}
                                    {canRefund && p.refundable
                                        ? <RefundForm payment={p} t={t} />
                                        : p.status === 'refunded'
                                            ? <span className="text-xs text-gray-400">{t.payments_fully_refunded || 'Fully refunded'}</span>
                                            : <span className="text-xs text-gray-300">—</span>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {pagination && pagination.last_page > 1 && (
                <nav className="mt-4 flex items-center gap-3 text-sm" aria-label={t.enrolments_pages || 'Pages'} data-testid="payments-pagination">
                    {pagination.prev ? <Link href={pagination.prev} className="btn-secondary">{t.enrolments_page_prev || '‹ Previous'}</Link> : <span className="btn-secondary opacity-50">{t.enrolments_page_prev || '‹ Previous'}</span>}
                    <span className="text-gray-600">{(t.enrolments_page_of || 'Page :page of :pages').replace(':page', pagination.current_page).replace(':pages', pagination.last_page)}</span>
                    {pagination.next ? <Link href={pagination.next} className="btn-secondary">{t.enrolments_page_next || 'Next ›'}</Link> : <span className="btn-secondary opacity-50">{t.enrolments_page_next || 'Next ›'}</span>}
                </nav>
            )}
        </AppShell>
    );
}
