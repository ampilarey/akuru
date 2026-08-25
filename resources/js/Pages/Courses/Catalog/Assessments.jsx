import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Assessments({ course, assessments, questions, types }) {
    const form = useForm({
        title: '',
        assessment_type: 'lesson_quiz',
        status: 'published',
        retake_limit: 2,
        show_correct_answers: true,
        show_results: true,
        randomize_questions: false,
    });
    const attachForm = useForm({
        assessment_id: assessments[0]?.id || '',
        question_id: questions[0]?.id || '',
        points_override: 1,
    });

    return (
        <AppShell title={`Assessments — ${course.title}`}>
            <div className="mb-4 flex flex-wrap gap-3 text-sm">
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${course.id}/outline`}>Outline</a>
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${course.id}/activities`}>Activities</a>
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/questions`}>Question bank</a>
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${course.id}/assessments/export`}>Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/catalog/courses/${course.id}/assessments`, { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <input className="form-input" placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <select className="form-input" value={form.data.assessment_type} onChange={(e) => form.setData('assessment_type', e.target.value)}>
                    {types.map((type) => <option key={type} value={type}>{type}</option>)}
                </select>
                <select className="form-input" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    <option value="draft">draft</option>
                    <option value="published">published</option>
                </select>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.show_correct_answers} onChange={(e) => form.setData('show_correct_answers', e.target.checked)} />
                    Show correct answers
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.randomize_questions} onChange={(e) => form.setData('randomize_questions', e.target.checked)} />
                    Randomize
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>Save assessment</button>
            </form>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    attachForm.post(`/catalog/courses/${course.id}/assessments/${attachForm.data.assessment_id}/questions`, { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" value={attachForm.data.assessment_id} onChange={(e) => attachForm.setData('assessment_id', e.target.value)}>
                    {assessments.map((row) => <option key={row.id} value={row.id}>{row.title}</option>)}
                </select>
                <select className="form-input" value={attachForm.data.question_id} onChange={(e) => attachForm.setData('question_id', e.target.value)}>
                    {questions.map((row) => <option key={row.id} value={row.id}>{row.title || row.question_text}</option>)}
                </select>
                <input className="form-input" type="number" min="1" value={attachForm.data.points_override} onChange={(e) => attachForm.setData('points_override', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={attachForm.processing || assessments.length === 0 || questions.length === 0}>Attach question</button>
            </form>
            <div className="space-y-3">
                {assessments.length === 0 && <p className="text-sm text-gray-500">No assessments yet.</p>}
                {assessments.map((row) => (
                    <section key={row.id} className="rounded-lg border bg-white p-4">
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <h2 className="font-medium">{row.title} <span className="text-xs uppercase text-gray-500">{row.status} · {row.assessment_type}</span></h2>
                            <span className="text-sm text-gray-600">max {row.max_score}</span>
                        </div>
                        <ul className="space-y-1 text-sm">
                            {row.questions.map((item) => (
                                <li key={item.question_id} className="flex items-center justify-between gap-2 border-t pt-2">
                                    <span>{item.question.question_text} · {item.points_override || 1} pts</span>
                                    <button
                                        type="button"
                                        className="btn-secondary"
                                        onClick={() => router.delete(`/catalog/courses/${course.id}/assessments/${row.id}/questions/${item.question_id}`)}
                                    >
                                        Remove
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>
        </AppShell>
    );
}
