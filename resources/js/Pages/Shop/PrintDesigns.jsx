import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';
import TagCanvas from '../../Components/Print/TagCanvas';
import { normaliseDesign } from '../../Components/Print/design';

/**
 * NAME_TAGS_PLAN §3.9 (NT3): *My designs* — every job the customer made,
 * newest first, drawn from its own design with its first name; open it,
 * rename it, copy it, or put it away while no order holds it.
 */
const money = (n) => (Number(n) || 0).toFixed(2);

function Row({ d, t, fonts, locale }) {
    const [name, setName] = useState(d.name || '');
    const [editing, setEditing] = useState(false);
    const design = normaliseDesign(d.design);
    const row = d.entry?.mode === 'list' ? (d.entry.list || []).find((r) => Object.values(r).join('') !== '') || {} : d.entry?.row || {};
    const base = `/${locale}/shop/print/jobs/${d.id}`;

    return (
        <li className="flex flex-col gap-3 rounded-lg border bg-white p-3 sm:flex-row sm:items-center" data-testid={`print-design-${d.id}`} data-status={d.status}>
            <div className="flex justify-center rounded bg-gray-50 p-2" dir="ltr">
                {d.source === 'upload'
                    ? <span className="flex h-24 w-32 items-center justify-center text-center text-xs text-gray-600" data-testid={`print-design-thumb-${d.id}`}>{t.upload_thumb}</span>
                    : <TagCanvas design={design} shape={d.shape} fonts={fonts} row={row} scale={Math.min(1.8, 150 / design.tag.w)} testId={`print-design-thumb-${d.id}`} />}
            </div>
            <div className="min-w-0 flex-1">
                {editing ? (
                    <form className="flex gap-2" onSubmit={(e) => { e.preventDefault(); router.post(`${base}/name`, { name }, { preserveScroll: true, onSuccess: () => setEditing(false) }); }}>
                        <input className="form-input flex-1" dir="auto" maxLength={120} value={name} onChange={(e) => setName(e.target.value)} aria-label={t.name_label} data-testid={`print-design-name-${d.id}`} />
                        <button type="submit" className="btn-primary text-sm">{t.save}</button>
                    </form>
                ) : <h2 className="font-semibold" dir="auto">{d.name}</h2>}
                <p className="text-sm text-gray-600">{t[`job_status_${d.status}`]}{d.template ? ` · ${d.template}` : ''} · {d.sheets} {t.sheets_short}{d.total > 0 ? ` · MVR ${money(d.total)}` : ''}</p>
                <div className="mt-2 flex flex-wrap gap-3 text-sm">
                    <Link href={base} className="text-blue-700 underline" data-testid={`print-design-open-${d.id}`}>{t.open}</Link>
                    {!editing && <button type="button" className="underline" onClick={() => setEditing(true)}>{t.rename}</button>}
                    {['approved', 'ordered', 'printing', 'finished'].includes(d.status)
                        // NT7: a copy at today's price, to proof and approve again.
                        ? <button type="button" className="font-medium text-blue-700 underline" onClick={() => router.post(`${base}/copy`, { again: 1 })} data-testid={`print-design-again-${d.id}`}>{t.order_again}</button>
                        : <button type="button" className="underline" onClick={() => router.post(`${base}/copy`)}>{t.make_copy}</button>}
                    {d.removable && <button type="button" className="text-red-700 underline" onClick={() => { if (window.confirm(t.remove_confirm)) router.delete(base, { preserveScroll: true }); }} data-testid={`print-design-remove-${d.id}`}>{t.remove_design}</button>}
                </div>
            </div>
        </li>
    );
}

export default function PrintDesigns({ t = {}, designs = [], fonts = {}, font_css: fontCss = '', enabled = true }) {
    const { errors = {}, locale = 'en' } = usePage().props;

    return (
        <AppShell title={t.my_designs_title}>
            <style>{fontCss}</style>
            <FormErrors errors={errors} className="mb-4" />
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-gray-600">{t.my_designs_intro}</p>
                {enabled && <Link href={`/${locale}/shop/print`} className="btn-primary text-sm" data-testid="print-designs-new">{t.start_new}</Link>}
            </div>
            {designs.length === 0 ? <p className="text-sm text-gray-600" data-testid="print-designs-none">{t.my_designs_none}</p> : (
                <ul className="space-y-3" data-testid="print-designs">{designs.map((d) => <Row key={d.id} d={d} t={t} fonts={fonts} locale={locale} />)}</ul>
            )}
        </AppShell>
    );
}
