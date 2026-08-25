import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ rows, subjects, canPublish }) {
    const form = useForm({
        title: '',
        title_dv: '',
        title_ar: '',
        subject_id: subjects[0]?.id || '',
        language: 'en',
    });

    return (
        <AppShell title="Course catalog">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/courses/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/catalog/courses', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-5"
            >
                <input className="form-input" placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <select className="form-input" value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                    {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name_en}</option>)}
                </select>
                <select className="form-input" value={form.data.language} onChange={(e) => form.setData('language', e.target.value)}>
                    <option value="en">EN</option>
                    <option value="dv">DV</option>
                    <option value="ar">AR</option>
                    <option value="mixed">Mixed</option>
                </select>
                <button type="submit" className="btn-primary" disabled={form.processing}>Save draft</button>
                {form.errors.title && <span className="text-xs text-red-600">{form.errors.title}</span>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Subject</th>
                            <th className="px-3 py-2">Workflow</th>
                            <th className="px-3 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No engine courses yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.title}</td>
                                <td className="px-3 py-2">{row.subject_name || '—'}</td>
                                <td className="px-3 py-2">{row.workflow_status}</td>
                                <td className="px-3 py-2">
                                    {row.workflow_status === 'draft' && (
                                        <button type="button" className="btn-secondary" onClick={() => router.post(`/catalog/courses/${row.id}/transition`, { workflow_status: 'in_review' })}>Submit review</button>
                                    )}
                                    {row.workflow_status === 'in_review' && canPublish && (
                                        <button type="button" className="btn-secondary" onClick={() => router.post(`/catalog/courses/${row.id}/transition`, { workflow_status: 'published' })}>Publish</button>
                                    )}
                                    {row.workflow_status === 'in_review' && (
                                        <button type="button" className="btn-secondary" onClick={() => router.post(`/catalog/courses/${row.id}/transition`, { workflow_status: 'draft' })}>Return draft</button>
                                    )}
                                    {row.workflow_status === 'published' && (
                                        <button type="button" className="btn-secondary" onClick={() => router.post(`/catalog/courses/${row.id}/transition`, { workflow_status: 'archived' })}>Archive</button>
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
