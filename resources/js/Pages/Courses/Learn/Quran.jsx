import { usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

const range = (row) =>
    row.start_ayah_number ? `${row.start_ayah_number}–${row.end_ayah_number ?? row.start_ayah_number}` : '—';

const statusBadge = (status) => {
    const tone = {
        passed: 'bg-green-100 text-green-800',
        strong: 'bg-green-100 text-green-800',
        needs_repeat: 'bg-amber-100 text-amber-800',
        needs_revision: 'bg-amber-100 text-amber-800',
        failed: 'bg-red-100 text-red-800',
        weak: 'bg-red-100 text-red-800',
    }[status] || 'bg-gray-100 text-gray-700';
    return <span className={`rounded px-2 py-0.5 text-xs ${tone}`}>{status?.replaceAll('_', ' ') ?? '—'}</span>;
};

export default function Quran({ student, submissions, progress, schedules }) {
    const t = usePage().props.i18n?.learn || {};

    return (
        <AppShell title={t.quran_dashboard || "My Qur'an"}>
            {!student && (
                <p className="mb-4 text-sm text-gray-600">{t.no_profile || 'No student profile is linked to this account.'}</p>
            )}

            <h2 className="mb-2 text-lg font-semibold">{t.quran_progress || 'Memorization progress'}</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.surah || 'Surah'}</th>
                            <th className="px-3 py-2">{t.ayahs || 'Ayahs'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                            <th className="px-3 py-2">{t.strength || 'Strength'}</th>
                            <th className="px-3 py-2">{t.mistakes || 'Mistakes'}</th>
                            <th className="px-3 py-2">{t.last_reviewed || 'Last reviewed'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {progress.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.no_progress || 'No memorization progress yet.'}</td></tr>
                        )}
                        {progress.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.surah ?? '—'}</td>
                                <td className="px-3 py-2">{range(row)}</td>
                                <td className="px-3 py-2">{statusBadge(row.status)}</td>
                                <td className="px-3 py-2">{row.strength_score ?? '—'}</td>
                                <td className="px-3 py-2">{row.mistake_count ?? '—'}</td>
                                <td className="px-3 py-2">{row.last_reviewed_at ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <h2 className="mb-2 text-lg font-semibold">{t.quran_submissions || 'My recitation submissions'}</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.surah || 'Surah'}</th>
                            <th className="px-3 py-2">{t.ayahs || 'Ayahs'}</th>
                            <th className="px-3 py-2">{t.submitted || 'Submitted'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                            <th className="px-3 py-2">{t.mistakes || 'Mistakes'}</th>
                            <th className="px-3 py-2">{t.teacher_note || 'Teacher note'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {submissions.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.no_submissions || 'No submissions yet.'}</td></tr>
                        )}
                        {submissions.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.surah ?? '—'}</td>
                                <td className="px-3 py-2">{range(row)}</td>
                                <td className="px-3 py-2">{row.submitted_at}</td>
                                <td className="px-3 py-2">{statusBadge(row.status)}</td>
                                <td className="px-3 py-2">{row.mistake_count}</td>
                                <td className="px-3 py-2">{row.review_note ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <h2 className="mb-2 text-lg font-semibold">{t.quran_revision || 'Upcoming revision'}</h2>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.date || 'Date'}</th>
                            <th className="px-3 py-2">{t.surah || 'Surah'}</th>
                            <th className="px-3 py-2">{t.ayahs || 'Ayahs'}</th>
                            <th className="px-3 py-2">{t.frequency || 'Frequency'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                            <th className="px-3 py-2">{t.notes || 'Notes'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {schedules.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.no_revision || 'No revision scheduled.'}</td></tr>
                        )}
                        {schedules.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.scheduled_date}</td>
                                <td className="px-3 py-2">{row.surah ?? '—'}</td>
                                <td className="px-3 py-2">{range(row)}</td>
                                <td className="px-3 py-2">{row.frequency ?? '—'}</td>
                                <td className="px-3 py-2">{statusBadge(row.status)}</td>
                                <td className="px-3 py-2">{row.notes ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
