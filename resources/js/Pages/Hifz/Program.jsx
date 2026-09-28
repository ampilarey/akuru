import { Link, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * One Hifz programme (the Hifz port, slice 1, STATUS §5jv): its description,
 * the doors to edit it and enrol a pupil, the supervisor picker for whoever
 * may assign one, and its enrolments — each pupil with their teacher, status
 * and page.
 */
export default function Program({ program, enrollments = [], can_update = false, can_assign_supervisor = false, supervisors = [], t = {} }) {
    const assign = useForm({ supervisor_id: program.supervisor_id ? String(program.supervisor_id) : '' });
    const status = (value) => t[`hifz_enrollment_status_${value}`] || value;

    return (
        <AppShell title={program.name}>
            <p className="mb-4 text-sm"><Link href="/hifz/programs" className="text-gray-500 underline" data-testid="program-back">{t.hifz_back_programs || '← Hifz Programs'}</Link></p>
            {program.description && <p className="mb-4 text-gray-600" data-testid="program-description">{program.description}</p>}

            <div className="mb-6 flex flex-wrap gap-2">
                {can_update && <Link href={`/hifz/programs/${program.id}/edit`} className="btn-secondary" data-testid="program-edit">{t.hifz_edit || 'Edit'}</Link>}
                {can_update && <Link href={`/hifz/programs/${program.id}/enrollments/create`} className="btn-primary" data-testid="program-enroll">{t.hifz_enroll_student || 'Enroll Student'}</Link>}
                <Link href={`/hifz/programs/${program.id}/enrollments`} className="chip-link" data-testid="program-enrollments">{t.hifz_all_enrollments || 'All enrollments'}</Link>
            </div>

            {can_assign_supervisor && (
                <form
                    onSubmit={(e) => { e.preventDefault(); assign.post(`/hifz/programs/${program.id}/assign-supervisor`, { preserveScroll: true }); }}
                    className="mb-6 flex flex-wrap items-end gap-2 rounded-lg border bg-white p-4"
                    data-testid="assign-supervisor"
                >
                    <label className="text-sm">
                        <span className="mb-1 block font-medium text-gray-700">{t.hifz_supervisor || 'Supervisor'}</span>
                        <select name="supervisor_id" className="form-input" value={assign.data.supervisor_id} onChange={(e) => assign.setData('supervisor_id', e.target.value)}>
                            <option value="">{t.hifz_none_option || '—'}</option>
                            {supervisors.map((s) => <option key={s.id} value={String(s.id)}>{s.name}</option>)}
                        </select>
                    </label>
                    <button type="submit" className="btn-secondary" disabled={assign.processing || !assign.data.supervisor_id}>{t.hifz_assign_supervisor || 'Assign Supervisor'}</button>
                    {assign.errors.supervisor_id && <p className="text-xs text-red-700">{assign.errors.supervisor_id}</p>}
                </form>
            )}

            <div className="relative overflow-x-auto rounded-lg border bg-white" data-testid="enrollments-table">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.hifz_col_student || 'Student'}</th>
                            <th className="px-3 py-2">{t.hifz_col_teacher || 'Teacher'}</th>
                            <th className="px-3 py-2">{t.hifz_col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.hifz_col_page || 'Page'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {enrollments.length === 0 && (
                            <tr><td className="px-3 py-6 text-center text-gray-500" colSpan="4">{t.hifz_enrollments_none || 'Nobody is enrolled yet.'}</td></tr>
                        )}
                        {enrollments.map((row) => (
                            <tr key={row.id} className="border-t" data-testid="enrollment-row">
                                <td className="px-3 py-2" data-label={t.hifz_col_student || 'Student'}>{row.student}</td>
                                <td className="px-3 py-2" data-label={t.hifz_col_teacher || 'Teacher'}>{row.teacher || '—'}</td>
                                <td className="px-3 py-2" data-label={t.hifz_col_status || 'Status'}>{status(row.status)}</td>
                                <td className="px-3 py-2" data-label={t.hifz_col_page || 'Page'}>{row.page ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
