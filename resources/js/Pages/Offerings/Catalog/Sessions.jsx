import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

export default function Sessions({ offering, types, sessions, programs = [], halaqa = null, halaqa_sessions = [], dual_write_enabled = false, t = {} }) {
    // Codes the server sends, named in the page's language (slice CT8).
    const typeName = (type) => t[`session_type_${type}`] || type;
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
        <AppShell title={(t.sessions_title || 'Sessions — :offering').replace(':offering', () => offering.title)}>
            <p className="mb-4 text-sm text-gray-600">{offering.course_title} · {t[`delivery_mode_${offering.delivery_mode}`] || offering.delivery_mode}</p>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href={`/catalog/offerings/${offering.id}/sessions/export`}>{t.catalog_export || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/catalog/offerings/${offering.id}/sessions`, { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <input className="form-input" placeholder={t.sessions_session_title || 'Session title'} aria-label={t.sessions_session_title || 'Session title'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <select className="form-input" aria-label={t.sessions_type || 'Session type'} value={form.data.session_type} onChange={(e) => form.setData('session_type', e.target.value)}>
                    {types.map((type) => <option key={type} value={type}>{typeName(type)}</option>)}
                </select>
                <input className="form-input" type="datetime-local" aria-label={t.sessions_starts || 'Starts'} value={form.data.starts_at} onChange={(e) => form.setData('starts_at', e.target.value)} />
                <input className="form-input" type="datetime-local" aria-label={t.sessions_ends || 'Ends'} value={form.data.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} />
                <input className="form-input" placeholder={t.sessions_location || 'Location'} aria-label={t.sessions_location || 'Location'} value={form.data.location_name} onChange={(e) => form.setData('location_name', e.target.value)} />
                <input className="form-input" placeholder={t.sessions_meeting_url || 'Meeting URL'} aria-label={t.sessions_meeting_url || 'Meeting URL'} value={form.data.online_meeting_url} onChange={(e) => form.setData('online_meeting_url', e.target.value)} />
                <input className="form-input" placeholder={t.sessions_teacher_id || 'Teacher user id'} aria-label={t.sessions_teacher_id || 'Teacher user id'} value={form.data.teacher_user_id} onChange={(e) => form.setData('teacher_user_id', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.sessions_save || 'Save session'}</button>
                <FormErrors errors={form.errors} />
            </form>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    halaqaForm.post(`/catalog/offerings/${offering.id}/halaqa`, { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <select className="form-input" aria-label={t.sessions_program || 'Hifz program'} value={halaqaForm.data.hifz_program_id} onChange={(e) => halaqaForm.setData('hifz_program_id', e.target.value)}>
                    <option value="">{t.sessions_link_program || 'Link a Hifz program'}</option>
                    {programs.map((program) => <option key={program.id} value={program.id}>{program.name}</option>)}
                </select>
                <p className="text-sm text-gray-600 md:col-span-1">
                    {halaqa?.program?.name
                        ? (t.sessions_linked || 'Linked: :name').replace(':name', () => halaqa.program.name)
                        : (t.sessions_mapping_only || 'Mapping only — Hifz dashboards stay unchanged.')}
                </p>
                <button type="submit" className="btn-secondary" disabled={halaqaForm.processing || programs.length === 0}>{t.sessions_save_link || 'Save halaqa link'}</button>
                {halaqa && dual_write_enabled && (
                    <button
                        type="button"
                        className="btn-primary"
                        onClick={() => router.post(`/catalog/offerings/${offering.id}/halaqa/sync`, {}, { preserveScroll: true })}
                    >
                        {t.sessions_sync || 'Sync dual-write'}
                    </button>
                )}
                {/* `QURAN_HALAQA_DUAL_WRITE` is the server's switch; the office
                    is told what it means, not what it is called. */}
                {halaqa && !dual_write_enabled && (
                    <p className="text-sm text-gray-600 md:col-span-3">{t.sessions_dual_write_off || 'Sessions and enrolments are not copied to the Hifz records: dual-write is switched off on this server.'}</p>
                )}
                {halaqa?.last_synced_at && (
                    <p className="text-sm text-gray-600">{(t.sessions_last_sync || 'Last sync: :when').replace(':when', halaqa.last_synced_at)}</p>
                )}
                <FormErrors errors={halaqaForm.errors} />
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.catalog_col_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.sessions_col_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.sessions_starts || 'Starts'}</th>
                            <th className="px-3 py-2">{t.sessions_col_halaqa || 'Halaqa session'}</th>
                            <th className="px-3 py-2">{t.sessions_col_action || 'Action'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {sessions.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.sessions_none || 'No sessions yet.'}</td></tr>
                        )}
                        {sessions.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.title}{row.is_required ? ` · ${t.sessions_required || 'required'}` : ''}</td>
                                <td className="px-3 py-2">{typeName(row.session_type)}</td>
                                <td className="px-3 py-2">{row.starts_at}</td>
                                <td className="px-3 py-2">
                                    {halaqa_sessions.length > 0 ? (
                                        <select
                                            className="form-input"
                                            aria-label={(t.sessions_map_for || 'Halaqa session for :title').replace(':title', () => row.title)}
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
                                            <option value="">{t.sessions_map || 'Map session'}</option>
                                            {halaqa_sessions.map((item) => (
                                                <option key={item.id} value={item.id}>{item.title || item.session_date}</option>
                                            ))}
                                        </select>
                                    ) : (row.hifz_session_id || '—')}
                                </td>
                                <td className="px-3 py-2">
                                    <button type="button" className="text-[#7C2D37] hover:underline" onClick={() => router.get(`/catalog/offerings/${offering.id}/sessions/${row.id}/attendance`)}>{t.sessions_attendance || 'Attendance'}</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
