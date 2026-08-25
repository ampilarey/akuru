import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Manual({ invoices, methods }) {
    const form = useForm({
        invoice_id: invoices[0]?.id || '',
        amount: invoices[0]?.balance || '',
        method: methods[0] || 'cash',
    });

    return (
        <AppShell title="Manual receipt">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/finance/receipts/manual');
                }}
                className="grid max-w-xl gap-3 rounded-lg border bg-white p-4"
            >
                <select className="form-input" value={form.data.invoice_id} onChange={(e) => form.setData('invoice_id', e.target.value)}>
                    <option value="">Invoice</option>
                    {invoices.map((row) => (
                        <option key={row.id} value={row.id}>{row.invoice_number} — {row.balance}</option>
                    ))}
                </select>
                <input className="form-input" placeholder="Amount" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                <select className="form-input" value={form.data.method} onChange={(e) => form.setData('method', e.target.value)}>
                    {methods.map((method) => <option key={method} value={method}>{method}</option>)}
                </select>
                <button type="submit" className="btn-primary" disabled={form.processing}>Record cash / transfer</button>
            </form>
        </AppShell>
    );
}
