import { Link, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * COMMERCE_PARITY_PLAN P8c: credit accounts for schools. The office opens
 * an account (limit, terms), the customer pays on account at checkout while
 * the credit covers it, and the office records what the school paid. The
 * ledger behind every figure is append-only; nothing here edits money.
 */
const fill = (s, vars) => Object.entries(vars).reduce((out, [k, v]) => out.split(`:${k}`).join(String(v ?? '')), s || '');

function OpenAccount({ t }) {
    const form = useForm({ identifier: '', organisation: '', credit_limit: '', terms_days: 30, note: '' });

    return (
        <section className="mb-6 rounded-lg border bg-white p-4">
            <h2 className="mb-2 text-lg font-semibold">{t.credit_open_heading}</h2>
            <form className="grid gap-2 sm:grid-cols-2" onSubmit={(e) => { e.preventDefault(); form.post('/admin/bookshop/credit', { preserveScroll: true, onSuccess: () => form.reset() }); }}>
                <label className="text-sm">{t.credit_identifier}<input className="form-input w-full" value={form.data.identifier} onChange={(e) => form.setData('identifier', e.target.value)} required data-testid="credit-identifier" /></label>
                <label className="text-sm">{t.credit_organisation}<input className="form-input w-full" value={form.data.organisation} onChange={(e) => form.setData('organisation', e.target.value)} data-testid="credit-organisation" /></label>
                <label className="text-sm">{t.credit_limit}<input type="number" step="0.01" min="0" className="form-input w-full" value={form.data.credit_limit} onChange={(e) => form.setData('credit_limit', e.target.value)} required data-testid="credit-limit" /></label>
                <label className="text-sm">{t.credit_terms_days}<input type="number" min="1" max="365" className="form-input w-full" value={form.data.terms_days} onChange={(e) => form.setData('terms_days', e.target.value)} required data-testid="credit-terms" /></label>
                <label className="text-sm sm:col-span-2">{t.credit_note}<input className="form-input w-full" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} /></label>
                <div><button type="submit" className="btn-primary" disabled={form.processing} data-testid="credit-open">{t.credit_open_button}</button></div>
            </form>
            {(form.errors.identifier || form.errors.credit_limit || form.errors.terms_days) && <p className="mt-1 text-sm text-red-700" data-testid="credit-open-error">{form.errors.identifier || form.errors.credit_limit || form.errors.terms_days}</p>}
        </section>
    );
}

function Settings({ account, t }) {
    const form = useForm({ organisation: account.organisation || '', credit_limit: account.limit, terms_days: account.terms_days, status: account.status, note: account.note || '' });

    return (
        <form className="flex flex-wrap items-end gap-2" onSubmit={(e) => { e.preventDefault(); form.post(`/admin/bookshop/credit/${account.id}`, { preserveScroll: true }); }}>
            <label className="text-xs">{t.credit_limit}<input type="number" step="0.01" min="0" className="form-input block w-28" value={form.data.credit_limit} onChange={(e) => form.setData('credit_limit', e.target.value)} data-testid={`credit-limit-${account.id}`} /></label>
            <label className="text-xs">{t.credit_terms_days}<input type="number" min="1" max="365" className="form-input block w-20" value={form.data.terms_days} onChange={(e) => form.setData('terms_days', e.target.value)} /></label>
            <label className="text-xs">{t.credit_status}
                <select className="form-input block" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} data-testid={`credit-status-${account.id}`}>
                    <option value="active">{t.credit_status_active}</option>
                    <option value="suspended">{t.credit_status_suspended}</option>
                </select>
            </label>
            <button type="submit" className="btn-secondary text-sm" disabled={form.processing} data-testid={`credit-save-${account.id}`}>{t.credit_save}</button>
        </form>
    );
}

function Payment({ account, t }) {
    const form = useForm({ amount: '', reference: '', note: '', deposit: false });

    return (
        <form className="flex flex-wrap items-end gap-2" onSubmit={(e) => { e.preventDefault(); form.post(`/admin/bookshop/credit/${account.id}/payments`, { preserveScroll: true, onSuccess: () => form.reset() }); }}>
            <label className="text-xs">{t.credit_amount}<input type="number" step="0.01" min="0.01" className="form-input block w-28" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} required data-testid={`credit-pay-amount-${account.id}`} /></label>
            <label className="text-xs">{t.credit_reference}<input className="form-input block w-40" value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} required data-testid={`credit-pay-ref-${account.id}`} /></label>
            <label className="flex items-center gap-1 text-xs"><input type="checkbox" checked={form.data.deposit} onChange={(e) => form.setData('deposit', e.target.checked)} data-testid={`credit-pay-deposit-${account.id}`} /> {t.credit_deposit}</label>
            <button type="submit" className="btn-primary text-sm" disabled={form.processing} data-testid={`credit-pay-${account.id}`}>{t.credit_record_button}</button>
            {(form.errors.amount || form.errors.reference) && <span className="w-full text-xs text-red-700" data-testid={`credit-pay-error-${account.id}`}>{form.errors.amount || form.errors.reference}</span>}
        </form>
    );
}

export default function Credit({ t = {}, accounts = [], statement = null }) {
    const { errors } = usePage().props;

    return (
        <AppShell title={t.credit_title}>
            <FormErrors errors={errors} className="mb-4" />
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-gray-600">{t.credit_intro}</p>
                <a href="/admin/bookshop/credit/export" className="btn-secondary" data-testid="export-credit">{t.export_csv}</a>
            </div>
            <OpenAccount t={t} />
            {accounts.length === 0 ? <p className="rounded border bg-white p-3 text-sm text-gray-600">{t.credit_none}</p> : (
                <ul className="grid gap-3" data-testid="credit-accounts">
                    {accounts.map((a) => (
                        <li key={a.id} className="rounded-lg border bg-white p-4 text-sm" data-testid={`credit-account-${a.id}`} data-owed={a.owed}>
                            <p className="font-semibold">{a.organisation || a.name} <span className="font-normal text-gray-600">· {a.name} · {[a.phone, a.email].filter(Boolean).join(' · ')}</span>
                                <span className={`ms-2 rounded px-1 text-xs ${a.status === 'active' ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-700'}`}>{t[`credit_status_${a.status}`] || a.status}</span></p>
                            <p className="mt-1" data-testid={`credit-figures-${a.id}`}>{fill(t.credit_figures, { limit: a.limit, owed: a.owed, available: a.available, days: a.terms_days })}
                                {Number(a.overdue) > 0 && <span className="ms-2 font-medium text-red-700">{fill(t.credit_overdue, { amount: `MVR ${a.overdue}` })}</span>}
                                {Number(a.in_credit) > 0 && <span className="ms-2 font-medium text-green-700" data-testid={`credit-in-credit-${a.id}`}>{fill(t.credit_in_credit, { amount: `MVR ${a.in_credit}` })}</span>}</p>
                            <div className="mt-3 grid gap-3 md:grid-cols-2">
                                <Settings account={a} t={t} />
                                <Payment account={a} t={t} />
                            </div>
                            <p className="mt-2 flex flex-wrap gap-3">
                                <Link href={`/admin/bookshop/credit?account=${a.id}`} preserveScroll className="text-blue-700 underline" data-testid={`credit-show-${a.id}`}>{t.credit_statement}</Link>
                                <a href={`/admin/bookshop/credit/${a.id}/statement`} className="text-blue-700 underline">{t.credit_statement_csv}</a>
                            </p>
                            {statement && statement.account_id === a.id && (
                                <div className="mt-2 overflow-x-auto" data-testid={`credit-statement-${a.id}`}>
                                    {statement.entries.length === 0 ? <p className="text-gray-600">{t.credit_empty_statement}</p> : (
                                        <table className="table-stack min-w-full text-xs">
                                            <thead className="bg-gray-50"><tr><th className="p-1 text-start">{t.credit_date}</th><th className="p-1 text-start">{t.credit_kind}</th><th className="p-1 text-start">{t.credit_reference}</th><th className="p-1 text-end">{t.credit_charge}</th><th className="p-1 text-end">{t.credit_paid}</th><th className="p-1 text-end">{t.credit_balance}</th></tr></thead>
                                            <tbody>
                                                {statement.entries.map((e, i) => (
                                                    <tr key={i} className="border-t">
                                                        <td className="p-1" data-label={t.credit_date}>{e.date}</td>
                                                        <td className="p-1" data-label={t.credit_kind}>{t[`credit_kind_${e.kind}`] || e.kind}</td>
                                                        <td className="p-1" data-label={t.credit_reference}>{e.reference}{e.note ? ` — ${e.note}` : ''}</td>
                                                        <td className="p-1 sm:text-end" data-label={t.credit_charge}>{e.charge}</td>
                                                        <td className="p-1 sm:text-end" data-label={t.credit_paid}>{e.credit}</td>
                                                        <td className="p-1 sm:text-end" data-label={t.credit_balance}>{e.balance}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    )}
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </AppShell>
    );
}
