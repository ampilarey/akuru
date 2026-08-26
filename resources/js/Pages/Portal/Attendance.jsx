import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Attendance({ children, studentId, rows, summary }) {
    return (
        <AppShell title="Attendance">
            <div className="mb-4">
                <select
                    className="form-input"
                    value={studentId || ''}
                    onChange={(e) => router.get(`/portal/attendance?student_id=${e.target.value}`)}
                >
                    {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                </select>
            </div>
            {summary && (
                <p className="mb-4 text-sm text-gray-600">
                    Present {summary.present} · Late {summary.late} · Absent {summary.absent} · Excused {summary.excused} · {summary.percent}%
                </p>
            )}
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Class</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Parent notified</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No attendance recorded yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.date}</td>
                                <td className="px-3 py-2">{row.class_name}</td>
                                <td className="px-3 py-2 uppercase">{row.status}</td>
                                <td className="px-3 py-2">
                                    {row.guardian_notified ? 'Yes' : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
