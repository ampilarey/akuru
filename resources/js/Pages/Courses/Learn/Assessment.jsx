import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

function blankAnswers(snapshots, existing) {
    const next = { ...(existing || {}) };
    (snapshots || []).forEach((snapshot) => {
        if (next[snapshot.question_id]) {
            return;
        }
        if (snapshot.pattern === 'selection') {
            next[snapshot.question_id] = { selected_ids: [] };
        } else if (snapshot.pattern === 'arrange') {
            next[snapshot.question_id] = { order: (snapshot.options || []).map((item) => item.id) };
        } else {
            next[snapshot.question_id] = { text: '' };
        }
    });
    return next;
}

export default function Assessment({ assessment, enrollment, attempt }) {
    const t = usePage().props.i18n?.learn || {};
    const submitted = attempt && attempt.status !== 'in_progress';
    const [answers, setAnswers] = useState(() => blankAnswers(attempt?.snapshots || [], attempt?.answers || {}));

    const setAnswer = (questionId, value) => {
        setAnswers((current) => ({ ...current, [questionId]: value }));
    };

    return (
        <AppShell title={assessment.title}>
            <p className="mb-4 text-sm text-gray-600">
                <a className="text-[#7C2D37] hover:underline" href={`/learn/courses/${enrollment.course_id}`}>{t.course || 'Course'}</a>
                {attempt?.status ? ` · ${attempt.status}` : ''}
                {attempt?.score != null ? ` · ${attempt.score}/${attempt.max_score}` : ''}
            </p>
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
