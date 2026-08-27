import { router, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Meetings({ children = [], slots = [], bookings = [], csvUrl = '/portal/meetings/export' }) {
    const form = useForm({
        student_id: children[0]?.id || '',
    });

    return (
        <AppShell title="Meetings">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">Book a published parent-teacher meeting slot for a linked child.</p>
                <a className="btn-secondary" href={csvUrl}>Export CSV</a>
            </div>

            {bookings.length > 0 && (
                <section className="mb-6 rounded-lg border bg-white p-4">
                    <h2 className="mb-2 text-sm font-medium">Your bookings</h2>
                    <ul className="space-y-2 text-sm">
                        {bookings.map((row) => (
                            <li key={row.id} className="flex flex-wrap items-center justify-between gap-2">
                                <span>{row.student_name} · {row.date} {row.start_time}–{row.end_time} · {row.teacher_name}</span>
                                <button
                                    type="button"
                                    className="text-[#7C2D37] hover:underline"
                                    onClick={() => router.post(`/portal/meetings/bookings/${row.id}/cancel`)}
                                >
                                    Cancel
                                </button>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {children.length === 0 && <p className="text-sm text-gray-600">No student or linked children.</p>}

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">When</th>
                            <th className="px-3 py-2">Teacher</th>
                            <th className="px-3 py-2">Class</th>
                            <th className="px-3 py-2">Seats</th>
                            <th className="px-3 py-2">Child</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {slots.length === 0 && (
                            <tr><td className="px-3 py-3 text-gray-500" colSpan={6}>No published meeting slots.</td></tr>
                        )}
                        {slots.map((slot) => (
                            <tr key={slot.id} className="border-t">
                                <td className="px-3 py-2">{slot.date} {slot.start_time}–{slot.end_time}</td>
                                <td className="px-3 py-2">{slot.teacher_name}</td>
                                <td className="px-3 py-2">{slot.class_name || '—'}</td>
                                <td className="px-3 py-2">{slot.remaining} left</td>
                                <td className="px-3 py-2">
                                    <select
                                        className="form-input"
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
                                <td className="px-3 py-2 text-end">
                                    {slot.booked_student_ids.includes(Number(form.data.student_id)) ? (
                                        <span className="text-gray-500">Booked</span>
                                    ) : (
                                        <button
                                            type="button"
                                            className="btn-primary"
                                            disabled={!slot.can_book || form.processing}
                                            onClick={() => form.post(`/portal/meetings/${slot.id}/book`)}
                                        >
                                            Book
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
