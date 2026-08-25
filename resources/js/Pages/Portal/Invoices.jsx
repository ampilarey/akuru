import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Invoices({ children, studentId, invoices }) {
    return (
        <AppShell title="Fees">
            <div className="mb-4">
                <select className="form-input" value={studentId || ''} onChange={(e) => router.get(`/portal/invoices?student_id=${e.target.value}`)}>
                    {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                </select>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Invoice</th>
                            <th className="px-3 py-2">Due</th>
                            <th className="px-3 py-2">Balance</th>
                            <th className="px-3 py-2">Plan</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No invoices.</td></tr>
                        )}
                        {invoices.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.invoice_number}</td>
                                <td className="px-3 py-2">{row.due_date}</td>
                                <td className="px-3 py-2">{row.balance}</td>
                                <td className="px-3 py-2">{row.plan_status || '—'}{row.next_installment ? ` / next ${row.next_installment}` : ''}</td>
                                <td className="px-3 py-2">
                                    {Number(row.balance) > 0 && (
                                        <form onSubmit={(e) => { e.preventDefault(); router.post(`/portal/invoices/${row.id}/pay`, { mode: row.next_installment ? 'installment' : 'full' }); }}>
                                            <button type="submit" className="btn-primary">Pay now</button>
                                        </form>
                                    )}
                                    {row.receipts.map((receipt) => (
                                        <a key={receipt.id} className="ml-2 text-[#7C2D37] underline" href={`/finance/receipts/${receipt.id}`}>Receipt</a>
                                    ))}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
