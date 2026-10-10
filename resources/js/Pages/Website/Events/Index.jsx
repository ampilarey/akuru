import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

function Field({ label, error, children }) {
    return (
        <label className="block text-sm">
            <span className="mb-1 block text-gray-600">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}

/**
 * The school's events and electives. Every word is the `academics` book's
 * (slice SE1, STATUS §5qr); an event's type, state and registration are
 * named rather than printed as codes, an event reads by the title the school
 * gave it in the page's language, and the list reads for a year and a state
 * — the server took both, and the screen offered neither.
 */
export default function Index({ events, years, types, statuses, registrationTypes, filters = {}, t = {} }) {
    const locale = usePage().props.locale || 'en';
    const titled = (row) => ({ dv: row?.title_dv, ar: row?.title_ar }[locale]) || row?.title;
    const [filter, setFilter] = useState({ academic_year_id: filters.academic_year_id ?? '', status: filters.status ?? '' });
    const query = new URLSearchParams(Object.entries(filter).filter(([, value]) => value !== '')).toString();
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
    const saidBeside = ['title', 'title_dv', 'title_ar', 'location', 'start_date', 'end_date', 'type', 'status', 'registration_type', 'academic_year_id', 'min_attendees', 'max_attendees', 'description'];

    return (
        <AppShell title={t.events_title || 'Events'}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get('/academics/events', Object.fromEntries(Object.entries(filter).filter(([, value]) => value !== '')), { preserveState: true });
                }}
                className="mb-4 flex flex-wrap items-end gap-3"
            >
                <Field label={t.year || 'Year'}>
                    <select className="form-input" aria-label={t.year || 'Year'} value={filter.academic_year_id} onChange={(e) => setFilter({ ...filter, academic_year_id: e.target.value })}>
                        <option value="">{t.all || 'All'}</option>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                </Field>
                <Field label={t.status || 'Status'}>
                    <select className="form-input" aria-label={t.status || 'Status'} value={filter.status} onChange={(e) => setFilter({ ...filter, status: e.target.value })}>
                        <option value="">{t.all || 'All'}</option>
                        {statuses.map((status) => <option key={status} value={status}>{t[`event_status_${status}`] || status}</option>)}
                    </select>
                </Field>
                <button type="submit" className="btn-secondary">{t.events_filter || 'Show'}</button>
                <a className="btn-secondary ms-auto" href={`/academics/events/export${query ? `?${query}` : ''}`}>{t.export_csv || 'Export CSV'}</a>
            </form>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/academics/events', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <p className="md:col-span-4 text-sm font-medium">{t.events_create || 'Create event / elective'}</p>
                <Field label={t.title_en || 'Title (EN)'} error={form.errors.title}>
                    <input className="form-input w-full" aria-label={t.title_en || 'Title (EN)'} dir="ltr" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </Field>
                <Field label={t.title_dv || 'Title (DV)'} error={form.errors.title_dv}>
                    <input className="form-input w-full" aria-label={t.title_dv || 'Title (DV)'} dir="rtl" value={form.data.title_dv} onChange={(e) => form.setData('title_dv', e.target.value)} />
                </Field>
                <Field label={t.title_ar || 'Title (AR)'} error={form.errors.title_ar}>
                    <input className="form-input w-full" aria-label={t.title_ar || 'Title (AR)'} dir="rtl" value={form.data.title_ar} onChange={(e) => form.setData('title_ar', e.target.value)} />
                </Field>
                <Field label={t.events_location || 'Location'} error={form.errors.location}>
                    <input className="form-input w-full" aria-label={t.events_location || 'Location'} value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} />
                </Field>
                <Field label={t.start || 'Start'} error={form.errors.start_date}>
                    <input className="form-input w-full" aria-label={t.start || 'Start'} type="datetime-local" value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} />
                </Field>
                <Field label={t.end || 'End'} error={form.errors.end_date}>
                    <input className="form-input w-full" aria-label={t.end || 'End'} type="datetime-local" value={form.data.end_date} onChange={(e) => form.setData('end_date', e.target.value)} />
                </Field>
                <Field label={t.type || 'Type'} error={form.errors.type}>
                    <select className="form-input w-full" aria-label={t.type || 'Type'} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((type) => <option key={type} value={type}>{t[`event_type_${type}`] || type}</option>)}
                    </select>
                </Field>
                <Field label={t.status || 'Status'} error={form.errors.status}>
                    <select className="form-input w-full" aria-label={t.status || 'Status'} value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                        {statuses.map((status) => <option key={status} value={status}>{t[`event_status_${status}`] || status}</option>)}
                    </select>
                </Field>
                <Field label={t.events_registration || 'Registration'} error={form.errors.registration_type}>
                    <select className="form-input w-full" aria-label={t.events_registration || 'Registration'} value={form.data.registration_type} onChange={(e) => form.setData('registration_type', e.target.value)}>
                        {registrationTypes.map((type) => <option key={type} value={type}>{t[`event_registration_${type}`] || type}</option>)}
                    </select>
                </Field>
                <Field label={t.year || 'Year'} error={form.errors.academic_year_id}>
                    <select className="form-input w-full" aria-label={t.year || 'Year'} value={form.data.academic_year_id} onChange={(e) => form.setData('academic_year_id', e.target.value)}>
                        <option value="">{t.none || 'None'}</option>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                </Field>
                <Field label={t.events_min || 'Min seats'} error={form.errors.min_attendees}>
                    <input className="form-input w-full" aria-label={t.events_min || 'Min seats'} type="number" min="0" value={form.data.min_attendees} onChange={(e) => form.setData('min_attendees', e.target.value)} />
                </Field>
                <Field label={t.events_max || 'Max seats'} error={form.errors.max_attendees}>
                    <input className="form-input w-full" aria-label={t.events_max || 'Max seats'} type="number" min="0" value={form.data.max_attendees} onChange={(e) => form.setData('max_attendees', e.target.value)} />
                </Field>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.waitlist_enabled} onChange={(e) => form.setData('waitlist_enabled', e.target.checked)} />
                    {t.events_waitlist || 'Waitlist'}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.requires_parent_confirmation} onChange={(e) => form.setData('requires_parent_confirmation', e.target.checked)} />
                    {t.events_parent_confirm || 'Parent confirm'}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.is_elective} onChange={(e) => form.setData('is_elective', e.target.checked)} />
                    {t.events_elective || 'Elective'}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.is_public} onChange={(e) => form.setData('is_public', e.target.checked)} />
                    {t.events_public || 'Public'}
                </label>
                <Field label={t.description || 'Description'} error={form.errors.description}>
                    <textarea className="form-input w-full md:col-span-3 min-h-16" aria-label={t.description || 'Description'} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </Field>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.events_create_button || 'Create event'}</button>
                <FormErrors errors={form.errors} except={saidBeside} className="md:col-span-4" />
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.events_when || 'When'}</th>
                            <th className="px-3 py-2">{t.events_seats || 'Seats'}</th>
                            <th className="px-3 py-2">{t.events_settings || 'Settings'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {events.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.events_none || 'No events yet.'}</td></tr>
                        )}
                        {events.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">
                                    <p className="font-medium">{titled(row)}</p>
                                    <p className="text-xs text-gray-500">{t[`event_status_${row.status}`] || row.status} · {t[`event_registration_${row.registration_type}`] || row.registration_type}</p>
                                </td>
                                <td className="px-3 py-2 text-xs">{row.start_date}</td>
                                <td className="px-3 py-2">
                                    {row.occupying}/{row.max_attendees ?? '∞'}
                                    {row.waitlisted > 0 ? ` · ${(t.events_waiting || ':count waiting').replace(':count', row.waitlisted)}` : ''}
                                </td>
                                <td className="px-3 py-2 text-xs">
                                    {[
                                        row.is_elective && (t.events_flag_elective || 'elective'),
                                        row.waitlist_enabled && (t.events_flag_waitlist || 'waitlist'),
                                        row.requires_parent_confirmation && (t.events_flag_parent || 'parent confirms'),
                                    ].filter(Boolean).join(t.list_separator || ', ')}
                                </td>
                                <td className="px-3 py-2">
                                    <Link className="btn-secondary" href={`/academics/events/${row.id}`}>{t.open || 'Open'}</Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
