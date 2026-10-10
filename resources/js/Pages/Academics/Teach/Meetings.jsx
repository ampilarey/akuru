import AppShell from '../../../Layouts/AppShell';

/**
 * The teacher's own parent-teacher meetings.
 *
 * Read-only. Generating, publishing and cancelling slots belong to the office
 * on `/academics/meetings`; this answers the question that had no screen at
 * all — who is coming to see me, and when.
 *
 * The family's name is the point of the page, so an empty slot says so rather
 * than showing a blank cell: "nobody yet" and "I forgot to look" should not
 * look the same at six in the evening.
 *
 * In the page's language (BACKLOG C21, slice OA3).
 */
function Slot({ slot, t }) {
    const families = slot.bookings || [];

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-2 whitespace-nowrap">
                <span className="font-medium">{slot.date}</span>
                <span className="block text-xs text-gray-600">{slot.start_time}–{slot.end_time}</span>
            </td>
            <td className="px-3 py-2">{slot.class_name || '—'}</td>
            <td className="px-3 py-2">{slot.room_name || '—'}</td>
            <td className="px-3 py-2">
                {families.length === 0 && <span className="text-sm text-gray-500">{t.teach_meetings_nobody || 'Nobody booked yet.'}</span>}
                {families.length > 0 && (
                    <ul className="space-y-1">
                        {families.map((booking) => (
                            <li key={booking.id} className="text-sm">{booking.student_name}</li>
                        ))}
                    </ul>
                )}
            </td>
            <td className="px-3 py-2 text-xs text-gray-600">{slot.booked}/{slot.capacity}</td>
        </tr>
    );
}

export default function Meetings({ teacher, slots = [], t = {} }) {
    const bookedCount = slots.reduce((total, slot) => total + (slot.booked || 0), 0);

    return (
        <AppShell title={t.teach_meetings_title || 'My meetings'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">
                    {teacher?.name}
                    {' · '}
                    {(t.teach_meetings_upcoming || 'Upcoming slots: :count').replace(':count', slots.length)}
                    {' · '}
                    {(t.teach_meetings_booked || 'Booked: :count').replace(':count', bookedCount)}
                </p>
                <a className="btn-secondary" href="/teach/meetings/export">{t.export_csv || 'Export CSV'}</a>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2 text-start">{t.col_when || 'When'}</th>
                            <th className="px-3 py-2 text-start">{t.col_class || 'Class'}</th>
                            <th className="px-3 py-2 text-start">{t.col_room || 'Room'}</th>
                            <th className="px-3 py-2 text-start">{t.teach_meetings_who || 'Who is coming'}</th>
                            <th className="px-3 py-2 text-start">{t.teach_meetings_seats || 'Seats'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {slots.map((slot) => <Slot key={slot.id} slot={slot} t={t} />)}
                    </tbody>
                </table>
                {slots.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">
                        {t.teach_meetings_none || 'No meeting slots are published for you. The office creates them.'}
                    </p>
                )}
            </div>
        </AppShell>
    );
}
