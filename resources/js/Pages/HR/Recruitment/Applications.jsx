import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Applications({ postings, statuses, rows }) {
    const form = useForm({
        job_posting_id: postings[0]?.id || '',
        name: '',
        email: '',
        mobile: '',
        cover_note: '',
        status: 'received',
    });

    return (
        <AppShell title="Job applications">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/hr/applications/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/hr/applications', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" value={form.data.job_posting_id} onChange={(e) => form.setData('job_posting_id', e.target.value)}>
                    {postings.map((posting) => <option key={posting.id} value={posting.id}>{posting.title}</option>)}
                </select>
                <input className="form-input" placeholder="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input className="form-input" placeholder="Email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                <input className="form-input" placeholder="Mobile" value={form.data.mobile} onChange={(e) => form.setData('mobile', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Record application</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Job</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                <td className="px-3 py-2">{row.job_title}</td>
                                <td className="px-3 py-2">{row.status}</td>
                                <td className="px-3 py-2 text-right">
                                    {row.status !== 'hired' && (
                                        <button type="button" className="btn-secondary" onClick={() => router.post(`/hr/applications/${row.id}/hire`)}>Hire</button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
