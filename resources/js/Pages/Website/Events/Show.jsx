import { Link, router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';

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

/**
 * One event: its details, who has registered, and the second round. Every
 * word is the `academics` book's (slice SE1, STATUS §5qr); a registration's
 * state is named rather than printed as a code, the event reads by the
 * title the school gave it in the page's language, every refusal of the form
 * is said — only a title's was — and a refused Confirm or second round is
 * said where it was asked for; both were said nowhere.
 */
export default function Show({ event, registrations, students, types, statuses, registrationTypes, years, t = {} }) {
    const locale = usePage().props.locale || 'en';
    const title = ({ dv: event.title_dv, ar: event.title_ar }[locale]) || event.title;
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
    const refusals = useRowRefusals(form, registerForm);
    const saidBeside = ['title', 'title_dv', 'title_ar', 'location', 'start_date', 'end_date', 'type', 'status', 'registration_type', 'academic_year_id', 'min_attendees', 'max_attendees'];

    return (
        <AppShell title={title}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <Link className="btn-secondary" href="/academics/events">{t.events_all || 'All events'}</Link>
                <div className="flex flex-wrap gap-2">
                    <a className="btn-secondary" href={`/academics/events/${event.id}/registrations/export`}>{t.export_csv || 'Export CSV'}</a>
                    <button
                        type="button"
                        className="btn-primary"
                        onClick={() => refusals.actOn('second-round', () => router.post(`/academics/events/${event.id}/second-round`, {}, { preserveScroll: true }))}
                    >
                        {t.events_second_round || 'Open second round'}
                    </button>
                </div>
            </div>
            <FormErrors errors={refusals.errorsFor('second-round')} className="mb-4" />
            <FormErrors errors={refusals.unplaced} className="mb-4" />

            <p className="mb-4 text-sm text-gray-600">
                {[
                    (t.events_occupying || 'Seats taken: :taken of :max').replace(':taken', event.occupying).replace(':max', event.max_attendees ?? '∞'),
                    event.min_attendees && (t.events_minimum || 'at least :count').replace(':count', event.min_attendees),
                    event.waitlisted && (t.events_waiting || ':count waiting').replace(':count', event.waitlisted),
                    event.second_round_opens_at && (t.events_second_round_at || 'second round :date').replace(':date', event.second_round_opens_at),
                ].filter(Boolean).join(' · ')}
            </p>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put(`/academics/events/${event.id}`, { preserveScroll: true });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
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
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.events_save || 'Save event'}</button>
                <FormErrors errors={form.errors} except={saidBeside} className="md:col-span-4" />
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    registerForm.post(`/academics/events/${event.id}/register`, { preserveScroll: true });
                }}
                className="mb-6 flex flex-wrap items-end gap-3 rounded-lg border bg-white p-4"
            >
                <Field label={t.events_register_student || 'Register student'} error={registerForm.errors.student_id}>
                    <select className="form-input" aria-label={t.events_register_student || 'Register student'} value={registerForm.data.student_id} onChange={(e) => registerForm.setData('student_id', e.target.value)}>
                        {students.map((student) => <option key={student.id} value={student.id}>{student.name}</option>)}
                    </select>
                </Field>
                <button type="submit" className="btn-primary" disabled={registerForm.processing}>{t.events_register || 'Register'}</button>
                <FormErrors errors={registerForm.errors} except={['student_id']} className="w-full" />
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_name || 'Name'}</th>
                            <th className="px-3 py-2">{t.events_email || 'Email'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                            <th className="px-3 py-2">{t.events_wait_position || 'Wait #'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {registrations.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.events_registrations_none || 'No registrations.'}</td></tr>
                        )}
                        {registrations.map((row) => (
                            <tr key={row.id} className="border-t align-top">
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.email}</td>
                                <td className="px-3 py-2 text-xs">{t[`registration_status_${row.status}`] || row.status}</td>
                                <td className="px-3 py-2">{row.waitlist_position ?? ''}</td>
                                <td className="px-3 py-2">
                                    {row.status === 'pending_parent' && (
                                        <button
                                            type="button"
                                            className="btn-secondary"
                                            onClick={() => refusals.actOn(`registration:${row.id}`, () => router.post(`/academics/events/${event.id}/registrations/${row.id}/confirm`, {}, { preserveScroll: true }))}
                                        >
                                            {t.events_confirm || 'Confirm'}
                                        </button>
                                    )}
                                    <FormErrors errors={refusals.errorsFor(`registration:${row.id}`)} className="mt-1" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
