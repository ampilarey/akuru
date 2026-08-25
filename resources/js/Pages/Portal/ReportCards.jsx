import { router, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function ReportCards({ children, studentId, cards }) {
    const transcript = useForm({
        student_id: studentId || '',
        locale: 'en',
    });

    return (
        <AppShell title="Report cards">
            <div className="mb-4 flex flex-wrap gap-3">
                <select
                    className="form-input"
                    value={studentId || ''}
                    onChange={(e) => router.get(`/portal/report-cards?student_id=${e.target.value}`)}
                >
                    {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                </select>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        window.location.href = `/portal/transcript?student_id=${transcript.data.student_id}&locale=${transcript.data.locale}`;
                    }}
                    className="flex gap-2"
                >
                    <button type="submit" className="btn-secondary">Request transcript</button>
                </form>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Term</th>
                            <th className="px-3 py-2">Published</th>
                            <th className="px-3 py-2">Download</th>
                        </tr>
                    </thead>
                    <tbody>
                        {cards.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No published report cards yet.</td></tr>
                        )}
                        {cards.map((card) => (
                            <tr key={card.id} className="border-t">
                                <td className="px-3 py-2">{card.student_name}</td>
                                <td className="px-3 py-2">{card.term_name}</td>
                                <td className="px-3 py-2">{card.published_at || '—'}</td>
                                <td className="px-3 py-2">
                                    <a className="text-[#7C2D37] underline" href={`/portal/report-cards/${card.id}/download`}>Download</a>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
