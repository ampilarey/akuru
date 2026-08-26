import { router, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Events({ children, events, registrations }) {
    const form = useForm({
        student_id: children[0]?.id || '',
        event_id: events[0]?.id || '',
    });

    const mineFor = (eventId) => registrations.filter((row) => row.event_id === eventId);

    return (
        <AppShell title="Events">
            {children.length === 0 && (
                <p className="mb-4 rounded-lg border bg-white p-4 text-sm text-gray-600">No children are linked to this login.</p>
            )}

            {children.length > 0 && events.length > 0 && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(`/portal/events/${form.data.event_id}/register`, { preserveScroll: true });
                    }}
                    className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
                >
                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">Child</span>
                        <select className="form-input w-full" value={form.data.student_id} onChange={(e) => form.setData('student_id', e.target.value)}>
                            {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                        </select>
                        {form.errors.student_id && <span className="text-xs text-red-600">{form.errors.student_id}</span>}
                    </label>
                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">Event</span>
                        <select className="form-input w-full" value={form.data.event_id} onChange={(e) => form.setData('event_id', e.target.value)}>
                            {events.map((event) => (
                                <option key={event.id} value={event.id}>
                                    {event.title} ({event.occupying}/{event.max_attendees ?? '∞'})
                                </option>
                            ))}
                        </select>
                        {form.errors.event_id && <span className="text-xs text-red-600">{form.errors.event_id}</span>}
                    </label>
                    <button type="submit" className="btn-primary self-end" disabled={form.processing}>Register</button>
                </form>
            )}

            <ul className="grid gap-3">
                {events.map((event) => (
                    <li key={event.id} className="rounded-lg border bg-white p-4 text-sm">
                        <div className="mb-1 flex flex-wrap justify-between gap-2">
                            <p className="font-semibold">{event.title}</p>
                            <span className="text-xs uppercase">{event.registration_type}</span>
                        </div>
                        <p className="text-gray-600">{event.location} · {event.start_date}</p>
                        <p className="mt-1 text-xs">
                            Seats {event.occupying}/{event.max_attendees ?? '∞'}
                            {event.waitlist_enabled ? ' · waitlist on' : ''}
                            {event.requires_parent_confirmation ? ' · parent confirmation' : ''}
                            {event.second_round_opens_at ? ' · second round open' : ''}
                        </p>
                        <ul className="mt-3 grid gap-1">
                            {mineFor(event.id).map((row) => (
                                <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 rounded bg-[#F9F4EE] px-2 py-1">
                                    <span>{row.student_name}: {row.status}{row.waitlist_position ? ` (#${row.waitlist_position})` : ''}</span>
                                    {row.status === 'pending_parent' && (
                                        <button
                                            type="button"
                                            className="btn-secondary"
                                            onClick={() => router.post(`/portal/events/registrations/${row.id}/confirm`)}
                                        >
                                            Confirm
                                        </button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </li>
                ))}
                {events.length === 0 && (
                    <li className="rounded-lg border bg-white p-4 text-sm text-gray-600">No events open for registration.</li>
                )}
            </ul>
        </AppShell>
    );
}
