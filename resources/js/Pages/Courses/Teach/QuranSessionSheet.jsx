import { useForm } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

const blank = (record) => ({
    attendance_status: '',
    new_from_surah_id: record?.new_from_surah_id ?? '',
    new_from_ayah: record?.new_from_ayah ?? '',
    new_to_surah_id: record?.new_to_surah_id ?? '',
    new_to_ayah: record?.new_to_ayah ?? '',
    new_result: record?.new_result ?? '',
    new_score: record?.new_score ?? '',
    recent_revision_text: record?.recent_revision_text ?? '',
    recent_revision_result: record?.recent_revision_result ?? '',
    recent_revision_score: record?.recent_revision_score ?? '',
    old_revision_text: record?.old_revision_text ?? '',
    old_revision_result: record?.old_revision_result ?? '',
    old_revision_score: record?.old_revision_score ?? '',
    haraka_mistakes: record?.haraka_mistakes ?? 0,
    word_mistakes: record?.word_mistakes ?? 0,
    fluency_mistakes: record?.fluency_mistakes ?? 0,
    teacher_note: record?.teacher_note ?? '',
    parent_visible_note: record?.parent_visible_note ?? '',
    next_target: record?.next_target ?? '',
    requires_parent_attention: record?.requires_parent_attention ?? false,
    requires_supervisor_review: record?.requires_supervisor_review ?? false,
    overall_status: record?.overall_status ?? '',
});

function Select({ value, onChange, options, placeholder }) {
    return (
        <select className="form-input" value={value} onChange={(e) => onChange(e.target.value)}>
            <option value="">{placeholder}</option>
            {options.map((option) => (
                <option key={option.value ?? option} value={option.value ?? option}>
                    {option.label ?? String(option).replaceAll('_', ' ')}
                </option>
            ))}
        </select>
    );
}

function RecordForm({ sessionId, row, surahs, options, onDone }) {
    const form = useForm(blank(row.record));
    const set = (key) => (value) => form.setData(key, value);
    const surahOptions = surahs.map((surah) => ({ value: surah.id, label: `${surah.index}. ${surah.english_name}` }));

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((data) => ({ ...data, course_enrollment_id: row.enrollment_id }))
                    .post(`/teach/quran-sessions/${sessionId}/records`, { preserveScroll: true, onSuccess: onDone });
            }}
            className="grid gap-3 border-t bg-[#FBF7F0] p-4"
        >
            <div className="grid gap-2 md:grid-cols-4">
                <Select value={form.data.attendance_status} onChange={set('attendance_status')} placeholder="Attendance…" options={['present', 'late', 'absent', 'excused']} />
                <Select value={form.data.overall_status} onChange={set('overall_status')} placeholder="Overall…" options={options.overall_statuses} />
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.requires_parent_attention} onChange={(e) => set('requires_parent_attention')(e.target.checked)} />
                    Parent attention
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.requires_supervisor_review} onChange={(e) => set('requires_supervisor_review')(e.target.checked)} />
                    Supervisor review
                </label>
            </div>

            <fieldset className="grid gap-2 rounded border bg-white p-3 md:grid-cols-6">
                <legend className="px-1 text-sm font-semibold">New memorization</legend>
                <Select value={form.data.new_from_surah_id} onChange={set('new_from_surah_id')} placeholder="From surah…" options={surahOptions} />
                <input className="form-input" type="number" min="1" placeholder="Ayah" value={form.data.new_from_ayah} onChange={(e) => set('new_from_ayah')(e.target.value)} />
                <Select value={form.data.new_to_surah_id} onChange={set('new_to_surah_id')} placeholder="To surah…" options={surahOptions} />
                <input className="form-input" type="number" min="1" placeholder="Ayah" value={form.data.new_to_ayah} onChange={(e) => set('new_to_ayah')(e.target.value)} />
                <Select value={form.data.new_result} onChange={set('new_result')} placeholder="Result…" options={options.lane_results} />
                <input className="form-input" type="number" min="0" max="100" placeholder="Score" value={form.data.new_score} onChange={(e) => set('new_score')(e.target.value)} />
            </fieldset>

            <fieldset className="grid gap-2 rounded border bg-white p-3 md:grid-cols-3">
                <legend className="px-1 text-sm font-semibold">Recent revision</legend>
                <input className="form-input" placeholder="Portion" value={form.data.recent_revision_text} onChange={(e) => set('recent_revision_text')(e.target.value)} />
                <Select value={form.data.recent_revision_result} onChange={set('recent_revision_result')} placeholder="Result…" options={options.revision_results} />
                <input className="form-input" type="number" min="0" max="100" placeholder="Score" value={form.data.recent_revision_score} onChange={(e) => set('recent_revision_score')(e.target.value)} />
            </fieldset>

            <fieldset className="grid gap-2 rounded border bg-white p-3 md:grid-cols-3">
                <legend className="px-1 text-sm font-semibold">Old revision</legend>
                <input className="form-input" placeholder="Portion" value={form.data.old_revision_text} onChange={(e) => set('old_revision_text')(e.target.value)} />
                <Select value={form.data.old_revision_result} onChange={set('old_revision_result')} placeholder="Result…" options={options.revision_results} />
                <input className="form-input" type="number" min="0" max="100" placeholder="Score" value={form.data.old_revision_score} onChange={(e) => set('old_revision_score')(e.target.value)} />
            </fieldset>

            <div className="grid gap-2 md:grid-cols-3">
                <label className="text-sm">Haraka mistakes
                    <input className="form-input" type="number" min="0" value={form.data.haraka_mistakes} onChange={(e) => set('haraka_mistakes')(e.target.value)} />
                </label>
                <label className="text-sm">Word mistakes
                    <input className="form-input" type="number" min="0" value={form.data.word_mistakes} onChange={(e) => set('word_mistakes')(e.target.value)} />
                </label>
                <label className="text-sm">Fluency mistakes
                    <input className="form-input" type="number" min="0" value={form.data.fluency_mistakes} onChange={(e) => set('fluency_mistakes')(e.target.value)} />
                </label>
            </div>

            <div className="grid gap-2 md:grid-cols-3">
                <input className="form-input" placeholder="Teacher note" value={form.data.teacher_note} onChange={(e) => set('teacher_note')(e.target.value)} />
                <input className="form-input" placeholder="Parent-visible note" value={form.data.parent_visible_note} onChange={(e) => set('parent_visible_note')(e.target.value)} />
                <input className="form-input" placeholder="Next target" value={form.data.next_target} onChange={(e) => set('next_target')(e.target.value)} />
            </div>

            <div>
                <button type="submit" className="btn-primary" disabled={form.processing}>Save record</button>
            </div>
        </form>
    );
}

export default function QuranSessionSheet({ session, roster, surahs, options }) {
    const [openId, setOpenId] = useState(null);

    return (
        <AppShell title={`Halaqa sheet — ${session.title || session.id}`}>
            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-gray-600">{session.offering_title} · {session.starts_at?.slice(0, 10)}</p>
                <a className="btn-secondary" href={`/teach/quran-sessions/${session.id}?format=csv`}>Export CSV</a>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Attendance</th>
                            <th className="px-3 py-2">New</th>
                            <th className="px-3 py-2">Recent</th>
                            <th className="px-3 py-2">Old</th>
                            <th className="px-3 py-2">Mistakes</th>
                            <th className="px-3 py-2">Overall</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {roster.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={8}>No students enrolled on this offering.</td></tr>
                        )}
                        {roster.map((row) => (
                            <Fragment key={row.enrollment_id}>
                                <tr className="border-t">
                                    <td className="px-3 py-2">{row.student_name}</td>
                                    <td className="px-3 py-2">{row.status}</td>
                                    <td className="px-3 py-2">{row.record?.new_result?.replaceAll('_', ' ') ?? '—'}{row.record?.new_score != null ? ` (${row.record.new_score})` : ''}</td>
                                    <td className="px-3 py-2">{row.record?.recent_revision_result?.replaceAll('_', ' ') ?? '—'}</td>
                                    <td className="px-3 py-2">{row.record?.old_revision_result?.replaceAll('_', ' ') ?? '—'}</td>
                                    <td className="px-3 py-2">{row.record?.mistake_count ?? '—'}</td>
                                    <td className="px-3 py-2">{row.record?.overall_status?.replaceAll('_', ' ') ?? '—'}</td>
                                    <td className="px-3 py-2 text-end">
                                        <button type="button" className="btn-secondary" onClick={() => setOpenId(openId === row.enrollment_id ? null : row.enrollment_id)}>
                                            {openId === row.enrollment_id ? 'Close' : (row.record ? 'Edit' : 'Record')}
                                        </button>
                                    </td>
                                </tr>
                                {openId === row.enrollment_id && (
                                    <tr>
                                        <td colSpan={8} className="p-0">
                                            <RecordForm sessionId={session.id} row={row} surahs={surahs} options={options} onDone={() => setOpenId(null)} />
                                        </td>
                                    </tr>
                                )}
                            </Fragment>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
