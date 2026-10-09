import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

export default function Daily({ yearId, classId, date, mode, years, classes, statuses, roster, marks, t = {} }) {
    const existing = Object.fromEntries(marks.map((mark) => [String(mark.student_id), mark]));
    const [grid, setGrid] = useState(() => Object.fromEntries(roster.map((student) => [String(student.student_id), {
        student_id: student.student_id,
        status: existing[String(student.student_id)]?.status || 'present',
        minutes_late: existing[String(student.student_id)]?.minutes_late || '',
    }])));
    const form = useForm({
        class_id: classId || '',
        date,
        attendance: [],
    });
    // In the page's language (BACKLOG C21, slice OA1); a mark's state is a
    // code, named here.
    const col = {
        student: t.col_student || 'Student',
        number: t.col_number || 'Number',
        dob: t.col_date_of_birth || 'Date of birth',
        status: t.col_status || 'Status',
        minutesLate: t.col_minutes_late || 'Minutes late',
    };

    return (
        <AppShell title={t.daily_title || 'Daily attendance'}>
            {mode !== 'daily' && (
                <p className="mb-4 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm">
                    {t.daily_per_lesson || 'School is in per-lesson mode. Use the class register grid instead.'}
                </p>
            )}
            <div className="mb-4 flex flex-wrap gap-2">
                <select className="form-input" aria-label={t.year || 'Year'} value={yearId || ''} onChange={(e) => router.get(`/academics/attendance/daily?academic_year_id=${e.target.value}`)}>
                    <option value="">{t.year || 'Year'}</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <select
                    className="form-input"
                    aria-label={t.class || 'Class'}
                    value={classId || ''}
                    onChange={(e) => router.get(`/academics/attendance/daily?academic_year_id=${yearId || ''}&class_id=${e.target.value}&date=${date}`)}
                >
                    <option value="">{t.class || 'Class'}</option>
                    {classes.map((item) => <option key={item.id} value={item.id}>{item.name} {item.section}</option>)}
                </select>
                <input
                    className="form-input"
                    type="date"
                    aria-label={t.date || 'Date'}
                    value={date}
                    onChange={(e) => router.get(`/academics/attendance/daily?academic_year_id=${yearId || ''}&class_id=${classId || ''}&date=${e.target.value}`)}
                />
            </div>

            {roster.length === 0 ? (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">{t.daily_choose_class || 'Choose a class to mark the day.'}</p>
            ) : (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform(() => ({
                            class_id: classId,
                            date,
                            attendance: Object.values(grid),
                        }));
                        form.post('/academics/attendance/daily', { preserveScroll: true });
                    }}
                    className="rounded-lg border bg-white p-4"
                >
                    <table className="mb-3 min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-2 py-1">{col.student}</th>
                                <th className="px-2 py-1">{col.number}</th>
                                <th className="px-2 py-1">{col.dob}</th>
                                <th className="px-2 py-1">{col.status}</th>
                                <th className="px-2 py-1">{col.minutesLate}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {roster.map((student) => {
                                const key = String(student.student_id);
                                const row = grid[key] || { student_id: student.student_id, status: 'present', minutes_late: '' };
                                return (
                                    <tr key={student.student_id} className="border-t">
                                        <td className="px-2 py-1">{student.name}</td>
                                        <td className="px-2 py-1">{student.student_number || '—'}</td>
                                        <td className="px-2 py-1">{student.date_of_birth || '—'}</td>
                                        <td className="px-2 py-1">
                                            <select
                                                className="form-input w-full"
                                                aria-label={`${col.status}: ${student.name}`}
                                                value={row.status}
                                                onChange={(e) => setGrid((current) => ({ ...current, [key]: { ...row, status: e.target.value } }))}
                                            >
                                                {statuses.map((status) => <option key={status} value={status}>{t[`attendance_status_${status}`] || status}</option>)}
                                            </select>
                                        </td>
                                        <td className="px-2 py-1">
                                            <input
                                                className="form-input w-20"
                                                type="number"
                                                min="0"
                                                aria-label={`${col.minutesLate}: ${student.name}`}
                                                value={row.minutes_late}
                                                onChange={(e) => setGrid((current) => ({ ...current, [key]: { ...row, minutes_late: e.target.value } }))}
                                            />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                    <button type="submit" className="btn-primary" disabled={mode !== 'daily' || form.processing}>{t.daily_save || 'Save daily attendance'}</button>
                    <FormErrors errors={form.errors} />
                </form>
            )}
        </AppShell>
    );
}
