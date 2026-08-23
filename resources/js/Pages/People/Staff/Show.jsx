import { useForm, router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Show({ staff }) {
    const form = useForm({
        title: '',
        institution: '',
        year: '',
    });

    return (
        <AppShell title={staff.first_name + ' ' + staff.last_name}>
            <section className="mb-6 rounded-lg border bg-white p-4 text-sm">
                <p><strong>Number:</strong> {staff.staff_number || '—'}</p>
                <p><strong>Employment:</strong> {staff.employment_type}</p>
                <p><strong>Status:</strong> {staff.status}</p>
                <p><strong>Joined:</strong> {staff.joined_date || '—'}</p>
            </section>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/people/staff/${staff.id}/qualifications`);
                }}
                className="mb-4 flex flex-wrap gap-3 rounded-lg border bg-white p-4"
            >
                <input className="form-input" placeholder="Qualification title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <input className="form-input" placeholder="Institution" value={form.data.institution} onChange={(e) => form.setData('institution', e.target.value)} />
                <input className="form-input w-28" placeholder="Year" value={form.data.year} onChange={(e) => form.setData('year', e.target.value)} />
                <button type="submit" className="btn-primary">Add qualification</button>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Institution</th>
                            <th className="px-3 py-2">Year</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {(staff.qualifications || []).map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.title}</td>
                                <td className="px-3 py-2">{row.institution}</td>
                                <td className="px-3 py-2">{row.year}</td>
                                <td className="px-3 py-2 text-right">
                                    <button
                                        type="button"
                                        className="text-red-700 hover:underline"
                                        onClick={() => router.delete(`/people/staff/${staff.id}/qualifications/${row.id}`)}
                                    >
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
