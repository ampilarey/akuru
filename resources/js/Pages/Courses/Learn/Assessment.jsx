import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

/**
 * SPEC §17 Pattern 3 covers two shapes: orderings ("arrange words", "arrange
 * steps") and mappings ("match pairs", "sort items into categories"). A
 * question carries a mapping when its answer key is an object rather than a
 * list — the same test the server uses, so the two never disagree.
 */
function isMapping(snapshot) {
    return Array.isArray(snapshot.targets) && snapshot.targets.length > 0;
}

function blankAnswers(snapshots, existing) {
    const next = { ...(existing || {}) };
    (snapshots || []).forEach((snapshot) => {
        if (next[snapshot.question_id]) {
            return;
        }
        if (snapshot.pattern === 'selection') {
            next[snapshot.question_id] = { selected_ids: [] };
        } else if (snapshot.pattern === 'arrange' && isMapping(snapshot)) {
            // §17 Pattern 3 covers mappings as well as orderings. An empty
            // pairing is seeded rather than a guessed one: unlike an ordering,
            // there is no neutral starting arrangement to offer.
            next[snapshot.question_id] = { pairs: {} };
        } else if (snapshot.pattern === 'arrange') {
            next[snapshot.question_id] = { order: (snapshot.options || []).map((item) => item.id) };
        } else {
            next[snapshot.question_id] = { text: '' };
        }
    });
    return next;
}

function formatRemaining(seconds) {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;

    return `${m}:${String(s).padStart(2, '0')}`;
}

export default function Assessment({ assessment, enrollment, attempt }) {
    const t = usePage().props.i18n?.learn || {};
    const submitted = attempt && attempt.status !== 'in_progress';
    const [answers, setAnswers] = useState(() => blankAnswers(attempt?.snapshots || [], attempt?.answers || {}));

    // SPEC §31: the countdown is SEEDED from the server's `seconds_remaining`
    // and only ticked locally. The device clock is never asked what time it is
    // — it is only asked how long a second is, which is the one thing it can be
    // trusted about. On reload the server's figure wins again.
    const [remaining, setRemaining] = useState(attempt?.seconds_remaining ?? null);

    useEffect(() => {
        setRemaining(attempt?.seconds_remaining ?? null);
    }, [attempt?.seconds_remaining]);

    useEffect(() => {
        if (remaining === null || submitted) return undefined;
        if (remaining <= 0) return undefined;
        const id = setInterval(() => setRemaining((r) => (r === null ? null : Math.max(0, r - 1))), 1000);

        return () => clearInterval(id);
    }, [remaining, submitted]);

    const outOfTime = remaining !== null && remaining <= 0;

    const setAnswer = (questionId, value) => {
        setAnswers((current) => ({ ...current, [questionId]: value }));
    };

    // Arrange questions had no control here at all, while `blankAnswers` seeded
    // an `order` from the presented option order — so the player submitted an
    // answer the student was never shown and could not change. Same movement
    // the activity player has used all along.
    const moveItem = (questionId, order, index, delta) => {
        const next = [...order];
        const target = index + delta;
        if (target < 0 || target >= next.length) {
            return;
        }
        [next[index], next[target]] = [next[target], next[index]];
        setAnswer(questionId, { order: next });
    };

    return (
        <AppShell title={assessment.title}>
            {remaining !== null && !submitted && (
                <div
                    role="status"
                    className={`mb-4 rounded-lg border p-3 text-sm ${
                        outOfTime
                            ? 'border-red-300 bg-red-50 text-red-900'
                            : remaining <= 120
                                ? 'border-amber-300 bg-amber-50 text-amber-900'
                                : 'border-gray-200 bg-gray-50 text-gray-700'
                    }`}
                >
                    {outOfTime ? (
                        <>
                            <strong>Time is up.</strong> Answers saved before the deadline have been kept;
                            anything typed after it will not be counted.
                        </>
                    ) : (
                        <>
                            <strong>Time remaining: {formatRemaining(remaining)}</strong>
                            {attempt?.time_limit_minutes ? ` of ${attempt.time_limit_minutes} minutes` : ''}.
                        </>
                    )}
                </div>
            )}
            <p className="mb-4 text-sm text-gray-600">
                {enrollment?.course_id ? (
                    <a className="text-[#7C2D37] hover:underline" href={`/learn/courses/${enrollment.course_id}`}>{t.course || 'Course'}</a>
                ) : (
                    <span>Class assessment</span>
                )}
                {attempt?.status ? ` · ${attempt.status}` : ''}
                {attempt?.score != null ? ` · ${attempt.score}/${attempt.max_score}` : ''}
            </p>
            {/* §19: an attempt held for a teacher, or a mark the teacher chose
                not to publish, reads as a bug unless it is explained. */}
            {attempt?.status === 'submitted' && (
                <p className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    {t.awaiting_marking || 'Submitted. Your teacher will mark this — the result is not final yet.'}
                </p>
            )}
            {attempt?.show_results === false && attempt?.status !== 'submitted' && (
                <p className="mb-4 rounded-lg border bg-white p-3 text-sm text-gray-600">
                    {t.marks_not_published || 'Your teacher has not published marks for this assessment.'}
                </p>
            )}
            {attempt?.feedback && (
                <p className="mb-4 rounded-lg border bg-white p-3 text-sm">Teacher feedback: {attempt.feedback}</p>
            )}
            <div className="space-y-4">
                {(attempt?.snapshots || []).map((snapshot, index) => {
                    const current = answers[snapshot.question_id] || {};
                    return (
                        <section key={snapshot.question_id} className="rounded-lg border bg-white p-4">
                            <h2 className="mb-2 font-medium">{index + 1}. {snapshot.question_text}</h2>
                            {snapshot.pattern === 'selection' && (
                                <ul className="space-y-2">
                                    {(snapshot.options || []).map((option) => (
                                        <li key={option.id}>
                                            <label className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    disabled={submitted}
                                                    checked={(current.selected_ids || []).includes(option.id)}
                                                    onChange={() => {
                                                        const selected = current.selected_ids || [];
                                                        const next = selected.includes(option.id)
                                                            ? selected.filter((id) => id !== option.id)
                                                            : [...selected, option.id];
                                                        setAnswer(snapshot.question_id, { selected_ids: next });
                                                    }}
                                                />
                                                {option.label}
                                            </label>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {/* §17 Pattern 3, mapping mode: "match pairs" and
                                "sort items into categories". One select per
                                left-hand item — reachable on touch, and it says
                                plainly what is being asked. */}
                            {snapshot.pattern === 'arrange' && isMapping(snapshot) && (
                                <ul className="space-y-2">
                                    {(snapshot.options || []).map((option) => (
                                        <li key={option.id} className="flex flex-wrap items-center gap-2 text-sm">
                                            <span className="min-w-40">{option.label}</span>
                                            <select
                                                className="form-input"
                                                disabled={submitted}
                                                value={(current.pairs || {})[option.id] || ''}
                                                onChange={(e) => setAnswer(snapshot.question_id, {
                                                    pairs: { ...(current.pairs || {}), [option.id]: e.target.value },
                                                })}
                                            >
                                                <option value="">{t.choose || '—'}</option>
                                                {snapshot.targets.map((target) => (
                                                    <option key={target.id} value={target.id}>{target.label}</option>
                                                ))}
                                            </select>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {snapshot.pattern === 'arrange' && !isMapping(snapshot) && (
                                <ul className="space-y-2">
                                    {(current.order || (snapshot.options || []).map((o) => o.id)).map((id, position) => {
                                        const option = (snapshot.options || []).find((o) => o.id === id) || { id, label: id };
                                        const order = current.order || (snapshot.options || []).map((o) => o.id);

                                        return (
                                            <li key={id} className="flex items-center justify-between gap-2 rounded-lg border p-2 text-sm">
                                                <span>{position + 1}. {option.label}</span>
                                                {!submitted && (
                                                    <span className="flex gap-2">
                                                        <button type="button" className="btn-secondary" onClick={() => moveItem(snapshot.question_id, order, position, -1)}>
                                                            {t.move_up || 'Up'}
                                                        </button>
                                                        <button type="button" className="btn-secondary" onClick={() => moveItem(snapshot.question_id, order, position, 1)}>
                                                            {t.move_down || 'Down'}
                                                        </button>
                                                    </span>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                            {snapshot.pattern === 'text_input' && (
                                <input
                                    className="form-input"
                                    disabled={submitted}
                                    value={current.text || ''}
                                    onChange={(e) => setAnswer(snapshot.question_id, { text: e.target.value })}
                                />
                            )}
                            {snapshot.pattern === 'teacher_marked' && (
                                <textarea
                                    className="form-input min-h-24"
                                    disabled={submitted}
                                    value={current.text || ''}
                                    onChange={(e) => setAnswer(snapshot.question_id, { text: e.target.value })}
                                />
                            )}
                            {snapshot.correct_answer && (
                                <p className="mt-2 text-sm text-green-700">Correct: {(snapshot.correct_answer || []).join(', ')}</p>
                            )}
                        </section>
                    );
                })}
            </div>
            <div className="mt-4 flex flex-wrap gap-3">
                <button
                    type="button"
                    className="btn-secondary"
                    disabled={submitted}
                    onClick={() => router.post(`/learn/assessments/${assessment.id}/autosave`, { answers }, { preserveScroll: true })}
                >
                    {t.save || 'Save draft'}
                </button>
                <button
                    type="button"
                    className="btn-primary"
                    disabled={submitted}
                    onClick={() => router.post(`/learn/assessments/${assessment.id}/submit`, { answers }, { preserveScroll: true })}
                >
                    {t.submit || 'Submit'}
                </button>
            </div>
        </AppShell>
    );
}
