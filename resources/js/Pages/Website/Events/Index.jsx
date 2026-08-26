import { Link, useForm } from '@inertiajs/react';
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

export default function Index({ events, years, types, statuses, registrationTypes }) {
    const form = useForm({
        title: '',
        title_dv: '',
        title_ar: '',
        description: '',
        location: '',
        start_date: '',
        end_date: '',
        type: types[0] || 'other',
        status: 'published',
        registration_type: 'required',
        min_attendees: '',
        max_attendees: '',
        waitlist_enabled: true,
        requires_parent_confirmation: true,
        is_elective: true,
        is_public: false,
        academic_year_id: years.find((year) => year.is_current)?.id || '',
    });

    return (
        <AppShell title="Events">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/academics/events/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/academics/events', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <p className="md:col-span-4 text-sm font-medium">Create event / elective</p>
                <Field label="Title (EN)" error={form.errors.title}>
                    <input className="form-input w-full" dir="ltr" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </Field>
                <Field label="Title (DV)">
                    <input className="form-input w-full" dir="rtl" value={form.data.title_dv} onChange={(e) => form.setData('title_dv', e.target.value)} />
                </Field>
                <Field label="Title (AR)">
                    <input className="form-input w-full" dir="rtl" value={form.data.title_ar} onChange={(e) => form.setData('title_ar', e.target.value)} />
                </Field>
                <Field label="Location" error={form.errors.location}>
                    <input className="form-input w-full" value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} />
                </Field>
                <Field label="Start" error={form.errors.start_date}>
                    <input className="form-input w-full" type="datetime-local" value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} />
                </Field>
                <Field label="End" error={form.errors.end_date}>
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
                <Field label="Max seats" error={form.errors.max_attendees}>
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
                <Field label="Description" error={form.errors.description}>
                    <textarea className="form-input w-full md:col-span-3 min-h-16" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </Field>
                <button type="submit" className="btn-primary" disabled={form.processing}>Create event</button>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">When</th>
                            <th className="px-3 py-2">Seats</th>
                            <th className="px-3 py-2">Flags</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {events.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No events yet.</td></tr>
                        )}
                        {events.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">
                                    <p className="font-medium">{row.title}</p>
                                    <p className="text-xs text-gray-500">{row.status} · {row.registration_type}</p>
                                </td>
                                <td className="px-3 py-2 text-xs">{row.start_date}</td>
                                <td className="px-3 py-2">
                                    {row.occupying}/{row.max_attendees ?? '∞'}
                                    {row.waitlisted > 0 ? ` · wait ${row.waitlisted}` : ''}
                                </td>
                                <td className="px-3 py-2 text-xs">
                                    {row.is_elective ? 'elective ' : ''}
                                    {row.waitlist_enabled ? 'waitlist ' : ''}
                                    {row.requires_parent_confirmation ? 'parent ' : ''}
                                </td>
                                <td className="px-3 py-2">
                                    <Link className="btn-secondary" href={`/academics/events/${row.id}`}>Open</Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
