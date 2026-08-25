import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ years, yearId, plans, openInvoices }) {
    const first = openInvoices[0];
    const form = useForm({
        academic_year_id: yearId || '',
        invoice_id: first?.id || '',
        installments: [
            { amount: first?.balance || '', due_date: '2026-02-01' },
            { amount: '', due_date: '2026-03-01' },
        ],
    });

    const setInstallment = (index, key, value) => {
        const installments = form.data.installments.map((row, i) => (i === index ? { ...row, [key]: value } : row));
        form.setData('installments', installments);
    };

    return (
        <AppShell title="Payment plans">
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
                <a className="btn-secondary" href={`/finance/payment-plans/export?academic_year_id=${yearId || ''}`}>Export CSV</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/finance/payment-plans', { preserveScroll: true });
                }}
                className="mb-4 space-y-3 rounded-lg border bg-white p-4"
            >
                <select className="form-input" value={form.data.invoice_id} onChange={(e) => form.setData('invoice_id', e.target.value)}>
                    <option value="">Open invoice</option>
                    {openInvoices.map((row) => (
                        <option key={row.id} value={row.id}>{row.invoice_number} — {row.balance} due</option>
                    ))}
                </select>
                {form.data.installments.map((row, index) => (
                    <div key={index} className="grid gap-3 md:grid-cols-2">
                        <input className="form-input" placeholder={`Installment ${index + 1} amount`} value={row.amount} onChange={(e) => setInstallment(index, 'amount', e.target.value)} />
                        <input className="form-input" type="date" value={row.due_date} onChange={(e) => setInstallment(index, 'due_date', e.target.value)} />
                    </div>
                ))}
                <button type="submit" className="btn-primary" disabled={form.processing}>Create plan</button>
                {form.errors.installments && <span className="text-xs text-red-600">{form.errors.installments}</span>}
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Invoice</th>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Paid / total</th>
                            <th className="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {plans.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No payment plans yet.</td></tr>
                        )}
                        {plans.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.invoice_number}</td>
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.paid_amount} / {row.total_amount}</td>
                                <td className="px-3 py-2">{row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
