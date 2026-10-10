import { useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * A subject's competencies. Every word is the `exams` book's (slice EG2,
 * STATUS §5qk). A competency reads by the name the school gave it in the
 * page's language, and names its subject — the list printed the subject's
 * id — and the form takes the Dhivehi name the competency has a place for.
 */
export default function Index({ subjects, competencies, subjectId, t = {} }) {
    const locale = usePage().props.locale || 'en';
    const named = (row) => ({ dv: row?.name_dhivehi, ar: row?.name_arabic }[locale]) || row?.name;
    const subjectName = (id) => named(subjects.find((row) => `${row.id}` === `${id}`)) ?? '—';
    const form = useForm({
        subject_id: subjectId || subjects[0]?.id || '',
        name: '',
        name_arabic: '',
        name_dhivehi: '',
        description: '',
        sort_order: 0,
    });
    const refused = form.errors.subject_id;

    return (
        <AppShell title={t.competencies_title || 'Competencies'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/exams/competencies/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/exams/competencies', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.subject || 'Subject'}</span>
                    <select className="form-input w-full" value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                        {subjects.map((subject) => <option key={subject.id} value={subject.id}>{named(subject)}</option>)}
                    </select>
                    {refused && <span className="text-xs text-red-600">{refused}</span>}
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.name_en || 'Name (EN)'}</span>
                    <input className="form-input w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    {form.errors.name && <span className="text-xs text-red-600">{form.errors.name}</span>}
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.name_dv || 'Name (DV)'}</span>
                    <input className="form-input w-full" dir="rtl" value={form.data.name_dhivehi} onChange={(e) => form.setData('name_dhivehi', e.target.value)} />
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.name_ar || 'Name (AR)'}</span>
                    <input className="form-input w-full" dir="rtl" value={form.data.name_arabic} onChange={(e) => form.setData('name_arabic', e.target.value)} />
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.competencies_create || 'Create competency'}</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.name || 'Name'}</th>
                            <th className="px-3 py-2">{t.subject || 'Subject'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {competencies.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={2}>{t.competencies_none || 'No competencies yet.'}</td></tr>
                        )}
                        {competencies.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{named(row)}</td>
                                <td className="px-3 py-2">{subjectName(row.subject_id)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
