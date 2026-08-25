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

export default function Index({ yearId, years, rooms, periods, bookings }) {
    const form = useForm({
        academic_year_id: yearId || '',
        room_id: rooms[0]?.id || '',
        title: '',
        title_arabic: '',
        title_dhivehi: '',
        date: '',
        period_id: periods.find((period) => !period.is_break)?.id || '',
        start_time: '',
        end_time: '',
        notes: '',
    });

    return (
        <AppShell title="Room bookings">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <select
                    className="form-input"
                    value={yearId || ''}
                    onChange={(e) => router.get(`/academics/bookings?academic_year_id=${e.target.value}`)}
                >
                    <option value="">Year</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <a className="btn-secondary" href={`/academics/bookings/export?academic_year_id=${yearId || ''}`}>
                    Export CSV
                </a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        start_time: data.period_id ? '' : data.start_time,
                        end_time: data.period_id ? '' : data.end_time,
                    }));
                    form.post('/academics/bookings', { preserveScroll: true });
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
                <Field label="Room" error={form.errors.room_id}>
                    <select className="form-input w-full" value={form.data.room_id} onChange={(e) => form.setData('room_id', e.target.value)}>
                        {rooms.map((room) => <option key={room.id} value={room.id}>{room.name}</option>)}
                    </select>
                </Field>
                <Field label="Date" error={form.errors.date}>
                    <input className="form-input w-full" type="date" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                </Field>
                <Field label="Period" error={form.errors.period_id}>
                    <select className="form-input w-full" value={form.data.period_id} onChange={(e) => form.setData('period_id', e.target.value)}>
                        <option value="">Time-based</option>
                        {periods.map((period) => <option key={period.id} value={period.id}>{period.name}</option>)}
                    </select>
                </Field>
                {!form.data.period_id && (
                    <>
                        <Field label="Start">
                            <input className="form-input w-full" type="time" value={form.data.start_time} onChange={(e) => form.setData('start_time', e.target.value)} />
                        </Field>
                        <Field label="End">
                            <input className="form-input w-full" type="time" value={form.data.end_time} onChange={(e) => form.setData('end_time', e.target.value)} />
                        </Field>
                    </>
                )}
                <Field label="Notes">
                    <input className="form-input w-full" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                </Field>
                <div className="md:col-span-4 flex items-center gap-3">
                    <button type="submit" className="btn-primary" disabled={form.processing}>Create booking</button>
                    {form.errors.conflicts && <p className="text-sm text-red-600">{form.errors.conflicts}</p>}
                </div>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Room</th>
                            <th className="px-3 py-2">Time</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {bookings.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No bookings yet.</td></tr>
                        )}
                        {bookings.map((row) => (
                            <BookingRow key={row.id} booking={row} rooms={rooms} />
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function BookingRow({ booking, rooms }) {
    const form = useForm({
        academic_year_id: booking.academic_year_id,
        room_id: booking.room_id,
        title: booking.title,
        title_arabic: booking.title_arabic || '',
        title_dhivehi: booking.title_dhivehi || '',
        date: booking.date,
        period_id: booking.period_id || '',
        start_time: booking.period_id ? '' : (booking.start_time || ''),
        end_time: booking.period_id ? '' : (booking.end_time || ''),
        notes: booking.notes || '',
    });
    const roomName = rooms.find((room) => String(room.id) === String(booking.room_id))?.name || booking.room_id;

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-2">
                <input className="form-input w-full" type="date" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
            </td>
            <td className="px-3 py-2">
                <input className="form-input w-full" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                {form.errors.title && <span className="text-xs text-red-600">{form.errors.title}</span>}
                {form.errors.conflicts && <span className="block text-xs text-red-600">{form.errors.conflicts}</span>}
            </td>
            <td className="px-3 py-2">{roomName}</td>
            <td className="px-3 py-2 text-xs text-gray-600">{booking.start_time}–{booking.end_time}</td>
            <td className="px-3 py-2">
                <button type="button" className="btn-secondary mr-2" disabled={form.processing} onClick={() => form.put(`/academics/bookings/${booking.id}`, { preserveScroll: true })}>Save</button>
                <button type="button" className="text-sm text-red-700 underline" onClick={() => router.delete(`/academics/bookings/${booking.id}`)}>Remove</button>
            </td>
        </tr>
    );
}
