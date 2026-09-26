import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * BOOKSHOP_PLAN slice B5 — the storefront designer, part 2: the home's
 * sections (a list with up / down / hide / schedule and a settings form
 * each — §6.3's form-based designer), the storefront menu and SEO fields
 * (§6.4, §6.7), pages built from the same sections (§6.4), collections
 * (§5) and the image library the sections pick from. The real public page
 * rendered from the draft sits in an iframe on the right; publishing lives
 * with part 1 and is offered here too for the owner.
 */

const LANGS = ['', '_dv', '_ar'];
const text = (v) => (v === null || v === undefined ? '' : String(v));
const newId = () => 's' + Math.random().toString(36).slice(2, 10);

function parseKind(kind) {
    const [name, arg] = String(kind).split(':');
    return { name, arg };
}

/**
 * B7 (§6.3): drag to reorder, beside the up / down buttons that stay for
 * keyboards and screen readers. Native drag and drop; the list is only
 * reordered on drop, and a drop outside a row changes nothing.
 */
function useDragOrder(list, onChange) {
    const [dragging, setDragging] = useState(null);
    const [over, setOver] = useState(null);
    // Only the handle arms a row, so text in a row's inputs stays selectable.
    const [armed, setArmed] = useState(null);
    const handle = (i) => ({ onMouseDown: () => setArmed(i), onMouseUp: () => setArmed(null), onTouchStart: () => setArmed(i) });
    const props = (i) => ({
        draggable: armed === i,
        onDragStart: (e) => { setDragging(i); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', String(i)); },
        onDragOver: (e) => { if (dragging === null) return; e.preventDefault(); e.dataTransfer.dropEffect = 'move'; setOver(i); },
        onDragLeave: () => setOver((o) => (o === i ? null : o)),
        onDrop: (e) => {
            e.preventDefault();
            const from = dragging;
            setDragging(null);
            setOver(null);
            if (from === null || from === i) return;
            const next = [...list];
            const [moved] = next.splice(from, 1);
            next.splice(i, 0, moved);
            onChange(next);
        },
        onDragEnd: () => { setDragging(null); setOver(null); setArmed(null); },
        'data-drop-target': over === i ? '1' : '0',
    });

    return { props, handle, dragging, over };
}

function blankSettings(fields) {
    const out = {};
    Object.entries(fields).forEach(([field, kind]) => {
        const { name, arg } = parseKind(kind);
        if (name === 'text' || name === 'rich') { out[field] = ''; out[`${field}_dv`] = ''; out[`${field}_ar`] = ''; }
        else if (['images', 'products', 'buttons', 'quotes', 'faq'].includes(name)) out[field] = [];
        else if (name === 'choice') out[field] = arg.split(',')[0];
        else if (name === 'bool') out[field] = true;
        else if (name === 'int') out[field] = arg.split(',')[0];
        else out[field] = null;
    });
    return out;
}

function Field({ label, children, className = '' }) {
    return (
        <label className={`block text-sm ${className}`}>
            <span className="mb-1 block font-medium text-gray-700">{label}</span>
            {children}
        </label>
    );
}

function ImagePicker({ library, value, multiple, max, onChange, t, testid }) {
    const selected = multiple ? (value || []).map(Number) : (value ? [Number(value)] : []);
    const toggle = (id) => {
        if (!multiple) return onChange(selected.includes(id) ? null : id);
        if (selected.includes(id)) return onChange(selected.filter((s) => s !== id));
        if (max && selected.length >= max) return undefined;
        return onChange([...selected, id]);
    };
    if (library.length === 0) {
        return <p className="text-xs text-gray-500">{t.library_empty}</p>;
    }
    return (
        <div className="flex flex-wrap gap-2" data-testid={testid}>
            {library.map((img) => {
                const on = selected.includes(img.media_file_id);
                return (
                    <button key={img.id} type="button" onClick={() => toggle(img.media_file_id)} title={img.alt || ''} className={`relative h-16 w-16 overflow-hidden rounded border ${on ? 'ring-2 ring-gray-900' : ''}`} data-testid={`${testid}-${img.id}`} data-selected={on ? '1' : '0'}>
                        {img.url ? <img src={img.url} alt={img.alt || ''} className="h-full w-full object-cover" /> : <span className="text-xs">#{img.media_file_id}</span>}
                        {on && multiple && <span className="absolute end-0 top-0 rounded-bl bg-gray-900 px-1 text-xs text-white">{selected.indexOf(img.media_file_id) + 1}</span>}
                    </button>
                );
            })}
        </div>
    );
}

function LinkRow({ value, onChange, d, t, withLabel, onRemove, testid }) {
    const link = value || { kind: 'all', target: '', label: '', label_dv: '', label_ar: '' };
    const set = (patch) => onChange({ ...link, ...patch });
    const targets = link.kind === 'collection' ? d.collections.map((c) => [c.slug, c.name])
        : link.kind === 'page' ? d.pages.map((p) => [p.slug, p.title])
            : link.kind === 'product' ? d.products.map((p) => [p.slug, p.title]) : [];
    return (
        <div className="flex flex-wrap items-end gap-2 rounded border bg-gray-50 p-2" data-testid={testid}>
            <Field label={t.link_to}>
                <select className="form-input" value={link.kind} onChange={(e) => set({ kind: e.target.value, target: '' })} data-testid={`${testid}-kind`}>
                    {d.link_kinds.map((k) => <option key={k} value={k}>{t[`link_${k}`] || k}</option>)}
                </select>
            </Field>
            {link.kind !== 'all' && (
                <Field label={t.link_target}>
                    <select className="form-input" value={text(link.target)} onChange={(e) => set({ target: e.target.value })} data-testid={`${testid}-target`}>
                        <option value="">—</option>
                        {targets.map(([slug, name]) => <option key={slug} value={slug}>{name}</option>)}
                    </select>
                </Field>
            )}
            {withLabel && LANGS.map((suffix) => (
                <Field key={suffix} label={t[`label${suffix}`]}>
                    <input className="form-input w-36" dir={suffix ? 'rtl' : 'auto'} value={text(link[`label${suffix}`])} onChange={(e) => set({ [`label${suffix}`]: e.target.value })} data-testid={suffix ? undefined : `${testid}-label`} />
                </Field>
            ))}
            {onRemove && <button type="button" className="text-xs text-red-700 underline" onClick={onRemove}>{t.remove}</button>}
        </div>
    );
}

function Rows({ items, max, blank, render, onChange, t, addLabel, testid }) {
    const list = items || [];
    const drag = useDragOrder(list, onChange);
    return (
        <div className="space-y-2" data-testid={testid}>
            {list.map((item, i) => (
                <div key={i} {...drag.props(i)} className={`flex flex-wrap items-end gap-2 rounded border bg-gray-50 p-2 ${drag.over === i ? 'ring-2 ring-blue-400' : ''}`}>
                    <span {...drag.handle(i)} className="cursor-grab select-none text-gray-400" title={t.drag_to_reorder} aria-hidden="true">⠿</span>
                    {render(item, (patch) => onChange(list.map((x, j) => (j === i ? { ...x, ...patch } : x))), i)}
                    <button type="button" className="text-xs text-red-700 underline" onClick={() => onChange(list.filter((_, j) => j !== i))}>{t.remove}</button>
                </div>
            ))}
            {(!max || list.length < max) && <button type="button" className="btn-secondary text-xs" onClick={() => onChange([...list, blank()])} data-testid={`${testid}-add`}>{addLabel}</button>}
        </div>
    );
}

function FieldEditor({ field, kind, settings, onChange, d, t, prefix }) {
    const { name, arg } = parseKind(kind);
    const value = settings[field];
    const set = (v) => onChange({ ...settings, [field]: v });
    const label = t[`field_${field}`] || field;
    const testid = `${prefix}-${field}`;

    switch (name) {
        case 'text':
        case 'rich':
            return (
                <div className="grid gap-2 md:grid-cols-3">
                    {LANGS.map((suffix) => (
                        <Field key={suffix} label={`${label}${suffix ? ` (${t[`lang${suffix}`]})` : ''}`}>
                            {name === 'rich'
                                ? <textarea className="form-input w-full" rows={4} dir={suffix ? 'rtl' : 'auto'} value={text(settings[`${field}${suffix}`])} onChange={(e) => onChange({ ...settings, [`${field}${suffix}`]: e.target.value })} data-testid={suffix ? undefined : testid} />
                                : <input className="form-input w-full" dir={suffix ? 'rtl' : 'auto'} value={text(settings[`${field}${suffix}`])} onChange={(e) => onChange({ ...settings, [`${field}${suffix}`]: e.target.value })} data-testid={suffix ? undefined : testid} />}
                        </Field>
                    ))}
                </div>
            );
        case 'image':
            return <Field label={label}><ImagePicker library={d.library} value={value} multiple={false} onChange={set} t={t} testid={testid} /></Field>;
        case 'images':
            return <Field label={`${label} (${t.up_to_n.replace(':n', arg)})`}><ImagePicker library={d.library} value={value} multiple max={Number(arg)} onChange={set} t={t} testid={testid} /></Field>;
        case 'products': {
            const chosen = (value || []).map(Number);
            return (
                <Field label={`${label} (${t.up_to_n.replace(':n', arg)})`}>
                    <div className="max-h-48 overflow-y-auto rounded border bg-white p-2 text-sm" data-testid={testid}>
                        {d.products.length === 0 && <p className="text-xs text-gray-500">{t.no_products_yet}</p>}
                        {d.products.map((p) => {
                            const on = chosen.includes(p.id);
                            return (
                                <label key={p.id} className="flex items-center gap-2">
                                    <input type="checkbox" checked={on} disabled={!on && chosen.length >= Number(arg)} onChange={(e) => set(e.target.checked ? [...chosen, p.id] : chosen.filter((id) => id !== p.id))} data-testid={`${testid}-${p.slug}`} />
                                    {on && <span className="text-xs text-gray-500">{chosen.indexOf(p.id) + 1}.</span>}
                                    <span>{p.title}</span>
                                </label>
                            );
                        })}
                    </div>
                </Field>
            );
        }
        case 'collection':
            return (
                <Field label={label}>
                    <select className="form-input" value={text(value)} onChange={(e) => set(e.target.value ? Number(e.target.value) : null)} data-testid={testid}>
                        <option value="">—</option>
                        {d.collections.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </Field>
            );
        case 'buttons':
            return (
                <Field label={`${label} (${t.up_to_n.replace(':n', arg)})`}>
                    <Rows items={value} max={Number(arg)} blank={() => ({ kind: 'all', target: '', label: '', label_dv: '', label_ar: '' })} onChange={set} t={t} addLabel={t.add_button} testid={testid}
                        render={(item, patch, i) => <LinkRow value={item} onChange={patch} d={d} t={t} withLabel testid={`${testid}-${i}`} />} />
                </Field>
            );
        case 'link':
            return (
                <Field label={label}>
                    {value ? <LinkRow value={value} onChange={set} d={d} t={t} withLabel={false} onRemove={() => set(null)} testid={testid} />
                        : <button type="button" className="btn-secondary text-xs" onClick={() => set({ kind: 'all', target: '' })} data-testid={`${testid}-add`}>{t.add_link}</button>}
                </Field>
            );
        case 'choice':
            return (
                <Field label={label}>
                    <select className="form-input" value={text(value)} onChange={(e) => set(e.target.value)} data-testid={testid}>
                        {arg.split(',').map((c) => <option key={c} value={c}>{t[`choice_${field}_${c}`] || t[`choice_${c}`] || c}</option>)}
                    </select>
                </Field>
            );
        case 'bool':
            return <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={Boolean(value)} onChange={(e) => set(e.target.checked)} data-testid={testid} /> {label}</label>;
        case 'date':
            return <Field label={label}><input type="date" className="form-input" value={text(value)} onChange={(e) => set(e.target.value || null)} data-testid={testid} /></Field>;
        case 'float':
            return <Field label={label}><input type="number" step="any" className="form-input w-40" value={text(value)} onChange={(e) => set(e.target.value === '' ? null : e.target.value)} data-testid={testid} /></Field>;
        case 'int': {
            const [min, max] = arg.split(',');
            return <Field label={label}><input type="number" min={min} max={max} className="form-input w-24" value={text(value)} onChange={(e) => set(e.target.value)} data-testid={testid} /></Field>;
        }
        case 'video':
            return <Field label={label}><input className="form-input w-full" placeholder="https://www.youtube.com/watch?v=…" value={text(value)} onChange={(e) => set(e.target.value)} data-testid={testid} /><span className="block text-xs text-gray-500">{t.video_hint}</span></Field>;
        case 'quotes':
            return (
                <Field label={`${label} (${t.up_to_n.replace(':n', arg)})`}>
                    <Rows items={value} max={Number(arg)} blank={() => ({ quote: '', name: '' })} onChange={set} t={t} addLabel={t.add_quote} testid={testid}
                        render={(item, patch, i) => (
                            <>
                                <Field label={t.quote} className="flex-1"><textarea className="form-input w-full" rows={2} value={text(item.quote)} onChange={(e) => patch({ quote: e.target.value })} data-testid={`${testid}-${i}-quote`} /></Field>
                                <Field label={t.quote_name}><input className="form-input" value={text(item.name)} onChange={(e) => patch({ name: e.target.value })} /></Field>
                            </>
                        )} />
                </Field>
            );
        case 'faq':
            return (
                <Field label={`${label} (${t.up_to_n.replace(':n', arg)})`}>
                    <Rows items={value} max={Number(arg)} blank={() => ({ question: '', answer: '' })} onChange={set} t={t} addLabel={t.add_question} testid={testid}
                        render={(item, patch, i) => (
                            <div className="flex-1 space-y-1">
                                <input className="form-input w-full" placeholder={t.question} value={text(item.question)} onChange={(e) => patch({ question: e.target.value })} data-testid={`${testid}-${i}-question`} />
                                <textarea className="form-input w-full" rows={2} placeholder={t.answer} value={text(item.answer)} onChange={(e) => patch({ answer: e.target.value })} data-testid={`${testid}-${i}-answer`} />
                            </div>
                        )} />
                </Field>
            );
        default:
            return null;
    }
}

function SectionsEditor({ sections, onChange, d, t, prefix }) {
    const [open, setOpen] = useState(null);
    const [type, setType] = useState('hero');
    const locked = d.moderation.locked_types || [];
    const types = Object.keys(d.schema);
    const drag = useDragOrder(sections, (next) => { onChange(next); setOpen(null); });
    const update = (i, patch) => onChange(sections.map((s, j) => (j === i ? { ...s, ...patch } : s)));
    const move = (i, dir) => {
        const j = i + dir;
        if (j < 0 || j >= sections.length) return;
        const next = [...sections];
        [next[i], next[j]] = [next[j], next[i]];
        onChange(next);
    };
    const add = () => {
        if (sections.length >= d.limits.sections || locked.includes(type)) return;
        onChange([...sections, { id: newId(), type, settings: blankSettings(d.schema[type]), visibility: 'published', from: null, until: null, mobile_order: null }]);
        setOpen(sections.length);
    };

    return (
        <div data-testid={`${prefix}-editor`}>
            {sections.length === 0 && <p className="mb-3 text-sm text-gray-600">{t.no_sections_yet}</p>}
            <ol className="space-y-2">
                {sections.map((s, i) => (
                    <li key={s.id} {...drag.props(i)} className={`rounded border bg-white ${locked.includes(s.type) ? 'border-red-300' : ''} ${drag.over === i ? 'ring-2 ring-blue-400' : ''} ${drag.dragging === i ? 'opacity-50' : ''}`} data-testid={`${prefix}-row-${i}`} data-type={s.type}>
                        <div className="flex flex-wrap items-center gap-2 p-2 text-sm">
                            <span {...drag.handle(i)} className="cursor-grab select-none text-gray-400" title={t.drag_to_reorder} aria-hidden="true" data-testid={`${prefix}-${i}-handle`}>⠿</span>
                            <span className="w-6 text-center text-gray-500">{i + 1}</span>
                            <span className="font-semibold">{t[`section_${s.type}`] || s.type}</span>
                            {s.settings?.heading && <span className="truncate text-gray-500" dir="auto">— {s.settings.heading}</span>}
                            {locked.includes(s.type) && <span className="rounded bg-red-100 px-2 py-0.5 text-xs text-red-800">{t.section_locked}</span>}
                            <span className="ms-auto flex flex-wrap items-center gap-1">
                                <select className="form-input py-1 text-xs" value={s.visibility} onChange={(e) => update(i, { visibility: e.target.value })} data-testid={`${prefix}-${i}-visibility`}>
                                    {d.visibilities.map((v) => <option key={v} value={v}>{t[`visibility_${v}`] || v}</option>)}
                                </select>
                                {s.visibility === 'scheduled' && (
                                    <>
                                        <input type="date" className="form-input py-1 text-xs" value={text(s.from)} onChange={(e) => update(i, { from: e.target.value || null })} aria-label={t.from} data-testid={`${prefix}-${i}-from`} />
                                        <input type="date" className="form-input py-1 text-xs" value={text(s.until)} onChange={(e) => update(i, { until: e.target.value || null })} aria-label={t.until} data-testid={`${prefix}-${i}-until`} />
                                    </>
                                )}
                                <input type="number" min="0" max="99" className="form-input w-16 py-1 text-xs" placeholder={t.mobile_order_short} title={t.mobile_order} value={text(s.mobile_order)} onChange={(e) => update(i, { mobile_order: e.target.value === '' ? null : Number(e.target.value) })} data-testid={`${prefix}-${i}-mobile-order`} />
                                <button type="button" className="btn-secondary px-2 py-1 text-xs" onClick={() => move(i, -1)} disabled={i === 0} aria-label={t.move_up} data-testid={`${prefix}-${i}-up`}>↑</button>
                                <button type="button" className="btn-secondary px-2 py-1 text-xs" onClick={() => move(i, 1)} disabled={i === sections.length - 1} aria-label={t.move_down} data-testid={`${prefix}-${i}-down`}>↓</button>
                                <button type="button" className="btn-secondary px-2 py-1 text-xs" onClick={() => setOpen(open === i ? null : i)} data-testid={`${prefix}-${i}-toggle`}>{open === i ? t.close : t.settings}</button>
                                <button type="button" className="px-2 py-1 text-xs text-red-700 underline" onClick={() => onChange(sections.filter((_, j) => j !== i))} data-testid={`${prefix}-${i}-remove`}>{t.remove}</button>
                            </span>
                        </div>
                        {open === i && (
                            <div className="space-y-3 border-t bg-gray-50 p-3" data-testid={`${prefix}-${i}-settings`}>
                                {Object.entries(d.schema[s.type] || {}).map(([field, kind]) => (
                                    <FieldEditor key={field} field={field} kind={kind} settings={s.settings || {}} onChange={(settings) => update(i, { settings })} d={d} t={t} prefix={`${prefix}-${i}`} />
                                ))}
                            </div>
                        )}
                    </li>
                ))}
            </ol>
            <div className="mt-3 flex flex-wrap items-end gap-2">
                <Field label={t.add_section}>
                    <select className="form-input" value={type} onChange={(e) => setType(e.target.value)} data-testid={`${prefix}-add-type`}>
                        {types.map((k) => <option key={k} value={k} disabled={locked.includes(k)}>{t[`section_${k}`] || k}{locked.includes(k) ? ` (${t.section_locked})` : ''}</option>)}
                    </select>
                </Field>
                <button type="button" className="btn-secondary" onClick={add} disabled={sections.length >= d.limits.sections} data-testid={`${prefix}-add`}>{t.add}</button>
                <span className="text-xs text-gray-500">{t.sections_count.replace(':count', sections.length).replace(':max', d.limits.sections)}</span>
            </div>
        </div>
    );
}

function SeoFields({ seo, onChange, d, t, prefix }) {
    return (
        <div className="grid gap-3 md:grid-cols-2">
            <Field label={t.seo_title}><input className="form-input w-full" maxLength={70} value={text(seo.title)} onChange={(e) => onChange({ ...seo, title: e.target.value })} data-testid={`${prefix}-seo-title`} /></Field>
            <Field label={t.seo_description}><textarea className="form-input w-full" rows={2} maxLength={160} value={text(seo.description)} onChange={(e) => onChange({ ...seo, description: e.target.value })} data-testid={`${prefix}-seo-description`} /></Field>
            <Field label={t.seo_image} className="md:col-span-2"><ImagePicker library={d.library} value={seo.image} multiple={false} onChange={(image) => onChange({ ...seo, image })} t={t} testid={`${prefix}-seo-image`} /></Field>
        </div>
    );
}

function Pages({ d, t, activePage, setActivePage, onSaved }) {
    const [draft, setDraft] = useState({ title: '', title_dv: '', title_ar: '', slug: '' });
    const page = d.pages.find((p) => p.id === activePage) || null;
    const [sections, setSections] = useState(page ? page.sections : []);
    const [seo, setSeo] = useState(page ? page.seo : { title: '', description: '', image: null });
    const [busy, setBusy] = useState(false);
    const openPage = (p) => { setActivePage(p.id); setSections(p.sections); setSeo(p.seo); };

    return (
        <div className="space-y-4">
            <form className="grid gap-2 rounded border bg-white p-3 md:grid-cols-5" data-testid="new-page-form" onSubmit={(e) => { e.preventDefault(); router.post('/vendor/storefront/pages', draft, { preserveScroll: true, onSuccess: () => setDraft({ title: '', title_dv: '', title_ar: '', slug: '' }) }); }}>
                <Field label={t.page_title}><input className="form-input w-full" value={draft.title} onChange={(e) => setDraft({ ...draft, title: e.target.value })} required data-testid="new-page-title" /></Field>
                <Field label={t.title_dv}><input className="form-input w-full" dir="rtl" value={draft.title_dv} onChange={(e) => setDraft({ ...draft, title_dv: e.target.value })} /></Field>
                <Field label={t.title_ar}><input className="form-input w-full" dir="rtl" value={draft.title_ar} onChange={(e) => setDraft({ ...draft, title_ar: e.target.value })} /></Field>
                <Field label={t.slug_label}><input className="form-input w-full" placeholder={t.slug_from_title} value={draft.slug} onChange={(e) => setDraft({ ...draft, slug: e.target.value })} data-testid="new-page-slug" /></Field>
                <div className="flex items-end"><button type="submit" className="btn-primary" disabled={d.pages.length >= d.limits.pages} data-testid="create-page">{t.add_page}</button></div>
            </form>
            {d.pages.length === 0 ? <p className="text-sm text-gray-600">{t.no_pages_yet}</p> : (
                <ul className="divide-y rounded border bg-white text-sm" data-testid="page-list">
                    {d.pages.map((p) => (
                        <li key={p.id} className="flex flex-wrap items-center gap-2 p-2" data-testid={`page-row-${p.slug}`}>
                            <span className="font-semibold" dir="auto">{p.title}</span>
                            <a href={`/shop/${d.vendorSlug}/p/${p.slug}`} target="_blank" rel="noreferrer" className="text-xs text-blue-700 underline">/p/{p.slug}</a>
                            <span className="text-xs text-gray-500">{p.published_at ? t.published_on.replace(':date', p.published_at) : t.not_published_yet_short}{p.draft_dirty ? ` · ${t.draft_differs}` : ''}</span>
                            <span className="ms-auto flex gap-2">
                                <button type="button" className={`btn-secondary px-2 py-1 text-xs ${activePage === p.id ? 'ring-1 ring-gray-900' : ''}`} onClick={() => openPage(p)} data-testid={`edit-page-${p.slug}`}>{t.edit_sections}</button>
                                <button type="button" className="px-2 py-1 text-xs text-red-700 underline" onClick={() => { if (window.confirm(t.confirm_delete_page)) router.delete(`/vendor/storefront/pages/${p.id}`, { preserveScroll: true, onSuccess: () => setActivePage(null) }); }} data-testid={`delete-page-${p.slug}`}>{t.delete}</button>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            {page && (
                <section className="rounded border bg-white p-3" data-testid="page-editor">
                    <h3 className="mb-2 font-semibold" dir="auto">{t.page_sections_heading.replace(':title', page.title)}</h3>
                    <SectionsEditor sections={sections} onChange={setSections} d={d} t={t} prefix="page" />
                    <h4 className="mb-2 mt-4 font-semibold">{t.seo_heading}</h4>
                    <SeoFields seo={seo} onChange={setSeo} d={d} t={t} prefix="page" />
                    <button type="button" className="btn-primary mt-3" disabled={busy} data-testid="save-page-sections" onClick={() => {
                        setBusy(true);
                        router.post(`/vendor/storefront/pages/${page.id}/sections`, { sections, seo }, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: (res) => { const fresh = res.props.designer.pages.find((p) => p.id === page.id); if (fresh) { setSections(fresh.sections); setSeo(fresh.seo); } onSaved(); } });
                    }}>{t.save_page}</button>
                </section>
            )}
        </div>
    );
}

function Collections({ d, t }) {
    const blank = { id: null, name: '', name_dv: '', name_ar: '', slug: '', description: '', kind: 'manual', product_ids: [], rule: { tags: [], category_id: '' }, is_active: true };
    const [form, setForm] = useState(blank);
    const set = (patch) => setForm({ ...form, ...patch });
    const chosen = form.product_ids.map(Number);

    return (
        <div className="space-y-4">
            <form className="grid gap-3 rounded border bg-white p-3 md:grid-cols-3" data-testid="collection-form" onSubmit={(e) => {
                e.preventDefault();
                router.post(form.id ? `/vendor/storefront/collections/${form.id}` : '/vendor/storefront/collections', { ...form, rule: { tags: form.rule.tags, category_id: form.rule.category_id || null } }, { preserveScroll: true, onSuccess: () => setForm(blank) });
            }}>
                <h3 className="font-semibold md:col-span-3">{form.id ? t.edit_collection : t.new_collection}</h3>
                <Field label={t.collection_name}><input className="form-input w-full" value={form.name} onChange={(e) => set({ name: e.target.value })} required data-testid="collection-name" /></Field>
                <Field label={t.name_dv}><input className="form-input w-full" dir="rtl" value={form.name_dv} onChange={(e) => set({ name_dv: e.target.value })} /></Field>
                <Field label={t.name_ar}><input className="form-input w-full" dir="rtl" value={form.name_ar} onChange={(e) => set({ name_ar: e.target.value })} /></Field>
                {!form.id && <Field label={t.slug_label}><input className="form-input w-full" placeholder={t.slug_from_title} value={form.slug} onChange={(e) => set({ slug: e.target.value })} data-testid="collection-slug" /></Field>}
                <Field label={t.description} className={form.id ? 'md:col-span-2' : ''}><input className="form-input w-full" value={form.description} onChange={(e) => set({ description: e.target.value })} /></Field>
                <Field label={t.collection_kind}>
                    <select className="form-input w-full" value={form.kind} onChange={(e) => set({ kind: e.target.value })} data-testid="collection-kind">
                        <option value="manual">{t.collection_manual}</option>
                        <option value="rule">{t.collection_rule}</option>
                    </select>
                </Field>
                {form.kind === 'manual' ? (
                    <Field label={t.collection_products} className="md:col-span-3">
                        <div className="max-h-48 overflow-y-auto rounded border p-2 text-sm" data-testid="collection-products">
                            {d.products.map((p) => {
                                const on = chosen.includes(p.id);
                                return (
                                    <label key={p.id} className="flex items-center gap-2">
                                        <input type="checkbox" checked={on} onChange={(e) => set({ product_ids: e.target.checked ? [...chosen, p.id] : chosen.filter((id) => id !== p.id) })} data-testid={`collection-product-${p.slug}`} />
                                        {on && <span className="text-xs text-gray-500">{chosen.indexOf(p.id) + 1}.</span>}
                                        <span>{p.title}</span>
                                    </label>
                                );
                            })}
                        </div>
                    </Field>
                ) : (
                    <>
                        <Field label={t.rule_tags} className="md:col-span-2">
                            <div className="flex flex-wrap gap-2 text-sm" data-testid="rule-tags">
                                {d.tags.length === 0 && <span className="text-xs text-gray-500">{t.no_tags_yet}</span>}
                                {d.tags.map((tag) => (
                                    <label key={tag} className="flex items-center gap-1 rounded border px-2 py-1"><input type="checkbox" checked={form.rule.tags.includes(tag)} onChange={(e) => set({ rule: { ...form.rule, tags: e.target.checked ? [...form.rule.tags, tag] : form.rule.tags.filter((x) => x !== tag) } })} data-testid={`rule-tag-${tag}`} /> {tag}</label>
                                ))}
                            </div>
                        </Field>
                        <Field label={t.rule_category}>
                            <select className="form-input w-full" value={text(form.rule.category_id)} onChange={(e) => set({ rule: { ...form.rule, category_id: e.target.value } })} data-testid="rule-category">
                                <option value="">{t.any_category}</option>
                                {d.categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </Field>
                    </>
                )}
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.is_active} onChange={(e) => set({ is_active: e.target.checked })} /> {t.collection_active}</label>
                <div className="flex gap-3 md:col-span-3">
                    <button type="submit" className="btn-primary" disabled={!form.id && d.collections.length >= d.limits.collections} data-testid="save-collection">{t.save}</button>
                    {form.id && <button type="button" className="text-sm underline" onClick={() => setForm(blank)}>{t.cancel}</button>}
                </div>
            </form>
            {d.collections.length === 0 ? <p className="text-sm text-gray-600">{t.no_collections_yet}</p> : (
                <ul className="divide-y rounded border bg-white text-sm" data-testid="collection-list">
                    {d.collections.map((c) => (
                        <li key={c.id} className="flex flex-wrap items-center gap-2 p-2" data-testid={`collection-row-${c.slug}`}>
                            <span className="font-semibold" dir="auto">{c.name}</span>
                            <a href={`/shop/${d.vendorSlug}/${c.slug}`} target="_blank" rel="noreferrer" className="text-xs text-blue-700 underline">/{c.slug}</a>
                            <span className="text-xs text-gray-500">{c.kind === 'manual' ? t.collection_manual : t.collection_rule} · {t.result_count.replace(':count', c.for_sale_count)}{c.is_active ? '' : ` · ${t.inactive}`}</span>
                            <span className="ms-auto flex gap-2">
                                <button type="button" className="btn-secondary px-2 py-1 text-xs" onClick={() => setForm({ ...c, slug: c.slug, rule: { tags: c.rule.tags || [], category_id: c.rule.category_id || '' } })} data-testid={`edit-collection-${c.slug}`}>{t.edit}</button>
                                <button type="button" className="px-2 py-1 text-xs text-red-700 underline" onClick={() => { if (window.confirm(t.confirm_delete_collection)) router.delete(`/vendor/storefront/collections/${c.id}`, { preserveScroll: true }); }} data-testid={`delete-collection-${c.slug}`}>{t.delete}</button>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function Library({ d, t }) {
    const [files, setFiles] = useState([]);
    const [alt, setAlt] = useState('');
    const [busy, setBusy] = useState(false);

    return (
        <div className="space-y-4">
            <form className="flex flex-wrap items-end gap-3 rounded border bg-white p-3" data-testid="upload-form" onSubmit={(e) => {
                e.preventDefault();
                if (files.length === 0) return;
                setBusy(true);
                router.post('/vendor/storefront/images', { images: files, alt }, { forceFormData: true, preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => { setFiles([]); setAlt(''); } });
            }}>
                <Field label={t.upload_images}><input type="file" multiple accept="image/jpeg,image/png,image/webp" className="block text-xs" onChange={(e) => setFiles(Array.from(e.target.files || []))} data-testid="upload-images" /></Field>
                <Field label={t.image_alt}><input className="form-input w-64" value={alt} onChange={(e) => setAlt(e.target.value)} /></Field>
                <button type="submit" className="btn-primary" disabled={busy || files.length === 0} data-testid="upload-submit">{t.upload}</button>
                <span className="text-xs text-gray-500">{t.images_count.replace(':count', d.library.length).replace(':max', d.limits.images)} · {t.image_logo_hint}</span>
            </form>
            {d.library.length === 0 ? <p className="text-sm text-gray-600">{t.library_empty}</p> : (
                <ul className="grid grid-cols-2 gap-3 md:grid-cols-4" data-testid="library">
                    {d.library.map((img) => (
                        <li key={img.id} className="rounded border bg-white p-2 text-xs" data-testid={`library-image-${img.id}`}>
                            {img.url ? <img src={img.url} alt={img.alt || ''} className="mb-1 aspect-square w-full rounded object-cover" /> : <span className="block aspect-square rounded bg-gray-100" />}
                            <input className="form-input mb-1 w-full py-1 text-xs" defaultValue={img.alt || ''} placeholder={t.image_alt} onBlur={(e) => { if (e.target.value !== (img.alt || '')) router.post(`/vendor/storefront/images/${img.id}`, { alt: e.target.value }, { preserveScroll: true }); }} />
                            <button type="button" className="text-red-700 underline" onClick={() => router.delete(`/vendor/storefront/images/${img.id}`, { preserveScroll: true })}>{t.remove}</button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default function VendorSections({ t, vendor, designer, preview_url, public_url }) {
    const { flash = {}, errors } = usePage().props;
    const isOwner = vendor.role === 'owner';
    const d = { ...designer, vendorSlug: vendor.slug };
    const [tab, setTab] = useState('home');
    const [sections, setSections] = useState(d.sections);
    const [navigation, setNavigation] = useState(d.navigation);
    const [seo, setSeo] = useState(d.seo);
    const [activePage, setActivePage] = useState(null);
    const [previewKey, setPreviewKey] = useState(0);
    const [busy, setBusy] = useState(false);
    const [note, setNote] = useState('');
    const refresh = () => setPreviewKey((k) => k + 1);
    const saveHome = () => {
        setBusy(true);
        router.post('/vendor/storefront/sections', { sections, navigation, seo }, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onSuccess: (res) => { setSections(res.props.designer.sections); setNavigation(res.props.designer.navigation); setSeo(res.props.designer.seo); refresh(); },
        });
    };
    const previewSrc = tab === 'pages' && activePage ? `/vendor/storefront/pages/${activePage}/preview` : preview_url;
    const tabs = ['home', 'menu', 'pages', 'collections', 'images'];

    return (
        <AppShell title={t.sections_title}>
            <FormErrors errors={errors} className="mb-4" />
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="flash-success">{flash.success}</p>}
            {d.moderation.held && <p className="mb-4 rounded border border-red-300 bg-red-50 p-3 text-red-800" data-testid="moderation-held"><strong>{t.storefront_held}</strong> {d.moderation.note}</p>}
            {!d.moderation.held && d.moderation.note && <p className="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-amber-900" data-testid="moderation-note"><strong>{t.changes_required}</strong> {d.moderation.note}</p>}
            {d.moderation.locked_types.length > 0 && <p className="mb-4 text-sm text-red-800" data-testid="locked-types">{t.locked_types_notice.replace(':types', d.moderation.locked_types.map((k) => t[`section_${k}`] || k).join(', '))}</p>}

            <header className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold" data-testid="sections-heading">{t.sections_title} · {vendor.name}</h1>
                    <p className="text-sm text-gray-600">
                        <a href="/vendor" className="text-blue-700 underline">{t.portal_title}</a>
                        {' · '}
                        <a href="/vendor/storefront" className="text-blue-700 underline" data-testid="open-identity">{t.designer_title}</a>
                        {' · '}
                        <a href={public_url} target="_blank" rel="noreferrer" className="text-blue-700 underline" data-testid="open-public">{t.open_shop_page}</a>
                        {' · '}
                        {d.published_at ? t.published_on.replace(':date', d.published_at) : t.not_published_yet}
                    </p>
                </div>
                {isOwner && (
                    <span className="flex flex-wrap items-center gap-2">
                        <input className="form-input w-48" placeholder={t.version_note} value={note} onChange={(e) => setNote(e.target.value)} data-testid="version-note" />
                        <button type="button" className="btn-primary" disabled={!d.exists || d.moderation.held} title={d.moderation.held ? t.storefront_held : ''} data-testid="publish" onClick={() => router.post('/vendor/storefront/publish', { note }, { preserveScroll: true, onSuccess: () => { setNote(''); refresh(); } })}>{t.publish}</button>
                        {d.draft_dirty && <span className="text-xs text-amber-800" data-testid="draft-dirty">{t.draft_differs}</span>}
                    </span>
                )}
            </header>

            <div className="grid gap-6 lg:grid-cols-2">
                <div>
                    <nav className="mb-3 flex flex-wrap gap-1 border-b text-sm" data-testid="designer-tabs">
                        {tabs.map((k) => (
                            <button key={k} type="button" className={`px-3 py-2 ${tab === k ? 'border-b-2 border-gray-900 font-semibold' : 'text-gray-600'}`} onClick={() => setTab(k)} data-testid={`tab-${k}`}>{t[`tab_${k}`]}</button>
                        ))}
                    </nav>

                    {tab === 'home' && (
                        <section data-testid="home-sections">
                            <p className="mb-3 text-sm text-gray-600">{t.sections_intro}</p>
                            <SectionsEditor sections={sections} onChange={setSections} d={d} t={t} prefix="home" />
                            <button type="button" className="btn-primary mt-4" disabled={busy} onClick={saveHome} data-testid="save-sections">{t.save_draft}</button>
                        </section>
                    )}
                    {tab === 'menu' && (
                        <section className="space-y-4" data-testid="menu-seo">
                            <div>
                                <h2 className="mb-1 font-semibold">{t.menu_heading}</h2>
                                <p className="mb-2 text-sm text-gray-600">{t.menu_intro}</p>
                                <Rows items={navigation} max={8} blank={() => ({ kind: 'all', target: '', label: '', label_dv: '', label_ar: '' })} onChange={setNavigation} t={t} addLabel={t.add_menu_entry} testid="nav"
                                    render={(item, patch, i) => <LinkRow value={item} onChange={patch} d={d} t={t} withLabel testid={`nav-${i}`} />} />
                            </div>
                            <div>
                                <h2 className="mb-1 font-semibold">{t.seo_heading}</h2>
                                <p className="mb-2 text-sm text-gray-600">{t.seo_intro}</p>
                                <SeoFields seo={seo} onChange={setSeo} d={d} t={t} prefix="home" />
                            </div>
                            <button type="button" className="btn-primary" disabled={busy} onClick={saveHome} data-testid="save-menu">{t.save_draft}</button>
                        </section>
                    )}
                    {tab === 'pages' && <Pages d={d} t={t} activePage={activePage} setActivePage={setActivePage} onSaved={refresh} />}
                    {tab === 'collections' && <Collections d={d} t={t} />}
                    {tab === 'images' && <Library d={d} t={t} />}
                </div>

                <section className="rounded-lg border bg-white p-2">
                    <div className="mb-2 flex items-center justify-between px-2 text-sm">
                        <span className="font-semibold">{t.preview_heading}</span>
                        <button type="button" className="text-blue-700 underline" onClick={refresh}>{t.refresh}</button>
                    </div>
                    <iframe key={`${previewKey}-${previewSrc}`} title={t.preview_heading} src={previewSrc} className="h-[75vh] w-full rounded border" data-testid="preview-frame" />
                    <p className="mt-1 px-2 text-xs text-gray-500">{t.preview_hint}</p>
                </section>
            </div>
        </AppShell>
    );
}
