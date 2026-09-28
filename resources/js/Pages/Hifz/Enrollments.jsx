import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * A programme's enrolments, paged (the Hifz port, slice 1, STATUS §5jv):
 * each pupil with their teacher and status, and the door to add one.
 */
export default function Enrollments({ program, enrollments = { data: [] }, can_update = false, t = {} }) {
    const status = (value) => t[`hifz_enrollment_status_${value}`] || value;

    return (
        <AppShell title={`${t.hifz_enrollments_title || 'Enrollments'} — ${program.name}`}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <Link href={`/hifz/programs/${program.id}`} className="text-gray-500 underline" data-testid="program-back">{t.hifz_back_program || '← Program'}</Link>
                {can_update && <Link href={`/hifz/programs/${program.id}/enrollments/create`} className="btn-primary ms-auto" data-testid="enrollment-new">{t.hifz_add_student || 'Add Student'}</Link>}
            </div>

            <div className="relative overflow-x-auto rounded-lg border bg-white" data-testid="enrollments-table">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.hifz_col_student || 'Student'}</th>
                            <th className="px-3 py-2">{t.hifz_col_teacher || 'Teacher'}</th>
                            <th className="px-3 py-2">{t.hifz_col_status || 'Status'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {enrollments.data.length === 0 && (
                            <tr><td className="px-3 py-6 text-center text-gray-500" colSpan="3">{t.hifz_enrollments_none || 'Nobody is enrolled yet.'}</td></tr>
                        )}
                        {enrollments.data.map((row) => (
                            <tr key={row.id} className="border-t" data-testid="enrollment-row">
                                <td className="px-3 py-2" data-label={t.hifz_col_student || 'Student'}>{row.student}</td>
                                <td className="px-3 py-2" data-label={t.hifz_col_teacher || 'Teacher'}>{row.teacher || '—'}</td>
                                <td className="px-3 py-2" data-label={t.hifz_col_status || 'Status'}>{status(row.status)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {(enrollments.prev_page_url || enrollments.next_page_url) && (
                <div className="mt-3 flex gap-2 text-sm">
                    {enrollments.prev_page_url && <Link href={enrollments.prev_page_url} className="chip-link">{t.hifz_prev || '‹ Previous'}</Link>}
                    {enrollments.next_page_url && <Link href={enrollments.next_page_url} className="chip-link">{t.hifz_next || 'Next ›'}</Link>}
                </div>
            )}
        </AppShell>
    );
}
