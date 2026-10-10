import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

/**
 * Cash or a bank transfer taken at the office against an open invoice. Every
 * word is the `finance` book's (slice FN2, STATUS §5qo); how it was paid is
 * named rather than printed as its code, and an open invoice says whose it
 * is — the office chose by number alone.
 */
export default function Manual({ invoices, methods, t = {} }) {
    const methodName = (method) => t[`receipt_method_${method}`] || method;
    const form = useForm({
        invoice_id: invoices[0]?.id || '',
        amount: invoices[0]?.balance || '',
        method: methods[0] || 'cash',
    });

    return (
        <AppShell title={t.manual_title || 'Manual receipt'}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/finance/receipts/manual', { preserveScroll: true });
                }}
                className="grid max-w-xl gap-3 rounded-lg border bg-white p-4"
            >
                <select
                    className="form-input"
                    aria-label={t.manual_invoice || 'Invoice'}
                    value={form.data.invoice_id}
                    onChange={(e) => form.setData({ ...form.data, invoice_id: e.target.value, amount: invoices.find((row) => `${row.id}` === e.target.value)?.balance || '' })}
                >
                    <option value="">{t.manual_invoice || 'Invoice'}</option>
                    {invoices.map((row) => (
                        <option key={row.id} value={row.id}>
                            {(t.manual_invoice_option || ':number — :student — :balance')
                                .replace(':number', row.invoice_number).replace(':student', row.student_name || '—').replace(':balance', row.balance)}
                        </option>
                    ))}
                </select>
                {invoices.length === 0 && <p className="text-sm text-gray-500">{t.manual_none || 'No invoice is waiting for money.'}</p>}
                <input className="form-input" inputMode="decimal" aria-label={t.amount || 'Amount'} placeholder={t.amount || 'Amount'} value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                <select className="form-input" aria-label={t.manual_method || 'How it was paid'} value={form.data.method} onChange={(e) => form.setData('method', e.target.value)}>
                    {methods.map((method) => <option key={method} value={method}>{methodName(method)}</option>)}
                </select>
                <button type="submit" className="btn-primary" disabled={form.processing || !form.data.invoice_id}>{t.manual_record || 'Record cash / transfer'}</button>
                <FormErrors errors={form.errors} />
            </form>
        </AppShell>
    );
}
