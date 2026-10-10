import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * Staff contracts. Every word is the `hr` book's (slice HR1, STATUS §5ql); a
 * contract's type and state are named rather than printed as codes. A
 * refused contract is said under the form, whichever field it was for — only
 * the start date's refusal was shown.
 */
export default function Index({ staff, types, rows, t = {} }) {
    const typeName = (type) => t[`contract_type_${type}`] || type;
    const statusName = (status) => t[`contract_status_${status}`] || status;
    const form = useForm({
        staff_profile_id: staff[0]?.id || '',
        contract_type: types[0] || 'permanent',
        start_date: '',
        end_date: '',
        basic_salary: '',
        working_hours_per_week: '40',
        status: 'active',
    });
    const refused = Object.values(form.errors)[0];

    return (
        <AppShell title={t.contracts_title || 'Staff contracts'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/hr/contracts/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/hr/contracts', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" aria-label={t.staff_member || 'Staff member'} value={form.data.staff_profile_id} onChange={(e) => form.setData('staff_profile_id', e.target.value)}>
                    {staff.map((row) => <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>)}
                </select>
                <select className="form-input" aria-label={t.type || 'Type'} value={form.data.contract_type} onChange={(e) => form.setData('contract_type', e.target.value)}>
                    {types.map((type) => <option key={type} value={type}>{typeName(type)}</option>)}
                </select>
                <input type="date" className="form-input" aria-label={t.contracts_start || 'Start'} value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} />
                <input type="date" className="form-input" aria-label={t.contracts_end || 'End'} value={form.data.end_date} onChange={(e) => form.setData('end_date', e.target.value)} />
                <input className="form-input" aria-label={t.contracts_salary || 'Basic salary'} placeholder={t.contracts_salary || 'Basic salary'} value={form.data.basic_salary} onChange={(e) => form.setData('basic_salary', e.target.value)} />
                <input className="form-input" aria-label={t.contracts_hours || 'Hours / week'} placeholder={t.contracts_hours || 'Hours / week'} value={form.data.working_hours_per_week} onChange={(e) => form.setData('working_hours_per_week', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.contracts_save || 'Save contract'}</button>
                {refused && <span className="text-xs text-red-600 md:col-span-4">{refused}</span>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.staff || 'Staff'}</th>
                            <th className="px-3 py-2">{t.type || 'Type'}</th>
                            <th className="px-3 py-2">{t.contracts_start || 'Start'}</th>
                            <th className="px-3 py-2">{t.contracts_end || 'End'}</th>
                            <th className="px-3 py-2">{t.contracts_col_salary || 'Salary'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.contracts_none || 'No contracts yet.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.staff_name}</td>
                                <td className="px-3 py-2">{typeName(row.contract_type)}</td>
                                <td className="px-3 py-2">{row.start_date}</td>
                                <td className="px-3 py-2">{row.end_date || '—'}</td>
                                <td className="px-3 py-2">{row.basic_salary}</td>
                                <td className="px-3 py-2">{statusName(row.status)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
