import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

function exportHref(filters) {
    const params = new URLSearchParams();
    Object.entries(filters || {}).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, String(value));
        }
    });
    const qs = params.toString();
    return qs ? `/catalog/reviews/export?${qs}` : '/catalog/reviews/export';
}

/**
 * SPEC §36 asks the teacher to "open student submissions", "play audio/voice
 * submissions" and "view uploaded files". All three were answered with
 * `JSON.stringify(row.answers)` inside a collapsed `<details>` — which is not
 * opening a submission, and plays nothing.
 *
 * The raw JSON stays, below, because activities score in shapes this cannot
 * render and a teacher looking at a disputed mark should still be able to see
 * exactly what was recorded.
 */
function Submission({ answers, t }) {
    const attachments = Array.isArray(answers?.attachments) ? answers.attachments : [];
    const text = typeof answers?.text === 'string' ? answers.text.trim() : '';

    return (
        <div className="mb-3">
            {text !== '' && (
                <blockquote className="mb-3 whitespace-pre-wrap rounded border-s-4 border-[#7C2D37] bg-[#F9F4EE] p-3 text-sm">
                    {text}
                </blockquote>
            )}
            {attachments.length > 0 && (
                <ul className="mb-3 space-y-2">
                    {attachments.map((file) => (
                        <li key={file.id} className="rounded border bg-white p-2 text-sm">
                            <a className="text-[#7C2D37] hover:underline" href={`/catalog/media/${file.id}`}>
                                {file.original_name || (t.review_file || 'File :id').replace(':id', file.id)}
                            </a>
                            {(file.mime || '').startsWith('audio/') && (
                                <audio className="mt-2 w-full" controls preload="none" src={`/catalog/media/${file.id}`} />
                            )}
                            {(file.mime || '').startsWith('image/') && (
                                <img className="mt-2 max-h-64 rounded" src={`/catalog/media/${file.id}`} alt={file.original_name || t.review_upload || 'Upload'} />
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {text === '' && attachments.length === 0 && (
                <p className="mb-3 text-sm text-gray-500">{t.review_nothing || 'Nothing written and nothing uploaded.'}</p>
            )}
            <details>
                <summary className="cursor-pointer text-xs text-gray-500">{t.review_raw || 'Raw submission'}</summary>
                <pre className="mt-2 overflow-x-auto rounded bg-[#F9F4EE] p-3 text-xs">{JSON.stringify(answers, null, 2)}</pre>
            </details>
        </div>
    );
}

/**
 * Moodle parity slice M2 (STATUS §5oi): with a rubric, the marker picks one
 * level per criterion and the score follows — the points out of the best,
 * scaled to what the item is marked out of. The server does the same sum and
 * is the one that counts; this only shows it before saving.
 */
function RubricMarker({ rubric, chosen, onChoose, outOf, t }) {
    const points = rubric.criteria.reduce((sum, c) => sum + (Number(c.levels.find((l) => l.id === chosen[c.id])?.points) || 0), 0);
    const complete = rubric.criteria.every((c) => chosen[c.id]);
    const score = Math.round((points / Math.max(1, rubric.max_points)) * outOf);

    return (
        <div className="mb-3 overflow-x-auto rounded border border-[#E6D9C5]" data-testid="rubric-marker">
            <p className="bg-[#F3EBE0] px-3 py-2 text-sm font-medium">{rubric.title}</p>
            <table className="min-w-full text-sm">
                <tbody>
                    {rubric.criteria.map((criterion) => (
                        <tr key={criterion.id} className="border-t align-top">
                            <th className="px-2 py-2 text-start font-medium">{criterion.title}</th>
                            {criterion.levels.map((level) => (
                                <td key={level.id} className="px-1 py-1">
                                    <label className={`block cursor-pointer rounded border p-2 ${chosen[criterion.id] === level.id ? 'border-[#7C2D37] bg-[#F9F4EE]' : 'border-transparent'}`}>
                                        <input
                                            type="radio"
                                            className="me-1"
                                            name={`rubric-${criterion.id}`}
                                            checked={chosen[criterion.id] === level.id}
                                            onChange={() => onChoose(criterion.id, level.id)}
                                        />
                                        {level.label}
                                        <span className="block text-xs text-gray-500">{level.points}</span>
                                    </label>
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
            <p className="px-3 py-2 text-sm" data-testid="rubric-total">
                {complete
                    ? (t.rubric_mark_total || 'Rubric: :points of :max points, so :score out of :out_of')
                        .replace(':points', points).replace(':max', rubric.max_points).replace(':score', score).replace(':out_of', outOf)
                    : `${points} / ${rubric.max_points}`}
            </p>
        </div>
    );
}

/** `activity` and `assessment` are codes; the page names them. */
const kindName = (t, kind) => t[`review_kind_${kind}`] || kind;

function ReviewRow({ row, t = {} }) {
    const form = useForm({
        kind: row.kind,
        attempt_id: row.id,
        score: row.score || 0,
        max_score: row.max_score || 1,
        feedback: row.feedback || '',
        rubric: {},
    });
    const rubric = row.rubric || null;
    const outOf = Number(row.max_score) || rubric?.max_points || 1;
    const waiting = row.waiting_hours == null
        ? ''
        : (row.waiting_hours >= 24
            ? (t.review_waiting_days || 'waiting :count days').replace(':count', Math.floor(row.waiting_hours / 24))
            : (t.review_waiting_hours || 'waiting :count hours').replace(':count', row.waiting_hours));

    return (
        <article className="rounded-lg border bg-white p-4">
            <h2 className="mb-1 font-medium">{row.title} <span className="text-xs uppercase text-gray-500">{kindName(t, row.kind)}</span></h2>
            <p className="mb-1 text-sm text-gray-700">{row.student_name || t.cert_student || 'Student'} · {row.course_title || t.questions_course || 'Course'}{waiting ? ` · ${waiting}` : ''}</p>
            <p className="mb-3 text-sm text-gray-600">{row.prompt || t.review_prompt_fallback || 'Teacher-marked submission'}</p>
            <Submission answers={row.answers} t={t} />
            {rubric && (
                <RubricMarker
                    rubric={rubric}
                    chosen={form.data.rubric}
                    outOf={outOf}
                    t={t}
                    onChoose={(criterionId, levelId) => form.setData('rubric', { ...form.data.rubric, [criterionId]: levelId })}
                />
            )}
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/catalog/reviews', { preserveScroll: true });
                }}
                className="grid gap-3 md:grid-cols-4"
            >
                {!rubric && (
                    <>
                        <input className="form-input" type="number" min="0" value={form.data.score} onChange={(e) => form.setData('score', e.target.value)} aria-label={t.activities_col_score || 'Score'} />
                        <input className="form-input" type="number" min="1" value={form.data.max_score} onChange={(e) => form.setData('max_score', e.target.value)} aria-label={t.activities_max_score || 'Max score'} />
                    </>
                )}
                <input className="form-input md:col-span-2" placeholder={t.review_feedback || 'Feedback'} aria-label={t.review_feedback || 'Feedback'} value={form.data.feedback} onChange={(e) => form.setData('feedback', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.review_score_release || 'Score and release'}</button>
                <FormErrors errors={form.errors} />
            </form>
        </article>
    );
}

function ReportTable({ rows, empty, t }) {
    return (
        <div className="overflow-x-auto rounded-lg border bg-white">
            <table className="min-w-full text-sm">
                <thead className="bg-[#F3EBE0] text-start">
                    <tr>
                        <th className="px-3 py-2">{t.cert_student || 'Student'}</th>
                        <th className="px-3 py-2">{t.questions_course || 'Course'}</th>
                        <th className="px-3 py-2">{t.review_col_item || 'Item'}</th>
                        <th className="px-3 py-2">{t.activities_col_score || 'Score'}</th>
                        <th className="px-3 py-2">{t.review_col_reason || 'Reason'}</th>
                        <th className="px-3 py-2">{t.review_col_recommendation || 'Recommendation'}</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && (
                        <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{empty}</td></tr>
                    )}
                    {rows.map((row) => (
                        <tr key={`${row.kind}-${row.attempt_id}`} className="border-t">
                            <td className="px-3 py-2">{row.student_name}</td>
                            <td className="px-3 py-2">{row.course_title || '—'}</td>
                            <td className="px-3 py-2">{row.title} <span className="text-xs uppercase text-gray-500">{kindName(t, row.kind)}</span></td>
                            <td className="px-3 py-2">{row.score}/{row.max_score} ({row.percent}%)</td>
                            <td className="px-3 py-2">{row.reason}</td>
                            <td className="px-3 py-2">{row.recommendation}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Reviews({
    rows = [],
    weaknesses = [],
    revisions = [],
    years = [],
    courses = [],
    filters = {},
    pending_count = 0,
    weak_item_count = 0,
    weak_student_count = 0,
    scope = {},
    t = {},
}) {
    // Every string below is a key in the `teach` book (slice CT3, STATUS
    // §5on); the English is the fallback.
    return (
        <AppShell title={t.review_title || 'Teacher review'}>
            {/* C16 slice N6: a reviewer without courses.manage sees their own courses only, and is told so — or told that none are assigned yet. */}
            {scope.own_courses && scope.course_count > 0 && (
                <p className="mb-4 rounded border border-[#E6D9C5] bg-[#F9F4EE] p-3 text-sm text-gray-700" data-testid="review-scope">
                    {(t.reviews_scope_own || 'Showing the submissions from your own courses only (:count).').replace(':count', scope.course_count)}
                </p>
            )}
            {/* Moodle parity slice M3: a teacher's own course forums, which they moderate. */}
            {scope.own_courses && courses.length > 0 && (
                <p className="mb-4 flex flex-wrap gap-3 text-sm" data-testid="review-forums">
                    <span className="text-gray-600">{t.forums || 'Course forums'}:</span>
                    {courses.map((course) => (
                        <a key={course.id} className="text-[#7C2D37] hover:underline" href={`/learn/courses/${course.id}/forum`}>{course.title}</a>
                    ))}
                </p>
            )}
            {scope.own_courses && !scope.course_count && (
                <p className="mb-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" data-testid="review-scope-empty">
                    {t.reviews_scope_none || 'No courses are assigned to you yet. The office links your sign-in to your instructor profile and assigns courses to it on the course form; then their submissions appear here.'}
                </p>
            )}
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">
                    {(t.review_counts || ':pending pending · :students weak students · :items weak items')
                        .replace(':pending', pending_count).replace(':students', weak_student_count).replace(':items', weak_item_count)}
                </p>
                <a className="btn-secondary" href={exportHref(filters)}>{t.catalog_export || 'Export CSV'}</a>
            </div>
            <form method="get" action="/catalog/reviews" className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4">
                <select className="form-input" name="academic_year_id" aria-label={t.cert_year || 'Academic year'} defaultValue={filters.academic_year_id || ''}>
                    <option value="">{t.review_all_years || 'All years'}</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <select className="form-input" name="course_id" aria-label={t.questions_course || 'Course'} defaultValue={filters.course_id || ''}>
                    <option value="">{t.review_all_courses || 'All courses'}</option>
                    {courses.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}
                </select>
                <input className="form-input" type="number" min="1" max="100" name="threshold" defaultValue={filters.threshold || 50} aria-label={t.review_threshold || 'Weakness threshold percent'} />
                <button type="submit" className="btn-secondary">{t.questions_filter || 'Filter'}</button>
            </form>

            <h2 className="mb-2 text-sm font-medium">{t.review_pending || 'Pending review'}</h2>
            {rows.length === 0 && <p className="mb-6 text-sm text-gray-500">{t.review_none_pending || 'No submitted work waiting for review.'}</p>}
            <div className="mb-8 space-y-3">
                {rows.map((row) => <ReviewRow key={`${row.kind}-${row.id}`} row={row} t={t} />)}
            </div>

            <h2 className="mb-2 text-sm font-medium">{t.review_weakness || 'Weakness'}</h2>
            <p className="mb-2 text-xs text-gray-500">{t.review_weakness_hint || 'Latest scored attempt below the passing score, or below the percent threshold when no passing score is set.'}</p>
            <div className="mb-8">
                <ReportTable rows={weaknesses} empty={t.review_no_weak || 'No weak scored attempts.'} t={t} />
            </div>

            <h2 className="mb-2 text-sm font-medium">{t.review_revision || 'Revision'}</h2>
            <p className="mb-2 text-xs text-gray-500">{t.review_revision_hint || 'Retry the weak item when retakes remain; otherwise review with a teacher.'}</p>
            <ReportTable rows={revisions} empty={t.review_no_revisions || 'No revision recommendations.'} t={t} />
        </AppShell>
    );
}
