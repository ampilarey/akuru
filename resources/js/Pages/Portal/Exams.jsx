import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Exams({ children, studentId, exams }) {
    return (
        <AppShell title="Exam results">
            <div className="mb-4">
                <select
                    className="form-input"
                    value={studentId || ''}
                    onChange={(e) => router.get(`/portal/exams?student_id=${e.target.value}`)}
                >
                    {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                </select>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Exam</th>
                            <th className="px-3 py-2">Subject</th>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Mark</th>
                            <th className="px-3 py-2">Max</th>
                        </tr>
                    </thead>
                    <tbody>
                        {exams.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No published results yet.</td></tr>
                        )}
                        {exams.map((exam) => (
                            <tr key={exam.id} className="border-t">
                                <td className="px-3 py-2">{exam.name}</td>
                                <td className="px-3 py-2">{exam.subject}</td>
                                <td className="px-3 py-2">{exam.exam_date || '—'}</td>
                                <td className="px-3 py-2">
                                    {exam.is_absent ? 'Absent' : exam.is_exempt ? 'Exempt' : (exam.marks ?? '—')}
                                </td>
                                <td className="px-3 py-2">{exam.max_marks}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
