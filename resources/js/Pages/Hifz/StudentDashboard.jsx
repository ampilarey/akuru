import AppShell from '../../Layouts/AppShell';

/**
 * A pupil's own Hifz progress (the Hifz port, slice 2, STATUS §5jw):
 * where they are, the next target, their recent sessions with the
 * teacher's note, and the milestones approved for them.
 */
export default function StudentDashboard({ enrollment = null, next_target = null, recent_records = [], milestones = [], t = {} }) {
    return (
        <AppShell title={t.hifz_student_title || 'My Hifz Progress'}>
            {enrollment && (
                <div className="mb-6 rounded-lg border bg-white p-4" data-testid="hifz-enrollment">
                    <p><strong>{t.hifz_program_label || 'Program'}:</strong> {enrollment.program}</p>
                    <p><strong>{t.hifz_current_label || 'Current'}:</strong> {t.hifz_juz || 'Juz'} {enrollment.current_juz ?? '—'} · {t.hifz_page || 'Page'} {enrollment.current_page ?? '—'}</p>
                    {next_target && <p className="mt-2"><strong>{t.hifz_next_target || 'Next target'}:</strong> {next_target}</p>}
                </div>
            )}
            <h3 className="mb-3 font-semibold">{t.hifz_recent_sessions || 'Recent Sessions'}</h3>
            <div data-testid="hifz-recent">
                {recent_records.map((r) => (
                    <div key={r.id} className="mb-2 rounded-lg border bg-white p-3 text-sm">
                        <span className="font-medium">{r.date}</span>
                        {r.overall && <span className="ms-2 rounded bg-gray-100 px-2 py-0.5">{r.overall}</span>}
                        {r.teacher_note && <p className="mt-1 text-gray-600">{r.teacher_note}</p>}
                    </div>
                ))}
            </div>
            <h3 className="mb-3 mt-6 font-semibold">{t.hifz_approved_milestones || 'Approved Milestones'}</h3>
            <div data-testid="hifz-milestones">
                {milestones.length === 0 && <p className="text-gray-500">{t.hifz_milestones_none || 'No milestones yet.'}</p>}
                {milestones.map((m) => (
                    <div key={m.id} className="py-1 text-sm">{m.title || m.type} — {m.approved_at}</div>
                ))}
            </div>
        </AppShell>
    );
}
