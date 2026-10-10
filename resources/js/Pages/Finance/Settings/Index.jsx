import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * The three billing settings the S4.3 and S4.4 migrations seeded and no
 * screen could change (S4 audit D3). Each says what it moves. Every word is
 * the `finance` book's (slice FN1, STATUS §5qn).
 */
export default function Index({ settings, t = {} }) {
    const form = useForm({ ...settings });

    return (
        <AppShell title={t.settings_title || 'Finance settings'}>
            <p className="mb-4 text-sm text-gray-600">
                {t.settings_intro || 'How the school bills and chases. These change what every family is invoiced and sent, so each one says plainly what it does.'}
            </p>

            <form
                className="grid max-w-2xl gap-4 rounded-lg border bg-white p-4"
                onSubmit={(e) => { e.preventDefault(); form.put('/finance/settings', { preserveScroll: true }); }}
            >
                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">{t.settings_monthly || 'Monthly fees are invoiced'}</span>
                    <select className="form-input w-full" value={form.data.invoice_monthly_mode}
                        onChange={(e) => form.setData('invoice_monthly_mode', e.target.value)}>
                        <option value="per_month">{t.settings_mode_per_month || 'One invoice per month'}</option>
                        <option value="consolidated">{t.settings_mode_consolidated || 'One consolidated invoice for the period'}</option>
                    </select>
                    <span className="mt-1 block text-xs text-gray-600">
                        {t.settings_monthly_hint || 'The default the Invoices screen starts on; a generation run can still choose the other.'}
                    </span>
                    {form.errors.invoice_monthly_mode && (
                        <span className="mt-1 block text-xs text-red-600" role="alert">{form.errors.invoice_monthly_mode}</span>
                    )}
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">{t.settings_reminder || 'Days after the due date before a reminder'}</span>
                    <input type="number" min="0" max="90" className="form-input w-32"
                        value={form.data.invoice_reminder_days}
                        onChange={(e) => form.setData('invoice_reminder_days', e.target.value)} />
                    <span className="mt-1 block text-xs text-gray-600">
                        {t.settings_reminder_hint || 'The nightly reminder run sends one notice per unpaid invoice this many days past due, and not again for a week.'}
                        {' '}<strong>{t.settings_reminder_zero || '0 reminds on the due date itself.'}</strong>
                    </span>
                    {form.errors.invoice_reminder_days && (
                        <span className="mt-1 block text-xs text-red-600" role="alert">{form.errors.invoice_reminder_days}</span>
                    )}
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">{t.settings_default || 'Days an installment may be overdue before the plan is defaulted'}</span>
                    <input type="number" min="0" max="365" className="form-input w-32"
                        value={form.data.plan_default_days}
                        onChange={(e) => form.setData('plan_default_days', e.target.value)} />
                    <span className="mt-1 block text-xs text-gray-600">
                        {t.settings_default_hint || 'A defaulted plan is a flag to follow up, never a lockout: later payments still complete it.'}
                    </span>
                    {form.errors.plan_default_days && (
                        <span className="mt-1 block text-xs text-red-600" role="alert">{form.errors.plan_default_days}</span>
                    )}
                </label>

                <div>
                    <button type="submit" className="btn-primary" disabled={form.processing}>{t.settings_save || 'Save settings'}</button>
                </div>
            </form>
        </AppShell>
    );
}
