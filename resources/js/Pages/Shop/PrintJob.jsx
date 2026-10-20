import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { Fragment, Suspense, lazy, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';
import TagCanvas from '../../Components/Print/TagCanvas';
import { normaliseDesign, withFields } from '../../Components/Print/design';

// NT6: the free-form designer is the Bookstore's largest piece; it loads only on a free-form design (risk R5).
const Designer = lazy(() => import('../../Components/Print/Designer'));

/**
 * NAME_TAGS_PLAN §3.2–3.7 (NT3): one job, from a template, in field mode.
 * The customer fills the fields — one child on every tag, a pasted list,
 * or a grade's subjects — picks the colours and the font, the layout, the
 * paper, colour or black and white, the finishing and the sheets, and sees
 * the price the server works out. Every change is saved as it is made.
 * *Make the proof* draws the sheets with PROOF across them; *Approve* locks
 * the job. An approved job is changed by making a copy.
 */
const job0IsFreeform = (job) => job?.source === 'freeform';
const fill = (s, vars) => Object.entries(vars).reduce((out, [k, v]) => out.split(`:${k}`).join(String(v ?? '')), s || '');
const money = (n) => (Number(n) || 0).toFixed(2);
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

/** The pasted list as the server will read it: tab-separated where a tab appears, else commas. */
function listText(list, keys) {
    return (list || []).map((row) => keys.map((k) => row[k] ?? '').join('\t')).join('\n');
}
function parseList(text, keys) {
    return (text || '').split(/\r\n|\r|\n/).map((line) => {
        const cells = line.includes('\t') ? line.split('\t') : line.split(',');
        return Object.fromEntries(keys.map((k, i) => [k, (cells[i] || '').trim()]));
    });
}

export default function PrintJob(props) {
    const { t = {}, job: initial, layouts = [], papers = [], finishing_options: finishingOptions = [], subject_sets: subjectSets = [], fonts = {}, font_css: fontCss = '', images = {}, settings = {}, signed_in: signedIn = false, designer = null } = props;
    const freeform = job0IsFreeform(initial);
    const { errors = {}, locale = 'en' } = usePage().props;
    const [job, setJob] = useState(initial);
    useEffect(() => setJob(initial), [initial]);
    const design = useMemo(() => normaliseDesign(job.design), [job.design]);
    const keys = useMemo(() => job.fields.map((f) => f.key).filter((k) => k !== 'subject'), [job.fields]);
    const hasSubject = job.fields.some((f) => f.key === 'subject');
    const entry = job.entry || { mode: 'same', row: {}, list: [], subjects: { set_id: null, items: [], language: 'en' } };
    const [listDraft, setListDraft] = useState(() => listText(entry.list, keys));
    const [state, setState] = useState('idle');
    const [problem, setProblem] = useState(null);
    const [preview, setPreview] = useState(0);
    const [busy, setBusy] = useState(false);
    const pending = useRef({});
    const timer = useRef(null);
    const inflight = useRef(Promise.resolve());
    const base = `/${locale}/shop/print/jobs/${job.id}`;
    const open = job.open;

    // Autosave: changes gather for half a second, then go together; the reply is the job, priced.
    const send = useCallback(() => {
        const body = pending.current;
        pending.current = {};
        if (Object.keys(body).length === 0) return inflight.current;
        setState('saving');
        inflight.current = axios.post(base, body, { headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } })
            .then((res) => {
                // A free-form design keeps the drawing on screen (the customer may be mid-drag); the rest is the server's.
                setJob((prev) => (prev.source === 'freeform' ? { ...res.data.job, design: prev.design } : res.data.job));
                setProblem(null);
                setState('saved');
            })
            .catch((err) => {
                const errs = err?.response?.data?.errors;
                setProblem(errs ? Object.values(errs).flat().join(' ') : (err?.response?.data?.message || String(err)));
                setState('error');
            });
        return inflight.current;
    }, [base]);
    const change = (patch, local) => {
        if (!open) return;
        Object.assign(pending.current, patch);
        if (local) setJob((j) => ({ ...j, ...local }));
        clearTimeout(timer.current);
        timer.current = setTimeout(send, 500);
    };
    const flush = () => { clearTimeout(timer.current); return send().then(() => inflight.current); };
    useEffect(() => () => clearTimeout(timer.current), []);

    const setEntry = (next) => change({ entry: next }, { entry: next });
    const setRow = (key, value) => setEntry({ ...entry, row: { ...entry.row, [key]: value } });
    const setMode = (mode) => setEntry({ ...entry, mode, list: mode === 'list' ? parseList(listDraft, keys) : entry.list });
    const setList = (text) => { setListDraft(text); setEntry({ ...entry, list: parseList(text, keys) }); };
    const readCsv = (file) => { if (!file) return; const r = new FileReader(); r.onload = () => setList(String(r.result || '')); r.readAsText(file); };
    const subjects = entry.subjects || { set_id: null, items: [], language: 'en' };
    const chooseSet = (id) => {
        const set = subjectSets.find((s) => String(s.id) === String(id));
        setEntry({ ...entry, subjects: { ...subjects, set_id: set ? set.id : null, items: set ? set.subjects : [] } });
    };
    const currentSet = subjectSets.find((s) => s.id === subjects.set_id);
    const toggleSubject = (subject) => {
        const on = subjects.items.some((s) => s.en === subject.en && s.dv === subject.dv);
        setEntry({ ...entry, subjects: { ...subjects, items: on ? subjects.items.filter((s) => !(s.en === subject.en && s.dv === subject.dv)) : [...subjects.items, subject] } });
    };
    const [extra, setExtra] = useState('');
    const addSubject = () => {
        const v = extra.trim();
        if (!v) return;
        setEntry({ ...entry, subjects: { ...subjects, items: [...subjects.items, { en: v, dv: v, ar: v }] } });
        setExtra('');
    };

    // The rows the tags will carry, for the preview (the server expands the same way for the print).
    const rows = useMemo(() => {
        const baseRows = entry.mode === 'list' ? (entry.list || []).filter((r) => Object.values(r).join('') !== '') : [entry.row || {}];
        if ((subjects.items || []).length > 0) {
            return baseRows.flatMap((r) => subjects.items.map((s) => ({ ...r, subject: s[subjects.language] || s.en })));
        }
        return baseRows;
    }, [entry, subjects]);
    const shown = rows[Math.min(preview, Math.max(0, rows.length - 1))] || {};
    const layout = layouts.find((l) => l.id === job.layout_id);
    const sheetSize = layout?.sheet_size ?? 'a4';
    // PP1: a paper is stock of one sheet size; only the chosen layout's size is offered.
    const sheetPapers = papers.filter((p) => (p.sheet_size ?? 'a4') === sheetSize);
    const paper = papers.find((p) => p.id === job.paper_id);
    const price = job.price;

    const proof = async () => { setBusy(true); await flush(); router.post(`${base}/proof`, {}, { preserveScroll: true, onFinish: () => setBusy(false) }); };
    const approve = () => { setBusy(true); router.post(`${base}/approve`, {}, { preserveScroll: true, onFinish: () => setBusy(false) }); };
    const copy = () => router.post(`${base}/copy`);
    const addToCart = () => { setBusy(true); router.post(`${base}/cart`, {}, { onFinish: () => setBusy(false) }); };
    const openDesigner = () => { setBusy(true); flush().then(() => router.post(`${base}/designer`, {}, { onFinish: () => setBusy(false) })); };
    // NT6: every change in the designer is the design, saved whole; the server validates it.
    const designChanged = (next) => change({ design: withFields(next) }, { design: next });

    return (
        <AppShell title={job.name || t.job_title}>
            <style>{fontCss}</style>
            <FormErrors errors={errors} className="mb-4" />
            <div className="mb-3 flex flex-wrap items-center gap-2 text-sm">
                <Link href={`/${locale}/shop/print/name-tags`} className="underline">← {t.door_title}</Link>
                {signedIn && <Link href={`/${locale}/my-designs`} className="underline">{t.my_designs_title}</Link>}
                <span className="ms-auto rounded bg-gray-100 px-2 py-0.5" data-testid="print-job-status">{t[`job_status_${job.status}`]}</span>
                <span className="text-xs text-gray-500" data-testid="print-job-saved" aria-live="polite">{state === 'saving' ? t.saving : state === 'saved' ? t.saved : ''}</span>
            </div>
            {problem && <p className="mb-3 rounded bg-red-50 p-2 text-sm text-red-800" data-testid="print-job-problem" role="alert">{problem}</p>}
            {!open && (
                <div className="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900" data-testid="print-job-locked">
                    <p className="font-medium">{t.locked_heading}</p>
                    <p>{fill(t.locked_body, { date: job.held?.held_until ? new Date(job.held.held_until).toLocaleDateString(locale) : '' })}</p>
                    {job.order && <p className="mt-1" data-testid="print-job-order">{fill(t.job_in_order, { number: job.order.number })} <a href={`/${locale}/my-orders/${job.order.number}`} className="underline">{t.job_see_order}</a></p>}
                    {job.lapsed && <p className="mt-1 text-amber-900" data-testid="print-job-lapsed">{t.job_price_lapsed}</p>}
                    <div className="mt-2 flex flex-wrap gap-2">
                        {job.buyable && signedIn && <button type="button" className="btn-primary text-sm" onClick={addToCart} disabled={busy} data-testid="print-add-to-cart">{t.add_to_cart}</button>}
                        <button type="button" className="btn-secondary text-sm" onClick={copy} data-testid="print-job-copy">{t.change_copy}</button>
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="space-y-4">
                    {freeform && open && designer ? (
                        <section className="rounded-lg border bg-white p-4" data-testid="print-job-designer">
                            <h2 className="mb-2 font-semibold">{t.freeform_heading}</h2>
                            <Suspense fallback={<p className="text-sm text-gray-600">{t.saving}</p>}>
                                <Designer design={design} onChange={designChanged} fonts={fonts} images={designer.images} fieldKeys={designer.field_keys} maxElements={designer.max_elements} sample={{ ...designer.sample, ...shown }} t={t} />
                            </Suspense>
                        </section>
                    ) : null}
                    <section className="rounded-lg border bg-white p-4" data-testid="print-job-preview">
                        <h2 className="mb-2 font-semibold">{t.preview_heading}</h2>
                        {open && !freeform && <button type="button" className="mb-2 text-sm text-blue-700 underline" onClick={openDesigner} disabled={busy} data-testid="print-open-designer">{t.open_in_designer}</button>}
                        <div className="flex justify-center overflow-hidden rounded bg-gray-100 p-3" dir="ltr" style={job.colour ? undefined : { filter: 'grayscale(100%)' }}>
                            <TagCanvas design={design} fonts={fonts} images={images} row={shown} scale={Math.min(5, 340 / design.tag.w)} testId="print-job-tag" />
                        </div>
                        <div className="mt-2 flex items-center justify-between text-sm text-gray-600">
                            <button type="button" className="px-2 underline disabled:opacity-30" disabled={preview <= 0} onClick={() => setPreview((p) => p - 1)} aria-label={t.previous_tag}>‹</button>
                            <span data-testid="print-job-count">{fill(t.tags_on_sheets, { tags: job.tags, sheets: job.sheets, per: job.per_sheet_tags })}</span>
                            <button type="button" className="px-2 underline disabled:opacity-30" disabled={preview >= rows.length - 1} onClick={() => setPreview((p) => p + 1)} aria-label={t.next_tag}>›</button>
                        </div>
                    </section>

                    <section className="rounded-lg border bg-white p-4" data-testid="print-job-details">
                        <h2 className="mb-2 font-semibold">{t.step_details}</h2>
                        <fieldset disabled={!open}>
                            <div className="mb-3 flex flex-wrap gap-3 text-sm">
                                <label className="flex items-center gap-2"><input type="radio" name="mode" checked={entry.mode !== 'list'} onChange={() => setMode('same')} data-testid="print-mode-same" /> {t.mode_same}</label>
                                <label className="flex items-center gap-2"><input type="radio" name="mode" checked={entry.mode === 'list'} onChange={() => setMode('list')} data-testid="print-mode-list" /> {t.mode_list}</label>
                            </div>
                            {entry.mode !== 'list' ? (
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {job.fields.filter((f) => f.key !== 'subject').map((f) => (
                                        <label key={f.key} className="text-sm">{f.label}{f.required ? ' *' : ''}
                                            <input className="form-input w-full" dir="auto" maxLength={80} value={entry.row?.[f.key] ?? ''} onChange={(e) => setRow(f.key, e.target.value)} data-testid={`print-field-${f.key}`} />
                                        </label>
                                    ))}
                                </div>
                            ) : (
                                <div>
                                    <p className="mb-1 text-xs text-gray-600">{fill(t.list_hint, { columns: job.fields.filter((f) => f.key !== 'subject').map((f) => f.label).join(' · '), max: settings.max_rows })}</p>
                                    <textarea rows={8} className="form-input w-full font-mono text-sm" dir="auto" value={listDraft} onChange={(e) => setList(e.target.value)} data-testid="print-list" />
                                    <label className="mt-1 block text-xs">{t.list_csv}<input type="file" accept=".csv,.txt,text/csv,text/plain" className="block text-xs" onChange={(e) => readCsv(e.target.files?.[0])} data-testid="print-list-file" /></label>
                                </div>
                            )}
                            {hasSubject && (
                                <div className="mt-4 border-t pt-3" data-testid="print-subjects">
                                    <h3 className="mb-1 text-sm font-semibold">{t.subjects_heading}</h3>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <label className="text-sm">{t.subject_set}<select className="form-input w-full" value={subjects.set_id ?? ''} onChange={(e) => chooseSet(e.target.value)} data-testid="print-subject-set">
                                            <option value="">{t.subject_set_none}</option>
                                            {subjectSets.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}
                                        </select></label>
                                        <label className="text-sm">{t.subjects_language}<select className="form-input w-full" value={subjects.language} onChange={(e) => setEntry({ ...entry, subjects: { ...subjects, language: e.target.value } })} data-testid="print-subject-language">
                                            {['en', 'dv', 'ar'].map((l) => <option key={l} value={l}>{t[`lang_${l}`]}</option>)}
                                        </select></label>
                                    </div>
                                    {subjects.set_id && (
                                        <>
                                            <ul className="mt-2 flex flex-wrap gap-2 text-sm">
                                                {[...(currentSet?.subjects || []), ...subjects.items.filter((s) => !(currentSet?.subjects || []).some((c) => c.en === s.en && c.dv === s.dv))].map((s, i) => (
                                                    <li key={`${s.en}-${i}`}><label className="flex items-center gap-1 rounded border px-2 py-1">
                                                        <input type="checkbox" checked={subjects.items.some((x) => x.en === s.en && x.dv === s.dv)} onChange={() => toggleSubject(s)} />
                                                        <span dir="auto">{s[subjects.language] || s.en}</span>
                                                    </label></li>
                                                ))}
                                            </ul>
                                            <div className="mt-2 flex gap-2 text-sm">
                                                <input className="form-input flex-1" dir="auto" maxLength={80} value={extra} onChange={(e) => setExtra(e.target.value)} aria-label={t.add_subject} placeholder={t.add_subject} />
                                                <button type="button" className="btn-secondary text-sm" onClick={addSubject}>{t.add}</button>
                                            </div>
                                        </>
                                    )}
                                </div>
                            )}
                        </fieldset>
                    </section>
                </div>

                <div className="space-y-4">
                    <section className="rounded-lg border bg-white p-4" data-testid="print-job-look">
                        <h2 className="mb-2 font-semibold">{t.step_look}</h2>
                        <fieldset disabled={!open} className="space-y-2 text-sm">
                            {Object.keys(job.schemes).length > 1 && (
                                <div className="flex flex-wrap gap-2" role="radiogroup" aria-label={t.scheme}>
                                    {Object.entries(job.schemes).map(([key, c]) => (
                                        <button key={key} type="button" role="radio" aria-checked={job.scheme === key} title={key} onClick={() => change({ scheme: key }, { scheme: key, design: { ...design, palette: { ...design.palette, scheme: key } } })} className={`flex h-10 items-center gap-1 rounded border px-2 ${job.scheme === key ? 'ring-2 ring-[#7C2D37]' : ''}`} data-testid={`print-scheme-${key}`}>
                                            <span className="h-5 w-5 rounded" style={{ background: c.primary }} /><span className="h-5 w-5 rounded" style={{ background: c.secondary }} />
                                        </button>
                                    ))}
                                </div>
                            )}
                            {job.font && (
                                <label className="block">{t.font}<select className="form-input w-full" value={job.font} onChange={(e) => change({ font: e.target.value }, { font: e.target.value, design: { ...design, elements: design.elements.map((el) => (el.type === 'text' && el.field ? { ...el, font: e.target.value } : el)) } })} data-testid="print-font">
                                    {Object.entries(fonts).map(([id, f]) => <option key={id} value={id}>{f.label}</option>)}
                                </select></label>
                            )}
                        </fieldset>
                    </section>

                    <section className="rounded-lg border bg-white p-4" data-testid="print-job-options">
                        <h2 className="mb-2 font-semibold">{t.step_options}</h2>
                        <fieldset disabled={!open} className="space-y-3 text-sm">
                            <label className="block">{t.layout}<select className="form-input w-full" value={job.layout_id ?? ''} onChange={(e) => change({ layout_id: Number(e.target.value) })} data-testid="print-layout">
                                {layouts.map((l) => <option key={l.id} value={l.id}>{l.name} ({l.sheet_label}) — {fill(t.tags_each, { count: l.tags })}</option>)}
                            </select></label>
                            <div>
                                <span className="block">{t.paper_name}</span>
                                {sheetPapers.length === 0 ? <p className="text-amber-800">{t.no_papers}</p> : sheetPapers.map((p) => (
                                    <label key={p.id} className="mt-1 flex items-center gap-2 rounded border px-2 py-1.5">
                                        <input type="radio" name="paper" checked={job.paper_id === p.id} onChange={() => change({ paper_id: p.id, colour: p.price_colour > 0 ? job.colour : false }, { paper_id: p.id })} data-testid={`print-paper-${p.id}`} />
                                        <span className="flex-1" dir="auto">{p.name}{p.gsm ? ` · ${p.gsm} gsm` : ''}</span>
                                        <span className="text-xs text-gray-600" dir="ltr">{p.price_colour > 0 ? `${money(p.price_colour)}` : '—'} / {p.price_bw > 0 ? money(p.price_bw) : '—'}</span>
                                    </label>
                                ))}
                            </div>
                            <div className="flex flex-wrap gap-3" role="radiogroup" aria-label={t.colour}>
                                <label className="flex items-center gap-2"><input type="radio" name="colour" checked={job.colour} disabled={paper && paper.price_colour <= 0} onChange={() => change({ colour: true }, { colour: true })} data-testid="print-colour" /> {t.colour_print}</label>
                                <label className="flex items-center gap-2"><input type="radio" name="colour" checked={!job.colour} disabled={paper && paper.price_bw <= 0} onChange={() => change({ colour: false }, { colour: false })} data-testid="print-bw" /> {t.bw_print}</label>
                            </div>
                            {finishingOptions.length > 0 && (
                                <div>
                                    <span className="block">{t.finishing_heading}</span>
                                    {finishingOptions.map((f) => {
                                        const on = job.finishing.includes(f.id);
                                        const next = on ? job.finishing.filter((x) => x !== f.id) : [...job.finishing, f.id];
                                        return <label key={f.id} className="mt-1 flex items-center gap-2"><input type="checkbox" checked={on} onChange={() => change({ finishing: next }, { finishing: next })} data-testid={`print-finishing-${f.id}`} /><span className="flex-1" dir="auto">{f.name}</span><span className="text-xs text-gray-600" dir="ltr">+{money(f.price)}</span></label>;
                                    })}
                                </div>
                            )}
                            {entry.mode !== 'list' && (subjects.items || []).length === 0 ? (
                                <label className="block">{t.sheets_label}<input type="number" min={settings.min_sheets || 1} max={500} className="form-input w-28" value={job.sheets} onChange={(e) => change({ sheets: Number(e.target.value) || 1 }, { sheets: Number(e.target.value) || 1 })} data-testid="print-sheets" /></label>
                            ) : <p className="text-xs text-gray-600">{t.sheets_auto}</p>}
                        </fieldset>
                    </section>

                    <section className="rounded-lg border bg-white p-4" data-testid="print-job-price" aria-live="polite">
                        <h2 className="mb-2 font-semibold">{t.step_price}</h2>
                        {!price ? <p className="text-sm text-gray-600">{t.price_none}</p> : (
                            <dl className="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1 text-sm">
                                <dt>{t.price_paper} <span dir="auto" className="text-gray-500">{price.paper.name}, {price.colour ? t.colour_print : t.bw_print}</span></dt><dd dir="ltr">{money(price.paper.price)}</dd>
                                {price.finishing.map((f) => <Fragment key={f.id}><dt dir="auto">{f.name}</dt><dd dir="ltr">{money(f.price)}</dd></Fragment>)}
                                <dt>{t.price_per_sheet}</dt><dd dir="ltr">{money(price.per_sheet)}</dd>
                                <dt>{fill(t.price_sheets, { count: price.sheets })}</dt><dd dir="ltr">{money(price.subtotal)}</dd>
                                {price.discount > 0 && <><dt>{fill(t.price_discount, { percent: price.tier_percent })}</dt><dd dir="ltr">−{money(price.discount)}</dd></>}
                                <dt className="font-semibold">{t.price_total}</dt><dd className="font-semibold" dir="ltr" data-testid="print-price-total">{price.currency} {money(price.total)}</dd>
                                <dt className="text-xs text-gray-500">{t.price_gst}</dt><dd className="text-xs text-gray-500" dir="ltr">{money(price.gst)}</dd>
                            </dl>
                        )}
                        {settings.lead_days > 0 && <p className="mt-2 text-xs text-gray-600">{fill(t.lead_time, { days: settings.lead_days })}</p>}
                        {job.warnings.length > 0 && (
                            <ul className="mt-3 list-disc space-y-1 ps-5 text-sm text-amber-800" data-testid="print-job-warnings">{job.warnings.map((w, i) => <li key={i} dir="auto">{w}</li>)}</ul>
                        )}
                    </section>

                    <section className="rounded-lg border bg-white p-4" data-testid="print-job-proof">
                        <h2 className="mb-2 font-semibold">{t.proof_heading}</h2>
                        {open && !signedIn && <a href={`/${locale}/login`} className="btn-primary inline-block" data-testid="print-sign-in">{t.sign_in_to_proof}</a>}
                        {open && signedIn && job.status === 'draft' && <button type="button" className="btn-primary" onClick={proof} disabled={busy || !price} data-testid="print-make-proof">{busy ? t.proofing : t.make_proof}</button>}
                        {job.status === 'proofed' && (
                            <div className="flex flex-wrap gap-2">
                                <button type="button" className="btn-primary" onClick={approve} disabled={busy} data-testid="print-approve">{t.approve}</button>
                                <span className="text-xs text-gray-600">{t.approve_note}</span>
                            </div>
                        )}
                    </section>
                </div>
            </div>

            {job.proof_images.length > 0 && (
                <section className="mt-6 rounded-lg border bg-white p-4" data-testid="print-proof-sheets">
                    <h2 className="mb-1 font-semibold">{t.proof_sheets_heading}</h2>
                    <p className="mb-3 text-sm text-gray-600">{t.proof_intro}</p>
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {job.proof_images.map((src, i) => (
                            <li key={src} className="rounded border bg-gray-50 p-2" onContextMenu={(e) => e.preventDefault()}>
                                <img src={src} alt={fill(t.proof_sheet_alt, { n: i + 1 })} className="w-full select-none" draggable={false} data-testid={`print-proof-${i + 1}`} />
                                <p className="mt-1 text-center text-xs text-gray-600">{fill(t.proof_sheet_alt, { n: i + 1 })}</p>
                            </li>
                        ))}
                    </ul>
                    {job.more_sheets > 0 && <p className="mt-2 text-sm text-gray-600">{fill(t.proof_more, { count: job.more_sheets })}</p>}
                </section>
            )}
        </AppShell>
    );
}
