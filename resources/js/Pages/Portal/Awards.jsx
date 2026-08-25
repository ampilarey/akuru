import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Awards({ children, studentId, awards }) {
    return (
        <AppShell title="Awards">
            <div className="mb-4">
                <select
                    className="form-input"
                    value={studentId || ''}
                    onChange={(e) => router.get(`/portal/awards?student_id=${e.target.value}`)}
                >
                    {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                </select>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Award</th>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        {awards.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={3}>No awards yet.</td></tr>
                        )}
                        {awards.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.award}</td>
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.awarded_date}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
