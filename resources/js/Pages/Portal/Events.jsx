import { router, useForm } from '@inertiajs/react';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';
import AppShell from '../../Layouts/AppShell';

export default function Events({ children, events, registrations, t = {} }) {
    const form = useForm({
        student_id: children[0]?.id || '',
        event_id: events[0]?.id || '',
    });
    // Confirm posts with `router`, so a refusal had no form to show it: it is
    // said under the registration now (BACKLOG C21, slice PT3).
    const refusals = useRowRefusals(form);
    const confirm = (row) => refusals.actOn(`registration:${row.id}`, () => router.post(`/portal/events/registrations/${row.id}/confirm`, {}, { preserveScroll: true, preserveState: 'errors' }));

    const mineFor = (eventId) => registrations.filter((row) => row.event_id === eventId);
    const seats = (event) => `${event.occupying}/${event.max_attendees ?? '∞'}`;

    return (
        <AppShell title={t.events_title || 'Events'}>
            {children.length === 0 && (
                <p className="mb-4 rounded-lg border bg-white p-4 text-sm text-gray-600">{t.events_no_children || 'No children are linked to this login.'}</p>
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
                        <span className="mb-1 block text-gray-600">{t.pick_child || 'Child'}</span>
                        <select className="form-input w-full" value={form.data.student_id} onChange={(e) => form.setData('student_id', e.target.value)}>
                            {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                        </select>
                        {form.errors.student_id && <span className="text-xs text-red-600">{form.errors.student_id}</span>}
                    </label>
                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">{t.events_event || 'Event'}</span>
                        <select className="form-input w-full" value={form.data.event_id} onChange={(e) => form.setData('event_id', e.target.value)}>
                            {events.map((event) => (
                                <option key={event.id} value={event.id}>{event.title} ({seats(event)})</option>
                            ))}
                        </select>
                        {form.errors.event_id && <span className="text-xs text-red-600">{form.errors.event_id}</span>}
                    </label>
                    <button type="submit" className="btn-primary self-end" disabled={form.processing}>{t.events_register || 'Register'}</button>
                    <FormErrors errors={form.errors} except={['student_id', 'event_id']} className="md:col-span-3" />
                </form>
            )}

            <FormErrors errors={refusals.unplaced} className="mb-3" />
            <ul className="grid gap-3">
                {events.map((event) => (
                    <li key={event.id} className="rounded-lg border bg-white p-4 text-sm">
                        <div className="mb-1 flex flex-wrap justify-between gap-2">
                            <p className="font-semibold">{event.title}</p>
                            <span className="text-xs font-semibold">{t[`events_type_${event.registration_type}`] || event.registration_type}</span>
                        </div>
                        <p className="text-gray-600">{event.location} · {event.start_date}</p>
                        <p className="mt-1 text-xs">
                            {(t.events_seats || 'Seats :seats').replace(':seats', seats(event))}
                            {event.waitlist_enabled ? ` · ${t.events_waitlist_on || 'waitlist on'}` : ''}
                            {event.requires_parent_confirmation ? ` · ${t.events_parent_confirmation || 'parent confirmation'}` : ''}
                            {event.second_round_opens_at ? ` · ${t.events_second_round || 'second round open'}` : ''}
                        </p>
                        <ul className="mt-3 grid gap-1">
                            {mineFor(event.id).map((row) => (
                                <li key={row.id} className="rounded bg-[#F9F4EE] px-2 py-1">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span>
                                            {row.student_name}: {t[`events_status_${row.status}`] || row.status}
                                            {row.waitlist_position ? ` (#${row.waitlist_position})` : ''}
                                        </span>
                                        {row.status === 'pending_parent' && (
                                            <button type="button" className="btn-secondary" onClick={() => confirm(row)}>
                                                {t.events_confirm || 'Confirm'}
                                            </button>
                                        )}
                                    </div>
                                    <FormErrors errors={refusals.errorsFor(`registration:${row.id}`)} className="mt-1" />
                                </li>
                            ))}
                        </ul>
                    </li>
                ))}
                {events.length === 0 && (
                    <li className="rounded-lg border bg-white p-4 text-sm text-gray-600">{t.events_none || 'No events open for registration.'}</li>
                )}
            </ul>
        </AppShell>
    );
}
