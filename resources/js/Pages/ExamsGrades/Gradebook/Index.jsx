import { router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ years, terms, classes, subjects, exams, competencies, rows, classId, subjectId, termId, missing_weights = false }) {
    return (
        <AppShell title="Gradebook">
            <form
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    const data = new FormData(e.currentTarget);
                    router.get('/exams/gradebook', {
                        class_id: data.get('class_id'),
                        subject_id: data.get('subject_id'),
                        term_id: data.get('term_id'),
                    });
                }}
            >
                <select className="form-input" name="term_id" defaultValue={termId || ''}>
                    <option value="">Term</option>
                    {terms.map((term) => <option key={term.id} value={term.id}>{term.name}</option>)}
                </select>
                <select className="form-input" name="class_id" defaultValue={classId || ''}>
                    <option value="">Class</option>
                    {classes.map((row) => <option key={row.id} value={row.id}>{row.name} {row.section}</option>)}
                </select>
                <select className="form-input" name="subject_id" defaultValue={subjectId || ''}>
                    <option value="">Subject</option>
                    {subjects.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                </select>
                <div className="flex gap-2">
                    <button type="submit" className="btn-secondary">Load</button>
                    {classId && subjectId && termId && (
                        <>
                            <button
                                type="button"
                                className="btn-primary"
                                onClick={() => router.post('/exams/gradebook/compute', {
                                    class_id: classId,
                                    subject_id: subjectId,
                                    term_id: termId,
                                })}
                            >
                                Recompute
                            </button>
                            <a className="btn-secondary" href={`/exams/gradebook/export?class_id=${classId}&subject_id=${subjectId}&term_id=${termId}`}>CSV</a>
                        </>
                    )}
                </div>
            </form>

            {classId && subjectId && termId && missing_weights && (
                <p className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
                    Term % / grade / rank stay blank until a weight scheme is saved for this year (and optionally this class or subject).
                    {' '}
                    <a className="underline" href="/exams/weights">Open Weights</a>
                    {' '}
                    and set type shares that add to 100, then Recompute.
                </p>
            )}

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Student</th>
                            {exams.map((exam) => <th key={exam.id} className="px-3 py-2">{exam.name}</th>)}
                            <th className="px-3 py-2">Term %</th>
                            <th className="px-3 py-2">Grade</th>
                            <th className="px-3 py-2">Rank</th>
                            {competencies.map((competency) => <th key={`c-${competency.id}`} className="px-3 py-2">{competency.name}</th>)}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>Select a class, subject, and term.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.student_id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                {exams.map((exam) => {
                                    const mark = row.marks[exam.id] || {};
                                    return (
                                        <td key={`${row.student_id}-${exam.id}`} className="px-3 py-2">
                                            {mark.is_absent ? 'Abs' : mark.is_exempt ? 'Ex' : (mark.marks ?? '—')}
                                        </td>
                                    );
                                })}
                                <td className="px-3 py-2">{row.term?.weighted_percent ?? '—'}</td>
                                <td className="px-3 py-2">{row.term?.grade ?? '—'}</td>
                                <td className="px-3 py-2">{row.term?.rank ?? '—'}</td>
                                {competencies.map((competency) => (
                                    <td key={`${row.student_id}-c-${competency.id}`} className="px-3 py-2">
                                        {row.competencies[competency.id] || '—'}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
