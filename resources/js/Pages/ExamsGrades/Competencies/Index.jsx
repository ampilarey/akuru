import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ subjects, competencies, subjectId }) {
    const form = useForm({
        subject_id: subjectId || subjects[0]?.id || '',
        name: '',
        name_arabic: '',
        name_dhivehi: '',
        description: '',
        sort_order: 0,
    });

    return (
        <AppShell title="Competencies">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/exams/competencies/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/exams/competencies', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Subject</span>
                    <select className="form-input w-full" value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                        {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                    </select>
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Name</span>
                    <input className="form-input w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    {form.errors.name && <span className="text-xs text-red-600">{form.errors.name}</span>}
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">AR</span>
                    <input className="form-input w-full" dir="rtl" value={form.data.name_arabic} onChange={(e) => form.setData('name_arabic', e.target.value)} />
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>Create competency</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Subject</th>
                        </tr>
                    </thead>
                    <tbody>
                        {competencies.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                <td className="px-3 py-2">{row.subject_id}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
