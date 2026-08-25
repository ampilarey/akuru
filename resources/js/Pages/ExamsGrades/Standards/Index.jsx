import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ subjects, terms, exams, standards, coverage, subjectId, termId }) {
    const form = useForm({
        subject_id: subjectId || '',
        code: '',
        title: '',
        parent_id: '',
        active: true,
    });
    const tag = useForm({
        standard_id: standards[0]?.id || '',
        taggable_type: 'exam',
        taggable_id: exams[0]?.id || '',
    });

    return (
        <AppShell title="Standards">
            <div className="mb-4 flex flex-wrap justify-between gap-2">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        const data = new FormData(e.currentTarget);
                        router.get('/exams/standards', {
                            subject_id: data.get('subject_id'),
                            term_id: data.get('term_id'),
                        });
                    }}
                    className="flex gap-2"
                >
                    <select className="form-input" name="subject_id" defaultValue={subjectId || ''}>
                        <option value="">All subjects</option>
                        {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                    </select>
                    <select className="form-input" name="term_id" defaultValue={termId || ''}>
                        <option value="">All terms</option>
                        {terms.map((term) => <option key={term.id} value={term.id}>{term.name}</option>)}
                    </select>
                    <button type="submit" className="btn-secondary">Coverage</button>
                </form>
                <a className="btn-secondary" href={`/exams/standards/export?subject_id=${subjectId || ''}&term_id=${termId || ''}`}>Export CSV</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/exams/standards', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <input className="form-input" placeholder="Code" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                <input className="form-input" placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <select className="form-input" value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                    <option value="">Any subject</option>
                    {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                </select>
                <button type="submit" className="btn-primary" disabled={form.processing}>Create standard</button>
                {form.errors.code && <span className="text-xs text-red-600">{form.errors.code}</span>}
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    tag.post('/exams/standards/tag', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" value={tag.data.standard_id} onChange={(e) => tag.setData('standard_id', e.target.value)}>
                    {standards.map((row) => <option key={row.id} value={row.id}>{row.code} {row.title}</option>)}
                </select>
                <select className="form-input" value={tag.data.taggable_type} onChange={(e) => tag.setData('taggable_type', e.target.value)}>
                    <option value="exam">Exam</option>
                    <option value="plan_topic">Plan topic</option>
                </select>
                <select className="form-input" value={tag.data.taggable_id} onChange={(e) => tag.setData('taggable_id', e.target.value)}>
                    {exams.map((exam) => <option key={exam.id} value={exam.id}>{exam.name}</option>)}
                </select>
                <button type="submit" className="btn-secondary" disabled={tag.processing}>Tag exam</button>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Code</th>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Exams</th>
                            <th className="px-3 py-2">Topics</th>
                            <th className="px-3 py-2">Covered</th>
                        </tr>
                    </thead>
                    <tbody>
                        {coverage.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2 font-mono">{row.code}</td>
                                <td className="px-3 py-2">{row.title}</td>
                                <td className="px-3 py-2">{row.exams_tagged}</td>
                                <td className="px-3 py-2">{row.topics_tagged}</td>
                                <td className="px-3 py-2">{row.covered ? 'yes' : 'no'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
