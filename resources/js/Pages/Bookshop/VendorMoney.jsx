import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * BOOKSHOP_PLAN slice B6 — the shop's money: what it has earned by state,
 * the balance it may ask for, bank details (owners), payout requests and
 * their outcomes, Akuru's monthly commission invoices, statements by
 * month, and every earning by order. CSVs of the earnings and statements.
 */

function Stat({ label, value, currency, testid, tone = '' }) {
    return (
        <div className={`rounded-lg border bg-white p-3 ${tone}`} data-testid={testid}>
            <p className="text-xs text-gray-500">{label}</p>
            <p className="text-lg font-semibold">{currency} {value}</p>
        </div>
    );
}

function BankDetails({ bank, isOwner, t }) {
    const form = useForm({ bank_name: bank?.bank_name || '', account_name: bank?.account_name || '', account_number: '' });
    const [editing, setEditing] = useState(!bank);

    return (
        <section className="rounded-lg border bg-white p-4" data-testid="bank-details">
            <h2 className="mb-1 text-lg font-semibold">{t.bank_details}</h2>
            <p className="mb-2 text-xs text-gray-500">{t.bank_details_hint}</p>
            {bank && !editing && (
                <p className="text-sm" data-testid="bank-summary">
                    {bank.bank_name} · {bank.account_name} · <span className="font-mono">{bank.account_number_masked}</span>
                    {isOwner && <button type="button" className="ms-3 text-blue-700 underline" onClick={() => setEditing(true)} data-testid="edit-bank">{t.edit}</button>}
                </p>
            )}
            {!bank && !isOwner && <p className="text-sm text-amber-800">{t.bank_details_owner_only}</p>}
            {isOwner && editing && (
                <form className="grid gap-2 md:grid-cols-4" onSubmit={(e) => { e.preventDefault(); form.post('/vendor/money/bank-details', { preserveScroll: true, onSuccess: () => { setEditing(false); form.setData('account_number', ''); } }); }}>
                    <label className="text-sm">{t.bank_name}<input className="form-input w-full" value={form.data.bank_name} onChange={(e) => form.setData('bank_name', e.target.value)} required data-testid="bank-name" /></label>
                    <label className="text-sm">{t.account_name}<input className="form-input w-full" value={form.data.account_name} onChange={(e) => form.setData('account_name', e.target.value)} required data-testid="account-name" /></label>
                    <label className="text-sm">{t.account_number}<input className="form-input w-full" value={form.data.account_number} onChange={(e) => form.setData('account_number', e.target.value)} required data-testid="account-number" /></label>
                    <div className="flex items-end gap-2">
                        <button type="submit" className="btn-primary" disabled={form.processing} data-testid="save-bank">{t.save}</button>
                        {bank && <button type="button" className="text-sm underline" onClick={() => setEditing(false)}>{t.cancel}</button>}
                    </div>
                    <FormErrors errors={form.errors} className="md:col-span-4" />
                </form>
            )}
        </section>
    );
}

export default function VendorMoney({ t, vendor, money }) {
    const { flash = {}, errors } = usePage().props;
    const isOwner = vendor.role === 'owner';
    const m = money;
    const s = m.summary;
    const c = m.currency;
    const [tab, setTab] = useState('earnings');

    return (
        <AppShell title={t.money_title}>
            <FormErrors errors={errors} className="mb-4" />
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="flash-success">{flash.success}</p>}
            <header className="mb-4">
                <h1 className="text-2xl font-bold" data-testid="money-heading">{t.money_title} · {vendor.name}</h1>
                <p className="text-sm text-gray-600">
                    <a href="/vendor" className="text-blue-700 underline">{t.portal_title}</a>
                    {' · '}
                    {t.money_intro.replace(':rate', s.commission_rate).replace(':days', s.return_window_days)}
                </p>
            </header>

            <div className="mb-4 grid gap-3 sm:grid-cols-2 md:grid-cols-5">
                <Stat label={t.awaiting_delivery} value={s.awaiting_delivery} currency={c} testid="stat-awaiting" />
                <Stat label={t.in_return_window} value={s.in_window} currency={c} testid="stat-window" />
                <Stat label={t.available_now} value={s.available} currency={c} testid="stat-available" tone="border-green-300" />
                <Stat label={t.stat_requested} value={s.requested} currency={c} testid="stat-requested" />
                <Stat label={t.paid_out} value={s.paid} currency={c} testid="stat-paid" />
            </div>

            <section className="mb-4 flex flex-wrap items-center gap-3 rounded-lg border bg-white p-4" data-testid="payout-box">
                <div>
                    <p className="text-xs text-gray-500">{t.requestable_balance}</p>
                    <p className="text-2xl font-bold" data-testid="requestable">{c} {s.requestable_money}</p>
                </div>
                {!s.payouts_enabled ? (
                    <p className="text-sm text-gray-600">{t.payouts_closed}</p>
                ) : isOwner ? (
                    <>
                        <button type="button" className="btn-primary" disabled={!s.can_request} title={!s.has_bank_details ? t.error_bank_details_first : ''} onClick={() => router.post('/vendor/money/payout-request', {}, { preserveScroll: true })} data-testid="request-payout">{t.request_payout}</button>
                        <span className="text-xs text-gray-500">
                            {s.has_open_payout ? t.payout_waiting : !s.has_bank_details ? t.error_bank_details_first : t.min_payout_hint.replace(':amount', `${c} ${s.min_payout}`)}
                        </span>
                    </>
                ) : (
                    <p className="text-sm text-gray-600">{t.owner_requests_payout}</p>
                )}
                {s.reversed_count > 0 && <span className="ms-auto text-xs text-red-700">{t.reversed_count.replace(':count', s.reversed_count)}</span>}
            </section>

            <div className="mb-4"><BankDetails bank={m.bank} isOwner={isOwner} t={t} /></div>

            <nav className="mb-3 flex flex-wrap gap-1 border-b text-sm" data-testid="money-tabs">
                {['earnings', 'statements', 'payouts', 'invoices'].map((k) => (
                    <button key={k} type="button" className={`px-3 py-2 ${tab === k ? 'border-b-2 border-gray-900 font-semibold' : 'text-gray-600'}`} onClick={() => setTab(k)} data-testid={`tab-${k}`}>{t[`money_tab_${k}`]}</button>
                ))}
            </nav>

            {tab === 'earnings' && (
                <section data-testid="earnings">
                    <div className="mb-2 flex items-center justify-between">
                        <p className="text-sm text-gray-600">{t.earnings_intro}</p>
                        <a href="/vendor/money/earnings/export" className="btn-secondary" data-testid="export-earnings">{t.export_csv}</a>
                    </div>
                    {m.earnings.length === 0 ? <p className="rounded border bg-white p-4 text-gray-600">{t.no_earnings}</p> : (
                        <div className="overflow-x-auto">
                            <table className="w-full rounded border bg-white text-sm">
                                <thead className="bg-gray-50"><tr>
                                    <th className="p-2 text-start">{t.order_number}</th><th className="p-2 text-start">{t.paid_on}</th><th className="p-2 text-end">{t.goods}</th><th className="p-2 text-end">{t.delivery}</th><th className="p-2 text-end">{t.commission}</th><th className="p-2 text-end">{t.refunded}</th><th className="p-2 text-end">{t.net}</th><th className="p-2 text-start">{t.status}</th>
                                </tr></thead>
                                <tbody>
                                    {m.earnings.map((e) => (
                                        <tr key={e.id} className="border-t" data-testid={`earning-${e.order_number}`} data-earning-status={e.status}>
                                            <td className="p-2 font-mono">{e.order_number}</td>
                                            <td className="p-2">{e.paid_at}</td>
                                            <td className="p-2 text-end">{e.gross}{Number(e.discount) > 0 && <span className="block text-xs text-gray-500">−{e.discount} {t[`funding_${e.discount_funding}`] || ''}</span>}</td>
                                            <td className="p-2 text-end">{e.delivery_fee}</td>
                                            <td className="p-2 text-end">{e.commission}{Number(e.commission_tax) > 0 && <span className="block text-xs text-gray-500">+{e.commission_tax} {t.gst}</span>}<span className="block text-xs text-gray-500">{e.commission_rate}%</span></td>
                                            <td className="p-2 text-end">{Number(e.refunded) > 0 ? e.refunded : '—'}</td>
                                            <td className="p-2 text-end font-semibold">{e.net}{Number(e.cash_collected) > 0 && <span className="block text-xs font-normal text-amber-800" data-testid="cash-collected">{t.cash_you_took}: {e.cash_collected}</span>}{Number(e.paid_amount) > 0 && Number(e.balance) !== 0 && <span className="block text-xs text-red-700">{t.clawback}: {e.balance}</span>}</td>
                                            <td className="p-2">{t[`earning_${e.status}`] || e.status}{e.status === 'pending' && <span className="block text-xs text-gray-500">{e.available_at ? t.available_on.replace(':date', e.available_at) : t.after_delivery}</span>}{e.in_payout && <span className="block text-xs text-gray-500">{t.in_payout_request}</span>}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            )}

            {tab === 'statements' && (
                <section data-testid="statements">
                    <div className="mb-2 flex items-center justify-between">
                        <p className="text-sm text-gray-600">{t.statements_intro}</p>
                        <a href="/vendor/money/statements/export" className="btn-secondary" data-testid="export-statements">{t.export_csv}</a>
                    </div>
                    {m.statements.length === 0 ? <p className="rounded border bg-white p-4 text-gray-600">{t.no_earnings}</p> : (
                        <div className="overflow-x-auto">
                            <table className="w-full rounded border bg-white text-sm">
                                <thead className="bg-gray-50"><tr>
                                    <th className="p-2 text-start">{t.month}</th><th className="p-2 text-end">{t.orders_title}</th><th className="p-2 text-end">{t.goods}</th><th className="p-2 text-end">{t.delivery}</th><th className="p-2 text-end">{t.refunded}</th><th className="p-2 text-end">{t.commission}</th><th className="p-2 text-end">{t.net}</th><th className="p-2 text-end">{t.paid_out}</th><th className="p-2 text-start">{t.invoice}</th>
                                </tr></thead>
                                <tbody>
                                    {m.statements.map((r) => (
                                        <tr key={r.month} className="border-t" data-testid={`statement-${r.month}`}>
                                            <td className="p-2">{r.label}</td>
                                            <td className="p-2 text-end">{r.orders}</td>
                                            <td className="p-2 text-end">{r.gross}{Number(r.discounts_vendor) > 0 && <span className="block text-xs text-gray-500">−{r.discounts_vendor}</span>}</td>
                                            <td className="p-2 text-end">{r.delivery}</td>
                                            <td className="p-2 text-end">{r.refunded}</td>
                                            <td className="p-2 text-end">{r.commission}{Number(r.commission_tax) > 0 && <span className="block text-xs text-gray-500">+{r.commission_tax} {t.gst}</span>}</td>
                                            <td className="p-2 text-end font-semibold">{r.net}</td>
                                            <td className="p-2 text-end">{r.paid_out}</td>
                                            <td className="p-2">{r.invoice_number ? `${r.invoice_number} · ${c} ${r.invoice_total}` : '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            )}

            {tab === 'payouts' && (
                <section data-testid="payouts">
                    {m.payouts.length === 0 ? <p className="rounded border bg-white p-4 text-gray-600">{t.no_payouts}</p> : (
                        <table className="w-full rounded border bg-white text-sm">
                            <thead className="bg-gray-50"><tr><th className="p-2 text-start">{t.requested_on}</th><th className="p-2 text-end">{t.amount}</th><th className="p-2 text-start">{t.status}</th><th className="p-2 text-start">{t.payment_ref}</th></tr></thead>
                            <tbody>
                                {m.payouts.map((p) => (
                                    <tr key={p.id} className="border-t" data-testid={`payout-${p.id}`} data-payout-status={p.status}>
                                        <td className="p-2">{p.requested_at}</td>
                                        <td className="p-2 text-end">{p.currency} {p.amount}</td>
                                        <td className="p-2">{t[`payout_${p.status}`] || p.status}{p.decided_at && <span className="block text-xs text-gray-500">{p.decided_at}</span>}</td>
                                        <td className="p-2">{p.reference || ''}{p.note && <span className="block text-xs text-gray-500">{p.note}</span>}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
            )}

            {tab === 'invoices' && (
                <section data-testid="invoices">
                    <p className="mb-2 text-sm text-gray-600">{t.invoices_intro}</p>
                    {m.invoices.length === 0 ? <p className="rounded border bg-white p-4 text-gray-600">{t.no_invoices}</p> : (
                        <table className="w-full rounded border bg-white text-sm">
                            <thead className="bg-gray-50"><tr><th className="p-2 text-start">{t.invoice}</th><th className="p-2 text-start">{t.period}</th><th className="p-2 text-end">{t.sales_charged}</th><th className="p-2 text-end">{t.commission}</th><th className="p-2 text-end">{t.gst}</th><th className="p-2 text-end">{t.total}</th><th className="p-2" /></tr></thead>
                            <tbody>
                                {m.invoices.map((i) => (
                                    <tr key={i.id} className="border-t" data-testid={`invoice-${i.number}`}>
                                        <td className="p-2 font-mono">{i.number}</td>
                                        <td className="p-2">{i.period}</td>
                                        <td className="p-2 text-end">{i.sales}</td>
                                        <td className="p-2 text-end">{i.commission}</td>
                                        <td className="p-2 text-end">{i.tax}</td>
                                        <td className="p-2 text-end font-semibold">{i.currency} {i.total}</td>
                                        <td className="p-2 text-end"><a href={`/vendor/money/invoices/${i.id}`} target="_blank" rel="noreferrer" className="text-blue-700 underline" data-testid={`open-invoice-${i.number}`}>{t.open}</a></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
            )}
        </AppShell>
    );
}
