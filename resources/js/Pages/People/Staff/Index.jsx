import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

export default function Index({ roles = ['teacher'], staff }) {
    // "New account" is the default: the retired Blade teacher form was the
    // only screen that could create a staff login, and this took its place.
    // "Existing account" covers a person who already has one (a parent who
    // joins the staff, an account made elsewhere).
    const [account, setAccount] = useState('new');
    const form = useForm({
        user_id: '',
        email: '',
        password: '',
        role: 'teacher',
        first_name: '',
        last_name: '',
        employment_type: 'full_time',
        status: 'active',
        staff_number: '',
        department: '',
        designation: '',
    });

    return (
        <AppShell title="Staff profiles">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/people/staff/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/people/staff');
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <label className="text-sm md:col-span-3">
                    <span className="mb-1 block text-gray-600">Account</span>
                    <select className="form-input" value={account} onChange={(e) => { setAccount(e.target.value); form.setData({ ...form.data, user_id: '', email: '', password: '' }); }}>
                        <option value="new">Create a new login for this person</option>
                        <option value="existing">Link an existing account</option>
                    </select>
                </label>
                {account === 'existing' ? (
                    <input className="form-input" placeholder="User ID" value={form.data.user_id} onChange={(e) => form.setData('user_id', e.target.value)} />
                ) : (
                    <>
                        <input className="form-input" type="email" placeholder="Email (their login)" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                        <input className="form-input" type="password" placeholder="Password (8+ characters)" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                        <select className="form-input" value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}>
                            {roles.map((role) => <option key={role} value={role}>{role}</option>)}
                        </select>
                    </>
                )}
                <input className="form-input" placeholder="First name" value={form.data.first_name} onChange={(e) => form.setData('first_name', e.target.value)} />
                <input className="form-input" placeholder="Last name" value={form.data.last_name} onChange={(e) => form.setData('last_name', e.target.value)} />
                <input className="form-input" placeholder="Staff number" value={form.data.staff_number} onChange={(e) => form.setData('staff_number', e.target.value)} />
                <input className="form-input" placeholder="Department" value={form.data.department || ''} onChange={(e) => form.setData('department', e.target.value)} />
                <input className="form-input" placeholder="Designation" value={form.data.designation || ''} onChange={(e) => form.setData('designation', e.target.value)} />
                <button type="submit" className="btn-primary">Create profile</button>
                <FormErrors errors={form.errors} />
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Number</th>
                            <th className="px-3 py-2">Department</th>
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
                                <td className="px-3 py-2">{row.department || '—'}</td>
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
