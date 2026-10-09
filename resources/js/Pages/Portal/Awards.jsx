import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Awards({ children, studentId, awards, t = {} }) {
    // The column names, also the captions a phone shows beside each value
    // (`data-label`), in the page's language (BACKLOG C21, slice PT2).
    const col = {
        award: t.col_award || 'Award',
        student: t.col_student || 'Student',
        date: t.col_date || 'Date',
    };

    return (
        <AppShell title={t.awards_title || 'Awards'}>
            <div className="mb-4">
                <select
                    className="form-input"
                    aria-label={t.pick_child || 'Child'}
                    value={studentId || ''}
                    onChange={(e) => router.get(`/portal/awards?student_id=${e.target.value}`)}
                >
                    {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                </select>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.award}</th>
                            <th className="px-3 py-2">{col.student}</th>
                            <th className="px-3 py-2">{col.date}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {awards.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={3}>{t.awards_none || 'No awards yet.'}</td></tr>
                        )}
                        {awards.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2" data-label={col.award}>{row.award}</td>
                                <td className="px-3 py-2" data-label={col.student}>{row.student_name}</td>
                                <td className="px-3 py-2" data-label={col.date}>{row.awarded_date}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
