import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * The supervisor's full dashboard — today's numbers (C9 slice 13, STATUS
 * §5jo): the roll, the staff and today's Quran progress, and the door to
 * the Hifz supervisor dashboard for those who may open it. The School
 * office is the home; this page carries the way back.
 */
export default function SupervisorDashboard({ stats = {}, can_hifz = false, hifz_href = '/hifz', home = '/school', t = {} }) {
    const cards = [
        ['students_on_roll', t.supervisor_students || 'Students on the roll'],
        ['teachers_teaching', t.supervisor_teachers || 'Teachers on staff'],
        ['quran_progress_today', t.supervisor_quran_today || 'Quran Progress Today'],
    ];

    return (
        <AppShell title={t.supervisor_title || 'Supervisor Dashboard'}>
            <p className="mb-4 flex flex-wrap items-center gap-3 text-xs text-gray-500" data-testid="dashboard-hint">
                <Link href={home} className="rounded bg-[#7C2D37] px-3 py-1.5 text-sm font-semibold text-white" data-testid="open-admin-panel">{t.supervisor_school || '🏠 School office →'}</Link>
                <span>{t.supervisor_hint || 'This dashboard is today’s numbers. To run the school, open the School office.'}</span>
            </p>
            <div className="mb-5 grid grid-cols-1 gap-3 md:grid-cols-3" data-testid="supervisor-cards">
                {cards.map(([key, label]) => (
                    <div key={key} className="rounded-xl border bg-white p-4" data-testid={`card-${key}`}>
                        <h6 className="mb-1 text-xs text-gray-500">{label}</h6>
                        <p className="text-3xl font-bold text-gray-900" dir="ltr">{stats[key]}</p>
                    </div>
                ))}
            </div>
            {can_hifz && (
                <div className="rounded-xl border bg-white p-4" data-testid="supervisor-hifz">
                    <h6 className="mb-3 text-sm font-bold text-[#1D4E89]">{t.supervisor_hifz_title || 'Hifz Progress'}</h6>
                    {/* Inertia since the Hifz port's second slice (STATUS §5jw). */}
                    <Link href={hifz_href} className="btn-primary">{t.supervisor_hifz_open || 'Open Hifz Supervisor Dashboard'}</Link>
                </div>
            )}
        </AppShell>
    );
}
