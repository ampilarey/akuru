import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * Report card templates. Every word is the `exams` book's (slice EG2, STATUS
 * §5qk). A template's sections are ticked by name — the form asked for their
 * codes typed with commas (*grades_table,attendance_summary*), and the list
 * printed them so. A template's header and footer are the school's words.
 */
export default function Templates({ templates, sections, t = {} }) {
    const sectionName = (section) => t[`report_section_${section}`] || section;
    const form = useForm({
        name: '',
        sections: [...sections],
        header: 'Akuru Institute',
        footer: t.templates_default_footer || 'Official report card',
        active: true,
    });
    const toggle = (section, on) => form.setData('sections', on
        ? sections.filter((each) => each === section || form.data.sections.includes(each))
        : form.data.sections.filter((each) => each !== section));

    return (
        <AppShell title={t.templates_title || 'Report card templates'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/exams/report-templates/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/exams/report-templates', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <input className="form-input" aria-label={t.name || 'Name'} placeholder={t.name || 'Name'} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input className="form-input" aria-label={t.templates_header || 'Header'} placeholder={t.templates_header || 'Header'} value={form.data.header} onChange={(e) => form.setData('header', e.target.value)} />
                <input className="form-input" aria-label={t.templates_footer || 'Footer'} placeholder={t.templates_footer || 'Footer'} value={form.data.footer} onChange={(e) => form.setData('footer', e.target.value)} />
                <fieldset className="md:col-span-3">
                    <legend className="mb-1 text-sm text-gray-600">{t.templates_sections || 'Sections'}</legend>
                    <div className="flex flex-wrap gap-3">
                        {sections.map((section) => (
                            <label key={section} className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={form.data.sections.includes(section)} onChange={(e) => toggle(section, e.target.checked)} />
                                {sectionName(section)}
                            </label>
                        ))}
                    </div>
                    {form.errors.sections && <span className="text-xs text-red-600">{form.errors.sections}</span>}
                </fieldset>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.templates_create || 'Create template'}</button>
                {form.errors.name && <span className="text-xs text-red-600 md:col-span-2">{form.errors.name}</span>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.name || 'Name'}</th>
                            <th className="px-3 py-2">{t.templates_sections || 'Sections'}</th>
                            <th className="px-3 py-2">{t.templates_col_active || 'Active'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {templates.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={3}>{t.templates_none || 'No templates yet.'}</td></tr>
                        )}
                        {templates.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                <td className="px-3 py-2">{(row.sections || []).map(sectionName).join(t.list_separator || ', ')}</td>
                                <td className="px-3 py-2">{row.active ? (t.yes || 'yes') : (t.no || 'no')}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
