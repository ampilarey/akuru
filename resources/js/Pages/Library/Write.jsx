import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

function ApplyForm({ t }) {
    const form = useForm({
        display_name: '',
        bio: '',
        qualifications: '',
        expertise: '',
        motivation: '',
        agreement_accepted: false,
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/write/apply', { preserveScroll: true });
            }}
            className="grid max-w-2xl gap-3 rounded-lg border bg-white p-4"
        >
            <h2 className="text-lg font-semibold">{t.library_apply_title || 'Apply to publish with Akuru'}</h2>
            <input className="form-input" placeholder="Display name (as shown to readers)" value={form.data.display_name} onChange={(e) => form.setData('display_name', e.target.value)} />
            {form.errors.display_name && <p className="text-sm text-red-600">{form.errors.display_name}</p>}
            <textarea className="form-input" rows="3" placeholder="Bio" value={form.data.bio} onChange={(e) => form.setData('bio', e.target.value)} />
            <textarea className="form-input" rows="2" placeholder="Qualifications" value={form.data.qualifications} onChange={(e) => form.setData('qualifications', e.target.value)} />
            <input className="form-input" placeholder="Expertise (e.g. Tafsir, Arabic grammar)" value={form.data.expertise} onChange={(e) => form.setData('expertise', e.target.value)} />
            <textarea className="form-input" rows="3" placeholder="Why do you want to publish with us?" value={form.data.motivation} onChange={(e) => form.setData('motivation', e.target.value)} />
            <label className="flex items-start gap-2 text-sm">
                <input type="checkbox" checked={form.data.agreement_accepted} onChange={(e) => form.setData('agreement_accepted', e.target.checked)} />
                <span>I own or have permission for everything I upload, accept the publishing, payment, and refund terms, and understand Akuru may remove content on a valid complaint.</span>
            </label>
            {form.errors.agreement_accepted && <p className="text-sm text-red-600">{form.errors.agreement_accepted}</p>}
            {form.errors.application && <p className="text-sm text-red-600">{form.errors.application}</p>}
            <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>{t.library_apply_submit || 'Submit application'}</button>
        </form>
    );
}

function ItemEditor({ item, options, onDone, t }) {
    const form = useForm({
        title: item?.title || '',
        content_type: item?.content_type || options.content_types[0] || 'article',
        access_type: item?.access_type || 'free_login',
        price: item?.price ?? '',
        abstract: item?.abstract || '',
        body: item?.body || '',
        citations: item?.citations || '',
        pdf: null,
    });

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, forceFormData: true, onSuccess: onDone };
        if (item) {
            form.transform((data) => ({ ...data, _method: 'put' })).post(`/write/items/${item.id}`, opts);
        } else {
            form.post('/write/items', opts);
        }
    };

    return (
        <form onSubmit={submit} className="mb-4 grid gap-2 rounded-lg border bg-white p-4 md:grid-cols-4">
            <input className="form-input md:col-span-2" placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
            <select className="form-input" value={form.data.content_type} onChange={(e) => form.setData('content_type', e.target.value)}>
                {options.content_types.map((type) => <option key={type} value={type}>{type.replaceAll('_', ' ')}</option>)}
            </select>
            <select className="form-input" value={form.data.access_type} onChange={(e) => form.setData('access_type', e.target.value)}>
                <option value="free_public">free public</option>
                <option value="free_login">free (login)</option>
                <option value="paid">paid</option>
            </select>
            <input className="form-input" placeholder="Suggested price (MVR)" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} />
            <textarea className="form-input md:col-span-3" rows="2" placeholder="Abstract" value={form.data.abstract} onChange={(e) => form.setData('abstract', e.target.value)} />
            <textarea className="form-input md:col-span-4" rows="6" placeholder="Body (HTML — use <!-- pagebreak --> between pages)" value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
            {form.data.content_type === 'research' && (
                <textarea className="form-input md:col-span-4" rows="3" placeholder="Citations (one per line)" value={form.data.citations} onChange={(e) => form.setData('citations', e.target.value)} />
            )}
            <label className="text-sm md:col-span-3">
                Original PDF (stored privately)
                <input className="form-input" type="file" accept="application/pdf" onChange={(e) => form.setData('pdf', e.target.files[0] ?? null)} />
            </label>
            <div className="flex gap-2 self-end">
                <button type="submit" className="btn-primary" disabled={form.processing}>{item ? t.library_update_draft || 'Update draft' : t.library_save_draft || 'Save draft'}</button>
                {onDone && <button type="button" className="btn-secondary" onClick={onDone}>{t.library_close || 'Close'}</button>}
            </div>
            {Object.values(form.errors).map((error) => <p key={error} className="text-sm text-red-600 md:col-span-4">{error}</p>)}
        </form>
    );
}

function EarningsCard({ earnings, itemSales = [], t = {} }) {
    const bank = useForm({ bank_name: '', account_name: '', account_number: '' });
    if (!earnings) return null;

    return (
        <div className="mb-6 rounded-lg border bg-white p-4">
            <div className="mb-3 flex flex-wrap items-center gap-6 text-sm">
                <span><strong>MVR {earnings.pending}</strong> pending (in refund window)</span>
                <span><strong>MVR {earnings.available}</strong> available</span>
                <span><strong>MVR {earnings.paid}</strong> paid out</span>
                {earnings.refunded > 0 && <span className="text-red-600">MVR {earnings.refunded} refunded</span>}
                {earnings.payouts_enabled ? (
                    <button
                        type="button"
                        className="btn-primary"
                        disabled={!earnings.can_request || !earnings.has_bank_details}
                        onClick={() => router.post('/write/payout-request', {}, { preserveScroll: true })}
                    >
                        Request payout (min MVR {earnings.min_payout})
                    </button>
                ) : (
                    <span className="text-xs text-gray-500">Payouts open soon — earnings keep accruing and stay yours.</span>
                )}
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    bank.post('/write/bank-details', { preserveScroll: true });
                }}
                className="flex flex-wrap items-center gap-2"
            >
                <input className="form-input w-40" placeholder="Bank name" value={bank.data.bank_name} onChange={(e) => bank.setData('bank_name', e.target.value)} />
                <input className="form-input w-40" placeholder="Account name" value={bank.data.account_name} onChange={(e) => bank.setData('account_name', e.target.value)} />
                <input className="form-input w-40" placeholder="Account number" value={bank.data.account_number} onChange={(e) => bank.setData('account_number', e.target.value)} />
                <button type="submit" className="btn-secondary" disabled={bank.processing}>
                    {earnings.has_bank_details ? 'Update bank details' : 'Save bank details'}
                </button>
            </form>
            {itemSales.length > 0 && (
                <div className="mt-4 overflow-x-auto">
                    <p className="mb-1 text-sm font-semibold">{t.library_sales_by_book || 'Sales by book'}</p>
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">{t.library_th_book || 'Book'}</th>
                                <th className="px-3 py-2">{t.library_th_sold || 'Copies sold'}</th>
                                <th className="px-3 py-2">{t.library_th_gross || 'Gross (MVR)'}</th>
                                <th className="px-3 py-2">{t.library_th_your_share || 'Your share (MVR)'}</th>
                                <th className="px-3 py-2">{t.library_th_refunded || 'Refunded'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {itemSales.map((row) => (
                                <tr key={row.item_id} className="border-t">
                                    <td className="px-3 py-2 font-medium">{row.title}</td>
                                    <td className="px-3 py-2">{row.sold}</td>
                                    <td className="px-3 py-2">{row.gross}</td>
                                    <td className="px-3 py-2">{row.earned}</td>
                                    <td className="px-3 py-2">{row.refunded > 0 ? <span className="text-red-600">{row.refunded}</span> : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

export default function Write({ dashboard, options, earnings = null, item_sales = [] }) {
    const { flash = {}, i18n } = usePage().props;
    const t = i18n?.common || {};
    const [editing, setEditing] = useState(null);
    const { profile, application, items, sales } = dashboard;

    return (
        <AppShell title={t.library_write_title || 'Writer portal'}>
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700">{flash.success}</p>}

            {!profile && (
                <div className="mb-6">
                    {application?.status === 'pending' && (
                        <p className="rounded bg-amber-50 p-3 text-amber-800">Your writer application is pending review (applied {application.created_at}).</p>
                    )}
                    {application?.status === 'rejected' && (
                        <p className="mb-4 rounded bg-red-50 p-3 text-red-700">Your last application was not approved{application.decision_note ? ` — ${application.decision_note}` : ''}. You may apply again.</p>
                    )}
                    {application?.status !== 'pending' && <ApplyForm t={t} />}
                </div>
            )}

            {profile && (
                <>
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 className="text-lg font-semibold">{profile.display_name}</h2>
                            <p className="text-sm text-gray-500">Approved writer since {profile.approved_at} · {sales.total_sales || 0} sales · MVR {sales.total_revenue || 0}</p>
                        </div>
                        <button type="button" className="btn-primary" onClick={() => setEditing(editing === 'new' ? null : 'new')}>
                            {editing === 'new' ? t.library_close_editor || 'Close editor' : t.library_new_draft || 'New draft'}
                        </button>
                    </div>

                    <EarningsCard earnings={earnings} itemSales={item_sales} t={t} />

                    {editing === 'new' && <ItemEditor options={options} onDone={() => setEditing(null)} t={t} />}
                    {editing && editing !== 'new' && <ItemEditor item={editing} options={options} onDone={() => setEditing(null)} t={t} />}

                    <div className="overflow-x-auto rounded-lg border bg-white">
                        <table className="min-w-full text-sm">
                            <thead className="bg-[#F3EBE0] text-start">
                                <tr>
                                    <th className="px-3 py-2">{t.library_th_title || 'Title'}</th>
                                    <th className="px-3 py-2">{t.library_th_status || 'Status'}</th>
                                    <th className="px-3 py-2">{t.library_th_feedback || 'Editor feedback'}</th>
                                    <th className="px-3 py-2">{t.library_th_sales || 'Sales'}</th>
                                    <th className="px-3 py-2">{t.library_th_actions || 'Actions'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.length === 0 && (
                                    <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.library_no_drafts || 'No drafts yet — start one.'}</td></tr>
                                )}
                                {items.map((item) => (
                                    <tr key={item.id} className="border-t">
                                        <td className="px-3 py-2">
                                            <p className="font-medium">{item.title}</p>
                                            <p className="text-xs text-gray-500">{item.content_type} · {item.access_type}{item.price ? ` · MVR ${item.price}` : ''}</p>
                                        </td>
                                        <td className="px-3 py-2">{item.status?.replaceAll('_', ' ')}</td>
                                        <td className="px-3 py-2 text-xs text-gray-600">{item.latest_comment || '—'}</td>
                                        <td className="px-3 py-2">{item.sales} ({item.revenue ? `MVR ${item.revenue}` : '—'})</td>
                                        <td className="px-3 py-2">
                                            {['draft', 'changes_requested'].includes(item.status) && (
                                                <span className="flex gap-2">
                                                    <button type="button" className="text-[#7C2D37] hover:underline" onClick={() => setEditing(item)}>{t.library_edit || 'Edit'}</button>
                                                    <button type="button" className="btn-secondary" onClick={() => router.post(`/write/items/${item.id}/submit`, {}, { preserveScroll: true })}>{t.library_submit_review || 'Submit for review'}</button>
                                                </span>
                                            )}
                                            {item.status === 'published' && <a className="text-[#7C2D37] hover:underline" href={`/library/${item.slug}`}>{t.library_view || 'View'}</a>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </AppShell>
    );
}
