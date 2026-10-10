import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

/**
 * The school's staff profiles. Every word is the `people` book's (slice PE2,
 * STATUS §5qq); a profile's kind of employment and its status are named
 * rather than printed as codes, and every field the form had only a
 * placeholder for is named for a screen reader.
 */
export default function Index({ roles = { teacher: 'Teacher' }, staff, t = {} }) {
    const employmentName = (type) => t[`employment_${type}`] || type;
    const staffStatusName = (status) => t[`staff_status_${status}`] || status;
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
        <AppShell title={t.staff_title || 'Staff profiles'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/people/staff/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/people/staff');
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <label className="text-sm md:col-span-3">
                    <span className="mb-1 block text-gray-600">{t.staff_account || 'Account'}</span>
                    <select className="form-input" value={account} onChange={(e) => { setAccount(e.target.value); form.setData({ ...form.data, user_id: '', email: '', password: '' }); }}>
                        <option value="new">{t.staff_account_new || 'Create a new login for this person'}</option>
                        <option value="existing">{t.staff_account_existing || 'Link an existing account'}</option>
                    </select>
                </label>
                {account === 'existing' ? (
                    <input className="form-input" aria-label={t.staff_user_id || 'User ID'} placeholder={t.staff_user_id || 'User ID'} value={form.data.user_id} onChange={(e) => form.setData('user_id', e.target.value)} />
                ) : (
                    <>
                        <input className="form-input" type="email" aria-label={t.staff_email || 'Email (their login)'} placeholder={t.staff_email || 'Email (their login)'} value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                        <input className="form-input" type="password" aria-label={t.staff_password || 'Password (8+ characters)'} placeholder={t.staff_password || 'Password (8+ characters)'} value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                        <select className="form-input" aria-label={t.staff_role || 'Role'} value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}>
                            {Object.entries(roles).map(([role, label]) => <option key={role} value={role}>{label}</option>)}
                        </select>
                    </>
                )}
                <input className="form-input" aria-label={t.first_name || 'First name'} placeholder={t.first_name || 'First name'} value={form.data.first_name} onChange={(e) => form.setData('first_name', e.target.value)} />
                <input className="form-input" aria-label={t.last_name || 'Last name'} placeholder={t.last_name || 'Last name'} value={form.data.last_name} onChange={(e) => form.setData('last_name', e.target.value)} />
                <input className="form-input" aria-label={t.staff_number || 'Staff number'} placeholder={t.staff_number || 'Staff number'} value={form.data.staff_number} onChange={(e) => form.setData('staff_number', e.target.value)} />
                <input className="form-input" aria-label={t.staff_department || 'Department'} placeholder={t.staff_department || 'Department'} value={form.data.department || ''} onChange={(e) => form.setData('department', e.target.value)} />
                <input className="form-input" aria-label={t.staff_designation || 'Designation'} placeholder={t.staff_designation || 'Designation'} value={form.data.designation || ''} onChange={(e) => form.setData('designation', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.staff_create || 'Create profile'}</button>
                <FormErrors errors={form.errors} className="md:col-span-3" />
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_name || 'Name'}</th>
                            <th className="px-3 py-2">{t.col_number || 'Number'}</th>
                            <th className="px-3 py-2">{t.staff_department || 'Department'}</th>
                            <th className="px-3 py-2">{t.staff_employment || 'Employment'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {staff.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.staff_none || 'No staff profile yet.'}</td></tr>
                        )}
                        {staff.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">
                                    <Link href={`/people/staff/${row.id}`} className="text-[#7C2D37] hover:underline">
                                        {row.first_name} {row.last_name}
                                    </Link>
                                </td>
                                <td className="px-3 py-2">{row.staff_number}</td>
                                <td className="px-3 py-2">{row.department || '—'}</td>
                                <td className="px-3 py-2">{employmentName(row.employment_type)}</td>
                                <td className="px-3 py-2">{staffStatusName(row.status)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
