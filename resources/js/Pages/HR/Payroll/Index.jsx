import { Link, router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';

/**
 * A payroll period's draft payslips, and approving, paying and locking it.
 * Every word is the `hr` book's (slice HR1, STATUS §5ql); a payslip's state
 * is named rather than printed as its code. A refused run is said under its
 * form, and a refused Approve, Mark paid or Lock beside the buttons — they
 * were said nowhere.
 */
export default function Index({ enabled, periods, periodId, rows, canApprove, t = {} }) {
    const run = useForm({
        year: new Date().getFullYear(),
        month: new Date().getMonth() + 1,
    });
    const refusals = useRowRefusals(run);
    const statusName = (status) => t[`payslip_status_${status}`] || status;

    return (
        <AppShell title={t.payroll_title || 'Payroll'}>
            {!enabled && (
                <p className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm">
                    {t.payroll_disabled || 'Payroll is disabled until two parallel cycles match.'}{' '}
                    <Link href="/hr/settings" className="underline">{t.payroll_where || 'The switch and the rules are in HR settings; the environment flag is the owner’s.'}</Link>
                </p>
            )}
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    run.post('/hr/payroll/run');
                }}
                className="mb-4 flex flex-wrap gap-3 rounded-lg border bg-white p-4"
            >
                <input className="form-input w-28" aria-label={t.year || 'Year'} value={run.data.year} onChange={(e) => run.setData('year', e.target.value)} />
                <input className="form-input w-20" aria-label={t.payroll_month || 'Month'} value={run.data.month} onChange={(e) => run.setData('month', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={run.processing || !enabled}>{t.payroll_run || 'Run payroll'}</button>
                <FormErrors errors={run.errors} className="w-full" />
            </form>
            {periodId && (
                <div className="mb-4 flex flex-wrap gap-3">
                    {canApprove && (
                        <>
                            <button type="button" className="btn-secondary" onClick={() => refusals.actOn('period', () => router.post(`/hr/payroll/${periodId}/approve`))}>{t.payroll_approve || 'Approve'}</button>
                            <button type="button" className="btn-secondary" onClick={() => refusals.actOn('period', () => router.post(`/hr/payroll/${periodId}/pay`))}>{t.payroll_mark_paid || 'Mark paid'}</button>
                            <button type="button" className="btn-secondary" onClick={() => refusals.actOn('period', () => router.post(`/hr/payroll/${periodId}/lock`))}>{t.payroll_lock || 'Lock'}</button>
                        </>
                    )}
                    <a className="btn-secondary" href={`/hr/payroll/${periodId}/export`}>{t.payroll_bank_csv || 'Bank CSV'}</a>
                    <FormErrors errors={{ ...refusals.unplaced, ...refusals.errorsFor('period') }} className="w-full" />
                </div>
            )}
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.staff || 'Staff'}</th>
                            <th className="px-3 py-2">{t.payroll_col_gross || 'Gross'}</th>
                            <th className="px-3 py-2">{t.payroll_col_net || 'Net'}</th>
                            <th className="px-3 py-2">{t.payroll_col_previous || 'Previous'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.payroll_none || 'No payslips for this period.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.staff_name}</td>
                                <td className="px-3 py-2">{row.gross}</td>
                                <td className="px-3 py-2">{row.net_pay}</td>
                                <td className="px-3 py-2">{row.previous_net || '—'}</td>
                                <td className="px-3 py-2">{statusName(row.status)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <p className="mt-3 text-xs text-gray-500">{(t.payroll_periods || 'Periods on file: :count').replace(':count', periods.length)}</p>
        </AppShell>
    );
}
