import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * A parent's view of a child's Hifz (the Hifz port, slice 2, STATUS §5jw):
 * a picker when there is more than one child, today's record with the
 * note meant for the family, the week, and the approved milestones.
 */
export default function ParentDashboard({ children = [], selected_child, today = null, week = [], milestones = [], t = {} }) {
    return (
        <AppShell title={t.hifz_parent_title || 'Hifz Progress'}>
            {children.length > 1 && (
                <label className="mb-4 block text-sm">
                    <span className="sr-only">{t.hifz_child || 'Child'}</span>
                    <select name="student_id" className="form-input" value={String(selected_child.id)} onChange={(e) => router.get('/hifz/parent', { student_id: e.target.value })} data-testid="hifz-child">
                        {children.map((child) => <option key={child.id} value={String(child.id)}>{child.name}</option>)}
                    </select>
                </label>
            )}
            <h2 className="mb-4 text-lg font-semibold" data-testid="hifz-child-name">{selected_child.name}</h2>
            {today ? (
                <div className={`mb-4 rounded-lg border bg-white p-4 ${today.requires_parent_attention ? 'border-s-4 border-s-red-500' : ''}`} data-testid="hifz-today">
                    <p><strong>{t.hifz_today_label || 'Today'}:</strong> {today.attendance} · {today.overall || (t.hifz_recorded || 'Recorded')}</p>
                    {today.parent_visible_note && <p className="mt-2">{today.parent_visible_note}</p>}
                    {today.next_target && <p className="mt-2 text-sm"><strong>{t.hifz_next_label || 'Next'}:</strong> {today.next_target}</p>}
                    {today.requires_parent_attention && <p className="mt-2 font-medium text-red-600">{t.hifz_parent_attention_required || 'Parent attention required'}</p>}
                </div>
            ) : (
                <p className="mb-4 text-gray-500">{t.hifz_no_record_today || 'No Hifz record for today yet.'}</p>
            )}
            <h3 className="mb-2 font-semibold">{t.hifz_this_week || 'This Week'}</h3>
            <div data-testid="hifz-week">
                {week.map((r) => (
                    <div key={r.id} className="border-b py-2 text-sm">{r.day} — {r.overall || '—'}</div>
                ))}
            </div>
            {milestones.length > 0 && (
                <>
                    <h3 className="mb-2 mt-6 font-semibold">{t.hifz_approved_milestones || 'Approved Milestones'}</h3>
                    {milestones.map((m) => <div key={m.id} className="py-1 text-sm">{m.title || m.type} — {m.approved_at}</div>)}
                </>
            )}
        </AppShell>
    );
}
