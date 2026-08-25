import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Sessions({ offering, types, sessions, programs = [], halaqa = null, halaqa_sessions = [], dual_write_enabled = false }) {
    const t = usePage().props.i18n?.learn || {};
    const halaqaForm = useForm({
        hifz_program_id: halaqa?.hifz_program_id || programs[0]?.id || '',
    });
    const form = useForm({
        title: '',
        session_type: types[0] || 'face_to_face',
        starts_at: '',
        ends_at: '',
        location_name: '',
        online_meeting_url: '',
        teacher_user_id: '',
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
                <input className="form-input" placeholder="Teacher user id" value={form.data.teacher_user_id} onChange={(e) => form.setData('teacher_user_id', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Save session</button>
            </form>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    halaqaForm.post(`/catalog/offerings/${offering.id}/halaqa`, { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <select className="form-input" value={halaqaForm.data.hifz_program_id} onChange={(e) => halaqaForm.setData('hifz_program_id', e.target.value)}>
                    <option value="">Link a Hifz program</option>
                    {programs.map((program) => <option key={program.id} value={program.id}>{program.name}</option>)}
                </select>
                <p className="text-sm text-gray-600 md:col-span-1">
                    {halaqa?.program?.name ? `Linked: ${halaqa.program.name}` : 'Mapping only — Hifz dashboards stay unchanged.'}
                </p>
                <button type="submit" className="btn-secondary" disabled={halaqaForm.processing || programs.length === 0}>Save halaqa link</button>
                {halaqa && dual_write_enabled && (
                    <button
                        type="button"
                        className="btn-primary"
                        onClick={() => router.post(`/catalog/offerings/${offering.id}/halaqa/sync`, {}, { preserveScroll: true })}
                    >
                        Sync dual-write
                    </button>
                )}
                {halaqa && !dual_write_enabled && (
                    <p className="text-sm text-gray-600 md:col-span-3">Dual-write is off (`QURAN_HALAQA_DUAL_WRITE`). Switch/cleanup stay later.</p>
                )}
                {halaqa?.last_synced_at && (
                    <p className="text-sm text-gray-600">Last sync: {halaqa.last_synced_at}</p>
                )}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Starts</th>
                            <th className="px-3 py-2">Halaqa session</th>
                            <th className="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {sessions.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No sessions yet.</td></tr>
                        )}
                        {sessions.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.title}{row.is_required ? ' · required' : ''}</td>
                                <td className="px-3 py-2">{row.session_type}</td>
                                <td className="px-3 py-2">{row.starts_at}</td>
                                <td className="px-3 py-2">
                                    {halaqa_sessions.length > 0 ? (
                                        <select
                                            className="form-input"
                                            defaultValue={row.hifz_session_id || ''}
                                            onChange={(e) => {
                                                if (!e.target.value) {
                                                    return;
                                                }
                                                router.post(`/catalog/offerings/${offering.id}/sessions/${row.id}/halaqa`, {
                                                    hifz_session_id: e.target.value,
                                                }, { preserveScroll: true });
                                            }}
                                        >
                                            <option value="">Map session</option>
                                            {halaqa_sessions.map((item) => (
                                                <option key={item.id} value={item.id}>{item.title || item.session_date}</option>
                                            ))}
                                        </select>
                                    ) : (row.hifz_session_id || '—')}
                                </td>
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
