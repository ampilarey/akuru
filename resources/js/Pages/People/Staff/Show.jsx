import { useForm, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppShell from '../../../Layouts/AppShell';
import CustomFields from '../../../Components/CustomFields';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';

/**
 * A member of staff's profile. Every word is the `people` book's (slice PE2,
 * STATUS §5qq); the kind of employment and the status are named rather than
 * printed as codes.
 */
export default function Show({ staff, employmentTypes = [], statuses = [], customFields = [], t = {} }) {
    const form = useForm({
        title: '',
        institution: '',
        year: '',
    });

    // S1.2 custom fields, keyed `staff` — the same engine and component the
    // student profile uses. Values post as `values[{id}]`.
    const initialFieldValues = useMemo(() => {
        const next = {};
        customFields.forEach((field) => {
            next[field.id] = field.value ?? (field.field_type === 'multiselect' ? [] : field.field_type === 'boolean' ? false : '');
        });
        return next;
    }, [customFields]);
    const [fieldValues, setFieldValues] = useState(initialFieldValues);
    const fieldForm = useForm({ values: fieldValues });
    const saveFields = (e) => {
        e.preventDefault();
        fieldForm.transform(() => ({ values: fieldValues }));
        fieldForm.put(`/people/staff/${staff.id}/custom-fields`, { preserveScroll: true });
    };

    // The controller has always sent `employmentTypes` and `statuses` to this
    // page and the page never used them: `people.staff.update` existed, and
    // validated a status, with no form anywhere that posted to it. So a staff
    // status could be read but never changed, and every screen that filters on
    // "still employed" was guarding a value that could not move.
    const employment = useForm({
        user_id: staff.user_id ?? '',
        first_name: staff.first_name ?? '',
        last_name: staff.last_name ?? '',
        staff_number: staff.staff_number ?? '',
        department: staff.department ?? '',
        designation: staff.designation ?? '',
        employment_type: staff.employment_type ?? 'full_time',
        status: staff.status ?? 'active',
    });
    const refusals = useRowRefusals(form, fieldForm, employment);

    return (
        <AppShell title={staff.first_name + ' ' + staff.last_name}>
            <section className="mb-6 rounded-lg border bg-white p-4 text-sm">
                <p><strong>{t.col_number || 'Number'}:</strong> {staff.staff_number || '—'}</p>
                <p><strong>{t.staff_department || 'Department'}:</strong> {staff.department || '—'}</p>
                <p><strong>{t.staff_designation || 'Designation'}:</strong> {staff.designation || '—'}</p>
                <p><strong>{t.staff_joined || 'Joined'}:</strong> {staff.joined_date || '—'}</p>
            </section>
            <FormErrors errors={refusals.unplaced} className="mb-4" />

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    employment.put(`/people/staff/${staff.id}`, { preserveScroll: true });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.staff_employment || 'Employment'}</span>
                    <select
                        className="form-input w-full"
                        value={employment.data.employment_type}
                        onChange={(e) => employment.setData('employment_type', e.target.value)}
                    >
                        {employmentTypes.map((type) => <option key={type} value={type}>{t[`employment_${type}`] || type}</option>)}
                    </select>
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.status || 'Status'}</span>
                    <select
                        className="form-input w-full"
                        value={employment.data.status}
                        onChange={(e) => employment.setData('status', e.target.value)}
                    >
                        {statuses.map((status) => <option key={status} value={status}>{t[`staff_status_${status}`] || status}</option>)}
                    </select>
                    <span className="mt-1 block text-xs text-gray-500">
                        {t.staff_status_hint || 'Ending employment also takes this person out of the teacher pickers and the staff count. On leave does not — cover is arranged through them.'}
                    </span>
                </label>
                <div className="flex items-end">
                    <button type="submit" className="btn-primary" disabled={employment.processing}>
                        {t.staff_save_employment || 'Save employment'}
                    </button>
                </div>
                <FormErrors errors={employment.errors} className="md:col-span-3" />
            </form>

            {customFields.length > 0 && (
                <form onSubmit={saveFields} className="mb-6 rounded-lg border bg-white p-4">
                    <h2 className="mb-3 font-semibold">{t.staff_fields || 'Additional fields'}</h2>
                    <CustomFields
                        fields={customFields}
                        values={fieldValues}
                        errors={fieldForm.errors}
                        onChange={(id, value) => setFieldValues((prev) => ({ ...prev, [id]: value }))}
                    />
                    <button type="submit" className="btn-primary mt-4" disabled={fieldForm.processing}>{t.save_fields || 'Save fields'}</button>
                </form>
            )}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/people/staff/${staff.id}/qualifications`, { preserveScroll: true, onSuccess: () => form.reset() });
                }}
                className="mb-4 flex flex-wrap gap-3 rounded-lg border bg-white p-4"
            >
                <input className="form-input" aria-label={t.qualification_title || 'Qualification title'} placeholder={t.qualification_title || 'Qualification title'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <input className="form-input" aria-label={t.qualification_institution || 'Institution'} placeholder={t.qualification_institution || 'Institution'} value={form.data.institution} onChange={(e) => form.setData('institution', e.target.value)} />
                <input className="form-input w-28" aria-label={t.qualification_year || 'Year'} placeholder={t.qualification_year || 'Year'} value={form.data.year} onChange={(e) => form.setData('year', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.qualification_add || 'Add qualification'}</button>
                <FormErrors errors={form.errors} className="w-full" />
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.qualification_col_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.qualification_institution || 'Institution'}</th>
                            <th className="px-3 py-2">{t.qualification_year || 'Year'}</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {(staff.qualifications || []).length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.qualifications_none || 'No qualification is recorded.'}</td></tr>
                        )}
                        {(staff.qualifications || []).map((row) => (
                            <tr key={row.id} className="border-t align-top">
                                <td className="px-3 py-2">{row.title}</td>
                                <td className="px-3 py-2">{row.institution}</td>
                                <td className="px-3 py-2">{row.year}</td>
                                <td className="px-3 py-2 text-end">
                                    <button
                                        type="button"
                                        className="text-red-700 hover:underline"
                                        onClick={() => refusals.actOn(`qualification:${row.id}`, () => router.delete(`/people/staff/${staff.id}/qualifications/${row.id}`, { preserveScroll: true }))}
                                    >
                                        {t.remove || 'Remove'}
                                    </button>
                                    <FormErrors errors={refusals.errorsFor(`qualification:${row.id}`)} className="mt-1 text-start" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
