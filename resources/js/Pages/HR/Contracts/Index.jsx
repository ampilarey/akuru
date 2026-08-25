import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ staff, types, rows }) {
    const form = useForm({
        staff_profile_id: staff[0]?.id || '',
        contract_type: types[0] || 'permanent',
        start_date: '',
        end_date: '',
        basic_salary: '',
        working_hours_per_week: '40',
        status: 'active',
    });

    return (
        <AppShell title="Staff contracts">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/hr/contracts/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/hr/contracts', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" value={form.data.staff_profile_id} onChange={(e) => form.setData('staff_profile_id', e.target.value)}>
                    {staff.map((row) => <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>)}
                </select>
                <select className="form-input" value={form.data.contract_type} onChange={(e) => form.setData('contract_type', e.target.value)}>
                    {types.map((type) => <option key={type} value={type}>{type}</option>)}
                </select>
                <input type="date" className="form-input" value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} />
                <input type="date" className="form-input" value={form.data.end_date} onChange={(e) => form.setData('end_date', e.target.value)} />
                <input className="form-input" placeholder="Basic salary" value={form.data.basic_salary} onChange={(e) => form.setData('basic_salary', e.target.value)} />
                <input className="form-input" placeholder="Hours / week" value={form.data.working_hours_per_week} onChange={(e) => form.setData('working_hours_per_week', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Save contract</button>
                {form.errors.start_date && <span className="text-xs text-red-600">{form.errors.start_date}</span>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Staff</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Start</th>
                            <th className="px-3 py-2">End</th>
                            <th className="px-3 py-2">Salary</th>
                            <th className="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>No contracts yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.staff_name}</td>
                                <td className="px-3 py-2">{row.contract_type}</td>
                                <td className="px-3 py-2">{row.start_date}</td>
                                <td className="px-3 py-2">{row.end_date || '—'}</td>
                                <td className="px-3 py-2">{row.basic_salary}</td>
                                <td className="px-3 py-2 uppercase">{row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
