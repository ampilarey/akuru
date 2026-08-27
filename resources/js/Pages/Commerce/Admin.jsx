import { useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

function GiftCardForm() {
    const flash = usePage().props.flash || {};
    const form = useForm({ amount: '', recipient_name: '', recipient_email: '', message: '', expires_at: '' });

    return (
        <div className="mb-6 rounded-lg border bg-white p-4">
            <h2 className="mb-2 text-lg font-semibold">Issue gift card</h2>
            {flash.gift_card_code && (
                <p className="mb-3 rounded bg-amber-50 p-3 font-mono text-lg">
                    {flash.gift_card_code}
                    <span className="ml-2 text-sm font-sans text-amber-800">Copy it now — it is shown only once.</span>
                </p>
            )}
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/admin/commerce/gift-cards', { preserveScroll: true, onSuccess: () => form.reset() });
                }}
                className="grid gap-2 md:grid-cols-5"
            >
                <input className="form-input" type="number" step="0.01" min="1" placeholder="Amount (MVR)" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                <input className="form-input" placeholder="Recipient name" value={form.data.recipient_name} onChange={(e) => form.setData('recipient_name', e.target.value)} />
                <input className="form-input" type="email" placeholder="Recipient email" value={form.data.recipient_email} onChange={(e) => form.setData('recipient_email', e.target.value)} />
                <input className="form-input" type="date" value={form.data.expires_at} onChange={(e) => form.setData('expires_at', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Issue</button>
            </form>
        </div>
    );
}

function CreditForm() {
    const form = useForm({ user_id: '', amount: '', description: '' });

    return (
        <div className="mb-6 rounded-lg border bg-white p-4">
            <h2 className="mb-2 text-lg font-semibold">Manual wallet credit</h2>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/admin/commerce/wallet-credits', { preserveScroll: true, onSuccess: () => form.reset() });
                }}
                className="grid gap-2 md:grid-cols-4"
            >
                <input className="form-input" type="number" placeholder="User ID" value={form.data.user_id} onChange={(e) => form.setData('user_id', e.target.value)} />
                <input className="form-input" type="number" step="0.01" min="0.01" placeholder="Amount (MVR)" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                <input className="form-input" placeholder="Reason" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Credit</button>
            </form>
        </div>
    );
}

function DiscountForm() {
    const form = useForm({
        code: '', name: '', discount_type: 'percentage', discount_value: '',
        max_discount_amount: '', usage_limit: '', per_user_limit: '', minimum_order_amount: '',
    });

    return (
        <div className="mb-6 rounded-lg border bg-white p-4">
            <h2 className="mb-2 text-lg font-semibold">New discount code</h2>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/admin/commerce/discount-codes', { preserveScroll: true, onSuccess: () => form.reset() });
                }}
                className="grid gap-2 md:grid-cols-4"
            >
                <input className="form-input" placeholder="CODE" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                <select className="form-input" value={form.data.discount_type} onChange={(e) => form.setData('discount_type', e.target.value)}>
                    <option value="percentage">percentage</option>
                    <option value="fixed">fixed</option>
                </select>
                <input className="form-input" type="number" step="0.01" min="0.01" placeholder="Value" value={form.data.discount_value} onChange={(e) => form.setData('discount_value', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Save</button>
                <input className="form-input" type="number" placeholder="Usage limit" value={form.data.usage_limit} onChange={(e) => form.setData('usage_limit', e.target.value)} />
                <input className="form-input" type="number" placeholder="Per-user limit" value={form.data.per_user_limit} onChange={(e) => form.setData('per_user_limit', e.target.value)} />
                <input className="form-input" type="number" step="0.01" placeholder="Min order" value={form.data.minimum_order_amount} onChange={(e) => form.setData('minimum_order_amount', e.target.value)} />
                <input className="form-input" placeholder="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
            </form>
        </div>
    );
}

export default function Admin({ gift_cards, discount_codes }) {
    return (
        <AppShell title="Commerce admin">
            <GiftCardForm />
            <CreditForm />
            <DiscountForm />

            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Gift card</th>
                            <th className="px-3 py-2">Recipient</th>
                            <th className="px-3 py-2">Amount</th>
                            <th className="px-3 py-2">Balance</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Expires</th>
                        </tr>
                    </thead>
                    <tbody>
                        {gift_cards.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>No gift cards issued.</td></tr>
                        )}
                        {gift_cards.map((card) => (
                            <tr key={card.id} className="border-t">
                                <td className="px-3 py-2">#{card.id}</td>
                                <td className="px-3 py-2">{card.recipient_name ?? card.recipient_email ?? '—'}</td>
                                <td className="px-3 py-2">{card.currency} {card.original_amount}</td>
                                <td className="px-3 py-2">{card.balance_amount}</td>
                                <td className="px-3 py-2">{card.status}</td>
                                <td className="px-3 py-2">{card.expires_at ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Discount code</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Value</th>
                            <th className="px-3 py-2">Limits</th>
                            <th className="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {discount_codes.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No discount codes.</td></tr>
                        )}
                        {discount_codes.map((code) => (
                            <tr key={code.id} className="border-t">
                                <td className="px-3 py-2 font-mono">{code.code}</td>
                                <td className="px-3 py-2">{code.discount_type}</td>
                                <td className="px-3 py-2">{code.discount_value}</td>
                                <td className="px-3 py-2">{code.usage_limit ?? '∞'} / {code.per_user_limit ?? '∞'} per user</td>
                                <td className="px-3 py-2">{code.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
