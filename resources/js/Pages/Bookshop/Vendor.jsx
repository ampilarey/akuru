import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * BOOKSHOP_PLAN slice B1a — the vendor portal. A member accepts the Vendor
 * Agreement once, then lists products: photos, prices, stock, tax class,
 * variants, and the book or educational details the shop will show.
 */

const DETAIL_BOOK = ['author', 'publisher', 'year', 'pages', 'language'];
const DETAIL_EDU = ['age_range', 'grade', 'subject'];

function blankProduct() {
    return {
        title: '', title_dv: '', title_ar: '', summary: '', summary_dv: '', summary_ar: '',
        description: '', description_dv: '', description_ar: '',
        product_category_id: '', brand_id: '', tags_text: '',
        price: '', compare_at_price: '', cost: '', tax_class: 'standard',
        sku: '', barcode: '', weight_grams: '', dimensions: '',
        track_stock: true, stock: '0', low_stock_at: '', lead_days: '',
        status: 'draft', visibility: 'shop',
        details: {}, variants: [], photos: [],
    };
}

function fromProduct(p) {
    const text = (value) => (value === null || value === undefined ? '' : String(value));

    return {
        ...blankProduct(),
        title: p.title, title_dv: text(p.title_dv), title_ar: text(p.title_ar),
        summary: text(p.summary), summary_dv: text(p.summary_dv), summary_ar: text(p.summary_ar),
        description: text(p.description), description_dv: text(p.description_dv), description_ar: text(p.description_ar),
        product_category_id: text(p.product_category_id), brand_id: text(p.brand_id), tags_text: (p.tags || []).join(', '),
        price: text(p.price), compare_at_price: text(p.compare_at_price), cost: text(p.cost), tax_class: p.tax_class,
        sku: text(p.sku), barcode: text(p.barcode), weight_grams: text(p.weight_grams), dimensions: text(p.dimensions),
        track_stock: Boolean(p.track_stock), stock: text(p.stock), low_stock_at: text(p.low_stock_at), lead_days: text(p.lead_days),
        status: p.status, visibility: p.visibility,
        details: { ...(p.details || {}) },
        variants: (p.variants || []).map((v) => ({ id: v.id, name: v.name, sku: text(v.sku), price: text(v.price), stock: text(v.stock), is_active: Boolean(v.is_active) })),
    };
}

function Field({ label, hint, children, className = '' }) {
    return (
        <label className={`block text-sm ${className}`}>
            <span className="mb-1 block font-medium text-gray-700">{label}</span>
            {children}
            {hint && <span className="mt-1 block text-xs text-gray-500">{hint}</span>}
        </label>
    );
}

function ProductEditor({ product, options, t, onDone }) {
    const form = useForm(product ? fromProduct(product) : blankProduct());
    const [showTranslations, setShowTranslations] = useState(Boolean(product?.title_dv || product?.title_ar));
    const set = (name) => (e) => form.setData(name, e.target.type === 'checkbox' ? e.target.checked : e.target.value);
    const setDetail = (key) => (e) => form.setData('details', { ...form.data.details, [key]: e.target.value });
    const setVariant = (index, key, value) => form.setData('variants', form.data.variants.map((v, i) => (i === index ? { ...v, [key]: value } : v)));

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => {
            // Booleans travel as 1/0 in multipart form data.
            const { tags_text, ...rest } = data;

            return {
                ...rest,
                track_stock: data.track_stock ? 1 : 0,
                tags: tags_text.split(',').map((tag) => tag.trim()).filter(Boolean),
                variants_sent: 1,
                variants: data.variants.map((v) => ({ ...v, is_active: v.is_active ? 1 : 0 })),
            };
        });
        const opts = { preserveScroll: true, forceFormData: true, onSuccess: onDone };
        if (product) {
            form.post(`/vendor/products/${product.id}`, opts);
        } else {
            form.post('/vendor/products', opts);
        }
    };

    const arrange = (image, move) => router.post(`/vendor/product-images/${image.id}`, { move }, { preserveScroll: true });

    return (
        <form onSubmit={submit} className="mb-6 space-y-4 rounded-lg border bg-white p-4" data-testid="product-editor">
            <h3 className="text-lg font-semibold">{product ? t.edit_product : t.new_product}</h3>
            <FormErrors errors={form.errors} />

            <div className="grid gap-3 md:grid-cols-3">
                <Field label={t.product_title} className="md:col-span-2">
                    <input className="form-input w-full" value={form.data.title} onChange={set('title')} data-testid="product-title" required />
                </Field>
                <Field label={t.status}>
                    <select className="form-input w-full" value={form.data.status} onChange={set('status')} data-testid="product-status">
                        {options.statuses.map((s) => <option key={s} value={s}>{t[`status_${s}`] || s}</option>)}
                    </select>
                </Field>
                <Field label={t.summary} className="md:col-span-3">
                    <input className="form-input w-full" value={form.data.summary} onChange={set('summary')} maxLength={500} />
                </Field>
                <Field label={t.description} hint={t.description_hint} className="md:col-span-3">
                    <textarea className="form-input w-full" rows={4} value={form.data.description} onChange={set('description')} />
                </Field>
            </div>

            <button type="button" className="text-sm text-blue-700 underline" onClick={() => setShowTranslations(!showTranslations)}>{t.translations}</button>
            {showTranslations && (
                <div className="grid gap-3 md:grid-cols-2">
                    <Field label={t.title_dv}><input dir="rtl" className="form-input w-full" value={form.data.title_dv} onChange={set('title_dv')} /></Field>
                    <Field label={t.title_ar}><input dir="rtl" className="form-input w-full" value={form.data.title_ar} onChange={set('title_ar')} /></Field>
                    <Field label={t.summary_dv}><input dir="rtl" className="form-input w-full" value={form.data.summary_dv} onChange={set('summary_dv')} /></Field>
                    <Field label={t.summary_ar}><input dir="rtl" className="form-input w-full" value={form.data.summary_ar} onChange={set('summary_ar')} /></Field>
                    <Field label={t.description_dv}><textarea dir="rtl" rows={3} className="form-input w-full" value={form.data.description_dv} onChange={set('description_dv')} /></Field>
                    <Field label={t.description_ar}><textarea dir="rtl" rows={3} className="form-input w-full" value={form.data.description_ar} onChange={set('description_ar')} /></Field>
                </div>
            )}

            <div className="grid gap-3 md:grid-cols-4">
                <Field label={t.price}><input className="form-input w-full" type="number" step="0.01" min="0" value={form.data.price} onChange={set('price')} data-testid="product-price" required /></Field>
                <Field label={t.compare_at_price} hint={t.compare_at_hint}><input className="form-input w-full" type="number" step="0.01" min="0" value={form.data.compare_at_price} onChange={set('compare_at_price')} /></Field>
                <Field label={t.cost} hint={t.cost_hint}><input className="form-input w-full" type="number" step="0.01" min="0" value={form.data.cost} onChange={set('cost')} /></Field>
                <Field label={t.tax_class}>
                    <select className="form-input w-full" value={form.data.tax_class} onChange={set('tax_class')} data-testid="product-tax-class">
                        {options.tax_classes.map((c) => <option key={c} value={c}>{t[`tax_${c}`] || c}</option>)}
                    </select>
                </Field>
                <Field label={t.category}>
                    <select className="form-input w-full" value={form.data.product_category_id} onChange={set('product_category_id')} data-testid="product-category">
                        <option value="">{t.none}</option>
                        {options.categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </Field>
                <Field label={t.brand}>
                    <select className="form-input w-full" value={form.data.brand_id} onChange={set('brand_id')}>
                        <option value="">{t.none}</option>
                        {options.brands.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                    </select>
                </Field>
                <Field label={t.tags} hint={t.tags_hint} className="md:col-span-2"><input className="form-input w-full" value={form.data.tags_text} onChange={set('tags_text')} /></Field>
                <Field label={t.sku}><input className="form-input w-full" value={form.data.sku} onChange={set('sku')} data-testid="product-sku" /></Field>
                <Field label={t.barcode}><input className="form-input w-full" value={form.data.barcode} onChange={set('barcode')} /></Field>
                <Field label={t.weight_grams}><input className="form-input w-full" type="number" min="0" value={form.data.weight_grams} onChange={set('weight_grams')} /></Field>
                <Field label={t.dimensions}><input className="form-input w-full" value={form.data.dimensions} onChange={set('dimensions')} /></Field>
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.track_stock} onChange={set('track_stock')} /> {t.track_stock}</label>
                <Field label={t.stock}><input className="form-input w-full" type="number" min="0" value={form.data.stock} onChange={set('stock')} data-testid="product-stock" /></Field>
                <Field label={t.low_stock_at}><input className="form-input w-full" type="number" min="0" value={form.data.low_stock_at} onChange={set('low_stock_at')} /></Field>
                <Field label={t.lead_days}><input className="form-input w-full" type="number" min="0" value={form.data.lead_days} onChange={set('lead_days')} /></Field>
                <Field label={t.visibility} className="md:col-span-2">
                    <select className="form-input w-full" value={form.data.visibility} onChange={set('visibility')}>
                        {options.visibilities.map((v) => <option key={v} value={v}>{t[`visibility_${v}`] || v}</option>)}
                    </select>
                </Field>
            </div>

            <fieldset className="grid gap-3 rounded border p-3 md:grid-cols-5">
                <legend className="px-1 text-sm font-semibold">{t.book_details}</legend>
                {DETAIL_BOOK.map((key) => (
                    <Field key={key} label={t[key]}><input className="form-input w-full" value={form.data.details[key] || ''} onChange={setDetail(key)} data-testid={`detail-${key}`} /></Field>
                ))}
            </fieldset>
            <fieldset className="grid gap-3 rounded border p-3 md:grid-cols-3">
                <legend className="px-1 text-sm font-semibold">{t.educational_details}</legend>
                {DETAIL_EDU.map((key) => (
                    <Field key={key} label={t[key]}><input className="form-input w-full" value={form.data.details[key] || ''} onChange={setDetail(key)} data-testid={`detail-${key}`} /></Field>
                ))}
            </fieldset>

            <fieldset className="rounded border p-3" data-testid="variants">
                <legend className="px-1 text-sm font-semibold">{t.variants}</legend>
                <p className="mb-2 text-xs text-gray-500">{t.variants_hint}</p>
                {form.data.variants.map((v, index) => (
                    <div key={v.id ?? `new-${index}`} className="mb-2 grid gap-2 md:grid-cols-6">
                        <input className="form-input md:col-span-2" placeholder={t.variant_name} value={v.name} onChange={(e) => setVariant(index, 'name', e.target.value)} data-testid={`variant-name-${index}`} />
                        <input className="form-input" placeholder={t.sku} value={v.sku} onChange={(e) => setVariant(index, 'sku', e.target.value)} />
                        <input className="form-input" type="number" step="0.01" min="0" placeholder={t.price} value={v.price} onChange={(e) => setVariant(index, 'price', e.target.value)} />
                        <input className="form-input" type="number" min="0" placeholder={t.stock} value={v.stock} onChange={(e) => setVariant(index, 'stock', e.target.value)} data-testid={`variant-stock-${index}`} />
                        <button type="button" className="text-sm text-red-700" onClick={() => form.setData('variants', form.data.variants.filter((_, i) => i !== index))}>{t.remove}</button>
                    </div>
                ))}
                <button type="button" className="text-sm text-blue-700 underline" data-testid="add-variant" onClick={() => form.setData('variants', [...form.data.variants, { name: '', sku: '', price: '', stock: '0', is_active: true }])}>{t.add_variant}</button>
            </fieldset>

            <fieldset className="rounded border p-3">
                <legend className="px-1 text-sm font-semibold">{t.photos}</legend>
                {product?.images?.length > 0 && (
                    <ul className="mb-3 flex flex-wrap gap-3" data-testid="product-images">
                        {product.images.map((image, index) => (
                            <li key={image.id} className="w-28 text-center text-xs">
                                {image.url && <img src={image.url} alt={image.alt || ''} className="mb-1 h-28 w-28 rounded object-cover" loading="lazy" />}
                                {index > 0 && <button type="button" className="me-2 text-blue-700 underline" onClick={() => arrange(image, 'first')}>{t.make_first}</button>}
                                <button type="button" className="text-red-700 underline" onClick={() => arrange(image, 'remove')}>{t.remove}</button>
                            </li>
                        ))}
                    </ul>
                )}
                <input type="file" multiple accept="image/jpeg,image/png,image/webp" onChange={(e) => form.setData('photos', Array.from(e.target.files || []))} data-testid="product-photos" />
                <p className="mt-1 text-xs text-gray-500">{t.photos_hint}</p>
            </fieldset>

            <div className="flex gap-3">
                <button type="submit" className="btn-primary" disabled={form.processing} data-testid="save-product">{t.save_product}</button>
                <button type="button" className="text-sm text-gray-600 underline" onClick={onDone}>{t.cancel}</button>
            </div>
        </form>
    );
}

function AgreementGate({ t, agreementUrl }) {
    const form = useForm({ accept: false });

    return (
        <form
            className="rounded-lg border bg-white p-6"
            data-testid="agreement-form"
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/vendor/agreement');
            }}
        >
            <h2 className="mb-2 text-xl font-semibold">{t.agreement_heading}</h2>
            <p className="mb-3 text-gray-700">{t.agreement_intro}</p>
            <p className="mb-4"><a href={agreementUrl} target="_blank" rel="noreferrer" className="text-blue-700 underline" data-testid="agreement-link">{t.read_agreement}</a></p>
            <label className="mb-4 flex items-center gap-2">
                <input type="checkbox" checked={form.data.accept} onChange={(e) => form.setData('accept', e.target.checked)} data-testid="accept-agreement" />
                {t.accept_agreement}
            </label>
            <FormErrors errors={form.errors} className="mb-3" />
            <button type="submit" className="btn-primary" disabled={!form.data.accept || form.processing}>{t.continue}</button>
        </form>
    );
}

function Members({ members, isOwner, t }) {
    const form = useForm({ name: '', email: '', phone: '' });
    const flash = usePage().props.flash || {};

    return (
        <section className="mt-8" data-testid="members">
            <h2 className="mb-2 text-lg font-semibold">{t.members_heading}</h2>
            <ul className="mb-3 divide-y rounded border bg-white">
                {members.map((m) => (
                    <li key={m.id} className="flex flex-wrap justify-between gap-2 p-3 text-sm">
                        <span>{m.name}{m.is_me ? ` (${t.you})` : ''} · {m.email}</span>
                        <span className="text-gray-600">{t[`role_${m.role}`] || m.role} · {t.agreement}: {m.agreement_accepted_at ? t.accepted : t.not_yet}</span>
                    </li>
                ))}
            </ul>
            {isOwner && (
                <form
                    className="grid gap-2 rounded border bg-white p-3 md:grid-cols-4"
                    data-testid="add-member-form"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/vendor/members', { preserveScroll: true, onSuccess: () => form.reset() });
                    }}
                >
                    <p className="text-sm text-gray-600 md:col-span-4">{t.add_member_intro}</p>
                    <input className="form-input" placeholder={t.name} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                    <input className="form-input" type="email" placeholder={t.contact_email} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required />
                    <input className="form-input" placeholder={t.owner_phone} value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                    <button type="submit" className="btn-primary" disabled={form.processing}>{t.add_member}</button>
                    <FormErrors errors={form.errors} className="md:col-span-4" />
                </form>
            )}
            {flash.temporary_password && (
                <p className="mt-3 rounded bg-amber-50 p-3" data-testid="temporary-password">
                    {t.new_password_for}: <span className="font-mono text-lg">{flash.temporary_password}</span>
                    <span className="ms-2 text-sm text-amber-800">{t.shown_once}</span>
                </p>
            )}
        </section>
    );
}

function ProductList({ products, t, onEdit }) {
    if (products.length === 0) {
        return <p className="rounded border bg-white p-4 text-gray-600">{t.no_products}</p>;
    }

    return (
        <table className="w-full overflow-hidden rounded border bg-white text-sm" data-testid="product-list">
            <thead className="bg-gray-50 text-start">
                <tr>
                    <th className="p-2 text-start" />
                    <th className="p-2 text-start">{t.product_title}</th>
                    <th className="p-2 text-start">{t.sku}</th>
                    <th className="p-2 text-end">{t.price}</th>
                    <th className="p-2 text-end">{t.stock}</th>
                    <th className="p-2 text-start">{t.status}</th>
                    <th className="p-2" />
                </tr>
            </thead>
            <tbody>
                {products.map((p) => (
                    <tr key={p.id} className="border-t" data-testid={`product-row-${p.slug}`}>
                        <td className="p-2">{p.images[0]?.url ? <img src={p.images[0].url} alt="" className="h-12 w-12 rounded object-cover" loading="lazy" /> : <span className="block h-12 w-12 rounded bg-gray-100" />}</td>
                        <td className="p-2">
                            <span className="font-medium">{p.title}</span>
                            {p.category && <span className="block text-xs text-gray-500">{p.category}</span>}
                            {p.variants.length > 0 && <span className="block text-xs text-gray-500">{t.variants}: {p.variants.map((v) => v.name).join(', ')}</span>}
                        </td>
                        <td className="p-2">{p.sku || t.none}</td>
                        <td className="p-2 text-end">
                            {p.price}
                            {p.compare_at_price && <span className="ms-1 text-xs text-gray-500 line-through">{p.compare_at_price}</span>}
                        </td>
                        <td className="p-2 text-end">
                            {p.track_stock ? p.stock : t.not_tracked}
                            {p.low_stock && <span className="ms-1 rounded bg-amber-100 px-1 text-xs text-amber-800">{t.low_stock}</span>}
                        </td>
                        <td className="p-2">{t[`status_${p.status}`] || p.status}</td>
                        <td className="p-2 text-end">
                            <button type="button" className="text-blue-700 underline" onClick={() => onEdit(p)} data-testid={`edit-${p.slug}`}>{t.edit}</button>
                            {p.status === 'active' && (
                                <a href={`/shop/products/${p.slug}`} target="_blank" rel="noreferrer" className="ms-3 text-blue-700 underline" data-testid={`view-${p.slug}`}>{t.view_on_shop}</a>
                            )}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

export default function Vendor({ t, vendor, memberships = [], agreement_url, products = [], members = [], options, filters, must_set_password, set_password_url }) {
    const { flash = {}, errors } = usePage().props;
    const [editing, setEditing] = useState(null);
    const [search, setSearch] = useState(filters.q || '');
    const [status, setStatus] = useState(filters.status || '');
    const isOwner = vendor.role === 'owner';

    return (
        <AppShell title={t.portal_title}>
            <FormErrors errors={errors} className="mb-4" />
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700">{flash.success}</p>}
            {must_set_password && (
                <p className="mb-4 rounded bg-amber-50 p-3 text-amber-900" data-testid="set-password-notice">
                    {t.set_password_notice} <a href={set_password_url} className="font-semibold underline">{t.set_password_link}</a>
                </p>
            )}

            <header className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold" data-testid="vendor-name">{vendor.name}</h1>
                    <p className="text-sm text-gray-600">
                        {t.at_akuru} · {t.your_role}: {t[`role_${vendor.role}`] || vendor.role} ·{' '}
                        <a href={`/shop/${vendor.slug}`} target="_blank" rel="noreferrer" className="text-blue-700 underline" data-testid="open-shop-page">{t.open_shop_page}</a>
                    </p>
                </div>
                {memberships.length > 1 && (
                    <label className="text-sm">
                        {t.switch_shop}{' '}
                        <select className="form-input" value={vendor.id} onChange={(e) => router.post('/vendor/switch', { vendor_id: e.target.value })}>
                            {memberships.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                        </select>
                    </label>
                )}
            </header>

            {!vendor.agreement_accepted ? (
                <AgreementGate t={t} agreementUrl={agreement_url} />
            ) : (
                <>
                    <section>
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <h2 className="text-lg font-semibold">{t.products_heading} <span className="text-sm font-normal text-gray-500">{t.products_count.replace(':count', products.length)}</span></h2>
                            <form
                                className="flex flex-wrap gap-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    router.get('/vendor', { q: search || undefined, status: status || undefined }, { preserveState: true });
                                }}
                            >
                                <input className="form-input" placeholder={t.search_products} value={search} onChange={(e) => setSearch(e.target.value)} />
                                <select className="form-input" value={status} onChange={(e) => setStatus(e.target.value)}>
                                    <option value="">{t.all_statuses}</option>
                                    {options.statuses.map((s) => <option key={s} value={s}>{t[`status_${s}`] || s}</option>)}
                                </select>
                                <button type="submit" className="btn-secondary">{t.search}</button>
                                <a href="/vendor/products/export" className="btn-secondary" data-testid="export-products">{t.export_csv}</a>
                                <button type="button" className="btn-primary" onClick={() => setEditing('new')} data-testid="new-product">{t.new_product}</button>
                            </form>
                        </div>
                        {editing && (
                            <ProductEditor
                                key={editing === 'new' ? 'new' : editing.id}
                                product={editing === 'new' ? null : products.find((p) => p.id === editing.id) || editing}
                                options={options}
                                t={t}
                                onDone={() => setEditing(null)}
                            />
                        )}
                        <ProductList products={products} t={t} onEdit={(p) => setEditing(p)} />
                    </section>
                    <Members members={members} isOwner={isOwner} t={t} />
                </>
            )}
        </AppShell>
    );
}
