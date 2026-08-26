import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

export default function Daily({ yearId, classId, date, mode, years, classes, statuses, roster, marks }) {
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

    return (
        <AppShell title="Daily attendance">
            {mode !== 'daily' && (
                <p className="mb-4 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm">
                    School is in per-lesson mode. Use the class register grid instead.
                </p>
            )}
            <div className="mb-4 flex flex-wrap gap-2">
                <select className="form-input" value={yearId || ''} onChange={(e) => router.get(`/academics/attendance/daily?academic_year_id=${e.target.value}`)}>
                    <option value="">Year</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <select
                    className="form-input"
                    value={classId || ''}
                    onChange={(e) => router.get(`/academics/attendance/daily?academic_year_id=${yearId || ''}&class_id=${e.target.value}&date=${date}`)}
                >
                    <option value="">Class</option>
                    {classes.map((item) => <option key={item.id} value={item.id}>{item.name} {item.section}</option>)}
                </select>
                <input
                    className="form-input"
                    type="date"
                    value={date}
                    onChange={(e) => router.get(`/academics/attendance/daily?academic_year_id=${yearId || ''}&class_id=${classId || ''}&date=${e.target.value}`)}
                />
            </div>

            {roster.length === 0 ? (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">Choose a class to mark the day.</p>
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
                        <thead className="bg-[#F3EBE0] text-left">
                            <tr>
                                <th className="px-2 py-1">Student</th>
                                <th className="px-2 py-1">Number</th>
                                <th className="px-2 py-1">Date of birth</th>
                                <th className="px-2 py-1">Status</th>
                                <th className="px-2 py-1">Minutes late</th>
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
                                                value={row.status}
                                                onChange={(e) => setGrid((current) => ({ ...current, [key]: { ...row, status: e.target.value } }))}
                                            >
                                                {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                                            </select>
                                        </td>
                                        <td className="px-2 py-1">
                                            <input
                                                className="form-input w-20"
                                                type="number"
                                                min="0"
                                                value={row.minutes_late}
                                                onChange={(e) => setGrid((current) => ({ ...current, [key]: { ...row, minutes_late: e.target.value } }))}
                                            />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                    <button type="submit" className="btn-primary" disabled={mode !== 'daily' || form.processing}>Save daily attendance</button>
                </form>
            )}
        </AppShell>
    );
}
