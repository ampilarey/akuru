import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * The milestone report (the Hifz port, slice 3, STATUS §5jx): every
 * milestone under the programmes this person may see, paged, with its
 * status — the read-only counterpart of the milestones list.
 */
export default function ReportMilestones({ milestones = { data: [] }, t = {} }) {
    const status = (value) => t[`hifz_ms_${value}`] || value.replace(/_/g, ' ');

    return (
        <AppShell title={t.hifz_report_title_milestones || 'Milestone Report'}>
            <p className="mb-4 text-sm"><Link href="/hifz/reports" className="underline" data-testid="hifz-reports-back">{t.hifz_back_reports || '← Hifz Reports'}</Link></p>
            <div className="relative overflow-x-auto rounded-lg border bg-white" data-testid="milestone-report">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.hifz_col_student || 'Student'}</th>
                            <th className="px-3 py-2">{t.hifz_col_program || 'Program'}</th>
                            <th className="px-3 py-2">{t.hifz_col_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.hifz_col_status || 'Status'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {milestones.data.length === 0 && (
                            <tr><td className="px-3 py-6 text-center text-gray-500" colSpan="4">{t.hifz_milestones_none || 'No milestones yet.'}</td></tr>
                        )}
                        {milestones.data.map((m) => (
                            <tr key={m.id} className="border-t" data-testid="milestone-report-row" data-status={m.status}>
                                <td className="px-3 py-2" data-label={t.hifz_col_student || 'Student'}>{m.student}</td>
                                <td className="px-3 py-2" data-label={t.hifz_col_program || 'Program'}>{m.program}</td>
                                <td className="px-3 py-2" data-label={t.hifz_col_type || 'Type'}>{m.type}</td>
                                <td className="px-3 py-2" data-label={t.hifz_col_status || 'Status'}>{status(m.status)}</td>
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
