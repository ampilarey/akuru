import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * BOOKSHOP_PLAN slice B1a — the vendor portal. A member accepts the Vendor
 * Agreement once, then lists products: photos, prices, stock, tax class,
 * variants, and the book or educational details the shop will show. B2 adds
 * the owner's delivery methods.
 */

const DETAIL_BOOK = ['author', 'publisher', 'year', 'pages', 'language'];
const DETAIL_EDU = ['age_range', 'grade', 'subject'];

function blankProduct() {
    return {
        title: '', title_dv: '', title_ar: '', summary: '', summary_dv: '', summary_ar: '',
        badge: '', badge_dv: '', badge_ar: '',
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
        badge: text(p.badge), badge_dv: text(p.badge_dv), badge_ar: text(p.badge_ar),
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
                <Field label={t.summary} className="md:col-span-2">
                    <input className="form-input w-full" value={form.data.summary} onChange={set('summary')} maxLength={500} />
                </Field>
                <Field label={t.product_badge} hint={t.product_badge_hint}>
                    <input className="form-input w-full" value={form.data.badge} onChange={set('badge')} maxLength={40} data-testid="product-badge" />
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
                    <Field label={t.product_badge_dv}><input dir="rtl" className="form-input w-full" value={form.data.badge_dv} onChange={set('badge_dv')} maxLength={40} /></Field>
                    <Field label={t.product_badge_ar}><input dir="rtl" className="form-input w-full" value={form.data.badge_ar} onChange={set('badge_ar')} maxLength={40} /></Field>
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

/**
 * B2: the owner's delivery methods, replaced as a whole. Until the shop
 * sets its own, the office's standard methods apply at checkout.
 */
function DeliveryMethods({ methods, kinds, isOwner, t }) {
    const form = useForm({ methods: methods.map((m) => ({ ...m, free_over: m.free_over ?? '', minimum_order: m.minimum_order ?? '', note: m.note ?? '', name_dv: m.name_dv ?? '', name_ar: m.name_ar ?? '' })) });
    const setRow = (index, key, value) => form.setData('methods', form.data.methods.map((m, i) => (i === index ? { ...m, [key]: value } : m)));
    const addRow = () => form.setData('methods', [...form.data.methods, { kind: kinds[0], name: '', name_dv: '', name_ar: '', fee: '0', free_over: '', minimum_order: '', carrier_paid_on_arrival: false, handling_days: 1, note: '', is_active: true }]);

    return (
        <section className="mt-8" data-testid="delivery-methods">
            <h2 className="mb-1 text-lg font-semibold">{t.delivery_methods_heading}</h2>
            <p className="mb-3 text-sm text-gray-600">{t.delivery_methods_intro}</p>
            {methods.length === 0 && (
                <p className="mb-3 rounded border bg-white p-3 text-sm text-gray-600" data-testid="no-methods">
                    {t.no_methods_yet}{' '}
                    {isOwner && <button type="button" className="text-blue-700 underline" data-testid="use-template" onClick={() => router.post('/vendor/delivery-methods/template', {}, { preserveScroll: true })}>{t.use_template}</button>}
                </p>
            )}
            {isOwner ? (
                <form
                    className="rounded border bg-white p-3"
                    data-testid="delivery-form"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) => ({ methods: data.methods.map((m) => ({ ...m, carrier_paid_on_arrival: m.carrier_paid_on_arrival ? 1 : 0, is_active: m.is_active ? 1 : 0 })) }));
                        form.post('/vendor/delivery-methods', { preserveScroll: true });
                    }}
                >
                    {form.data.methods.map((m, index) => (
                        <div key={m.id ?? `new-${index}`} className="mb-3 grid gap-2 border-b pb-3 md:grid-cols-6" data-testid={`delivery-row-${index}`}>
                            <select className="form-input" value={m.kind} onChange={(e) => setRow(index, 'kind', e.target.value)} aria-label={t.kind}>
                                {kinds.map((k) => <option key={k} value={k}>{t[`kind_${k}`] || k}</option>)}
                            </select>
                            <input className="form-input md:col-span-2" placeholder={t.name} value={m.name} onChange={(e) => setRow(index, 'name', e.target.value)} data-testid={`delivery-name-${index}`} />
                            <input className="form-input" type="number" step="0.01" min="0" placeholder={t.fee} value={m.fee} onChange={(e) => setRow(index, 'fee', e.target.value)} disabled={m.kind === 'boat'} aria-label={t.fee} data-testid={`delivery-fee-${index}`} />
                            <input className="form-input" type="number" step="0.01" min="0" placeholder={t.free_over} value={m.free_over} onChange={(e) => setRow(index, 'free_over', e.target.value)} aria-label={t.free_over} />
                            <input className="form-input" type="number" step="0.01" min="0" placeholder={t.minimum_order} value={m.minimum_order} onChange={(e) => setRow(index, 'minimum_order', e.target.value)} aria-label={t.minimum_order} />
                            <input className="form-input" type="number" min="0" max="60" placeholder={t.handling_days} value={m.handling_days} onChange={(e) => setRow(index, 'handling_days', e.target.value)} aria-label={t.handling_days} />
                            <input className="form-input md:col-span-2" placeholder={t.note} value={m.note} onChange={(e) => setRow(index, 'note', e.target.value)} />
                            <input className="form-input" dir="rtl" placeholder={t.name_dv} value={m.name_dv} onChange={(e) => setRow(index, 'name_dv', e.target.value)} />
                            <input className="form-input" dir="rtl" placeholder={t.name_ar} value={m.name_ar} onChange={(e) => setRow(index, 'name_ar', e.target.value)} />
                            <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={Boolean(m.is_active)} onChange={(e) => setRow(index, 'is_active', e.target.checked)} /> {t.active}</label>
                            <button type="button" className="text-sm text-red-700 underline" onClick={() => form.setData('methods', form.data.methods.filter((_, i) => i !== index))}>{t.remove}</button>
                        </div>
                    ))}
                    <FormErrors errors={form.errors} className="mb-2" />
                    <div className="flex gap-3">
                        <button type="button" className="btn-secondary" onClick={addRow} data-testid="add-method">{t.add_method}</button>
                        <button type="submit" className="btn-primary" disabled={form.processing} data-testid="save-methods">{t.save_methods}</button>
                    </div>
                </form>
            ) : (
                <ul className="divide-y rounded border bg-white text-sm">
                    {methods.map((m) => <li key={m.id} className="p-2">{m.name} · {t[`kind_${m.kind}`] || m.kind} · {m.fee}</li>)}
                </ul>
            )}
        </section>
    );
}

/**
 * B3: the return window (seven days at least, decision 8) and conditions,
 * and holiday mode — products stay visible marked "back on", the cart
 * refuses them, the shop page shows the notice. Owners edit; staff read.
 */
function ShopSettings({ settings, isOwner, t }) {
    const form = useForm({
        return_window_days: String(settings.return_window_days),
        return_conditions: settings.return_conditions || '',
        holiday_from: settings.holiday_from || '',
        holiday_until: settings.holiday_until || '',
        holiday_notice: settings.holiday_notice || '',
        free_delivery_over: settings.free_delivery_over || '',
        cod_enabled: !!settings.cod_enabled,
        cod_max: settings.cod_max || '',
    });
    const set = (name) => (e) => form.setData(name, e.target.value);

    return (
        <section className="mt-8" data-testid="shop-settings">
            <h2 className="mb-1 text-lg font-semibold">{t.shop_settings_heading}</h2>
            {settings.on_holiday && <p className="mb-2 rounded bg-amber-50 p-2 text-sm text-amber-900" data-testid="on-holiday">{t.on_holiday_now}</p>}
            <form
                className="grid gap-3 rounded border bg-white p-3 md:grid-cols-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/vendor/settings', { preserveScroll: true });
                }}
            >
                <Field label={t.return_window_days} hint={t.return_window_hint.replace(':min', settings.minimum_window)}>
                    <input className="form-input w-full" type="number" min={settings.minimum_window} max="60" value={form.data.return_window_days} onChange={set('return_window_days')} disabled={!isOwner} data-testid="return-window" />
                </Field>
                <Field label={t.return_conditions} className="md:col-span-2">
                    <textarea className="form-input w-full" rows={2} value={form.data.return_conditions} onChange={set('return_conditions')} disabled={!isOwner} />
                </Field>
                <Field label={t.holiday_from}><input className="form-input w-full" type="date" value={form.data.holiday_from} onChange={set('holiday_from')} disabled={!isOwner} data-testid="holiday-from" /></Field>
                <Field label={t.holiday_until}><input className="form-input w-full" type="date" value={form.data.holiday_until} onChange={set('holiday_until')} disabled={!isOwner} data-testid="holiday-until" /></Field>
                <Field label={t.holiday_notice} hint={t.holiday_hint}><input className="form-input w-full" value={form.data.holiday_notice} onChange={set('holiday_notice')} disabled={!isOwner} data-testid="holiday-notice" /></Field>
                <Field label={t.free_delivery_over} hint={t.free_delivery_over_hint}><input className="form-input w-full" type="number" min="0" step="1" value={form.data.free_delivery_over} onChange={set('free_delivery_over')} disabled={!isOwner} data-testid="free-delivery-over" /></Field>
                {/* B9b: cash on delivery — the shop's own collection and couriers only; the office can switch it off for everyone. */}
                <Field label={t.cod_label} hint={settings.cod_office_on ? t.cod_hint : t.cod_office_off}>
                    <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.cod_enabled} onChange={(e) => form.setData('cod_enabled', e.target.checked)} disabled={!isOwner || !settings.cod_office_on} data-testid="cod-enabled" /> {t.cod_take_cash}</label>
                </Field>
                <Field label={t.cod_max} hint={t.cod_max_hint}><input className="form-input w-full" type="number" min="0" step="1" value={form.data.cod_max} onChange={set('cod_max')} disabled={!isOwner || !form.data.cod_enabled} data-testid="cod-max" /></Field>
                <FormErrors errors={form.errors} className="md:col-span-3" />
                {isOwner && <div className="md:col-span-3"><button type="submit" className="btn-primary" disabled={form.processing} data-testid="save-settings">{t.save}</button></div>}
            </form>
        </section>
    );
}

/** B7 (§6.5): the shop's own discount codes — funded by the shop, good on its products only. */
function DiscountCodes({ codes, isOwner, t }) {
    const blank = { code: '', name: '', discount_type: 'percentage', discount_value: '', minimum_order_amount: '', max_discount_amount: '', usage_limit: '', per_user_limit: '1', starts_at: '', ends_at: '' };
    const form = useForm(blank);
    const set = (name) => (e) => form.setData(name, e.target.value);

    return (
        <section className="mt-8" data-testid="discount-codes">
            <h2 className="mb-1 text-lg font-semibold">{t.discount_codes_heading}</h2>
            <p className="mb-2 text-sm text-gray-600">{t.discount_codes_intro}</p>
            {codes.length > 0 && (
                <table className="mb-3 w-full rounded border bg-white text-sm">
                    <thead className="bg-gray-50"><tr><th className="p-2 text-start">{t.discount_code}</th><th className="p-2 text-start">{t.discount}</th><th className="p-2 text-start">{t.code_valid}</th><th className="p-2 text-end">{t.code_used}</th><th className="p-2 text-start">{t.status}</th><th className="p-2" /></tr></thead>
                    <tbody>
                        {codes.map((c) => (
                            <tr key={c.id} className="border-t" data-testid={`code-${c.code}`} data-code-status={c.status}>
                                <td className="p-2 font-mono">{c.code}<span className="block text-xs text-gray-500">{c.name}</span></td>
                                <td className="p-2">{c.discount_type === 'percentage' ? `${Number(c.discount_value)}%` : `MVR ${c.discount_value}`}{c.minimum_order_amount && <span className="block text-xs text-gray-500">{t.code_minimum.replace(':amount', c.minimum_order_amount)}</span>}</td>
                                <td className="p-2 text-xs">{c.starts_at || '—'} → {c.ends_at || '—'}</td>
                                <td className="p-2 text-end">{c.used_count}{c.usage_limit ? ` / ${c.usage_limit}` : ''}<span className="block text-xs text-gray-500">MVR {c.discounted_total}</span></td>
                                <td className="p-2">{c.status === 'active' ? t.active : t.inactive}</td>
                                <td className="p-2 text-end">{isOwner && <button type="button" className="text-blue-700 underline" onClick={() => router.post(`/vendor/discount-codes/${c.id}/status`, { active: c.status === 'active' ? 0 : 1 }, { preserveScroll: true })} data-testid={`toggle-code-${c.code}`}>{c.status === 'active' ? t.switch_off : t.switch_on}</button>}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
            {isOwner ? (
                <form className="grid gap-3 rounded border bg-white p-3 md:grid-cols-4" data-testid="code-form" onSubmit={(e) => { e.preventDefault(); form.post('/vendor/discount-codes', { preserveScroll: true, onSuccess: () => form.reset() }); }}>
                    <Field label={t.discount_code}><input className="form-input w-full font-mono uppercase" value={form.data.code} onChange={set('code')} maxLength={20} required data-testid="code-code" /></Field>
                    <Field label={t.code_type}>
                        <select className="form-input w-full" value={form.data.discount_type} onChange={set('discount_type')} data-testid="code-type">
                            <option value="percentage">{t.code_percentage}</option>
                            <option value="fixed">{t.code_fixed}</option>
                        </select>
                    </Field>
                    <Field label={t.code_value}><input className="form-input w-full" type="number" min="0.01" step="0.01" value={form.data.discount_value} onChange={set('discount_value')} required data-testid="code-value" /></Field>
                    <Field label={t.code_minimum_label}><input className="form-input w-full" type="number" min="0" step="1" value={form.data.minimum_order_amount} onChange={set('minimum_order_amount')} /></Field>
                    <Field label={t.code_usage_limit}><input className="form-input w-full" type="number" min="1" value={form.data.usage_limit} onChange={set('usage_limit')} /></Field>
                    <Field label={t.code_per_user}><input className="form-input w-full" type="number" min="1" value={form.data.per_user_limit} onChange={set('per_user_limit')} /></Field>
                    <Field label={t.from}><input className="form-input w-full" type="date" value={form.data.starts_at} onChange={set('starts_at')} /></Field>
                    <Field label={t.until}><input className="form-input w-full" type="date" value={form.data.ends_at} onChange={set('ends_at')} /></Field>
                    <FormErrors errors={form.errors} className="md:col-span-4" />
                    <div className="md:col-span-4"><button type="submit" className="btn-primary" disabled={form.processing} data-testid="save-code">{t.create_code}</button></div>
                </form>
            ) : (
                <p className="text-sm text-gray-600">{t.owner_manages_codes}</p>
            )}
        </section>
    );
}

/** B8 (§5 "Notifications: which events email or SMS them"): every notice is in the app; these add email or SMS. */
function Notices({ settings, isOwner, t }) {
    const form = useForm({ events: settings.events });
    const set = (event, channel, value) => form.setData('events', { ...form.data.events, [event]: { ...form.data.events[event], [channel]: value } });
    const office = settings.office;

    return (
        <section className="mt-8" data-testid="shop-notices">
            <h2 className="mb-1 text-lg font-semibold">{t.notices_heading}</h2>
            <p className="mb-2 text-sm text-gray-600">{t.notices_hint}{!settings.phone && <span className="ms-1 text-amber-800">{t.notices_no_phone}</span>}</p>
            {(!office.vendor_email || !office.vendor_sms) && (
                <p className="mb-2 rounded bg-gray-50 p-2 text-xs text-gray-600" data-testid="notices-office-off">
                    {!office.vendor_email && t.notices_email_off_by_office} {!office.vendor_sms && t.notices_sms_off_by_office}
                </p>
            )}
            <form onSubmit={(e) => { e.preventDefault(); form.post('/vendor/notices', { preserveScroll: true }); }}>
                <table className="w-full rounded border bg-white text-sm">
                    <thead className="bg-gray-50"><tr><th className="p-2 text-start">{t.notice_event}</th><th className="p-2">{t.in_app}</th><th className="p-2">{t.email}</th><th className="p-2">SMS</th></tr></thead>
                    <tbody>
                        {Object.keys(form.data.events).map((event) => (
                            <tr key={event} className="border-t" data-testid={`notice-${event}`}>
                                <td className="p-2">{t[`notice_event_${event}`] || event}</td>
                                <td className="p-2 text-center">✓</td>
                                <td className="p-2 text-center"><input type="checkbox" checked={!!form.data.events[event].email} disabled={!isOwner || !office.vendor_email} onChange={(e) => set(event, 'email', e.target.checked)} data-testid={`notice-${event}-email`} /></td>
                                <td className="p-2 text-center"><input type="checkbox" checked={!!form.data.events[event].sms} disabled={!isOwner || !office.vendor_sms} onChange={(e) => set(event, 'sms', e.target.checked)} data-testid={`notice-${event}-sms`} /></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {isOwner && <button type="submit" className="btn-primary mt-2" disabled={form.processing} data-testid="save-notices">{t.save}</button>}
            </form>
        </section>
    );
}

/** B9c (§6.3 "Newsletter"): who asked for the shop's news. Add the Newsletter section on the storefront to collect them. */
function Newsletter({ newsletter, t }) {
    return (
        <section className="mt-8" data-testid="shop-newsletter">
            <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold">{t.newsletter_list_heading} <span className="text-sm font-normal text-gray-500" data-testid="newsletter-count">{t.newsletter_count.replace(':count', newsletter.active)}</span></h2>
                <a href="/vendor/newsletter/export" className="btn-secondary" data-testid="export-newsletter">{t.export_csv}</a>
            </div>
            <p className="mb-2 text-sm text-gray-600">{t.newsletter_list_hint}</p>
            {newsletter.recent.length > 0 && (
                <ul className="divide-y rounded border bg-white text-sm">
                    {newsletter.recent.map((s) => <li key={s.email} className="flex justify-between gap-2 p-2"><span>{s.name ? `${s.name} · ` : ''}{s.email}</span><span className="text-gray-500">{s.since}</span></li>)}
                </ul>
            )}
        </section>
    );
}

function ProductList({ products, t, onEdit, selected, setSelected }) {
    if (products.length === 0) {
        return <p className="rounded border bg-white p-4 text-gray-600">{t.no_products}</p>;
    }
    const all = products.every((p) => selected.includes(p.id));
    const toggle = (id) => setSelected(selected.includes(id) ? selected.filter((x) => x !== id) : [...selected, id]);

    return (
        <table className="w-full overflow-hidden rounded border bg-white text-sm" data-testid="product-list">
            <thead className="bg-gray-50 text-start">
                <tr>
                    <th className="p-2 text-start"><input type="checkbox" checked={all} onChange={() => setSelected(all ? [] : products.map((p) => p.id))} aria-label={t.select_all} data-testid="select-all" /></th>
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
                        <td className="p-2"><input type="checkbox" checked={selected.includes(p.id)} onChange={() => toggle(p.id)} aria-label={p.title} data-testid={`select-${p.slug}`} /></td>
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
                            <button type="button" className="ms-3 text-blue-700 underline" onClick={() => router.post(`/vendor/products/${p.id}/duplicate`, {}, { preserveScroll: true })} data-testid={`duplicate-${p.slug}`}>{t.duplicate}</button>
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

export default function Vendor({ t, vendor, memberships = [], agreement_url, products = [], products_page = null, members = [], delivery_methods = [], delivery_kinds = [], shop_settings = null, discount_codes = [], notice_settings = null, newsletter = null, options, filters, must_set_password, set_password_url }) {
    const { flash = {}, errors } = usePage().props;
    const [editing, setEditing] = useState(null);
    const [search, setSearch] = useState(filters.q || '');
    const [status, setStatus] = useState(filters.status || '');
    const [low, setLow] = useState(!!filters.low);
    const [category, setCategory] = useState(filters.category || '');
    const [selected, setSelected] = useState([]);
    const [bulkStatus, setBulkStatus] = useState('active');
    const isOwner = vendor.role === 'owner';
    const listQuery = (page) => ({ q: search || undefined, status: status || undefined, low: low ? 1 : undefined, category: category || undefined, page: page > 1 ? page : undefined });
    const goPage = (page) => router.get('/vendor', listQuery(page), { preserveState: true, preserveScroll: true, onSuccess: () => setSelected([]) });

    return (
        <AppShell title={t.portal_title}>
            <FormErrors errors={errors} className="mb-4" />
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="flash-success">{flash.success}</p>}
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
                    {vendor.agreement_accepted && (
                        <span className="mt-2 inline-flex gap-2">
                            <a href="/vendor/orders" className="btn-primary" data-testid="open-orders">{t.orders_title}</a>
                            <a href="/vendor/storefront" className="btn-secondary" data-testid="open-designer">{t.designer_title}</a>
                            <a href="/vendor/storefront/sections" className="btn-secondary" data-testid="open-sections">{t.sections_title}</a>
                            <a href="/vendor/money" className="btn-secondary" data-testid="open-money">{t.money_title}</a>
                            <a href="/vendor/reviews" className="btn-secondary" data-testid="open-reviews">{t.reviews_heading}</a>
                            <a href="/vendor/stock" className="btn-secondary" data-testid="open-stock">{t.stock_title}</a>
                            <a href="/vendor/quotes" className="btn-secondary" data-testid="open-quotes">{t.quotes_title}</a>
                            <a href="/vendor/insights" className="btn-secondary" data-testid="open-insights">{t.insights_title}</a>
                        </span>
                    )}
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
                            <h2 className="text-lg font-semibold">{t.products_heading} <span className="text-sm font-normal text-gray-500" data-testid="products-total">{t.products_count.replace(':count', products_page ? products_page.total : products.length)}</span></h2>
                            <form
                                className="flex flex-wrap gap-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    goPage(1);
                                }}
                            >
                                <input className="form-input" placeholder={t.search_products} value={search} onChange={(e) => setSearch(e.target.value)} />
                                <select className="form-input" value={status} onChange={(e) => setStatus(e.target.value)}>
                                    <option value="">{t.all_statuses}</option>
                                    {options.statuses.map((s) => <option key={s} value={s}>{t[`status_${s}`] || s}</option>)}
                                </select>
                                <select className="form-input" value={category} onChange={(e) => setCategory(e.target.value)} data-testid="filter-category">
                                    <option value="">{t.all_categories}</option>
                                    {options.categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                                <label className="flex items-center gap-1 text-sm"><input type="checkbox" checked={low} onChange={(e) => setLow(e.target.checked)} data-testid="filter-low" /> {t.low_stock_only}</label>
                                <button type="submit" className="btn-secondary" data-testid="filter-products">{t.search}</button>
                                <a href="/vendor/products/export" className="btn-secondary" data-testid="export-products">{t.export_csv}</a>
                                <a href="/vendor/stock#import" className="btn-secondary" data-testid="open-import">{t.import_heading}</a>
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
                        {selected.length > 0 && (
                            <div className="mb-2 flex flex-wrap items-center gap-2 rounded border border-blue-200 bg-blue-50 p-2 text-sm" data-testid="bulk-bar">
                                <span>{t.selected_count.replace(':count', selected.length)}</span>
                                <select className="form-input" value={bulkStatus} onChange={(e) => setBulkStatus(e.target.value)} data-testid="bulk-status">
                                    {['active', 'draft', 'archived'].map((s) => <option key={s} value={s}>{t[`bulk_to_${s}`]}</option>)}
                                </select>
                                <button type="button" className="btn-primary" onClick={() => router.post('/vendor/products/bulk', { ids: selected, status: bulkStatus }, { preserveScroll: true, onSuccess: () => setSelected([]) })} data-testid="bulk-apply">{t.apply}</button>
                                <button type="button" className="text-gray-600 underline" onClick={() => setSelected([])}>{t.cancel}</button>
                            </div>
                        )}
                        <ProductList products={products} t={t} onEdit={(p) => setEditing(p)} selected={selected} setSelected={setSelected} />
                        {products_page && products_page.last_page > 1 && (
                            <nav className="mt-2 flex items-center gap-2 text-sm" aria-label={t.pages} data-testid="products-pages">
                                <button type="button" className="btn-secondary" disabled={products_page.page <= 1} onClick={() => goPage(products_page.page - 1)} data-testid="products-prev">{t.previous}</button>
                                <span data-testid="products-page">{t.page_of.replace(':page', products_page.page).replace(':pages', products_page.last_page)}</span>
                                <button type="button" className="btn-secondary" disabled={products_page.page >= products_page.last_page} onClick={() => goPage(products_page.page + 1)} data-testid="products-next">{t.next}</button>
                            </nav>
                        )}
                    </section>
                    {/* Keyed on the rows, so the form re-reads them after the template or a save (useForm keeps its first values otherwise). */}
                    {shop_settings && <ShopSettings settings={shop_settings} isOwner={isOwner} t={t} />}
                    <DiscountCodes codes={discount_codes} isOwner={isOwner} t={t} />
                    {notice_settings && <Notices key={JSON.stringify(notice_settings.events)} settings={notice_settings} isOwner={isOwner} t={t} />}
                    {newsletter && <Newsletter newsletter={newsletter} t={t} />}
                    <DeliveryMethods key={delivery_methods.map((m) => `${m.id}:${m.name}`).join('|')} methods={delivery_methods} kinds={delivery_kinds} isOwner={isOwner} t={t} />
                    <Members members={members} isOwner={isOwner} t={t} />
                </>
            )}
        </AppShell>
    );
}
