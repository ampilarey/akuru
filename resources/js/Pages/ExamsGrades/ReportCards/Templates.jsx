import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Templates({ templates, sections }) {
    const form = useForm({
        name: '',
        sections: sections.join(','),
        header: 'Akuru Institute',
        footer: 'Official report card',
        active: true,
    });

    return (
        <AppShell title="Report card templates">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/exams/report-templates/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/exams/report-templates', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2"
            >
                <input className="form-input" placeholder="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input className="form-input" placeholder="Header" value={form.data.header} onChange={(e) => form.setData('header', e.target.value)} />
                <input className="form-input md:col-span-2" placeholder="Sections" value={form.data.sections} onChange={(e) => form.setData('sections', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Create template</button>
                {form.errors.name && <span className="text-xs text-red-600">{form.errors.name}</span>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Sections</th>
                            <th className="px-3 py-2">Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        {templates.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                <td className="px-3 py-2">{(row.sections || []).join(', ')}</td>
                                <td className="px-3 py-2">{row.active ? 'yes' : 'no'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
