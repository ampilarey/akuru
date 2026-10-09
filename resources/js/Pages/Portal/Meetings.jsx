import { router, useForm } from '@inertiajs/react';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';
import AppShell from '../../Layouts/AppShell';

export default function Meetings({ children = [], slots = [], bookings = [], csvUrl = '/portal/meetings/export', t = {} }) {
    const form = useForm({
        student_id: children[0]?.id || '',
    });
    // Cancel posts with `router`, so a refusal had no form to show it: it is
    // said under the booking now (BACKLOG C21, slice PT3). Booking stays a
    // form post, and its refusal stays above the table.
    const refusals = useRowRefusals(form);
    const cancel = (row) => refusals.actOn(`booking:${row.id}`, () => router.post(`/portal/meetings/bookings/${row.id}/cancel`, {}, { preserveScroll: true, preserveState: 'errors' }));
    const col = {
        when: t.col_when || 'When',
        teacher: t.col_teacher || 'Teacher',
        class: t.col_class || 'Class',
        seats: t.col_seats || 'Seats',
        child: t.pick_child || 'Child',
    };

    return (
        <AppShell title={t.meetings_title || 'Meetings'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">{t.meetings_intro || 'Book a published parent-teacher meeting slot for a linked child.'}</p>
                <a className="btn-secondary" href={csvUrl}>{t.export_csv || 'Export CSV'}</a>
            </div>

            {/* Booking is a button, not a form — a slot taken a second earlier
                by another parent came back refused with nothing on screen. */}
            <FormErrors errors={form.errors} className="mb-4" />
            <FormErrors errors={refusals.unplaced} className="mb-4" />

            {bookings.length > 0 && (
                <section className="mb-6 rounded-lg border bg-white p-4">
                    <h2 className="mb-2 text-sm font-medium">{t.meetings_yours || 'Your bookings'}</h2>
                    <ul className="space-y-2 text-sm">
                        {bookings.map((row) => (
                            <li key={row.id}>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span>{row.student_name} · {row.date} {row.start_time}–{row.end_time} · {row.teacher_name}</span>
                                    <button type="button" className="chip-link" onClick={() => cancel(row)}>
                                        {t.meetings_cancel || 'Cancel'}
                                    </button>
                                </div>
                                <FormErrors errors={refusals.errorsFor(`booking:${row.id}`)} className="mt-1" />
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {children.length === 0 && <p className="text-sm text-gray-600">{t.meetings_no_children || 'No student or linked children.'}</p>}

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.when}</th>
                            <th className="px-3 py-2">{col.teacher}</th>
                            <th className="px-3 py-2">{col.class}</th>
                            <th className="px-3 py-2">{col.seats}</th>
                            <th className="px-3 py-2">{col.child}</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {slots.length === 0 && (
                            <tr><td className="px-3 py-3 text-gray-500" colSpan={6}>{t.meetings_none || 'No published meeting slots.'}</td></tr>
                        )}
                        {slots.map((slot) => (
                            <tr key={slot.id} className="border-t">
                                <td className="px-3 py-2" data-label={col.when}>{slot.date} {slot.start_time}–{slot.end_time}</td>
                                <td className="px-3 py-2" data-label={col.teacher}>{slot.teacher_name}</td>
                                <td className="px-3 py-2" data-label={col.class}>{slot.class_name || '—'}</td>
                                <td className="px-3 py-2" data-label={col.seats}>{(t.meetings_seats_left || ':count left').replace(':count', slot.remaining)}</td>
                                <td className="px-3 py-2" data-label={col.child}>
                                    <select
                                        className="form-input"
                                        aria-label={col.child}
                                        value={form.data.student_id}
                                        onChange={(e) => form.setData('student_id', e.target.value)}
                                    >
                                        {children
                                            .filter((child) => slot.eligible_student_ids.includes(child.id))
                                            .map((child) => (
                                                <option key={child.id} value={child.id}>{child.name}</option>
                                            ))}
                                    </select>
                                </td>
                                <td className="table-actions px-3 py-2 text-end">
                                    {slot.booked_student_ids.includes(Number(form.data.student_id)) ? (
                                        <span className="text-gray-500">{t.meetings_booked || 'Booked'}</span>
                                    ) : (
                                        <button
                                            type="button"
                                            className="btn-primary"
                                            disabled={!slot.can_book || form.processing}
                                            onClick={() => form.post(`/portal/meetings/${slot.id}/book`)}
                                        >
                                            {t.meetings_book || 'Book'}
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
