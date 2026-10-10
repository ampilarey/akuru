import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * The five HR and payroll settings that were read on every request and
 * editable from no screen (S5 audit D3). Two forms: the HR policy needs
 * `hr.manage`; the payroll rules and switch need `payroll.approve`, because
 * a rule change moves every net figure the next run produces. Every word is
 * the `hr` book's (slice HR1, STATUS §5ql); a checklist's items are the
 * school's, written one per line.
 */
export default function Index({ hr, payroll, canApprovePayroll, t = {} }) {
    const hrForm = useForm({
        staff_self_checkin: hr.staff_self_checkin,
        onboarding_items: hr.onboarding.join('\n'),
        offboarding_items: hr.offboarding.join('\n'),
    });

    const payrollForm = useForm({
        enabled: payroll.setting_on,
        employee_pension_rate: payroll.rules.employee_pension_rate ?? 0.07,
        employer_pension_rate: payroll.rules.employer_pension_rate ?? 0.07,
        working_days: payroll.rules.working_days ?? 22,
        tax_brackets: (payroll.rules.tax_brackets ?? []).map((b) => ({ up_to: b.up_to ?? '', rate: b.rate })),
    });

    const setBracket = (i, key, value) => {
        const next = payrollForm.data.tax_brackets.map((b, j) => (j === i ? { ...b, [key]: value } : b));
        payrollForm.setData('tax_brackets', next);
    };

    const Error = ({ name, form }) => (form.errors[name] ? <span className="mt-1 block text-xs text-red-600">{form.errors[name]}</span> : null);
    const now = (t.settings_payroll_now || 'Right now the environment flag is :flag, so payroll is :state.')
        .replace(':flag', payroll.environment_on ? (t.settings_flag_on || 'on') : (t.settings_flag_off || 'off'))
        .replace(':state', payroll.enabled ? (t.settings_payroll_enabled || 'enabled') : (t.settings_payroll_disabled || 'disabled'));

    return (
        <AppShell title={t.settings_title || 'HR settings'}>
            <p className="mb-4 text-sm text-gray-600">
                {t.settings_intro || 'How the school runs its staff. Each setting says what it moves.'}
            </p>

            <form
                className="mb-6 grid max-w-2xl gap-4 rounded-lg border bg-white p-4"
                onSubmit={(e) => { e.preventDefault(); hrForm.put('/hr/settings', { preserveScroll: true }); }}
            >
                <h2 className="font-semibold">{t.settings_staff_checklists || 'Staff and checklists'}</h2>

                <label className="flex items-start gap-2 text-sm">
                    <input type="checkbox" className="mt-1" checked={hrForm.data.staff_self_checkin}
                        onChange={(e) => hrForm.setData('staff_self_checkin', e.target.checked)} />
                    <span>
                        <span className="block font-semibold">{t.settings_self_checkin || 'Staff may check themselves in from the portal'}</span>
                        <span className="block text-xs text-gray-600">
                            {t.settings_self_checkin_hint || 'Off, the Check in button is hidden and the office marks every day by hand.'}
                        </span>
                    </span>
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">{t.settings_onboarding || 'Onboarding checklist, one item per line'}</span>
                    <textarea className="form-input w-full" rows={5} value={hrForm.data.onboarding_items}
                        onChange={(e) => hrForm.setData('onboarding_items', e.target.value)} />
                    <span className="mt-1 block text-xs text-gray-600">
                        {t.settings_onboarding_hint || 'Seeded for every new hire the moment they are hired. Changing it does not touch checklists already open.'}
                    </span>
                    <Error name="onboarding_items" form={hrForm} />
                </label>

                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">{t.settings_offboarding || 'Offboarding checklist, one item per line'}</span>
                    <textarea className="form-input w-full" rows={4} value={hrForm.data.offboarding_items}
                        onChange={(e) => hrForm.setData('offboarding_items', e.target.value)} />
                    <Error name="offboarding_items" form={hrForm} />
                </label>

                <div>
                    <button type="submit" className="btn-primary" disabled={hrForm.processing}>{t.settings_save_hr || 'Save HR settings'}</button>
                </div>
            </form>

            <form
                className="grid max-w-2xl gap-4 rounded-lg border bg-white p-4"
                onSubmit={(e) => { e.preventDefault(); payrollForm.put('/hr/settings/payroll', { preserveScroll: true }); }}
            >
                <h2 className="font-semibold">{t.payroll_title || 'Payroll'}</h2>

                <div className="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs">
                    <p>{t.settings_payroll_both || 'Payroll runs only when both halves are on: this switch, and the host’s flag below, which stays off until two parallel cycles match the manual process.'}</p>
                    <p className="mt-1" dir="ltr"><code>{'PAYROLL_ENABLED'}</code></p>
                    <p className="mt-1 font-semibold">{now}</p>
                </div>

                {!canApprovePayroll && (
                    <p className="text-xs text-gray-600">{t.settings_needs_approver || 'Saving this section needs the payroll approver’s permission.'}</p>
                )}

                <label className="flex items-start gap-2 text-sm">
                    <input type="checkbox" className="mt-1" checked={payrollForm.data.enabled}
                        onChange={(e) => payrollForm.setData('enabled', e.target.checked)} disabled={!canApprovePayroll} />
                    <span className="font-semibold">{t.settings_payroll_switch || 'Payroll switch (the settings half)'}</span>
                </label>

                <div className="grid grid-cols-3 gap-3">
                    <label className="block text-sm">
                        <span className="mb-1 block font-semibold">{t.settings_employee_pension || 'Employee pension rate'}</span>
                        <input type="number" step="0.0001" min="0" max="0.5" className="form-input w-full"
                            value={payrollForm.data.employee_pension_rate} disabled={!canApprovePayroll}
                            onChange={(e) => payrollForm.setData('employee_pension_rate', e.target.value)} />
                        <Error name="employee_pension_rate" form={payrollForm} />
                    </label>
                    <label className="block text-sm">
                        <span className="mb-1 block font-semibold">{t.settings_employer_pension || 'Employer pension rate'}</span>
                        <input type="number" step="0.0001" min="0" max="0.5" className="form-input w-full"
                            value={payrollForm.data.employer_pension_rate} disabled={!canApprovePayroll}
                            onChange={(e) => payrollForm.setData('employer_pension_rate', e.target.value)} />
                        <Error name="employer_pension_rate" form={payrollForm} />
                    </label>
                    <label className="block text-sm">
                        <span className="mb-1 block font-semibold">{t.settings_working_days || 'Working days a month'}</span>
                        <input type="number" min="1" max="31" className="form-input w-full"
                            value={payrollForm.data.working_days} disabled={!canApprovePayroll}
                            onChange={(e) => payrollForm.setData('working_days', e.target.value)} />
                        <span className="mt-1 block text-xs text-gray-600">{t.settings_working_days_hint || 'An unpaid day deducts the basic salary divided by this.'}</span>
                        <Error name="working_days" form={payrollForm} />
                    </label>
                </div>

                <div className="text-sm">
                    <span className="mb-1 block font-semibold">{t.settings_tax_brackets || 'Tax brackets (monthly gross ceiling → rate)'}</span>
                    <span className="mb-2 block text-xs text-gray-600">
                        {t.settings_tax_hint || 'Ceilings rise from one bracket to the next; the last has no ceiling. Rounding is half-up to two places.'}
                    </span>
                    {payrollForm.data.tax_brackets.map((b, i) => (
                        <div key={i} className="mb-2 flex items-center gap-2">
                            <input type="number" min="0" className="form-input w-40"
                                aria-label={(t.settings_ceiling_n || 'Ceiling of bracket :n').replace(':n', i + 1)}
                                placeholder={i === payrollForm.data.tax_brackets.length - 1 ? (t.settings_no_ceiling || 'no ceiling') : (t.settings_up_to || 'up to')}
                                value={b.up_to} disabled={!canApprovePayroll || i === payrollForm.data.tax_brackets.length - 1}
                                onChange={(e) => setBracket(i, 'up_to', e.target.value)} />
                            <input type="number" step="0.0001" min="0" max="1" className="form-input w-28"
                                aria-label={(t.settings_rate_n || 'Rate of bracket :n').replace(':n', i + 1)}
                                value={b.rate} disabled={!canApprovePayroll}
                                onChange={(e) => setBracket(i, 'rate', e.target.value)} />
                            {canApprovePayroll && payrollForm.data.tax_brackets.length > 1 && (
                                <button type="button" className="text-xs text-red-700 underline"
                                    onClick={() => payrollForm.setData('tax_brackets', payrollForm.data.tax_brackets.filter((_, j) => j !== i))}>
                                    {t.settings_remove || 'remove'}
                                </button>
                            )}
                        </div>
                    ))}
                    {canApprovePayroll && (
                        <button type="button" className="btn-secondary text-xs"
                            onClick={() => {
                                const last = payrollForm.data.tax_brackets[payrollForm.data.tax_brackets.length - 1];
                                const rest = payrollForm.data.tax_brackets.slice(0, -1);
                                payrollForm.setData('tax_brackets', [...rest, { up_to: '', rate: last?.rate ?? 0 }, { up_to: '', rate: last?.rate ?? 0 }]);
                            }}>
                            {t.settings_add_bracket || 'Add bracket'}
                        </button>
                    )}
                    <Error name="tax_brackets" form={payrollForm} />
                </div>

                <div>
                    <button type="submit" className="btn-primary" disabled={payrollForm.processing || !canApprovePayroll}>{t.settings_save_payroll || 'Save payroll settings'}</button>
                </div>
            </form>
        </AppShell>
    );
}
