import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * COMMERCE_PARITY_PLAN P6a: Akuru packs and delivers for the shops that
 * choose it. The office works those orders here (the shop sees them
 * read-only), records the stock a shop hands over, and sets the charges.
 */
function Step({ order, to, t }) {
    const form = useForm({ to, carrier: '', tracking_note: '', cash_received: false });
    const label = to === 'delivered' && order.delivery_kind?.startsWith('collect') ? t.step_collected : t[`step_${to}`];

    return (
        <form className="flex flex-wrap items-center gap-2" onSubmit={(e) => { e.preventDefault(); form.post(`/admin/bookshop/akuru/orders/${order.id}`, { preserveScroll: true }); }}>
            {to === 'dispatched' && (
                <>
                    <input className="form-input w-36 text-sm" placeholder={t.carrier} value={form.data.carrier} onChange={(e) => form.setData('carrier', e.target.value)} />
                    <input className="form-input w-44 text-sm" placeholder={t.tracking_note} value={form.data.tracking_note} onChange={(e) => form.setData('tracking_note', e.target.value)} />
                </>
            )}
            {to === 'delivered' && order.status && (
                <label className="flex items-center gap-1 text-xs"><input type="checkbox" checked={form.data.cash_received} onChange={(e) => form.setData('cash_received', e.target.checked)} /> {t.cash_received}</label>
            )}
            <button type="submit" className="btn-primary text-sm" disabled={form.processing} data-testid={`akuru-step-${order.id}-${to}`}>{label}</button>
            {form.errors.status && <span className="w-full text-xs text-red-700">{form.errors.status}</span>}
        </form>
    );
}

/** P6b: who delivers an order Akuru's courier carries. */
function AssignDriver({ order, drivers, t }) {
    const form = useForm({ driver_id: order.delivery_by_driver?.driver_id || '' });
    const picked = order.delivery_by_driver?.picked_up_at;

    return (
        <form className="flex flex-wrap items-center gap-2" onSubmit={(e) => { e.preventDefault(); form.post(`/admin/bookshop/akuru/orders/${order.id}/driver`, { preserveScroll: true }); }}>
            <select className="form-input text-sm" value={form.data.driver_id} onChange={(e) => form.setData('driver_id', e.target.value)} disabled={Boolean(picked)} data-testid={`driver-select-${order.id}`}>
                <option value="">{t.driver_assign}</option>
                {drivers.filter((d) => d.active).map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
            </select>
            {!picked && <button type="submit" className="btn-secondary text-sm" disabled={form.processing || !form.data.driver_id} data-testid={`driver-assign-${order.id}`}>{t.driver_assign_button}</button>}
            {form.errors.driver && <span className="w-full text-xs text-red-700">{form.errors.driver}</span>}
        </form>
    );
}

function Drivers({ drivers, added, t }) {
    const form = useForm({ email: '', name: '', phone: '' });

    return (
        <section className="mb-8 rounded-lg border bg-white p-4" data-testid="akuru-drivers">
            <h2 className="mb-2 text-lg font-semibold">{t.drivers}</h2>
            {added && (
                <p className="mb-3 rounded bg-amber-50 p-2 text-sm" data-testid="driver-added">{added.temporary_password ? (t.driver_password_once || '').replace(':name', added.name).replace(':email', added.email).replace(':password', added.temporary_password) : (t.driver_existing || '').replace(':name', added.name)}</p>
            )}
            <ul className="mb-3 divide-y text-sm">
                {drivers.map((d) => (
                    <li key={d.id} className="flex flex-wrap items-center justify-between gap-2 py-1" data-testid={`driver-${d.id}`}>
                        <span>{d.name}{d.phone ? ` · ${d.phone}` : ''} · {(t.driver_open_count || '').replace(':count', d.open)}{!d.active && <span className="ms-1 text-gray-500">({t.driver_off})</span>}</span>
                        <button type="button" className="text-blue-700 underline" onClick={() => router.post(`/admin/bookshop/akuru/drivers/${d.id}`, { active: d.active ? 0 : 1 }, { preserveScroll: true })} data-testid={`driver-toggle-${d.id}`}>{d.active ? t.driver_switch_off : t.driver_switch_on}</button>
                    </li>
                ))}
            </ul>
            <form className="flex flex-wrap items-end gap-2" onSubmit={(e) => { e.preventDefault(); form.post('/admin/bookshop/akuru/drivers', { preserveScroll: true, onSuccess: () => form.reset() }); }}>
                <label className="text-sm">{t.driver_name}<input className="form-input w-40" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required data-testid="driver-name" /></label>
                <label className="text-sm">{t.driver_email}<input type="email" className="form-input w-56" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required data-testid="driver-email" /></label>
                <label className="text-sm">{t.driver_phone}<input type="tel" className="form-input w-32" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} data-testid="driver-phone" /></label>
                <button type="submit" className="btn-primary" disabled={form.processing} data-testid="driver-add">{t.driver_add}</button>
            </form>
            {(form.errors.email || form.errors.name || form.errors.phone) && <p className="mt-1 text-xs text-red-700">{form.errors.email || form.errors.name || form.errors.phone}</p>}
        </section>
    );
}

function StockForm({ product, t }) {
    const form = useForm({ direction: 'in', quantity: '', note: '' });

    return (
        <form className="flex flex-wrap items-center gap-2" onSubmit={(e) => { e.preventDefault(); form.post(`/admin/bookshop/akuru/stock/${product.id}`, { preserveScroll: true, onSuccess: () => form.reset('quantity', 'note') }); }}>
            <select className="form-input text-sm" value={form.data.direction} onChange={(e) => form.setData('direction', e.target.value)} data-testid={`akuru-direction-${product.id}`}>
                <option value="in">{t.akuru_stock_in}</option>
                <option value="out">{t.akuru_stock_out}</option>
            </select>
            <input className="form-input w-20 text-sm" type="number" min="1" value={form.data.quantity} onChange={(e) => form.setData('quantity', e.target.value)} required data-testid={`akuru-quantity-${product.id}`} />
            <button type="submit" className="btn-secondary text-sm" disabled={form.processing} data-testid={`akuru-record-${product.id}`}>{t.akuru_stock_record}</button>
            {form.errors.quantity && <span className="w-full text-xs text-red-700">{form.errors.quantity}</span>}
        </form>
    );
}

function Charges({ settings, t }) {
    const form = useForm({ handling_fee: settings.handling_fee, delivery_fee: settings.delivery_fee, delivery_free_over: settings.delivery_free_over ?? '' });

    return (
        <section className="mb-8 rounded-lg border bg-white p-4" data-testid="akuru-charges">
            <h2 className="mb-1 text-lg font-semibold">{t.akuru_charges}</h2>
            <p className="mb-3 text-sm text-gray-600">{t.akuru_charges_hint}</p>
            <form className="flex flex-wrap items-end gap-3" onSubmit={(e) => { e.preventDefault(); form.post('/admin/bookshop/akuru/settings', { preserveScroll: true }); }}>
                <label className="text-sm">{t.akuru_handling_fee}<input className="form-input w-32" type="number" step="0.01" min="0" value={form.data.handling_fee} onChange={(e) => form.setData('handling_fee', e.target.value)} data-testid="akuru-handling-fee" /></label>
                <label className="text-sm">{t.akuru_delivery_fee}<input className="form-input w-32" type="number" step="0.01" min="0" value={form.data.delivery_fee} onChange={(e) => form.setData('delivery_fee', e.target.value)} data-testid="akuru-delivery-fee" /></label>
                <label className="text-sm">{t.akuru_free_over}<input className="form-input w-32" type="number" step="0.01" min="0" value={form.data.delivery_free_over} onChange={(e) => form.setData('delivery_free_over', e.target.value)} /></label>
                <button type="submit" className="btn-primary" disabled={form.processing} data-testid="akuru-save-charges">{t.save}</button>
            </form>
            <FormErrors errors={form.errors} />
        </section>
    );
}

export default function Akuru({ t = {}, orders = [], shops = [], settings, drivers = [], driver_added = null }) {
    const { errors } = usePage().props;

    return (
        <AppShell title={t.akuru_page_title}>
            <FormErrors errors={errors} className="mb-4" />
            <p className="mb-4 text-sm text-gray-600">{t.akuru_page_intro} <a href="/admin/bookshop" className="text-blue-700 underline">{t.akuru_back}</a></p>

            <section className="mb-8" data-testid="akuru-orders">
                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-lg font-semibold">{t.akuru_orders} ({orders.length})</h2>
                    <a href="/admin/bookshop/akuru/export" className="btn-secondary" data-testid="export-akuru">{t.export_csv}</a>
                </div>
                {orders.length === 0 ? <p className="rounded border bg-white p-3 text-sm text-gray-600">{t.akuru_orders_empty}</p> : (
                    <ul className="divide-y rounded border bg-white">
                        {orders.map((o) => (
                            <li key={o.id} className="grid gap-2 p-3 md:grid-cols-[1fr_auto]" data-testid={`akuru-order-${o.number}`} data-status={o.status}>
                                <div className="min-w-0 text-sm">
                                    <p className="font-semibold">{o.number} · {o.vendor} · <span className="rounded bg-gray-100 px-1">{t[`status_${o.status}`] || o.status}</span></p>
                                    <p className="text-gray-600">{o.paid_at} · {o.delivery}</p>
                                    <p>{o.recipient}{o.address ? ` — ${o.address}` : ''}</p>
                                    <ul className="list-disc ps-5">{o.items.map((i, k) => <li key={k}>{i.quantity} × {i.title}</li>)}</ul>
                                </div>
                                <div className="flex flex-col gap-2">
                                    {o.delivery_kind === 'akuru_courier' && <AssignDriver order={o} drivers={drivers} t={t} />}
                                    {o.delivery_by_driver && <p className="text-xs text-gray-600" data-testid={`order-driver-${o.id}`}>{o.delivery_by_driver.driver}{o.delivery_by_driver.picked_up_at ? ` · ${t.out_for_delivery} ${o.delivery_by_driver.picked_up_at}` : ''}{o.delivery_by_driver.proof_url && <> · <a href={o.delivery_by_driver.proof_url} target="_blank" rel="noreferrer" className="underline">{t.proof_photo}</a></>}</p>}
                                    {o.next.map((to) => <Step key={to} order={o} to={to} t={t} />)}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <Drivers drivers={drivers} added={driver_added} t={t} />

            <section className="mb-8" data-testid="akuru-shops">
                <h2 className="mb-2 text-lg font-semibold">{t.akuru_shops}</h2>
                {shops.length === 0 && <p className="rounded border bg-white p-3 text-sm text-gray-600">{t.akuru_shops_empty}</p>}
                {shops.map((s) => (
                    <div key={s.id} className="mb-4 rounded border bg-white p-3" data-testid={`akuru-shop-${s.id}`}>
                        <p className="mb-2 text-sm"><span className="font-semibold">{s.name}</span> · {t.akuru_fulfilment}: {s.fulfilment === 'akuru' ? t.akuru_by_akuru : t.akuru_by_shop} · {t.akuru_delivery_by}: {s.delivery_by === 'akuru' ? t.akuru_by_akuru : t.akuru_by_shop} · {t.akuru_handling_fee}: MVR {s.handling_fee}</p>
                        {s.products.length > 0 && (
                            <div className="overflow-x-auto">
                                <table className="table-stack min-w-full text-sm">
                                    <thead className="bg-gray-50"><tr><th className="p-2 text-start">{t.product_title}</th><th className="p-2 text-end">{t.stock}</th><th className="p-2 text-end">{t.akuru_at_akuru}</th><th className="p-2" /></tr></thead>
                                    <tbody>
                                        {s.products.map((p) => (
                                            <tr key={p.id} className="border-t" data-testid={`akuru-product-${p.id}`}>
                                                <td className="p-2" data-label={t.product_title}>{p.title}{p.sku ? <span className="ms-1 text-xs text-gray-500">{p.sku}</span> : null}</td>
                                                <td className="p-2 sm:text-end" data-label={t.stock}>{p.stock}</td>
                                                <td className="p-2 sm:text-end" data-label={t.akuru_at_akuru} data-testid={`akuru-at-${p.id}`}>{p.at_akuru}</td>
                                                <td className="table-actions p-2"><StockForm product={p} t={t} /></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                ))}
            </section>

            <Charges settings={settings} t={t} />
        </AppShell>
    );
}
