import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Sessions({ offering, types, sessions }) {
    const t = usePage().props.i18n?.learn || {};
    const form = useForm({
        title: '',
        session_type: types[0] || 'face_to_face',
        starts_at: '',
        ends_at: '',
        location_name: '',
        online_meeting_url: '',
        is_required: true,
    });

    return (
        <AppShell title={`Sessions — ${offering.title}`}>
            <p className="mb-4 text-sm text-gray-600">{offering.course_title} · {offering.delivery_mode}</p>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href={`/catalog/offerings/${offering.id}/sessions/export`}>{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/catalog/offerings/${offering.id}/sessions`, { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <input className="form-input" placeholder="Session title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <select className="form-input" value={form.data.session_type} onChange={(e) => form.setData('session_type', e.target.value)}>
                    {types.map((type) => <option key={type} value={type}>{type}</option>)}
                </select>
                <input className="form-input" type="datetime-local" value={form.data.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} />
                <input className="form-input" type="datetime-local" value={form.data.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} />
                <input className="form-input" placeholder="Location" value={form.data.location_name} onChange={(e) => form.setData('location_name', e.target.value)} />
                <input className="form-input" placeholder="Meeting URL" value={form.data.online_meeting_url} onChange={(e) => form.setData('online_meeting_url', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Save session</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Starts</th>
                            <th className="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {sessions.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No sessions yet.</td></tr>
                        )}
                        {sessions.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.title}{row.is_required ? ' · required' : ''}</td>
                                <td className="px-3 py-2">{row.session_type}</td>
                                <td className="px-3 py-2">{row.starts_at}</td>
                                <td className="px-3 py-2">
                                    <button type="button" className="text-[#7C2D37] hover:underline" onClick={() => router.get(`/catalog/offerings/${offering.id}/sessions/${row.id}/attendance`)}>Attendance</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
