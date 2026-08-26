import { Link, useForm, router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ years, yearId, classes, teachers = [] }) {
    const form = useForm({
        academic_year_id: yearId || years[0]?.id || '',
        name: '',
        section: '',
        level: 'Primary',
        capacity: 30,
        class_teacher_id: '',
    });

    return (
        <AppShell title="Classes">
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
                <input className="form-input" placeholder="Class name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input className="form-input" placeholder="Section" value={form.data.section} onChange={(e) => form.setData('section', e.target.value)} />
                <input className="form-input" placeholder="Level" value={form.data.level} onChange={(e) => form.setData('level', e.target.value)} />
                <input className="form-input" type="number" value={form.data.capacity} onChange={(e) => form.setData('capacity', e.target.value)} />
                <select
                    className="form-input"
                    value={form.data.class_teacher_id}
                    onChange={(e) => form.setData('class_teacher_id', e.target.value)}
                >
                    <option value="">No class teacher</option>
                    {teachers.map((teacher) => (
                        <option key={teacher.id} value={teacher.id}>{teacher.name}</option>
                    ))}
                </select>
                <button type="submit" className="btn-primary">Create class</button>
                {form.errors.name && <p className="md:col-span-6 text-sm text-red-600">{form.errors.name}</p>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Section</th>
                            <th className="px-3 py-2">Capacity</th>
                            <th className="px-3 py-2">Class teacher</th>
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
