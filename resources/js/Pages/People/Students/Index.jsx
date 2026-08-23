import { router, useForm, Link } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ students, filters, statuses }) {
    const form = useForm({
        search: filters.search || '',
        status: filters.status || '',
        class_id: filters.class_id || '',
    });

    const apply = (e) => {
        e.preventDefault();
        form.get('/people/students', { preserveState: true });
    };

    return (
        <AppShell title="Students">
            <form onSubmit={apply} className="mb-4 flex flex-wrap gap-3 rounded-lg border bg-white p-4">
                <input
                    className="form-input min-w-56"
                    placeholder="Search name, ID, student number"
                    value={form.data.search}
                    onChange={(e) => form.setData('search', e.target.value)}
                />
                <select className="form-input" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    <option value="">All statuses</option>
                    {statuses.map((status) => (
                        <option key={status} value={status}>{status}</option>
                    ))}
                </select>
                <button type="submit" className="btn-primary">Filter</button>
                <a
                    className="btn-secondary"
                    href={`/people/students/export?search=${encodeURIComponent(form.data.search || '')}&status=${encodeURIComponent(form.data.status || '')}`}
                >
                    Export CSV
                </a>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Number</th>
                            <th className="px-3 py-2">National ID</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Class</th>
                        </tr>
                    </thead>
                    <tbody>
                        {students.map((student) => (
                            <tr key={student.id} className="border-t">
                                <td className="px-3 py-2">
                                    <Link href={`/people/students/${student.id}`} className="text-[#7C2D37] hover:underline">
                                        {student.first_name} {student.last_name}
                                    </Link>
                                </td>
                                <td className="px-3 py-2">{student.student_id}</td>
                                <td className="px-3 py-2">{student.national_id}</td>
                                <td className="px-3 py-2">{student.status}</td>
                                <td className="px-3 py-2">{student.class_name}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
