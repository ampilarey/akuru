import { useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * B12 (LIBRARY_PLAN §42): the Digital Library's knobs, in three blocks.
 * What is typed here is in force at once; a blank history means the
 * deploy's defaults, which each field shows beside it.
 */
function NumberField({ name, label, hint, form, settings, t }) {
    return (
        <label className="text-sm">
            <span className="mb-1 block font-medium">{label}</span>
            <input
                className="form-input"
                type="number"
                name={name}
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
            />
            <span className="mt-1 block text-xs text-gray-500">{hint}</span>
            <span className="mt-1 block text-xs text-gray-500">{t.library_settings_default || 'Default'}: {String(settings[name].default)}</span>
            {form.errors[name] && <span className="mt-1 block text-xs text-red-600">{form.errors[name]}</span>}
        </label>
    );
}

export default function Settings({ settings, t = {} }) {
    const form = useForm(Object.fromEntries(Object.entries(settings).map(([key, knob]) => [key, knob.value])));
    const money = [
        ['refund_window_days', t.library_settings_label_refund || 'Refund window', t.library_settings_hint_refund || 'Days after a purchase before a writer earning matures.'],
        ['default_writer_commission', t.library_settings_label_commission || 'Writer\'s share', t.library_settings_hint_commission || 'Percent of a sale.'],
        ['min_payout', t.library_settings_label_min_payout || 'Minimum payout', t.library_settings_hint_min_payout || 'In MVR.'],
        ['gift_card_min', t.library_settings_label_gift_min || 'Smallest gift card', t.library_settings_hint_gift_min || 'In MVR.'],
        ['gift_card_max', t.library_settings_label_gift_max || 'Largest gift card', t.library_settings_hint_gift_max || 'In MVR.'],
        ['gift_card_expiry_months', t.library_settings_label_gift_expiry || 'Gift card expiry', t.library_settings_hint_gift_expiry || 'Months after purchase. 0 means never.'],
    ];

    return (
        <AppShell title={t.library_settings_title || 'Digital Library settings'}>
            <p className="mb-4 text-sm text-gray-600">{t.library_settings_intro || 'Money, publishing and notices. Each field shows the deploy\'s default beside it; what you save here is in force at once.'}</p>
            <form
                onSubmit={(e) => { e.preventDefault(); form.put('/admin/library/settings', { preserveScroll: true }); }}
                className="grid gap-8 rounded-lg border bg-white p-4"
                data-testid="library-settings"
            >
                <fieldset className="grid gap-4 md:grid-cols-2" data-testid="library-settings-money">
                    <legend className="mb-2 text-base font-semibold md:col-span-2">{t.library_settings_money || 'Money'}</legend>
                    {money.map(([key, label, hint]) => (
                        <NumberField key={key} name={key} label={label} hint={hint} form={form} settings={settings} t={t} />
                    ))}
                    <label className="flex items-start gap-2 text-sm md:col-span-2">
                        <input type="checkbox" name="payouts_enabled" checked={Boolean(form.data.payouts_enabled)} onChange={(e) => form.setData('payouts_enabled', e.target.checked)} data-testid="library-setting-payouts_enabled" />
                        <span>
                            {t.library_settings_payouts_enabled || 'Writers may request payouts'}
                            <span className="ms-2 text-xs text-gray-500">({t.library_settings_default || 'Default'}: {settings.payouts_enabled.default ? t.library_settings_on || 'on' : t.library_settings_off || 'off'})</span>
                        </span>
                    </label>
                    <p className="text-xs text-amber-800 md:col-span-2">{t.library_settings_payouts_note || 'Payouts stay off until the tax and accounting treatment of writer payouts is confirmed; earnings accrue meanwhile.'}</p>
                </fieldset>

                <fieldset className="grid gap-4 md:grid-cols-2" data-testid="library-settings-publishing">
                    <legend className="mb-2 text-base font-semibold md:col-span-2">{t.library_settings_publishing || 'Publishing'}</legend>
                    <NumberField
                        name="research_reviews_required"
                        label={t.library_settings_label_reviews || 'Research reviews'}
                        hint={t.library_settings_hint_reviews || 'How many reviewer accepts a research item needs before it can be published. At least 1.'}
                        form={form}
                        settings={settings}
                        t={t}
                    />
                </fieldset>

                <fieldset className="grid gap-4 md:grid-cols-2" data-testid="library-settings-notices">
                    <legend className="mb-2 text-base font-semibold md:col-span-2">{t.library_settings_notices || 'Notices'}</legend>
                    {[['notices_email', t.library_settings_notices_email || 'Send the important notices by email too'], ['notices_sms', t.library_settings_notices_sms || 'Send the important notices by SMS too']].map(([key, label]) => (
                        <label key={key} className="flex items-start gap-2 text-sm md:col-span-2">
                            <input type="checkbox" name={key} checked={Boolean(form.data[key])} onChange={(e) => form.setData(key, e.target.checked)} data-testid={`library-setting-${key}`} />
                            <span>
                                {label}
                                <span className="ms-2 text-xs text-gray-500">({t.library_settings_default || 'Default'}: {settings[key].default ? t.library_settings_on || 'on' : t.library_settings_off || 'off'})</span>
                            </span>
                        </label>
                    ))}
                    <p className="text-xs text-gray-600 md:col-span-2" data-testid="library-notices-note">{t.library_settings_notices_note || 'Important notices: a decision on an application or submission, a publication, a sale, a payout, a review asked for, a purchase ready. Reader reminders and office alerts stay in the app.'}</p>
                    {[['office_email', 'email', t.library_settings_label_office_email || 'Office email'], ['office_phone', 'tel', t.library_settings_label_office_phone || 'Office phone']].map(([key, type, label]) => (
                        <label key={key} className="text-sm">
                            <span className="mb-1 block font-medium">{label}</span>
                            <input className="form-input w-full" type={type} name={key} value={form.data[key] || ''} onChange={(e) => form.setData(key, e.target.value)} data-testid={`library-setting-${key}`} />
                            {form.errors[key] && <span className="mt-1 block text-xs text-red-600">{form.errors[key]}</span>}
                        </label>
                    ))}
                    <p className="text-xs text-gray-600 md:col-span-2">{t.library_settings_office_note || 'Every sale reaches this address and number, as the two switches above allow. Leave empty to keep sales in the app.'}</p>
                </fieldset>

                {/* BLOG_PLAN BL6a: the blogs' tip box. */}
                <fieldset className="grid gap-4 md:grid-cols-2" data-testid="library-settings-tips">
                    <legend className="mb-2 text-base font-semibold md:col-span-2">{t.library_settings_tips || 'Tips to blog writers'}</legend>
                    <label className="text-sm">
                        <span className="mb-1 block font-medium">{t.library_settings_label_tip_amounts || 'Tip amounts (MVR, comma between)'}</span>
                        <input className="form-input w-full" name="tip_amounts" value={form.data.tip_amounts || ''} onChange={(e) => form.setData('tip_amounts', e.target.value)} data-testid="library-setting-tip_amounts" />
                        <span className="mt-1 block text-xs text-gray-500">{t.library_settings_default || 'Default'}: {String(settings.tip_amounts?.default ?? '')}</span>
                        {form.errors.tip_amounts && <span className="mt-1 block text-xs text-red-600">{form.errors.tip_amounts}</span>}
                    </label>
                    <NumberField name="tip_share_percent" label={t.library_settings_label_tip_share || 'Akuru’s share of a tip'} hint={t.library_settings_hint_tip_share || 'Percent kept by Akuru; the rest is the writer’s earning.'} form={form} settings={settings} t={t} />
                </fieldset>

                <FormErrors errors={form.errors} />
                <div className="flex items-center gap-3">
                    <button type="submit" className="btn-primary" disabled={form.processing}>{t.library_settings_save || 'Save settings'}</button>
                    <a href="/admin/library" className="text-sm underline">{t.library_settings_back || 'Back to the Library office'}</a>
                </div>
            </form>
        </AppShell>
    );
}
