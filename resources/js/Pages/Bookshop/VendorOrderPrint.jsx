/**
 * BOOKSHOP_PLAN slice B3 — an order's packing slip and delivery label, on one
 * page for the printer. No shell. The label is the top half, cut off and taped
 * to the parcel; the slip goes inside.
 */
export default function VendorOrderPrint({ t, order }) {
    const a = order.address || {};

    return (
        <div className="min-h-screen bg-white p-6 text-gray-900">
            <style>{'@page { size: A4; margin: 12mm } @media print { .no-print { display: none } }'}</style>
            <div className="no-print mb-4 flex items-center justify-between">
                <a href="/vendor/orders" className="text-sm text-blue-700 underline">{t.orders_title}</a>
                <button type="button" className="btn-primary" onClick={() => window.print()} data-testid="print-now">{t.print}</button>
            </div>

            <section className="mb-8 rounded border-2 border-dashed border-gray-400 p-5" data-testid="delivery-label">
                <p className="text-xs uppercase tracking-wide text-gray-500">{t.label_to}</p>
                <p className="text-2xl font-bold" dir="auto">{a.recipient_name}</p>
                <p className="text-xl">{a.phone}</p>
                <p className="text-lg" dir="auto">{a.street}</p>
                <p className="text-lg font-semibold" dir="auto">{a.island}, {a.atoll}</p>
                {a.notes && <p className="text-sm">{a.notes}</p>}
                <div className="mt-4 flex justify-between text-sm">
                    <span>{t.label_from}: {order.vendor.name}{order.vendor.phone ? ` · ${order.vendor.phone}` : ''}</span>
                    <span className="font-mono font-semibold">{order.number}</span>
                </div>
                <p className="text-sm">{order.delivery.name}{order.delivery.carrier_paid ? ` · ${t.carrier_paid_note}` : ''}</p>
            </section>

            <section data-testid="packing-slip">
                <h1 className="text-xl font-bold">{t.packing_slip} · <span className="font-mono">{order.number}</span></h1>
                <p className="mb-3 text-sm text-gray-600">{order.vendor.legal_name || order.vendor.name} · {t.at_akuru} · {t.order_placed} {order.placed_at}</p>
                <table className="mb-4 w-full text-sm">
                    <thead className="border-b text-gray-500"><tr><th className="py-1 text-start">{t.product_title}</th><th className="text-start">{t.sku}</th><th className="text-end">{t.quantity}</th><th className="w-16 text-center">✓</th></tr></thead>
                    <tbody>
                        {order.items.map((i) => (
                            <tr key={i.id} className="border-b"><td className="py-1" dir="auto">{i.title}{i.variant ? ` (${i.variant})` : ''}</td><td>{i.sku || ''}</td><td className="text-end">{i.quantity}</td><td className="text-center">☐</td></tr>
                        ))}
                    </tbody>
                </table>
                {order.notes && <p className="text-sm"><span className="font-semibold">{t.order_notes}:</span> {order.notes}</p>}
                <p className="mt-6 text-xs text-gray-500">{t.slip_footer}</p>
            </section>
        </div>
    );
}
