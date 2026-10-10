import { useState } from 'react';
import { router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * Receipts against payments and the balances they leave, and each day's
 * takings by how they were paid. Every word is the `finance` book's (slice
 * FN2, STATUS §5qo); how a receipt was paid is named rather than printed as
 * its code (*bml*, *gift_card*). The report reads the days the office chooses
 * — the server took a from and a to, and the screen offered neither.
 */
export default function Index({ rows, daily, from = '', to = '', t = {} }) {
    const methodName = (method) => t[`receipt_method_${method}`] || method;
    const [range, setRange] = useState({ from: from || '', to: to || '' });
    const query = new URLSearchParams(Object.entries(range).filter(([, value]) => value)).toString();

    return (
        <AppShell title={t.reconciliation_title || 'Reconciliation'}>
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/finance/reconciliation', Object.fromEntries(Object.entries(range).filter(([, value]) => value)), { preserveScroll: true });
                    }}
                >
                    <input type="date" className="form-input" aria-label={t.reconciliation_from || 'From'} value={range.from} onChange={(e) => setRange({ ...range, from: e.target.value })} />
                    <input type="date" className="form-input" aria-label={t.reconciliation_to || 'To'} value={range.to} onChange={(e) => setRange({ ...range, to: e.target.value })} />
                    <button type="submit" className="btn-secondary">{t.reconciliation_show || 'Show these days'}</button>
                </form>
                <a className="btn-secondary" href={`/finance/reconciliation/export${query ? `?${query}` : ''}`}>{t.export_csv || 'Export CSV'}</a>
            </div>
            <h2 className="mb-2 text-sm font-medium">{t.reconciliation_daily || 'Daily totals by method'}</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.reconciliation_date || 'Date'}</th>
                            <th className="px-3 py-2">{t.reconciliation_method || 'Method'}</th>
                            <th className="px-3 py-2">{t.invoices_col_total || 'Total'}</th>
                            <th className="px-3 py-2">{t.reconciliation_count || 'Receipts'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {daily.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.reconciliation_none || 'No receipts.'}</td></tr>
                        )}
                        {daily.map((row) => (
                            <tr key={`${row.date}-${row.method}`} className="border-t">
                                <td className="px-3 py-2">{row.date}</td>
                                <td className="px-3 py-2">{methodName(row.method)}</td>
                                <td className="px-3 py-2">{row.total}</td>
                                <td className="px-3 py-2">{row.count}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <h2 className="mb-2 text-sm font-medium">{t.reconciliation_receipts || 'Payments, receipts and balances'}</h2>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.reconciliation_receipt || 'Receipt'}</th>
                            <th className="px-3 py-2">{t.reconciliation_payment || 'Payment'}</th>
                            <th className="px-3 py-2">{t.plans_col_invoice || 'Invoice'}</th>
                            <th className="px-3 py-2">{t.reconciliation_method || 'Method'}</th>
                            <th className="px-3 py-2">{t.amount || 'Amount'}</th>
                            <th className="px-3 py-2">{t.arrears_balance || 'Balance'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.reconciliation_none || 'No receipts.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.receipt_number} className="border-t">
                                <td className="px-3 py-2">{row.receipt_number}</td>
                                <td className="px-3 py-2">{row.payment_reference || '—'}</td>
                                <td className="px-3 py-2">{row.invoice_number}</td>
                                <td className="px-3 py-2">{methodName(row.method)}</td>
                                <td className="px-3 py-2">{row.amount}</td>
                                <td className="px-3 py-2">{row.invoice_balance}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
