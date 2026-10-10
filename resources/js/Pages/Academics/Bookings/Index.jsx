import { router, useForm, usePage } from '@inertiajs/react';
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

export default function Index({ yearId, years, rooms, periods, bookings, t = {} }) {
    const locale = usePage().props.locale || 'en';
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
    // In the page's language (BACKLOG C21, slice OA2); a room by the name the
    // office gave it for the page's language, where it gave one.
    const roomName = (room) => ({ dv: room.name_dhivehi, ar: room.name_arabic }[locale]) || room.name;

    return (
        <AppShell title={t.bookings_title || 'Room bookings'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <select
                    className="form-input"
                    aria-label={t.year || 'Year'}
                    value={yearId || ''}
                    onChange={(e) => router.get(`/academics/bookings?academic_year_id=${e.target.value}`)}
                >
                    <option value="">{t.year || 'Year'}</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <a className="btn-secondary" href={`/academics/bookings/export?academic_year_id=${yearId || ''}`}>
                    {t.export_csv || 'Export CSV'}
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
                <Field label={t.title_en || 'Title (EN)'} error={form.errors.title}>
                    <input className="form-input w-full" aria-label={t.title_en || 'Title (EN)'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </Field>
                <Field label={t.title_ar || 'Title (AR)'}>
                    <input className="form-input w-full" dir="rtl" aria-label={t.title_ar || 'Title (AR)'} value={form.data.title_arabic} onChange={(e) => form.setData('title_arabic', e.target.value)} />
                </Field>
                <Field label={t.title_dv || 'Title (DV)'}>
                    <input className="form-input w-full" dir="rtl" aria-label={t.title_dv || 'Title (DV)'} value={form.data.title_dhivehi} onChange={(e) => form.setData('title_dhivehi', e.target.value)} />
                </Field>
                <Field label={t.room || 'Room'} error={form.errors.room_id}>
                    <select className="form-input w-full" aria-label={t.room || 'Room'} value={form.data.room_id} onChange={(e) => form.setData('room_id', e.target.value)}>
                        {rooms.map((room) => <option key={room.id} value={room.id}>{roomName(room)}</option>)}
                    </select>
                </Field>
                <Field label={t.date || 'Date'} error={form.errors.date}>
                    <input className="form-input w-full" type="date" aria-label={t.date || 'Date'} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                </Field>
                <Field label={t.col_period || 'Period'} error={form.errors.period_id}>
                    <select className="form-input w-full" aria-label={t.col_period || 'Period'} value={form.data.period_id} onChange={(e) => form.setData('period_id', e.target.value)}>
                        <option value="">{t.today_time_based || 'Time-based'}</option>
                        {periods.map((period) => <option key={period.id} value={period.id}>{period.name}</option>)}
                    </select>
                </Field>
                {!form.data.period_id && (
                    <>
                        <Field label={t.start || 'Start'} error={form.errors.start_time}>
                            <input className="form-input w-full" type="time" aria-label={t.start || 'Start'} value={form.data.start_time} onChange={(e) => form.setData('start_time', e.target.value)} />
                        </Field>
                        <Field label={t.end || 'End'} error={form.errors.end_time}>
                            <input className="form-input w-full" type="time" aria-label={t.end || 'End'} value={form.data.end_time} onChange={(e) => form.setData('end_time', e.target.value)} />
                        </Field>
                    </>
                )}
                <Field label={t.notes || 'Notes'}>
                    <input className="form-input w-full" aria-label={t.notes || 'Notes'} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                </Field>
                <div className="md:col-span-4 flex items-center gap-3">
                    <button type="submit" className="btn-primary" disabled={form.processing}>{t.bookings_create || 'Create booking'}</button>
                    {form.errors.conflicts && <p className="text-sm text-red-600">{form.errors.conflicts}</p>}
                </div>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_date || 'Date'}</th>
                            <th className="px-3 py-2">{t.col_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.col_room || 'Room'}</th>
                            <th className="px-3 py-2">{t.col_time || 'Time'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {bookings.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.bookings_none || 'No bookings yet.'}</td></tr>
                        )}
                        {bookings.map((row) => (
                            <BookingRow key={row.id} booking={row} rooms={rooms} roomName={roomName} t={t} />
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function BookingRow({ booking, rooms, roomName, t }) {
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
    const room = rooms.find((row) => String(row.id) === String(booking.room_id));
    // Each box says which booking it belongs to.
    const label = (column) => `${column}: ${booking.title}`;

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-2">
                <input className="form-input w-full" type="date" aria-label={label(t.date || 'Date')} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                {form.errors.date && <span className="text-xs text-red-600">{form.errors.date}</span>}
            </td>
            <td className="px-3 py-2">
                <input className="form-input w-full" aria-label={label(t.title_en || 'Title (EN)')} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                {form.errors.title && <span className="text-xs text-red-600">{form.errors.title}</span>}
                {form.errors.conflicts && <span className="block text-xs text-red-600">{form.errors.conflicts}</span>}
            </td>
            <td className="px-3 py-2">{room ? roomName(room) : booking.room_id}</td>
            <td className="px-3 py-2 text-xs text-gray-600">{booking.start_time}–{booking.end_time}</td>
            <td className="px-3 py-2">
                <button type="button" className="btn-secondary me-2" disabled={form.processing} onClick={() => form.put(`/academics/bookings/${booking.id}`, { preserveScroll: true })}>{t.save || 'Save'}</button>
                <button type="button" className="text-sm text-red-700 underline" onClick={() => router.delete(`/academics/bookings/${booking.id}`)}>{t.remove || 'Remove'}</button>
            </td>
        </tr>
    );
}
