import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import TagCanvas from './TagCanvas';
import { clampElement, fieldKeysOf, newElement, nextId, resolveColour, round, withFields } from './design';

/**
 * NAME_TAGS_PLAN §7.1 (NT2): the designer — a canvas, a toolbar, the
 * properties of the selected element, the layers, the colour schemes and
 * the fields. Template mode (the office) shows all of it; later modes hide
 * parts. A history of snapshots gives undo and redo; arrows nudge the
 * selected element; Delete removes it. Phone: the canvas fits the width
 * and the panels stack below.
 *
 *   design, onChange(design)   the model, owned by the page
 *   fonts, images, fieldKeys, maxElements, sample   from the server
 *   t                          the print phrase book
 */
const fill = (s, vars) => Object.entries(vars).reduce((out, [k, v]) => out.split(`:${k}`).join(String(v ?? '')), s || '');
const HISTORY = 60;

export default function Designer({ design, onChange, fonts = {}, images = [], fieldKeys = [], maxElements = 60, sample = {}, t = {}, shape = 'rect' }) {
    const [selected, setSelected] = useState(null);
    const history = useRef({ past: [], future: [] });
    const [, tick] = useState(0);
    const frame = useRef(null);
    const [scale, setScale] = useState(4);
    const imageUrls = useMemo(() => Object.fromEntries(images.map((i) => [i.media, i.url])), [images]);
    const current = design.elements.find((e) => e.id === selected) || null;

    // The canvas fits its frame: at most 6 px per mm, never wider than the column.
    useLayoutEffect(() => {
        const el = frame.current;
        if (!el) return undefined;
        const fit = () => setScale(Math.max(1, Math.min(6, round((el.clientWidth - 24) / design.tag.w, 2))));
        fit();
        const ro = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(fit);
        ro?.observe(el);
        return () => ro?.disconnect();
    }, [design.tag.w]);

    /** A change that is a step in the history (every change but a drag's intermediate frames, whose start is what undo returns to). */
    const dragBase = useRef(null);
    const commit = useCallback((next) => {
        history.current.past = [...history.current.past.slice(-HISTORY), dragBase.current ?? design];
        history.current.future = [];
        dragBase.current = null;
        onChange(withFields(next));
        tick((n) => n + 1);
    }, [design, onChange]);

    const undo = () => {
        const prev = history.current.past.pop();
        if (!prev) return;
        history.current.future.push(design);
        onChange(prev);
        tick((n) => n + 1);
    };
    const redo = () => {
        const next = history.current.future.pop();
        if (!next) return;
        history.current.past.push(design);
        onChange(next);
        tick((n) => n + 1);
    };

    const setElements = (elements, isCommit) => {
        if (isCommit) { commit({ ...design, elements }); return; }
        if (dragBase.current === null) dragBase.current = design;
        onChange({ ...design, elements });
    };
    const patch = (id, changes) => commit({ ...design, elements: design.elements.map((e) => (e.id === id ? clampElement({ ...e, ...changes }, design.tag) : e)) });
    const add = (type) => {
        if (design.elements.length >= maxElements) return;
        const extra = type === 'image' && images[0] ? { media: images[0].media } : {};
        const el = newElement(type, design, extra);
        commit({ ...design, elements: [...design.elements, el] });
        setSelected(el.id);
    };
    const remove = (id) => { commit({ ...design, elements: design.elements.filter((e) => e.id !== id) }); setSelected(null); };
    const duplicate = (el) => {
        if (design.elements.length >= maxElements) return;
        const copy = clampElement({ ...el, id: nextId(el.type), x: el.x + 3, y: el.y + 3, locked: false }, design.tag);
        commit({ ...design, elements: [...design.elements, copy] });
        setSelected(copy.id);
    };
    const reorder = (id, by) => {
        const i = design.elements.findIndex((e) => e.id === id);
        const j = i + by;
        if (i < 0 || j < 0 || j >= design.elements.length) return;
        const elements = [...design.elements];
        [elements[i], elements[j]] = [elements[j], elements[i]];
        commit({ ...design, elements });
    };

    // Keys: arrows nudge, Delete removes, Ctrl+Z / Ctrl+Y the history — unless a field has the focus.
    const onKey = (e) => {
        if (/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? redo() : undo(); return; }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') { e.preventDefault(); redo(); return; }
        if (!current || current.locked) return;
        const step = e.shiftKey ? 5 : 0.5;
        const moves = { ArrowLeft: { x: current.x - step }, ArrowRight: { x: current.x + step }, ArrowUp: { y: current.y - step }, ArrowDown: { y: current.y + step } };
        if (moves[e.key]) { e.preventDefault(); patch(current.id, moves[e.key]); }
        if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); remove(current.id); }
    };

    useEffect(() => { if (selected && !design.elements.some((e) => e.id === selected)) setSelected(null); }, [design.elements, selected]);

    const keys = fieldKeysOf(design);

    return (
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]" data-testid="print-designer" dir="ltr">
            <div>
                <div className="mb-2 flex flex-wrap items-center gap-2" data-testid="designer-toolbar">
                    {['text', 'image', 'rect', 'ellipse', 'line'].map((type) => (
                        <button key={type} type="button" className="btn-secondary text-sm" onClick={() => add(type)} disabled={design.elements.length >= maxElements} data-testid={`designer-add-${type}`}>+ {t[`add_${type}`]}</button>
                    ))}
                    <span className="mx-1 text-xs text-gray-500">{fill(t.elements_count, { count: design.elements.length, max: maxElements })}</span>
                    <button type="button" className="text-sm underline disabled:opacity-40" onClick={undo} disabled={history.current.past.length === 0} data-testid="designer-undo">{t.undo}</button>
                    <button type="button" className="text-sm underline disabled:opacity-40" onClick={redo} disabled={history.current.future.length === 0} data-testid="designer-redo">{t.redo}</button>
                </div>
                <div ref={frame} className="overflow-auto rounded-lg border bg-gray-100 p-3 outline-none" tabIndex={0} onKeyDown={onKey} data-testid="designer-frame">
                    <TagCanvas design={design} shape={shape} fonts={fonts} images={imageUrls} row={sample} scale={scale} selected={selected} onSelect={setSelected} onChange={setElements} interactive />
                </div>
                <p className="mt-1 text-xs text-gray-500">{t.keyboard_hint}</p>

                <section className="mt-4 rounded-lg border bg-white p-3">
                    <h3 className="mb-2 text-sm font-semibold">{t.layers}</h3>
                    {design.elements.length === 0 ? <p className="text-xs text-gray-500">{t.nothing_selected}</p> : (
                        <ul className="divide-y text-sm" data-testid="designer-layers">
                            {[...design.elements].reverse().map((el) => (
                                <li key={el.id} className={`flex items-center gap-2 py-1 ${selected === el.id ? 'bg-blue-50' : ''}`}>
                                    <button type="button" className="min-w-0 flex-1 truncate text-start" onClick={() => setSelected(el.id)} data-testid={`layer-${el.id}`}>
                                        <span className="text-gray-500">{t[`add_${el.type}`]}</span> {el.type === 'text' ? <span dir="auto">{el.text}</span> : null}{el.field ? <span className="ms-1 rounded bg-amber-100 px-1 text-xs">{`{${el.field}}`}</span> : null}{el.locked ? <span className="ms-1 text-xs text-gray-400">🔒</span> : null}
                                    </button>
                                    <button type="button" className="px-1 text-xs underline" onClick={() => reorder(el.id, 1)} title={t.forward}>▲</button>
                                    <button type="button" className="px-1 text-xs underline" onClick={() => reorder(el.id, -1)} title={t.backward}>▼</button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <div className="space-y-4">
                <section className="rounded-lg border bg-white p-3" data-testid="designer-properties">
                    <h3 className="mb-2 text-sm font-semibold">{t.properties}</h3>
                    {current ? <Properties el={current} design={design} fonts={fonts} images={images} fieldKeys={fieldKeys} patch={patch} remove={remove} duplicate={duplicate} t={t} /> : <p className="text-xs text-gray-500">{t.nothing_selected}</p>}
                </section>

                <section className="rounded-lg border bg-white p-3">
                    <h3 className="mb-2 text-sm font-semibold">{t.background}</h3>
                    <ColourField value={design.background.color} design={design} onChange={(color) => commit({ ...design, background: { ...design.background, color: color || '#FFFFFF' } })} t={t} testId="designer-background" />
                </section>

                <Palette design={design} commit={commit} t={t} />

                <section className="rounded-lg border bg-white p-3" data-testid="designer-fields">
                    <h3 className="mb-2 text-sm font-semibold">{t.fields_on_tag}</h3>
                    {keys.length === 0 ? <p className="text-xs text-amber-800">{t.fields_none}</p> : (
                        <ul className="space-y-1 text-sm">
                            {design.fields.map((f) => (
                                <li key={f.key} className="flex items-center justify-between gap-2">
                                    <code className="rounded bg-gray-100 px-1">{`{${f.key}}`}</code>
                                    <label className="flex items-center gap-1 text-xs"><input type="checkbox" checked={!!f.required} onChange={(e) => commit({ ...design, fields: design.fields.map((x) => (x.key === f.key ? { ...x, required: e.target.checked } : x)) })} /> {t.field_required}</label>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </div>
    );
}

function Num({ label, value, onChange, step = 0.5, min, max, testId }) {
    return (
        <label className="text-xs">{label}<input type="number" step={step} min={min} max={max} className="form-input w-full" value={value ?? ''} onChange={(e) => onChange(e.target.value === '' ? 0 : Number(e.target.value))} data-testid={testId} /></label>
    );
}

export function ColourField({ value, design, onChange, allowNone = false, t, testId }) {
    const mode = value === '{primary}' || value === '{secondary}' ? value : value ? 'custom' : 'none';
    const hex = resolveColour(value, design) || '#000000';
    return (
        <div className="flex items-center gap-2" data-testid={testId}>
            <select className="form-input flex-1" value={mode} onChange={(e) => onChange(e.target.value === 'custom' ? hex : e.target.value === 'none' ? null : e.target.value)}>
                <option value="custom">{t.colour_custom}</option>
                <option value="{primary}">{t.colour_primary}</option>
                <option value="{secondary}">{t.colour_secondary}</option>
                {allowNone && <option value="none">{t.colour_none}</option>}
            </select>
            {mode === 'custom' && <input type="color" className="h-9 w-12 rounded border" value={hex} onChange={(e) => onChange(e.target.value.toUpperCase())} aria-label={t.colour} />}
            {mode !== 'custom' && mode !== 'none' && <span className="inline-block h-6 w-6 rounded border" style={{ background: hex }} />}
        </div>
    );
}

function Properties({ el, design, fonts, images, fieldKeys, patch, remove, duplicate, t }) {
    const set = (changes) => patch(el.id, changes);
    const weights = fonts[el.font]?.weights || [400];
    const known = fieldKeys.includes(el.field);
    const fieldMode = !el.field ? '' : known ? el.field : 'custom';

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center gap-2 text-xs">
                <span className="font-medium">{t[`add_${el.type}`]}</span>
                <button type="button" className="underline" onClick={() => set({ locked: !el.locked })} data-testid="prop-lock">{el.locked ? t.unlock : t.lock}</button>
                <button type="button" className="underline" onClick={() => duplicate(el)}>{t.duplicate}</button>
                <button type="button" className="text-red-700 underline" onClick={() => remove(el.id)} data-testid="prop-remove">{t.remove_element}</button>
            </div>
            {el.locked && <p className="text-xs text-gray-500">{t.locked_el}</p>}
            <fieldset disabled={el.locked} className="grid grid-cols-2 gap-2">
                <Num label={t.position_x} value={el.x} onChange={(x) => set({ x })} testId="prop-x" />
                <Num label={t.position_y} value={el.y} onChange={(y) => set({ y })} testId="prop-y" />
                <Num label={t.width_mm} value={el.w} onChange={(w) => set({ w })} min={0.5} testId="prop-w" />
                <Num label={t.height_mm} value={el.h} onChange={(h) => set({ h })} min={0.5} testId="prop-h" />
                <Num label={t.rotation} value={el.rotate} onChange={(rotate) => set({ rotate: Math.max(-360, Math.min(360, rotate)) })} step={1} min={-360} max={360} testId="prop-rotate" />

                {el.type === 'text' && (
                    <>
                        <label className="col-span-2 text-xs">{t.text}<textarea rows={2} className="form-input w-full" dir="auto" value={el.text} maxLength={200} onChange={(e) => set({ text: e.target.value })} data-testid="prop-text" /></label>
                        <label className="text-xs">{t.font}<select className="form-input w-full" value={el.font} onChange={(e) => set({ font: e.target.value, weight: (fonts[e.target.value]?.weights || [400]).includes(el.weight) ? el.weight : (fonts[e.target.value]?.weights || [400])[0] })} data-testid="prop-font">
                            {Object.entries(fonts).map(([id, f]) => <option key={id} value={id}>{f.label} ({f.scripts.join(', ')})</option>)}
                        </select></label>
                        <Num label={t.font_size} value={el.size} onChange={(size) => set({ size: Math.max(4, Math.min(72, size)) })} step={1} min={4} max={72} testId="prop-size" />
                        <label className="text-xs">{t.weight}<select className="form-input w-full" value={el.weight} onChange={(e) => set({ weight: Number(e.target.value) })}>
                            {weights.map((wt) => <option key={wt} value={wt}>{wt >= 700 ? t.weight_bold : t.weight_regular}</option>)}
                        </select></label>
                        <label className="text-xs">{t.colour}<ColourField value={el.color} design={design} onChange={(color) => set({ color: color || '#111111' })} t={t} /></label>
                        <label className="text-xs">{t.align}<select className="form-input w-full" value={el.align} onChange={(e) => set({ align: e.target.value })}>
                            {['left', 'center', 'right'].map((a) => <option key={a} value={a}>{t[`align_${a}`]}</option>)}
                        </select></label>
                        <label className="text-xs">{t.direction}<select className="form-input w-full" value={el.dir} onChange={(e) => set({ dir: e.target.value })}>
                            {['auto', 'ltr', 'rtl'].map((d) => <option key={d} value={d}>{t[`dir_${d}`]}</option>)}
                        </select></label>
                        <label className="col-span-2 text-xs">{t.field}<select className="form-input w-full" value={fieldMode} onChange={(e) => {
                            const v = e.target.value;
                            // A field's text must name its token, or the tag would print the label instead of the value.
                            if (v === '') set({ field: null, text: el.text.replace(/\{[a-z0-9_]+\}/g, '').trim() || 'Text' });
                            else set({ field: v, text: el.text.includes(`{${v}}`) ? el.text : `{${v}}` });
                        }} data-testid="prop-field">
                            <option value="">{t.field_none}</option>
                            {fieldKeys.map((k) => <option key={k} value={k}>{t[`field_${k}`] || k}</option>)}
                            <option value="custom">{t.field_custom}</option>
                        </select></label>
                        {fieldMode === 'custom' && <label className="col-span-2 text-xs">{t.field_custom_key}<input className="form-input w-full" dir="ltr" value={el.field} onChange={(e) => { const key = e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '').slice(0, 30) || 'custom'; set({ field: key, text: el.text.includes(`{${el.field}}`) ? el.text.split(`{${el.field}}`).join(`{${key}}`) : `{${key}}` }); }} data-testid="prop-field-key" /></label>}
                        {el.field && <p className="col-span-2 text-xs text-gray-500">{t.field_hint}</p>}
                    </>
                )}

                {el.type === 'image' && (
                    <>
                        <div className="col-span-2 text-xs">{t.picture}
                            {images.length === 0 ? <p className="text-gray-500">{t.images_none}</p> : (
                                <div className="mt-1 flex flex-wrap gap-1" data-testid="prop-images">
                                    {images.map((img) => (
                                        <button key={img.id} type="button" className={`rounded border p-0.5 ${el.media === img.media ? 'border-blue-600 ring-2 ring-blue-200' : ''}`} onClick={() => set({ media: img.media })} title={img.alt || ''}>
                                            <img src={img.thumb || img.url} alt={img.alt || ''} className="h-12 w-12 object-contain" />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                        <label className="text-xs">{t.fit}<select className="form-input w-full" value={el.fit} onChange={(e) => set({ fit: e.target.value })}>
                            <option value="contain">{t.fit_contain}</option><option value="cover">{t.fit_cover}</option>
                        </select></label>
                    </>
                )}

                {(el.type === 'rect' || el.type === 'ellipse') && (
                    <>
                        <label className="col-span-2 text-xs">{t.fill}<ColourField value={el.fill} design={design} onChange={(fill) => set({ fill })} allowNone t={t} testId="prop-fill" /></label>
                        <label className="col-span-2 text-xs">{t.stroke}<ColourField value={el.stroke} design={design} onChange={(stroke) => set({ stroke })} allowNone t={t} /></label>
                        <Num label={t.stroke_mm} value={el.stroke_mm} onChange={(stroke_mm) => set({ stroke_mm: Math.max(0, Math.min(5, stroke_mm)) })} step={0.1} min={0} max={5} />
                        {el.type === 'rect' && <Num label={t.radius} value={el.radius} onChange={(radius) => set({ radius: Math.max(0, Math.min(50, radius)) })} step={0.5} min={0} max={50} />}
                    </>
                )}

                {el.type === 'line' && <label className="col-span-2 text-xs">{t.colour}<ColourField value={el.stroke} design={design} onChange={(stroke) => set({ stroke: stroke || '#111111' })} t={t} /></label>}
            </fieldset>
        </div>
    );
}

function Palette({ design, commit, t }) {
    const { scheme, schemes } = design.palette;
    const [name, setName] = useState('');
    const setScheme = (key, colours) => commit({ ...design, palette: { ...design.palette, schemes: { ...schemes, [key]: { ...schemes[key], ...colours } } } });
    const addScheme = () => {
        const key = name.toLowerCase().replace(/[^a-z0-9_-]/g, '');
        if (!key || schemes[key] || Object.keys(schemes).length >= 8) return;
        commit({ ...design, palette: { scheme: key, schemes: { ...schemes, [key]: { ...schemes[scheme] } } } });
        setName('');
    };

    return (
        <section className="rounded-lg border bg-white p-3" data-testid="designer-palette">
            <h3 className="mb-2 text-sm font-semibold">{t.palette}</h3>
            <label className="text-xs">{t.scheme}<select className="form-input w-full" value={scheme} onChange={(e) => commit({ ...design, palette: { ...design.palette, scheme: e.target.value } })} data-testid="palette-scheme">
                {Object.keys(schemes).map((k) => <option key={k} value={k}>{k}</option>)}
            </select></label>
            <div className="mt-2 grid grid-cols-2 gap-2 text-xs">
                <label>{t.primary}<input type="color" className="block h-9 w-full rounded border" value={schemes[scheme]?.primary || '#7A1F2B'} onChange={(e) => setScheme(scheme, { primary: e.target.value.toUpperCase() })} data-testid="palette-primary" /></label>
                <label>{t.secondary}<input type="color" className="block h-9 w-full rounded border" value={schemes[scheme]?.secondary || '#E9D8B4'} onChange={(e) => setScheme(scheme, { secondary: e.target.value.toUpperCase() })} /></label>
            </div>
            <div className="mt-2 flex items-end gap-2 text-xs">
                <label className="flex-1">{t.scheme_name}<input className="form-input w-full" dir="ltr" value={name} onChange={(e) => setName(e.target.value)} /></label>
                <button type="button" className="btn-secondary text-xs" onClick={addScheme} disabled={!name.trim()}>{t.add_scheme}</button>
            </div>
        </section>
    );
}
