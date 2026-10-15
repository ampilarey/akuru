import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * The Bookstore's switches on their own page: notices, cash on delivery,
 * rewards, referrals, and whether the shop is open. Each still posts to the
 * route it always posted to; those routes come back with back().
 */
function CodSwitch({ on, t }) {
    return (
        <section data-testid="office-cod">
            <h2 className="mb-1 text-lg font-semibold">{t.cod_label}</h2>
            <p className="mb-2 text-sm text-gray-600">{t.office_cod_hint}</p>
            <div className="flex flex-wrap items-center gap-3 rounded border bg-white p-3 text-sm">
                <span data-testid="cod-state">{on ? t.cod_is_on : t.cod_is_off}</span>
                <button type="button" className="btn-secondary" onClick={() => router.post('/admin/bookshop/cod', { on: on ? 0 : 1 }, { preserveScroll: true })} data-testid="toggle-cod">{on ? t.cod_turn_off : t.cod_turn_on}</button>
            </div>
        </section>
    );
}

function Rewards({ rewards, t }) {
    const s = rewards.settings;
    const form = useForm({ on: s.on ? 1 : 0, percent: String(s.percent), min_order: String(s.min_order), max_per_order: String(s.max_per_order) });
    const save = (on) => {
        form.transform((data) => ({ ...data, on }));
        form.post('/admin/bookshop/rewards', { preserveScroll: true });
    };

    return (
        <section data-testid="office-rewards">
            <h2 className="mb-1 text-lg font-semibold">{t.rewards_label}</h2>
            <p className="mb-2 max-w-3xl text-sm text-gray-600">{t.rewards_hint}</p>
            <div className="space-y-3 rounded border bg-white p-3 text-sm">
                <p data-testid="rewards-state">
                    <span className={`rounded px-2 py-0.5 font-semibold ${s.on ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'}`}>{s.on ? t.rewards_is_on : t.rewards_is_off}</span>
                    {s.on && s.since && <>{' '}<span className="ms-1 text-gray-600">{(t.rewards_since || '').replace(':date', s.since.slice(0, 10))}</span></>}
                </p>
                <div className="flex flex-wrap items-end gap-3">
                    <label>{t.rewards_percent}
                        <input type="number" step="0.1" min="0.1" max={rewards.max_percent} className="form-input mt-1 block w-24" value={form.data.percent} onChange={(e) => form.setData('percent', e.target.value)} data-testid="rewards-percent" />
                    </label>
                    <label>{t.rewards_min_order}
                        <input type="number" step="1" min="0" className="form-input mt-1 block w-28" value={form.data.min_order} onChange={(e) => form.setData('min_order', e.target.value)} data-testid="rewards-min" />
                    </label>
                    <label>{t.rewards_max}
                        <input type="number" step="1" min="1" className="form-input mt-1 block w-28" value={form.data.max_per_order} onChange={(e) => form.setData('max_per_order', e.target.value)} data-testid="rewards-max" />
                    </label>
                    <button type="button" className="btn-primary" disabled={form.processing} onClick={() => save(s.on ? 1 : 0)} data-testid="rewards-save">{t.save}</button>
                    <button type="button" className="btn-secondary" disabled={form.processing} onClick={() => save(s.on ? 0 : 1)} data-testid="toggle-rewards">{s.on ? t.rewards_turn_off : t.rewards_turn_on}</button>
                </div>
                {['percent', 'min_order', 'max_per_order'].map((k) => form.errors[k] && <p key={k} className="text-red-700">{form.errors[k]}</p>)}
                <p className="text-gray-600" data-testid="rewards-paid">
                    {(t.rewards_paid || '').replace(':count', rewards.paid_count).replace(':total', rewards.paid_total)}
                    {' '}<a href="/admin/bookshop/rewards/export" className="underline" data-testid="rewards-export">{t.export_csv}</a>
                </p>
                {rewards.latest.length > 0 && (
                    <table className="table-stack w-full text-start text-xs">
                        <thead><tr className="text-gray-500"><th className="text-start">{t.date}</th><th className="text-start">{t.rewards_col_order}</th><th className="text-end">%</th><th className="text-end">MVR</th></tr></thead>
                        <tbody>{rewards.latest.map((r) => <tr key={`${r.order}-${r.at}`} className="border-t"><td data-label={t.date}>{r.at}</td><td dir="ltr" data-label={t.rewards_col_order}>{r.order}</td><td className="text-end" data-label="%">{r.percent}</td><td className="text-end" data-label="MVR">{r.amount}</td></tr>)}</tbody>
                    </table>
                )}
            </div>
        </section>
    );
}

function Referrals({ referrals, t }) {
    const s = referrals.settings;
    const form = useForm({ on: s.on ? 1 : 0, referrer_amount: String(s.referrer_amount), friend_amount: String(s.friend_amount), min_order: String(s.min_order) });
    const save = (on) => {
        form.transform((data) => ({ ...data, on }));
        form.post('/admin/bookshop/referrals', { preserveScroll: true });
    };

    return (
        <section data-testid="office-referrals">
            <h2 className="mb-1 text-lg font-semibold">{t.referrals_label}</h2>
            <p className="mb-2 max-w-3xl text-sm text-gray-600">{t.referrals_hint}</p>
            <div className="space-y-3 rounded border bg-white p-3 text-sm">
                <p data-testid="referrals-state">
                    <span className={`rounded px-2 py-0.5 font-semibold ${s.on ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'}`}>{s.on ? t.rewards_is_on : t.rewards_is_off}</span>
                    {s.on && s.since && <>{' '}<span className="ms-1 text-gray-600">{(t.referrals_since || '').replace(':date', s.since.slice(0, 10))}</span></>}
                </p>
                <div className="flex flex-wrap items-end gap-3">
                    <label>{t.referrals_referrer_amount}
                        <input type="number" step="1" min="1" max={referrals.max_amount} className="form-input mt-1 block w-28" value={form.data.referrer_amount} onChange={(e) => form.setData('referrer_amount', e.target.value)} data-testid="referrals-referrer" />
                    </label>
                    <label>{t.referrals_friend_amount}
                        <input type="number" step="1" min="1" max={referrals.max_amount} className="form-input mt-1 block w-28" value={form.data.friend_amount} onChange={(e) => form.setData('friend_amount', e.target.value)} data-testid="referrals-friend" />
                    </label>
                    <label>{t.referrals_min_order}
                        <input type="number" step="1" min="0" className="form-input mt-1 block w-28" value={form.data.min_order} onChange={(e) => form.setData('min_order', e.target.value)} data-testid="referrals-min" />
                    </label>
                    <button type="button" className="btn-primary" disabled={form.processing} onClick={() => save(s.on ? 1 : 0)} data-testid="referrals-save">{t.save}</button>
                    <button type="button" className="btn-secondary" disabled={form.processing} onClick={() => save(s.on ? 0 : 1)} data-testid="toggle-referrals">{s.on ? t.referrals_turn_off : t.referrals_turn_on}</button>
                </div>
                {['referrer_amount', 'friend_amount', 'min_order'].map((k) => form.errors[k] && <p key={k} className="text-red-700">{form.errors[k]}</p>)}
                <p className="text-gray-600" data-testid="referrals-paid">
                    {(t.referrals_paid || '').replace(':count', referrals.paid_count).replace(':pending', referrals.pending_count).replace(':total', referrals.paid_total)}
                    {' '}<a href="/admin/bookshop/referrals/export" className="underline" data-testid="referrals-export">{t.export_csv}</a>
                </p>
                {referrals.latest.length > 0 && (
                    <table className="table-stack w-full text-start text-xs">
                        <thead><tr className="text-gray-500"><th className="text-start">{t.date}</th><th className="text-start">{t.referrals_col_checkout}</th><th className="text-start">{t.referrals_col_status}</th><th className="text-end">MVR</th></tr></thead>
                        <tbody>{referrals.latest.map((r) => <tr key={`${r.checkout}-${r.at}`} className="border-t"><td data-label={t.date}>{r.at}</td><td dir="ltr" data-label={t.referrals_col_checkout}>{r.checkout}</td><td data-label={t.referrals_col_status}>{t[`referral_status_${r.status}`] || r.status}</td><td className="text-end" data-label="MVR">{r.referrer_amount !== null ? `${r.referrer_amount} + ${r.friend_amount}` : '—'}</td></tr>)}</tbody>
                    </table>
                )}
            </div>
        </section>
    );
}

function ShopOpenSwitch({ shopOpen, t }) {
    const [message, setMessage] = useState(shopOpen.message || '');
    const save = (open) => router.post('/admin/bookshop/open', { open: open ? 1 : 0, message }, { preserveScroll: true });

    return (
        <section data-testid="office-shop-open">
            <h2 className="mb-1 text-lg font-semibold">{t.shop_open_label}</h2>
            <p className="mb-2 text-sm text-gray-600">{t.office_shop_open_hint}</p>
            <form className="flex flex-wrap items-end gap-3 rounded border bg-white p-3 text-sm" onSubmit={(e) => { e.preventDefault(); save(shopOpen.open); }}>
                <span className={`rounded px-2 py-1 font-semibold ${shopOpen.open ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'}`} data-testid="shop-open-state">{shopOpen.open ? t.shop_is_open : t.shop_is_closed}</span>
                <label className="block grow">
                    <span className="mb-1 block text-xs text-gray-500">{t.shop_closed_message}</span>
                    <input className="form-input w-full" maxLength={500} value={message} onChange={(e) => setMessage(e.target.value)} placeholder={t.closed_default} aria-label={t.closed_default} data-testid="shop-closed-message" />
                </label>
                <button type="submit" className="text-sm text-blue-700 underline" data-testid="save-closed-message">{t.save}</button>
                <button type="button" className="btn-secondary" onClick={() => save(!shopOpen.open)} data-testid="toggle-shop-open">{shopOpen.open ? t.close_shop : t.open_shop}</button>
            </form>
        </section>
    );
}

function NoticeSwitches({ notices, t }) {
    const form = useForm({ ...notices });

    return (
        <section data-testid="office-notices">
            <h2 className="mb-1 text-lg font-semibold">{t.notices_heading}</h2>
            <p className="mb-2 text-sm text-gray-600">{t.office_notices_hint}</p>
            <form className="flex flex-wrap items-center gap-4 rounded border bg-white p-3 text-sm" onSubmit={(e) => { e.preventDefault(); form.post('/admin/bookshop/notices', { preserveScroll: true }); }}>
                {['customer_email', 'customer_sms', 'vendor_email', 'vendor_sms', 'office_email', 'office_sms'].map((k) => (
                    <label key={k} className="flex items-center gap-2"><input type="checkbox" checked={!!form.data[k]} onChange={(e) => form.setData(k, e.target.checked)} data-testid={`switch-${k}`} /> {t[`switch_${k}`]}</label>
                ))}
                <label className="flex w-full flex-wrap items-center gap-2 sm:w-auto">{t.office_contact_email}
                    <input type="email" className="form-input w-64" value={form.data.office_contact_email || ''} onChange={(e) => form.setData('office_contact_email', e.target.value)} data-testid="office-contact-email" />
                    {form.errors.office_contact_email && <span className="text-xs text-red-700">{form.errors.office_contact_email}</span>}
                </label>
                <label className="flex w-full flex-wrap items-center gap-2 sm:w-auto">{t.office_contact_phone}
                    <input type="tel" className="form-input w-40" value={form.data.office_contact_phone || ''} onChange={(e) => form.setData('office_contact_phone', e.target.value)} data-testid="office-contact-phone" />
                    {form.errors.office_contact_phone && <span className="text-xs text-red-700">{form.errors.office_contact_phone}</span>}
                </label>
                <p className="w-full text-xs text-gray-600">{t.office_contact_hint}</p>
                <button type="submit" className="btn-primary" disabled={form.processing} data-testid="save-switches">{t.save}</button>
            </form>
        </section>
    );
}

export default function Settings({ t, notices = null, cod_on = true, rewards = null, referrals = null, shop_open = { open: true, message: null } }) {
    const { errors } = usePage().props;

    return (
        <AppShell title={t.settings_heading}>
            <FormErrors errors={errors} className="mb-4" />
            <p className="mb-6 text-sm text-gray-600">{t.settings_hint}</p>
            <div className="space-y-8" data-testid="bookshop-settings">
                {notices && <NoticeSwitches key={JSON.stringify(notices)} notices={notices} t={t} />}
                <CodSwitch on={cod_on} t={t} />
                {rewards && <Rewards key={JSON.stringify(rewards.settings)} rewards={rewards} t={t} />}
                {referrals && <Referrals key={JSON.stringify(referrals.settings)} referrals={referrals} t={t} />}
                <ShopOpenSwitch shopOpen={shop_open} t={t} />
            </div>
            <p className="mt-8"><a href="/admin/bookshop" className="text-sm underline">{t.settings_back || 'Back to the bookstore office'}</a></p>
        </AppShell>
    );
}
