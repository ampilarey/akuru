import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

export default function Invoices({ children, studentId, invoices, t = {} }) {
    // A Pay button posts with `router`, so a refusal ("already paid", "not
    // available") came back with no word on which row it was for, and nothing
    // showed it. It is said under the row now, in the page's language
    // (BACKLOG C21, slice PT2).
    const refusals = useRowRefusals();
    const col = {
        invoice: t.col_invoice || 'Invoice',
        for: t.col_for || 'For',
        due: t.col_due || 'Due',
        balance: t.col_balance || 'Balance',
        plan: t.col_plan || 'Plan',
    };
    // `preserveState: 'errors'` keeps the page, and so the row it remembers,
    // when the answer is a refusal; the bank's page is a full visit (§5px).
    const pay = (row, mode) => refusals.actOn(`invoice:${row.id}`, () => router.post(`/portal/invoices/${row.id}/pay`, { mode }, { preserveScroll: true, preserveState: 'errors' }));

    return (
        <AppShell title={t.fees_title || 'Fees'}>
            <div className="mb-4">
                <select className="form-input" aria-label={t.pick_child || 'Child'} value={studentId || ''} onChange={(e) => router.get(`/portal/invoices?student_id=${e.target.value}`)}>
                    {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                </select>
            </div>
            <FormErrors errors={refusals.unplaced} className="mb-3" />
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.invoice}</th>
                            <th className="px-3 py-2">{col.for}</th>
                            <th className="px-3 py-2">{col.due}</th>
                            <th className="px-3 py-2">{col.balance}</th>
                            <th className="px-3 py-2">{col.plan}</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.home_no_invoices || 'No invoices.'}</td></tr>
                        )}
                        {invoices.map((row) => {
                            const installment = row.next_installment && Number(row.next_installment) < Number(row.balance);

                            return (
                                <tr key={row.id} className="border-t">
                                    <td className="px-3 py-2" data-label={col.invoice}>{row.invoice_number}</td>
                                    <td className="px-3 py-2" data-label={col.for}>{row.description || '—'}</td>
                                    <td className="px-3 py-2" data-label={col.due}>{row.due_date}</td>
                                    <td className="px-3 py-2" data-label={col.balance}>{row.balance}</td>
                                    <td className="px-3 py-2" data-label={col.plan}>
                                        {row.plan_status ? (t[`plan_status_${row.plan_status}`] || row.plan_status) : '—'}
                                        {row.next_installment ? ` / ${(t.invoices_next || 'next :amount').replace(':amount', row.next_installment)}` : ''}
                                    </td>
                                    <td className="table-actions px-3 py-2">
                                        {Number(row.balance) > 0 && (
                                            <span className="inline-flex flex-wrap gap-2">
                                                {/* S4.6: "pay-now (full / next installment)" — a choice, not a
                                                    guess. A family on a plan may still clear the whole balance. */}
                                                {installment && (
                                                    <button type="button" className="btn-primary" onClick={() => pay(row, 'installment')}>
                                                        {(t.invoices_pay_next || 'Pay next :amount').replace(':amount', row.next_installment)}
                                                    </button>
                                                )}
                                                <button type="button" className={installment ? 'btn-secondary' : 'btn-primary'} onClick={() => pay(row, 'full')}>
                                                    {(t.invoices_pay || 'Pay :amount').replace(':amount', row.balance)}
                                                </button>
                                            </span>
                                        )}
                                        {row.receipts.map((receipt) => (
                                            <a key={receipt.id} className="chip-link" href={`/finance/receipts/${receipt.id}/document`}>{t.invoices_receipt || 'Receipt'}</a>
                                        ))}
                                        <FormErrors errors={refusals.errorsFor(`invoice:${row.id}`)} className="mt-1" />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
