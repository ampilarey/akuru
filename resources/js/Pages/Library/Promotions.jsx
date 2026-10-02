import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * B4 (LIBRARY_PLAN §18): the office's promotion campaigns — start one
 * (name, window, discount, who funds it, what it covers), end one, and
 * see how each did. A campaign is never edited: end it and start another.
 */
function targetLabel(target, options, t) {
    if (target.type === 'all') return t.library_promotions_covers_all || 'Everything paid in the library';
    if (target.type === 'gift_card') return t.library_promotions_gift_card_label || 'Gift cards (a bonus on the card)';
    const pool = { library_category: options.categories, writer_profile: options.writers, library_item: options.items }[target.type] || [];
    const found = pool.find((row) => row.id === target.id);
    return found ? found.label : `${target.type} #${target.id}`;
}

function CampaignForm({ options, fundingSources, t }) {
    const [mode, setMode] = useState('all');
    const [picked, setPicked] = useState({ library_category: [], writer_profile: [], library_item: [] });
    const form = useForm({
        name: '', description: '', starts_at: '', ends_at: '',
        discount_type: 'percentage', discount_value: '', max_discount_amount: '', minimum_amount: '', funding_source: 'akuru',
        banner: null,
    });
    const toggle = (type, id) => setPicked((current) => ({
        ...current,
        [type]: current[type].includes(id) ? current[type].filter((x) => x !== id) : [...current[type], id],
    }));
    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            targets: mode === 'all'
                ? [{ type: 'all' }]
                : mode === 'gift_card'
                    ? [{ type: 'gift_card' }]
                    : Object.entries(picked).flatMap(([type, ids]) => ids.map((id) => ({ type, id }))),
        }));
        form.post('/admin/library/promotions', { preserveScroll: true, forceFormData: true, onSuccess: () => { form.reset(); setPicked({ library_category: [], writer_profile: [], library_item: [] }); setMode('all'); } });
    };
    const pickList = (type, rows, label) => (
        <fieldset className="rounded border p-2">
            <legend className="px-1 text-xs uppercase text-gray-500">{label}</legend>
            <div className="max-h-40 overflow-y-auto text-sm">
                {rows.length === 0 && <p className="text-gray-400">—</p>}
                {rows.map((row) => (
                    <label key={row.id} className="flex items-center gap-2">
                        <input type="checkbox" checked={picked[type].includes(row.id)} onChange={() => toggle(type, row.id)} data-target={`${type}-${row.id}`} />
                        <span>{row.label}</span>
                    </label>
                ))}
            </div>
        </fieldset>
    );

    return (
        <form onSubmit={submit} className="mb-6 grid gap-2 rounded-lg border bg-white p-4 md:grid-cols-4" data-testid="campaign-form">
            <h2 className="text-lg font-semibold md:col-span-4">{t.library_promotions_new || 'Start a campaign'}</h2>
            <input className="form-input md:col-span-2" name="name" placeholder={t.library_promotions_name || 'Name (readers see it)'} aria-label={t.library_promotions_name || 'Name (readers see it)'} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
            <label className="text-sm">{t.library_promotions_starts || 'Starts'}<input className="form-input" type="datetime-local" value={form.data.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} /></label>
            <label className="text-sm">{t.library_promotions_ends || 'Ends (optional)'}<input className="form-input" type="datetime-local" value={form.data.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} /></label>
            <textarea className="form-input md:col-span-3" rows="2" placeholder={t.library_promotions_description || 'Description (optional)'} aria-label={t.library_promotions_description || 'Description (optional)'} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
            {/* B4c: the offer's picture, shown on the public offers page. */}
            <label className="text-sm">
                {t.library_promotions_banner || 'Banner image (optional; JPEG, PNG or WebP)'}
                <input className="form-input" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => form.setData('banner', e.target.files[0] ?? null)} data-testid="campaign-banner" />
            </label>
            <select className="form-input" value={form.data.discount_type} onChange={(e) => form.setData('discount_type', e.target.value)} aria-label={t.library_promotions_type || 'Discount'}>
                <option value="percentage">{t.library_promotions_percentage || 'Percentage off'}</option>
                <option value="fixed">{t.library_promotions_fixed || 'Fixed amount off (MVR)'}</option>
            </select>
            <input className="form-input" name="discount_value" type="number" min="0.01" step="0.01" placeholder={t.library_promotions_value || 'Value'} aria-label={t.library_promotions_value || 'Value'} value={form.data.discount_value} onChange={(e) => form.setData('discount_value', e.target.value)} />
            <input className="form-input" type="number" min="0" step="0.01" placeholder={t.library_promotions_max || 'Maximum off (MVR, optional)'} aria-label={t.library_promotions_max || 'Maximum off (MVR, optional)'} value={form.data.max_discount_amount} onChange={(e) => form.setData('max_discount_amount', e.target.value)} />
            <select className="form-input" value={form.data.funding_source} onChange={(e) => form.setData('funding_source', e.target.value)} aria-label={t.library_promotions_funding || 'Who funds it'}>
                {fundingSources.map((source) => <option key={source} value={source}>{t[`library_promotions_funding_${source}`] || source}</option>)}
            </select>
            <div className="md:col-span-4">
                <p className="mb-1 text-sm font-medium">{t.library_promotions_covers || 'What it covers'}</p>
                <div className="mb-2 flex flex-wrap gap-4 text-sm">
                    <label className="flex items-center gap-1"><input type="radio" name="covers" checked={mode === 'all'} onChange={() => setMode('all')} /> {t.library_promotions_covers_all || 'Everything paid in the library'}</label>
                    <label className="flex items-center gap-1"><input type="radio" name="covers" checked={mode === 'pick'} onChange={() => setMode('pick')} /> {t.library_promotions_covers_pick || 'Only what I pick'}</label>
                    {/* B4b: not a price off — value added to a gift card the buyer pays full price for. */}
                    <label className="flex items-center gap-1"><input type="radio" name="covers" checked={mode === 'gift_card'} onChange={() => setMode('gift_card')} data-testid="covers-gift-card" /> {t.library_promotions_covers_gift_card || 'Gift cards bought (a bonus on the card)'}</label>
                </div>
                {mode === 'gift_card' && (
                    <label className="block text-sm md:w-1/2">
                        {t.library_promotions_minimum || 'From a card of (MVR, optional) — "buy 500, get the bonus"'}
                        <input className="form-input" name="minimum_amount" type="number" min="0" step="1" value={form.data.minimum_amount} onChange={(e) => form.setData('minimum_amount', e.target.value)} />
                    </label>
                )}
                {mode === 'pick' && (
                    <div className="grid gap-2 md:grid-cols-3">
                        {pickList('library_category', options.categories, t.library_promotions_covers_categories || 'Categories')}
                        {pickList('writer_profile', options.writers, t.library_promotions_covers_writers || 'Writers')}
                        {pickList('library_item', options.items, t.library_promotions_covers_items || 'Items')}
                    </div>
                )}
            </div>
            <button type="submit" className="btn-primary justify-self-start md:col-span-4" disabled={form.processing}>{t.library_promotions_start || 'Start campaign'}</button>
            <FormErrors errors={form.errors} className="md:col-span-4" />
        </form>
    );
}

export default function Promotions({ campaigns = [], options = { categories: [], writers: [], items: [] }, funding_sources = [], t = {} }) {
        const describe = (c) => (c.discount_type === 'percentage' ? `${c.discount_value}%` : `MVR ${c.discount_value}`)
        + (c.max_discount_amount ? ` (max MVR ${c.max_discount_amount})` : '')
        + (c.is_gift_card_bonus ? ` ${t.library_promotions_bonus || 'bonus'}` : '')
        + (c.minimum_amount ? ` ${t.library_promotions_from || 'from'} MVR ${c.minimum_amount}` : '');
    const stateLabel = (state) => t[`library_promotions_state_${state}`] || { live: 'Live', scheduled: 'Scheduled', expired: 'Expired', ended: 'Ended' }[state] || state;

    return (
        <AppShell title={t.library_promotions_title || 'Library promotions'}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <a href="/admin/library/promotions/export" className="underline" data-testid="export-csv">{t.library_promotions_export || 'Export CSV'}</a>
                <a href="/library/promotions" className="underline" target="_blank" rel="noopener">{t.library_promotions_public || 'The public offers page'}</a>
                <a href="/admin/library" className="ms-auto underline">{t.library_settings_back || 'Back to the Library office'}</a>
            </div>
            <CampaignForm options={options} fundingSources={funding_sources} t={t} />

            <div className="overflow-x-auto rounded-lg border bg-white" data-testid="campaigns">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.library_promotions_name_col || 'Campaign'}</th>
                            <th className="px-3 py-2">{t.library_promotions_col_window || 'Window'}</th>
                            <th className="px-3 py-2">{t.library_promotions_col_discount || 'Discount'}</th>
                            <th className="px-3 py-2">{t.library_promotions_col_covers || 'Covers'}</th>
                            <th className="px-3 py-2">{t.library_promotions_col_uses || 'Uses'}</th>
                            <th className="px-3 py-2">{t.library_promotions_col_given || 'Given (MVR)'}</th>
                            <th className="px-3 py-2">{t.library_promotions_col_state || 'State'}</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {campaigns.length === 0 && <tr><td className="px-3 py-4 text-gray-500" colSpan="8">{t.library_promotions_none || 'No campaigns yet.'}</td></tr>}
                        {campaigns.map((c) => (
                            <tr key={c.id} className="border-t align-top" data-campaign={c.slug}>
                                <td className="px-3 py-2">
                                    {c.banner_url && <img src={c.banner_url} alt="" className="mb-1 h-12 w-24 rounded object-cover" data-testid="campaign-banner-thumb" />}
                                    <p className="font-medium">{c.name}</p>
                                    {c.description && <p className="text-xs text-gray-500">{c.description}</p>}
                                    <p className="text-xs text-gray-400">{t[`library_promotions_funding_${c.funding_source}`] || c.funding_source}</p>
                                </td>
                                <td className="px-3 py-2 text-xs text-gray-600">{c.starts_at}<br />{c.ends_at || '—'}</td>
                                <td className="px-3 py-2">{describe(c)}</td>
                                <td className="px-3 py-2 text-xs text-gray-600">{c.targets.map((target, i) => <p key={i}>{targetLabel(target, options, t)}</p>)}</td>
                                <td className="px-3 py-2">{c.uses} <span className="text-xs text-gray-500">({c.confirmed} {t.library_promotions_confirmed || 'paid'})</span></td>
                                <td className="px-3 py-2">{c.given}</td>
                                <td className="px-3 py-2"><span className={`rounded px-2 py-0.5 text-xs ${c.state === 'live' ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-700'}`} data-state={c.state}>{stateLabel(c.state)}</span></td>
                                <td className="px-3 py-2">
                                    {c.status === 'active' && (
                                        <button type="button" className="text-sm text-red-600" onClick={() => router.post(`/admin/library/promotions/${c.id}/end`, {}, { preserveScroll: true })} data-testid={`end-${c.slug}`}>{t.library_promotions_end || 'End now'}</button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
