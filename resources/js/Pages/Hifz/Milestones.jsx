import { Link, router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * The Hifz milestones list (the Hifz port, slice 3, STATUS §5jx): every
 * milestone this person may see, with Review on a pending one for the
 * supervisor and Approve on a reviewed one for the dean. Both post to the
 * routes the Blade forms did and come back here with the flash the shell
 * renders.
 */
export default function Milestones({ milestones = { data: [] }, t = {} }) {
    const status = (value) => t[`hifz_ms_${value}`] || value.replace(/_/g, ' ');
    const post = (id, action) => router.post(`/hifz/milestones/${id}/${action}`, {}, { preserveScroll: true });

    return (
        <AppShell title={t.hifz_milestones_title || 'Hifz Milestones'}>
            <div className="relative overflow-x-auto rounded-lg border bg-white" data-testid="milestones-table">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.hifz_col_student || 'Student'}</th>
                            <th className="px-3 py-2">{t.hifz_col_program || 'Program'}</th>
                            <th className="px-3 py-2">{t.hifz_col_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.hifz_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.hifz_col_actions || 'Actions'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {milestones.data.length === 0 && (
                            <tr><td className="px-3 py-6 text-center text-gray-500" colSpan="5">{t.hifz_milestones_none || 'No milestones yet.'}</td></tr>
                        )}
                        {milestones.data.map((m) => (
                            <tr key={m.id} className="border-t" data-testid="milestone-row" data-status={m.status}>
                                <td className="px-3 py-2" data-label={t.hifz_col_student || 'Student'}>{m.student}</td>
                                <td className="px-3 py-2" data-label={t.hifz_col_program || 'Program'}>{m.program}</td>
                                <td className="px-3 py-2" data-label={t.hifz_col_type || 'Type'}>{m.type}</td>
                                <td className="px-3 py-2" data-label={t.hifz_col_status || 'Status'}>{status(m.status)}</td>
                                <td className="table-actions px-3 py-2">
                                    {m.can_review && <button type="button" className="chip-link text-blue-700" onClick={() => post(m.id, 'supervisor-review')}>{t.hifz_review || 'Review'}</button>}
                                    {m.can_approve && <button type="button" className="chip-link text-green-700" onClick={() => post(m.id, 'approve')}>{t.hifz_approve || 'Approve'}</button>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {(milestones.prev_page_url || milestones.next_page_url) && (
                <div className="mt-3 flex gap-2 text-sm">
                    {milestones.prev_page_url && <Link href={milestones.prev_page_url} className="chip-link">{t.hifz_prev || '‹ Previous'}</Link>}
                    {milestones.next_page_url && <Link href={milestones.next_page_url} className="chip-link">{t.hifz_next || 'Next ›'}</Link>}
                </div>
            )}
        </AppShell>
    );
}
