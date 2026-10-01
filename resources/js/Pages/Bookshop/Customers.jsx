import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * COMMERCE_PARITY_PLAN P7c: the Bookstore's customers — everyone with a
 * paid order, biggest spenders first — with the office's tags and the
 * follow-ups that are due.
 */
export default function Customers({ t = {}, customers = [], tags = [], filters = {} }) {
    const { errors } = usePage().props;
    const [q, setQ] = useState(filters.q || '');
    const [tag, setTag] = useState(filters.tag || '');
    const [followUps, setFollowUps] = useState(Boolean(filters.follow_ups));
    const query = new URLSearchParams(Object.fromEntries(Object.entries({ q, tag, follow_ups: followUps ? '1' : '' }).filter(([, v]) => v))).toString();
    const apply = (e) => { e.preventDefault(); router.get('/admin/bookshop/customers', Object.fromEntries(new URLSearchParams(query)), { preserveState: true }); };

    return (
        <AppShell title={t.customers_title}>
            <FormErrors errors={errors} className="mb-4" />
            <p className="mb-4 text-sm text-gray-600">{t.customers_intro}</p>
            <form className="mb-4 flex flex-wrap items-end gap-2" onSubmit={apply}>
                <input className="form-input w-72 max-w-full" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t.customers_search} aria-label={t.customers_search} data-testid="customers-search" />
                <select className="form-input" value={tag} onChange={(e) => setTag(e.target.value)} aria-label={t.customer_tags} data-testid="customers-tag">
                    <option value="">{t.customers_all_tags}</option>
                    {tags.map((x) => <option key={x} value={x}>{x}</option>)}
                </select>
                <label className="flex items-center gap-1 text-sm"><input type="checkbox" checked={followUps} onChange={(e) => setFollowUps(e.target.checked)} data-testid="customers-follow-ups" /> {t.customers_follow_ups}</label>
                <button type="submit" className="btn-secondary" data-testid="customers-filter">{t.customers_filter}</button>
                <a href={`/admin/bookshop/customers/export${query ? `?${query}` : ''}`} className="btn-secondary ms-auto" data-testid="export-customers">{t.export_csv}</a>
            </form>
            {customers.length === 0 ? <p className="rounded border bg-white p-3 text-sm text-gray-600" data-testid="customers-empty">{t.customers_empty}</p> : (
                <div className="overflow-x-auto rounded border bg-white">
                    <table className="table-stack min-w-full text-sm" data-testid="customers">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="p-2 text-start">{t.customers_title}</th>
                                <th className="p-2 text-end">{t.customer_orders}</th>
                                <th className="p-2 text-end">{t.customer_spent}</th>
                                <th className="p-2 text-start">{t.customer_last_order}</th>
                                <th className="p-2 text-start">{t.customer_tags}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {customers.map((c) => (
                                <tr key={c.id} className="border-t" data-testid={`customer-${c.id}`}>
                                    <td className="p-2" data-label={t.customers_title}>
                                        <Link href={`/admin/bookshop/customers/${c.id}`} className="font-medium text-blue-700 underline">{c.name}</Link>
                                        <span className="block text-xs text-gray-500">{[c.phone, c.email].filter(Boolean).join(' · ')}</span>
                                        {c.follow_ups_due > 0 && <span className="mt-1 inline-block rounded bg-amber-100 px-1 text-xs text-amber-900" data-testid={`customer-due-${c.id}`}>{(t.customer_follow_ups_due || '').replace(':count', c.follow_ups_due)}</span>}
                                    </td>
                                    <td className="p-2 sm:text-end" data-label={t.customer_orders}>{c.orders}</td>
                                    <td className="p-2 sm:text-end" data-label={t.customer_spent}>MVR {c.spent}</td>
                                    <td className="p-2" data-label={t.customer_last_order}>{c.last_order}</td>
                                    <td className="p-2" data-label={t.customer_tags}>{c.tags.map((x) => <span key={x} className="me-1 inline-block rounded bg-gray-100 px-1 text-xs">{x}</span>)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AppShell>
    );
}
