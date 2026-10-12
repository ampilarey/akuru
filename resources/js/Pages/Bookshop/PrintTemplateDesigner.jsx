import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';
import Designer from '../../Components/Print/Designer';
import { blankDesign, normaliseDesign, withFields } from '../../Components/Print/design';

/**
 * NAME_TAGS_PLAN §7.1 (NT2): the designer in template mode — the office
 * names a template in three languages, picks its kind and layout, draws it,
 * marks its fields and saves. The design travels as JSON and the server
 * validates it (`PrintDesign::validate`) before anything is kept. The
 * picture library is uploaded here too.
 */
const fill = (s, vars) => Object.entries(vars).reduce((out, [k, v]) => out.split(`:${k}`).join(String(v ?? '')), s || '');
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

export default function PrintTemplateDesigner({ t = {}, template = null, sample = {}, layouts = [], fonts = {}, font_css: fontCss = '', images = [], field_keys: fieldKeys = [], kinds = [], max_elements: maxElements = 60 }) {
    const { errors = {} } = usePage().props;
    const [design, setDesign] = useState(() => (template ? normaliseDesign(template.design) : blankDesign()));
    const [checked, setChecked] = useState(null);
    const [busy, setBusy] = useState(false);
    const [meta, setMeta] = useState({
        title: template?.title ?? '', title_dv: template?.title_dv ?? '', title_ar: template?.title_ar ?? '', kind: template?.kind ?? 'name_tag',
        print_layout_id: template?.print_layout_id ?? '', sort_order: template?.sort_order ?? 0,
    });
    const set = (key, value) => setMeta((m) => ({ ...m, [key]: value }));
    const upload = useForm({ images: [], alt: '' });

    const pickLayout = (id) => {
        set('print_layout_id', id);
        const layout = layouts.find((l) => String(l.id) === String(id));
        if (layout) setDesign((d) => ({ ...d, tag: { w: layout.tag_w_mm, h: layout.tag_h_mm } }));
    };
    const save = (e) => {
        e.preventDefault();
        setBusy(true);
        router.post(template ? `/admin/bookshop/print/templates/${template.id}` : '/admin/bookshop/print/templates', { ...meta, design: JSON.stringify(withFields(design)) }, { onFinish: () => setBusy(false) });
    };
    const check = async () => {
        setChecked(null);
        try {
            const res = await fetch('/admin/bookshop/print/templates/check', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ design: withFields(design) }),
            });
            const body = await res.json().catch(() => ({}));
            setChecked(res.ok ? { ok: true, text: fill(t.design_ok, { count: (body.fields || []).length }) } : { ok: false, text: Object.values(body.errors || {}).flat().join(' ') || body.message || String(res.status) });
        } catch (err) {
            setChecked({ ok: false, text: String(err) });
        }
    };
    const setStatus = (status) => router.post(`/admin/bookshop/print/templates/${template.id}/status`, { status }, { preserveScroll: true });

    return (
        <AppShell title={template ? t.designer_edit : t.designer_new}>
            <style>{fontCss}</style>
            <FormErrors errors={errors} className="mb-4" />
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                <Link href="/admin/bookshop/print/templates" className="underline">← {t.templates_title}</Link>
                {template && (
                    <div className="flex flex-wrap items-center gap-3" data-testid="template-status-bar">
                        <span className="rounded bg-gray-100 px-2 py-0.5" data-testid="template-status">{t[`template_status_${template.status}`]}</span>
                        <a href={`/admin/bookshop/print/templates/${template.id}/sheet`} target="_blank" rel="noreferrer" className="underline" data-testid="template-sheet">{t.sample_sheet}</a>
                        {template.status !== 'active' && <button type="button" className="underline" onClick={() => setStatus('active')} data-testid="template-activate">{t.make_active}</button>}
                        {template.status === 'active' && <button type="button" className="underline" onClick={() => setStatus('draft')}>{t.make_draft}</button>}
                        {template.status !== 'retired' && <button type="button" className="text-gray-600 underline" onClick={() => setStatus('retired')} data-testid="template-retire">{t.retire}</button>}
                    </div>
                )}
            </div>

            <form onSubmit={save} className="mb-4 grid gap-2 rounded-lg border bg-white p-4 sm:grid-cols-3" data-testid="template-form">
                <label className="text-sm">{t.template_title}<input className="form-input w-full" value={meta.title} onChange={(e) => set('title', e.target.value)} required maxLength={120} data-testid="template-title" />{errors.title && <p className="text-xs text-red-700">{errors.title}</p>}</label>
                <label className="text-sm">{t.title_in_dv}<input className="form-input w-full" dir="rtl" value={meta.title_dv} onChange={(e) => set('title_dv', e.target.value)} maxLength={120} data-testid="template-title-dv" /></label>
                <label className="text-sm">{t.title_in_ar}<input className="form-input w-full" dir="rtl" value={meta.title_ar} onChange={(e) => set('title_ar', e.target.value)} maxLength={120} /></label>
                <label className="text-sm">{t.kind}<select className="form-input w-full" value={meta.kind} onChange={(e) => set('kind', e.target.value)} data-testid="template-kind">{kinds.map((k) => <option key={k} value={k}>{t[`kind_${k}`]}</option>)}</select>{errors.kind && <p className="text-xs text-red-700">{errors.kind}</p>}</label>
                <label className="text-sm">{t.layout}<select className="form-input w-full" value={meta.print_layout_id ?? ''} onChange={(e) => pickLayout(e.target.value)} data-testid="template-layout">
                    <option value="">{t.layout_none}</option>
                    {layouts.map((l) => <option key={l.id} value={l.id}>{l.name} — {l.tag_w_mm} × {l.tag_h_mm} mm</option>)}
                </select></label>
                <div className="grid grid-cols-2 gap-2">
                    <label className="text-sm">{t.tag_size}<div className="flex items-center gap-1" dir="ltr">
                        <input type="number" min={10} max={210} step={0.5} className="form-input w-full" value={design.tag.w} onChange={(e) => setDesign((d) => ({ ...d, tag: { ...d.tag, w: Number(e.target.value) || 10 } }))} aria-label={t.width_mm} data-testid="tag-w" />
                        <span>×</span>
                        <input type="number" min={10} max={297} step={0.5} className="form-input w-full" value={design.tag.h} onChange={(e) => setDesign((d) => ({ ...d, tag: { ...d.tag, h: Number(e.target.value) || 10 } }))} aria-label={t.height_mm} data-testid="tag-h" />
                    </div></label>
                    <label className="text-sm">{t.sort_order}<input type="number" min={0} className="form-input w-full" value={meta.sort_order} onChange={(e) => set('sort_order', e.target.value)} /></label>
                </div>
                {errors.design && <p className="text-sm text-red-700 sm:col-span-3" data-testid="design-error">{errors.design}</p>}
                {errors.elements && <p className="text-sm text-red-700 sm:col-span-3" data-testid="design-error">{errors.elements}</p>}
                {errors.tag && <p className="text-sm text-red-700 sm:col-span-3" data-testid="design-error">{errors.tag}</p>}
                <div className="flex flex-wrap items-center gap-3 sm:col-span-3">
                    <button type="submit" className="btn-primary" disabled={busy} data-testid="template-save">{t.save_template}</button>
                    <button type="button" className="btn-secondary" onClick={check} data-testid="template-check">{t.check_design}</button>
                    {checked && <span className={`text-sm ${checked.ok ? 'text-green-800' : 'text-red-700'}`} data-testid="template-checked">{checked.text}</span>}
                </div>
            </form>

            <Designer design={design} onChange={setDesign} fonts={fonts} images={images} fieldKeys={fieldKeys} maxElements={maxElements} sample={sample} t={t} />

            <section className="mt-6 rounded-lg border bg-white p-4" data-testid="print-images">
                <h2 className="text-lg font-semibold">{t.images_heading}</h2>
                <p className="mb-2 text-sm text-gray-600">{t.images_intro}</p>
                {images.length === 0 ? <p className="mb-2 text-sm text-gray-600">{t.images_none}</p> : (
                    <ul className="mb-3 flex flex-wrap gap-2">
                        {images.map((img) => <li key={img.id} className="rounded border p-1"><img src={img.thumb || img.url} alt={img.alt || ''} className="h-16 w-16 object-contain" /></li>)}
                    </ul>
                )}
                <form className="flex flex-wrap items-end gap-2" onSubmit={(e) => { e.preventDefault(); upload.post('/admin/bookshop/print/templates/images', { forceFormData: true, preserveScroll: true, onSuccess: () => upload.reset() }); }}>
                    <label className="text-sm">{t.upload_images}<input type="file" accept="image/*" multiple className="block text-sm" onChange={(e) => upload.setData('images', Array.from(e.target.files || []))} data-testid="print-images-files" /></label>
                    <label className="text-sm">{t.alt}<input className="form-input w-full" value={upload.data.alt} onChange={(e) => upload.setData('alt', e.target.value)} maxLength={200} /></label>
                    <button type="submit" className="btn-secondary text-sm" disabled={upload.processing || upload.data.images.length === 0} data-testid="print-images-upload">{t.upload_images}</button>
                    {(upload.errors.images || upload.errors['images.0']) && <p className="w-full text-xs text-red-700">{upload.errors.images || upload.errors['images.0']}</p>}
                </form>
            </section>
        </AppShell>
    );
}
