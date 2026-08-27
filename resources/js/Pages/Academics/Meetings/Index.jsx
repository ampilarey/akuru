import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

function Field({ label, error, children }) {
    return (
        <label className="block text-sm">
            <span className="mb-1 block text-gray-600">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}

export default function Index({ yearId, years = [], terms = [], teachers = [], classes = [], rooms = [], slots = [] }) {
    const form = useForm({
        academic_year_id: yearId || '',
        term_id: terms[0]?.id || '',
        teacher_id: teachers[0]?.id || '',
        class_id: classes[0]?.id || '',
        room_id: '',
        title: 'Parent-teacher meeting',
        title_arabic: '',
        title_dhivehi: '',
        date: '',
        start_time: '18:00',
        end_time: '19:00',
        slot_minutes: 10,
        capacity: 1,
        status: 'published',
        notes: '',
    });

    return (
        <AppShell title="Meetings">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <select
                    className="form-input"
                    value={yearId || ''}
                    onChange={(e) => router.get(`/academics/meetings?academic_year_id=${e.target.value}`)}
                >
                    <option value="">Year</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <a className="btn-secondary" href={`/academics/meetings/export?academic_year_id=${yearId || ''}`}>
                    Export CSV
                </a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/academics/meetings', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <Field label="Title (EN)" error={form.errors.title}>
                    <input className="form-input w-full" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </Field>
                <Field label="Title (AR)">
                    <input className="form-input w-full" dir="rtl" value={form.data.title_arabic} onChange={(e) => form.setData('title_arabic', e.target.value)} />
                </Field>
                <Field label="Title (DV)">
                    <input className="form-input w-full" dir="rtl" value={form.data.title_dhivehi} onChange={(e) => form.setData('title_dhivehi', e.target.value)} />
                </Field>
                <Field label="Teacher" error={form.errors.teacher_id}>
                    <select className="form-input w-full" value={form.data.teacher_id} onChange={(e) => form.setData('teacher_id', e.target.value)}>
                        {teachers.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name}</option>)}
                    </select>
                </Field>
                <Field label="Class">
                    <select className="form-input w-full" value={form.data.class_id} onChange={(e) => form.setData('class_id', e.target.value)}>
                        <option value="">Any class</option>
                        {classes.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                    </select>
                </Field>
                <Field label="Room">
                    <select className="form-input w-full" value={form.data.room_id} onChange={(e) => form.setData('room_id', e.target.value)}>
                        <option value="">None</option>
                        {rooms.map((room) => <option key={room.id} value={room.id}>{room.name}</option>)}
                    </select>
                </Field>
                <Field label="Term">
                    <select className="form-input w-full" value={form.data.term_id} onChange={(e) => form.setData('term_id', e.target.value)}>
                        <option value="">None</option>
                        {terms.map((term) => <option key={term.id} value={term.id}>{term.name}</option>)}
                    </select>
                </Field>
                <Field label="Date" error={form.errors.date}>
                    <input className="form-input w-full" type="date" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                </Field>
                <Field label="Start" error={form.errors.start_time}>
                    <input className="form-input w-full" type="time" value={form.data.start_time} onChange={(e) => form.setData('start_time', e.target.value)} />
                </Field>
                <Field label="End" error={form.errors.end_time}>
                    <input className="form-input w-full" type="time" value={form.data.end_time} onChange={(e) => form.setData('end_time', e.target.value)} />
                </Field>
                <Field label="Minutes per slot" error={form.errors.slot_minutes}>
                    <input className="form-input w-full" type="number" min="5" value={form.data.slot_minutes} onChange={(e) => form.setData('slot_minutes', e.target.value)} />
                </Field>
                <Field label="Capacity">
                    <input className="form-input w-full" type="number" min="1" value={form.data.capacity} onChange={(e) => form.setData('capacity', e.target.value)} />
                </Field>
                <Field label="Status">
                    <select className="form-input w-full" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                        <option value="draft">draft</option>
                        <option value="published">published</option>
                    </select>
                </Field>
                <div className="md:col-span-4">
                    <button className="btn-primary" type="submit" disabled={form.processing}>Generate slots</button>
                </div>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">When</th>
                            <th className="px-3 py-2">Teacher</th>
                            <th className="px-3 py-2">Class</th>
                            <th className="px-3 py-2">Booked</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {slots.length === 0 && (
                            <tr><td className="px-3 py-3 text-gray-500" colSpan={6}>No meeting slots.</td></tr>
                        )}
                        {slots.map((slot) => (
                            <tr key={slot.id} className="border-t">
                                <td className="px-3 py-2">{slot.date} {slot.start_time}–{slot.end_time}</td>
                                <td className="px-3 py-2">{slot.teacher_name}</td>
                                <td className="px-3 py-2">{slot.class_name || '—'}</td>
                                <td className="px-3 py-2">{slot.booked}/{slot.capacity} {slot.bookings.map((row) => row.student_name).join(', ')}</td>
                                <td className="px-3 py-2">{slot.status}</td>
                                <td className="px-3 py-2 text-end">
                                    <button
                                        type="button"
                                        className="text-sm text-[#7C2D37] hover:underline"
                                        onClick={() => router.delete(`/academics/meetings/${slot.id}`)}
                                    >
                                        {slot.booked ? 'Cancel' : 'Remove'}
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
