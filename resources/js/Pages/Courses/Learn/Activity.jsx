import { router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

function initialAnswers(activity, attempt) {
    if (attempt?.answers) {
        return attempt.answers;
    }
    if (activity.pattern === 'selection') {
        return { selected_ids: [] };
    }
    if (activity.pattern === 'text_input') {
        return { text: '' };
    }
    if (activity.pattern === 'arrange' && isMapping(activity)) {
        // No neutral starting pairing exists, unlike an ordering — so an empty
        // one is seeded rather than a guessed one.
        return { pairs: {} };
    }
    if (activity.pattern === 'arrange') {
        return { order: (activity.data.items || []).map((item) => item.id) };
    }
    return { text: '' };
}

/**
 * SPEC §17 Pattern 3 covers orderings ("arrange words") and mappings ("match
 * pairs", "sort items into categories"). The server sends `targets` for the
 * mapping shape; it never sends the answer key to a student, so the presence of
 * the right-hand column is what distinguishes them.
 */
function isMapping(activity) {
    return Array.isArray(activity.data?.targets) && activity.data.targets.length > 0;
}

/**
 * SPEC §36 asks the teacher to "play audio/voice submissions" and "view
 * uploaded files". Neither was possible, because a teacher-marked activity had
 * no way to take a file at all: `submission_kind` was stored and never read,
 * and this page rendered a `<textarea>` whichever kind the author chose.
 *
 * The upload posts straight to the server rather than riding along in
 * `answers`, so the media id is minted where the file is stored and the client
 * never gets to name one of its own.
 */
function Attachments({ activity, attachments, submitted }) {
    // A refusal the student cannot see is worse than one they can act on: the
    // browser walk for this slice uploaded a file the MIME allowlist rejected,
    // and the page answered "Nothing uploaded yet" with no reason given. The
    // guard was right and silent, which is indistinguishable from broken.
    const error = usePage().props.errors?.file;

    const upload = (file) => {
        if (!file) {
            return;
        }
        const body = new FormData();
        body.append('file', file);
        router.post(`/learn/activities/${activity.id}/upload`, body, { preserveScroll: true, forceFormData: true });
    };

    return (
        <section className="mb-4 rounded-lg border bg-white p-4">
            <p className="mb-2 text-sm font-medium">{activity.submission?.label || 'Upload'}</p>
            {!submitted && (
                <input
                    type="file"
                    className="form-input mb-3"
                    accept={activity.submission?.accept || undefined}
                    aria-label={activity.submission?.label || 'Upload a file'}
                    onChange={(e) => upload(e.target.files?.[0])}
                />
            )}
            {error && <p className="mb-3 rounded border border-red-300 bg-red-50 p-2 text-sm text-red-800">{error}</p>}
            {attachments.length === 0 && <p className="text-sm text-gray-500">Nothing uploaded yet.</p>}
            <ul className="space-y-2">
                {attachments.map((file) => (
                    <li key={file.id} className="rounded border p-2 text-sm">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <a className="text-[#7C2D37] hover:underline" href={`/learn/media/${file.id}`}>
                                {file.original_name || `File ${file.id}`}
                            </a>
                            {!submitted && (
                                <button
                                    type="button"
                                    className="btn-secondary"
                                    onClick={() => router.delete(
                                        `/learn/activities/${activity.id}/attachments/${file.id}`,
                                        { preserveScroll: true },
                                    )}
                                >
                                    Remove
                                </button>
                            )}
                        </div>
                        {(file.mime || '').startsWith('audio/') && (
                            <audio className="mt-2 w-full" controls preload="none" src={`/learn/media/${file.id}`} />
                        )}
                        {(file.mime || '').startsWith('image/') && (
                            <img className="mt-2 max-h-64 rounded" src={`/learn/media/${file.id}`} alt={file.original_name || 'Upload'} />
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function Activity({ activity, enrollment, attempt }) {
    const t = usePage().props.i18n?.learn || {};
    const [answers, setAnswers] = useState(() => initialAnswers(activity, attempt));
    const submitted = attempt && attempt.status !== 'in_progress';
    const items = useMemo(() => {
        const byId = Object.fromEntries((activity.data.items || []).map((item) => [item.id, item]));
        return (answers.order || []).map((id) => byId[id]).filter(Boolean);
    }, [activity.data.items, answers.order]);

    const move = (index, direction) => {
        const next = [...(answers.order || [])];
        const swap = index + direction;
        if (swap < 0 || swap >= next.length) {
            return;
        }
        [next[index], next[swap]] = [next[swap], next[index]];
        setAnswers({ ...answers, order: next });
    };

    const toggleSelected = (id) => {
        const current = answers.selected_ids || [];
        if (activity.data.multiple) {
            setAnswers({
                ...answers,
                selected_ids: current.includes(id) ? current.filter((row) => row !== id) : [...current, id],
            });
            return;
        }
        setAnswers({ ...answers, selected_ids: [id] });
    };

    return (
        <AppShell title={activity.title}>
            <p className="mb-4 text-sm text-gray-600">
                <a className="text-[#7C2D37] hover:underline" href={`/learn/courses/${enrollment.course_id}`}>{t.course || 'Course'}</a>
                {' · '}
                {activity.pattern}
                {attempt?.status ? ` · ${attempt.status}` : ''}
                {attempt?.score != null ? ` · ${attempt.score}/${attempt.max_score}` : ''}
            </p>
            {activity.quran && (
                <div className="mb-4 rounded-lg border bg-white p-4">
                    <p className="mb-2 text-sm text-gray-600">
                        {activity.quran.surah.english_name} {activity.quran.ayah_start}–{activity.quran.ayah_end}
                    </p>
                    <div className="space-y-2 text-lg" dir="rtl">
                        {(activity.quran.ayahs || []).map((ayah) => (
                            <p key={ayah.id}>{ayah.text_uthmani}</p>
                        ))}
                    </div>
                </div>
            )}
            <p className="mb-4">{activity.data.prompt}</p>
            {activity.pattern === 'selection' && (
                <ul className="mb-4 space-y-2">
                    {(activity.data.options || []).map((option) => (
                        <li key={option.id}>
                            <label className="flex items-center gap-2 rounded-lg border bg-white p-3 text-sm">
                                <input
                                    type={activity.data.multiple ? 'checkbox' : 'radio'}
                                    name="selection"
                                    checked={(answers.selected_ids || []).includes(option.id)}
                                    onChange={() => toggleSelected(option.id)}
                                    disabled={submitted}
                                />
                                {option.label}
                            </label>
                        </li>
                    ))}
                </ul>
            )}
            {activity.pattern === 'text_input' && (
                <input
                    className="form-input mb-4"
                    value={answers.text || ''}
                    onChange={(e) => setAnswers({ ...answers, text: e.target.value })}
                    disabled={submitted}
                />
            )}
            {activity.pattern === 'arrange' && isMapping(activity) && (
                <ul className="mb-4 space-y-2">
                    {(activity.data.items || []).map((item) => (
                        <li key={item.id} className="flex flex-wrap items-center gap-2 rounded-lg border bg-white p-3 text-sm">
                            <span className="min-w-40">{item.label}</span>
                            <select
                                className="form-input"
                                disabled={submitted}
                                value={(answers.pairs || {})[item.id] || ''}
                                onChange={(e) => setAnswers({
                                    ...answers,
                                    pairs: { ...(answers.pairs || {}), [item.id]: e.target.value },
                                })}
                            >
                                <option value="">{t.choose || '—'}</option>
                                {activity.data.targets.map((target) => (
                                    <option key={target.id} value={target.id}>{target.label}</option>
                                ))}
                            </select>
                        </li>
                    ))}
                </ul>
            )}
            {activity.pattern === 'arrange' && !isMapping(activity) && (
                <ul className="mb-4 space-y-2">
                    {items.map((item, index) => (
                        <li key={item.id} className="flex items-center justify-between gap-2 rounded-lg border bg-white p-3 text-sm">
                            <span>{item.label}</span>
                            {!submitted && (
                                <span className="flex gap-2">
                                    <button type="button" className="btn-secondary" onClick={() => move(index, -1)}>Up</button>
                                    <button type="button" className="btn-secondary" onClick={() => move(index, 1)}>Down</button>
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {activity.pattern === 'teacher_marked' && (
                <>
                    {(activity.submission?.accepts_text ?? true) && (
                        <textarea
                            className="form-input mb-4 min-h-32"
                            value={answers.text || ''}
                            onChange={(e) => setAnswers({ ...answers, text: e.target.value })}
                            disabled={submitted}
                        />
                    )}
                    {activity.submission?.accepts_uploads && (
                        /* Read from the attempt, never from local `answers` state.
                           The browser walk for this slice uploaded a file, the server
                           stored it, and the list still read "Nothing uploaded yet":
                           `useState` seeds once on mount, and Inertia's redirect back
                           re-renders the same component instance rather than remounting
                           it. Attachments are server-owned anyway — `reconcile()` lets a
                           client drop one and never add one — so a local copy of them
                           was only ever a way to be wrong. */
                        <Attachments
                            activity={activity}
                            attachments={attempt?.answers?.attachments || []}
                            submitted={submitted}
                        />
                    )}
                </>
            )}
            {activity.data.correct_ids && (
                <p className="mb-3 text-sm text-green-700">Correct: {(activity.data.correct_ids || []).join(', ')}</p>
            )}
            {activity.data.acceptable && (
                <p className="mb-3 text-sm text-green-700">Accepted: {(activity.data.acceptable || []).join(', ')}</p>
            )}
            {activity.data.correct_order && (
                <p className="mb-3 text-sm text-green-700">Order: {(activity.data.correct_order || []).join(', ')}</p>
            )}
            {attempt?.feedback && (
                <p className="mb-3 rounded-lg border bg-white p-3 text-sm">Teacher feedback: {attempt.feedback}</p>
            )}
            <div className="flex flex-wrap gap-3">
                <button
                    type="button"
                    className="btn-secondary"
                    disabled={submitted}
                    onClick={() => router.post(`/learn/activities/${activity.id}/autosave`, { answers }, { preserveScroll: true })}
                >
                    {t.save || 'Save draft'}
                </button>
                <button
                    type="button"
                    className="btn-primary"
                    disabled={submitted}
                    onClick={() => router.post(`/learn/activities/${activity.id}/submit`, { answers }, { preserveScroll: true })}
                >
                    {t.submit || 'Submit'}
                </button>
            </div>
        </AppShell>
    );
}
