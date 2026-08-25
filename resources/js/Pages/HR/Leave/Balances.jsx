import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Balances({ filters, years, staff, leaveTypes, rows }) {
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
        <AppShell title="Leave balances">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        const data = new FormData(e.currentTarget);
                        router.get('/hr/leave-balances', { academic_year_id: data.get('academic_year_id') });
                    }}
                    className="flex flex-wrap gap-3"
                >
                    <select name="academic_year_id" className="form-input" defaultValue={filters.academic_year_id || ''}>
                        <option value="">All years</option>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                    <button type="submit" className="btn-secondary">Filter</button>
                </form>
                <a className="btn-secondary" href={`/hr/leave-balances/export?academic_year_id=${filters.academic_year_id || ''}`}>Export CSV</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    createForm.post('/hr/leave-balances', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" value={createForm.data.staff_profile_id} onChange={(e) => createForm.setData('staff_profile_id', e.target.value)}>
                    {staff.map((row) => <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>)}
                </select>
                <select className="form-input" value={createForm.data.leave_type_id} onChange={(e) => createForm.setData('leave_type_id', e.target.value)}>
                    {leaveTypes.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}
                </select>
                <select className="form-input" value={createForm.data.academic_year_id} onChange={(e) => createForm.setData('academic_year_id', e.target.value)}>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <button type="submit" className="btn-primary" disabled={createForm.processing}>Open entitlement</button>
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    carryForm.post('/hr/leave-balances/carry-over', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <select className="form-input" value={carryForm.data.from_year_id} onChange={(e) => carryForm.setData('from_year_id', e.target.value)}>
                    {years.map((year) => <option key={year.id} value={year.id}>From {year.name}</option>)}
                </select>
                <select className="form-input" value={carryForm.data.to_year_id} onChange={(e) => carryForm.setData('to_year_id', e.target.value)}>
                    <option value="">To year</option>
                    {years.map((year) => <option key={`to-${year.id}`} value={year.id}>To {year.name}</option>)}
                </select>
                <button type="submit" className="btn-secondary" disabled={carryForm.processing}>Carry over</button>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Staff</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Entitled</th>
                            <th className="px-3 py-2">Carried</th>
                            <th className="px-3 py-2">Adjusted</th>
                            <th className="px-3 py-2">Balance</th>
                            <th className="px-3 py-2">Adjust</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={7}>No entitlements yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <BalanceRow key={row.id} row={row} />
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function BalanceRow({ row }) {
    const form = useForm({ days: '', reason: '' });

    return (
        <tr className="border-t">
            <td className="px-3 py-2">{row.staff_name}</td>
            <td className="px-3 py-2">{row.leave_type}</td>
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
                    <input className="form-input w-20" placeholder="+/-" value={form.data.days} onChange={(e) => form.setData('days', e.target.value)} />
                    <input className="form-input w-32" placeholder="Reason" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                    <button type="submit" className="btn-secondary" disabled={form.processing}>Save</button>
                </form>
            </td>
        </tr>
    );
}
