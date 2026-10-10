import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ yearId, years, types, categories, records, canManage, t = {} }) {
    const form = useForm({
        student_id: '',
        academic_year_id: yearId || years[0]?.id || '',
        type: types[0] || 'notice',
        category: categories[0] || 'other',
        description: '',
        points: '',
        date: '',
        parent_visible: true,
        requires_followup: false,
    });
    // In the page's language (BACKLOG C21, slice OA3). A record's type is a
    // code, named here; a category is the school's own word, and the three a
    // school starts with (conduct, homework, other) are named too.
    const typeName = (type) => t[`behavior_type_${type}`] || type;
    const categoryName = (category) => t[`behavior_category_${category}`] || category;

    return (
        <AppShell title={t.behavior_title || 'Behavior records'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <select className="form-input" aria-label={t.year || 'Year'} value={yearId || ''} onChange={(e) => router.get(`/academics/behavior?academic_year_id=${e.target.value}`)}>
                    <option value="">{t.year || 'Year'}</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <a className="btn-secondary" href={`/academics/behavior/export?academic_year_id=${yearId || ''}`}>{t.export_csv || 'Export CSV'}</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/academics/behavior', { preserveScroll: true });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.behavior_student_id || 'Student id'}</span>
                    <input className="form-input w-full" aria-label={t.behavior_student_id || 'Student id'} value={form.data.student_id} onChange={(e) => form.setData('student_id', e.target.value)} />
                    {form.errors.student_id && <span className="text-xs text-red-600">{form.errors.student_id}</span>}
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.type || 'Type'}</span>
                    <select className="form-input w-full" aria-label={t.type || 'Type'} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((type) => <option key={type} value={type}>{typeName(type)}</option>)}
                    </select>
                    {form.errors.type && <span className="text-xs text-red-600">{form.errors.type}</span>}
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.behavior_category || 'Category'}</span>
                    <select className="form-input w-full" aria-label={t.behavior_category || 'Category'} value={form.data.category} onChange={(e) => form.setData('category', e.target.value)}>
                        {categories.map((category) => <option key={category} value={category}>{categoryName(category)}</option>)}
                    </select>
                    {form.errors.category && <span className="text-xs text-red-600">{form.errors.category}</span>}
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.date || 'Date'}</span>
                    <input className="form-input w-full" type="date" aria-label={t.date || 'Date'} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                    {form.errors.date && <span className="text-xs text-red-600">{form.errors.date}</span>}
                </label>
                <label className="block text-sm md:col-span-2">
                    <span className="mb-1 block text-gray-600">{t.behavior_description || 'Description'}</span>
                    <input className="form-input w-full" aria-label={t.behavior_description || 'Description'} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    {form.errors.description && <span className="text-xs text-red-600">{form.errors.description}</span>}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.parent_visible} onChange={(e) => form.setData('parent_visible', e.target.checked)} />
                    {t.behavior_parent_visible || 'Parent visible'}
                </label>
                <button type="submit" className="btn-primary justify-self-start">{t.behavior_record || 'Record'}</button>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_date || 'Date'}</th>
                            <th className="px-3 py-2">{t.col_student || 'Student'}</th>
                            <th className="px-3 py-2">{t.col_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.behavior_category || 'Category'}</th>
                            {/* The description is required on save, is already in the
                                props, and was displayed nowhere — a teacher had to
                                export the CSV to read back what they had written. */}
                            <th className="px-3 py-2">{t.behavior_col_note || 'Note'}</th>
                            <th className="px-3 py-2">{t.behavior_col_visible || 'Visible'}</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {records.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={7}>{t.behavior_none || 'No behavior records yet.'}</td></tr>
                        )}
                        {records.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.date}</td>
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{typeName(row.type)}</td>
                                <td className="px-3 py-2">{categoryName(row.category)}</td>
                                <td className="px-3 py-2">{row.description}</td>
                                <td className="px-3 py-2">{row.parent_visible ? (t.yes || 'Yes') : (t.no || 'No')}</td>
                                <td className="px-3 py-2">
                                    {canManage && (
                                        <button type="button" className="text-red-700 underline" onClick={() => router.delete(`/academics/behavior/${row.id}`)}>{t.delete || 'Delete'}</button>
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
