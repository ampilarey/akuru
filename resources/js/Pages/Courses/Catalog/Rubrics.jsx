import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

/**
 * Moodle parity slice M2 (STATUS §5oi): a course's marking rubrics. Each has
 * criteria with scored levels, and says which of the course's activities and
 * assessments it marks. The marker then picks a level per criterion on the
 * review screen instead of typing a score.
 */
const key = () => Math.random().toString(36).slice(2, 10);

function blankRubric(t) {
    return {
        title: '',
        description: '',
        criteria: [{
            id: key(),
            title: t.rubric_default_criterion || 'Content',
            levels: [
                { id: key(), label: t.rubric_default_low || 'Not yet', points: 0 },
                { id: key(), label: t.rubric_default_mid || 'Good', points: 2 },
                { id: key(), label: t.rubric_default_high || 'Excellent', points: 4 },
            ],
        }],
        activity_ids: [],
        assessment_ids: [],
    };
}

function toggle(list, id) {
    return list.includes(id) ? list.filter((x) => x !== id) : [...list, id];
}

function RubricForm({ course, rubric, activities, assessments, t, onDone }) {
    const form = useForm(rubric
        ? {
            title: rubric.title,
            description: rubric.description || '',
            criteria: rubric.criteria,
            activity_ids: rubric.activity_ids,
            assessment_ids: rubric.assessment_ids,
        }
        : blankRubric(t));
    const criteria = form.data.criteria;
    const setCriteria = (next) => form.setData('criteria', next);
    const setCriterion = (i, patch) => setCriteria(criteria.map((c, j) => (j === i ? { ...c, ...patch } : c)));
    const setLevel = (i, l, patch) => setCriterion(i, { levels: criteria[i].levels.map((level, m) => (m === l ? { ...level, ...patch } : level)) });
    const best = criteria.reduce((sum, c) => sum + Math.max(0, ...c.levels.map((l) => Number(l.points) || 0)), 0);

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onDone() };
        if (rubric) {
            form.put(`/catalog/courses/${course.id}/rubrics/${rubric.id}`, options);
        } else {
            form.post(`/catalog/courses/${course.id}/rubrics`, options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4 rounded-lg border bg-white p-4" data-testid="rubric-form">
            <div className="grid gap-3 md:grid-cols-2">
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.rubric_title || 'Title'}</span>
                    <input className="form-input w-full" name="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} maxLength={255} required />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.rubric_description || 'Description (optional)'}</span>
                    <input className="form-input w-full" name="description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} maxLength={2000} />
                </label>
            </div>

            {criteria.map((criterion, i) => (
                <fieldset key={criterion.id} className="rounded border border-[#E6D9C5] p-3" data-testid="rubric-criterion">
                    <div className="mb-2 flex flex-wrap items-end gap-2">
                        <label className="block flex-1 text-sm">
                            <span className="mb-1 block text-gray-600">{t.rubric_criterion || 'Criterion'} {i + 1}</span>
                            <input className="form-input w-full" value={criterion.title} onChange={(e) => setCriterion(i, { title: e.target.value })} maxLength={255} />
                        </label>
                        {criteria.length > 1 && (
                            <button type="button" className="btn-secondary" onClick={() => setCriteria(criteria.filter((_, j) => j !== i))}>{t.rubric_remove || 'Remove'}</button>
                        )}
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        {criterion.levels.map((level, l) => (
                            <div key={level.id} className="rounded bg-[#F9F4EE] p-2">
                                <input className="form-input mb-1 w-full" aria-label={`${t.rubric_level || 'Level'} ${l + 1}`} value={level.label} onChange={(e) => setLevel(i, l, { label: e.target.value })} maxLength={255} />
                                <div className="flex items-center gap-2">
                                    <input className="form-input w-20" type="number" min="0" max="100" aria-label={t.rubric_points || 'Points'} value={level.points} onChange={(e) => setLevel(i, l, { points: e.target.value })} />
                                    <span className="text-xs text-gray-500">{t.rubric_points || 'Points'}</span>
                                    {criterion.levels.length > 2 && (
                                        <button type="button" className="ms-auto text-xs text-red-700 hover:underline" onClick={() => setCriterion(i, { levels: criterion.levels.filter((_, m) => m !== l) })}>{t.rubric_remove || 'Remove'}</button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                    {criterion.levels.length < 8 && (
                        <button type="button" className="mt-2 text-sm text-[#7C2D37] hover:underline" onClick={() => setCriterion(i, { levels: [...criterion.levels, { id: key(), label: '', points: 0 }] })}>
                            + {t.rubric_add_level || 'Add a level'}
                        </button>
                    )}
                </fieldset>
            ))}
            {criteria.length < 20 && (
                <button
                    type="button"
                    className="btn-secondary"
                    onClick={() => setCriteria([...criteria, { id: key(), title: '', levels: [{ id: key(), label: '', points: 0 }, { id: key(), label: '', points: 1 }] }])}
                >
                    + {t.rubric_add_criterion || 'Add a criterion'}
                </button>
            )}
            <p className="text-sm text-gray-600">{(t.rubric_max || 'Best possible: :points points').replace(':points', best)}</p>

            <div className="grid gap-4 md:grid-cols-2">
                {[
                    ['activity_ids', t.rubric_activities || 'Activities', activities],
                    ['assessment_ids', t.rubric_assessments || 'Assessments', assessments],
                ].map(([field, label, items]) => (
                    <fieldset key={field} className="rounded border p-3">
                        <legend className="px-1 text-sm font-medium">{t.rubric_use_for || 'It marks'}: {label}</legend>
                        {items.length === 0 && <p className="text-sm text-gray-500">—</p>}
                        {items.map((item) => (
                            <label key={item.id} className="flex items-center gap-2 py-1 text-sm">
                                <input type="checkbox" checked={form.data[field].includes(item.id)} onChange={() => form.setData(field, toggle(form.data[field], item.id))} />
                                <span>{item.title}</span>
                                {item.teacher_marked && <span className="rounded bg-[#F3EBE0] px-1 text-xs text-gray-600">{t.rubric_teacher_marked || 'teacher-marked'}</span>}
                            </label>
                        ))}
                    </fieldset>
                ))}
            </div>

            <FormErrors errors={form.errors} />
            <div className="flex gap-2">
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.rubric_save || 'Save rubric'}</button>
                <button type="button" className="btn-secondary" onClick={onDone}>{t.rubric_cancel || 'Cancel'}</button>
            </div>
        </form>
    );
}

export default function Rubrics({ course, rubrics = [], activities = [], assessments = [], t = {} }) {
    const [editing, setEditing] = useState(null);
    const titleOf = (items, ids) => items.filter((item) => ids.includes(item.id)).map((item) => item.title);

    return (
        <AppShell title={`${t.rubrics_title || 'Marking rubrics'} · ${course.title}`}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <a className="text-sm text-[#7C2D37] hover:underline" href="/catalog/courses">← {t.rubric_back || 'Back to the catalog'}</a>
                {editing === null && (
                    <button type="button" className="btn-primary" data-testid="rubric-new" onClick={() => setEditing('new')}>{t.rubric_new || 'New rubric'}</button>
                )}
            </div>
            <p className="mb-4 text-sm text-gray-600">{t.rubrics_intro}</p>

            {editing === 'new' && (
                <div className="mb-6">
                    <RubricForm course={course} activities={activities} assessments={assessments} t={t} onDone={() => setEditing(null)} />
                </div>
            )}

            {rubrics.length === 0 && editing === null && <p className="text-sm text-gray-500">{t.rubric_none || 'No rubrics yet.'}</p>}
            <div className="space-y-4">
                {rubrics.map((rubric) => (editing === rubric.id ? (
                    <RubricForm key={rubric.id} course={course} rubric={rubric} activities={activities} assessments={assessments} t={t} onDone={() => setEditing(null)} />
                ) : (
                    <article key={rubric.id} className="rounded-lg border bg-white p-4" data-testid="rubric-card">
                        <div className="mb-2 flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h2 className="font-medium">{rubric.title}</h2>
                                {rubric.description && <p className="text-sm text-gray-600">{rubric.description}</p>}
                                <p className="text-xs text-gray-500">
                                    {(t.rubric_max || 'Best possible: :points points').replace(':points', rubric.max_points)}
                                    {' · '}
                                    {(t.rubric_used_by || 'Marks :count item(s)').replace(':count', rubric.activity_ids.length + rubric.assessment_ids.length)}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <button type="button" className="btn-secondary" onClick={() => setEditing(rubric.id)}>{t.rubric_edit || 'Edit'}</button>
                                <button
                                    type="button"
                                    className="btn-secondary"
                                    onClick={() => {
                                        if (window.confirm(t.rubric_delete_confirm || 'Delete this rubric? The items it marks go back to a typed score. Marks already given keep their rubric.')) {
                                            router.delete(`/catalog/courses/${course.id}/rubrics/${rubric.id}`, { preserveScroll: true });
                                        }
                                    }}
                                >
                                    {t.rubric_delete || 'Delete'}
                                </button>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <tbody>
                                    {rubric.criteria.map((criterion) => (
                                        <tr key={criterion.id} className="border-t align-top">
                                            <th className="px-2 py-2 text-start font-medium">{criterion.title}</th>
                                            {criterion.levels.map((level) => (
                                                <td key={level.id} className="px-2 py-2">
                                                    <div>{level.label}</div>
                                                    <div className="text-xs text-gray-500">{level.points}</div>
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {[...titleOf(activities, rubric.activity_ids), ...titleOf(assessments, rubric.assessment_ids)].length > 0 && (
                            <p className="mt-2 text-xs text-gray-600">
                                {t.rubric_use_for || 'It marks'}: {[...titleOf(activities, rubric.activity_ids), ...titleOf(assessments, rubric.assessment_ids)].join(', ')}
                            </p>
                        )}
                    </article>
                )))}
            </div>
        </AppShell>
    );
}
