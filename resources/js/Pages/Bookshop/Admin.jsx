import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * BOOKSHOP_PLAN slice B1a — the office's side of the Akuru Bookstore:
 * invite a vendor with its owner, edit or suspend it, and keep the shared
 * categories and brands. B2 adds the bank-transfer slips to confirm and
 * the orders list.
 */

function InviteVendor({ t, defaultRate }) {
    const form = useForm({ name: '', slug: '', code: '', tagline: '', commission_rate: '', contact_email: '', contact_phone: '', owner_name: '', owner_email: '', owner_phone: '' });
    const set = (name) => (e) => form.setData(name, e.target.value);

    return (
        <form
            className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            data-testid="invite-vendor"
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/admin/bookshop/vendors', { preserveScroll: true, onSuccess: () => form.reset() });
            }}
        >
            <h2 className="text-lg font-semibold md:col-span-3">{t.invite_vendor}</h2>
            <p className="text-sm text-gray-600 md:col-span-3">{t.invite_vendor_intro}</p>
            <label className="text-sm">{t.vendor_name}<input className="form-input w-full" value={form.data.name} onChange={set('name')} data-testid="vendor-name" required /></label>
            <label className="text-sm">{t.slug_label}<input className="form-input w-full" value={form.data.slug} onChange={set('slug')} data-testid="vendor-slug" /><span className="block text-xs text-gray-500">{t.slug_hint}</span></label>
            <label className="text-sm">{t.code_label}<input className="form-input w-full" maxLength={3} value={form.data.code} onChange={set('code')} data-testid="vendor-code" /><span className="block text-xs text-gray-500">{t.code_hint}</span></label>
            <label className="text-sm">{t.tagline}<input className="form-input w-full" value={form.data.tagline} onChange={set('tagline')} /></label>
            <label className="text-sm">{t.commission_rate}<input className="form-input w-full" type="number" step="0.01" min="0" max="100" value={form.data.commission_rate} onChange={set('commission_rate')} /><span className="block text-xs text-gray-500">{t.commission_default.replace(':rate', defaultRate)}</span></label>
            <label className="text-sm">{t.contact_phone}<input className="form-input w-full" value={form.data.contact_phone} onChange={set('contact_phone')} /></label>
            <label className="text-sm">{t.owner_name}<input className="form-input w-full" value={form.data.owner_name} onChange={set('owner_name')} data-testid="owner-name" required /></label>
            <label className="text-sm">{t.owner_email}<input className="form-input w-full" type="email" value={form.data.owner_email} onChange={set('owner_email')} data-testid="owner-email" required /></label>
            <label className="text-sm">{t.owner_phone}<input className="form-input w-full" value={form.data.owner_phone} onChange={set('owner_phone')} /></label>
            <FormErrors errors={form.errors} className="md:col-span-3" />
            <div className="md:col-span-3"><button type="submit" className="btn-primary" disabled={form.processing} data-testid="create-vendor">{t.create_vendor}</button></div>
        </form>
    );
}

function InviteCard({ invite, t, signInUrl }) {
    return (
        <div className="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4" data-testid="vendor-invite">
            <p className="font-semibold">{t.invite_ready.replace(':vendor', invite.vendor).replace(':email', invite.email)}</p>
            {invite.existing_account ? (
                <p className="mt-1">{t.invite_existing}</p>
            ) : (
                <>
                    <p className="mt-1">{t.invite_new}</p>
                    <p className="mt-2">{t.sign_in_at} <span className="font-mono" data-testid="invite-sign-in-url">{signInUrl}</span></p>
                    <p className="mt-1">
                        {t.one_time_password}: <span className="font-mono text-lg" data-testid="invite-password">{invite.temporary_password}</span>
                        <span className="ms-2 text-sm text-amber-800">{t.shown_once}</span>
                    </p>
                </>
            )}
        </div>
    );
}

function VendorEditor({ vendor, t, onDone }) {
    const form = useForm({
        name: vendor.name, tagline: vendor.tagline || '', legal_name: vendor.legal_name || '', tin: vendor.tin || '',
        gst_registered: Boolean(vendor.gst_registered), status: vendor.status, commission_rate: vendor.commission_rate || '',
        contact_email: vendor.contact_email || '', contact_phone: vendor.contact_phone || '', address: vendor.address || '',
        opening_hours: vendor.opening_hours || '', office_notes: vendor.office_notes || '',
        badges: vendor.badges || [],
    });
    const set = (name) => (e) => form.setData(name, e.target.type === 'checkbox' ? e.target.checked : e.target.value);
    const toggleBadge = (badge) => (e) => form.setData('badges', e.target.checked ? [...form.data.badges, badge] : form.data.badges.filter((b) => b !== badge));

    return (
        <form
            className="grid gap-2 bg-gray-50 p-3 md:grid-cols-3"
            data-testid={`vendor-editor-${vendor.slug}`}
            onSubmit={(e) => {
                e.preventDefault();
                form.put(`/admin/bookshop/vendors/${vendor.id}`, { preserveScroll: true, onSuccess: onDone });
            }}
        >
            <label className="text-sm">{t.vendor_name}<input className="form-input w-full" value={form.data.name} onChange={set('name')} /></label>
            <label className="text-sm">{t.status}
                <select className="form-input w-full" value={form.data.status} onChange={set('status')} data-testid="vendor-status">
                    <option value="active">{t.active}</option>
                    <option value="suspended">{t.suspended}</option>
                </select>
            </label>
            <label className="text-sm">{t.commission_rate}<input className="form-input w-full" type="number" step="0.01" min="0" max="100" value={form.data.commission_rate} onChange={set('commission_rate')} /></label>
            <label className="text-sm">{t.tagline}<input className="form-input w-full" value={form.data.tagline} onChange={set('tagline')} /></label>
            <label className="text-sm">{t.legal_name}<input className="form-input w-full" value={form.data.legal_name} onChange={set('legal_name')} /></label>
            <label className="text-sm">{t.tin}<input className="form-input w-full" value={form.data.tin} onChange={set('tin')} /></label>
            <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.gst_registered} onChange={set('gst_registered')} /> {t.gst_registered}</label>
            {/* B4 (plan §6.1): the office's badges; "Akuru partner" also unlocks Akuru's own palette (decision 10). */}
            <fieldset className="flex flex-wrap items-center gap-3 text-sm md:col-span-2">
                <legend className="sr-only">{t.badges}</legend>
                <span className="font-medium">{t.badges}:</span>
                {['verified', 'akuru_partner'].map((badge) => (
                    <label key={badge} className="flex items-center gap-1"><input type="checkbox" checked={form.data.badges.includes(badge)} onChange={toggleBadge(badge)} data-testid={`badge-${badge}`} /> {t[`badge_${badge}`]}</label>
                ))}
                {vendor.storefront_published_at && <span className="text-xs text-gray-500">{t.storefront_published_on.replace(':date', vendor.storefront_published_at)}</span>}
            </fieldset>
            <label className="text-sm">{t.contact_email}<input className="form-input w-full" type="email" value={form.data.contact_email} onChange={set('contact_email')} /></label>
            <label className="text-sm">{t.contact_phone}<input className="form-input w-full" value={form.data.contact_phone} onChange={set('contact_phone')} /></label>
            <label className="text-sm md:col-span-3">{t.address}<textarea className="form-input w-full" rows={2} value={form.data.address} onChange={set('address')} /></label>
            <label className="text-sm md:col-span-3">{t.opening_hours}<textarea className="form-input w-full" rows={2} value={form.data.opening_hours} onChange={set('opening_hours')} /></label>
            <label className="text-sm md:col-span-3">{t.office_notes}<textarea className="form-input w-full" rows={2} value={form.data.office_notes} onChange={set('office_notes')} /></label>
            <FormErrors errors={form.errors} className="md:col-span-3" />
            <div className="flex gap-3 md:col-span-3">
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.save}</button>
                <button type="button" className="text-sm underline" onClick={onDone}>{t.cancel}</button>
            </div>
        </form>
    );
}

function VendorTable({ vendors, t }) {
    const [editing, setEditing] = useState(null);

    if (vendors.length === 0) {
        return <p className="rounded border bg-white p-4 text-gray-600">{t.no_vendors}</p>;
    }

    return (
        <table className="w-full rounded border bg-white text-sm" data-testid="vendor-table">
            <thead className="bg-gray-50">
                <tr>
                    <th className="p-2 text-start">{t.vendor_name}</th>
                    <th className="p-2 text-start">{t.owner}</th>
                    <th className="p-2 text-start">{t.agreement}</th>
                    <th className="p-2 text-end">{t.commission_rate}</th>
                    <th className="p-2 text-end">{t.products}</th>
                    <th className="p-2 text-start">{t.status}</th>
                    <th className="p-2" />
                </tr>
            </thead>
            <tbody>
                {vendors.map((v) => (
                    <FragmentRow key={v.id} vendor={v} t={t} editing={editing === v.id} onEdit={() => setEditing(editing === v.id ? null : v.id)} onDone={() => setEditing(null)} />
                ))}
            </tbody>
        </table>
    );
}

function FragmentRow({ vendor: v, t, editing, onEdit, onDone }) {
    const owner = v.owners[0];

    return (
        <>
            <tr className="border-t" data-testid={`vendor-row-${v.slug}`}>
                <td className="p-2"><span className="font-medium">{v.name}</span><a href={`/shop/${v.slug}`} target="_blank" rel="noreferrer" className="block text-xs text-blue-700 underline">/shop/{v.slug}</a><span className="block text-xs text-gray-500">{v.code}</span></td>
                <td className="p-2">{owner ? <>{owner.name}<span className="block text-xs text-gray-500">{owner.email}</span></> : t.none}</td>
                <td className="p-2">{owner?.agreement_accepted_at ? t.accepted : t.not_yet}</td>
                <td className="p-2 text-end">{v.effective_commission_rate}%</td>
                <td className="p-2 text-end">{v.active_products_count} / {v.products_count}</td>
                <td className="p-2">{v.status === 'active' ? t.active : t.suspended}</td>
                <td className="p-2 text-end"><button type="button" className="text-blue-700 underline" onClick={onEdit}>{t.edit}</button></td>
            </tr>
            {editing && (
                <tr><td colSpan={7}><VendorEditor vendor={v} t={t} onDone={onDone} /></td></tr>
            )}
        </>
    );
}

function Catalogue({ catalogue, t }) {
    const category = useForm({ name: '', name_dv: '', name_ar: '', parent_id: '' });
    const brand = useForm({ name: '' });

    return (
        <section className="mt-8 grid gap-6 md:grid-cols-2">
            <div>
                <h2 className="text-lg font-semibold">{t.categories}</h2>
                <p className="mb-2 text-sm text-gray-600">{t.catalogue_intro}</p>
                <ul className="mb-3 rounded border bg-white text-sm" data-testid="category-list">
                    {catalogue.categories.map((c) => <li key={c.id} className="border-t p-2 first:border-t-0">{c.name}{c.name_dv ? ` · ${c.name_dv}` : ''}{c.name_ar ? ` · ${c.name_ar}` : ''}</li>)}
                </ul>
                <form
                    className="grid gap-2 md:grid-cols-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        category.post('/admin/bookshop/categories', { preserveScroll: true, onSuccess: () => category.reset() });
                    }}
                >
                    <input className="form-input" placeholder={t.name} value={category.data.name} onChange={(e) => category.setData('name', e.target.value)} data-testid="category-name" required />
                    <select className="form-input" value={category.data.parent_id} onChange={(e) => category.setData('parent_id', e.target.value)} aria-label={t.parent_category}>
                        <option value="">{t.parent_category}: {t.none}</option>
                        {catalogue.categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                    <input className="form-input" dir="rtl" placeholder={t.name_dv} value={category.data.name_dv} onChange={(e) => category.setData('name_dv', e.target.value)} />
                    <input className="form-input" dir="rtl" placeholder={t.name_ar} value={category.data.name_ar} onChange={(e) => category.setData('name_ar', e.target.value)} />
                    <FormErrors errors={category.errors} className="md:col-span-2" />
                    <button type="submit" className="btn-secondary md:col-span-2" disabled={category.processing}>{t.add_category}</button>
                </form>
            </div>
            <div>
                <h2 className="text-lg font-semibold">{t.brands}</h2>
                <p className="mb-2 text-sm text-gray-600">{t.catalogue_intro}</p>
                <ul className="mb-3 rounded border bg-white text-sm" data-testid="brand-list">
                    {catalogue.brands.map((b) => <li key={b.id} className="border-t p-2 first:border-t-0">{b.name}</li>)}
                </ul>
                <form
                    className="flex gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        brand.post('/admin/bookshop/brands', { preserveScroll: true, onSuccess: () => brand.reset() });
                    }}
                >
                    <input className="form-input flex-1" placeholder={t.name} value={brand.data.name} onChange={(e) => brand.setData('name', e.target.value)} required />
                    <button type="submit" className="btn-secondary" disabled={brand.processing}>{t.add_brand}</button>
                    <FormErrors errors={brand.errors} />
                </form>
            </div>
        </section>
    );
}

/** B2 (decision 7): the office reads a slip against the bank and decides. */
function SlipRow({ slip, t }) {
    const [note, setNote] = useState('');
    const decide = (decision) => router.post(`/admin/bookshop/slips/${slip.id}/decide`, { decision, note }, { preserveScroll: true });

    return (
        <tr className="border-t" data-testid={`slip-row-${slip.id}`} data-slip-status={slip.status}>
            <td className="p-2"><span className="font-mono">{slip.checkout_number}</span><span className="block text-xs text-gray-500">{t[`status_${slip.checkout_status}`] || slip.checkout_status}</span></td>
            <td className="p-2">{slip.customer}<span className="block text-xs text-gray-500">{slip.customer_email}</span></td>
            <td className="p-2 text-end">{slip.currency} {slip.total}</td>
            <td className="p-2">{slip.reference || t.none}{slip.note && <span className="block text-xs text-gray-500">{slip.note}</span>}</td>
            <td className="p-2">{slip.uploaded_at}<a href={`/shop/slips/${slip.id}`} target="_blank" rel="noreferrer" className="block text-xs text-blue-700 underline">{t.view_slip}</a></td>
            <td className="p-2">
                {slip.status === 'waiting' ? (
                    <div className="flex flex-wrap items-center gap-2">
                        <input className="form-input w-40" placeholder={t.decision_note} value={note} onChange={(e) => setNote(e.target.value)} data-testid={`slip-note-${slip.id}`} />
                        <button type="button" className="btn-primary" onClick={() => decide('confirm')} data-testid={`confirm-slip-${slip.id}`}>{t.confirm}</button>
                        <button type="button" className="text-red-700 underline" onClick={() => decide('reject')} data-testid={`reject-slip-${slip.id}`}>{t.reject}</button>
                    </div>
                ) : (
                    <span>{t[`slip_status_${slip.status}`] || slip.status}{slip.decision_note && <span className="block text-xs text-gray-500">{slip.decision_note}</span>}</span>
                )}
            </td>
        </tr>
    );
}

function Slips({ slips, t }) {
    return (
        <section className="mt-8" data-testid="bank-slips">
            <h2 className="mb-2 text-lg font-semibold">{t.bank_slips}</h2>
            {slips.length === 0 ? (
                <p className="rounded border bg-white p-4 text-gray-600">{t.no_slips}</p>
            ) : (
                <table className="w-full rounded border bg-white text-sm">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="p-2 text-start">{t.checkout_col}</th>
                            <th className="p-2 text-start">{t.customer}</th>
                            <th className="p-2 text-end">{t.total}</th>
                            <th className="p-2 text-start">{t.slip_reference}</th>
                            <th className="p-2 text-start">{t.uploaded}</th>
                            <th className="p-2 text-start">{t.status}</th>
                        </tr>
                    </thead>
                    <tbody>{slips.map((s) => <SlipRow key={s.id} slip={s} t={t} />)}</tbody>
                </table>
            )}
        </section>
    );
}

function Orders({ orders, t }) {
    return (
        <section className="mt-8" data-testid="office-orders">
            <div className="mb-2 flex items-center justify-between">
                <h2 className="text-lg font-semibold">{t.orders}</h2>
                <a href="/admin/bookshop/orders/export" className="btn-secondary" data-testid="export-orders">{t.export_csv}</a>
            </div>
            {orders.length === 0 ? (
                <p className="rounded border bg-white p-4 text-gray-600">{t.no_orders_office}</p>
            ) : (
                <table className="w-full rounded border bg-white text-sm">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="p-2 text-start">{t.order_number}</th>
                            <th className="p-2 text-start">{t.shop_col}</th>
                            <th className="p-2 text-start">{t.customer}</th>
                            <th className="p-2 text-start">{t.delivery_heading}</th>
                            <th className="p-2 text-end">{t.total}</th>
                            <th className="p-2 text-start">{t.status}</th>
                            <th className="p-2 text-start">{t.placed}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {orders.map((o) => (
                            <tr key={o.id} className="border-t" data-testid={`order-row-${o.number}`}>
                                <td className="p-2 font-mono">{o.number}</td>
                                <td className="p-2">{o.vendor}</td>
                                <td className="p-2">{o.customer}<span className="block text-xs text-gray-500">{o.island}</span></td>
                                <td className="p-2">{o.delivery}</td>
                                <td className="p-2 text-end">{o.currency} {o.total}</td>
                                <td className="p-2">{t[`status_${o.status}`] || o.status}</td>
                                <td className="p-2">{o.placed_at}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </section>
    );
}

/**
 * B3 (audit finding 6): card refunds a shop has accepted, waiting for the
 * office to return the money through BML's merchant portal and record it —
 * or to credit the wallet instead where the customer asks.
 */
function Refunds({ refunds, t }) {
    const [notes, setNotes] = useState({});
    const pending = refunds.filter((r) => r.status === 'pending');
    const process = (r, destination) => router.post(`/admin/bookshop/refunds/${r.id}`, { destination, note: notes[r.id] || '' }, { preserveScroll: true });

    return (
        <section className="mt-8" data-testid="office-refunds">
            <div className="mb-2 flex items-center justify-between">
                <h2 className="text-lg font-semibold">{t.refunds_heading} {pending.length > 0 && <span className="ms-2 rounded bg-amber-100 px-2 text-sm text-amber-800">{pending.length}</span>}</h2>
                <a href="/admin/bookshop/refunds/export" className="btn-secondary" data-testid="export-refunds">{t.export_csv}</a>
            </div>
            {refunds.length === 0 ? (
                <p className="rounded border bg-white p-4 text-gray-600">{t.no_refunds}</p>
            ) : (
                <table className="w-full rounded border bg-white text-sm">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="p-2 text-start">{t.order_number}</th>
                            <th className="p-2 text-start">{t.customer}</th>
                            <th className="p-2 text-end">{t.total}</th>
                            <th className="p-2 text-start">{t.payment_method}</th>
                            <th className="p-2 text-start">{t.status}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {refunds.map((r) => (
                            <tr key={r.id} className="border-t" data-testid={`refund-row-${r.id}`} data-refund-status={r.status}>
                                <td className="p-2"><span className="font-mono">{r.order_number}</span><span className="block text-xs text-gray-500">{r.vendor} · {r.reason}</span></td>
                                <td className="p-2">{r.customer}<span className="block text-xs text-gray-500">{r.customer_email}</span></td>
                                <td className="p-2 text-end">{r.currency} {r.amount}</td>
                                <td className="p-2">{t[`pay_${r.paid_with}`] || r.paid_with}{r.bml_reference ? <span className="block text-xs text-gray-500">{t.payment_ref}: {r.bml_reference}</span> : null}</td>
                                <td className="p-2">
                                    {r.status === 'pending' ? (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <input className="form-input w-40" placeholder={t.note} value={notes[r.id] || ''} onChange={(e) => setNotes({ ...notes, [r.id]: e.target.value })} />
                                            <button type="button" className="btn-primary" onClick={() => process(r, 'manual')} data-testid={`refund-card-${r.id}`}>{t.refunded_to_card}</button>
                                            <button type="button" className="text-blue-700 underline" onClick={() => process(r, 'wallet')} data-testid={`refund-wallet-${r.id}`}>{t.refund_to_wallet_instead}</button>
                                        </div>
                                    ) : (
                                        <span>{t.refund_done} · {t[`refund_to_${r.destination}`] || r.destination} · {r.processed_at}</span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </section>
    );
}

export default function Admin({ t, vendors, catalogue, slips = [], orders = [], refunds = [], default_commission_rate, sign_in_url }) {
    const { flash = {}, errors } = usePage().props;

    return (
        <AppShell title={t.office_title}>
            <FormErrors errors={errors} className="mb-4" />
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700">{flash.success}</p>}
            {flash.vendor_invite && <InviteCard invite={flash.vendor_invite} t={t} signInUrl={sign_in_url} />}

            {slips.some((s) => s.status === 'waiting') && <Slips slips={slips} t={t} />}
            {refunds.some((r) => r.status === 'pending') && <Refunds refunds={refunds} t={t} />}

            <InviteVendor t={t} defaultRate={default_commission_rate} />

            <div className="mb-2 flex items-center justify-between">
                <h2 className="text-lg font-semibold">{t.vendors}</h2>
                <a href="/admin/bookshop/vendors/export" className="btn-secondary" data-testid="export-vendors">{t.export_csv}</a>
            </div>
            <VendorTable vendors={vendors} t={t} />

            {!slips.some((s) => s.status === 'waiting') && <Slips slips={slips} t={t} />}
            {!refunds.some((r) => r.status === 'pending') && <Refunds refunds={refunds} t={t} />}
            <Orders orders={orders} t={t} />

            <Catalogue catalogue={catalogue} t={t} />
        </AppShell>
    );
}
