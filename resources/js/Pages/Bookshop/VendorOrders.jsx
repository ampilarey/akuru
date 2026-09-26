import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * BOOKSHOP_PLAN slice B3 — the shop's order queue. Tabs by status with
 * counts; open an order to move it on (processing → ready to collect or
 * dispatched → delivered or collected), print its packing slip and label,
 * cancel it with a reason before it leaves, answer a return, confirm a
 * bank-transfer slip on a checkout that is the shop's alone, and write to
 * the customer. Contacts are masked once an order has closed and its return
 * window passed (decision 15).
 */

const TABS = ['', 'pending_payment', 'paid', 'needs_attention', 'processing', 'ready', 'dispatched', 'delivered', 'cancelled', 'returns'];

const tone = {
    pending_payment: 'bg-amber-100 text-amber-800',
    paid: 'bg-blue-100 text-blue-800',
    needs_attention: 'bg-red-100 text-red-800',
    processing: 'bg-indigo-100 text-indigo-800',
    ready: 'bg-teal-100 text-teal-800',
    dispatched: 'bg-teal-100 text-teal-800',
    delivered: 'bg-green-100 text-green-800',
    cancelled: 'bg-gray-200 text-gray-700',
};

const post = (url, data) => router.post(url, data, { preserveScroll: true });

function StepButtons({ order, t }) {
    const [carrier, setCarrier] = useState(order.carrier || '');
    const [tracking, setTracking] = useState(order.tracking_note || '');
    if (order.next.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-end gap-2" data-testid={`steps-${order.number}`}>
            {order.next.includes('dispatched') && (
                <>
                    <label className="text-sm">{t.carrier}<input className="form-input block w-40" value={carrier} onChange={(e) => setCarrier(e.target.value)} data-testid="carrier" /></label>
                    <label className="text-sm">{t.tracking_note}<input className="form-input block w-64" value={tracking} onChange={(e) => setTracking(e.target.value)} data-testid="tracking-note" /></label>
                </>
            )}
            {order.next.map((to) => (
                <button
                    key={to}
                    type="button"
                    className={to === order.next[order.next.length - 1] ? 'btn-primary' : 'btn-secondary'}
                    data-testid={`step-${to}`}
                    onClick={() => post(`/vendor/orders/${order.id}/advance`, { to, carrier, tracking_note: tracking })}
                >
                    {to === 'delivered' && order.collection ? t.step_collected : t[`step_${to}`]}
                </button>
            ))}
        </div>
    );
}

function CancelForm({ order, t }) {
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    if (!order.cancellable) {
        return null;
    }

    return open ? (
        <div className="flex flex-wrap items-center gap-2" data-testid="vendor-cancel-form">
            <input className="form-input w-72" placeholder={t.cancel_reason} value={reason} onChange={(e) => setReason(e.target.value)} data-testid="vendor-cancel-reason" />
            <button type="button" className="rounded bg-red-600 px-3 py-2 text-sm text-white" data-testid="vendor-cancel-confirm" onClick={() => post(`/vendor/orders/${order.id}/cancel`, { reason })}>{t.cancel_order_refund}</button>
            <button type="button" className="text-sm underline" onClick={() => setOpen(false)}>{t.cancel}</button>
        </div>
    ) : (
        <button type="button" className="text-sm text-red-700 underline" data-testid="vendor-cancel" onClick={() => setOpen(true)}>{t.cancel_order}</button>
    );
}

function Slip({ order, t }) {
    const [note, setNote] = useState('');
    const slip = order.slip;
    if (!slip) {
        return null;
    }

    return (
        <div className="rounded border border-amber-300 bg-amber-50 p-3 text-sm" data-testid="vendor-slip">
            <p className="font-semibold">{t.slip_waiting}</p>
            <p>
                {slip.reference || t.none} · {slip.uploaded_at} ·{' '}
                <a href={`/vendor/slips/${slip.id}`} target="_blank" rel="noreferrer" className="text-blue-700 underline">{t.view_slip}</a>
            </p>
            {slip.can_confirm ? (
                <div className="mt-2 flex flex-wrap items-center gap-2">
                    <input className="form-input w-56" placeholder={t.decision_note} value={note} onChange={(e) => setNote(e.target.value)} />
                    <button type="button" className="btn-primary" data-testid="vendor-confirm-slip" onClick={() => post(`/vendor/slips/${slip.id}/decide`, { decision: 'confirm', note })}>{t.confirm}</button>
                    <button type="button" className="text-red-700 underline" onClick={() => post(`/vendor/slips/${slip.id}/decide`, { decision: 'reject', note })}>{t.reject}</button>
                </div>
            ) : (
                <p className="mt-1 text-gray-600">{t.slip_office_confirms}</p>
            )}
        </div>
    );
}

function ReturnRow({ ret, t }) {
    const [restock, setRestock] = useState(ret.reason !== 'damaged');
    const [note, setNote] = useState('');

    return (
        <li className="py-2" data-testid={`return-${ret.id}`} data-return-status={ret.status}>
            <p>
                <span className="font-medium">{ret.quantity} × {ret.title}{ret.variant ? ` (${ret.variant})` : ''}</span>
                {' · '}{t[`reason_${ret.reason}`] || ret.reason}{ret.note ? ` — “${ret.note}”` : ''}
                {' · '}{t.refund_value}: {ret.refund_amount}
            </p>
            {ret.status === 'requested' ? (
                <div className="mt-1 flex flex-wrap items-center gap-2">
                    <label className="flex items-center gap-1 text-sm"><input type="checkbox" checked={restock} onChange={(e) => setRestock(e.target.checked)} /> {t.restock}</label>
                    <input className="form-input w-56" placeholder={t.decline_reason} value={note} onChange={(e) => setNote(e.target.value)} data-testid="return-note" />
                    <button type="button" className="btn-primary" data-testid="accept-return" onClick={() => post(`/vendor/returns/${ret.id}/decide`, { decision: 'accept', restock: restock ? 1 : 0, note })}>{t.accept_return}</button>
                    <button type="button" className="text-red-700 underline" data-testid="decline-return" onClick={() => post(`/vendor/returns/${ret.id}/decide`, { decision: 'decline', note })}>{t.decline_return}</button>
                </div>
            ) : (
                <p className="text-sm text-gray-600">{t[`return_status_${ret.status}`] || ret.status}{ret.decision_note ? ` — ${ret.decision_note}` : ''}{ret.restocked ? ` · ${t.restocked}` : ''}</p>
            )}
        </li>
    );
}

function Message({ order, t }) {
    const [body, setBody] = useState('');

    return (
        <form
            className="flex flex-wrap items-start gap-2"
            onSubmit={(e) => {
                e.preventDefault();
                router.post(`/vendor/orders/${order.id}/message`, { body }, { preserveScroll: true, onSuccess: () => setBody('') });
            }}
        >
            <textarea className="form-input w-full md:w-96" rows={2} placeholder={t.message_customer} value={body} onChange={(e) => setBody(e.target.value)} data-testid="vendor-message" />
            <button type="submit" className="btn-secondary" data-testid="vendor-send-message">{t.send}</button>
            {order.message_thread_id && <a href={`/portal/messages/${order.message_thread_id}`} className="self-center text-sm text-blue-700 underline" data-testid="open-thread">{t.open_conversation}</a>}
        </form>
    );
}

function OrderCard({ order, t, open, onToggle }) {
    const address = order.address || {};

    return (
        <li className="rounded border bg-white" data-testid={`vendor-order-${order.number}`} data-status={order.status}>
            <button type="button" className="flex w-full flex-wrap items-center justify-between gap-2 p-3 text-start" onClick={onToggle} aria-expanded={open}>
                <span>
                    <span className="font-mono font-semibold">{order.number}</span>
                    <span className={`ms-2 rounded px-2 py-0.5 text-xs ${tone[order.status] || 'bg-gray-100'}`}>{t[`status_${order.status}`] || order.status}</span>
                    {order.returns.some((r) => r.status === 'requested') && <span className="ms-2 rounded bg-orange-100 px-2 py-0.5 text-xs text-orange-800">{t.return_requested}</span>}
                    <span className="block text-sm text-gray-600">{order.customer} · {order.delivery.name} · {order.placed_at}</span>
                </span>
                <span className="font-semibold">{order.currency} {order.total}</span>
            </button>
            {open && (
                <div className="space-y-4 border-t p-3 text-sm">
                    <Slip order={order} t={t} />
                    <table className="w-full">
                        <thead className="text-gray-500"><tr><th className="text-start">{t.product_title}</th><th className="text-start">{t.sku}</th><th className="text-end">{t.quantity}</th><th className="text-end">{t.line_total}</th></tr></thead>
                        <tbody>
                            {order.items.map((i) => (
                                <tr key={i.id} className="border-t"><td dir="auto">{i.title}{i.variant ? ` (${i.variant})` : ''}</td><td>{i.sku || t.none}</td><td className="text-end">{i.quantity}</td><td className="text-end">{i.line_total}</td></tr>
                            ))}
                        </tbody>
                    </table>
                    <div className="grid gap-3 md:grid-cols-2">
                        <div data-testid="vendor-order-address">
                            <p className="font-semibold">{t.deliver_to}</p>
                            <p>{address.recipient_name} · {address.phone}</p>
                            <p>{address.street}, {address.island}, {address.atoll}</p>
                            {address.notes && <p className="text-gray-600">{address.notes}</p>}
                            {address.masked && <p className="text-xs text-gray-500">{t.contact_masked}</p>}
                        </div>
                        <div>
                            <p><span className="font-semibold">{t.delivery_heading}:</span> {order.delivery.name}{order.delivery.carrier_paid ? ` · ${t.carrier_paid_note}` : ''}</p>
                            <p><span className="font-semibold">{t.payment_method}:</span> {t[`pay_${order.payment_method}`] || order.payment_method}</p>
                            {order.notes && <p><span className="font-semibold">{t.order_notes}:</span> {order.notes}</p>}
                            {(order.carrier || order.tracking_note) && <p><span className="font-semibold">{t.tracking_note}:</span> {order.carrier} {order.tracking_note}</p>}
                            {order.cancel_reason && <p className="text-red-700"><span className="font-semibold">{t.cancel_reason}:</span> {order.cancel_reason}</p>}
                        </div>
                    </div>
                    <StepButtons order={order} t={t} />
                    <div className="flex flex-wrap items-center gap-4">
                        <a href={`/vendor/orders/${order.id}/print`} target="_blank" rel="noreferrer" className="btn-secondary" data-testid="print-order">{t.print_slip}</a>
                        <CancelForm order={order} t={t} />
                    </div>
                    {order.returns.length > 0 && (
                        <div>
                            <p className="font-semibold">{t.returns_heading}</p>
                            <ul className="divide-y">{order.returns.map((r) => <ReturnRow key={r.id} ret={r} t={t} />)}</ul>
                        </div>
                    )}
                    {order.refunds.length > 0 && (
                        <div data-testid="vendor-refunds">
                            <p className="font-semibold">{t.refunds_heading}</p>
                            <ul>{order.refunds.map((r) => <li key={r.id}>{order.currency} {r.amount} · {t[`refund_${r.status}`]}{r.destination ? ` · ${t[`refund_to_${r.destination}`] || r.destination}` : ''} · {r.reason}</li>)}</ul>
                        </div>
                    )}
                    <Message order={order} t={t} />
                    <details>
                        <summary className="cursor-pointer text-gray-600">{t.history}</summary>
                        <ul className="mt-1">{order.events.map((e, idx) => <li key={idx}>{e.at} · {t[`event_${e.type}`] || e.type}{e.note ? ` — ${e.note}` : ''}</li>)}</ul>
                    </details>
                </div>
            )}
        </li>
    );
}

export default function VendorOrders({ t, vendor, orders, counts, filters }) {
    const { flash = {}, errors } = usePage().props;
    const [open, setOpen] = useState(orders.length === 1 ? orders[0].id : null);
    const [q, setQ] = useState(filters.q || '');
    const total = Object.entries(counts).filter(([k]) => k !== 'returns').reduce((sum, [, n]) => sum + n, 0);

    return (
        <AppShell title={t.orders_title}>
            <FormErrors errors={errors} className="mb-4" />
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="flash-success">{flash.success}</p>}
            <header className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold" data-testid="orders-heading">{t.orders_title} · {vendor.name}</h1>
                    <a href="/vendor" className="text-sm text-blue-700 underline">{t.portal_title}</a>
                </div>
                <form className="flex gap-2" onSubmit={(e) => { e.preventDefault(); router.get('/vendor/orders', { status: filters.status || undefined, q: q || undefined }); }}>
                    <input className="form-input" placeholder={t.search_orders} value={q} onChange={(e) => setQ(e.target.value)} />
                    <button type="submit" className="btn-secondary">{t.search}</button>
                    <a href="/vendor/orders/export" className="btn-secondary" data-testid="export-vendor-orders">{t.export_csv}</a>
                </form>
            </header>
            <nav className="mb-4 flex flex-wrap gap-2" data-testid="order-tabs">
                {TABS.map((s) => {
                    const n = s === '' ? total : counts[s] || 0;
                    if (s !== '' && n === 0 && filters.status !== s) {
                        return null;
                    }

                    return (
                        <a key={s || 'all'} href={s ? `/vendor/orders?status=${s}` : '/vendor/orders'} data-testid={`tab-${s || 'all'}`} className={`rounded-full border px-3 py-1 text-sm ${(filters.status || '') === s ? 'bg-gray-900 text-white' : 'bg-white'}`}>
                            {s === '' ? t.all_orders : s === 'returns' ? t.returns_heading : s === 'pending_payment' ? t.to_confirm : t[`status_${s}`]} ({n})
                        </a>
                    );
                })}
            </nav>
            {orders.length === 0 ? (
                <p className="rounded border bg-white p-4 text-gray-600">{t.no_orders_vendor}</p>
            ) : (
                <ul className="space-y-2">
                    {orders.map((o) => <OrderCard key={o.id} order={o} t={t} open={open === o.id} onToggle={() => setOpen(open === o.id ? null : o.id)} />)}
                </ul>
            )}
        </AppShell>
    );
}
