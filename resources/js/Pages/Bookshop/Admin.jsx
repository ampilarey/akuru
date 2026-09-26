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

/** B5 (plan §6.6): the office moderates a storefront — require changes, take it down, lift, lock section types. */
function StorefrontModeration({ vendor, t, sectionTypes }) {
    const sf = vendor.storefront || { exists: false, locked_types: [] };
    const [note, setNote] = useState('');
    const [locked, setLocked] = useState(sf.locked_types || []);
    const act = (action, extra = {}) => router.post(`/admin/bookshop/vendors/${vendor.id}/storefront`, { action, note, locked_types: locked, ...extra }, { preserveScroll: true, onSuccess: () => setNote('') });

    return (
        <fieldset className="rounded border border-gray-300 p-3 text-sm md:col-span-3" data-testid={`moderation-${vendor.slug}`}>
            <legend className="px-1 font-medium">{t.storefront_moderation}</legend>
            <p className="mb-2 text-xs text-gray-600">
                {sf.held_at ? <span className="font-semibold text-red-700" data-testid="moderation-state">{t.storefront_taken_down.replace(':date', sf.held_at)}</span>
                    : sf.published_at ? <span data-testid="moderation-state">{t.storefront_published_on.replace(':date', sf.published_at)}</span>
                        : <span data-testid="moderation-state">{t.not_published_yet_short}</span>}
                {sf.draft_dirty && ` · ${t.draft_differs}`}
                {sf.note && !sf.held_at && <span className="block text-amber-800">{t.changes_required} {sf.note}</span>}
                {' · '}
                <a href={`/shop/${vendor.slug}`} target="_blank" rel="noreferrer" className="text-blue-700 underline">{t.published_version}</a>
                {' · '}
                <a href={`/admin/bookshop/storefronts/${vendor.slug}/preview`} target="_blank" rel="noreferrer" className="text-blue-700 underline" data-testid="office-preview">{t.draft_version}</a>
            </p>
            <div className="flex flex-wrap items-end gap-2">
                <label className="flex-1 text-sm">{t.moderation_note}<input className="form-input w-full" value={note} onChange={(e) => setNote(e.target.value)} data-testid="moderation-note-input" /></label>
                <button type="button" className="btn-secondary" onClick={() => act('require_changes')} data-testid="require-changes">{t.require_changes}</button>
                {sf.held_at
                    ? <button type="button" className="btn-primary" onClick={() => act('lift')} data-testid="lift-hold">{t.lift_hold}</button>
                    : <button type="button" className="rounded bg-red-700 px-3 py-2 text-white" onClick={() => act('hold')} data-testid="take-down">{t.take_down}</button>}
            </div>
            <div className="mt-2 flex flex-wrap items-center gap-2">
                <span className="font-medium">{t.locked_types}:</span>
                {sectionTypes.map((k) => (
                    <label key={k} className="flex items-center gap-1"><input type="checkbox" checked={locked.includes(k)} onChange={(e) => setLocked(e.target.checked ? [...locked, k] : locked.filter((x) => x !== k))} data-testid={`lock-${k}`} /> {t[`section_${k}`] || k}</label>
                ))}
                <button type="button" className="btn-secondary text-xs" onClick={() => act('lock')} data-testid="save-locks">{t.save_locks}</button>
            </div>
        </fieldset>
    );
}

function VendorEditor({ vendor, t, onDone, sectionTypes = [] }) {
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
            <StorefrontModeration vendor={vendor} t={t} sectionTypes={sectionTypes} />
            <FormErrors errors={form.errors} className="md:col-span-3" />
            <div className="flex gap-3 md:col-span-3">
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.save}</button>
                <button type="button" className="text-sm underline" onClick={onDone}>{t.cancel}</button>
            </div>
        </form>
    );
}

function VendorTable({ vendors, t, sectionTypes }) {
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
                    <FragmentRow key={v.id} vendor={v} t={t} sectionTypes={sectionTypes} editing={editing === v.id} onEdit={() => setEditing(editing === v.id ? null : v.id)} onDone={() => setEditing(null)} />
                ))}
            </tbody>
        </table>
    );
}

function FragmentRow({ vendor: v, t, sectionTypes, editing, onEdit, onDone }) {
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
                <tr><td colSpan={7}><VendorEditor vendor={v} t={t} onDone={onDone} sectionTypes={sectionTypes} /></td></tr>
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

function Orders({ orders, vendors, statuses, t }) {
    // B8: the exports take a shop, a status and dates; per order or per line.
    const [f, setF] = useState({ vendor: '', status: '', from: '', to: '' });
    const query = new URLSearchParams(Object.fromEntries(Object.entries(f).filter(([, v]) => v))).toString();
    const suffix = query ? `?${query}` : '';

    return (
        <section className="mt-8" data-testid="office-orders">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold">{t.orders}</h2>
                <span className="flex flex-wrap items-center gap-2 text-sm" data-testid="office-order-exports">
                    <select className="form-input" value={f.vendor} onChange={(e) => setF({ ...f, vendor: e.target.value })} aria-label={t.shop_col} data-testid="export-vendor">
                        <option value="">{t.all_shops}</option>
                        {vendors.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                    </select>
                    <select className="form-input" value={f.status} onChange={(e) => setF({ ...f, status: e.target.value })} aria-label={t.status}>
                        <option value="">{t.all_statuses}</option>
                        {statuses.map((s) => <option key={s} value={s}>{t[`status_${s}`] || s}</option>)}
                    </select>
                    <input className="form-input" type="date" value={f.from} onChange={(e) => setF({ ...f, from: e.target.value })} aria-label={t.from} />
                    <input className="form-input" type="date" value={f.to} onChange={(e) => setF({ ...f, to: e.target.value })} aria-label={t.to} />
                    <a href={`/admin/bookshop/orders/export${suffix}`} className="btn-secondary" data-testid="export-orders">{t.export_orders_csv}</a>
                    <a href={`/admin/bookshop/orders/lines/export${suffix}`} className="btn-secondary" data-testid="export-order-lines">{t.export_lines_csv}</a>
                </span>
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

/** B6 (plan §7 "Payouts", "Reports"): payout requests with where they go, vendor balances, commission invoices, the tax report. */
function Money({ money, t }) {
    const [forms, setForms] = useState({});
    const [month, setMonth] = useState(money.last_month);
    const field = (id, key) => forms[id]?.[key] || '';
    const set = (id, key) => (e) => setForms({ ...forms, [id]: { ...forms[id], [key]: e.target.value } });
    const decide = (id, decision) => router.post(`/admin/bookshop/payouts/${id}/decide`, { decision, reference: field(id, 'reference'), note: field(id, 'note') }, { preserveScroll: true });
    const c = money.currency;

    return (
        <section className="mt-8" data-testid="office-money">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold">{t.money_title} {money.requests.length > 0 && <span className="ms-2 rounded bg-amber-100 px-2 text-sm text-amber-800">{money.requests.length}</span>}</h2>
                <span className="flex flex-wrap gap-2">
                    <a href="/admin/bookshop/money/payouts/export" className="btn-secondary" data-testid="export-payouts">{t.export_csv}: {t.money_tab_payouts}</a>
                    <a href="/admin/bookshop/money/balances/export" className="btn-secondary">{t.export_csv}: {t.balances}</a>
                    <a href="/admin/bookshop/money/tax-report/export" className="btn-secondary" data-testid="export-tax-report">{t.export_csv}: {t.tax_report}</a>
                </span>
            </div>

            <h3 className="mb-1 font-semibold">{t.payout_requests}</h3>
            {money.requests.length === 0 ? <p className="mb-4 rounded border bg-white p-3 text-sm text-gray-600">{t.no_payout_requests}</p> : (
                <table className="mb-4 w-full rounded border bg-white text-sm" data-testid="payout-requests">
                    <thead className="bg-gray-50"><tr><th className="p-2 text-start">{t.vendor_name}</th><th className="p-2 text-end">{t.amount}</th><th className="p-2 text-start">{t.pay_to}</th><th className="p-2 text-start">{t.decision}</th></tr></thead>
                    <tbody>
                        {money.requests.map((p) => (
                            <tr key={p.id} className="border-t" data-testid={`payout-request-${p.id}`}>
                                <td className="p-2">{p.vendor}<span className="block text-xs text-gray-500">{p.requested_at}</span></td>
                                <td className="p-2 text-end font-semibold">{p.currency} {p.amount}</td>
                                <td className="p-2">{p.bank ? <>{p.bank.bank_name}<span className="block">{p.bank.account_name}</span><span className="block font-mono" data-testid="payout-account">{p.bank.account_number}</span></> : '—'}</td>
                                <td className="p-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <input className="form-input w-40" placeholder={t.payment_ref} value={field(p.id, 'reference')} onChange={set(p.id, 'reference')} data-testid={`payout-reference-${p.id}`} />
                                        <input className="form-input w-40" placeholder={t.note} value={field(p.id, 'note')} onChange={set(p.id, 'note')} data-testid={`payout-note-${p.id}`} />
                                        <button type="button" className="btn-primary" onClick={() => decide(p.id, 'paid')} data-testid={`payout-paid-${p.id}`}>{t.mark_paid}</button>
                                        <button type="button" className="text-red-700 underline" onClick={() => decide(p.id, 'rejected')} data-testid={`payout-reject-${p.id}`}>{t.decline}</button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}

            <div className="grid gap-6 lg:grid-cols-2">
                <div>
                    <h3 className="mb-1 font-semibold">{t.balances}</h3>
                    {money.vendors.length === 0 ? <p className="rounded border bg-white p-3 text-sm text-gray-600">{t.no_earnings}</p> : (
                        <table className="w-full rounded border bg-white text-sm" data-testid="vendor-balances">
                            <thead className="bg-gray-50"><tr><th className="p-2 text-start">{t.vendor_name}</th><th className="p-2 text-end">{t.in_return_window}</th><th className="p-2 text-end">{t.available_now}</th><th className="p-2 text-end">{t.paid_out}</th><th className="p-2 text-end">{t.lifetime_commission}</th></tr></thead>
                            <tbody>
                                {money.vendors.map((v) => (
                                    <tr key={v.id} className="border-t" data-testid={`balance-${v.slug}`}>
                                        <td className="p-2">{v.name}<span className="block text-xs text-gray-500">{v.commission_rate}% · {t.result_count.replace(':count', v.orders_count)}</span></td>
                                        <td className="p-2 text-end">{v.in_window}<span className="block text-xs text-gray-500">+{v.awaiting_delivery}</span></td>
                                        <td className="p-2 text-end">{v.requestable_money}</td>
                                        <td className="p-2 text-end">{v.paid}</td>
                                        <td className="p-2 text-end">{v.lifetime_commission}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                    <h3 className="mb-1 mt-4 font-semibold">{t.payout_history}</h3>
                    {money.payouts.length === 0 ? <p className="rounded border bg-white p-3 text-sm text-gray-600">{t.no_payouts}</p> : (
                        <table className="w-full rounded border bg-white text-sm" data-testid="payout-history">
                            <tbody>
                                {money.payouts.map((p) => (
                                    <tr key={p.id} className="border-t" data-testid={`payout-done-${p.id}`}><td className="p-2">{p.vendor}</td><td className="p-2 text-end">{p.currency} {p.amount}</td><td className="p-2">{t[`payout_${p.status}`] || p.status}</td><td className="p-2 text-xs text-gray-500">{p.reference || p.note} · {p.decided_at}</td></tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
                <div>
                    <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
                        <h3 className="font-semibold">{t.commission_invoices}</h3>
                        <form className="flex items-center gap-2" onSubmit={(e) => { e.preventDefault(); router.post('/admin/bookshop/commission-invoices/issue', { month }, { preserveScroll: true }); }}>
                            <input type="month" className="form-input" value={month} onChange={(e) => setMonth(e.target.value)} data-testid="invoice-month" />
                            <button type="submit" className="btn-secondary" data-testid="issue-invoices">{t.issue_invoices}</button>
                        </form>
                    </div>
                    <p className="mb-2 text-xs text-gray-500">{money.issuer.name}{money.issuer.tin ? ` · ${t.tin} ${money.issuer.tin}` : ''} · {money.issuer.gst_registered ? t.gst_on_commission.replace(':rate', money.issuer.tax_rate) : t.no_gst_on_commission}</p>
                    {money.invoices.length === 0 ? <p className="rounded border bg-white p-3 text-sm text-gray-600">{t.no_invoices}</p> : (
                        <table className="w-full rounded border bg-white text-sm" data-testid="office-invoices">
                            <tbody>
                                {money.invoices.map((i) => (
                                    <tr key={i.id} className="border-t" data-testid={`office-invoice-${i.number}`}><td className="p-2 font-mono">{i.number}</td><td className="p-2">{i.vendor}<span className="block text-xs text-gray-500">{i.period}</span></td><td className="p-2 text-end">{i.currency} {i.total}</td><td className="p-2 text-end"><a href={`/admin/bookshop/commission-invoices/${i.id}`} target="_blank" rel="noreferrer" className="text-blue-700 underline">{t.open}</a></td></tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                    <h3 className="mb-1 mt-4 font-semibold">{t.tax_report}</h3>
                    {money.tax_report.length === 0 ? <p className="rounded border bg-white p-3 text-sm text-gray-600">{t.no_earnings}</p> : (
                        <table className="w-full rounded border bg-white text-sm" data-testid="tax-report">
                            <thead className="bg-gray-50"><tr><th className="p-2 text-start">{t.month}</th><th className="p-2 text-end">{t.sales_charged}</th><th className="p-2 text-end">{t.commission}</th><th className="p-2 text-end">{t.gst}</th><th className="p-2 text-end">{t.invoiced}</th></tr></thead>
                            <tbody>
                                {money.tax_report.map((r) => (
                                    <tr key={r.month} className="border-t" data-testid={`tax-${r.month}`}><td className="p-2">{r.label}</td><td className="p-2 text-end">{r.sales}</td><td className="p-2 text-end">{r.commission}</td><td className="p-2 text-end">{r.commission_tax}</td><td className="p-2 text-end">{r.invoiced} <span className="text-xs text-gray-500">({r.invoices})</span></td></tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </section>
    );
}

/** B7 (plan §4 "office may hide"; decision 12): the newest reviews, those waiting first; hide with a note, or publish. */
/** B9a (§3 "apply → approve"): shop applications, and the switch that opens or closes the form. */
function ApplicationRow({ a, t }) {
    const [note, setNote] = useState('');
    const [rate, setRate] = useState('');
    const [code, setCode] = useState('');
    const decide = (decision) => router.post(`/admin/bookshop/applications/${a.id}/decide`, { decision, note, commission_rate: rate, code }, { preserveScroll: true });

    return (
        <li className="p-3 text-sm" data-testid={`application-${a.id}`} data-status={a.status}>
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <span className="font-semibold" dir="auto">{a.shop_name}</span>
                <span className="text-xs text-gray-500">{a.submitted_at} · {t[`application_state_${a.status}`] || a.status}</span>
            </div>
            <p className="text-gray-700">{a.applicant} · {a.contact_email} · {a.contact_phone} · {a.island}{a.legal_name && ` · ${a.legal_name}`}{a.tin && ` · TIN ${a.tin}`}</p>
            <p className="mt-1 whitespace-pre-line text-gray-700" dir="auto">{a.what_they_sell}</p>
            {a.link && <a href={a.link} target="_blank" rel="noreferrer nofollow" className="text-blue-700 underline">{a.link}</a>}
            {a.status === 'pending' ? (
                <div className="mt-2 flex flex-wrap items-center gap-2">
                    <input className="form-input w-24" type="number" min="0" max="100" step="0.5" placeholder={t.commission_rate} value={rate} onChange={(e) => setRate(e.target.value)} aria-label={t.commission_rate} data-testid={`application-rate-${a.id}`} />
                    <input className="form-input w-20" maxLength={3} placeholder={t.code} value={code} onChange={(e) => setCode(e.target.value)} aria-label={t.code} data-testid={`application-code-${a.id}`} />
                    <button type="button" className="btn-primary" onClick={() => decide('approve')} data-testid={`application-approve-${a.id}`}>{t.approve_open_shop}</button>
                    <input className="form-input min-w-48 flex-1" placeholder={t.decline_note} value={note} onChange={(e) => setNote(e.target.value)} data-testid={`application-note-${a.id}`} />
                    <button type="button" className="text-red-700 underline" onClick={() => decide('decline')} data-testid={`application-decline-${a.id}`}>{t.decline}</button>
                </div>
            ) : (
                <p className="mt-1 text-xs text-gray-500">{a.decided_at}{a.decision_note && ` · ${a.decision_note}`}{a.vendor && <> · <a href={`/shop/${a.vendor.slug}`} className="text-blue-700 underline">{a.vendor.name}</a></>}</p>
            )}
        </li>
    );
}

function Applications({ applications, open, t }) {
    const waiting = applications.filter((a) => a.status === 'pending').length;

    return (
        <section className="mt-8" data-testid="office-applications">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold">{t.applications_heading} {waiting > 0 && <span className="rounded bg-amber-100 px-2 text-sm text-amber-900">{t.waiting_count.replace(':count', waiting)}</span>}</h2>
                <span className="flex flex-wrap items-center gap-2">
                    <span className="text-sm text-gray-600" data-testid="applications-state">{open ? t.applications_are_open : t.applications_are_closed}</span>
                    <button type="button" className="btn-secondary" onClick={() => router.post('/admin/bookshop/applications/open', { open: open ? 0 : 1 }, { preserveScroll: true })} data-testid="toggle-applications">{open ? t.close_applications : t.open_applications}</button>
                    <a href="/admin/bookshop/applications/export" className="btn-secondary">{t.export_csv}</a>
                </span>
            </div>
            {applications.length === 0 ? (
                <p className="rounded border bg-white p-3 text-sm text-gray-600">{t.no_applications}</p>
            ) : (
                <ul className="divide-y rounded border bg-white">{applications.slice(0, 50).map((a) => <ApplicationRow key={a.id} a={a} t={t} />)}</ul>
            )}
        </section>
    );
}

/** B9f (§2): shops' own domains — checked, then turned on; and the whole-shop subdomain. */
function Hosts({ hosts, t }) {
    const check = hosts.check;

    return (
        <section className="mt-8" data-testid="office-hosts">
            <h2 className="mb-1 text-lg font-semibold">{t.hosts_heading}</h2>
            <p className="mb-2 text-sm text-gray-600" data-testid="shop-host">{hosts.shop_host ? t.shop_host_set.replace(':host', hosts.shop_host) : t.shop_host_unset}</p>
            {check && (
                <p className={`mb-2 rounded p-2 text-sm ${check.points_here ? 'bg-green-50 text-green-800' : 'bg-amber-50 text-amber-900'}`} data-testid="host-check">
                    {(check.points_here ? t.host_points_here : t.host_points_elsewhere).replace(':host', check.host)}{' '}
                    <span className="text-xs">({[...check.addresses, ...check.aliases].join(', ') || t.host_no_records})</span>
                </p>
            )}
            {hosts.shops.length === 0 ? (
                <p className="rounded border bg-white p-3 text-sm text-gray-600">{t.no_host_requests}</p>
            ) : (
                <ul className="divide-y rounded border bg-white text-sm">
                    {hosts.shops.map((h) => (
                        <li key={h.vendor_id} className="flex flex-wrap items-center justify-between gap-2 p-2" data-testid={`host-${h.slug}`} data-status={h.status}>
                            <span><span className="font-mono" dir="ltr">{h.host}</span> · {h.vendor} · {h.status === 'active' ? t.host_state_active : t.host_state_requested}</span>
                            <span className="flex gap-2">
                                <button type="button" className="btn-secondary" onClick={() => router.post(`/admin/bookshop/hosts/${h.vendor_id}/check`, {}, { preserveScroll: true })} data-testid={`host-check-${h.slug}`}>{t.host_check}</button>
                                {h.status === 'active'
                                    ? <button type="button" className="btn-secondary" onClick={() => router.post(`/admin/bookshop/hosts/${h.vendor_id}`, { decision: 'off' }, { preserveScroll: true })} data-testid={`host-off-${h.slug}`}>{t.host_turn_off}</button>
                                    : <button type="button" className="btn-primary" onClick={() => router.post(`/admin/bookshop/hosts/${h.vendor_id}`, { decision: 'approve' }, { preserveScroll: true })} data-testid={`host-approve-${h.slug}`}>{t.host_turn_on}</button>}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/** B9e: every shop's funnel side by side. */
function Funnels({ insights, t }) {
    const fill = (s, vars) => Object.entries(vars).reduce((out, [k, v]) => out.replaceAll(`:${k}`, v), s || '');
    const steps = ['shop_view', 'product_view', 'cart_add', 'checkout', 'order_paid'];

    return (
        <section className="mt-8" data-testid="office-insights">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold">{t.insights_office_heading}</h2>
                <span className="flex flex-wrap gap-1">
                    {insights.ranges.map((d) => (
                        <button key={d} type="button" className={`rounded px-2 py-1 text-sm ${d === insights.days ? 'bg-gray-800 text-white' : 'bg-gray-100'}`}
                            onClick={() => router.get('/admin/bookshop', { insight_days: d }, { preserveScroll: true })}>{fill(t.insights_last_days, { days: d })}</button>
                    ))}
                    <a href={`/admin/bookshop/insights/export?days=${insights.days}`} className="btn-secondary" data-testid="export-insights">{t.export_csv}</a>
                </span>
            </div>
            <div className="overflow-x-auto rounded border bg-white">
                <table className="w-full text-sm">
                    <thead className="bg-gray-50"><tr><th className="p-2 text-start">{t.insights_shop}</th>{steps.map((s) => <th key={s} className="p-2 text-end">{t[`insights_step_${s}`] || s}</th>)}<th className="p-2 text-end">{t.insights_sales}</th><th className="p-2 text-end">%</th></tr></thead>
                    <tbody>
                        {insights.shops.map((r) => (
                            <tr key={r.slug} className="border-t" data-testid={`insights-${r.slug}`}>
                                <td className="p-2">{r.vendor}</td>
                                {steps.map((s) => <td key={s} className="p-2 text-end">{r[s]}</td>)}
                                <td className="p-2 text-end">{r.revenue}</td>
                                <td className="p-2 text-end">{r.conversion ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

/** B9d: bulk quotes across shops — a request nobody answers is seen here. */
function Quotes({ quotes, t }) {
    const statuses = ['requested', 'quoted', 'accepted', 'ordered', 'declined', 'withdrawn'];

    return (
        <section className="mt-8" data-testid="office-quotes">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold">{t.quotes_title}</h2>
                <a href="/admin/bookshop/quotes/export" className="btn-secondary" data-testid="export-quotes-all">{t.export_csv}</a>
            </div>
            <p className="mb-2 flex flex-wrap gap-2 text-sm">
                {statuses.map((s) => <span key={s} className="rounded bg-gray-100 px-2 py-0.5" data-testid={`quotes-count-${s}`}>{t[`quote_status_${s}`] || s}: {quotes.counts[s] || 0}</span>)}
            </p>
            {quotes.recent.length > 0 && (
                <ul className="divide-y rounded border bg-white text-sm">
                    {quotes.recent.map((q) => (
                        <li key={q.id} className="flex flex-wrap justify-between gap-2 p-2">
                            <span><span className="font-mono">{q.number}</span> · {q.vendor.name} · <span dir="auto">{q.organisation}</span> · {t[`quote_status_${q.status}`] || q.status}</span>
                            <span>{q.currency} {q.quoted_total ?? q.list_total} · {q.requested_at}</span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/** B8 (§7 Reports "low stock across vendors"). */
function LowStockAll({ rows, t }) {
    return (
        <section className="mt-8" data-testid="office-low-stock">
            <div className="mb-2 flex items-center justify-between">
                <h2 className="text-lg font-semibold">{t.low_stock_heading} <span className="text-sm font-normal text-gray-500">({rows.length})</span></h2>
                <a href="/admin/bookshop/low-stock/export" className="btn-secondary" data-testid="export-low-stock-all">{t.export_csv}</a>
            </div>
            {rows.length === 0 ? (
                <p className="rounded border bg-white p-3 text-sm text-gray-600">{t.no_low_stock}</p>
            ) : (
                <table className="w-full rounded border bg-white text-sm">
                    <thead className="bg-gray-50"><tr><th className="p-2 text-start">{t.shop_col}</th><th className="p-2 text-start">{t.product}</th><th className="p-2 text-start">SKU</th><th className="p-2 text-end">{t.stock}</th><th className="p-2 text-start">{t.state}</th></tr></thead>
                    <tbody>
                        {rows.slice(0, 100).map((r) => (
                            <tr key={`${r.product_id}-${r.variant_id || 0}`} className="border-t">
                                <td className="p-2">{r.vendor}</td>
                                <td className="p-2" dir="auto">{r.title}{r.variant && <span className="text-gray-500"> · {r.variant}</span>}</td>
                                <td className="p-2">{r.sku || '—'}</td>
                                <td className="p-2 text-end">{r.stock}</td>
                                <td className="p-2">{t[`stock_state_${r.state}`] || r.state}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </section>
    );
}

/** B8 (§7 Settings "email/SMS notice switches"): the in-app notice always goes; these add channels. */
/** B9b (decision 7): cash on delivery for the whole bookstore; each shop then opts in. */
function CodSwitch({ on, t }) {
    return (
        <section className="mt-8" data-testid="office-cod">
            <h2 className="mb-1 text-lg font-semibold">{t.cod_label}</h2>
            <p className="mb-2 text-sm text-gray-600">{t.office_cod_hint}</p>
            <div className="flex flex-wrap items-center gap-3 rounded border bg-white p-3 text-sm">
                <span data-testid="cod-state">{on ? t.cod_is_on : t.cod_is_off}</span>
                <button type="button" className="btn-secondary" onClick={() => router.post('/admin/bookshop/cod', { on: on ? 0 : 1 }, { preserveScroll: true })} data-testid="toggle-cod">{on ? t.cod_turn_off : t.cod_turn_on}</button>
            </div>
        </section>
    );
}

function NoticeSwitches({ notices, t }) {
    const form = useForm({ ...notices });

    return (
        <section className="mt-8" data-testid="office-notices">
            <h2 className="mb-1 text-lg font-semibold">{t.notices_heading}</h2>
            <p className="mb-2 text-sm text-gray-600">{t.office_notices_hint}</p>
            <form className="flex flex-wrap items-center gap-4 rounded border bg-white p-3 text-sm" onSubmit={(e) => { e.preventDefault(); form.post('/admin/bookshop/notices', { preserveScroll: true }); }}>
                {['customer_email', 'customer_sms', 'vendor_email', 'vendor_sms'].map((k) => (
                    <label key={k} className="flex items-center gap-2"><input type="checkbox" checked={!!form.data[k]} onChange={(e) => form.setData(k, e.target.checked)} data-testid={`switch-${k}`} /> {t[`switch_${k}`]}</label>
                ))}
                <button type="submit" className="btn-primary" disabled={form.processing} data-testid="save-switches">{t.save}</button>
            </form>
        </section>
    );
}

function Reviews({ reviews, t }) {
    const [notes, setNotes] = useState({});
    const act = (id, action) => router.post(`/admin/bookshop/reviews/${id}/moderate`, { action, note: notes[id] || '' }, { preserveScroll: true });
    const waiting = reviews.filter((r) => r.status === 'pending').length;

    return (
        <section className="mt-8" data-testid="office-reviews">
            <h2 className="mb-2 text-lg font-semibold">{t.reviews_heading} {waiting > 0 && <span className="ms-2 rounded bg-amber-100 px-2 text-sm text-amber-800">{waiting}</span>}</h2>
            {reviews.length === 0 ? <p className="rounded border bg-white p-3 text-sm text-gray-600">{t.no_reviews}</p> : (
                <ul className="divide-y rounded border bg-white text-sm">
                    {reviews.map((r) => (
                        <li key={r.id} className="flex flex-wrap items-start gap-3 p-2" data-testid={`office-review-${r.id}`} data-review-status={r.status}>
                            <div className="min-w-64 flex-1">
                                <p><span className="text-amber-700">{'★'.repeat(r.rating)}{'☆'.repeat(5 - r.rating)}</span> · <a href={`/shop/products/${r.product_slug}#reviews`} target="_blank" rel="noreferrer" className="text-blue-700 underline">{r.product}</a> <span className="text-gray-500">· {r.vendor} · {r.created_at}</span></p>
                                {r.body && <p className="whitespace-pre-line" dir="auto">{r.body}</p>}
                                {r.reply && <p className="ms-3 text-xs text-gray-600">↳ {r.reply}</p>}
                                {r.moderation_note && <p className="text-xs text-red-700">{t.office_note}: {r.moderation_note}</p>}
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-xs">{t[`review_status_${r.status}`] || r.status}</span>
                                <input className="form-input w-40" placeholder={t.note} value={notes[r.id] || ''} onChange={(e) => setNotes({ ...notes, [r.id]: e.target.value })} data-testid={`review-note-${r.id}`} />
                                {r.status !== 'hidden' && <button type="button" className="text-red-700 underline" onClick={() => act(r.id, 'hide')} data-testid={`review-hide-${r.id}`}>{t.hide}</button>}
                                {r.status !== 'published' && <button type="button" className="text-blue-700 underline" onClick={() => act(r.id, 'publish')} data-testid={`review-publish-${r.id}`}>{t.publish}</button>}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/** B7 (plan §7): the shop home — hero slides, featured products, featured collections, in the office's order. */
function ShopHome({ home, t }) {
    const o = home.options;
    const hero = useForm({ kind: 'hero', heading: '', heading_dv: '', heading_ar: '', subheading: '', link: { kind: '', target: '' }, image: null });
    const [productId, setProductId] = useState('');
    const [collectionId, setCollectionId] = useState('');
    const targets = { vendor: o.vendors.map((v) => [v.slug, v.label]), category: o.categories.map((c) => [c.slug, c.label]), product: o.products.map((p) => [p.slug, p.label]), collection: o.collections.map((c) => [String(c.id), c.label]) };
    const list = (kind) => home.features.filter((f) => f.kind === kind);
    const row = (f) => (
        <li key={f.id} className="flex flex-wrap items-center gap-2 p-2" data-testid={`home-feature-${f.id}`}>
            {f.image && <img src={f.image} alt="" className="h-10 w-16 rounded object-cover" />}
            <span className="flex-1" dir="auto">{f.label}{!f.is_active && <span className="ms-2 text-xs text-gray-500">({t.inactive})</span>}</span>
            <button type="button" className="btn-secondary px-2 py-1 text-xs" onClick={() => router.post(`/admin/bookshop/home/${f.id}/move`, { direction: -1 }, { preserveScroll: true })} aria-label={t.move_up}>↑</button>
            <button type="button" className="btn-secondary px-2 py-1 text-xs" onClick={() => router.post(`/admin/bookshop/home/${f.id}/move`, { direction: 1 }, { preserveScroll: true })} aria-label={t.move_down}>↓</button>
            <button type="button" className="text-xs text-red-700 underline" onClick={() => router.delete(`/admin/bookshop/home/${f.id}`, { preserveScroll: true })} data-testid={`home-remove-${f.id}`}>{t.remove}</button>
        </li>
    );

    return (
        <section className="mt-8" data-testid="office-home">
            <div className="mb-2 flex items-center justify-between"><h2 className="text-lg font-semibold">{t.shop_home_heading}</h2><a href="/shop" target="_blank" rel="noreferrer" className="text-sm text-blue-700 underline">/shop</a></div>
            <div className="grid gap-6 lg:grid-cols-3">
                <div>
                    <h3 className="mb-1 font-semibold">{t.hero_slides}</h3>
                    <ul className="mb-2 divide-y rounded border bg-white text-sm">{list('hero').map(row)}</ul>
                    <form className="space-y-2 rounded border bg-white p-2 text-sm" data-testid="hero-form" onSubmit={(e) => { e.preventDefault(); hero.post('/admin/bookshop/home', { forceFormData: true, preserveScroll: true, onSuccess: () => hero.reset() }); }}>
                        <input className="form-input w-full" placeholder={t.field_heading} value={hero.data.heading} onChange={(e) => hero.setData('heading', e.target.value)} required data-testid="hero-heading" />
                        <input className="form-input w-full" dir="rtl" placeholder={t.name_dv} value={hero.data.heading_dv} onChange={(e) => hero.setData('heading_dv', e.target.value)} />
                        <input className="form-input w-full" placeholder={t.field_subheading} value={hero.data.subheading} onChange={(e) => hero.setData('subheading', e.target.value)} />
                        <div className="flex gap-2">
                            <select className="form-input" value={hero.data.link.kind} onChange={(e) => hero.setData('link', { kind: e.target.value, target: '' })} data-testid="hero-link-kind">
                                <option value="">{t.no_link}</option>
                                {o.link_kinds.map((k) => <option key={k} value={k}>{t[`home_link_${k}`] || k}</option>)}
                            </select>
                            {hero.data.link.kind && (
                                <select className="form-input flex-1" value={hero.data.link.target} onChange={(e) => hero.setData('link', { ...hero.data.link, target: e.target.value })} data-testid="hero-link-target">
                                    <option value="">—</option>
                                    {(targets[hero.data.link.kind] || []).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                                </select>
                            )}
                        </div>
                        <input type="file" accept="image/jpeg,image/png,image/webp" className="block w-full text-xs" onChange={(e) => hero.setData('image', e.target.files?.[0] || null)} data-testid="hero-image" />
                        <FormErrors errors={hero.errors} />
                        <button type="submit" className="btn-primary" disabled={hero.processing} data-testid="add-hero">{t.add_slide}</button>
                    </form>
                </div>
                <div>
                    <h3 className="mb-1 font-semibold">{t.featured_heading}</h3>
                    <ul className="mb-2 divide-y rounded border bg-white text-sm" data-testid="home-featured">{list('product').map(row)}</ul>
                    <div className="flex gap-2">
                        <select className="form-input flex-1" value={productId} onChange={(e) => setProductId(e.target.value)} data-testid="feature-product">
                            <option value="">—</option>
                            {o.products.map((p) => <option key={p.id} value={p.id}>{p.label}</option>)}
                        </select>
                        <button type="button" className="btn-secondary" disabled={!productId} onClick={() => router.post('/admin/bookshop/home', { kind: 'product', product_id: productId }, { preserveScroll: true, onSuccess: () => setProductId('') })} data-testid="add-featured">{t.add}</button>
                    </div>
                </div>
                <div>
                    <h3 className="mb-1 font-semibold">{t.featured_collections}</h3>
                    <ul className="mb-2 divide-y rounded border bg-white text-sm" data-testid="home-collections">{list('collection').map(row)}</ul>
                    <div className="flex gap-2">
                        <select className="form-input flex-1" value={collectionId} onChange={(e) => setCollectionId(e.target.value)} data-testid="feature-collection">
                            <option value="">—</option>
                            {o.collections.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                        </select>
                        <button type="button" className="btn-secondary" disabled={!collectionId} onClick={() => router.post('/admin/bookshop/home', { kind: 'collection', vendor_collection_id: collectionId }, { preserveScroll: true, onSuccess: () => setCollectionId('') })} data-testid="add-collection">{t.add}</button>
                    </div>
                </div>
            </div>
        </section>
    );
}

export default function Admin({ t, vendors, catalogue, slips = [], orders = [], refunds = [], money = null, reviews = [], home = null, low_stock = [], notices = null, order_statuses = [], applications = [], applications_open = true, quotes = null, insights = null, hosts = null, cod_on = true, default_commission_rate, sign_in_url, section_types = [] }) {
    const { flash = {}, errors } = usePage().props;

    return (
        <AppShell title={t.office_title}>
            <FormErrors errors={errors} className="mb-4" />
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="flash-success">{flash.success}</p>}
            {flash.vendor_invite && <InviteCard invite={flash.vendor_invite} t={t} signInUrl={sign_in_url} />}

            {slips.some((s) => s.status === 'waiting') && <Slips slips={slips} t={t} />}
            {refunds.some((r) => r.status === 'pending') && <Refunds refunds={refunds} t={t} />}
            {money && money.requests.length > 0 && <Money money={money} t={t} />}
            {applications.some((a) => a.status === 'pending') && <Applications applications={applications} open={applications_open} t={t} />}

            <InviteVendor t={t} defaultRate={default_commission_rate} />

            <div className="mb-2 flex items-center justify-between">
                <h2 className="text-lg font-semibold">{t.vendors}</h2>
                <a href="/admin/bookshop/vendors/export" className="btn-secondary" data-testid="export-vendors">{t.export_csv}</a>
            </div>
            <VendorTable vendors={vendors} t={t} sectionTypes={section_types} />

            {!slips.some((s) => s.status === 'waiting') && <Slips slips={slips} t={t} />}
            {!refunds.some((r) => r.status === 'pending') && <Refunds refunds={refunds} t={t} />}
            {money && money.requests.length === 0 && <Money money={money} t={t} />}
            {!applications.some((a) => a.status === 'pending') && <Applications applications={applications} open={applications_open} t={t} />}
            {quotes && <Quotes quotes={quotes} t={t} />}
            {insights && <Funnels insights={insights} t={t} />}
            {hosts && <Hosts hosts={hosts} t={t} />}
            <Orders orders={orders} vendors={vendors} statuses={order_statuses} t={t} />
            <LowStockAll rows={low_stock} t={t} />
            <Reviews reviews={reviews} t={t} />
            {home && <ShopHome home={home} t={t} />}
            {notices && <NoticeSwitches key={JSON.stringify(notices)} notices={notices} t={t} />}
            <CodSwitch on={cod_on} t={t} />

            <Catalogue catalogue={catalogue} t={t} />
        </AppShell>
    );
}
