import { useForm, usePage } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

// A code the server sends, named from the `quran` book (slice CT5a).
const named = (q, family, code) => (code ? q[`${family}${code}`] || code.replaceAll('_', ' ') : '—');
const ATTENDANCE = ['present', 'late', 'absent', 'excused'];

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

// The placeholder is the field's name too: a screen reader says it.
function Select({ value, onChange, options, placeholder }) {
    return (
        <select className="form-input" aria-label={placeholder} value={value} onChange={(e) => onChange(e.target.value)}>
            <option value="">{placeholder}</option>
            {options.map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
            ))}
        </select>
    );
}

function RecordForm({ t, q, sessionId, row, surahs, options, surahName, onDone }) {
    const form = useForm(blank(row.record));
    const set = (key) => (value) => form.setData(key, value);
    const surahOptions = surahs.map((surah) => ({ value: surah.id, label: `${surah.index}. ${surahName(surah)}` }));
    const codes = (family, values) => values.map((value) => ({ value, label: named(q, family, value) }));

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((data) => ({ ...data, course_enrollment_id: row.enrollment_id }));
                form.post(`/teach/quran-sessions/${sessionId}/records`, { preserveScroll: true, onSuccess: onDone });
            }}
            className="grid gap-3 border-t bg-[#FBF7F0] p-4"
        >
            <div className="grid gap-2 md:grid-cols-4">
                <Select value={form.data.attendance_status} onChange={set('attendance_status')} placeholder={t.qsheet_attendance_pick || 'Attendance…'} options={codes('attendance_', ATTENDANCE)} />
                <Select value={form.data.overall_status} onChange={set('overall_status')} placeholder={t.qsheet_overall_pick || 'Overall…'} options={codes('overall_', options.overall_statuses)} />
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.requires_parent_attention} onChange={(e) => set('requires_parent_attention')(e.target.checked)} />
                    {t.qsheet_parent_attention || 'Parent attention'}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.requires_supervisor_review} onChange={(e) => set('requires_supervisor_review')(e.target.checked)} />
                    {t.qsheet_supervisor_review || 'Supervisor review'}
                </label>
            </div>

            <fieldset className="grid gap-2 rounded border bg-white p-3 md:grid-cols-6">
                <legend className="px-1 text-sm font-semibold">{t.qsheet_new || 'New memorization'}</legend>
                <Select value={form.data.new_from_surah_id} onChange={set('new_from_surah_id')} placeholder={t.qsheet_from_surah || 'From surah…'} options={surahOptions} />
                <input className="form-input" type="number" min="1" placeholder={t.qt_ayah || 'Ayah'} aria-label={t.qt_ayah || 'Ayah'} value={form.data.new_from_ayah} onChange={(e) => set('new_from_ayah')(e.target.value)} />
                <Select value={form.data.new_to_surah_id} onChange={set('new_to_surah_id')} placeholder={t.qsheet_to_surah || 'To surah…'} options={surahOptions} />
                <input className="form-input" type="number" min="1" placeholder={t.qt_ayah || 'Ayah'} aria-label={t.qt_ayah || 'Ayah'} value={form.data.new_to_ayah} onChange={(e) => set('new_to_ayah')(e.target.value)} />
                <Select value={form.data.new_result} onChange={set('new_result')} placeholder={t.qsheet_result_pick || 'Result…'} options={codes('result_', options.lane_results)} />
                <input className="form-input" type="number" min="0" max="100" placeholder={t.qt_score || 'Score'} aria-label={t.qt_score || 'Score'} value={form.data.new_score} onChange={(e) => set('new_score')(e.target.value)} />
            </fieldset>

            <fieldset className="grid gap-2 rounded border bg-white p-3 md:grid-cols-3">
                <legend className="px-1 text-sm font-semibold">{t.qsheet_recent || 'Recent revision'}</legend>
                <input className="form-input" placeholder={t.qsheet_portion || 'Portion'} aria-label={t.qsheet_portion || 'Portion'} value={form.data.recent_revision_text} onChange={(e) => set('recent_revision_text')(e.target.value)} />
                <Select value={form.data.recent_revision_result} onChange={set('recent_revision_result')} placeholder={t.qsheet_result_pick || 'Result…'} options={codes('result_', options.revision_results)} />
                <input className="form-input" type="number" min="0" max="100" placeholder={t.qt_score || 'Score'} aria-label={t.qt_score || 'Score'} value={form.data.recent_revision_score} onChange={(e) => set('recent_revision_score')(e.target.value)} />
            </fieldset>

            <fieldset className="grid gap-2 rounded border bg-white p-3 md:grid-cols-3">
                <legend className="px-1 text-sm font-semibold">{t.qsheet_old || 'Old revision'}</legend>
                <input className="form-input" placeholder={t.qsheet_portion || 'Portion'} aria-label={t.qsheet_portion || 'Portion'} value={form.data.old_revision_text} onChange={(e) => set('old_revision_text')(e.target.value)} />
                <Select value={form.data.old_revision_result} onChange={set('old_revision_result')} placeholder={t.qsheet_result_pick || 'Result…'} options={codes('result_', options.revision_results)} />
                <input className="form-input" type="number" min="0" max="100" placeholder={t.qt_score || 'Score'} aria-label={t.qt_score || 'Score'} value={form.data.old_revision_score} onChange={(e) => set('old_revision_score')(e.target.value)} />
            </fieldset>

            <div className="grid gap-2 md:grid-cols-3">
                <label className="text-sm">{t.qsheet_haraka_mistakes || 'Haraka mistakes'}
                    <input className="form-input" type="number" min="0" value={form.data.haraka_mistakes} onChange={(e) => set('haraka_mistakes')(e.target.value)} />
                </label>
                <label className="text-sm">{t.qsheet_word_mistakes || 'Word mistakes'}
                    <input className="form-input" type="number" min="0" value={form.data.word_mistakes} onChange={(e) => set('word_mistakes')(e.target.value)} />
                </label>
                <label className="text-sm">{t.qsheet_fluency_mistakes || 'Fluency mistakes'}
                    <input className="form-input" type="number" min="0" value={form.data.fluency_mistakes} onChange={(e) => set('fluency_mistakes')(e.target.value)} />
                </label>
            </div>

            <div className="grid gap-2 md:grid-cols-3">
                <input className="form-input" placeholder={t.qsheet_teacher_note || 'Teacher note'} aria-label={t.qsheet_teacher_note || 'Teacher note'} value={form.data.teacher_note} onChange={(e) => set('teacher_note')(e.target.value)} />
                <input className="form-input" placeholder={t.qsheet_parent_note || 'Parent-visible note'} aria-label={t.qsheet_parent_note || 'Parent-visible note'} value={form.data.parent_visible_note} onChange={(e) => set('parent_visible_note')(e.target.value)} />
                <input className="form-input" placeholder={t.qsheet_next_target || 'Next target'} aria-label={t.qsheet_next_target || 'Next target'} value={form.data.next_target} onChange={(e) => set('next_target')(e.target.value)} />
            </div>

            <div>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.qsheet_save || 'Save record'}</button>
            </div>
            <FormErrors errors={form.errors} />
        </form>
    );
}

export default function QuranSessionSheet({ session, roster, surahs, options, t = {}, q = {} }) {
    const [openId, setOpenId] = useState(null);
    const locale = usePage().props.locale || 'en';
    // A surah by its Arabic name on a Dhivehi or Arabic page.
    const surahName = (surah) => (locale === 'en' ? surah.english_name : surah.arabic_name || surah.english_name);

    return (
        <AppShell title={(t.qsheet_title || 'Halaqa sheet — :title').replace(':title', session.title || session.id)}>
            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-gray-600">{session.offering_title} · {session.starts_at?.slice(0, 10)}</p>
                <a className="btn-secondary" href={`/teach/quran-sessions/${session.id}?format=csv`}>{t.catalog_export || 'Export CSV'}</a>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.qt_col_student || 'Student'}</th>
                            <th className="px-3 py-2">{t.qsheet_col_attendance || 'Attendance'}</th>
                            <th className="px-3 py-2">{t.qsheet_col_new || 'New'}</th>
                            <th className="px-3 py-2">{t.qsheet_col_recent || 'Recent'}</th>
                            <th className="px-3 py-2">{t.qsheet_col_old || 'Old'}</th>
                            <th className="px-3 py-2">{t.qt_col_mistakes || 'Mistakes'}</th>
                            <th className="px-3 py-2">{t.qsheet_col_overall || 'Overall'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {roster.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={8}>{t.qsheet_none || 'No students enrolled on this offering.'}</td></tr>
                        )}
                        {roster.map((row) => (
                            <Fragment key={row.enrollment_id}>
                                <tr className="border-t">
                                    <td className="px-3 py-2">{row.student_name}</td>
                                    <td className="px-3 py-2">{named(q, 'attendance_', row.status)}</td>
                                    <td className="px-3 py-2">{named(q, 'result_', row.record?.new_result)}{row.record?.new_score != null ? ` (${row.record.new_score})` : ''}</td>
                                    <td className="px-3 py-2">{named(q, 'result_', row.record?.recent_revision_result)}</td>
                                    <td className="px-3 py-2">{named(q, 'result_', row.record?.old_revision_result)}</td>
                                    <td className="px-3 py-2">{row.record?.mistake_count ?? '—'}</td>
                                    <td className="px-3 py-2">{named(q, 'overall_', row.record?.overall_status)}</td>
                                    <td className="px-3 py-2 text-end">
                                        <button type="button" className="btn-secondary" onClick={() => setOpenId(openId === row.enrollment_id ? null : row.enrollment_id)}>
                                            {openId === row.enrollment_id ? (t.qt_close || 'Close') : (row.record ? (t.qsheet_edit || 'Edit') : (t.qsheet_record || 'Record'))}
                                        </button>
                                    </td>
                                </tr>
                                {openId === row.enrollment_id && (
                                    <tr>
                                        <td colSpan={8} className="p-0">
                                            <RecordForm t={t} q={q} sessionId={session.id} row={row} surahs={surahs} options={options} surahName={surahName} onDone={() => setOpenId(null)} />
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
