import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

// An amount in rufiyaa, said in the page's language; any other currency by its code.
function money(t, amount, currency = 'MVR') {
    return currency === 'MVR' ? (t.commerce_money || ':amount MVR').replace(':amount', amount) : `${currency} ${amount}`;
}

function GiftCardForm({ t, actOn }) {
    const flash = usePage().props.flash || {};
    const form = useForm({ amount: '', recipient_name: '', recipient_email: '', message: '', expires_at: '' });

    return (
        <div className="mb-6 rounded-lg border bg-white p-4">
            <h2 className="mb-2 text-lg font-semibold">{t.commerce_issue_heading || 'Issue gift card'}</h2>
            {flash.gift_card_code && (
                <p className="mb-3 rounded bg-amber-50 p-3 font-mono text-lg">
                    {flash.gift_card_code}
                    <span className="ms-2 text-sm font-sans text-amber-800">{t.commerce_code_once || 'Copy it now — it is shown only once.'}</span>
                </p>
            )}
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    actOn('gift-card-form', () => form.post('/admin/commerce/gift-cards', { preserveScroll: true, onSuccess: () => form.reset() }));
                }}
                className="grid gap-2 md:grid-cols-5"
            >
                <input className="form-input" type="number" step="0.01" min="1" placeholder={t.commerce_amount_mvr || 'Amount (MVR)'} aria-label={t.commerce_amount_mvr || 'Amount (MVR)'} value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                <input className="form-input" placeholder={t.commerce_recipient_name || 'Recipient name'} aria-label={t.commerce_recipient_name || 'Recipient name'} value={form.data.recipient_name} onChange={(e) => form.setData('recipient_name', e.target.value)} />
                <input className="form-input" type="email" placeholder={t.commerce_recipient_email || 'Recipient email'} aria-label={t.commerce_recipient_email || 'Recipient email'} value={form.data.recipient_email} onChange={(e) => form.setData('recipient_email', e.target.value)} />
                <input className="form-input" type="date" value={form.data.expires_at} onChange={(e) => form.setData('expires_at', e.target.value)} aria-label={t.commerce_expires_on || 'Expires on'} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.commerce_issue || 'Issue'}</button>
                <FormErrors errors={form.errors} />
            </form>
        </div>
    );
}

function CreditForm({ t, actOn }) {
    const form = useForm({ user_id: '', amount: '', description: '' });

    return (
        <div className="mb-6 rounded-lg border bg-white p-4">
            <h2 className="mb-2 text-lg font-semibold">{t.commerce_credit_heading || 'Manual wallet credit'}</h2>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    actOn('credit-form', () => form.post('/admin/commerce/wallet-credits', { preserveScroll: true, onSuccess: () => form.reset() }));
                }}
                className="grid gap-2 md:grid-cols-4"
            >
                <input className="form-input" type="number" placeholder={t.commerce_user_id || 'User ID'} aria-label={t.commerce_user_id || 'User ID'} value={form.data.user_id} onChange={(e) => form.setData('user_id', e.target.value)} />
                <input className="form-input" type="number" step="0.01" min="0.01" placeholder={t.commerce_amount_mvr || 'Amount (MVR)'} aria-label={t.commerce_amount_mvr || 'Amount (MVR)'} value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                <input className="form-input" placeholder={t.commerce_reason || 'Reason'} aria-label={t.commerce_reason || 'Reason'} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.commerce_credit || 'Credit'}</button>
                <FormErrors errors={form.errors} />
            </form>
        </div>
    );
}

function DiscountForm({ t, actOn }) {
    const form = useForm({
        code: '', name: '', discount_type: 'percentage', discount_value: '',
        max_discount_amount: '', usage_limit: '', per_user_limit: '', minimum_order_amount: '',
    });

    return (
        <div className="mb-6 rounded-lg border bg-white p-4">
            <h2 className="mb-2 text-lg font-semibold">{t.commerce_discount_heading || 'New discount code'}</h2>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    actOn('discount-form', () => form.post('/admin/commerce/discount-codes', { preserveScroll: true, onSuccess: () => form.reset() }));
                }}
                className="grid gap-2 md:grid-cols-4"
            >
                <input className="form-input" placeholder={t.commerce_code || 'CODE'} aria-label={t.commerce_code || 'CODE'} value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                <select className="form-input" value={form.data.discount_type} onChange={(e) => form.setData('discount_type', e.target.value)} aria-label={t.commerce_discount_type || 'Discount type'}>
                    <option value="percentage">{t.commerce_type_percentage || 'Percentage'}</option>
                    <option value="fixed">{t.commerce_type_fixed || 'Fixed amount'}</option>
                </select>
                <input className="form-input" type="number" step="0.01" min="0.01" placeholder={t.commerce_value || 'Value'} aria-label={t.commerce_value || 'Value'} value={form.data.discount_value} onChange={(e) => form.setData('discount_value', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.commerce_save || 'Save'}</button>
                <input className="form-input" type="number" placeholder={t.commerce_usage_limit || 'Usage limit'} aria-label={t.commerce_usage_limit || 'Usage limit'} value={form.data.usage_limit} onChange={(e) => form.setData('usage_limit', e.target.value)} />
                <input className="form-input" type="number" placeholder={t.commerce_per_user_limit || 'Per-user limit'} aria-label={t.commerce_per_user_limit || 'Per-user limit'} value={form.data.per_user_limit} onChange={(e) => form.setData('per_user_limit', e.target.value)} />
                <input className="form-input" type="number" step="0.01" placeholder={t.commerce_min_order || 'Min order'} aria-label={t.commerce_min_order || 'Min order'} value={form.data.minimum_order_amount} onChange={(e) => form.setData('minimum_order_amount', e.target.value)} />
                <input className="form-input" placeholder={t.commerce_name || 'Name'} aria-label={t.commerce_name || 'Name'} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <FormErrors errors={form.errors} />
            </form>
        </div>
    );
}

function LiabilityPanel({ liability, t }) {
    // §13.7. Two figures and a total rather than one number: redemption MOVES
    // value from a card to a wallet, so an admin reconciling the books needs to
    // see that the total did not change when it did.
    if (!liability) return null;

    const owed = (n) => money(t, Number(n).toFixed(2));

    return (
        <div className="mb-6 rounded-lg border bg-white p-4">
            <h2 className="mb-1 text-lg font-semibold">{t.commerce_owed_heading || 'Stored value owed'}</h2>
            <p className="mb-3 text-sm text-gray-600">
                {t.commerce_owed_intro || 'What the Institute owes in goods it has already been paid for, or has given away.'}
            </p>
            <div className="grid gap-3 sm:grid-cols-3">
                <div className="rounded border p-3">
                    <div className="text-xs uppercase text-gray-500">{t.commerce_gift_cards || 'Gift cards'}</div>
                    <div className="text-xl font-semibold">{owed(liability.gift_cards)}</div>
                    <div className="text-xs text-gray-500">{(t.commerce_still_spendable || ':count still spendable').replace(':count', liability.gift_card_count)}</div>
                </div>
                <div className="rounded border p-3">
                    <div className="text-xs uppercase text-gray-500">{t.commerce_wallets || 'Wallets'}</div>
                    <div className="text-xl font-semibold">{owed(liability.wallets)}</div>
                    <div className="text-xs text-gray-500">{(t.commerce_with_balance || ':count with a balance').replace(':count', liability.wallet_count)}</div>
                </div>
                <div className="rounded border border-[#7C2D37] p-3">
                    <div className="text-xs uppercase text-gray-500">{t.commerce_total_owed || 'Total owed'}</div>
                    <div className="text-xl font-semibold text-[#7C2D37]">{owed(liability.total)}</div>
                    <div className="text-xs text-gray-500">{t.commerce_redeem_moves || 'Redeeming a card moves value here, it does not add any.'}</div>
                </div>
            </div>
        </div>
    );
}

export default function Admin({ gift_cards, gift_card_orders = [], discount_codes, liability, t = {} }) {
    // CO1: a refused Deactivate is said under its card; each form says its own.
    const refusals = useRowRefusals();
    // How the bought code went out — email, SMS, or both — and to whom (masked).
    const sentVia = (order) => (t.commerce_sent_to || ':via to :to')
        .replace(':via', order.delivered_via.split('+').map((via) => t[`commerce_via_${via}`] || via).join(' + '))
        .replace(':to', order.delivered_to ?? '');

    return (
        <AppShell title={t.commerce_title || 'Commerce admin'}>
            <FormErrors errors={refusals.unplaced} className="mb-4" />
            <LiabilityPanel liability={liability} t={t} />
            <GiftCardForm t={t} actOn={refusals.actOn} />
            <CreditForm t={t} actOn={refusals.actOn} />
            <DiscountForm t={t} actOn={refusals.actOn} />

            {/* §7.7 gift card usage, §41 admin "gift card purchase": what people
                bought at /gift-cards, whether the bank confirmed it, and where the
                code went (masked — the code itself is never stored). */}
            <div className="mb-2 flex items-center justify-between">
                <h2 className="text-lg font-semibold">{t.commerce_purchases_heading || 'Gift card purchases'}</h2>
                <a className="text-sm text-[#7C2D37] hover:underline" href="/admin/commerce/gift-card-orders/export" data-testid="gift-card-orders-export">{t.ft_export || 'Export CSV'}</a>
            </div>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white" data-testid="gift-card-orders">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.commerce_col_order || 'Order'}</th>
                            <th className="px-3 py-2">{t.commerce_col_buyer || 'Buyer'}</th>
                            <th className="px-3 py-2">{t.commerce_col_for || 'For'}</th>
                            <th className="px-3 py-2">{t.commerce_col_amount || 'Amount'}</th>
                            <th className="px-3 py-2">{t.commerce_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.commerce_col_code_sent || 'Code sent'}</th>
                            <th className="px-3 py-2">{t.commerce_col_when || 'When'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {gift_card_orders.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={7}>{t.commerce_no_purchases || 'No gift cards bought yet.'}</td></tr>
                        )}
                        {gift_card_orders.map((order) => (
                            <tr key={order.id} className="border-t">
                                <td data-label={t.commerce_col_order || 'Order'} className="px-3 py-2">#{order.id}</td>
                                <td data-label={t.commerce_col_buyer || 'Buyer'} className="px-3 py-2">{order.buyer}{order.buyer_email ? <span className="block text-xs text-gray-500">{order.buyer_email}</span> : null}</td>
                                <td data-label={t.commerce_col_for || 'For'} className="px-3 py-2">{order.recipient_name}</td>
                                <td data-label={t.commerce_col_amount || 'Amount'} className="px-3 py-2">{money(t, order.amount, order.currency)}{order.bonus_amount ? <span className="block text-xs text-green-700" data-testid="order-bonus">{(t.commerce_bonus || '+ :amount bonus').replace(':amount', order.bonus_amount)}</span> : null}</td>
                                <td data-label={t.commerce_col_status || 'Status'} className="px-3 py-2">{t[`commerce_order_status_${order.status}`] || order.status}{order.gift_card_id ? ` · ${(t.commerce_card_number || 'card #:id').replace(':id', order.gift_card_id)}` : ''}</td>
                                <td data-label={t.commerce_col_code_sent || 'Code sent'} className="px-3 py-2">{order.delivered_via ? sentVia(order) : '—'}</td>
                                <td data-label={t.commerce_col_when || 'When'} className="px-3 py-2">{order.paid_at ?? order.created_at}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <h2 className="mb-2 text-lg font-semibold">{t.commerce_gift_cards || 'Gift cards'}</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.commerce_col_card || 'Gift card'}</th>
                            <th className="px-3 py-2">{t.commerce_col_recipient || 'Recipient'}</th>
                            <th className="px-3 py-2">{t.commerce_col_amount || 'Amount'}</th>
                            <th className="px-3 py-2">{t.commerce_col_balance || 'Balance'}</th>
                            <th className="px-3 py-2">{t.commerce_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.commerce_col_source || 'Source'}</th>
                            <th className="px-3 py-2">{t.commerce_col_expires || 'Expires'}</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {gift_cards.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={8}>{t.commerce_no_cards || 'No gift cards issued.'}</td></tr>
                        )}
                        {gift_cards.map((card) => (
                            <tr key={card.id} className="border-t" data-testid="gift-card-row">
                                <td data-label={t.commerce_col_card || 'Gift card'} className="px-3 py-2">#{card.id}</td>
                                <td data-label={t.commerce_col_recipient || 'Recipient'} className="px-3 py-2">{card.recipient_name ?? card.recipient_email ?? '—'}</td>
                                <td data-label={t.commerce_col_amount || 'Amount'} className="px-3 py-2">{money(t, card.original_amount, card.currency)}</td>
                                <td data-label={t.commerce_col_balance || 'Balance'} className="px-3 py-2">{card.balance_amount}</td>
                                <td data-label={t.commerce_col_status || 'Status'} className="px-3 py-2">
                                    {t[`commerce_card_status_${card.status}`] || card.status}
                                    {card.status === 'deactivated' && card.deactivated_reason && <span className="block text-xs text-gray-500" data-testid="deactivated-reason">{card.deactivated_reason}</span>}
                                </td>
                                <td data-label={t.commerce_col_source || 'Source'} className="px-3 py-2">{t[`commerce_source_${card.source}`] || card.source}</td>
                                <td data-label={t.commerce_col_expires || 'Expires'} className="px-3 py-2">{card.expires_at ?? '—'}</td>
                                <td className="table-actions px-3 py-2">
                                    {/* B10 (§15.2): a leaked code or a disputed purchase; the reason goes on the ledger. */}
                                    {['active', 'partially_used'].includes(card.status) && (
                                        <button
                                            type="button"
                                            className="text-sm text-red-600"
                                            data-testid="deactivate-gift-card"
                                            onClick={() => {
                                                const reason = window.prompt(t.commerce_deactivate_prompt || 'Why is this card being deactivated? The reason is kept on the card\'s history.');
                                                if (reason && reason.trim()) refusals.actOn(`card:${card.id}`, () => router.post(`/admin/commerce/gift-cards/${card.id}/deactivate`, { reason: reason.trim() }, { preserveScroll: true }));
                                            }}
                                        >
                                            {t.commerce_deactivate || 'Deactivate'}
                                        </button>
                                    )}
                                    <FormErrors errors={refusals.errorsFor(`card:${card.id}`)} className="mt-1 text-start" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.commerce_col_discount_code || 'Discount code'}</th>
                            <th className="px-3 py-2">{t.commerce_col_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.commerce_value || 'Value'}</th>
                            <th className="px-3 py-2">{t.commerce_col_limits || 'Limits'}</th>
                            <th className="px-3 py-2">{t.commerce_col_status || 'Status'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {discount_codes.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.commerce_no_discounts || 'No discount codes.'}</td></tr>
                        )}
                        {discount_codes.map((code) => (
                            <tr key={code.id} className="border-t">
                                <td data-label={t.commerce_col_discount_code || 'Discount code'} className="px-3 py-2 font-mono">{code.code}</td>
                                <td data-label={t.commerce_col_type || 'Type'} className="px-3 py-2">{t[`commerce_type_${code.discount_type}`] || code.discount_type}</td>
                                <td data-label={t.commerce_value || 'Value'} className="px-3 py-2">{code.discount_value}</td>
                                <td data-label={t.commerce_col_limits || 'Limits'} className="px-3 py-2">{(t.commerce_limits || ':total / :per_user per user').replace(':total', code.usage_limit ?? '∞').replace(':per_user', code.per_user_limit ?? '∞')}</td>
                                <td data-label={t.commerce_col_status || 'Status'} className="px-3 py-2">{t[`commerce_discount_status_${code.status}`] || code.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
