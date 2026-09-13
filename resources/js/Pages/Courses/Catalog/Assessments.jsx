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
        requires_teacher_marking: false,
    });
    const attachForm = useForm({
        assessment_id: assessments[0]?.id || '',
        question_id: questions[0]?.id || '',
        points_override: 1,
        // SPEC §21's "Is required". The controller has always read it — with a
        // default of true — and no control ever sent it, so every attached
        // question was silently required and nothing enforced it either.
        is_required: true,
    });

    // §21's "Position". Attaching assigned max+1 and nothing could ever change
    // it, so the order questions happened to be attached in was the order every
    // student sat them in. Moving one posts the whole list, which is what
    // `ReorderAssessmentQuestionsAction` requires — a partial list would
    // renumber some rows and leave the rest colliding.
    const moveQuestion = (assessmentId, items, index, delta) => {
        const ids = items.map((item) => item.question_id);
        const target = index + delta;
        if (target < 0 || target >= ids.length) {
            return;
        }
        [ids[index], ids[target]] = [ids[target], ids[index]];
        router.post(`/catalog/courses/${course.id}/assessments/${assessmentId}/questions/reorder`, { question_ids: ids }, { preserveScroll: true });
    };

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
                    {/* §19's eleven types, now served from the enum that owns
                        them rather than a hardcoded controller array. */}
                    {types.map((type) => (
                        typeof type === 'string'
                            ? <option key={type} value={type}>{type}</option>
                            : <option key={type.value} value={type.value}>{type.label}</option>
                    ))}
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
                {/* §19 "Show/hide correct answers" has a sibling: whether the
                    student sees the mark at all. It was posted as a hardcoded
                    true with no control, and read by nothing. */}
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.show_results} onChange={(e) => form.setData('show_results', e.target.checked)} />
                    Show the mark to the student
                </label>
                {/* §19 "Teacher marking". Without this an assessment waited for
                    a human only if some question could not be auto-scored — so
                    a speaking or writing paper made of multiple-choice
                    questions was graded and finalised by nobody. */}
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.requires_teacher_marking} onChange={(e) => form.setData('requires_teacher_marking', e.target.checked)} />
                    Needs teacher marking
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
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={attachForm.data.is_required}
                        onChange={(e) => attachForm.setData('is_required', e.target.checked)}
                    />
                    Required
                </label>
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
                            {row.questions.map((item, index) => (
                                <li key={item.question_id} className="flex flex-wrap items-center justify-between gap-2 border-t pt-2">
                                    <span>
                                        {index + 1}. {item.question.question_text} · {item.points_override || 1} pts
                                        {item.is_required
                                            ? <span className="ms-2 text-xs uppercase text-amber-800">required</span>
                                            : <span className="ms-2 text-xs uppercase text-gray-400">optional</span>}
                                    </span>
                                    <span className="flex gap-2">
                                        <button
                                            type="button"
                                            className="btn-secondary"
                                            aria-label={`Move ${item.question.question_text} up`}
                                            onClick={() => moveQuestion(row.id, row.questions, index, -1)}
                                        >
                                            Up
                                        </button>
                                        <button
                                            type="button"
                                            className="btn-secondary"
                                            aria-label={`Move ${item.question.question_text} down`}
                                            onClick={() => moveQuestion(row.id, row.questions, index, 1)}
                                        >
                                            Down
                                        </button>
                                        <button
                                            type="button"
                                            className="btn-secondary"
                                            onClick={() => router.delete(`/catalog/courses/${course.id}/assessments/${row.id}/questions/${item.question_id}`)}
                                        >
                                            Remove
                                        </button>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>
        </AppShell>
    );
}
