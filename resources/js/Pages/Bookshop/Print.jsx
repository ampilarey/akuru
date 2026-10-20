import { useState } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * NAME_TAGS_PLAN §4 (NT1): the print desk's office. Its settings and the
 * four lists it prices and prints from — papers, finishing, layouts,
 * subject sets — each a table with one form that adds a row or, after
 * *Edit*, changes it. Nothing is deleted: a paper, a finishing or a layout
 * is retired, and a job priced on it keeps its name.
 */
const fill = (s, vars) => Object.entries(vars).reduce((out, [k, v]) => out.split(`:${k}`).join(String(v ?? '')), s || '');
const Err = ({ children }) => (children ? <p className="mt-1 text-xs text-red-700">{children}</p> : null);

function Settings({ t, settings, canEnable, why }) {
    const form = useForm({
        enabled: settings.enabled, lead_days: settings.lead_days, min_sheets: settings.min_sheets, tiers: settings.tiers,
        cash_allowed: settings.cash_allowed, watermark: settings.watermark, max_rows: settings.max_rows, max_upload_kb: settings.max_upload_kb,
    });
    const setTier = (i, key, value) => form.setData('tiers', form.data.tiers.map((tier, j) => (j === i ? { ...tier, [key]: value } : tier)));

    return (
        <section className="mb-6 rounded-lg border bg-white p-4" data-testid="print-settings">
            <h2 className="mb-2 text-lg font-semibold">{t.settings_heading}</h2>
            {!canEnable && <p className="mb-3 rounded bg-amber-50 p-2 text-sm text-amber-900" data-testid="print-cannot-enable">{fill(t.cannot_enable, { why })}</p>}
            <form className="grid gap-3 sm:grid-cols-2" onSubmit={(e) => { e.preventDefault(); form.post('/admin/bookshop/print/settings', { preserveScroll: true }); }}>
                <label className="flex items-start gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" className="mt-1" checked={form.data.enabled} onChange={(e) => form.setData('enabled', e.target.checked)} data-testid="print-enabled" />
                    <span><span className="font-medium">{t.enabled}</span><span className="block text-xs text-gray-600">{t.enabled_hint}</span></span>
                </label>
                {form.errors.enabled && <p className="text-sm text-red-700 sm:col-span-2" data-testid="print-enabled-error">{form.errors.enabled}</p>}
                <label className="text-sm">{t.lead_days}<input type="number" min="0" max="60" className="form-input w-full" value={form.data.lead_days} onChange={(e) => form.setData('lead_days', e.target.value)} data-testid="print-lead-days" /><Err>{form.errors.lead_days}</Err></label>
                <label className="text-sm">{t.min_sheets}<input type="number" min="1" max="1000" className="form-input w-full" value={form.data.min_sheets} onChange={(e) => form.setData('min_sheets', e.target.value)} /><Err>{form.errors.min_sheets}</Err></label>
                <div className="text-sm sm:col-span-2">
                    <span className="font-medium">{t.tiers}</span><span className="ms-2 text-xs text-gray-600">{t.tiers_hint}</span>
                    {form.data.tiers.map((tier, i) => (
                        <div key={i} className="mt-1 flex flex-wrap items-center gap-2">
                            <input type="number" min="2" className="form-input w-28" aria-label={t.tier_sheets} value={tier.sheets} onChange={(e) => setTier(i, 'sheets', e.target.value)} />
                            <input type="number" min="0.01" max="99" step="0.01" className="form-input w-28" aria-label={t.tier_percent} value={tier.percent} onChange={(e) => setTier(i, 'percent', e.target.value)} />
                            <button type="button" className="text-sm text-gray-600 underline" onClick={() => form.setData('tiers', form.data.tiers.filter((_, j) => j !== i))}>{t.remove}</button>
                        </div>
                    ))}
                    <button type="button" className="mt-1 text-sm text-blue-700 underline" onClick={() => form.setData('tiers', [...form.data.tiers, { sheets: 10, percent: 5 }])}>{t.add_tier}</button>
                </div>
                <label className="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" checked={form.data.cash_allowed} onChange={(e) => form.setData('cash_allowed', e.target.checked)} /> {t.cash_allowed}</label>
                <label className="text-sm">{t.watermark}<input className="form-input w-full" maxLength={60} value={form.data.watermark} onChange={(e) => form.setData('watermark', e.target.value)} /></label>
                <label className="text-sm">{t.max_rows}<input type="number" min="1" max="5000" className="form-input w-full" value={form.data.max_rows} onChange={(e) => form.setData('max_rows', e.target.value)} /></label>
                <label className="text-sm">{t.max_upload_kb}<input type="number" min="256" max="51200" className="form-input w-full" value={form.data.max_upload_kb} onChange={(e) => form.setData('max_upload_kb', e.target.value)} /></label>
                <div className="sm:col-span-2"><button type="submit" className="btn-primary" disabled={form.processing} data-testid="print-save-settings">{t.save_settings}</button></div>
            </form>
        </section>
    );
}

function Section({ title, list, children, exportUrl, t }) {
    return (
        <section className="mb-6 rounded-lg border bg-white p-4" data-testid={`print-${list}`}>
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold">{title}</h2>
                <a href={exportUrl} className="btn-secondary text-sm" data-testid={`print-export-${list}`}>{t.export_csv}</a>
            </div>
            {children}
        </section>
    );
}

function Papers({ t, papers, kinds, sizes }) {
    const blank = { name: '', name_dv: '', name_ar: '', gsm: '', kind: 'plain', sheet_size: 'a4', price_bw: '', price_colour: '', is_active: false, sort_order: 0 };
    const [editing, setEditing] = useState(null);
    const form = useForm(blank);
    const edit = (p) => { setEditing(p.id); form.setData({ ...p, gsm: p.gsm ?? '', name_dv: p.name_dv ?? '', name_ar: p.name_ar ?? '' }); };
    const reset = () => { setEditing(null); form.setData(blank); form.clearErrors(); };

    return (
        <Section title={t.papers_heading} list="papers" exportUrl="/admin/bookshop/print/export?list=papers" t={t}>
            {papers.length === 0 ? <p className="text-sm text-gray-600">{t.papers_none}</p> : (
                <table className="table-stack mb-3 min-w-full text-sm">
                    <thead className="bg-gray-50"><tr><th className="p-2 text-start">{t.paper_name}</th><th className="p-2 text-start">{t.kind}</th><th className="p-2 text-start">{t.sheet_size}</th><th className="p-2 text-end">{t.price_bw}</th><th className="p-2 text-end">{t.price_colour}</th><th className="p-2 text-start">{t.active}</th><th className="p-2" /></tr></thead>
                    <tbody>
                        {papers.map((p) => (
                            <tr key={p.id} className="border-t" data-testid={`print-paper-${p.id}`}>
                                <td className="p-2" data-label={t.paper_name}><span dir="auto">{p.name}</span>{p.name_dv && <span className="block text-xs text-gray-600" dir="rtl">{p.name_dv}</span>}{p.gsm ? <span className="block text-xs text-gray-500">{p.gsm} gsm</span> : null}</td>
                                <td className="p-2" data-label={t.kind}>{t[`kind_${p.kind}`]}</td>
                                <td className="p-2" data-label={t.sheet_size} dir="ltr">{(p.sheet_size ?? 'a4').toUpperCase()}</td>
                                <td className="p-2 sm:text-end" data-label={t.price_bw}>{p.price_bw}</td>
                                <td className="p-2 sm:text-end" data-label={t.price_colour}>{p.price_colour}</td>
                                <td className="p-2" data-label={t.active}>{p.is_active ? t.active : t.retired}</td>
                                <td className="table-actions p-2 text-end"><button type="button" className="text-blue-700 underline" onClick={() => edit(p)} data-testid={`print-paper-edit-${p.id}`}>{t.edit}</button></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
            <form className="grid gap-2 sm:grid-cols-3" data-testid="print-paper-form" onSubmit={(e) => { e.preventDefault(); form.post(editing ? `/admin/bookshop/print/papers/${editing}` : '/admin/bookshop/print/papers', { preserveScroll: true, onSuccess: reset }); }}>
                <label className="text-sm">{t.paper_name}<input className="form-input w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required data-testid="print-paper-name" /><Err>{form.errors.name}</Err></label>
                <label className="text-sm">{t.name_dv}<input className="form-input w-full" dir="rtl" value={form.data.name_dv} onChange={(e) => form.setData('name_dv', e.target.value)} data-testid="print-paper-name-dv" /></label>
                <label className="text-sm">{t.name_ar}<input className="form-input w-full" dir="rtl" value={form.data.name_ar} onChange={(e) => form.setData('name_ar', e.target.value)} /></label>
                <label className="text-sm">{t.gsm}<input type="number" min="40" max="600" className="form-input w-full" value={form.data.gsm} onChange={(e) => form.setData('gsm', e.target.value)} /></label>
                <label className="text-sm">{t.kind}<select className="form-input w-full" value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)}>{kinds.map((k) => <option key={k} value={k}>{t[`kind_${k}`]}</option>)}</select></label>
                <label className="text-sm">{t.sheet_size}<select className="form-input w-full" value={form.data.sheet_size ?? 'a4'} onChange={(e) => form.setData('sheet_size', e.target.value)} data-testid="print-paper-size">{sizes.map((s) => <option key={s} value={s}>{s.toUpperCase()}</option>)}</select></label>
                <label className="text-sm">{t.sort_order}<input type="number" min="0" className="form-input w-full" value={form.data.sort_order} onChange={(e) => form.setData('sort_order', e.target.value)} /></label>
                <label className="text-sm">{t.price_bw}<input type="number" step="0.01" min="0" className="form-input w-full" value={form.data.price_bw} onChange={(e) => form.setData('price_bw', e.target.value)} required data-testid="print-paper-price-bw" /><Err>{form.errors.price_bw}</Err></label>
                <label className="text-sm">{t.price_colour}<input type="number" step="0.01" min="0" className="form-input w-full" value={form.data.price_colour} onChange={(e) => form.setData('price_colour', e.target.value)} required data-testid="print-paper-price-colour" /><Err>{form.errors.price_colour}</Err></label>
                <label className="flex items-center gap-2 self-end pb-2 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} data-testid="print-paper-active" /> {t.active}</label>
                <div className="flex items-center gap-3 sm:col-span-3">
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="print-paper-save">{t.save_paper}</button>
                    {editing && <button type="button" className="text-sm underline" onClick={reset}>{t.cancel_edit}</button>}
                </div>
            </form>
        </Section>
    );
}

function Finishing({ t, finishing }) {
    const blank = { name: '', name_dv: '', name_ar: '', price: '', cuts_sheet: false, is_active: false, sort_order: 0 };
    const [editing, setEditing] = useState(null);
    const form = useForm(blank);
    const edit = (f) => { setEditing(f.id); form.setData({ ...f, name_dv: f.name_dv ?? '', name_ar: f.name_ar ?? '' }); };
    const reset = () => { setEditing(null); form.setData(blank); form.clearErrors(); };

    return (
        <Section title={t.finishing_heading} list="finishing" exportUrl="/admin/bookshop/print/export?list=finishing" t={t}>
            {finishing.length === 0 ? <p className="text-sm text-gray-600">{t.finishing_none}</p> : (
                <table className="table-stack mb-3 min-w-full text-sm">
                    <thead className="bg-gray-50"><tr><th className="p-2 text-start">{t.finishing_name}</th><th className="p-2 text-end">{t.price_per_sheet}</th><th className="p-2 text-start">{t.cuts_sheet}</th><th className="p-2 text-start">{t.active}</th><th className="p-2" /></tr></thead>
                    <tbody>
                        {finishing.map((f) => (
                            <tr key={f.id} className="border-t" data-testid={`print-finishing-${f.id}`}>
                                <td className="p-2" data-label={t.finishing_name}><span dir="auto">{f.name}</span>{f.name_dv && <span className="block text-xs text-gray-600" dir="rtl">{f.name_dv}</span>}</td>
                                <td className="p-2 sm:text-end" data-label={t.price_per_sheet}>{f.price}</td>
                                <td className="p-2" data-label={t.cuts_sheet}>{f.cuts_sheet ? t.yes : t.no}</td>
                                <td className="p-2" data-label={t.active}>{f.is_active ? t.active : t.retired}</td>
                                <td className="table-actions p-2 text-end"><button type="button" className="text-blue-700 underline" onClick={() => edit(f)}>{t.edit}</button></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
            <form className="grid gap-2 sm:grid-cols-3" onSubmit={(e) => { e.preventDefault(); form.post(editing ? `/admin/bookshop/print/finishing/${editing}` : '/admin/bookshop/print/finishing', { preserveScroll: true, onSuccess: reset }); }}>
                <label className="text-sm">{t.finishing_name}<input className="form-input w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required /><Err>{form.errors.name}</Err></label>
                <label className="text-sm">{t.name_dv}<input className="form-input w-full" dir="rtl" value={form.data.name_dv} onChange={(e) => form.setData('name_dv', e.target.value)} /></label>
                <label className="text-sm">{t.name_ar}<input className="form-input w-full" dir="rtl" value={form.data.name_ar} onChange={(e) => form.setData('name_ar', e.target.value)} /></label>
                <label className="text-sm">{t.price_per_sheet}<input type="number" step="0.01" min="0" className="form-input w-full" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} required /><Err>{form.errors.price}</Err></label>
                <label className="text-sm">{t.sort_order}<input type="number" min="0" className="form-input w-full" value={form.data.sort_order} onChange={(e) => form.setData('sort_order', e.target.value)} /></label>
                <div className="flex flex-wrap items-center gap-4 self-end pb-2 text-sm">
                    <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.cuts_sheet} onChange={(e) => form.setData('cuts_sheet', e.target.checked)} /> {t.cuts_sheet}</label>
                    <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} /> {t.active}</label>
                </div>
                <div className="flex items-center gap-3 sm:col-span-3">
                    <button type="submit" className="btn-primary" disabled={form.processing}>{t.save_finishing}</button>
                    {editing && <button type="button" className="text-sm underline" onClick={reset}>{t.cancel_edit}</button>}
                </div>
            </form>
        </Section>
    );
}

function Layouts({ t, layouts, sizes, products }) {
    const blank = { name: '', print_kind: 'name_tags', shape: 'rect', sheet_size: 'a4', columns: 2, rows: 4, tag_w_mm: '', tag_h_mm: '', margin_top_mm: 0, margin_left_mm: 0, gutter_x_mm: 0, gutter_y_mm: 0, bleed_mm: 0, sheet_code: '', is_active: true, sort_order: 0 };
    const [editing, setEditing] = useState(null);
    const form = useForm(blank);
    const edit = (l) => { setEditing(l.id); form.setData({ ...l, sheet_code: l.sheet_code ?? '' }); };
    const reset = () => { setEditing(null); form.setData(blank); form.clearErrors(); };
    const num = (key, label, step = '0.1') => <label className="text-sm">{label}<input type="number" step={step} min="0" className="form-input w-full" value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} required /><Err>{form.errors[key]}</Err></label>;

    return (
        <Section title={t.layouts_heading} list="layouts" exportUrl="/admin/bookshop/print/export?list=layouts" t={t}>
            {layouts.length === 0 ? <p className="text-sm text-gray-600">{t.layouts_none}</p> : (
                <table className="table-stack mb-3 min-w-full text-sm">
                    <thead className="bg-gray-50"><tr><th className="p-2 text-start">{t.layout_name}</th><th className="p-2 text-start">{t.sheet_size}</th><th className="p-2 text-start">{t.product}</th><th className="p-2 text-end">{t.tags_per_sheet}</th><th className="p-2 text-start">{t.tag_w} × {t.tag_h}</th><th className="p-2 text-start">{t.active}</th><th className="p-2" /></tr></thead>
                    <tbody>
                        {layouts.map((l) => (
                            <tr key={l.id} className="border-t" data-testid={`print-layout-${l.id}`}>
                                <td className="p-2" data-label={t.layout_name}>{l.name}{l.sheet_code && <span className="ms-1 text-xs text-gray-500" dir="ltr">{l.sheet_code}</span>}</td>
                                <td className="p-2" data-label={t.sheet_size} dir="ltr">{(l.sheet_size ?? 'a4').toUpperCase()}</td>
                                <td className="p-2" data-label={t.product} dir="auto">{products.find((p) => p.kind === l.print_kind)?.title ?? l.print_kind}</td>
                                <td className="p-2 sm:text-end" data-label={t.tags_per_sheet}>{l.tags} ({l.columns} × {l.rows})</td>
                                <td className="p-2" data-label={t.tag_w} dir="ltr">{l.shape === 'circle' ? '◯ ' : ''}{l.tag_w_mm} × {l.tag_h_mm} mm{l.bleed_mm > 0 ? ` + ${l.bleed_mm} mm` : ''}</td>
                                <td className="p-2" data-label={t.active}>{l.is_active ? t.active : t.retired}</td>
                                <td className="table-actions p-2 text-end"><button type="button" className="text-blue-700 underline" onClick={() => edit(l)}>{t.edit}</button></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
            <form className="grid gap-2 sm:grid-cols-4" data-testid="print-layout-form" onSubmit={(e) => { e.preventDefault(); form.post(editing ? `/admin/bookshop/print/layouts/${editing}` : '/admin/bookshop/print/layouts', { preserveScroll: true, onSuccess: reset }); }}>
                <label className="text-sm sm:col-span-2">{t.layout_name}<input className="form-input w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required data-testid="print-layout-name" /><Err>{form.errors.name}</Err></label>
                <label className="text-sm sm:col-span-2">{t.product}<select className="form-input w-full" value={form.data.print_kind ?? 'name_tags'} onChange={(e) => form.setData('print_kind', e.target.value)} data-testid="print-layout-product">{products.map((p) => <option key={p.kind} value={p.kind}>{p.title}</option>)}</select></label>
                <label className="text-sm sm:col-span-2">{t.shape}<select className="form-input w-full" value={form.data.shape ?? 'rect'} onChange={(e) => form.setData('shape', e.target.value)} data-testid="print-layout-shape">{['rect', 'circle'].map((s) => <option key={s} value={s}>{t[`shape_${s}`]}</option>)}</select></label>
                <label className="text-sm sm:col-span-2">{t.sheet_size}<select className="form-input w-full" value={form.data.sheet_size ?? 'a4'} onChange={(e) => form.setData('sheet_size', e.target.value)} data-testid="print-layout-size">{sizes.map((s) => <option key={s} value={s}>{s.toUpperCase()}</option>)}</select></label>
                {num('columns', t.columns, '1')}
                {num('rows', t.rows, '1')}
                {num('tag_w_mm', t.tag_w)}
                {num('tag_h_mm', t.tag_h)}
                {num('margin_top_mm', t.margin_top)}
                {num('margin_left_mm', t.margin_left)}
                {num('gutter_x_mm', t.gutter_x)}
                {num('gutter_y_mm', t.gutter_y)}
                <label className="text-sm">{t.bleed}<input type="number" step="0.5" min="0" max="5" className="form-input w-full" value={form.data.bleed_mm ?? 0} onChange={(e) => form.setData('bleed_mm', e.target.value)} data-testid="print-layout-bleed" /><span className="block text-xs text-gray-500">{t.bleed_help}</span><Err>{form.errors.bleed_mm}</Err></label>
                <label className="text-sm">{t.sheet_code}<input className="form-input w-full" dir="ltr" value={form.data.sheet_code} onChange={(e) => form.setData('sheet_code', e.target.value)} /></label>
                <label className="text-sm">{t.sort_order}<input type="number" min="0" className="form-input w-full" value={form.data.sort_order} onChange={(e) => form.setData('sort_order', e.target.value)} /></label>
                <label className="flex items-center gap-2 self-end pb-2 text-sm sm:col-span-2"><input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} /> {t.active}</label>
                <div className="flex items-center gap-3 sm:col-span-4">
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="print-layout-save">{t.save_layout}</button>
                    {editing && <button type="button" className="text-sm underline" onClick={reset}>{t.cancel_edit}</button>}
                </div>
            </form>
        </Section>
    );
}

function SubjectSets({ t, sets }) {
    const blank = { label: '', label_dv: '', label_ar: '', subjects_en: '', subjects_dv: '', subjects_ar: '', is_active: true, sort_order: 0 };
    const [editing, setEditing] = useState(null);
    const form = useForm(blank);
    const edit = (s) => { setEditing(s.id); form.setData({ ...s, label_dv: s.label_dv ?? '', label_ar: s.label_ar ?? '' }); };
    const reset = () => { setEditing(null); form.setData(blank); form.clearErrors(); };

    return (
        <Section title={t.subject_sets_heading} list="subject_sets" exportUrl="/admin/bookshop/print/export?list=subject_sets" t={t}>
            {sets.length === 0 ? <p className="text-sm text-gray-600">{t.subject_sets_none}</p> : (
                <ul className="mb-3 divide-y text-sm">
                    {sets.map((s) => (
                        <li key={s.id} className="flex flex-wrap items-start justify-between gap-2 py-2" data-testid={`print-subject-set-${s.id}`}>
                            <span><span className="font-medium" dir="auto">{s.label}</span>{s.label_dv && <span className="ms-2 text-gray-600" dir="rtl">{s.label_dv}</span>} · {fill(t.subjects_count, { count: s.count })}{!s.is_active && <span className="ms-2 text-gray-500">({t.retired})</span>}<span className="block text-xs text-gray-600" dir="auto">{s.subjects.map((x) => x.en).join(' · ')}</span></span>
                            <button type="button" className="text-blue-700 underline" onClick={() => edit(s)}>{t.edit}</button>
                        </li>
                    ))}
                </ul>
            )}
            <form className="grid gap-2 sm:grid-cols-3" data-testid="print-subject-set-form" onSubmit={(e) => { e.preventDefault(); form.post(editing ? `/admin/bookshop/print/subject-sets/${editing}` : '/admin/bookshop/print/subject-sets', { preserveScroll: true, onSuccess: reset }); }}>
                <label className="text-sm">{t.set_label}<input className="form-input w-full" value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} required data-testid="print-set-label" /><Err>{form.errors.label}</Err></label>
                <label className="text-sm">{t.label_dv}<input className="form-input w-full" dir="rtl" value={form.data.label_dv} onChange={(e) => form.setData('label_dv', e.target.value)} /></label>
                <label className="text-sm">{t.label_ar}<input className="form-input w-full" dir="rtl" value={form.data.label_ar} onChange={(e) => form.setData('label_ar', e.target.value)} /></label>
                <p className="text-xs text-gray-600 sm:col-span-3">{t.subjects_hint}</p>
                <label className="text-sm">{t.subjects_en}<textarea rows={5} className="form-input w-full" value={form.data.subjects_en} onChange={(e) => form.setData('subjects_en', e.target.value)} required data-testid="print-set-en" /><Err>{form.errors.subjects_en}</Err></label>
                <label className="text-sm">{t.subjects_dv}<textarea rows={5} className="form-input w-full" dir="rtl" value={form.data.subjects_dv} onChange={(e) => form.setData('subjects_dv', e.target.value)} required data-testid="print-set-dv" /></label>
                <label className="text-sm">{t.subjects_ar}<textarea rows={5} className="form-input w-full" dir="rtl" value={form.data.subjects_ar} onChange={(e) => form.setData('subjects_ar', e.target.value)} required data-testid="print-set-ar" /></label>
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} /> {t.active}</label>
                <label className="text-sm">{t.sort_order}<input type="number" min="0" className="form-input w-full" value={form.data.sort_order} onChange={(e) => form.setData('sort_order', e.target.value)} /></label>
                <div className="flex items-center gap-3 sm:col-span-3">
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="print-set-save">{t.save_subject_set}</button>
                    {editing && <button type="button" className="text-sm underline" onClick={reset}>{t.cancel_edit}</button>}
                </div>
            </form>
        </Section>
    );
}

/** PP2: each print product, on sale or off, with the layouts and designs it has. */
function Products({ t, products }) {
    const [busy, setBusy] = useState(null);
    const toggle = (p) => { setBusy(p.kind); router.post(`/admin/bookshop/print/products/${p.kind}`, { on_sale: !p.on_sale }, { preserveScroll: true, onFinish: () => setBusy(null) }); };

    return (
        <section className="mb-6 rounded-lg border bg-white p-4" data-testid="print-products">
            <h2 className="mb-1 text-lg font-semibold">{t.products_heading}</h2>
            <p className="mb-3 text-sm text-gray-600">{t.products_intro}</p>
            <ul className="divide-y">
                {products.map((p) => (
                    <li key={p.kind} className="flex flex-wrap items-center gap-3 py-2" data-testid={`print-product-${p.kind}`} data-on-sale={p.on_sale ? 'yes' : 'no'}>
                        <div className="min-w-0 flex-1">
                            <p className="font-medium" dir="auto">{p.title}</p>
                            <p className="text-xs text-gray-600">{fill(t.product_counts, { layouts: p.layouts, templates: p.templates })} · {p.on_sale ? t.status_active : t.status_draft}</p>
                        </div>
                        {p.kind === 'name_tags'
                            ? <span className="text-xs text-gray-600">{t.product_with_desk}</span>
                            : (
                                <button type="button" className={p.on_sale ? 'btn-secondary text-sm' : 'btn-primary text-sm'} disabled={busy !== null} onClick={() => toggle(p)} data-testid={`print-product-${p.kind}-toggle`}>
                                    {p.on_sale ? t.product_take_off : t.product_put_on}
                                </button>
                            )}
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function Print({ t = {}, settings, can_enable: canEnable, cannot_enable_because: why, product, vendor, papers = [], finishing = [], layouts = [], subject_sets: sets = [], kinds = [], sheet_sizes: sizes = ['a4'], products = [] }) {
    const { errors } = usePage().props;

    return (
        <AppShell title={t.title}>
            <FormErrors errors={errors} className="mb-4" />
            <p className="mb-1 text-sm text-gray-600">{t.intro} <Link href="/admin/bookshop/print/templates" className="text-blue-700 underline" data-testid="print-templates-link">{t.templates_link} →</Link> · <Link href="/admin/bookshop/print/queue" className="text-blue-700 underline" data-testid="print-queue-link">{t.queue_link} →</Link> · <Link href="/admin/bookshop/print/quotes" className="text-blue-700 underline" data-testid="print-quotes-link">{t.quotes_link} →</Link></p>
            {vendor && <p className="mb-1 text-sm text-gray-600" data-testid="print-vendor">{fill(t.vendor_line, { name: vendor.name, slug: vendor.slug })}</p>}
            {product && <p className="mb-4 text-sm text-gray-600" data-testid="print-product">{fill(t.product_line, { title: product.title, status: product.status === 'active' ? t.status_active : t.status_draft })}</p>}
            <Settings t={t} settings={settings} canEnable={canEnable} why={why} />
            <Products t={t} products={products} />
            <Papers t={t} papers={papers} kinds={kinds} sizes={sizes} />
            <Finishing t={t} finishing={finishing} />
            <Layouts t={t} layouts={layouts} sizes={sizes} products={products} />
            <SubjectSets t={t} sets={sets} />
        </AppShell>
    );
}
