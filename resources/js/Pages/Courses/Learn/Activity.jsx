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
    if (activity.pattern === 'arrange') {
        return { order: (activity.data.items || []).map((item) => item.id) };
    }
    return { text: '' };
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
            {activity.pattern === 'arrange' && (
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
                <textarea
                    className="form-input mb-4 min-h-32"
                    value={answers.text || ''}
                    onChange={(e) => setAnswers({ ...answers, text: e.target.value })}
                    disabled={submitted}
                />
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
