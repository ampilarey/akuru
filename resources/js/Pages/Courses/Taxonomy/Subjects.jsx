import { useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Subjects({ rows, t = {} }) {
    const locale = usePage().props.locale || 'en';
    // A subject in the page's language where it has a name in it (slice CT4).
    const named = (row) => row[`name_${locale}`] || row.name_en;
    const form = useForm({
        parent_id: '',
        name_en: '',
        name_dv: '',
        name_ar: '',
        sort_order: 0,
    });

    return (
        <AppShell title={t.taxonomy_subjects_title || 'Course subjects'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/subjects/export">{t.catalog_export || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/catalog/subjects', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-5"
            >
                <select className="form-input" aria-label={t.taxonomy_parent || 'Parent subject'} value={form.data.parent_id} onChange={(e) => form.setData('parent_id', e.target.value)}>
                    <option value="">{t.taxonomy_top_level || 'Top level'}</option>
                    {rows.map((row) => <option key={row.id} value={row.id}>{named(row)}</option>)}
                </select>
                <input className="form-input" placeholder={t.taxonomy_name_en || 'Name (EN)'} aria-label={t.taxonomy_name_en || 'Name (EN)'} dir="ltr" value={form.data.name_en} onChange={(e) => form.setData('name_en', e.target.value)} />
                <input className="form-input" placeholder={t.taxonomy_name_dv || 'Name (DV)'} aria-label={t.taxonomy_name_dv || 'Name (DV)'} dir="rtl" value={form.data.name_dv} onChange={(e) => form.setData('name_dv', e.target.value)} />
                <input className="form-input" placeholder={t.taxonomy_name_ar || 'Name (AR)'} aria-label={t.taxonomy_name_ar || 'Name (AR)'} dir="rtl" value={form.data.name_ar} onChange={(e) => form.setData('name_ar', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.taxonomy_save_subject || 'Save subject'}</button>
                {form.errors.name_en && <span className="text-xs text-red-600">{form.errors.name_en}</span>}
                {form.errors.parent_id && <span className="text-xs text-red-600">{form.errors.parent_id}</span>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.taxonomy_col_name || 'Name'}</th>
                            <th className="px-3 py-2">{t.taxonomy_col_parent || 'Parent'}</th>
                            <th className="px-3 py-2">{t.taxonomy_col_address || 'Address'}</th>
                            <th className="px-3 py-2">{t.taxonomy_col_active || 'Active'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.taxonomy_none || 'None yet.'}</td></tr>
                        )}
                        {rows.map((row) => {
                            const parent = rows.find((item) => item.id === row.parent_id);
                            return (
                                <tr key={row.id} className="border-t">
                                    <td className="px-3 py-2">{named(row)}</td>
                                    <td className="px-3 py-2">{parent ? named(parent) : '—'}</td>
                                    <td className="px-3 py-2 font-mono text-xs" dir="ltr">{row.slug}</td>
                                    <td className="px-3 py-2">{row.active ? (t.taxonomy_yes || 'yes') : (t.taxonomy_no || 'no')}</td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
