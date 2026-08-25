import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ yearId, years, types, categories, records, canManage }) {
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

    return (
        <AppShell title="Behavior records">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <select className="form-input" value={yearId || ''} onChange={(e) => router.get(`/academics/behavior?academic_year_id=${e.target.value}`)}>
                    <option value="">Year</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <a className="btn-secondary" href={`/academics/behavior/export?academic_year_id=${yearId || ''}`}>Export CSV</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/academics/behavior', { preserveScroll: true });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Student id</span>
                    <input className="form-input w-full" value={form.data.student_id} onChange={(e) => form.setData('student_id', e.target.value)} />
                    {form.errors.student_id && <span className="text-xs text-red-600">{form.errors.student_id}</span>}
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Type</span>
                    <select className="form-input w-full" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((type) => <option key={type} value={type}>{type}</option>)}
                    </select>
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Category</span>
                    <select className="form-input w-full" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)}>
                        {categories.map((category) => <option key={category} value={category}>{category}</option>)}
                    </select>
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Date</span>
                    <input className="form-input w-full" type="date" value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                </label>
                <label className="block text-sm md:col-span-2">
                    <span className="mb-1 block text-gray-600">Description</span>
                    <input className="form-input w-full" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.parent_visible} onChange={(e) => form.setData('parent_visible', e.target.checked)} />
                    Parent visible
                </label>
                <button type="submit" className="btn-primary justify-self-start">Record</button>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Category</th>
                            <th className="px-3 py-2">Visible</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {records.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.date}</td>
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.type}</td>
                                <td className="px-3 py-2">{row.category}</td>
                                <td className="px-3 py-2">{row.parent_visible ? 'yes' : 'no'}</td>
                                <td className="px-3 py-2">
                                    {canManage && (
                                        <button type="button" className="text-red-700 underline" onClick={() => router.delete(`/academics/behavior/${row.id}`)}>Delete</button>
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
