import { Link, router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { Fragment, useCallback, useEffect, useRef, useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * NAME_TAGS_PLAN §3.4, §5.5, §5.6 (NT5): a job from the customer's own
 * artwork. One tag's artwork repeated on the layout, or a whole A4 sheet
 * already laid out; a PNG or JPEG (a PDF where the host can proof one).
 * The file is checked at once — resolution at the printed size, A4's shape
 * for a sheet, colour on black and white, transparency — and what stops it
 * is said; warnings are read and accepted, and *I have the right to print
 * this* given, before the proof. Options and price as for any job; every
 * change saved as it is made.
 */
const fill = (s, vars) => Object.entries(vars).reduce((out, [k, v]) => out.split(`:${k}`).join(String(v ?? '')), s || '');
const money = (n) => (Number(n) || 0).toFixed(2);
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

export default function PrintUploadJob(props) {
    const { t = {}, job: initial, layouts = [], papers = [], finishing_options: finishingOptions = [], settings = {}, door = 'name-tags', product_title: productTitle = '' } = props;
    const { errors = {}, locale = 'en' } = usePage().props;
    const [job, setJob] = useState(initial);
    useEffect(() => setJob(initial), [initial]);
    const upload = job.upload || {};
    const [state, setState] = useState('idle');
    const [problem, setProblem] = useState(null);
    const [busy, setBusy] = useState(false);
    const [declared, setDeclared] = useState(false);
    const [accepted, setAccepted] = useState(false);
    const pending = useRef({});
    const timer = useRef(null);
    const inflight = useRef(Promise.resolve());
    const base = `/${locale}/shop/print/jobs/${job.id}`;
    const open = job.open;
    const file = useForm({ file: null, upload_kind: upload.kind || 'tag' });

    const send = useCallback(() => {
        const body = pending.current;
        pending.current = {};
        if (Object.keys(body).length === 0) return inflight.current;
        setState('saving');
        inflight.current = axios.post(base, body, { headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } })
            .then((res) => { setJob(res.data.job); setProblem(null); setState('saved'); })
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

    const layout = layouts.find((l) => l.id === job.layout_id);
    const sheetSize = layout?.sheet_size ?? 'a4';
    // PP1: a paper is stock of one sheet size; only the chosen layout's size is offered.
    const sheetPapers = papers.filter((p) => (p.sheet_size ?? 'a4') === sheetSize);
    const paper = papers.find((p) => p.id === job.paper_id);
    const price = job.price;
    const refused = (upload.refusals || []).length > 0;
    const warned = job.warnings.length > 0;
    const accept = upload.pdf ? 'image/png,image/jpeg,application/pdf,.pdf' : 'image/png,image/jpeg';
    const sendFile = (e) => {
        e.preventDefault();
        file.post(`${base}/upload`, { forceFormData: true, preserveScroll: true, onSuccess: () => { file.reset('file'); setAccepted(false); } });
    };
    const proof = async () => { setBusy(true); await flush(); router.post(`${base}/proof`, { declared, accept_warnings: accepted }, { preserveScroll: true, onFinish: () => setBusy(false) }); };
    const approve = () => { setBusy(true); router.post(`${base}/approve`, {}, { preserveScroll: true, onFinish: () => setBusy(false) }); };
    const copy = () => router.post(`${base}/copy`);
    const addToCart = () => { setBusy(true); router.post(`${base}/cart`, {}, { onFinish: () => setBusy(false) }); };

    return (
        <AppShell title={job.name || t.upload_title}>
            <FormErrors errors={errors} className="mb-4" />
            <div className="mb-3 flex flex-wrap items-center gap-2 text-sm">
                <Link href={`/${locale}/shop/print/${door}`} className="underline">← {door === 'name-tags' ? t.door_title : productTitle}</Link>
                <Link href={`/${locale}/my-designs`} className="underline">{t.my_designs_title}</Link>
                <span className="ms-auto rounded bg-gray-100 px-2 py-0.5" data-testid="print-job-status">{t[`job_status_${job.status}`]}</span>
                <span className="text-xs text-gray-500" data-testid="print-job-saved" aria-live="polite">{state === 'saving' ? t.saving : state === 'saved' ? t.saved : ''}</span>
            </div>
            {problem && <p className="mb-3 rounded bg-red-50 p-2 text-sm text-red-800" data-testid="print-job-problem" role="alert">{problem}</p>}
            {!open && (
                <div className="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900" data-testid="print-job-locked">
                    <p className="font-medium">{t.locked_heading}</p>
                    <p>{fill(t.locked_body, { date: job.held?.held_until ? new Date(job.held.held_until).toLocaleDateString(locale) : '' })}</p>
                    {job.order && <p className="mt-1" data-testid="print-job-order">{fill(t.job_in_order, { number: job.order.number })} <a href={`/${locale}/my-orders/${job.order.number}`} className="underline">{t.job_see_order}</a></p>}
                    {job.lapsed && <p className="mt-1 text-amber-900">{t.job_price_lapsed}</p>}
                    <div className="mt-2 flex flex-wrap gap-2">
                        {job.buyable && <button type="button" className="btn-primary text-sm" onClick={addToCart} disabled={busy} data-testid="print-add-to-cart">{t.add_to_cart}</button>}
                        <button type="button" className="btn-secondary text-sm" onClick={copy} data-testid="print-job-copy">{t.change_copy}</button>
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="space-y-4">
                    <section className="rounded-lg border bg-white p-4" data-testid="print-upload-file">
                        <h2 className="mb-1 font-semibold">{t.upload_heading}</h2>
                        <p className="mb-3 text-sm text-gray-600">{door === 'photos' ? t.photo_door_body : t.upload_intro}</p>
                        <form onSubmit={sendFile} className="space-y-3 text-sm">
                            <fieldset disabled={!open} className="space-y-3">
                                {door !== 'photos' && <div className="flex flex-wrap gap-3" role="radiogroup" aria-label={t.upload_kind}>
                                    <label className="flex items-center gap-2"><input type="radio" name="upload_kind" checked={file.data.upload_kind === 'tag'} onChange={() => { file.setData('upload_kind', 'tag'); if (upload.has_file) change({ upload_kind: 'tag' }); }} data-testid="print-upload-kind-tag" /> {t.upload_kind_tag}</label>
                                    <label className="flex items-center gap-2"><input type="radio" name="upload_kind" checked={file.data.upload_kind === 'sheet'} onChange={() => { file.setData('upload_kind', 'sheet'); if (upload.has_file) change({ upload_kind: 'sheet' }); }} data-testid="print-upload-kind-sheet" /> {t.upload_kind_sheet}</label>
                                </div>}
                                <label className="block">{upload.pdf ? t.upload_file_pdf : t.upload_file}
                                    <input type="file" accept={accept} className="mt-1 block w-full text-sm" onChange={(e) => file.setData('file', e.target.files?.[0] || null)} data-testid="print-upload-input" />
                                </label>
                                <p className="text-xs text-gray-600">{fill(upload.pdf ? t.upload_types_pdf : t.upload_types, { mb: upload.max_mb })}</p>
                                {file.errors.file && <p className="text-sm text-red-700" role="alert" data-testid="print-upload-error">{file.errors.file}</p>}
                                <button type="submit" className="btn-primary" disabled={!file.data.file || file.processing} data-testid="print-upload-send">{file.processing ? t.uploading : t.upload_send}</button>
                            </fieldset>
                        </form>
                        {upload.has_file && (
                            <dl className="mt-4 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 border-t pt-3 text-sm" data-testid="print-upload-facts">
                                <dt className="text-gray-600">{t.upload_fact_type}</dt><dd className="uppercase">{upload.type}</dd>
                                {upload.w && <><dt className="text-gray-600">{t.upload_fact_pixels}</dt><dd dir="ltr">{upload.w} × {upload.h}</dd></>}
                                {upload.dpi !== null && upload.dpi !== undefined && <><dt className="text-gray-600">{t.upload_fact_dpi}</dt><dd data-testid="print-upload-dpi">{upload.dpi}</dd></>}
                                {upload.type === 'pdf' && <><dt className="text-gray-600">{t.upload_fact_pages}</dt><dd>{upload.pages}</dd></>}
                            </dl>
                        )}
                        {refused && <ul className="mt-3 list-disc space-y-1 ps-5 text-sm text-red-800" data-testid="print-upload-refusals" role="alert">{upload.refusals.map((r, i) => <li key={i} dir="auto">{r}</li>)}</ul>}
                        {warned && <ul className="mt-3 list-disc space-y-1 ps-5 text-sm text-amber-800" data-testid="print-job-warnings">{job.warnings.map((w, i) => <li key={i} dir="auto">{w}</li>)}</ul>}
                    </section>
                </div>

                <div className="space-y-4">
                    <section className="rounded-lg border bg-white p-4" data-testid="print-job-options">
                        <h2 className="mb-2 font-semibold">{t.step_options}</h2>
                        <fieldset disabled={!open} className="space-y-3 text-sm">
                            {upload.kind !== 'sheet' && (
                                <label className="block">{t.layout}<select className="form-input w-full" value={job.layout_id ?? ''} onChange={(e) => change({ layout_id: Number(e.target.value) })} data-testid="print-layout">
                                    {layouts.map((l) => <option key={l.id} value={l.id}>{l.name} ({l.sheet_label}) — {fill(t.tags_each, { count: l.tags })} · {l.tag_w_mm} × {l.tag_h_mm} mm</option>)}
                                </select></label>
                            )}
                            {door === 'photos' && (
                                <div role="radiogroup" aria-label={t.upload_fit} data-testid="print-photo-fit">
                                    <span className="block">{t.upload_fit}</span>
                                    {['cover', 'contain'].map((f) => (
                                        <label key={f} className="mt-1 flex items-center gap-2"><input type="radio" name="fit" checked={(job.fit || 'cover') === f} onChange={() => change({ fit: f }, { fit: f })} data-testid={`print-photo-fit-${f}`} /> {t[`upload_fit_${f}`]}</label>
                                    ))}
                                    {layout && <p className="mt-1 text-xs text-gray-600" data-testid="print-photo-count">{fill(t.photo_prints_count, { count: (job.sheets || 1) * (layout.tags || 1) })}</p>}
                                </div>
                            )}
                            <div>
                                <span className="block">{t.paper_name}</span>
                                {sheetPapers.length === 0 ? <p className="text-amber-800">{t.no_papers}</p> : sheetPapers.map((p) => (
                                    <label key={p.id} className="mt-1 flex items-center gap-2 rounded border px-2 py-1.5">
                                        <input type="radio" name="paper" checked={job.paper_id === p.id} onChange={() => change({ paper_id: p.id, colour: p.price_colour > 0 ? job.colour : false }, { paper_id: p.id })} data-testid={`print-paper-${p.id}`} />
                                        <span className="flex-1" dir="auto">{p.name}{p.gsm ? ` · ${p.gsm} gsm` : ''}</span>
                                        <span className="text-xs text-gray-600" dir="ltr">{p.price_colour > 0 ? money(p.price_colour) : '—'} / {p.price_bw > 0 ? money(p.price_bw) : '—'}</span>
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
                                        return <label key={f.id} className="mt-1 flex items-center gap-2"><input type="checkbox" checked={on} onChange={() => change({ finishing: next }, { finishing: next })} /><span className="flex-1" dir="auto">{f.name}</span><span className="text-xs text-gray-600" dir="ltr">+{money(f.price)}</span></label>;
                                    })}
                                </div>
                            )}
                            {upload.type === 'pdf'
                                ? <p className="text-xs text-gray-600">{fill(t.upload_pdf_sheets, { count: job.sheets })}</p>
                                : <label className="block">{t.sheets_label}<input type="number" min={settings.min_sheets || 1} max={500} className="form-input w-28" value={job.sheets} onChange={(e) => change({ sheets: Number(e.target.value) || 1 }, { sheets: Number(e.target.value) || 1 })} data-testid="print-sheets" /></label>}
                        </fieldset>
                    </section>

                    <section className="rounded-lg border bg-white p-4" data-testid="print-job-price" aria-live="polite">
                        <h2 className="mb-2 font-semibold">{t.step_price}</h2>
                        {!price ? <p className="text-sm text-gray-600">{t.price_none}</p> : (
                            <dl className="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1 text-sm">
                                <dt>{t.price_paper} <span dir="auto" className="text-gray-500">{price.paper.name}, {price.colour ? t.colour_print : t.bw_print}</span></dt><dd dir="ltr">{money(price.paper.price)}</dd>
                                {price.finishing.map((f) => <Fragment key={f.id}><dt dir="auto">{f.name}</dt><dd dir="ltr">{money(f.price)}</dd></Fragment>)}
                                <dt>{fill(t.price_sheets, { count: price.sheets })}</dt><dd dir="ltr">{money(price.subtotal)}</dd>
                                {price.discount > 0 && <><dt>{fill(t.price_discount, { percent: price.tier_percent })}</dt><dd dir="ltr">−{money(price.discount)}</dd></>}
                                <dt className="font-semibold">{t.price_total}</dt><dd className="font-semibold" dir="ltr" data-testid="print-price-total">{price.currency} {money(price.total)}</dd>
                            </dl>
                        )}
                    </section>

                    <section className="rounded-lg border bg-white p-4" data-testid="print-job-proof">
                        <h2 className="mb-2 font-semibold">{t.proof_heading}</h2>
                        {open && job.status === 'draft' && (
                            <div className="space-y-2 text-sm">
                                {warned && <label className="flex items-start gap-2"><input type="checkbox" className="mt-1" checked={accepted} onChange={(e) => setAccepted(e.target.checked)} data-testid="print-upload-accept" /> <span>{t.upload_accept_warnings}</span></label>}
                                <label className="flex items-start gap-2"><input type="checkbox" className="mt-1" checked={declared} onChange={(e) => setDeclared(e.target.checked)} data-testid="print-upload-declare" /> <span>{t.upload_declaration}</span></label>
                                <button type="button" className="btn-primary" onClick={proof} disabled={busy || !price || !upload.has_file || refused} data-testid="print-make-proof">{busy ? t.proofing : t.make_proof}</button>
                            </div>
                        )}
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
                            </li>
                        ))}
                    </ul>
                    {job.more_sheets > 0 && <p className="mt-2 text-sm text-gray-600">{fill(t.proof_more, { count: job.more_sheets })}</p>}
                </section>
            )}
        </AppShell>
    );
}
