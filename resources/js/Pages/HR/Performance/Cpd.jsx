import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Cpd({ staff, rows }) {
    const form = useForm({
        staff_profile_id: staff[0]?.id || '',
        title: '',
        provider: '',
        hours: '',
        date: '',
    });

    return (
        <AppShell title="CPD records">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/hr/cpd/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/hr/cpd', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-5"
            >
                <select className="form-input" value={form.data.staff_profile_id} onChange={(e) => form.setData('staff_profile_id', e.target.value)}>
                    {staff.map((row) => <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>)}
                </select>
                <input className="form-input" placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <input className="form-input" placeholder="Provider" value={form.data.provider} onChange={(e) => form.setData('provider', e.target.value)} />
                <input className="form-input" placeholder="Hours" value={form.data.hours} onChange={(e) => form.setData('hours', e.target.value)} />
                <input type="date" className="form-input" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Save CPD</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Staff</th>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Provider</th>
                            <th className="px-3 py-2">Hours</th>
                            <th className="px-3 py-2">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.staff_name}</td>
                                <td className="px-3 py-2">{row.title}</td>
                                <td className="px-3 py-2">{row.provider}</td>
                                <td className="px-3 py-2">{row.hours}</td>
                                <td className="px-3 py-2">{row.date}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
