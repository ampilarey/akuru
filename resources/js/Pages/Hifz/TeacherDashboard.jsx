import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * The teacher's Hifz dashboard (the Hifz port, slice 2, STATUS §5jw): a
 * roll-up only — session recording lives on the engine's schedule since
 * F5. Their pupils, their programmes, whether today's session exists.
 */
export default function TeacherDashboard({ assigned_students = 0, programs = [], today_session = false, schedule_href = '/teach/schedule', t = {} }) {
    return (
        <AppShell title={t.hifz_teacher_title || 'Hifz Teacher Dashboard'}>
            <div className="mb-6 grid gap-4 md:grid-cols-3" data-testid="hifz-cards">
                <div className="rounded-lg border bg-white p-4"><p className="text-sm text-gray-500">{t.hifz_assigned_students || 'Assigned Students'}</p><p className="text-3xl font-bold">{assigned_students}</p></div>
                <div className="rounded-lg border bg-white p-4"><p className="text-sm text-gray-500">{t.hifz_card_programs || 'Programs'}</p><p className="text-3xl font-bold">{programs.length}</p></div>
                <div className="rounded-lg border bg-white p-4"><p className="text-sm text-gray-500">{t.hifz_todays_session || "Today's Session"}</p><p className="text-lg font-semibold">{today_session ? (t.hifz_session_active || 'Active') : (t.hifz_session_not_started || 'Not started')}</p></div>
            </div>
            {programs.map((program) => (
                <div key={program.id} className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-white p-4" data-testid="hifz-program">
                    <h3 className="font-semibold">{program.name}</h3>
                    <Link href={schedule_href} className="btn-primary">{t.hifz_open_schedule || "Open today's schedule"}</Link>
                </div>
            ))}
            <Link href={schedule_href} className="chip-link">{t.hifz_all_sessions || 'View all sessions on the engine schedule →'}</Link>
        </AppShell>
    );
}
