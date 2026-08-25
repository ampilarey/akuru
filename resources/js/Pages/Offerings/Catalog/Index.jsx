import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ rows, courses, modes }) {
    const form = useForm({
        course_id: courses[0]?.id || '',
        title: '',
        delivery_mode: modes[0] || 'self_learning',
        status: 'draft',
        pin_mode: 'latest',
        seat_limit: '',
    });

    return (
        <AppShell title="Offerings">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/offerings/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/catalog/offerings', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-6"
            >
                <select className="form-input" value={form.data.course_id} onChange={(e) => form.setData('course_id', e.target.value)}>
                    {courses.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}
                </select>
                <input className="form-input" placeholder="Offering title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <select className="form-input" value={form.data.delivery_mode} onChange={(e) => form.setData('delivery_mode', e.target.value)}>
                    {modes.map((mode) => <option key={mode} value={mode}>{mode}</option>)}
                </select>
                <select className="form-input" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    <option value="draft">draft</option>
                    <option value="open">open</option>
                    <option value="closed">closed</option>
                    <option value="archived">archived</option>
                </select>
                <input className="form-input" placeholder="Seat limit" value={form.data.seat_limit} onChange={(e) => form.setData('seat_limit', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing || courses.length === 0}>Save offering</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Course</th>
                            <th className="px-3 py-2">Mode</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Pin</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No offerings yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.title}</td>
                                <td className="px-3 py-2">{row.course_title}</td>
                                <td className="px-3 py-2">{row.delivery_mode}</td>
                                <td className="px-3 py-2">{row.status}</td>
                                <td className="px-3 py-2">
                                    <span className="me-2">{row.pin_mode}</span>
                                    <button type="button" className="btn-secondary" onClick={() => router.post(`/catalog/offerings/${row.id}/pin`)}>Pin now</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
