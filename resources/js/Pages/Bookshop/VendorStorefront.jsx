import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * BOOKSHOP_PLAN slice B4 — the storefront designer, part 1: identity and
 * theme. A form on the left, the real public page rendered from the draft
 * in an iframe on the right (so what the vendor sees is what publishes).
 * Save as draft, publish (owners), roll back to an earlier version.
 * Colour pairs that fail to read are listed with the ratio they reached;
 * publishing refuses until they pass.
 */

const text = (v) => (v === null || v === undefined ? '' : String(v));

function Field({ label, hint, children, className = '' }) {
    return (
        <label className={`block text-sm ${className}`}>
            <span className="mb-1 block font-medium text-gray-700">{label}</span>
            {children}
            {hint && <span className="mt-1 block text-xs text-gray-500">{hint}</span>}
        </label>
    );
}

function Swatches({ colors }) {
    return (
        <span className="inline-flex overflow-hidden rounded border">
            {['primary', 'secondary', 'accent', 'page_bg', 'text'].map((slot) => <span key={slot} className="h-5 w-5" style={{ background: colors[slot] }} />)}
        </span>
    );
}

/** B10c (ADR-039): the shop's own CSS — confined to its page, cleaned, live once the office approves it. */
function CustomCssEditor({ css, isOwner, t, onSaved }) {
    const form = useForm({ css: css.pending ?? css.live ?? '' });
    const status = css.status;
    const bytes = new Blob([form.data.css]).size;

    return (
        <section className="rounded-lg border bg-white p-4" data-testid="custom-css">
            <h2 className="mb-1 text-lg font-semibold">{t.css_heading}</h2>
            <p className="mb-2 text-sm text-gray-600">{t.css_intro}</p>
            {status && (
                <p className={`mb-2 rounded p-2 text-sm ${status === 'approved' ? 'bg-green-50 text-green-800' : status === 'pending' ? 'bg-amber-50 text-amber-900' : 'bg-red-50 text-red-800'}`} data-testid="css-status" data-status={status}>
                    {t[`css_status_${status}`] || status}{css.note && <span className="block text-xs">{css.note}</span>}
                </p>
            )}
            <form onSubmit={(e) => { e.preventDefault(); form.post('/vendor/storefront/css', { preserveScroll: true, onSuccess: onSaved }); }}>
                <textarea className="form-input w-full font-mono text-xs" rows={12} dir="ltr" spellCheck={false} value={form.data.css} onChange={(e) => form.setData('css', e.target.value)} disabled={!isOwner}
                    placeholder={'.sf-hero h2 { letter-spacing: .05em; }\n.sf-card { border-width: 2px; }'} data-testid="css-input" />
                <p className={`text-xs ${bytes > css.max_bytes ? 'text-red-700' : 'text-gray-500'}`}>{bytes} / {css.max_bytes} · {t.css_rules}</p>
                <FormErrors errors={form.errors} className="mt-2" />
                {isOwner && (
                    <div className="mt-2 flex flex-wrap gap-2">
                        <button type="submit" className="btn-primary" disabled={form.processing} data-testid="css-send">{t.css_send}</button>
                        {(css.live || css.pending) && (
                            <button type="button" className="btn-secondary" onClick={() => { if (window.confirm(t.css_remove_confirm)) router.post('/vendor/storefront/css/remove', {}, { preserveScroll: true, onSuccess: () => { form.setData('css', ''); onSaved(); } }); }} data-testid="css-remove">{t.css_remove}</button>
                        )}
                    </div>
                )}
            </form>
        </section>
    );
}

/** B10d (ADR-039): looks the office has published — applied to the draft in one go; and offering this shop's own look. */
function ThemeSwatches({ colors }) {
    return (
        <span className="flex gap-1" aria-hidden="true">
            {['primary', 'secondary', 'accent', 'page_bg', 'text'].map((slot) => <span key={slot} className="inline-block h-5 w-5 rounded border" style={{ background: colors[slot] }} />)}
        </span>
    );
}

function ThemeGallery({ gallery, isOwner, published, t, onApplied }) {
    const offer = useForm({ name: '', description: '' });

    return (
        <section className="rounded-lg border bg-white p-4" data-testid="theme-gallery">
            <h2 className="mb-1 text-lg font-semibold">{t.gallery_heading}</h2>
            <p className="mb-3 text-sm text-gray-600">{t.gallery_intro}</p>
            <ul className="grid gap-3 sm:grid-cols-2">
                {gallery.themes.map((theme) => (
                    <li key={theme.id} className="rounded border p-3" data-testid={`gallery-${theme.slug}`}>
                        <div className="mb-1 flex items-center justify-between gap-2">
                            <span className="font-semibold">{theme.name}</span>
                            <ThemeSwatches colors={theme.colors} />
                        </div>
                        {theme.description && <p className="text-xs text-gray-600">{theme.description}</p>}
                        <p className="mt-1 text-xs text-gray-500">
                            {theme.fonts.heading}{theme.fonts.body !== theme.fonts.heading && ` / ${theme.fonts.body}`}
                            {theme.has_css && ` · ${t.gallery_has_css}`}
                            {theme.by && ` · ${t.gallery_by.replace(':shop', theme.by)}`}
                        </p>
                        {isOwner && (
                            <button type="button" className="btn-secondary mt-2 text-sm" data-testid={`gallery-apply-${theme.slug}`}
                                onClick={() => { if (window.confirm(t.gallery_apply_confirm.replace(':name', theme.name))) router.post(`/vendor/storefront/themes/${theme.id}/apply`, {}, { preserveScroll: true, onSuccess: onApplied }); }}>
                                {t.gallery_apply}
                            </button>
                        )}
                    </li>
                ))}
            </ul>
            {isOwner && (
                <div className="mt-4 border-t pt-3" data-testid="gallery-offer">
                    <h3 className="text-sm font-semibold">{t.gallery_offer_heading}</h3>
                    {gallery.offered ? (
                        <p className="text-sm text-gray-600" data-testid="gallery-offered" data-status={gallery.offered.status}>
                            {(gallery.offered.status === 'declined' ? t.gallery_offer_declined : t.gallery_offer_waiting).replace(':name', gallery.offered.name)}
                            {gallery.offered.note && <span className="block text-xs">{gallery.offered.note}</span>}
                        </p>
                    ) : null}
                    {(!gallery.offered || gallery.offered.status === 'declined') && (
                        published ? (
                            <form className="mt-2 flex flex-wrap items-end gap-2 text-sm" onSubmit={(e) => { e.preventDefault(); offer.post('/vendor/storefront/themes/offer', { preserveScroll: true, onSuccess: () => offer.reset() }); }}>
                                <label>{t.gallery_offer_name}<input className="form-input block w-48" required maxLength={80} value={offer.data.name} onChange={(e) => offer.setData('name', e.target.value)} data-testid="gallery-offer-name" /></label>
                                <label className="flex-1">{t.gallery_offer_description}<input className="form-input block w-full" maxLength={300} value={offer.data.description} onChange={(e) => offer.setData('description', e.target.value)} /></label>
                                <button type="submit" className="btn-secondary" disabled={offer.processing} data-testid="gallery-offer-send">{t.gallery_offer_send}</button>
                                <FormErrors errors={offer.errors} className="w-full" />
                            </form>
                        ) : <p className="text-xs text-gray-500">{t.error_gallery_publish_first}</p>
                    )}
                </div>
            )}
        </section>
    );
}

export default function VendorStorefront({ t, vendor, designer, preview_url, public_url, custom_css, gallery }) {
    const { flash = {}, errors } = usePage().props;
    const isOwner = vendor.role === 'owner';
    const d = designer;
    const form = useForm({
        name_dv: text(d.identity.name_dv), name_ar: text(d.identity.name_ar),
        tagline_dv: text(d.identity.tagline_dv), tagline_ar: text(d.identity.tagline_ar),
        story: text(d.identity.story), story_dv: text(d.identity.story_dv), story_ar: text(d.identity.story_ar),
        contact: { phone: text(d.identity.contact.phone), email: text(d.identity.contact.email), viber: text(d.identity.contact.viber), address: text(d.identity.contact.address), map_url: text(d.identity.contact.map_url) },
        hours: text(d.identity.hours),
        socials: Object.fromEntries(d.options.socials.map((n) => [n, text(d.identity.socials[n])])),
        theme: {
            preset: d.theme.preset || '',
            colors: { ...d.theme.colors },
            fonts: { ...d.theme.fonts, accent: d.theme.fonts.accent || '' },
            scale: d.theme.scale,
            shape: { ...d.theme.shape },
            dark: { enabled: Boolean(d.theme.dark?.enabled), colors: d.theme.dark?.colors || {} },
        },
        images: {},
        remove_images: [],
    });
    const [previewKey, setPreviewKey] = useState(0);
    const [note, setNote] = useState('');
    const setTheme = (patch) => form.setData('theme', { ...form.data.theme, ...patch });
    const setColor = (slot, value) => setTheme({ preset: '', colors: { ...form.data.theme.colors, [slot]: value } });
    const choosePreset = (key) => setTheme({ preset: key, colors: { ...d.options.presets[key].colors } });

    const save = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            theme: { ...data.theme, dark: { ...data.theme.dark, enabled: data.theme.dark.enabled ? 1 : 0 } },
        }));
        form.post('/vendor/storefront/draft', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.setData({ ...form.data, images: {}, remove_images: [] });
                setPreviewKey((k) => k + 1);
            },
        });
    };

    return (
        <AppShell title={t.designer_title}>
            <FormErrors errors={errors} className="mb-4" />
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="flash-success">{flash.success}</p>}
            <header className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold" data-testid="designer-heading">{t.designer_title} · {vendor.name}</h1>
                    <p className="text-sm text-gray-600">
                        <a href="/vendor" className="text-blue-700 underline">{t.portal_title}</a>
                        {' · '}
                        <a href={public_url} target="_blank" rel="noreferrer" className="text-blue-700 underline" data-testid="open-public">{t.open_shop_page}</a>
                        {' · '}
                        <a href="/vendor/storefront/sections" className="text-blue-700 underline" data-testid="open-sections">{t.sections_title}</a>
                        {' · '}
                        {d.published_at ? t.published_on.replace(':date', d.published_at) : t.not_published_yet}
                    </p>
                </div>
            </header>

            <div className="grid gap-6 lg:grid-cols-2">
                <form onSubmit={save} className="space-y-6" data-testid="designer-form">
                    <section className="rounded-lg border bg-white p-4">
                        <h2 className="mb-3 text-lg font-semibold">{t.theme_heading}</h2>
                        <p className="mb-2 text-sm text-gray-600">{t.presets_intro}</p>
                        <div className="mb-4 flex flex-wrap gap-2" data-testid="presets">
                            {Object.entries(d.options.presets).map(([key, preset]) => (
                                <button
                                    key={key}
                                    type="button"
                                    disabled={preset.locked}
                                    title={preset.locked ? t.preset_locked : ''}
                                    data-testid={`preset-${key}`}
                                    className={`flex items-center gap-2 rounded border px-3 py-2 text-sm ${form.data.theme.preset === key ? 'border-gray-900 ring-1 ring-gray-900' : ''} ${preset.locked ? 'opacity-40' : ''}`}
                                    onClick={() => choosePreset(key)}
                                >
                                    <Swatches colors={preset.colors} /> {preset.label}{preset.locked ? ' 🔒' : ''}
                                </button>
                            ))}
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-4" data-testid="colors">
                            {d.options.colors.map((slot) => (
                                <Field key={slot} label={t[`color_${slot}`] || slot}>
                                    <span className="flex items-center gap-2">
                                        <input type="color" value={form.data.theme.colors[slot]} onChange={(e) => setColor(slot, e.target.value.toUpperCase())} aria-label={t[`color_${slot}`] || slot} />
                                        <input className="form-input w-full font-mono text-xs" value={form.data.theme.colors[slot]} onChange={(e) => setColor(slot, e.target.value)} data-testid={`color-${slot}`} />
                                    </span>
                                </Field>
                            ))}
                        </div>
                        {d.problems.length > 0 ? (
                            <ul className="mt-3 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800" data-testid="contrast-problems">
                                <li className="mb-1 font-semibold">{t.contrast_failing}</li>
                                {d.problems.map((p) => (
                                    <li key={`${p.scheme}-${p.pair}`}>{t.contrast_problem.replace(':pair', p.pair.split('/').map((s) => t[`color_${s}`] || s).join(' / ')).replace(':ratio', p.ratio).replace(':needed', p.needed)}{p.scheme === 'dark' ? ` (${t.dark_scheme})` : ''}</li>
                                ))}
                            </ul>
                        ) : d.exists && (
                            <p className="mt-3 rounded bg-green-50 p-2 text-sm text-green-800" data-testid="contrast-ok">{t.contrast_ok}</p>
                        )}

                        <div className="mt-4 grid gap-3 md:grid-cols-3">
                            <Field label={t.font_heading}>
                                <select className="form-input w-full" value={form.data.theme.fonts.heading} onChange={(e) => setTheme({ fonts: { ...form.data.theme.fonts, heading: e.target.value } })} data-testid="font-heading">
                                    {d.options.fonts.latin.map((f) => <option key={f} value={f}>{f}</option>)}
                                </select>
                            </Field>
                            <Field label={t.font_body}>
                                <select className="form-input w-full" value={form.data.theme.fonts.body} onChange={(e) => setTheme({ fonts: { ...form.data.theme.fonts, body: e.target.value } })} data-testid="font-body">
                                    {d.options.fonts.latin.map((f) => <option key={f} value={f}>{f}</option>)}
                                </select>
                            </Field>
                            <Field label={t.font_accent} hint={t.font_accent_hint}>
                                <select className="form-input w-full" value={form.data.theme.fonts.accent} onChange={(e) => setTheme({ fonts: { ...form.data.theme.fonts, accent: e.target.value } })}>
                                    <option value="">{t.none}</option>
                                    {d.options.fonts.latin.map((f) => <option key={f} value={f}>{f}</option>)}
                                </select>
                            </Field>
                            <Field label={t.font_dhivehi}>
                                <select className="form-input w-full" value={form.data.theme.fonts.dhivehi} onChange={(e) => setTheme({ fonts: { ...form.data.theme.fonts, dhivehi: e.target.value } })}>
                                    {d.options.fonts.dhivehi.map((f) => <option key={f} value={f}>{f}</option>)}
                                </select>
                            </Field>
                            <Field label={t.font_arabic}>
                                <select className="form-input w-full" value={form.data.theme.fonts.arabic} onChange={(e) => setTheme({ fonts: { ...form.data.theme.fonts, arabic: e.target.value } })}>
                                    {d.options.fonts.arabic.map((f) => <option key={f} value={f}>{f}</option>)}
                                </select>
                            </Field>
                            <Field label={t.scale}>
                                <select className="form-input w-full" value={form.data.theme.scale} onChange={(e) => setTheme({ scale: e.target.value })}>
                                    {d.options.scales.map((s) => <option key={s} value={s}>{t[`scale_${s}`] || s}</option>)}
                                </select>
                            </Field>
                            {Object.entries(d.options.shapes).map(([key, choices]) => (
                                <Field key={key} label={t[`shape_${key}`] || key}>
                                    <select className="form-input w-full" value={form.data.theme.shape[key]} onChange={(e) => setTheme({ shape: { ...form.data.theme.shape, [key]: e.target.value } })} data-testid={`shape-${key}`}>
                                        {choices.map((c) => <option key={c} value={c}>{t[`shape_${key}_${c}`] || c}</option>)}
                                    </select>
                                </Field>
                            ))}
                        </div>
                        <label className="mt-3 flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={form.data.theme.dark.enabled} onChange={(e) => setTheme({ dark: { ...form.data.theme.dark, enabled: e.target.checked } })} data-testid="dark-enabled" />
                            {t.dark_mode}
                        </label>
                        <p className="text-xs text-gray-500">{t.dark_mode_hint}</p>
                    </section>

                    <section className="rounded-lg border bg-white p-4">
                        <h2 className="mb-3 text-lg font-semibold">{t.identity_heading}</h2>
                        <div className="grid gap-3 md:grid-cols-3">
                            {['logo', 'logo_dark', 'banner'].map((slot) => (
                                <Field key={slot} label={t[`image_${slot}`]} hint={t[`image_${slot}_hint`]}>
                                    {d.image_previews[slot] && !form.data.remove_images.includes(slot) && (
                                        <span className="mb-1 block">
                                            <img src={d.image_previews[slot]} alt="" className={slot === 'banner' ? 'h-16 w-full rounded object-cover' : 'h-16 w-16 rounded object-cover'} data-testid={`preview-${slot}`} />
                                            <button type="button" className="text-xs text-red-700 underline" onClick={() => form.setData('remove_images', [...form.data.remove_images, slot])}>{t.remove}</button>
                                        </span>
                                    )}
                                    <input type="file" accept="image/jpeg,image/png,image/webp" className="block w-full text-xs" onChange={(e) => form.setData('images', { ...form.data.images, [slot]: e.target.files?.[0] || null })} data-testid={`image-${slot}`} />
                                </Field>
                            ))}
                            <Field label={t.name_dv}><input dir="rtl" className="form-input w-full" value={form.data.name_dv} onChange={(e) => form.setData('name_dv', e.target.value)} /></Field>
                            <Field label={t.name_ar}><input dir="rtl" className="form-input w-full" value={form.data.name_ar} onChange={(e) => form.setData('name_ar', e.target.value)} /></Field>
                            <span />
                            <Field label={t.tagline_dv}><input dir="rtl" className="form-input w-full" value={form.data.tagline_dv} onChange={(e) => form.setData('tagline_dv', e.target.value)} /></Field>
                            <Field label={t.tagline_ar}><input dir="rtl" className="form-input w-full" value={form.data.tagline_ar} onChange={(e) => form.setData('tagline_ar', e.target.value)} /></Field>
                            <span />
                            <Field label={t.story} hint={t.description_hint} className="md:col-span-3"><textarea className="form-input w-full" rows={5} value={form.data.story} onChange={(e) => form.setData('story', e.target.value)} data-testid="story" /></Field>
                            <Field label={t.story_dv}><textarea dir="rtl" className="form-input w-full" rows={3} value={form.data.story_dv} onChange={(e) => form.setData('story_dv', e.target.value)} /></Field>
                            <Field label={t.story_ar}><textarea dir="rtl" className="form-input w-full" rows={3} value={form.data.story_ar} onChange={(e) => form.setData('story_ar', e.target.value)} /></Field>
                            <span />
                            <Field label={t.contact_phone}><input className="form-input w-full" value={form.data.contact.phone} onChange={(e) => form.setData('contact', { ...form.data.contact, phone: e.target.value })} data-testid="contact-phone" /></Field>
                            <Field label="Viber"><input className="form-input w-full" value={form.data.contact.viber} onChange={(e) => form.setData('contact', { ...form.data.contact, viber: e.target.value })} /></Field>
                            <Field label={t.contact_email}><input className="form-input w-full" type="email" value={form.data.contact.email} onChange={(e) => form.setData('contact', { ...form.data.contact, email: e.target.value })} /></Field>
                            <Field label={t.address} className="md:col-span-2"><input className="form-input w-full" value={form.data.contact.address} onChange={(e) => form.setData('contact', { ...form.data.contact, address: e.target.value })} /></Field>
                            <Field label={t.map_url} hint={t.map_url_hint}><input className="form-input w-full" value={form.data.contact.map_url} onChange={(e) => form.setData('contact', { ...form.data.contact, map_url: e.target.value })} /></Field>
                            <Field label={t.opening_hours} className="md:col-span-3"><textarea className="form-input w-full" rows={2} value={form.data.hours} onChange={(e) => form.setData('hours', e.target.value)} /></Field>
                            {d.options.socials.map((n) => (
                                <Field key={n} label={t[`social_${n}`]}><input className="form-input w-full" placeholder="https://" value={form.data.socials[n]} onChange={(e) => form.setData('socials', { ...form.data.socials, [n]: e.target.value })} data-testid={`social-${n}`} /></Field>
                            ))}
                        </div>
                    </section>

                    <FormErrors errors={form.errors} />
                    <div className="flex flex-wrap items-center gap-3">
                        <button type="submit" className="btn-secondary" disabled={form.processing} data-testid="save-draft">{t.save_draft}</button>
                        {isOwner && (
                            <>
                                <input className="form-input w-56" placeholder={t.version_note} value={note} onChange={(e) => setNote(e.target.value)} data-testid="version-note" />
                                <button
                                    type="button"
                                    className="btn-primary"
                                    disabled={!d.exists || d.problems.length > 0}
                                    title={d.problems.length > 0 ? t.contrast_failing : ''}
                                    data-testid="publish"
                                    onClick={() => router.post('/vendor/storefront/publish', { note }, { preserveScroll: true, onSuccess: () => { setNote(''); setPreviewKey((k) => k + 1); } })}
                                >
                                    {t.publish}
                                </button>
                            </>
                        )}
                        {d.draft_dirty && <span className="text-xs text-amber-800" data-testid="draft-dirty">{t.draft_differs}</span>}
                    </div>
                </form>

                <div className="space-y-4">
                    <section className="rounded-lg border bg-white p-2">
                        <div className="mb-2 flex items-center justify-between px-2 text-sm">
                            <span className="font-semibold">{t.preview_heading}</span>
                            <button type="button" className="text-blue-700 underline" onClick={() => setPreviewKey((k) => k + 1)}>{t.refresh}</button>
                        </div>
                        <iframe key={previewKey} title={t.preview_heading} src={preview_url} className="h-[70vh] w-full rounded border" data-testid="preview-frame" />
                        <p className="mt-1 px-2 text-xs text-gray-500">{t.preview_hint}</p>
                    </section>

                    {gallery && <ThemeGallery gallery={gallery} isOwner={isOwner} published={d.versions.length > 0} t={t} onApplied={() => window.location.reload()} />}
                    {custom_css && <CustomCssEditor css={custom_css} isOwner={isOwner} t={t} onSaved={() => setPreviewKey((k) => k + 1)} />}

                    <section className="rounded-lg border bg-white p-4" data-testid="versions">
                        <h2 className="mb-2 text-lg font-semibold">{t.versions_heading}</h2>
                        {d.versions.length === 0 ? (
                            <p className="text-sm text-gray-600">{t.no_versions}</p>
                        ) : (
                            <ul className="divide-y text-sm">
                                {d.versions.map((v) => (
                                    <li key={v.id} className="flex flex-wrap items-center justify-between gap-2 py-2" data-testid={`version-${v.number}`} data-live={v.live ? '1' : '0'}>
                                        <span>
                                            <span className="font-semibold">v{v.number}</span>
                                            {v.live && <span className="ms-2 rounded bg-green-100 px-2 py-0.5 text-xs text-green-800">{t.live}</span>}
                                            <span className="ms-2 inline-block h-3 w-3 rounded-full align-middle" style={{ background: v.primary || '#999' }} />
                                            <span className="ms-2 text-gray-600">{v.note || (v.preset ? d.options.presets[v.preset]?.label : t.custom_palette)} · {v.created_at}</span>
                                        </span>
                                        {isOwner && !v.live && <button type="button" className="text-blue-700 underline" data-testid={`roll-back-${v.number}`} onClick={() => router.post(`/vendor/storefront/versions/${v.id}/roll-back`, {}, { preserveScroll: true, onSuccess: () => setPreviewKey((k) => k + 1) })}>{t.roll_back}</button>}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </AppShell>
    );
}
