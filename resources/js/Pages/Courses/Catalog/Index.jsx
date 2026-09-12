import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ rows, subjects, canPublish, unlockModes = [] }) {
    const form = useForm({
        title: '',
        title_dv: '',
        title_ar: '',
        subject_id: subjects[0]?.id || '',
        language: 'en',
        // SPEC §26. Sequential is the default the resolver applies, so the
        // form opens on the behaviour a course would have had anyway.
        unlock_mode: 'sequential',
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
                {/* SPEC §26 "Admin must be able to configure unlock rules."
                    Unlock was hardcoded sequential for every course, so "All
                    lessons open" — the first rule §26 lists — could not be
                    chosen at all. */}
                <select className="form-input" value={form.data.unlock_mode} onChange={(e) => form.setData('unlock_mode', e.target.value)}>
                    {unlockModes.map((mode) => <option key={mode.value} value={mode.value}>{mode.label}</option>)}
                </select>
                <button type="submit" className="btn-primary" disabled={form.processing}>Save draft</button>
                {form.errors.title && <span className="text-xs text-red-600">{form.errors.title}</span>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Subject</th>
                            <th className="px-3 py-2">Workflow</th>
                            <th className="px-3 py-2">Unlock</th>
                            <th className="px-3 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No engine courses yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">
                                    <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${row.id}/outline`}>{row.title}</a>
                                    <a className="ms-3 text-xs text-[#7C2D37] hover:underline" href={`/catalog/courses/${row.id}/activities`}>Activities</a>
                                </td>
                                <td className="px-3 py-2">{row.subject_name || '—'}</td>
                                <td className="px-3 py-2">{row.workflow_status}</td>
                                <td className="px-3 py-2">
                                    {row.workflow_status === 'draft' ? (
                                        <select
                                            className="form-input"
                                            value={row.unlock_mode}
                                            onChange={(e) => router.post(`/catalog/courses/${row.id}`, {
                                                _method: 'put',
                                                title: row.title,
                                                title_dv: row.title_dv || '',
                                                title_ar: row.title_ar || '',
                                                subject_id: row.subject_id || '',
                                                language: row.language || 'en',
                                                unlock_mode: e.target.value,
                                            }, { preserveScroll: true })}
                                        >
                                            {unlockModes.map((mode) => <option key={mode.value} value={mode.value}>{mode.label}</option>)}
                                        </select>
                                    ) : (
                                        // Only draft courses are editable
                                        // (SaveEngineCourseAction refuses the
                                        // rest), so show the mode rather than a
                                        // control that would always fail.
                                        <span>{(unlockModes.find((m) => m.value === row.unlock_mode) || {}).label || row.unlock_mode}</span>
                                    )}
                                </td>
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
