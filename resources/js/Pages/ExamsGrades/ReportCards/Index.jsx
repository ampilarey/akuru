import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ classes, terms, cards, unpublished, classId, termId }) {
    const generate = useForm({
        class_id: classId || classes[0]?.id || '',
        term_id: termId || terms[0]?.id || '',
        locale: 'en',
    });
    const publish = useForm({
        class_id: classId || classes[0]?.id || '',
        term_id: termId || terms[0]?.id || '',
    });
    const comment = useForm({
        report_card_id: cards[0]?.id || '',
        comment_type: 'class_teacher',
        comment: '',
    });
    const transcript = useForm({
        student_id: cards[0]?.student_id || '',
        locale: 'en',
    });

    return (
        <AppShell title="Report cards">
            <div className="mb-4 flex flex-wrap justify-between gap-2">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        const data = new FormData(e.currentTarget);
                        router.get('/exams/report-cards', {
                            class_id: data.get('class_id'),
                            term_id: data.get('term_id'),
                        });
                    }}
                    className="flex gap-2"
                >
                    <select className="form-input" name="class_id" defaultValue={classId || ''}>
                        <option value="">All classes</option>
                        {classes.map((row) => <option key={row.id} value={row.id}>{row.name} {row.section}</option>)}
                    </select>
                    <select className="form-input" name="term_id" defaultValue={termId || ''}>
                        <option value="">All terms</option>
                        {terms.map((term) => <option key={term.id} value={term.id}>{term.name}</option>)}
                    </select>
                    <button type="submit" className="btn-secondary">Filter</button>
                </form>
                <a className="btn-secondary" href={`/exams/report-cards/export?class_id=${classId || ''}&term_id=${termId || ''}`}>Export CSV</a>
            </div>

            {unpublished.length > 0 && (
                <div className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
                    {unpublished.length} unpublished report card{unpublished.length === 1 ? '' : 's'}.
                </div>
            )}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    generate.post('/exams/report-cards/generate', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" value={generate.data.class_id} onChange={(e) => generate.setData('class_id', e.target.value)}>
                    {classes.map((row) => <option key={row.id} value={row.id}>{row.name} {row.section}</option>)}
                </select>
                <select className="form-input" value={generate.data.term_id} onChange={(e) => generate.setData('term_id', e.target.value)}>
                    {terms.map((term) => <option key={term.id} value={term.id}>{term.name}</option>)}
                </select>
                <select className="form-input" value={generate.data.locale} onChange={(e) => generate.setData('locale', e.target.value)}>
                    <option value="en">EN</option>
                    <option value="dv">DV</option>
                    <option value="ar">AR</option>
                </select>
                <button type="submit" className="btn-primary" disabled={generate.processing}>Generate</button>
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    publish.post('/exams/report-cards/publish', { preserveScroll: true });
                }}
                className="mb-4 flex flex-wrap gap-3 rounded-lg border bg-white p-4"
            >
                <select className="form-input" value={publish.data.class_id} onChange={(e) => publish.setData('class_id', e.target.value)}>
                    {classes.map((row) => <option key={row.id} value={row.id}>{row.name} {row.section}</option>)}
                </select>
                <select className="form-input" value={publish.data.term_id} onChange={(e) => publish.setData('term_id', e.target.value)}>
                    {terms.map((term) => <option key={term.id} value={term.id}>{term.name}</option>)}
                </select>
                <button type="submit" className="btn-secondary" disabled={publish.processing}>Publish ready cards</button>
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    comment.post('/exams/report-cards/comment', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" value={comment.data.report_card_id} onChange={(e) => comment.setData('report_card_id', e.target.value)}>
                    {cards.map((card) => <option key={card.id} value={card.id}>{card.student_name} — {card.term_name}</option>)}
                </select>
                <select className="form-input" value={comment.data.comment_type} onChange={(e) => comment.setData('comment_type', e.target.value)}>
                    <option value="class_teacher">Class teacher</option>
                    <option value="head">Head</option>
                </select>
                <input className="form-input" placeholder="Comment" value={comment.data.comment} onChange={(e) => comment.setData('comment', e.target.value)} />
                <button type="submit" className="btn-secondary" disabled={comment.processing}>Save comment</button>
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    window.location.href = `/exams/transcript?student_id=${transcript.data.student_id}&locale=${transcript.data.locale}`;
                }}
                className="mb-4 flex flex-wrap gap-3 rounded-lg border bg-white p-4"
            >
                <select className="form-input" value={transcript.data.student_id} onChange={(e) => transcript.setData('student_id', e.target.value)}>
                    {cards.map((card) => <option key={card.student_id} value={card.student_id}>{card.student_name}</option>)}
                </select>
                <button type="submit" className="btn-secondary">Open transcript</button>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Class</th>
                            <th className="px-3 py-2">Term</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Document</th>
                        </tr>
                    </thead>
                    <tbody>
                        {cards.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No report cards yet.</td></tr>
                        )}
                        {cards.map((card) => (
                            <tr key={card.id} className="border-t">
                                <td className="px-3 py-2">{card.student_name}</td>
                                <td className="px-3 py-2">{card.class_name}</td>
                                <td className="px-3 py-2">{card.term_name}</td>
                                <td className="px-3 py-2">{card.status}</td>
                                <td className="px-3 py-2">
                                    {card.document_id ? <a className="text-[#7C2D37] underline" href={`/exams/report-cards/${card.id}/download`}>Download</a> : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
