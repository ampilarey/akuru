import { Link, router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * COMMERCE_PARITY_PLAN P7c: one customer as the office sees them — orders,
 * problems reported, the office's tags, and notes with follow-ups.
 */
function Tags({ customer, t }) {
    const form = useForm({ tags: customer.tags.join(', ') });
    const save = (e) => {
        e.preventDefault();
        // The list goes as an array; a plain router post, never a chained transform.
        router.post(`/admin/bookshop/customers/${customer.id}/tags`, { tags: form.data.tags.split(',').map((x) => x.trim()).filter(Boolean) }, { preserveScroll: true, onSuccess: (page) => form.setData('tags', page.props.customer.tags.join(', ')) });
    };

    return (
        <form className="flex flex-wrap items-end gap-2" onSubmit={save}>
            <label className="text-sm">{t.customer_tags}
                <input className="form-input block w-80 max-w-full" value={form.data.tags} onChange={(e) => form.setData('tags', e.target.value)} placeholder={t.customer_tags_hint} aria-label={t.customer_tags_hint} data-testid="customer-tags" />
            </label>
            <button type="submit" className="btn-secondary" data-testid="customer-save-tags">{t.customer_save_tags}</button>
        </form>
    );
}

function AddNote({ customer, t }) {
    const form = useForm({ body: '', follow_up_on: '' });

    return (
        <form className="grid gap-2" onSubmit={(e) => { e.preventDefault(); form.post(`/admin/bookshop/customers/${customer.id}/notes`, { preserveScroll: true, onSuccess: () => form.reset() }); }}>
            <label className="text-sm">{t.customer_note_body}
                <textarea className="form-input w-full" rows={2} maxLength={2000} dir="auto" value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} required data-testid="customer-note-body" />
            </label>
            <div className="flex flex-wrap items-end gap-2">
                <label className="text-sm">{t.customer_follow_up_on}
                    <input type="date" className="form-input block" value={form.data.follow_up_on} onChange={(e) => form.setData('follow_up_on', e.target.value)} data-testid="customer-note-follow-up" />
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing} data-testid="customer-add-note">{t.customer_add_note}</button>
            </div>
            {(form.errors.body || form.errors.follow_up_on) && <p className="text-xs text-red-700">{form.errors.body || form.errors.follow_up_on}</p>}
        </form>
    );
}

export default function Customer({ t = {}, customer }) {
    const { errors } = usePage().props;

    return (
        <AppShell title={customer.name}>
            <FormErrors errors={errors} className="mb-4" />
            <p className="mb-4 text-sm"><Link href="/admin/bookshop/customers" className="text-blue-700 underline">{t.customer_back}</Link></p>

            <section className="mb-6 rounded-lg border bg-white p-4" data-testid="customer-summary">
                <p className="text-sm text-gray-600">{[customer.phone, customer.email].filter(Boolean).join(' · ')} · {(t.customer_joined || '').replace(':date', customer.joined)} · {customer.sms_offers ? t.customer_sms_on : t.customer_sms_off}</p>
                <p className="mt-1 text-sm">{t.customer_orders}: <span className="font-semibold">{customer.orders_count}</span> · {t.customer_spent}: <span className="font-semibold">MVR {customer.spent}</span> · {t.customer_last_order}: {customer.last_order}</p>
                <div className="mt-3"><Tags customer={customer} t={t} /></div>
            </section>

            <section className="mb-6 rounded-lg border bg-white p-4" data-testid="customer-notes">
                <h2 className="mb-2 text-lg font-semibold">{t.customer_notes}</h2>
                <AddNote customer={customer} t={t} />
                {customer.notes.length === 0 ? <p className="mt-3 text-sm text-gray-600">{t.customer_no_notes}</p> : (
                    <ul className="mt-3 divide-y text-sm">
                        {customer.notes.map((n) => (
                            <li key={n.id} className="py-2" data-testid={`customer-note-${n.id}`} data-due={n.due ? '1' : '0'}>
                                <p className="whitespace-pre-line break-words" dir="auto">{n.body}</p>
                                <p className="text-xs text-gray-500">
                                    {n.created_at}{n.author ? ` · ${n.author}` : ''}
                                    {n.follow_up_on && !n.done_at && <span className={`ms-1 rounded px-1 ${n.due ? 'bg-amber-100 text-amber-900' : 'bg-gray-100'}`}>{n.due ? t.customer_note_due : (t.customer_note_follow_up || '').replace(':date', n.follow_up_on)}</span>}
                                    {n.done_at && <span className="ms-1">· {(t.customer_note_done || '').replace(':date', n.done_at)}</span>}
                                </p>
                                {n.follow_up_on && !n.done_at && <button type="button" className="mt-1 text-xs text-blue-700 underline" onClick={() => router.post(`/admin/bookshop/customers/${customer.id}/notes/${n.id}/done`, {}, { preserveScroll: true })} data-testid={`customer-note-done-${n.id}`}>{t.customer_mark_done}</button>}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="mb-6" data-testid="customer-orders">
                <h2 className="mb-2 text-lg font-semibold">{t.customer_orders}</h2>
                <div className="overflow-x-auto rounded border bg-white">
                    <table className="table-stack min-w-full text-sm">
                        <tbody>
                            {customer.orders.map((o) => (
                                <tr key={o.number} className="border-t">
                                    <td className="p-2 font-mono" data-label={t.customer_orders}>{o.number}</td>
                                    <td className="p-2" data-label={t.campaign_shop}>{o.shop}</td>
                                    <td className="p-2" data-label="status">{t[`status_${o.status}`] || o.status}</td>
                                    <td className="p-2 sm:text-end" data-label={t.customer_spent}>MVR {o.total}</td>
                                    <td className="p-2" data-label={t.customer_last_order}>{o.placed_at}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            {customer.complaints.length > 0 && (
                <section data-testid="customer-complaints">
                    <h2 className="mb-2 text-lg font-semibold">{t.customer_complaints}</h2>
                    <ul className="divide-y rounded border bg-white text-sm">
                        {customer.complaints.map((c, i) => <li key={i} className="p-2">{c.created_at} · {c.number} · {t[`complaint_kind_${c.kind}`] || c.kind} · {t[`complaint_status_${c.status}`] || c.status}</li>)}
                    </ul>
                </section>
            )}
        </AppShell>
    );
}
