/**
 * BOOKSHOP_PLAN slice B6 — Akuru's monthly commission tax invoice to a vendor
 * (§8, audit finding 4), on one page for the printer. No shell. The same page
 * for the shop and the office.
 */
export default function CommissionInvoice({ t, invoice: i, back_url }) {
    return (
        <div className="min-h-screen bg-white p-8 text-gray-900" data-testid="commission-invoice">
            <style>{'@page { size: A4; margin: 14mm } @media print { .no-print { display: none } }'}</style>
            <div className="no-print mb-6 flex items-center justify-between">
                <a href={back_url} className="text-sm text-blue-700 underline">{t.back}</a>
                <button type="button" className="btn-primary" onClick={() => window.print()} data-testid="print-now">{t.print}</button>
            </div>

            <header className="mb-6 flex flex-wrap justify-between gap-4 border-b pb-4">
                <div>
                    <p className="text-2xl font-bold">{i.issuer_name}</p>
                    {i.issuer_tin && <p className="text-sm">{t.tin}: {i.issuer_tin}</p>}
                </div>
                <div className="text-end">
                    <p className="text-xl font-semibold">{Number(i.tax) > 0 ? t.tax_invoice : t.invoice}</p>
                    <p className="font-mono text-lg" data-testid="invoice-number">{i.number}</p>
                    <p className="text-sm">{t.issued_on} {i.issued_at}</p>
                </div>
            </header>

            <section className="mb-6 grid gap-4 md:grid-cols-2 text-sm">
                <div>
                    <p className="text-xs uppercase tracking-wide text-gray-500">{t.invoice_to}</p>
                    <p className="font-semibold" dir="auto">{i.vendor_legal_name}</p>
                    {i.vendor_tin && <p>{t.tin}: {i.vendor_tin}</p>}
                </div>
                <div>
                    <p className="text-xs uppercase tracking-wide text-gray-500">{t.period}</p>
                    <p className="font-semibold">{i.period}</p>
                    <p>{i.period_start} – {i.period_end}</p>
                </div>
            </section>

            <table className="mb-4 w-full text-sm">
                <thead className="border-b text-gray-500"><tr><th className="py-1 text-start">{t.order_number}</th><th className="text-start">{t.paid_on}</th><th className="text-end">{t.sales_charged}</th><th className="text-end">{t.rate}</th><th className="text-end">{t.commission}</th>{Number(i.tax) > 0 && <th className="text-end">{t.gst}</th>}</tr></thead>
                <tbody>
                    {(i.lines || []).map((l) => (
                        <tr key={l.order_number} className="border-b"><td className="py-1 font-mono">{l.order_number}</td><td>{l.paid_at}</td><td className="text-end">{l.sales}</td><td className="text-end">{l.rate}%</td><td className="text-end">{l.commission}</td>{Number(i.tax) > 0 && <td className="text-end">{l.tax}</td>}</tr>
                    ))}
                </tbody>
            </table>

            <section className="ms-auto w-72 text-sm" data-testid="invoice-totals">
                <p className="flex justify-between border-b py-1"><span>{t.commission_on_sales.replace(':amount', `${i.currency} ${i.sales}`)}</span><span>{i.currency} {i.commission}</span></p>
                {Number(i.tax) > 0 && <p className="flex justify-between border-b py-1"><span>{t.gst} {i.tax_rate}%</span><span>{i.currency} {i.tax}</span></p>}
                <p className="flex justify-between py-1 text-base font-bold"><span>{t.total}</span><span>{i.currency} {i.total}</span></p>
            </section>

            <p className="mt-8 text-xs text-gray-500">{t.invoice_footer}</p>
        </div>
    );
}
