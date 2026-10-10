import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

/** The first of the month `offset` months from now, as the date box wants it. */
function firstOfMonth(offset) {
    const now = new Date();
    const date = new Date(now.getFullYear(), now.getMonth() + offset, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-01`;
}

/** A balance split into `count` installments, the last taking what rounding leaves. */
function split(balance, count) {
    const cents = Math.round(Number(balance || 0) * 100);
    if (!cents || count < 1) return Array.from({ length: count }, () => '');
    const share = Math.floor(cents / count);

    return Array.from({ length: count }, (_, i) => ((i === count - 1 ? cents - share * (count - 1) : share) / 100).toFixed(2));
}

/**
 * A year's payment plans: an open invoice paid in installments. Every word is
 * the `finance` book's (slice FN1, STATUS §5qn); a plan's state is named
 * rather than printed as its code, and an open invoice says whose it is.
 *
 * The form proposed installments due on 1 February and 1 March 2026, months
 * gone, so a plan saved as proposed was overdue the day it was made; they fall
 * on the first of the next two months now. It had room for exactly two
 * installments and left the second's amount empty, so the plan it proposed
 * was refused — with nothing on the page, as only a refusal of the
 * installments as a whole was said. It splits the balance across as many
 * installments as the office asks for, and a refused plan is said under the
 * form, whichever field was refused.
 */
export default function Index({ years, yearId, plans, openInvoices, t = {} }) {
    const statusName = (status) => t[`plan_status_${status}`] || status;
    const balanceOf = (id) => openInvoices.find((row) => `${row.id}` === `${id}`)?.balance || '';
    const installmentsFor = (id, count) => split(balanceOf(id), count).map((amount, i) => ({ amount, due_date: firstOfMonth(i + 1) }));
    const first = openInvoices[0];
    const form = useForm({
        academic_year_id: yearId || '',
        invoice_id: first?.id || '',
        installments: installmentsFor(first?.id, 2),
    });

    const setInstallment = (index, key, value) => {
        const installments = form.data.installments.map((row, i) => (i === index ? { ...row, [key]: value } : row));
        form.setData('installments', installments);
    };
    // A new count re-splits the balance and keeps the dates already chosen.
    const resize = (count) => form.setData('installments', installmentsFor(form.data.invoice_id, count).map((row, i) => ({
        ...row,
        due_date: form.data.installments[i]?.due_date || row.due_date,
    })));

    return (
        <AppShell title={t.plans_title || 'Payment plans'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-2">
                    {years.map((year) => (
                        <button
                            key={year.id}
                            type="button"
                            className={`rounded px-3 py-1 text-sm ${String(year.id) === String(yearId) ? 'bg-[#7C2D37] text-white' : 'border bg-white'}`}
                            onClick={() => router.get('/finance/payment-plans', { academic_year_id: year.id })}
                        >
                            {year.name}
                        </button>
                    ))}
                </div>
                <a className="btn-secondary" href={`/finance/payment-plans/export?academic_year_id=${yearId || ''}`}>{t.export_csv || 'Export CSV'}</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/finance/payment-plans', { preserveScroll: true });
                }}
                className="mb-4 space-y-3 rounded-lg border bg-white p-4"
            >
                <select
                    className="form-input"
                    aria-label={t.plans_invoice || 'Open invoice'}
                    value={form.data.invoice_id}
                    onChange={(e) => form.setData({ ...form.data, invoice_id: e.target.value, installments: installmentsFor(e.target.value, form.data.installments.length || 1) })}
                >
                    <option value="">{t.plans_invoice || 'Open invoice'}</option>
                    {openInvoices.map((row) => (
                        <option key={row.id} value={row.id}>
                            {(t.plans_invoice_option || ':number — :student — :balance due')
                                .replace(':number', row.invoice_number).replace(':student', row.student_name || '—').replace(':balance', row.balance)}
                        </option>
                    ))}
                </select>
                {openInvoices.length === 0 && <p className="text-sm text-gray-500">{t.plans_no_open || 'No open invoice this year is without a plan.'}</p>}
                {form.data.installments.map((row, index) => (
                    <div key={index} className="grid gap-3 md:grid-cols-3">
                        <input
                            className="form-input"
                            inputMode="decimal"
                            aria-label={(t.plans_installment_amount || 'Installment :n amount').replace(':n', index + 1)}
                            placeholder={(t.plans_installment_amount || 'Installment :n amount').replace(':n', index + 1)}
                            value={row.amount}
                            onChange={(e) => setInstallment(index, 'amount', e.target.value)}
                        />
                        <input
                            className="form-input"
                            type="date"
                            aria-label={(t.plans_installment_due || 'Installment :n due date').replace(':n', index + 1)}
                            value={row.due_date}
                            onChange={(e) => setInstallment(index, 'due_date', e.target.value)}
                        />
                        {form.data.installments.length > 1 && (
                            <button type="button" className="btn-secondary" onClick={() => resize(form.data.installments.length - 1)}>{t.plans_remove || 'One installment fewer'}</button>
                        )}
                    </div>
                ))}
                <div className="flex flex-wrap gap-3">
                    <button type="button" className="btn-secondary" onClick={() => resize(form.data.installments.length + 1)}>{t.plans_add || 'One installment more'}</button>
                    <button type="submit" className="btn-primary" disabled={form.processing || !form.data.invoice_id}>{t.plans_create || 'Create plan'}</button>
                </div>
                <FormErrors errors={form.errors} />
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.plans_col_invoice || 'Invoice'}</th>
                            <th className="px-3 py-2">{t.student || 'Student'}</th>
                            <th className="px-3 py-2">{t.plans_col_paid || 'Paid / total'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {plans.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.plans_none || 'No payment plans yet.'}</td></tr>
                        )}
                        {plans.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.invoice_number}</td>
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.paid_amount} / {row.total_amount}</td>
                                <td className="px-3 py-2">{statusName(row.status)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
