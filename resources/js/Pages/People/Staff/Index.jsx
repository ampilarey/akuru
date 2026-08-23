import { Link, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ staff }) {
    const form = useForm({
        user_id: '',
        first_name: '',
        last_name: '',
        employment_type: 'full_time',
        status: 'active',
        staff_number: '',
    });

    return (
        <AppShell title="Staff profiles">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/people/staff');
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <input className="form-input" placeholder="User ID" value={form.data.user_id} onChange={(e) => form.setData('user_id', e.target.value)} />
                <input className="form-input" placeholder="First name" value={form.data.first_name} onChange={(e) => form.setData('first_name', e.target.value)} />
                <input className="form-input" placeholder="Last name" value={form.data.last_name} onChange={(e) => form.setData('last_name', e.target.value)} />
                <input className="form-input" placeholder="Staff number" value={form.data.staff_number} onChange={(e) => form.setData('staff_number', e.target.value)} />
                <button type="submit" className="btn-primary">Create profile</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Number</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {staff.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">
                                    <Link href={`/people/staff/${row.id}`} className="text-[#7C2D37] hover:underline">
                                        {row.first_name} {row.last_name}
                                    </Link>
                                </td>
                                <td className="px-3 py-2">{row.staff_number}</td>
                                <td className="px-3 py-2">{row.employment_type}</td>
                                <td className="px-3 py-2">{row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
