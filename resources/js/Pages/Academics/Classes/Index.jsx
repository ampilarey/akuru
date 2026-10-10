import { Link, useForm, router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ years, yearId, classes, teachers = [], t = {} }) {
    const form = useForm({
        academic_year_id: yearId || years[0]?.id || '',
        name: '',
        section: '',
        level: t.classes_level_default || 'Primary',
        capacity: 30,
        class_teacher_id: '',
    });

    // In the page's language (BACKLOG C21, slice OA2).
    return (
        <AppShell title={t.classes_title || 'Classes'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href={`/academics/classes/export?academic_year_id=${yearId || ''}`}>{t.export_csv || 'Export CSV'}</a>
            </div>
            <div className="mb-4 flex flex-wrap gap-2">
                {years.map((year) => (
                    <button
                        key={year.id}
                        type="button"
                        className={`rounded px-3 py-1 text-sm ${String(year.id) === String(yearId) ? 'bg-[#7C2D37] text-white' : 'bg-white border'}`}
                        onClick={() => router.get('/academics/classes', { academic_year_id: year.id })}
                    >
                        {year.name}
                    </button>
                ))}
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/academics/classes');
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-6"
            >
                <input className="form-input" placeholder={t.classes_name || 'Class name'} aria-label={t.classes_name || 'Class name'} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input className="form-input" placeholder={t.section || 'Section'} aria-label={t.section || 'Section'} value={form.data.section} onChange={(e) => form.setData('section', e.target.value)} />
                <input className="form-input" placeholder={t.classes_level || 'Level'} aria-label={t.classes_level || 'Level'} value={form.data.level} onChange={(e) => form.setData('level', e.target.value)} />
                <input className="form-input" type="number" aria-label={t.capacity || 'Capacity'} value={form.data.capacity} onChange={(e) => form.setData('capacity', e.target.value)} />
                <select
                    className="form-input"
                    aria-label={t.classes_teacher || 'Class teacher'}
                    value={form.data.class_teacher_id}
                    onChange={(e) => form.setData('class_teacher_id', e.target.value)}
                >
                    <option value="">{t.classes_no_teacher || 'No class teacher'}</option>
                    {teachers.map((teacher) => (
                        <option key={teacher.id} value={teacher.id}>{teacher.name}</option>
                    ))}
                </select>
                <button type="submit" className="btn-primary">{t.classes_create || 'Create class'}</button>
                {form.errors.name && <p className="md:col-span-6 text-sm text-red-600">{form.errors.name}</p>}
                {form.errors.class_teacher_id && <p className="md:col-span-6 text-sm text-red-600">{form.errors.class_teacher_id}</p>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_name || 'Name'}</th>
                            <th className="px-3 py-2">{t.col_section || 'Section'}</th>
                            <th className="px-3 py-2">{t.col_capacity || 'Capacity'}</th>
                            <th className="px-3 py-2">{t.classes_teacher || 'Class teacher'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {classes.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">
                                    <Link href={`/academics/classes/${row.id}`} className="text-[#7C2D37] hover:underline">{row.name}</Link>
                                </td>
                                <td className="px-3 py-2">{row.section}</td>
                                <td className="px-3 py-2">{row.capacity}</td>
                                <td className="px-3 py-2">{row.class_teacher_name || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
