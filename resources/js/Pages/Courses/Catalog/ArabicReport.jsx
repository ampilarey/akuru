import AppShell from '../../../Layouts/AppShell';

export default function ArabicReport({ rows, t = {} }) {
    return (
        <AppShell title={t.arrep_title || 'Arabic skill report'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/arabic/reports?format=csv">{t.catalog_export || 'Export CSV'}</a>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.arrep_col_activity || 'Activity'}</th>
                            <th className="px-3 py-2">{t.arrep_col_skill || 'Skill'}</th>
                            <th className="px-3 py-2">{t.arrep_col_letter || 'Letter'}</th>
                            <th className="px-3 py-2">{t.arrep_col_attempts || 'Attempts'}</th>
                            <th className="px-3 py-2">{t.arrep_col_average || 'Avg score'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.arrep_none || 'No skill-tagged activities yet.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.activity_id} className="border-t">
                                <td className="px-3 py-2">{row.title}</td>
                                {/* A skill is a code the server sends (slice CT6a). */}
                                <td className="px-3 py-2">{row.skill ? (t[`skill_${row.skill}`] || row.skill) : '—'}</td>
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
