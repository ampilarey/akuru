import { router, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import AppShell from '../../../Layouts/AppShell';

/**
 * The year an exam form should start on: the one being viewed, else the active
 * one, else the newest. `years` is ordered by start_date descending.
 */
function defaultYearId(years, yearId) {
    return yearId
        || years.find((year) => year.status === 'active')?.id
        || years[0]?.id
        || '';
}

const forYear = (rows, yearId) => rows.filter(
    (row) => `${row.academic_year_id ?? ''}` === `${yearId}`,
);

/**
 * The exam schedule. Every word is the `exams` book's (slice EG1, STATUS
 * §5qj); a subject and an exam type read by the name the school gave them
 * in the page's language.
 */
export default function Index({ years, terms, classes, subjects, rooms, examTypes, exams, ungraded, statuses, yearId, t = {} }) {
    const locale = usePage().props.locale || 'en';
    const named = (row) => ({ dv: row?.name_dhivehi, ar: row?.name_arabic }[locale]) || row?.name;
    const subjectOptions = subjects.map((row) => ({ id: row.id, name: named(row) }));
    const typeOptions = examTypes.map((row) => ({ id: row.id, name: named(row) }));

    // Terms and classes belong to a year. Offering all of them let the form
    // open on "Extra / Term 2 / Arabic Beginners" while the table below showed
    // Pilot Grade 5 A — the defaults wandered away from what was on screen.
    const startYear = defaultYearId(years, yearId);
    const startTerms = forYear(terms, startYear);
    const startClasses = forYear(classes, startYear);

    const form = useForm({
        academic_year_id: startYear,
        term_id: startTerms[0]?.id || '',
        class_id: startClasses[0]?.id || '',
        subject_id: subjects[0]?.id || '',
        exam_type_id: examTypes[0]?.id || '',
        name: '',
        exam_date: '',
        start_time: '',
        end_time: '',
        room_id: '',
        max_marks: 100,
        instructions: '',
        confirm_calendar: false,
        confirm_same_day: false,
        confirm_room: false,
    });

    const bulk = useForm({
        academic_year_id: startYear,
        term_id: startTerms[0]?.id || '',
        class_id: startClasses[0]?.id || '',
        exam_type_id: examTypes[0]?.id || '',
        name: '',
        exam_date: '',
        start_time: '',
        end_time: '',
        room_id: '',
        max_marks: 100,
        subject_ids: subjects.map((subject) => subject.id),
        confirm_calendar: false,
        confirm_same_day: true,
        confirm_room: false,
    });

    const formTerms = forYear(terms, form.data.academic_year_id);
    const formClasses = forYear(classes, form.data.academic_year_id);
    const bulkTerms = forYear(terms, bulk.data.academic_year_id);
    const bulkClasses = forYear(classes, bulk.data.academic_year_id);
    const classOptions = (rows) => rows.map((row) => ({ id: row.id, name: `${row.name} ${row.section}` }));

    // Changing the year must not leave last year's term selected underneath it.
    useEffect(() => {
        if (!formTerms.some((row) => `${row.id}` === `${form.data.term_id}`)) {
            form.setData('term_id', formTerms[0]?.id || '');
        }
        if (!formClasses.some((row) => `${row.id}` === `${form.data.class_id}`)) {
            form.setData('class_id', formClasses[0]?.id || '');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.academic_year_id]);

    useEffect(() => {
        if (!bulkTerms.some((row) => `${row.id}` === `${bulk.data.term_id}`)) {
            bulk.setData('term_id', bulkTerms[0]?.id || '');
        }
        if (!bulkClasses.some((row) => `${row.id}` === `${bulk.data.class_id}`)) {
            bulk.setData('class_id', bulkClasses[0]?.id || '');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [bulk.data.academic_year_id]);

    return (
        <AppShell title={t.exams_title || 'Exams'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <select
                    className="form-input"
                    aria-label={t.year || 'Year'}
                    value={yearId || ''}
                    onChange={(e) => router.get(`/exams/schedule?academic_year_id=${e.target.value}`)}
                >
                    <option value="">{t.all_years || 'All years'}</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <a className="btn-secondary" href={`/exams/schedule/export?academic_year_id=${yearId || ''}`}>{t.export_csv || 'Export CSV'}</a>
            </div>

            {ungraded.length > 0 && (
                <p className="mb-4 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    {(t.exams_ungraded || ':count exam(s) still in marks entry after the exam date.').replace(':count', ungraded.length)}
                </p>
            )}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/exams/schedule', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <h2 className="md:col-span-4 text-sm font-semibold">{t.exams_schedule_one || 'Schedule one exam'}</h2>
                <Select label={t.year || 'Year'} value={form.data.academic_year_id} error={form.errors.academic_year_id} onChange={(v) => form.setData('academic_year_id', v)} options={years} />
                <Select label={t.term || 'Term'} value={form.data.term_id} error={form.errors.term_id} onChange={(v) => form.setData('term_id', v)} options={formTerms} />
                <Select label={t.class || 'Class'} value={form.data.class_id} error={form.errors.class_id} onChange={(v) => form.setData('class_id', v)} options={classOptions(formClasses)} />
                <Select label={t.subject || 'Subject'} value={form.data.subject_id} error={form.errors.subject_id} onChange={(v) => form.setData('subject_id', v)} options={subjectOptions} />
                <Select label={t.type || 'Type'} value={form.data.exam_type_id} error={form.errors.exam_type_id} onChange={(v) => form.setData('exam_type_id', v)} options={typeOptions} />
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.name || 'Name'}</span>
                    <input className="form-input w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    {form.errors.name && <span className="text-xs text-red-600">{form.errors.name}</span>}
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.date || 'Date'}</span>
                    <input className="form-input w-full" type="date" value={form.data.exam_date} onChange={(e) => form.setData('exam_date', e.target.value)} />
                    {form.errors.exam_date && <span className="text-xs text-red-600">{form.errors.exam_date}</span>}
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.start || 'Start'}</span>
                    <input className="form-input w-full" type="time" value={form.data.start_time} onChange={(e) => form.setData('start_time', e.target.value)} />
                    {form.errors.start_time && <span className="text-xs text-red-600">{form.errors.start_time}</span>}
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.end || 'End'}</span>
                    <input className="form-input w-full" type="time" value={form.data.end_time} onChange={(e) => form.setData('end_time', e.target.value)} />
                    {form.errors.end_time && <span className="text-xs text-red-600">{form.errors.end_time}</span>}
                </label>
                <Select label={t.room || 'Room'} value={form.data.room_id} error={form.errors.room_id} onChange={(v) => form.setData('room_id', v)} options={[{ id: '', name: t.none || 'None' }, ...rooms]} />
                {form.errors.max_marks && <p className="text-xs text-red-600 md:col-span-4">{form.errors.max_marks}</p>}
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.confirm_calendar} onChange={(e) => form.setData('confirm_calendar', e.target.checked)} />
                    {t.exams_confirm_calendar || 'Confirm holiday/closure'}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.confirm_same_day} onChange={(e) => form.setData('confirm_same_day', e.target.checked)} />
                    {t.exams_confirm_same_day || 'Confirm same-day clash'}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.confirm_room} onChange={(e) => form.setData('confirm_room', e.target.checked)} />
                    {t.exams_confirm_room || 'Confirm room clash'}
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.exams_schedule || 'Schedule'}</button>
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    bulk.post('/exams/schedule/bulk', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <h2 className="md:col-span-4 text-sm font-semibold">{t.exams_bulk_title || 'Bulk: one exam per subject'}</h2>
                <Select label={t.year || 'Year'} value={bulk.data.academic_year_id} error={bulk.errors.academic_year_id} onChange={(v) => bulk.setData('academic_year_id', v)} options={years} />
                <Select label={t.term || 'Term'} value={bulk.data.term_id} error={bulk.errors.term_id} onChange={(v) => bulk.setData('term_id', v)} options={bulkTerms} />
                <Select label={t.class || 'Class'} value={bulk.data.class_id} error={bulk.errors.class_id} onChange={(v) => bulk.setData('class_id', v)} options={classOptions(bulkClasses)} />
                <Select label={t.type || 'Type'} value={bulk.data.exam_type_id} error={bulk.errors.exam_type_id} onChange={(v) => bulk.setData('exam_type_id', v)} options={typeOptions} />
                <label className="text-sm md:col-span-2">
                    <span className="mb-1 block text-gray-600">{t.exams_name_prefix || 'Name prefix'}</span>
                    <input className="form-input w-full" placeholder={t.exams_name_prefix_example || 'Term 1 Finals — Grade 5'} value={bulk.data.name} onChange={(e) => bulk.setData('name', e.target.value)} />
                    {bulk.errors.name && <span className="text-xs text-red-600">{bulk.errors.name}</span>}
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.date || 'Date'}</span>
                    <input className="form-input w-full" type="date" value={bulk.data.exam_date} onChange={(e) => bulk.setData('exam_date', e.target.value)} />
                    {bulk.errors.exam_date && <span className="text-xs text-red-600">{bulk.errors.exam_date}</span>}
                </label>
                <button type="submit" className="btn-secondary" disabled={bulk.processing}>{t.exams_create_per_subject || 'Create per subject'}</button>
                {bulk.errors.subject_ids && <p className="text-xs text-red-600 md:col-span-4">{bulk.errors.subject_ids}</p>}
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.name || 'Name'}</th>
                            <th className="px-3 py-2">{t.exams_col_class_subject || 'Class / subject'}</th>
                            <th className="px-3 py-2">{t.date || 'Date'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {exams.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.exams_none || 'No exams scheduled.'}</td></tr>
                        )}
                        {exams.map((exam) => (
                            <ExamRow
                                key={exam.id}
                                exam={exam}
                                statuses={statuses}
                                subject={named(subjects.find((row) => row.id === exam.subject_id)) || exam.subject_name}
                                t={t}
                            />
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function ExamRow({ exam, statuses, subject, t }) {
    const form = useForm({
        status: exam.status,
        reason: '',
    });
    const statusName = (status) => t[`exam_status_${status}`] || status;
    // A move the server refuses (a reason missing, a step it does not allow)
    // is said under the row that asked.
    const refused = form.errors.status || form.errors.reason;

    return (
        <tr className="border-t">
            <td className="px-3 py-2">{exam.name}</td>
            <td className="px-3 py-2">{exam.class_name} / {subject}</td>
            <td className="px-3 py-2">{exam.exam_date || '—'}</td>
            <td className="px-3 py-2">{statusName(exam.status)}</td>
            <td className="px-3 py-2">
                <div className="flex flex-wrap items-center gap-2">
                    <select
                        className="form-input"
                        aria-label={(t.exams_move_to || 'Move :name to').replace(':name', exam.name)}
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    >
                        {statuses.map((status) => <option key={status} value={status}>{statusName(status)}</option>)}
                    </select>
                    {form.data.status !== exam.status && exam.status === 'locked' && (
                        <input
                            className="form-input"
                            aria-label={t.exams_unlock_reason || 'Unlock reason'}
                            placeholder={t.exams_unlock_reason || 'Unlock reason'}
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                        />
                    )}
                    <a className="btn-secondary" href={`/exams/${exam.id}/marks`}>{t.exams_marks || 'Marks'}</a>
                    <button
                        type="button"
                        className="btn-secondary"
                        disabled={form.processing || form.data.status === exam.status}
                        onClick={() => form.post(`/exams/schedule/${exam.id}/transition`, { preserveScroll: true })}
                    >
                        {t.exams_move || 'Move'}
                    </button>
                </div>
                {refused && <span className="mt-1 block text-xs text-red-600">{refused}</span>}
            </td>
        </tr>
    );
}

function Select({ label, value, onChange, options, error }) {
    return (
        <label className="text-sm">
            <span className="mb-1 block text-gray-600">{label}</span>
            <select className="form-input w-full" value={value} onChange={(e) => onChange(e.target.value)}>
                {options.map((option) => (
                    <option key={`${option.id}`} value={option.id}>{option.name}</option>
                ))}
            </select>
            {error && <span className="text-xs text-red-600">{error}</span>}
        </label>
    );
}
