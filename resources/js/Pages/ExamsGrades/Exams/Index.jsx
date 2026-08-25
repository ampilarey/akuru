import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ years, terms, classes, subjects, rooms, examTypes, exams, ungraded, statuses, yearId }) {
    const form = useForm({
        academic_year_id: yearId || years[0]?.id || '',
        term_id: terms[0]?.id || '',
        class_id: classes[0]?.id || '',
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
        academic_year_id: yearId || years[0]?.id || '',
        term_id: terms[0]?.id || '',
        class_id: classes[0]?.id || '',
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

    return (
        <AppShell title="Exams">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <select
                    className="form-input"
                    value={yearId || ''}
                    onChange={(e) => router.get(`/exams/schedule?academic_year_id=${e.target.value}`)}
                >
                    <option value="">All years</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <a className="btn-secondary" href={`/exams/schedule/export?academic_year_id=${yearId || ''}`}>Export CSV</a>
            </div>

            {ungraded.length > 0 && (
                <p className="mb-4 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    {ungraded.length} exam(s) still in marks entry after the exam date.
                </p>
            )}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/exams/schedule', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <h2 className="md:col-span-4 text-sm font-semibold">Schedule one exam</h2>
                <Select label="Year" value={form.data.academic_year_id} error={form.errors.academic_year_id} onChange={(v) => form.setData('academic_year_id', v)} options={years} />
                <Select label="Term" value={form.data.term_id} error={form.errors.term_id} onChange={(v) => form.setData('term_id', v)} options={terms} />
                <Select label="Class" value={form.data.class_id} error={form.errors.class_id} onChange={(v) => form.setData('class_id', v)} options={classes.map((row) => ({ id: row.id, name: `${row.name} ${row.section}` }))} />
                <Select label="Subject" value={form.data.subject_id} error={form.errors.subject_id} onChange={(v) => form.setData('subject_id', v)} options={subjects} />
                <Select label="Type" value={form.data.exam_type_id} error={form.errors.exam_type_id} onChange={(v) => form.setData('exam_type_id', v)} options={examTypes} />
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Name</span>
                    <input className="form-input w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    {form.errors.name && <span className="text-xs text-red-600">{form.errors.name}</span>}
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Date</span>
                    <input className="form-input w-full" type="date" value={form.data.exam_date} onChange={(e) => form.setData('exam_date', e.target.value)} />
                    {form.errors.exam_date && <span className="text-xs text-red-600">{form.errors.exam_date}</span>}
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Start</span>
                    <input className="form-input w-full" type="time" value={form.data.start_time} onChange={(e) => form.setData('start_time', e.target.value)} />
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">End</span>
                    <input className="form-input w-full" type="time" value={form.data.end_time} onChange={(e) => form.setData('end_time', e.target.value)} />
                </label>
                <Select label="Room" value={form.data.room_id} error={form.errors.room_id} onChange={(v) => form.setData('room_id', v)} options={[{ id: '', name: 'None' }, ...rooms]} />
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.confirm_calendar} onChange={(e) => form.setData('confirm_calendar', e.target.checked)} />
                    Confirm holiday/closure
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.confirm_same_day} onChange={(e) => form.setData('confirm_same_day', e.target.checked)} />
                    Confirm same-day clash
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.confirm_room} onChange={(e) => form.setData('confirm_room', e.target.checked)} />
                    Confirm room clash
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>Schedule</button>
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    bulk.post('/exams/schedule/bulk', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <h2 className="md:col-span-4 text-sm font-semibold">Bulk: one exam per subject</h2>
                <Select label="Year" value={bulk.data.academic_year_id} onChange={(v) => bulk.setData('academic_year_id', v)} options={years} />
                <Select label="Term" value={bulk.data.term_id} onChange={(v) => bulk.setData('term_id', v)} options={terms} />
                <Select label="Class" value={bulk.data.class_id} onChange={(v) => bulk.setData('class_id', v)} options={classes.map((row) => ({ id: row.id, name: `${row.name} ${row.section}` }))} />
                <Select label="Type" value={bulk.data.exam_type_id} onChange={(v) => bulk.setData('exam_type_id', v)} options={examTypes} />
                <label className="text-sm md:col-span-2">
                    <span className="mb-1 block text-gray-600">Name prefix</span>
                    <input className="form-input w-full" placeholder="Term 1 Finals — Grade 5" value={bulk.data.name} onChange={(e) => bulk.setData('name', e.target.value)} />
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Date</span>
                    <input className="form-input w-full" type="date" value={bulk.data.exam_date} onChange={(e) => bulk.setData('exam_date', e.target.value)} />
                </label>
                <button type="submit" className="btn-secondary" disabled={bulk.processing}>Create per subject</button>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Class / subject</th>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {exams.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No exams scheduled.</td></tr>
                        )}
                        {exams.map((exam) => (
                            <ExamRow key={exam.id} exam={exam} statuses={statuses} />
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function ExamRow({ exam, statuses }) {
    const form = useForm({
        status: exam.status,
        reason: '',
    });

    return (
        <tr className="border-t">
            <td className="px-3 py-2">{exam.name}</td>
            <td className="px-3 py-2">{exam.class_name} / {exam.subject_name}</td>
            <td className="px-3 py-2">{exam.exam_date || '—'}</td>
            <td className="px-3 py-2 uppercase">{exam.status}</td>
            <td className="px-3 py-2">
                <div className="flex flex-wrap items-center gap-2">
                    <select className="form-input" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                        {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                    </select>
                    {form.data.status !== exam.status && exam.status === 'locked' && (
                        <input className="form-input" placeholder="Unlock reason" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
                    )}
                    <button
                        type="button"
                        className="btn-secondary"
                        disabled={form.processing || form.data.status === exam.status}
                        onClick={() => form.post(`/exams/schedule/${exam.id}/transition`, { preserveScroll: true })}
                    >
                        Move
                    </button>
                </div>
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
                    <option key={String(option.id)} value={option.id}>{option.name}</option>
                ))}
            </select>
            {error && <span className="text-xs text-red-600">{error}</span>}
        </label>
    );
}
