import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';

// A code the server sends, named from the `quran` book (slice CT5a).
const named = (q, family, code) => (code ? q[`${family}${code}`] || code.replaceAll('_', ' ') : '—');

function RecommendForm({ t, q, targets, options, actOn }) {
    const form = useForm({
        hifz_program_id: '',
        student_id: '',
        type: 'juz_completed',
        surah_number: '',
        juz_number: '',
        page_number: '',
        title: '',
        note: '',
    });

    const pickTarget = (value) => {
        const [programId, studentId] = value.split(':');
        form.setData((data) => ({
            ...data,
            hifz_program_id: programId ?? '',
            student_id: studentId ?? '',
        }));
    };

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                // Marks the form as the last thing acted on, so a row's
                // refusal list does not repeat the form's.
                actOn('recommend', () => form.post('/teach/milestones', { preserveScroll: true, onSuccess: () => form.reset() }));
            }}
            className="mb-6 grid gap-2 rounded-lg border bg-white p-4 md:grid-cols-4"
        >
            <select className="form-input" aria-label={t.qt_col_student || 'Student'} onChange={(e) => pickTarget(e.target.value)} defaultValue="">
                <option value="">{t.qmile_student_pick || 'Student (mapped halaqas)…'}</option>
                {targets.map((target) => (
                    <option key={`${target.hifz_program_id}:${target.student_id}`} value={`${target.hifz_program_id}:${target.student_id}`}>
                        {target.student_name} — {target.program_name}
                    </option>
                ))}
            </select>
            <select className="form-input" aria-label={t.qt_col_type || 'Type'} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                {options.types.map((type) => <option key={type} value={type}>{named(q, 'milestone_type_', type)}</option>)}
            </select>
            <input className="form-input" placeholder={t.qmile_title_field || 'Title (optional)'} aria-label={t.qmile_title_field || 'Title (optional)'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
            <button type="submit" className="btn-primary" disabled={form.processing || !form.data.student_id}>{t.qmile_recommend || 'Recommend'}</button>

            <input className="form-input" type="number" min="1" max="114" placeholder={t.qmile_surah || 'Surah #'} aria-label={t.qmile_surah || 'Surah #'} value={form.data.surah_number} onChange={(e) => form.setData('surah_number', e.target.value)} />
            <input className="form-input" type="number" min="1" max="30" placeholder={t.qmile_juz || 'Juz #'} aria-label={t.qmile_juz || 'Juz #'} value={form.data.juz_number} onChange={(e) => form.setData('juz_number', e.target.value)} />
            <input className="form-input" type="number" min="1" placeholder={t.qmile_page || 'Page #'} aria-label={t.qmile_page || 'Page #'} value={form.data.page_number} onChange={(e) => form.setData('page_number', e.target.value)} />
            <input className="form-input" placeholder={t.qt_col_note || 'Note'} aria-label={t.qt_col_note || 'Note'} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
            <FormErrors errors={form.errors} />
        </form>
    );
}

export default function QuranMilestones({ rows, targets, options, status, can_decide, t = {}, q = {} }) {
    // What a milestone covers: its title, or its surah, juz and page.
    const detail = (row) => row.title || [
        row.surah_number && (t.qmile_detail_surah || 'Surah :n').replace(':n', row.surah_number),
        row.juz_number && (t.qmile_detail_juz || 'Juz :n').replace(':n', row.juz_number),
        row.page_number && (t.qmile_detail_page || 'Page :n').replace(':n', row.page_number),
    ].filter(Boolean).join(', ') || '—';
    // Review, Approve and Reject post with `router`, and a refusal of any of
    // them was shown nowhere (slice CT6b-2c); it is said on its row.
    const refusals = useRowRefusals();

    return (
        <AppShell title={t.qmile_title || 'Memorization milestones'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-1" role="group" aria-label={t.qt_filter || 'Show'}>
                    {options.statuses.map((option) => (
                        <button
                            key={option}
                            type="button"
                            onClick={() => router.get('/teach/milestones', option === 'all' ? {} : { status: option }, { preserveState: false })}
                            className={`rounded px-3 py-1 text-sm ${option === status ? 'bg-[#0F6D5F] text-white' : 'bg-gray-100'}`}
                        >
                            {option === 'all' ? (q.all || 'all') : named(q, 'status_', option)}
                        </button>
                    ))}
                </div>
                <a className="btn-secondary" href={`/teach/milestones?status=${status}&format=csv`}>{t.catalog_export || 'Export CSV'}</a>
            </div>

            <RecommendForm t={t} q={q} targets={targets} options={options} actOn={refusals.actOn} />

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.qt_col_student || 'Student'}</th>
                            <th className="px-3 py-2">{t.qmile_col_program || 'Program'}</th>
                            <th className="px-3 py-2">{t.qt_col_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.qmile_col_detail || 'Detail'}</th>
                            <th className="px-3 py-2">{t.qt_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.qt_col_note || 'Note'}</th>
                            {can_decide && <th className="px-3 py-2" />}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={can_decide ? 7 : 6}>{t.qmile_none || 'No milestones.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.program_name}</td>
                                <td className="px-3 py-2">{named(q, 'milestone_type_', row.type)}</td>
                                <td className="px-3 py-2">{detail(row)}</td>
                                <td className="px-3 py-2">{named(q, 'status_', row.status)}</td>
                                <td className="px-3 py-2">{row.note ?? '—'}</td>
                                {can_decide && (
                                    <td className="px-3 py-2 text-end">
                                        {row.status === 'pending' && (
                                            <button type="button" className="btn-secondary me-1" onClick={() => refusals.actOn(`milestone:${row.id}`, () => router.post(`/teach/milestones/${row.id}/review`, {}, { preserveScroll: true }))}>
                                                {t.qt_review || 'Review'}
                                            </button>
                                        )}
                                        {(row.status === 'pending' || row.status === 'supervisor_reviewed') && (
                                            <>
                                                <button type="button" className="btn-primary me-1" onClick={() => refusals.actOn(`milestone:${row.id}`, () => router.post(`/teach/milestones/${row.id}/decide`, { approved: true }, { preserveScroll: true }))}>
                                                    {t.qmile_approve || 'Approve'}
                                                </button>
                                                <button type="button" className="text-sm text-red-600" onClick={() => refusals.actOn(`milestone:${row.id}`, () => router.post(`/teach/milestones/${row.id}/decide`, { approved: false }, { preserveScroll: true }))}>
                                                    {t.qmile_reject || 'Reject'}
                                                </button>
                                            </>
                                        )}
                                        <FormErrors errors={refusals.errorsFor(`milestone:${row.id}`)} className="mt-1 text-start" />
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
