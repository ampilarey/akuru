import { Link, router, useForm } from '@inertiajs/react';
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

function toLocalInput(value) {
    if (!value) {
        return '';
    }
    return value.replace(' ', 'T').slice(0, 16);
}

export default function Show({ event, registrations, students, types, statuses, registrationTypes, years }) {
    const form = useForm({
        title: event.title || '',
        title_dv: event.title_dv || '',
        title_ar: event.title_ar || '',
        description: event.description || '',
        location: event.location || '',
        start_date: toLocalInput(event.start_date),
        end_date: toLocalInput(event.end_date),
        type: event.type,
        status: event.status,
        registration_type: event.registration_type,
        min_attendees: event.min_attendees ?? '',
        max_attendees: event.max_attendees ?? '',
        waitlist_enabled: event.waitlist_enabled,
        requires_parent_confirmation: event.requires_parent_confirmation,
        is_elective: event.is_elective,
        is_public: event.is_public,
        academic_year_id: event.academic_year_id || '',
    });
    const registerForm = useForm({ student_id: students[0]?.id || '' });

    return (
        <AppShell title={event.title}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <Link className="btn-secondary" href="/academics/events">All events</Link>
                <div className="flex flex-wrap gap-2">
                    <a className="btn-secondary" href={`/academics/events/${event.id}/registrations/export`}>Export CSV</a>
                    <button
                        type="button"
                        className="btn-primary"
                        onClick={() => router.post(`/academics/events/${event.id}/second-round`)}
                    >
                        Open second round
                    </button>
                </div>
            </div>

            <p className="mb-4 text-sm text-gray-600">
                Occupying {event.occupying}/{event.max_attendees ?? '∞'}
                {event.min_attendees ? ` · min ${event.min_attendees}` : ''}
                {event.waitlisted ? ` · waitlist ${event.waitlisted}` : ''}
                {event.second_round_opens_at ? ` · second round ${event.second_round_opens_at}` : ''}
            </p>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put(`/academics/events/${event.id}`, { preserveScroll: true });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <Field label="Title (EN)" error={form.errors.title}>
                    <input className="form-input w-full" dir="ltr" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </Field>
                <Field label="Title (DV)">
                    <input className="form-input w-full" dir="rtl" value={form.data.title_dv} onChange={(e) => form.setData('title_dv', e.target.value)} />
                </Field>
                <Field label="Title (AR)">
                    <input className="form-input w-full" dir="rtl" value={form.data.title_ar} onChange={(e) => form.setData('title_ar', e.target.value)} />
                </Field>
                <Field label="Location">
                    <input className="form-input w-full" value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} />
                </Field>
                <Field label="Start">
                    <input className="form-input w-full" type="datetime-local" value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} />
                </Field>
                <Field label="End">
                    <input className="form-input w-full" type="datetime-local" value={form.data.end_date} onChange={(e) => form.setData('end_date', e.target.value)} />
                </Field>
                <Field label="Type">
                    <select className="form-input w-full" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((type) => <option key={type} value={type}>{type}</option>)}
                    </select>
                </Field>
                <Field label="Status">
                    <select className="form-input w-full" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                        {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                    </select>
                </Field>
                <Field label="Registration">
                    <select className="form-input w-full" value={form.data.registration_type} onChange={(e) => form.setData('registration_type', e.target.value)}>
                        {registrationTypes.map((type) => <option key={type} value={type}>{type}</option>)}
                    </select>
                </Field>
                <Field label="Year">
                    <select className="form-input w-full" value={form.data.academic_year_id} onChange={(e) => form.setData('academic_year_id', e.target.value)}>
                        <option value="">None</option>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                </Field>
                <Field label="Min seats">
                    <input className="form-input w-full" type="number" min="0" value={form.data.min_attendees} onChange={(e) => form.setData('min_attendees', e.target.value)} />
                </Field>
                <Field label="Max seats">
                    <input className="form-input w-full" type="number" min="0" value={form.data.max_attendees} onChange={(e) => form.setData('max_attendees', e.target.value)} />
                </Field>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.waitlist_enabled} onChange={(e) => form.setData('waitlist_enabled', e.target.checked)} />
                    Waitlist
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.requires_parent_confirmation} onChange={(e) => form.setData('requires_parent_confirmation', e.target.checked)} />
                    Parent confirm
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.is_elective} onChange={(e) => form.setData('is_elective', e.target.checked)} />
                    Elective
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.is_public} onChange={(e) => form.setData('is_public', e.target.checked)} />
                    Public
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>Save event</button>
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    registerForm.post(`/academics/events/${event.id}/register`, { preserveScroll: true });
                }}
                className="mb-6 flex flex-wrap items-end gap-3 rounded-lg border bg-white p-4"
            >
                <Field label="Register student" error={registerForm.errors.student_id}>
                    <select className="form-input" value={registerForm.data.student_id} onChange={(e) => registerForm.setData('student_id', e.target.value)}>
                        {students.map((student) => <option key={student.id} value={student.id}>{student.name}</option>)}
                    </select>
                </Field>
                <button type="submit" className="btn-primary" disabled={registerForm.processing}>Register</button>
                {registerForm.errors.event_id && <span className="text-xs text-red-600">{registerForm.errors.event_id}</span>}
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Email</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Wait #</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {registrations.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No registrations.</td></tr>
                        )}
                        {registrations.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.email}</td>
                                <td className="px-3 py-2 uppercase text-xs">{row.status}</td>
                                <td className="px-3 py-2">{row.waitlist_position ?? ''}</td>
                                <td className="px-3 py-2">
                                    {row.status === 'pending_parent' && (
                                        <button
                                            type="button"
                                            className="btn-secondary"
                                            onClick={() => router.post(`/academics/events/${event.id}/registrations/${row.id}/confirm`)}
                                        >
                                            Confirm
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
