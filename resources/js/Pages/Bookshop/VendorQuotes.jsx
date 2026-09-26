import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * BOOKSHOP_PLAN slice B9d — bulk quotes for schools, the shop's side: the
 * requests waiting for a price first, a price for each line and how many
 * days it holds, or a decline with a note. The customer accepts it into
 * their cart and pays the quoted price while it holds.
 */

const fill = (s, vars) => Object.entries(vars).reduce((out, [k, v]) => out.replaceAll(`:${k}`, v), s || '');
const money = (n) => (Math.round(Number(n || 0) * 100) / 100).toFixed(2);

function PriceForm({ quote, t, defaultDays, maxDays }) {
    const form = useForm({
        prices: Object.fromEntries(quote.items.map((i) => [i.id, i.quoted_price ?? i.list_price])),
        valid_days: defaultDays,
        note: quote.vendor_note || '',
    });
    const total = quote.items.reduce((sum, i) => sum + Number(form.data.prices[i.id] || 0) * i.quantity, 0);
    const [declining, setDeclining] = useState(false);
    const decline = useForm({ note: '' });

    return (
        <div className="mt-3 border-t pt-3">
            <form onSubmit={(e) => { e.preventDefault(); form.post(`/vendor/quotes/${quote.id}`, { preserveScroll: true }); }}>
                <table className="w-full text-sm">
                    <thead className="text-start text-xs text-gray-500"><tr><th className="p-1 text-start">{t.quote_item}</th><th className="p-1 text-end">{t.quantity}</th><th className="p-1 text-end">{t.quote_list_price}</th><th className="p-1 text-end">{t.quote_your_price}</th></tr></thead>
                    <tbody>
                        {quote.items.map((i) => (
                            <tr key={i.id} className="border-t">
                                <td className="p-1" dir="auto">{i.title}{i.variant && <span className="text-gray-500"> · {i.variant}</span>}{i.sku && <span className="block text-xs text-gray-500">{i.sku}</span>}</td>
                                <td className="p-1 text-end">{i.quantity}</td>
                                <td className="p-1 text-end">{i.list_price}</td>
                                <td className="p-1 text-end">
                                    <input className="form-input w-28 text-end" type="number" step="0.01" min="0.01" required value={form.data.prices[i.id]}
                                        onChange={(e) => form.setData('prices', { ...form.data.prices, [i.id]: e.target.value })} data-testid={`quote-price-${i.id}`} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot><tr className="border-t font-semibold"><td className="p-1" colSpan={2}>{t.total}</td><td className="p-1 text-end">{quote.list_total}</td><td className="p-1 text-end" data-testid="quote-live-total">{money(total)}</td></tr></tfoot>
                </table>
                <div className="mt-2 grid gap-2 md:grid-cols-4">
                    <label className="text-sm">{t.quote_valid_days}
                        <input className="form-input w-full" type="number" min="1" max={maxDays} required value={form.data.valid_days} onChange={(e) => form.setData('valid_days', e.target.value)} data-testid="quote-valid-days" />
                    </label>
                    <label className="text-sm md:col-span-3">{t.quote_vendor_note}
                        <input className="form-input w-full" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} maxLength={2000} dir="auto" data-testid="quote-vendor-note" />
                    </label>
                </div>
                <div className="mt-2 flex flex-wrap gap-2">
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="quote-send">{quote.status === 'quoted' ? t.quote_update : t.quote_send}</button>
                    <button type="button" className="btn-secondary" onClick={() => setDeclining(!declining)} data-testid="quote-decline-open">{t.quote_decline}</button>
                </div>
            </form>
            {declining && (
                <form className="mt-2 flex flex-wrap gap-2" onSubmit={(e) => { e.preventDefault(); decline.post(`/vendor/quotes/${quote.id}/decline`, { preserveScroll: true }); }}>
                    <input className="form-input flex-1" required maxLength={2000} placeholder={t.quote_decline_note} value={decline.data.note} onChange={(e) => decline.setData('note', e.target.value)} dir="auto" data-testid="quote-decline-note" />
                    <button type="submit" className="btn-secondary text-red-700" disabled={decline.processing} data-testid="quote-decline">{t.quote_decline}</button>
                </form>
            )}
        </div>
    );
}

function QuoteCard({ quote, t, defaultDays, maxDays }) {
    const open = ['requested', 'quoted'].includes(quote.status);

    return (
        <li className="rounded border bg-white p-3" data-testid={`quote-${quote.number}`} data-status={quote.status}>
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className="font-mono font-semibold">{quote.number} <span className="ms-1 rounded bg-gray-100 px-2 py-0.5 font-sans text-xs">{t[`quote_status_${quote.status}`] || quote.status}</span></p>
                    <p className="text-sm" dir="auto">{quote.organisation} · {quote.customer} {quote.customer_email && <span className="text-gray-500">({quote.customer_email})</span>}{quote.contact_phone && <span className="text-gray-500"> · {quote.contact_phone}</span>}</p>
                    {quote.note && <p className="mt-1 rounded bg-brandBeige-50 p-2 text-sm" dir="auto">{quote.note}</p>}
                    <p className="text-xs text-gray-500">{fill(t.quote_requested_on, { date: quote.requested_at })}{quote.valid_until && ` · ${fill(t.quote_valid_until, { date: quote.valid_until })}`}</p>
                </div>
                <div className="text-end text-sm">
                    <p>{t.quote_list_total} {quote.currency} {quote.list_total}</p>
                    {quote.quoted_total && <p className="font-semibold">{t.quote_quoted_total} {quote.currency} {quote.quoted_total}</p>}
                </div>
            </div>
            {quote.status === 'declined' && quote.vendor_note && <p className="mt-2 text-sm text-gray-600" dir="auto">{quote.vendor_note}</p>}
            {open && <PriceForm quote={quote} t={t} defaultDays={defaultDays} maxDays={maxDays} />}
        </li>
    );
}

export default function VendorQuotes({ t, vendor, quotes, counts, statuses, status, default_valid_days, max_valid_days }) {
    const { flash = {}, errors } = usePage().props;

    return (
        <AppShell title={t.quotes_title}>
            <FormErrors errors={errors} className="mb-4" />
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="flash-success">{flash.success}</p>}
            <header className="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h1 className="text-2xl font-bold" data-testid="quotes-heading">{t.quotes_title} · {vendor.name}</h1>
                    <p className="text-sm text-gray-600"><a href="/vendor" className="text-blue-700 underline">{t.portal_title}</a> · {t.quotes_vendor_intro}</p>
                </div>
                <a href="/vendor/quotes/export" className="btn-secondary" data-testid="export-quotes">{t.export_csv}</a>
            </header>
            <nav className="mb-4 flex flex-wrap gap-2 text-sm" data-testid="quote-filters">
                <button type="button" className={`rounded px-2 py-1 ${!status ? 'bg-gray-800 text-white' : 'bg-gray-100'}`} onClick={() => router.get('/vendor/quotes')}>{t.all_orders}</button>
                {statuses.map((s) => (
                    <button key={s} type="button" className={`rounded px-2 py-1 ${status === s ? 'bg-gray-800 text-white' : 'bg-gray-100'}`} onClick={() => router.get('/vendor/quotes', { status: s })} data-testid={`quote-filter-${s}`}>
                        {t[`quote_status_${s}`] || s} ({counts[s] || 0})
                    </button>
                ))}
            </nav>
            {quotes.length === 0 ? (
                <p className="rounded border bg-white p-4 text-gray-600" data-testid="no-quotes">{t.no_vendor_quotes}</p>
            ) : (
                <ul className="space-y-3">
                    {quotes.map((q) => <QuoteCard key={q.id} quote={q} t={t} defaultDays={default_valid_days} maxDays={max_valid_days} />)}
                </ul>
            )}
        </AppShell>
    );
}
