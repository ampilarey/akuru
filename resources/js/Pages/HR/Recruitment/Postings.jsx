import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Postings({ rows, statuses }) {
    const form = useForm({
        title: '',
        department: '',
        employment_type: 'full_time',
        status: 'draft',
        public: false,
        closes_at: '',
        description: '',
    });

    return (
        <AppShell title="Job postings">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/hr/postings/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/hr/postings', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <input className="form-input" placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <input className="form-input" placeholder="Department" value={form.data.department} onChange={(e) => form.setData('department', e.target.value)} />
                <select className="form-input" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                </select>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.public} onChange={(e) => form.setData('public', e.target.checked)} />
                    Public
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>Save posting</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Department</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Public</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.title}</td>
                                <td className="px-3 py-2">{row.department || '—'}</td>
                                <td className="px-3 py-2">{row.status}</td>
                                <td className="px-3 py-2">{row.public ? 'yes' : 'no'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
