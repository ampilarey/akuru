import { useForm, router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

export default function Show({ staff, employmentTypes = [], statuses = [] }) {
    const form = useForm({
        title: '',
        institution: '',
        year: '',
    });

    // The controller has always sent `employmentTypes` and `statuses` to this
    // page and the page never used them: `people.staff.update` existed, and
    // validated a status, with no form anywhere that posted to it. So a staff
    // status could be read but never changed, and every screen that filters on
    // "still employed" was guarding a value that could not move.
    const employment = useForm({
        user_id: staff.user_id ?? '',
        first_name: staff.first_name ?? '',
        last_name: staff.last_name ?? '',
        staff_number: staff.staff_number ?? '',
        department: staff.department ?? '',
        designation: staff.designation ?? '',
        employment_type: staff.employment_type ?? 'full_time',
        status: staff.status ?? 'active',
    });

    return (
        <AppShell title={staff.first_name + ' ' + staff.last_name}>
            <section className="mb-6 rounded-lg border bg-white p-4 text-sm">
                <p><strong>Number:</strong> {staff.staff_number || '—'}</p>
                <p><strong>Department:</strong> {staff.department || '—'}</p>
                <p><strong>Designation:</strong> {staff.designation || '—'}</p>
                <p><strong>Joined:</strong> {staff.joined_date || '—'}</p>
            </section>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    employment.put(`/people/staff/${staff.id}`, { preserveScroll: true });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Employment</span>
                    <select
                        className="form-input w-full"
                        value={employment.data.employment_type}
                        onChange={(e) => employment.setData('employment_type', e.target.value)}
                    >
                        {employmentTypes.map((type) => <option key={type} value={type}>{type}</option>)}
                    </select>
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Status</span>
                    <select
                        className="form-input w-full"
                        value={employment.data.status}
                        onChange={(e) => employment.setData('status', e.target.value)}
                    >
                        {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                    </select>
                    <span className="mt-1 block text-xs text-gray-500">
                        Ending employment also takes this person out of the teacher pickers and
                        the staff count. On leave does not — cover is arranged through them.
                    </span>
                </label>
                <div className="flex items-end">
                    <button type="submit" className="btn-primary" disabled={employment.processing}>
                        Save employment
                    </button>
                </div>
                <FormErrors errors={employment.errors} />
            </form>

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
                <FormErrors errors={form.errors} />
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
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
                                <td className="px-3 py-2 text-end">
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
