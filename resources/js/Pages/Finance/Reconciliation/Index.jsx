import AppShell from '../../../Layouts/AppShell';

export default function Index({ rows, daily }) {
    return (
        <AppShell title="Reconciliation">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/finance/reconciliation/export">Export CSV</a>
            </div>
            <h2 className="mb-2 text-sm font-medium">Daily totals by method</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Method</th>
                            <th className="px-3 py-2">Total</th>
                            <th className="px-3 py-2">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        {daily.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No receipts.</td></tr>
                        )}
                        {daily.map((row) => (
                            <tr key={`${row.date}-${row.method}`} className="border-t">
                                <td className="px-3 py-2">{row.date}</td>
                                <td className="px-3 py-2">{row.method}</td>
                                <td className="px-3 py-2">{row.total}</td>
                                <td className="px-3 py-2">{row.count}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <h2 className="mb-2 text-sm font-medium">Payments ↔ receipts ↔ balances</h2>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Receipt</th>
                            <th className="px-3 py-2">Payment</th>
                            <th className="px-3 py-2">Invoice</th>
                            <th className="px-3 py-2">Amount</th>
                            <th className="px-3 py-2">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.receipt_number} className="border-t">
                                <td className="px-3 py-2">{row.receipt_number}</td>
                                <td className="px-3 py-2">{row.payment_reference || '—'}</td>
                                <td className="px-3 py-2">{row.invoice_number}</td>
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
