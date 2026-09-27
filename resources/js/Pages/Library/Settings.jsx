import { useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * B12 (LIBRARY_PLAN §42): the Digital Library's commercial knobs. What is
 * typed here is in force at once; a blank history means the deploy's
 * defaults, which each field shows beside it.
 */
export default function Settings({ settings, t = {} }) {
    const form = useForm(Object.fromEntries(Object.entries(settings).map(([key, knob]) => [key, knob.value])));
    const numbers = [
        ['refund_window_days', t.library_settings_refund_window || 'Refund window (days after purchase before a writer earning matures)'],
        ['default_writer_commission', t.library_settings_commission || 'Writer\'s share of a sale (%)'],
        ['min_payout', t.library_settings_min_payout || 'Minimum payout (MVR)'],
        ['gift_card_min', t.library_settings_gift_min || 'Smallest gift card (MVR)'],
        ['gift_card_max', t.library_settings_gift_max || 'Largest gift card (MVR)'],
    ];
    const switches = [
        ['research_review_required', t.library_settings_review_required || 'Research needs a peer reviewer\'s recommendation before it can be approved'],
        ['payouts_enabled', t.library_settings_payouts_enabled || 'Writers may request payouts'],
    ];

    return (
        <AppShell title={t.library_settings_title || 'Digital Library settings'}>
            <p className="mb-4 text-sm text-gray-600">{t.library_settings_intro || 'The money rules of the Digital Library. Each field shows the deploy\'s default beside it; what you save here is in force at once.'}</p>
            <form
                onSubmit={(e) => { e.preventDefault(); form.put('/admin/library/settings', { preserveScroll: true }); }}
                className="grid gap-4 rounded-lg border bg-white p-4 md:grid-cols-2"
                data-testid="library-settings"
            >
                {numbers.map(([key, label]) => (
                    <label key={key} className="text-sm">
                        <span className="mb-1 block font-medium">{label}</span>
                        <input
                            className="form-input"
                            type="number"
                            name={key}
                            value={form.data[key]}
                            onChange={(e) => form.setData(key, e.target.value)}
                        />
                        <span className="mt-1 block text-xs text-gray-500">{t.library_settings_default || 'Default'}: {String(settings[key].default)}</span>
                        {form.errors[key] && <span className="mt-1 block text-xs text-red-600">{form.errors[key]}</span>}
                    </label>
                ))}
                {switches.map(([key, label]) => (
                    <label key={key} className="flex items-start gap-2 text-sm md:col-span-2">
                        <input type="checkbox" name={key} checked={Boolean(form.data[key])} onChange={(e) => form.setData(key, e.target.checked)} />
                        <span>
                            {label}
                            <span className="ms-2 text-xs text-gray-500">({t.library_settings_default || 'Default'}: {settings[key].default ? 'on' : 'off'})</span>
                        </span>
                    </label>
                ))}
                <p className="text-xs text-amber-800 md:col-span-2">{t.library_settings_payouts_note || 'Payouts stay off until the tax and accounting treatment of writer payouts is confirmed (ROADMAP §9.4); earnings accrue meanwhile.'}</p>
                <FormErrors errors={form.errors} />
                <div className="flex items-center gap-3 md:col-span-2">
                    <button type="submit" className="btn-primary" disabled={form.processing}>{t.library_settings_save || 'Save settings'}</button>
                    <a href="/admin/library" className="text-sm underline">{t.library_settings_back || 'Back to the Library office'}</a>
                </div>
            </form>
        </AppShell>
    );
}
