import AppShell from '../../Layouts/AppShell';

/**
 * The supervisor's Hifz dashboard, scoped to their programmes (the Hifz
 * port, slice 2, STATUS §5jw): seven cards, the haraka alerts and weak
 * students, the pending milestones by pupil with their Review, and the
 * doors to reports and milestones — both still Blade until slice 3.
 */
const CARDS = ['programs', 'teachers', 'sessions_today', 'pending_review', 'missing_teachers', 'needs_supervisor', 'parent_attention'];
const LABELS = { programs: 'Programs', teachers: 'Teachers', sessions_today: 'Sessions Today', pending_review: 'Pending Review', missing_teachers: 'Teachers Missing Records', needs_supervisor: 'Needs Supervisor Review', parent_attention: 'Parent Attention' };
const KEYS = { missing_teachers: 'hifz_card_missing_teachers_supervisor' };

export default function SupervisorDashboard({ cards = {}, haraka_leaders = [], weak_students = [], pending_milestones = [], links = {}, t = {} }) {
    const unit = (key, count, fallback) => (t[key] || fallback).replace(':count', String(count));

    return (
        <AppShell title={t.hifz_supervisor_title || 'Hifz Supervisor Dashboard'}>
            <div className="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4" data-testid="hifz-cards">
                {CARDS.map((key) => (
                    <div key={key} className="rounded-lg border bg-white p-4" data-testid={`hifz-card-${key}`}>
                        <p className="text-sm text-gray-500">{t[KEYS[key] || `hifz_card_${key}`] || LABELS[key]}</p>
                        <p className="text-2xl font-bold">{cards[key] ?? 0}</p>
                    </div>
                ))}
            </div>
            <div className="grid gap-6 lg:grid-cols-2">
                <div className="rounded-lg border bg-white p-4" data-testid="hifz-haraka">
                    <h3 className="mb-3 font-semibold">{t.hifz_haraka_alerts || 'Haraka Mistake Alerts'}</h3>
                    {haraka_leaders.length === 0 && <p className="text-sm text-gray-500">{t.hifz_haraka_none || 'No haraka issues this period.'}</p>}
                    {haraka_leaders.map((row, i) => (
                        <div key={i} className="flex justify-between border-b py-2 text-sm"><span>{row.student}</span><span className="font-medium text-red-600">{unit('hifz_haraka_unit', row.total_haraka, ':count haraka')}</span></div>
                    ))}
                </div>
                <div className="rounded-lg border bg-white p-4" data-testid="hifz-weak">
                    <h3 className="mb-3 font-semibold">{t.hifz_weak_students || 'Weak Students'}</h3>
                    {weak_students.length === 0 && <p className="text-sm text-gray-500">{t.hifz_weak_none || 'No weak students flagged.'}</p>}
                    {weak_students.map((row, i) => (
                        <div key={i} className="flex justify-between border-b py-2 text-sm"><span>{row.student}</span><span>{unit('hifz_weak_unit', row.weak_count, ':count weak sessions')}</span></div>
                    ))}
                </div>
            </div>
            {/* The controller has always built this list; the Blade never showed it
                until STATUS §5fz. A supervisor sees whose milestone is pending. */}
            <div className="mt-6 rounded-lg border bg-white p-4" data-testid="hifz-pending-milestones">
                <h3 className="mb-3 font-semibold">{t.hifz_milestones_pending || 'Pending Milestones'}</h3>
                {pending_milestones.length === 0 && <p className="text-sm text-gray-500">{t.hifz_milestones_none_waiting || 'No milestones waiting for review.'}</p>}
                {pending_milestones.map((m) => (
                    <div key={m.id} className="flex items-center justify-between border-b py-2 text-sm">
                        <span>{m.student} — {m.type}</span>
                        <a href={links.milestones || '/hifz/milestones'} className="chip-link">{t.hifz_review || 'Review'}</a>
                    </div>
                ))}
            </div>
            <div className="mt-6 flex flex-wrap gap-3">
                <a href={links.reports || '/hifz/reports'} className="btn-secondary">{t.hifz_reports_button || 'Reports'}</a>
                <a href={links.milestones || '/hifz/milestones'} className="btn-secondary">{t.hifz_milestones_button || 'Milestones'}</a>
            </div>
        </AppShell>
    );
}
