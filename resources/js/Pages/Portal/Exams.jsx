import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Exams({ children, studentId, exams, t = {} }) {
    // The column names, also the captions a phone shows beside each value
    // (`data-label`), in the page's language (BACKLOG C21, slice PT2).
    const col = {
        exam: t.col_exam || 'Exam',
        subject: t.col_subject || 'Subject',
        date: t.col_date || 'Date',
        mark: t.col_mark || 'Mark',
        max: t.col_max || 'Max',
    };

    return (
        <AppShell title={t.exams_title || 'Exam results'}>
            <div className="mb-4">
                <select
                    className="form-input"
                    aria-label={t.pick_child || 'Child'}
                    value={studentId || ''}
                    onChange={(e) => router.get(`/portal/exams?student_id=${e.target.value}`)}
                >
                    {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                </select>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.exam}</th>
                            <th className="px-3 py-2">{col.subject}</th>
                            <th className="px-3 py-2">{col.date}</th>
                            <th className="px-3 py-2">{col.mark}</th>
                            <th className="px-3 py-2">{col.max}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {exams.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.exams_none || 'No published results yet.'}</td></tr>
                        )}
                        {exams.map((exam) => (
                            <tr key={exam.id} className="border-t">
                                <td className="px-3 py-2" data-label={col.exam}>{exam.name}</td>
                                <td className="px-3 py-2" data-label={col.subject}>{exam.subject}</td>
                                <td className="px-3 py-2" data-label={col.date}>{exam.exam_date || '—'}</td>
                                <td className="px-3 py-2" data-label={col.mark}>
                                    {exam.is_absent
                                        ? (t.attendance_status_absent || 'Absent')
                                        : exam.is_exempt ? (t.exams_exempt || 'Exempt') : (exam.marks ?? '—')}
                                </td>
                                <td className="px-3 py-2" data-label={col.max}>{exam.max_marks}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
