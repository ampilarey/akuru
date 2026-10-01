import { useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * COMMERCE_PARITY_PLAN P7b: SMS offers to the customers who asked for them
 * at checkout. The cost shows before sending (people × messages each × the
 * rate) and a month cannot pass the budget; every message ends with its own
 * stop link. Counted the way SmsSegments.php counts.
 */
const GSM = '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
const GSM_EXTENDED = '^{}\\[~]|€';

function segments(text) {
    const chars = Array.from(text);
    if (chars.length === 0) return { count: 1, unicode: false, length: 0 };
    const unicode = chars.some((c) => !GSM.includes(c) && !GSM_EXTENDED.includes(c));
    if (unicode) return { count: chars.length <= 70 ? 1 : Math.ceil(chars.length / 67), unicode, length: chars.length };
    const length = chars.reduce((n, c) => n + (GSM_EXTENDED.includes(c) ? 2 : 1), 0);

    return { count: length <= 160 ? 1 : Math.ceil(length / 153), unicode, length };
}

const fill = (s, vars) => Object.entries(vars).reduce((out, [k, v]) => out.split(`:${k}`).join(String(v)), s || '');

function Settings({ summary, t }) {
    const form = useForm({ rate: summary.rate, budget: summary.budget });

    return (
        <form className="flex flex-wrap items-end gap-3" onSubmit={(e) => { e.preventDefault(); form.post('/admin/bookshop/campaigns/settings', { preserveScroll: true }); }}>
            <label className="text-sm">{t.campaign_rate}<input type="number" step="0.01" min="0" className="form-input w-28" value={form.data.rate} onChange={(e) => form.setData('rate', e.target.value)} data-testid="campaign-rate" /></label>
            <label className="text-sm">{t.campaign_budget}<input type="number" step="0.01" min="0" className="form-input w-32" value={form.data.budget} onChange={(e) => form.setData('budget', e.target.value)} data-testid="campaign-budget" /></label>
            <button type="submit" className="btn-secondary" disabled={form.processing} data-testid="campaign-save-settings">{t.campaign_save_settings}</button>
            {(form.errors.rate || form.errors.budget) && <span className="w-full text-xs text-red-700">{form.errors.rate || form.errors.budget}</span>}
        </form>
    );
}

export default function Campaigns({ t = {}, summary, campaigns = [] }) {
    const { errors } = usePage().props;
    const form = useForm({ audience: 'opted_in', vendor_id: '', message: '' });
    const shop = summary.shops.find((s) => String(s.id) === String(form.data.vendor_id));
    const people = form.data.audience === 'shop_buyers' ? (shop?.opted_in_buyers ?? 0) : summary.opted_in;
    const seg = segments(form.data.message + summary.suffix_sample);
    const cost = (people * seg.count * Number(summary.rate)).toFixed(2);
    const over = Number(cost) > Number(summary.left);
    const submit = (e) => {
        e.preventDefault();
        // The office confirms the number and the price it is about to spend.
        if (window.confirm(fill(t.campaign_confirm, { count: people, cost }))) {
            form.post('/admin/bookshop/campaigns', { preserveScroll: true, onSuccess: () => form.reset('message') });
        }
    };

    return (
        <AppShell title={t.campaigns_title}>
            <FormErrors errors={errors} className="mb-4" />
            <p className="mb-4 text-sm text-gray-600">{t.campaigns_intro}</p>

            <section className="mb-6 rounded-lg border bg-white p-4" data-testid="campaign-budget-box">
                <p className="mb-3 text-sm" data-testid="campaign-spent">{fill(t.campaign_spent, { spent: summary.spent, budget: summary.budget, left: summary.left })}</p>
                <Settings summary={summary} t={t} />
            </section>

            <section className="mb-6 rounded-lg border bg-white p-4">
                <form className="grid gap-3" onSubmit={submit}>
                    <fieldset className="grid gap-1 text-sm">
                        <legend className="font-medium">{t.campaign_audience}</legend>
                        <label className="flex items-center gap-2"><input type="radio" name="audience" value="opted_in" checked={form.data.audience === 'opted_in'} onChange={() => form.setData('audience', 'opted_in')} data-testid="audience-opted-in" /> {fill(t.campaign_audience_opted_in, { count: summary.opted_in })}</label>
                        <label className="flex flex-wrap items-center gap-2"><input type="radio" name="audience" value="shop_buyers" checked={form.data.audience === 'shop_buyers'} onChange={() => form.setData('audience', 'shop_buyers')} data-testid="audience-shop-buyers" /> {t.campaign_audience_shop_buyers}
                            <select className="form-input text-sm" value={form.data.vendor_id} onChange={(e) => form.setData((d) => ({ ...d, audience: 'shop_buyers', vendor_id: e.target.value }))} aria-label={t.campaign_shop} data-testid="campaign-shop">
                                <option value="">{t.campaign_shop}</option>
                                {summary.shops.map((s) => <option key={s.id} value={s.id}>{s.name} ({s.opted_in_buyers})</option>)}
                            </select>
                        </label>
                    </fieldset>
                    <label className="text-sm">{t.campaign_message}
                        <textarea className="form-input w-full" rows={4} maxLength={600} dir="auto" value={form.data.message} onChange={(e) => form.setData('message', e.target.value)} required data-testid="campaign-message" />
                    </label>
                    <p className="text-xs text-gray-600" data-testid="campaign-length">{fill(t.campaign_chars, { chars: seg.length, segments: seg.count })}{seg.unicode ? ` · ${t.campaign_unicode}` : ''}</p>
                    <p className={`text-sm font-medium ${over ? 'text-red-700' : ''}`} data-testid="campaign-cost">{fill(t.campaign_cost_line, { count: people, segments: seg.count, rate: summary.rate, cost })}</p>
                    {(form.errors.message || form.errors.audience || form.errors.vendor_id) && <p className="text-sm text-red-700" data-testid="campaign-error">{form.errors.message || form.errors.audience || form.errors.vendor_id}</p>}
                    <div><button type="submit" className="btn-primary" disabled={form.processing || people === 0 || over || form.data.message.trim() === ''} data-testid="campaign-send">{t.campaign_send}</button></div>
                </form>
            </section>

            <section>
                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-lg font-semibold">{t.campaign_history}</h2>
                    <a href="/admin/bookshop/campaigns/export" className="btn-secondary" data-testid="export-campaigns">{t.export_csv}</a>
                </div>
                {campaigns.length === 0 ? <p className="rounded border bg-white p-3 text-sm text-gray-600">{t.campaign_none}</p> : (
                    <ul className="divide-y rounded border bg-white" data-testid="campaigns">
                        {campaigns.map((c) => (
                            <li key={c.id} className="p-3 text-sm" data-testid={`campaign-${c.id}`} data-status={c.status}>
                                <p className="font-medium">{c.created_at} · {c.shop || fill(t.campaign_audience_opted_in, { count: c.recipients })} · {t[`campaign_status_${c.status}`] || c.status} · MVR {c.cost}</p>
                                <p className="whitespace-pre-line break-words text-gray-700" dir="auto">{c.message}</p>
                                <p className="text-xs text-gray-500">{fill(t.campaign_cost_line, { count: c.recipients, segments: c.segments, rate: (Number(c.cost) / Math.max(1, c.recipients * c.segments)).toFixed(2), cost: c.cost })} · {fill(t.campaign_delivered, { sent: c.sent, failed: c.failed })}</p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </AppShell>
    );
}
