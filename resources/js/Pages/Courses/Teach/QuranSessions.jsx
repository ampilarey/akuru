import { Link } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * The halaqa sessions a teacher teaches — or, for whoever runs the courses,
 * every one — from two weeks back on, each opening its sheet (STATUS §5pu).
 * The sheet had no door: the schedule links a session to attendance only.
 */
export default function QuranSessions({ sessions = [], scope = 'mine', look_back_days: lookBack = 14, t = {} }) {
    const hint = scope === 'all'
        ? t.qsessions_hint_all || 'Every halaqa session, from :days days back on.'
        : t.qsessions_hint_mine || 'The halaqa sessions you teach, from :days days back on.';

    return (
        <AppShell title={t.qsessions_title || 'Halaqa sessions'}>
            <p className="mb-4 text-sm text-gray-600">{hint.replace(':days', lookBack)}</p>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm" data-testid="quran-sessions">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.qsessions_col_when || 'When'}</th>
                            <th className="px-3 py-2">{t.qsessions_col_session || 'Session'}</th>
                            <th className="px-3 py-2">{t.qsessions_col_halaqa || 'Halaqa'}</th>
                            <th className="px-3 py-2">{t.qsessions_col_place || 'Place'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {sessions.length === 0 && (
                            <tr>
                                <td className="px-3 py-4 text-gray-500" colSpan={5}>
                                    {scope === 'all'
                                        ? t.qsessions_empty_all || 'No halaqa sessions to show.'
                                        : t.qsessions_empty_mine || 'No halaqa sessions of yours to show. A session is yours when the office names you its teacher.'}
                                </td>
                            </tr>
                        )}
                        {sessions.map((row) => (
                            <tr key={row.id} className="border-t" data-testid={`quran-session-${row.id}`}>
                                <td className="px-3 py-2 whitespace-nowrap" dir="ltr">{row.when ?? '—'}</td>
                                <td className="px-3 py-2">{row.title || '—'}</td>
                                <td className="px-3 py-2">{row.course_title} · {row.offering_title}</td>
                                <td className="px-3 py-2">{row.location_name || (row.online ? t.qsessions_online || 'Online' : '—')}</td>
                                <td className="px-3 py-2 text-end">
                                    <Link className="btn-secondary" href={`/teach/quran-sessions/${row.id}`}>{t.qsessions_open || 'Open sheet'}</Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
