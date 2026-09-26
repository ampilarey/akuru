import { useMemo, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * BOOKSHOP_PLAN slice B8 — the shop's stock page: what is low or sold out,
 * stock received or counted by hand, the product sheet in (checked and
 * previewed before anything is written) and out, and the stock log with
 * who did what. Every list has its CSV.
 */

const fill = (s, vars) => Object.entries(vars).reduce((out, [k, v]) => out.replaceAll(`:${k}`, v), s || '');

function LowStock({ rows, t }) {
    return (
        <section className="mb-8" data-testid="low-stock">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold">{t.low_stock_heading} <span className="text-sm font-normal text-gray-500">({rows.length})</span></h2>
                <a href="/vendor/stock/low/export" className="btn-secondary" data-testid="export-low-stock">{t.export_csv}</a>
            </div>
            {rows.length === 0 ? (
                <p className="rounded border bg-white p-3 text-sm text-gray-600" data-testid="no-low-stock">{t.no_low_stock}</p>
            ) : (
                <table className="w-full overflow-hidden rounded border bg-white text-sm">
                    <thead className="bg-gray-50 text-start"><tr><th className="p-2 text-start">{t.product}</th><th className="p-2 text-start">SKU</th><th className="p-2 text-end">{t.stock}</th><th className="p-2 text-end">{t.low_stock_at}</th><th className="p-2 text-start">{t.state}</th></tr></thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={`${r.product_id}-${r.variant_id || 0}`} className="border-t" data-testid={`low-${r.sku || r.slug}`} data-state={r.state}>
                                <td className="p-2" dir="auto">{r.title}{r.variant && <span className="text-gray-500"> · {r.variant}</span>}</td>
                                <td className="p-2">{r.sku || '—'}</td>
                                <td className="p-2 text-end font-semibold">{r.stock}</td>
                                <td className="p-2 text-end">{r.low_stock_at ?? '—'}</td>
                                <td className="p-2"><span className={`rounded px-1 text-xs ${r.state === 'sold_out' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'}`}>{t[`stock_state_${r.state}`] || r.state}</span></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </section>
    );
}

function Adjust({ products, t }) {
    const counted = products.filter((p) => p.track_stock);
    const form = useForm({ product_id: '', variant_id: '', mode: 'in', quantity: '', note: '' });
    const product = counted.find((p) => String(p.id) === String(form.data.product_id));
    const variant = product?.variants.find((v) => String(v.id) === String(form.data.variant_id));
    const current = variant ? variant.stock : product && product.variants.length === 0 ? product.stock : null;

    return (
        <section className="mb-8" data-testid="stock-adjust">
            <h2 className="mb-2 text-lg font-semibold">{t.adjust_heading}</h2>
            <form
                className="grid gap-3 rounded border bg-white p-3 md:grid-cols-6"
                onSubmit={(e) => { e.preventDefault(); form.post('/vendor/stock/adjust', { preserveScroll: true, onSuccess: () => form.reset('quantity', 'note') }); }}
            >
                <label className="text-sm md:col-span-2">{t.product}
                    <select className="form-input w-full" value={form.data.product_id} onChange={(e) => form.setData({ ...form.data, product_id: e.target.value, variant_id: '' })} required data-testid="adjust-product">
                        <option value="">—</option>
                        {counted.map((p) => <option key={p.id} value={p.id}>{p.title}{p.sku ? ` (${p.sku})` : ''}</option>)}
                    </select>
                </label>
                {product && product.variants.length > 0 && (
                    <label className="text-sm">{t.variant}
                        <select className="form-input w-full" value={form.data.variant_id} onChange={(e) => form.setData('variant_id', e.target.value)} required data-testid="adjust-variant">
                            <option value="">—</option>
                            {product.variants.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                        </select>
                    </label>
                )}
                <label className="text-sm">{t.adjust_mode}
                    <select className="form-input w-full" value={form.data.mode} onChange={(e) => form.setData('mode', e.target.value)} data-testid="adjust-mode">
                        {['in', 'count', 'adjustment'].map((m) => <option key={m} value={m}>{t[`adjust_mode_${m}`]}</option>)}
                    </select>
                </label>
                <label className="text-sm">{form.data.mode === 'count' ? t.counted_quantity : t.quantity}
                    <input className="form-input w-full" type="number" value={form.data.quantity} onChange={(e) => form.setData('quantity', e.target.value)} required data-testid="adjust-quantity" />
                </label>
                <label className="text-sm md:col-span-2">{t.note}
                    <input className="form-input w-full" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} maxLength={255} data-testid="adjust-note" />
                </label>
                <div className="flex items-end gap-3 md:col-span-6">
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="adjust-save">{t.save}</button>
                    {current !== null && <span className="text-sm text-gray-600" data-testid="adjust-current">{fill(t.stock_now, { stock: current })}</span>}
                    <span className="text-xs text-gray-500">{t.adjust_hint}</span>
                </div>
            </form>
        </section>
    );
}

function Import({ t, preview, expired, columns, limits }) {
    const form = useForm({ file: null });
    const [showAll, setShowAll] = useState(false);
    const rows = preview ? (showAll ? preview.rows : preview.rows.filter((r) => r.action !== 'unchanged' || r.errors.length > 0)) : [];

    return (
        <section className="mb-8" id="import" data-testid="import">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold">{t.import_heading}</h2>
                <span className="flex gap-2">
                    <a href="/vendor/products/export" className="btn-secondary" data-testid="export-sheet">{t.export_sheet}</a>
                    <a href="/vendor/stock/template" className="btn-secondary" data-testid="download-template">{t.download_template}</a>
                </span>
            </div>
            <p className="mb-2 text-sm text-gray-600">{fill(t.import_hint, { rows: limits.rows, kb: limits.kilobytes })}</p>
            <details className="mb-3 text-xs text-gray-500"><summary className="cursor-pointer">{t.import_columns}</summary><p className="mt-1 font-mono">{columns.join(', ')}</p><p className="mt-1">{t.import_rules}</p></details>
            {expired && <p className="mb-3 rounded bg-amber-50 p-2 text-sm text-amber-900" data-testid="import-expired">{t.import_expired}</p>}
            <form className="flex flex-wrap items-center gap-2 rounded border bg-white p-3" onSubmit={(e) => { e.preventDefault(); form.post('/vendor/stock/import', { forceFormData: true }); }}>
                <input type="file" accept=".csv,text/csv" onChange={(e) => form.setData('file', e.target.files?.[0] || null)} required data-testid="import-file" />
                <button type="submit" className="btn-primary" disabled={form.processing || !form.data.file} data-testid="import-check">{t.import_check}</button>
            </form>
            {preview && (
                <div className="mt-3 rounded border bg-white p-3" data-testid="import-preview">
                    <p className="text-sm font-semibold" data-testid="import-summary">
                        {fill(t.import_summary, { file: preview.file, total: preview.summary.total, create: preview.summary.create, update: preview.summary.update, variant: preview.summary.variant, unchanged: preview.summary.unchanged, errors: preview.summary.errors })}
                    </p>
                    {preview.ignored.length > 0 && <p className="text-xs text-amber-800">{fill(t.import_ignored, { columns: preview.ignored.join(', ') })}</p>}
                    <label className="mt-2 flex items-center gap-2 text-xs"><input type="checkbox" checked={showAll} onChange={(e) => setShowAll(e.target.checked)} /> {t.import_show_unchanged}</label>
                    <table className="mt-2 w-full text-sm">
                        <thead className="bg-gray-50"><tr><th className="p-1 text-start">{t.line}</th><th className="p-1 text-start">{t.import_action}</th><th className="p-1 text-start">SKU</th><th className="p-1 text-start">{t.product}</th><th className="p-1 text-start">{t.import_what}</th></tr></thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.line} className={`border-t ${r.errors.length ? 'bg-red-50' : ''}`} data-testid={`import-line-${r.line}`} data-action={r.errors.length ? 'error' : r.action}>
                                    <td className="p-1">{r.line}</td>
                                    <td className="p-1">{r.errors.length ? t.import_error : t[`import_action_${r.action}`]}</td>
                                    <td className="p-1">{r.sku || '—'}</td>
                                    <td className="p-1" dir="auto">{r.title}</td>
                                    <td className="p-1 text-xs">{r.errors.length ? <span className="text-red-800">{r.errors.join(' · ')}</span> : r.changes.join(', ')}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <div className="mt-3 flex flex-wrap items-center gap-3">
                        <button type="button" className="btn-primary" disabled={preview.summary.total - preview.summary.errors - preview.summary.unchanged === 0}
                            onClick={() => router.post(`/vendor/stock/import/${preview.token}`)} data-testid="import-apply">
                            {fill(t.import_apply, { count: preview.summary.create + preview.summary.update + preview.summary.variant })}
                        </button>
                        {preview.summary.errors > 0 && <span className="text-sm text-red-800">{fill(t.import_errors_skipped, { count: preview.summary.errors })}</span>}
                        <a href="/vendor/stock" className="text-sm text-gray-600 underline">{t.cancel}</a>
                    </div>
                </div>
            )}
        </section>
    );
}

function Movements({ movements, kinds, filters, t }) {
    const [f, setF] = useState({ q: filters.q || '', kind: filters.kind || '', from: filters.from || '', to: filters.to || '' });
    const query = useMemo(() => Object.fromEntries(Object.entries(f).filter(([, v]) => v)), [f]);
    const go = (page) => router.get('/vendor/stock', { ...query, page }, { preserveScroll: true, preserveState: true });

    return (
        <section data-testid="stock-log">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold">{t.stock_log_heading} <span className="text-sm font-normal text-gray-500">({movements.total})</span></h2>
                <a href={`/vendor/stock/movements/export?${new URLSearchParams(query).toString()}`} className="btn-secondary" data-testid="export-stock-log">{t.export_csv}</a>
            </div>
            <form className="mb-2 flex flex-wrap gap-2 text-sm" onSubmit={(e) => { e.preventDefault(); go(1); }}>
                <input className="form-input" placeholder={t.search_products} value={f.q} onChange={(e) => setF({ ...f, q: e.target.value })} data-testid="log-search" />
                <select className="form-input" value={f.kind} onChange={(e) => setF({ ...f, kind: e.target.value })} data-testid="log-kind">
                    <option value="">{t.all_kinds}</option>
                    {kinds.map((k) => <option key={k} value={k}>{t[`movement_${k}`] || k}</option>)}
                </select>
                <input className="form-input" type="date" value={f.from} onChange={(e) => setF({ ...f, from: e.target.value })} aria-label={t.from} />
                <input className="form-input" type="date" value={f.to} onChange={(e) => setF({ ...f, to: e.target.value })} aria-label={t.to} />
                <button type="submit" className="btn-secondary">{t.search}</button>
            </form>
            {movements.rows.length === 0 ? (
                <p className="rounded border bg-white p-3 text-sm text-gray-600">{t.no_movements}</p>
            ) : (
                <table className="w-full overflow-hidden rounded border bg-white text-sm">
                    <thead className="bg-gray-50"><tr><th className="p-2 text-start">{t.date}</th><th className="p-2 text-start">{t.movement_kind}</th><th className="p-2 text-start">{t.product}</th><th className="p-2 text-end">{t.quantity}</th><th className="p-2 text-end">{t.stock_after}</th><th className="p-2 text-start">{t.note}</th><th className="p-2 text-start">{t.by}</th></tr></thead>
                    <tbody>
                        {movements.rows.map((m) => (
                            <tr key={m.id} className="border-t" data-testid={`movement-${m.id}`} data-kind={m.kind} data-sku={m.sku || ''}>
                                <td className="p-2 whitespace-nowrap">{m.at}</td>
                                <td className="p-2">{t[`movement_${m.kind}`] || m.kind}</td>
                                <td className="p-2" dir="auto">{m.product}{m.variant && <span className="text-gray-500"> · {m.variant}</span>}{m.order && <span className="block text-xs text-gray-500">{m.order}</span>}</td>
                                <td className={`p-2 text-end font-semibold ${m.quantity < 0 ? 'text-red-700' : 'text-green-700'}`}>{m.quantity > 0 ? `+${m.quantity}` : m.quantity}</td>
                                <td className="p-2 text-end">{m.stock_after}</td>
                                <td className="p-2 text-xs" dir="auto">{m.note}</td>
                                <td className="p-2 text-xs">{m.by || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
            {movements.last_page > 1 && (
                <nav className="mt-2 flex items-center gap-2 text-sm" aria-label={t.pages}>
                    <button type="button" className="btn-secondary" disabled={movements.page <= 1} onClick={() => go(movements.page - 1)}>{t.previous}</button>
                    <span>{fill(t.page_of, { page: movements.page, pages: movements.last_page })}</span>
                    <button type="button" className="btn-secondary" disabled={movements.page >= movements.last_page} onClick={() => go(movements.page + 1)}>{t.next}</button>
                </nav>
            )}
        </section>
    );
}

export default function VendorStock({ t, vendor, low_stock, movements, kinds, filters, products, import: preview, import_expired, import_result: result, columns, limits }) {
    const { flash = {}, errors } = usePage().props;

    return (
        <AppShell title={t.stock_title}>
            <FormErrors errors={errors} className="mb-4" />
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="flash-success">{flash.success}</p>}
            {result && result.failed?.length > 0 && (
                <ul className="mb-4 rounded bg-red-50 p-3 text-sm text-red-800" data-testid="import-failed">
                    {result.failed.map((f) => <li key={f.line}>{fill(t.import_failed_line, { line: f.line, error: f.error })}</li>)}
                </ul>
            )}
            <header className="mb-6">
                <h1 className="text-2xl font-bold" data-testid="stock-heading">{t.stock_title} · {vendor.name}</h1>
                <p className="text-sm text-gray-600"><a href="/vendor" className="text-blue-700 underline">{t.portal_title}</a> · {t.stock_intro}</p>
            </header>
            <LowStock rows={low_stock} t={t} />
            <Adjust products={products} t={t} />
            <Import t={t} preview={preview} expired={import_expired} columns={columns} limits={limits} />
            <Movements movements={movements} kinds={kinds} filters={filters} t={t} />
        </AppShell>
    );
}
