import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Observations({ staff, classes, subjects, rows }) {
    const form = useForm({
        staff_profile_id: staff[0]?.id || '',
        date: '',
        class_id: '',
        subject_id: '',
        summary: '',
        shared_with_staff: true,
    });

    return (
        <AppShell title="Lesson observations">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/hr/observations/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/hr/observations', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" value={form.data.staff_profile_id} onChange={(e) => form.setData('staff_profile_id', e.target.value)}>
                    {staff.map((row) => <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>)}
                </select>
                <input type="date" className="form-input" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                <select className="form-input" value={form.data.class_id} onChange={(e) => form.setData('class_id', e.target.value)}>
                    <option value="">Class</option>
                    {classes.map((row) => <option key={row.id} value={row.id}>{row.label}</option>)}
                </select>
                <select className="form-input" value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                    <option value="">Subject</option>
                    {subjects.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                </select>
                <input className="form-input md:col-span-3" placeholder="Summary" value={form.data.summary} onChange={(e) => form.setData('summary', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Save observation</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Staff</th>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Class</th>
                            <th className="px-3 py-2">Subject</th>
                            <th className="px-3 py-2">Summary</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.staff_name}</td>
                                <td className="px-3 py-2">{row.date}</td>
                                <td className="px-3 py-2">{row.class_name || '—'}</td>
                                <td className="px-3 py-2">{row.subject_name || '—'}</td>
                                <td className="px-3 py-2">{row.summary}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
