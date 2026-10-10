import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

/**
 * Leave entitlements and their balances. Every word is the `hr` book's
 * (slice HR1, STATUS §5ql); a leave type reads by the name the school gave
 * it in the page's language.
 */
export default function Balances({ filters, years, staff, leaveTypes, rows, t = {} }) {
    const locale = usePage().props.locale || 'en';
    const named = (row) => ({ dv: row?.name_dhivehi, ar: row?.name_arabic }[locale]) || row?.name;
    const createForm = useForm({
        staff_profile_id: staff[0]?.id || '',
        leave_type_id: leaveTypes[0]?.id || '',
        academic_year_id: filters.academic_year_id || years[0]?.id || '',
    });
    const carryForm = useForm({
        from_year_id: filters.academic_year_id || years[0]?.id || '',
        to_year_id: '',
    });

    return (
        <AppShell title={t.balances_title || 'Leave balances'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        const data = new FormData(e.currentTarget);
                        router.get('/hr/leave-balances', { academic_year_id: data.get('academic_year_id') });
                    }}
                    className="flex flex-wrap gap-3"
                >
                    <select name="academic_year_id" className="form-input" aria-label={t.year || 'Year'} defaultValue={filters.academic_year_id || ''}>
                        <option value="">{t.all_years || 'All years'}</option>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                    <button type="submit" className="btn-secondary">{t.filter || 'Filter'}</button>
                </form>
                <a className="btn-secondary" href={`/hr/leave-balances/export?academic_year_id=${filters.academic_year_id || ''}`}>{t.export_csv || 'Export CSV'}</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    createForm.post('/hr/leave-balances', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" aria-label={t.staff_member || 'Staff member'} value={createForm.data.staff_profile_id} onChange={(e) => createForm.setData('staff_profile_id', e.target.value)}>
                    {staff.map((row) => <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>)}
                </select>
                <select className="form-input" aria-label={t.balances_leave_type || 'Leave type'} value={createForm.data.leave_type_id} onChange={(e) => createForm.setData('leave_type_id', e.target.value)}>
                    {leaveTypes.map((type) => <option key={type.id} value={type.id}>{named(type)}</option>)}
                </select>
                <select className="form-input" aria-label={t.year || 'Year'} value={createForm.data.academic_year_id} onChange={(e) => createForm.setData('academic_year_id', e.target.value)}>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <button type="submit" className="btn-primary" disabled={createForm.processing}>{t.balances_open || 'Open entitlement'}</button>
                <FormErrors errors={createForm.errors} />
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    carryForm.post('/hr/leave-balances/carry-over', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <select className="form-input" aria-label={t.balances_from_year_label || 'Carry from year'} value={carryForm.data.from_year_id} onChange={(e) => carryForm.setData('from_year_id', e.target.value)}>
                    {years.map((year) => <option key={year.id} value={year.id}>{(t.balances_from_year || 'From :year').replace(':year', year.name)}</option>)}
                </select>
                <select className="form-input" aria-label={t.balances_to_year_label || 'Carry to year'} value={carryForm.data.to_year_id} onChange={(e) => carryForm.setData('to_year_id', e.target.value)}>
                    <option value="">{t.balances_to_year_pick || 'To year'}</option>
                    {years.map((year) => <option key={`to-${year.id}`} value={year.id}>{(t.balances_to_year || 'To :year').replace(':year', year.name)}</option>)}
                </select>
                <button type="submit" className="btn-secondary" disabled={carryForm.processing}>{t.balances_carry || 'Carry over'}</button>
                <FormErrors errors={carryForm.errors} />
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.staff || 'Staff'}</th>
                            <th className="px-3 py-2">{t.type || 'Type'}</th>
                            <th className="px-3 py-2">{t.balances_col_entitled || 'Entitled'}</th>
                            <th className="px-3 py-2">{t.balances_col_carried || 'Carried'}</th>
                            <th className="px-3 py-2">{t.balances_col_adjusted || 'Adjusted'}</th>
                            <th className="px-3 py-2">{t.balances_col_balance || 'Balance'}</th>
                            <th className="px-3 py-2">{t.balances_col_adjust || 'Adjust'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={7}>{t.balances_none || 'No entitlements yet.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <BalanceRow key={row.id} row={row} t={t} named={named} />
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function BalanceRow({ row, t, named }) {
    const form = useForm({ days: '', reason: '' });
    const forStaff = (phrase) => phrase.replace(':name', row.staff_name);

    return (
        <tr className="border-t">
            <td className="px-3 py-2">{row.staff_name}</td>
            <td className="px-3 py-2">{named({ name: row.leave_type, name_dhivehi: row.leave_type_dhivehi, name_arabic: row.leave_type_arabic })}</td>
            <td className="px-3 py-2">{row.entitled_days}</td>
            <td className="px-3 py-2">{row.carried_over_days}</td>
            <td className="px-3 py-2">{row.adjusted_days}</td>
            <td className="px-3 py-2">{row.balance}</td>
            <td className="px-3 py-2">
                <form
                    className="flex flex-wrap gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(`/hr/leave-balances/${row.id}/adjust`, { preserveScroll: true });
                    }}
                >
                    <input className="form-input w-20" aria-label={forStaff(t.balances_days_for || 'Days to add or take for :name')} placeholder="+/-" value={form.data.days} onChange={(e) => form.setData('days', e.target.value)} />
                    <input className="form-input w-32" aria-label={forStaff(t.balances_reason_for || 'Reason for :name')} placeholder={t.reason || 'Reason'} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                    <button type="submit" className="btn-secondary" disabled={form.processing}>{t.save || 'Save'}</button>
                    <FormErrors errors={form.errors} />
                </form>
            </td>
        </tr>
    );
}
