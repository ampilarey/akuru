import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';

export default function Assessments({ course, assessments, questions, types, t = {} }) {
    // Every string below is a key in the `teach` book (slice CT2, STATUS
    // §5ol); the English is the fallback.
    const typeName = (value, label) => t[`assessment_type_${value}`] || label || value;
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
    // Reordering or removing a question posts with `router`, and its refusal
    // — a list that does not name every question once — was shown nowhere
    // (slice CT6b-2b). It is said under the assessment it came from.
    const refusals = useRowRefusals(form, attachForm);

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
        refusals.actOn(`assessment:${assessmentId}`, () => router.post(`/catalog/courses/${course.id}/assessments/${assessmentId}/questions/reorder`, { question_ids: ids }, { preserveScroll: true }));
    };

    return (
        <AppShell title={(t.assess_title || 'Assessments — :course').replace(':course', course.title)}>
            <div className="mb-4 flex flex-wrap gap-3 text-sm">
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${course.id}/outline`}>{t.nav_outline || 'Outline'}</a>
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${course.id}/activities`}>{t.catalog_activities || 'Activities'}</a>
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/questions`}>{t.nav_question_bank || 'Question bank'}</a>
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${course.id}/assessments/export`}>{t.catalog_export || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/catalog/courses/${course.id}/assessments`, { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <input className="form-input" placeholder={t.catalog_new_title || 'Title'} aria-label={t.catalog_new_title || 'Title'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <select className="form-input" aria-label={t.assess_type || 'Assessment type'} value={form.data.assessment_type} onChange={(e) => form.setData('assessment_type', e.target.value)}>
                    {/* §19's eleven types, now served from the enum that owns
                        them rather than a hardcoded controller array. */}
                    {types.map((type) => (
                        typeof type === 'string'
                            ? <option key={type} value={type}>{typeName(type)}</option>
                            : <option key={type.value} value={type.value}>{typeName(type.value, type.label)}</option>
                    ))}
                </select>
                <select className="form-input" aria-label={t.assess_status || 'Status'} value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    <option value="draft">{t.status_draft || 'draft'}</option>
                    <option value="published">{t.status_published || 'published'}</option>
                </select>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.show_correct_answers} onChange={(e) => form.setData('show_correct_answers', e.target.checked)} />
                    {t.assess_show_answers || 'Show correct answers'}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.randomize_questions} onChange={(e) => form.setData('randomize_questions', e.target.checked)} />
                    {t.assess_randomize || 'Randomize'}
                </label>
                {/* §19 "Show/hide correct answers" has a sibling: whether the
                    student sees the mark at all. It was posted as a hardcoded
                    true with no control, and read by nothing. */}
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.show_results} onChange={(e) => form.setData('show_results', e.target.checked)} />
                    {t.assess_show_mark || 'Show the mark to the student'}
                </label>
                {/* §19 "Teacher marking". Without this an assessment waited for
                    a human only if some question could not be auto-scored — so
                    a speaking or writing paper made of multiple-choice
                    questions was graded and finalised by nobody. */}
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.requires_teacher_marking} onChange={(e) => form.setData('requires_teacher_marking', e.target.checked)} />
                    {t.assess_teacher_marking || 'Needs teacher marking'}
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.assess_save || 'Save assessment'}</button>
                <FormErrors errors={form.errors} />
            </form>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    attachForm.post(`/catalog/courses/${course.id}/assessments/${attachForm.data.assessment_id}/questions`, { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" aria-label={t.assess_which || 'Assessment'} value={attachForm.data.assessment_id} onChange={(e) => attachForm.setData('assessment_id', e.target.value)}>
                    {assessments.map((row) => <option key={row.id} value={row.id}>{row.title}</option>)}
                </select>
                <select className="form-input" aria-label={t.assess_question || 'Question'} value={attachForm.data.question_id} onChange={(e) => attachForm.setData('question_id', e.target.value)}>
                    {questions.map((row) => <option key={row.id} value={row.id}>{row.title || row.question_text}</option>)}
                </select>
                <input className="form-input" type="number" min="1" aria-label={t.rubric_points || 'Points'} value={attachForm.data.points_override} onChange={(e) => attachForm.setData('points_override', e.target.value)} />
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={attachForm.data.is_required}
                        onChange={(e) => attachForm.setData('is_required', e.target.checked)}
                    />
                    {t.outline_required || 'Required'}
                </label>
                <button type="submit" className="btn-primary" disabled={attachForm.processing || assessments.length === 0 || questions.length === 0}>{t.assess_attach || 'Attach question'}</button>
                <FormErrors errors={attachForm.errors} />
            </form>
            <FormErrors errors={refusals.unplaced} className="mb-4 rounded border border-red-200 bg-red-50 py-2 pe-3" />
            <div className="space-y-3">
                {assessments.length === 0 && <p className="text-sm text-gray-500">{t.assess_none || 'No assessments yet.'}</p>}
                {assessments.map((row) => (
                    <section key={row.id} className="rounded-lg border bg-white p-4">
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <h2 className="font-medium">{row.title} <span className="text-xs uppercase text-gray-500">{t[`status_${row.status}`] || row.status} · {typeName(row.assessment_type)}</span></h2>
                            <span className="text-sm text-gray-600">{(t.assess_max || 'max :score').replace(':score', row.max_score)}</span>
                        </div>
                        <ul className="space-y-1 text-sm">
                            {row.questions.map((item, index) => (
                                <li key={item.question_id} className="flex flex-wrap items-center justify-between gap-2 border-t pt-2">
                                    <span>
                                        {index + 1}. {item.question.question_text} · {(t.assess_points || ':points pts').replace(':points', item.points_override || 1)}
                                        {item.is_required
                                            ? <span className="ms-2 text-xs uppercase text-amber-800">{t.outline_required_badge || 'required'}</span>
                                            : <span className="ms-2 text-xs uppercase text-gray-400">{t.assess_optional || 'optional'}</span>}
                                    </span>
                                    <span className="flex gap-2">
                                        <button
                                            type="button"
                                            className="btn-secondary"
                                            aria-label={(t.assess_move_up || 'Move :question up').replace(':question', item.question.question_text)}
                                            onClick={() => moveQuestion(row.id, row.questions, index, -1)}
                                        >
                                            {t.outline_up || 'Up'}
                                        </button>
                                        <button
                                            type="button"
                                            className="btn-secondary"
                                            aria-label={(t.assess_move_down || 'Move :question down').replace(':question', item.question.question_text)}
                                            onClick={() => moveQuestion(row.id, row.questions, index, 1)}
                                        >
                                            {t.outline_down || 'Down'}
                                        </button>
                                        <button
                                            type="button"
                                            className="btn-secondary"
                                            onClick={() => refusals.actOn(`assessment:${row.id}`, () => router.delete(`/catalog/courses/${course.id}/assessments/${row.id}/questions/${item.question_id}`, { preserveScroll: true }))}
                                        >
                                            {t.outline_remove || 'Remove'}
                                        </button>
                                    </span>
                                </li>
                            ))}
                        </ul>
                        <FormErrors errors={refusals.errorsFor(`assessment:${row.id}`)} className="mt-2" />
                    </section>
                ))}
            </div>
        </AppShell>
    );
}
