import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Behavior({ children, studentId, records }) {
    return (
        <AppShell title="Behavior">
            <div className="mb-4">
                <select className="form-input" value={studentId || ''} onChange={(e) => router.get(`/portal/behavior?student_id=${e.target.value}`)}>
                    {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                </select>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Category</th>
                            <th className="px-3 py-2">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        {records.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No parent-visible records.</td></tr>
                        )}
                        {records.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.date}</td>
                                <td className="px-3 py-2">{row.type}</td>
                                <td className="px-3 py-2">{row.category}</td>
                                <td className="px-3 py-2">{row.description}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
