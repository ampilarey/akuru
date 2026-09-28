import { Link, router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * The dean's Hifz dashboard (the Hifz port, slice 2, STATUS §5jw): ten
 * cards, the pupils with the most haraka mistakes, the milestones the
 * supervisor has reviewed and the dean may approve, and the doors to
 * programmes, reports and the Qur'an source.
 */
const CARDS = ['active_students', 'active_programs', 'supervisors', 'teachers', 'sessions_today', 'pending_supervisor_review', 'absent_today', 'parent_attention', 'juz_this_month', 'missing_teachers'];
const LABELS = { active_students: 'Active Students', active_programs: 'Programs', supervisors: 'Supervisors', teachers: 'Teachers', sessions_today: 'Sessions Today', pending_supervisor_review: 'Pending Reviews', absent_today: 'Absent Today', parent_attention: 'Parent Attention', juz_this_month: 'Juz This Month', missing_teachers: 'Missing Records' };

export default function DeanDashboard({ cards = {}, haraka_leaders = [], pending_milestones = [], links = {}, t = {} }) {
    return (
        <AppShell title={t.hifz_dean_title || 'Hifz Dean Dashboard'}>
            <div className="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4" data-testid="hifz-cards">
                {CARDS.map((key) => (
                    <div key={key} className="rounded-lg border bg-white p-4" data-testid={`hifz-card-${key}`}>
                        <p className="text-sm text-gray-500">{t[`hifz_card_${key}`] || LABELS[key]}</p>
                        <p className="text-2xl font-bold">{cards[key] ?? 0}</p>
                    </div>
                ))}
            </div>
            <div className="mb-6 grid gap-6 lg:grid-cols-2">
                <div className="rounded-lg border bg-white p-4" data-testid="hifz-haraka">
                    <h3 className="mb-3 font-semibold">{t.hifz_haraka_high || 'High Haraka Mistakes'}</h3>
                    {haraka_leaders.length === 0 && <p className="text-sm text-gray-500">{t.hifz_haraka_none || 'No haraka issues this period.'}</p>}
                    {haraka_leaders.map((row, i) => (
                        <div key={i} className="flex justify-between border-b py-1 text-sm"><span>{row.student}</span><span>{row.total_haraka}</span></div>
                    ))}
                </div>
                <div className="rounded-lg border bg-white p-4" data-testid="hifz-pending-milestones">
                    <h3 className="mb-3 font-semibold">{t.hifz_milestones_awaiting || 'Milestones Awaiting Approval'}</h3>
                    {pending_milestones.length === 0 && <p className="text-sm text-gray-500">{t.hifz_milestones_none_waiting || 'No milestones waiting for review.'}</p>}
                    {pending_milestones.map((m) => (
                        <div key={m.id} className="flex items-center justify-between border-b py-2 text-sm">
                            <span>{m.student} — {m.type}</span>
                            <button type="button" className="chip-link text-green-700" onClick={() => router.post(`/hifz/milestones/${m.id}/approve`, {}, { preserveScroll: true })}>{t.hifz_approve || 'Approve'}</button>
                        </div>
                    ))}
                </div>
            </div>
            <div className="flex flex-wrap gap-2">
                <Link href={links.programs || '/hifz/programs'} className="btn-primary">{t.hifz_programs_button || 'Programs'}</Link>
                {/* Reports are still Blade until the port's third slice: a full load. */}
                <Link href={links.reports || '/hifz/reports'} className="btn-secondary">{t.hifz_reports_button || 'Reports'}</Link>
                <Link href={links.mushafs || '/quran/mushafs'} className="btn-secondary">{t.hifz_quran_source || 'Quran Source'}</Link>
            </div>
        </AppShell>
    );
}
