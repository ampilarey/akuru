import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

// A code the server sends, named from the `quran` book (slice CT5a).
const named = (q, family, code) => (code ? q[`${family}${code}`] || code.replaceAll('_', ' ') : '—');

function CreateForm({ t, q, targets, options, reference, surahs, surahName }) {
    const form = useForm({
        student_id: '',
        course_id: '',
        course_offering_id: '',
        assignment_type: 'new_memorization',
        surah_id: '',
        start_ayah_number: '',
        end_ayah_number: '',
        expected_letter_id: '',
        expected_haraka_id: '',
        due_date: '',
        notes: '',
    });

    const pickTarget = (value) => {
        const target = targets.find((row) => String(row.enrollment_id) === value);
        form.setData((data) => ({
            ...data,
            student_id: target?.student_id ?? '',
            course_id: target?.course_id ?? '',
            course_offering_id: target?.course_offering_id ?? '',
        }));
    };

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/teach/assignments', { preserveScroll: true, onSuccess: () => form.reset() });
            }}
            className="mb-6 grid gap-2 rounded-lg border bg-white p-4 md:grid-cols-4"
        >
            <select className="form-input" aria-label={t.qt_col_student || 'Student'} onChange={(e) => pickTarget(e.target.value)} defaultValue="">
                <option value="">{t.qassign_student_pick || 'Student (hifz enrollments)…'}</option>
                {targets.map((target) => (
                    <option key={target.enrollment_id} value={target.enrollment_id}>{target.student_name}</option>
                ))}
            </select>
            <select className="form-input" aria-label={t.qt_col_type || 'Type'} value={form.data.assignment_type} onChange={(e) => form.setData('assignment_type', e.target.value)}>
                {options.types.map((type) => <option key={type} value={type}>{named(q, 'assignment_type_', type)}</option>)}
            </select>
            <input className="form-input" type="date" aria-label={t.qassign_due || 'Due date'} value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
            <button type="submit" className="btn-primary" disabled={form.processing || !form.data.student_id}>{t.qassign_assign || 'Assign'}</button>

            <select className="form-input" aria-label={t.qt_col_surah || 'Surah'} value={form.data.surah_id} onChange={(e) => form.setData('surah_id', e.target.value)}>
                <option value="">{t.qassign_surah_pick || 'Surah (optional)…'}</option>
                {surahs.map((surah) => (
                    <option key={surah.id} value={surah.id}>{surah.index}. {surahName(surah)}</option>
                ))}
            </select>
            <input className="form-input" type="number" min="1" placeholder={t.qassign_from_ayah || 'From ayah'} aria-label={t.qassign_from_ayah || 'From ayah'} value={form.data.start_ayah_number} onChange={(e) => form.setData('start_ayah_number', e.target.value)} />
            <input className="form-input" type="number" min="1" placeholder={t.qassign_to_ayah || 'To ayah'} aria-label={t.qassign_to_ayah || 'To ayah'} value={form.data.end_ayah_number} onChange={(e) => form.setData('end_ayah_number', e.target.value)} />
            <input className="form-input" placeholder={t.qassign_notes || 'Notes'} aria-label={t.qassign_notes || 'Notes'} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />

            <select className="form-input" aria-label={t.qassign_letter_pick || 'Letter (practice)…'} value={form.data.expected_letter_id} onChange={(e) => form.setData('expected_letter_id', e.target.value)}>
                <option value="">{t.qassign_letter_pick || 'Letter (practice)…'}</option>
                {reference.letters.map((letter) => (
                    <option key={letter.id} value={letter.id}>{letter.arabic_character} {letter.display_name}</option>
                ))}
            </select>
            <select className="form-input" aria-label={t.qassign_haraka_pick || 'Haraka (practice)…'} value={form.data.expected_haraka_id} onChange={(e) => form.setData('expected_haraka_id', e.target.value)}>
                <option value="">{t.qassign_haraka_pick || 'Haraka (practice)…'}</option>
                {reference.harakas.map((haraka) => (
                    <option key={haraka.id} value={haraka.id}>{haraka.symbol} {haraka.display_name}</option>
                ))}
            </select>
            <FormErrors errors={form.errors} />
        </form>
    );
}

export default function QuranAssignments({ rows, targets, options, status, reference, surahs, t = {}, q = {} }) {
    const locale = usePage().props.locale || 'en';
    // A surah by its Arabic name on a Dhivehi or Arabic page.
    const surahName = (surah) => (locale === 'en' ? surah.english_name : surah.arabic_name || surah.english_name);

    return (
        <AppShell title={t.qassign_title || 'Qur’an assignments'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-1" role="group" aria-label={t.qt_filter || 'Show'}>
                    {['all', ...options.statuses].map((option) => (
                        <button
                            key={option}
                            type="button"
                            onClick={() => router.get('/teach/assignments', option === 'all' ? {} : { status: option }, { preserveState: false })}
                            className={`rounded px-3 py-1 text-sm ${option === status ? 'bg-[#0F6D5F] text-white' : 'bg-gray-100'}`}
                        >
                            {option === 'all' ? (q.all || 'all') : named(q, 'status_', option)}
                        </button>
                    ))}
                </div>
                <a className="btn-secondary" href={`/teach/assignments?status=${status}&format=csv`}>{t.catalog_export || 'Export CSV'}</a>
            </div>

            <CreateForm t={t} q={q} targets={targets} options={options} reference={reference} surahs={surahs} surahName={surahName} />

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.qt_col_student || 'Student'}</th>
                            <th className="px-3 py-2">{t.qt_col_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.qt_col_surah || 'Surah'}</th>
                            <th className="px-3 py-2">{t.qt_col_ayahs || 'Ayahs'}</th>
                            <th className="px-3 py-2">{t.qassign_col_due || 'Due'}</th>
                            <th className="px-3 py-2">{t.qt_col_status || 'Status'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={7}>{t.qassign_none || 'No assignments.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.student?.name ?? '—'}</td>
                                <td className="px-3 py-2">{named(q, 'assignment_type_', row.assignment_type)}</td>
                                <td className="px-3 py-2">{(locale === 'en' ? row.surah : row.surah_arabic || row.surah) ?? '—'}</td>
                                <td className="px-3 py-2">{row.start_ayah_number ? `${row.start_ayah_number}–${row.end_ayah_number ?? row.start_ayah_number}` : '—'}</td>
                                <td className="px-3 py-2">{row.due_date ?? '—'}</td>
                                <td className="px-3 py-2">{named(q, 'status_', row.status)}</td>
                                <td className="px-3 py-2 text-end">
                                    {row.status !== 'cancelled' && (
                                        <button
                                            type="button"
                                            className="text-sm text-red-600"
                                            onClick={() => router.put(`/teach/assignments/${row.id}`, { status: 'cancelled' }, { preserveScroll: true })}
                                        >
                                            {t.qassign_cancel || 'Cancel'}
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
