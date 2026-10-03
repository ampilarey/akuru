import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * A programme's enrolments (the Hifz port, slice 1 — STATUS §5jv):
 * each pupil with their teacher and status, and the door to add one.
 *
 * C16 slice N4 (STATUS §5nz; OWNER_ACTIONS 15, the owner: "Add
 * \"withdrawn\""): an enrolment can be ended from here — withdrawn,
 * transferred or completed — on a date, with a note. Until then no screen
 * changed an enrolment's status, so a pupil who had left read "active"
 * for ever.
 */
export default function Enrollments({ program, enrollments, can_update = false, endings = [], today = '', t = {} }) {
    const { errors = {} } = usePage().props;
    const status = (value) => t[`hifz_enrollment_status_${value}`] || value;

    return (
        <AppShell title={`${t.hifz_enrollments_title || 'Enrollments'} — ${program.name}`}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <Link href={`/hifz/programs/${program.id}`} className="text-gray-500 underline" data-testid="program-back">{t.hifz_back_program || '← Program'}</Link>
                {can_update && <Link href={`/hifz/programs/${program.id}/enrollments/create`} className="btn-primary ms-auto" data-testid="enrollment-new">{t.hifz_add_student || 'Add Student'}</Link>}
            </div>
            <FormErrors errors={errors} className="mb-4" />
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm" data-testid="enrollments-table">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.hifz_col_student || 'Student'}</th>
                            <th className="px-3 py-2">{t.hifz_col_teacher || 'Teacher'}</th>
                            <th className="px-3 py-2">{t.hifz_col_status || 'Status'}</th>
                            {can_update && <th className="px-3 py-2 text-end"><span className="sr-only">{t.hifz_col_actions || 'Actions'}</span></th>}
                        </tr>
                    </thead>
                    <tbody>
                        {enrollments.data.length === 0 && (
                            <tr><td className="px-3 py-6 text-center text-gray-500" colSpan={can_update ? 4 : 3}>{t.hifz_enrollments_none || 'Nobody is enrolled yet.'}</td></tr>
                        )}
                        {enrollments.data.map((row) => (
                            <Row key={row.id} row={row} program={program} canUpdate={can_update} endings={endings} today={today} t={t} status={status} />
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

function Row({ row, program, canUpdate, endings, today, t, status }) {
    const [ending, setEnding] = useState(false);
    const [form, setForm] = useState({ status: endings[0] || 'withdrawn', ended_at: today, reason: '' });
    const [busy, setBusy] = useState(false);
    const submit = (e) => {
        e.preventDefault();
        setBusy(true);
        router.post(`/hifz/programs/${program.id}/enrollments/${row.id}/end`, form, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => setEnding(false) });
    };

    return (
        <>
            <tr className="border-t" data-testid="enrollment-row">
                <td className="px-3 py-2" data-label={t.hifz_col_student || 'Student'}>{row.student}</td>
                <td className="px-3 py-2" data-label={t.hifz_col_teacher || 'Teacher'}>{row.teacher || '—'}</td>
                <td className="px-3 py-2" data-label={t.hifz_col_status || 'Status'}>
                    <span data-testid="enrollment-status">{status(row.status)}</span>
                    {row.ended && row.ended_at && <p className="text-xs text-gray-500" data-testid="enrollment-ended">{(t.hifz_ended_on || 'Ended :date').replace(':date', row.ended_at)}{row.end_reason ? ` — ${row.end_reason}` : ''}</p>}
                </td>
                {canUpdate && (
                    <td className="table-actions whitespace-nowrap px-3 py-2 text-end">
                        {!row.ended && !ending && (
                            <button type="button" className="text-xs font-semibold text-red-700 underline" onClick={() => setEnding(true)} data-testid="enrollment-end">{t.hifz_end_enrolment || 'End enrolment'}</button>
                        )}
                    </td>
                )}
            </tr>
            {ending && (
                <tr className="border-t bg-[#FDFBF8]" data-testid="enrollment-end-form">
                    <td colSpan={4} className="px-3 py-3">
                        <form onSubmit={submit} className="flex flex-wrap items-end gap-3 text-sm">
                            <p className="w-full text-xs text-gray-600">{t.hifz_end_intro || 'Ending an enrolment stops the halaqa\'s work and the counts for this pupil; the record stays.'}</p>
                            <label className="block">
                                <span className="mb-1 block text-xs text-gray-500">{t.hifz_end_how || 'How it ended'}</span>
                                <select className="form-input" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })} data-testid="end-status">
                                    {endings.map((value) => <option key={value} value={value}>{t[`hifz_end_${value}`] || status(value)}</option>)}
                                </select>
                            </label>
                            <label className="block">
                                <span className="mb-1 block text-xs text-gray-500">{t.hifz_end_date || 'On'}</span>
                                <input type="date" className="form-input" value={form.ended_at} onChange={(e) => setForm({ ...form, ended_at: e.target.value })} required data-testid="end-date" />
                            </label>
                            <label className="block grow">
                                <span className="mb-1 block text-xs text-gray-500">{t.hifz_end_reason || 'Note (optional)'}</span>
                                <input className="form-input w-full" maxLength={500} value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} data-testid="end-reason" />
                            </label>
                            <button type="submit" className="btn-primary" disabled={busy} data-testid="end-confirm">{t.hifz_end_confirm || 'End'}</button>
                            <button type="button" className="btn-secondary" onClick={() => setEnding(false)}>{t.hifz_end_cancel || 'Cancel'}</button>
                        </form>
                    </td>
                </tr>
            )}
        </>
    );
}
