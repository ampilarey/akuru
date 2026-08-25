import AppShell from '../../../Layouts/AppShell';

export default function ArabicReport({ rows }) {
    return (
        <AppShell title="Arabic skill report">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/arabic/reports?format=csv">Export CSV</a>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Activity</th>
                            <th className="px-3 py-2">Skill</th>
                            <th className="px-3 py-2">Letter</th>
                            <th className="px-3 py-2">Attempts</th>
                            <th className="px-3 py-2">Avg score</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No skill-tagged activities yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.activity_id} className="border-t">
                                <td className="px-3 py-2">{row.title}</td>
                                <td className="px-3 py-2">{row.skill}</td>
                                <td className="px-3 py-2">{row.letter?.arabic_character || '—'}</td>
                                <td className="px-3 py-2">{row.attempts}</td>
                                <td className="px-3 py-2">{row.average_score ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
