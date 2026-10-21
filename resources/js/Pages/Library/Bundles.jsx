import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * LIBRARY_PLAN §19 (B4d, STATUS §5un): the office's bundles — several paid
 * works at one price below what they cost apart. The list comes first on a
 * phone; the form to make or change one follows. Each bundle's sales and
 * revenue, and a CSV.
 */
const fill = (s, vars) => Object.entries(vars).reduce((out, [k, v]) => out.split(`:${k}`).join(String(v ?? '')), s || '');
const blank = { title: '', title_dv: '', title_ar: '', description: '', price: '', item_ids: [], is_active: true, ends_at: '' };

function BundleForm({ t, items, limits, editing, onDone }) {
    const form = useForm(editing ? { ...blank, ...editing, ends_at: editing.ends_at ?? '', title_dv: editing.title_dv ?? '', title_ar: editing.title_ar ?? '', description: editing.description ?? '' } : blank);
    const [query, setQuery] = useState('');
    const chosen = new Set(form.data.item_ids);
    const total = items.filter((i) => chosen.has(i.id)).reduce((sum, i) => sum + Number(i.price || 0), 0);
    const toggle = (id) => form.setData('item_ids', chosen.has(id) ? form.data.item_ids.filter((x) => x !== id) : [...form.data.item_ids, id]);
    const shown = items.filter((i) => chosen.has(i.id) || i.label.toLowerCase().includes(query.trim().toLowerCase()));
    const submit = (e) => {
        e.preventDefault();
        form.post(editing ? `/admin/library/bundles/${editing.id}` : '/admin/library/bundles', { preserveScroll: true, onSuccess: () => { form.reset(); onDone(); } });
    };

    return (
        <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2" data-testid="bundle-form">
            <h2 className="text-lg font-semibold md:col-span-2">{editing ? t.library_bundles_edit : t.library_bundles_new}</h2>
            <label className="text-sm">{t.library_bundles_name}<input className="form-input w-full" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required maxLength={160} data-testid="bundle-title" /></label>
            <label className="text-sm">{t.library_bundles_price}<input className="form-input w-full" type="number" min="0.01" step="0.01" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} required data-testid="bundle-price" /></label>
            <label className="text-sm">{t.library_bundles_name_dv}<input className="form-input w-full" dir="rtl" value={form.data.title_dv} onChange={(e) => form.setData('title_dv', e.target.value)} maxLength={160} /></label>
            <label className="text-sm">{t.library_bundles_name_ar}<input className="form-input w-full" dir="rtl" value={form.data.title_ar} onChange={(e) => form.setData('title_ar', e.target.value)} maxLength={160} /></label>
            <label className="text-sm md:col-span-2">{t.library_bundles_description}<textarea className="form-input w-full" rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} maxLength={2000} /></label>
            <fieldset className="text-sm md:col-span-2" data-testid="bundle-works">
                <legend>{fill(t.library_bundles_works, limits)} · <span data-testid="bundle-list-total">{fill(t.library_bundles_list_total, { total: total.toFixed(2) })}</span></legend>
                <input className="form-input mt-1 w-full" type="search" value={query} onChange={(e) => setQuery(e.target.value)} aria-label={t.library_bundles_works} />
                <ul className="mt-2 max-h-64 overflow-y-auto rounded border">
                    {shown.map((i) => (
                        <li key={i.id} className="border-b last:border-0">
                            <label className="flex min-h-[2.75rem] items-center gap-2 px-2 py-1">
                                <input type="checkbox" checked={chosen.has(i.id)} onChange={() => toggle(i.id)} data-testid={`bundle-work-${i.id}`} />
                                <span className="flex-1" dir="auto">{i.label}</span>
                            </label>
                        </li>
                    ))}
                </ul>
            </fieldset>
            <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={!!form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} data-testid="bundle-active" /> {t.library_bundles_on}</label>
            <label className="text-sm">{t.library_bundles_ends}<input className="form-input w-full" type="date" value={form.data.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} /></label>
            <div className="flex flex-wrap gap-3 md:col-span-2">
                <button type="submit" className="btn-primary" disabled={form.processing} data-testid="bundle-save">{t.library_bundles_save}</button>
                {editing && <button type="button" className="btn-secondary" onClick={onDone}>{t.library_bundles_cancel}</button>}
            </div>
            <FormErrors errors={form.errors} className="md:col-span-2" />
        </form>
    );
}

export default function Bundles({ bundles = [], items = [], limits = { min: 2, max: 20 }, t = {} }) {
    const [editing, setEditing] = useState(null);

    return (
        <AppShell title={t.library_bundles_title}>
            <p className="mb-3 text-sm text-gray-600">{t.library_bundles_intro}</p>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <a href="/admin/library/bundles/export" className="btn-secondary" data-testid="export-csv">{t.library_bundles_export}</a>
                <a href="/library/promotions" className="btn-secondary" target="_blank" rel="noopener">{t.library_promotions_public}</a>
                <a href="/admin/library" className="btn-secondary">{t.library_settings_back}</a>
            </div>
            <section className="mb-6" data-phone-main data-testid="bundles">
                {bundles.length === 0 ? <p className="rounded border bg-white p-3 text-sm text-gray-600" data-testid="bundles-none">{t.library_bundles_none}</p> : (
                    <table className="table-stack min-w-full rounded-lg border bg-white text-sm">
                        <thead className="bg-[#F3EBE0]">
                            <tr>
                                <th className="px-3 py-2 text-start">{t.library_bundles_col_bundle}</th>
                                <th className="px-3 py-2 text-end">{t.library_bundles_col_price}</th>
                                <th className="px-3 py-2 text-start">{t.library_bundles_col_works}</th>
                                <th className="px-3 py-2 text-start">{t.library_bundles_col_state}</th>
                                <th className="px-3 py-2 text-end">{t.library_bundles_col_sold}</th>
                                <th className="px-3 py-2 text-end">{t.library_bundles_col_revenue}</th>
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {bundles.map((b) => (
                                <tr key={b.id} className="border-t" data-testid={`bundle-${b.slug}`} data-on-sale={b.on_sale ? 'yes' : 'no'}>
                                    <td className="px-3 py-2" data-label={t.library_bundles_col_bundle}><span className="font-medium" dir="auto">{b.title}</span>{b.ends_at && <span className="block text-xs text-gray-500">{b.ends_at}</span>}</td>
                                    <td className="px-3 py-2 sm:text-end" data-label={t.library_bundles_col_price}><s className="text-gray-400">{b.list_total}</s> {b.price}</td>
                                    <td className="px-3 py-2" data-label={t.library_bundles_col_works}>{b.items.map((i) => i.title).join(' · ')}</td>
                                    <td className="px-3 py-2" data-label={t.library_bundles_col_state}>{b.on_sale ? t.library_bundles_state_on : t.library_bundles_state_off}</td>
                                    <td className="px-3 py-2 sm:text-end" data-label={t.library_bundles_col_sold}>{b.sold}</td>
                                    <td className="px-3 py-2 sm:text-end" data-label={t.library_bundles_col_revenue}>{b.revenue}</td>
                                    <td className="table-actions px-3 py-2 text-end">
                                        <a href={`/library/bundles/${b.slug}`} className="btn-secondary me-2" target="_blank" rel="noopener">{t.library_bundles_view}</a>
                                        <button type="button" className="btn-secondary" onClick={() => setEditing(b)} data-testid={`bundle-edit-${b.slug}`}>{t.library_bundles_change}</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </section>
            <BundleForm key={editing?.id ?? 'new'} t={t} items={items} limits={limits} editing={editing} onDone={() => setEditing(null)} />
        </AppShell>
    );
}
