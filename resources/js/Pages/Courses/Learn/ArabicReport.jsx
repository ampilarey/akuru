import { usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function ArabicReport({ rows, student }) {
    const t = usePage().props.i18n?.learn || {};

    return (
        <AppShell title={t.arabic_report_title || 'My Arabic skills'}>
            {!student && <p className="mb-4 text-sm text-gray-600">{t.no_profile || 'No student profile is linked to this account.'}</p>}
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.skills_col_activity || 'Activity'}</th>
                            <th className="px-3 py-2">{t.skills_col_skill || 'Skill'}</th>
                            <th className="px-3 py-2">{t.skills_col_attempts || 'Attempts'}</th>
                            <th className="px-3 py-2">{t.skills_col_average || 'Avg score'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.skills_none || 'No skill activities yet.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.activity_id} className="border-t">
                                <td className="px-3 py-2">{row.title}</td>
                                {/* A skill is a code the server sends (slice CT7a). */}
                                <td className="px-3 py-2">{row.skill ? (t[`skill_${row.skill}`] || row.skill) : '—'}</td>
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
