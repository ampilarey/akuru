import { useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

/**
 * Lesson observations. Every word is the `hr` book's (slice HR2, STATUS
 * §5qm); a subject reads by the school's name for it in the page's language,
 * in the form and on the list. The summary is the observer's.
 */
export default function Observations({ staff, classes, subjects, rows, t = {} }) {
    const locale = usePage().props.locale || 'en';
    const named = (row) => ({ dv: row?.name_dhivehi, ar: row?.name_arabic }[locale]) || row?.name;
    const form = useForm({
        staff_profile_id: staff[0]?.id || '',
        date: '',
        class_id: '',
        subject_id: '',
        summary: '',
        shared_with_staff: true,
    });

    return (
        <AppShell title={t.observations_title || 'Lesson observations'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/hr/observations/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/hr/observations', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" aria-label={t.staff_member || 'Staff member'} value={form.data.staff_profile_id} onChange={(e) => form.setData('staff_profile_id', e.target.value)}>
                    {staff.map((row) => <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>)}
                </select>
                <input type="date" className="form-input" aria-label={t.date || 'Date'} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                <select className="form-input" aria-label={t.class || 'Class'} value={form.data.class_id} onChange={(e) => form.setData('class_id', e.target.value)}>
                    <option value="">{t.class || 'Class'}</option>
                    {classes.map((row) => <option key={row.id} value={row.id}>{row.label}</option>)}
                </select>
                <select className="form-input" aria-label={t.subject || 'Subject'} value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                    <option value="">{t.subject || 'Subject'}</option>
                    {subjects.map((row) => <option key={row.id} value={row.id}>{named(row)}</option>)}
                </select>
                <input className="form-input md:col-span-3" aria-label={t.observations_summary || 'Summary'} placeholder={t.observations_summary || 'Summary'} value={form.data.summary} onChange={(e) => form.setData('summary', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.observations_save || 'Save observation'}</button>
                <label className="flex items-center gap-2 text-sm md:col-span-4">
                    <input type="checkbox" checked={form.data.shared_with_staff} onChange={(e) => form.setData('shared_with_staff', e.target.checked)} />
                    {t.observations_shared || 'Shared with the member of staff'}
                </label>
                <FormErrors errors={form.errors} className="md:col-span-4" />
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.staff || 'Staff'}</th>
                            <th className="px-3 py-2">{t.date || 'Date'}</th>
                            <th className="px-3 py-2">{t.class || 'Class'}</th>
                            <th className="px-3 py-2">{t.subject || 'Subject'}</th>
                            <th className="px-3 py-2">{t.observations_summary || 'Summary'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.observations_none || 'No observations yet.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.staff_name}</td>
                                <td className="px-3 py-2">{row.date}</td>
                                <td className="px-3 py-2">{row.class_name || '—'}</td>
                                <td className="px-3 py-2">{named({ name: row.subject_name, name_dhivehi: row.subject_name_dhivehi, name_arabic: row.subject_name_arabic }) || '—'}</td>
                                <td className="px-3 py-2">{row.summary}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
