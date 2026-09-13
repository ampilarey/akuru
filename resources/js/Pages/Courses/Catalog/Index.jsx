import { router, useForm } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

/**
 * SPEC §35 "Dean / Supervisor Dashboard" names three outcomes — approve,
 * reject, request changes — and §34 "Course Creator Dashboard" names the other
 * half of the same thing: "View supervisor comments".
 *
 * The buttons here used to be "Publish" and "Return draft". **Return draft
 * carried no reason at all**: no comment, no reviewer, no date, and no
 * distinction between a rejection and a request for changes. A creator whose
 * course was bounced back was told nothing, and the supervisor's actual review
 * — the only part of the exchange with any content in it — was discarded the
 * instant the button was pressed.
 */
function ReviewDecision({ row, decisions, canPublish }) {
    const [decision, setDecision] = useState('changes_requested');
    const [comment, setComment] = useState('');
    const chosen = decisions.find((option) => option.value === decision);

    // Approving is the one decision that says nothing is wrong, so it is the
    // one that may be silent.
    const blocked = (chosen?.requires_comment ?? true) && comment.trim() === '';

    return (
        <div className="space-y-2">
            <select className="form-input" value={decision} onChange={(e) => setDecision(e.target.value)} aria-label="Review decision">
                {decisions
                    .filter((option) => option.value !== 'approved' || canPublish)
                    .map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
            </select>
            <input
                className="form-input"
                placeholder={chosen?.requires_comment ? 'Why? (required)' : 'Comment (optional)'}
                value={comment}
                onChange={(e) => setComment(e.target.value)}
                aria-label="Review comment"
            />
            <button
                type="button"
                className="btn-primary"
                disabled={blocked}
                onClick={() => router.post(
                    `/catalog/courses/${row.id}/review-decision`,
                    { decision, comment },
                    { preserveScroll: true },
                )}
            >
                Record review
            </button>
        </div>
    );
}

export default function Index({ rows, subjects, canPublish, unlockModes = [], decisions = [] }) {
    const form = useForm({
        title: '',
        title_dv: '',
        title_ar: '',
        subject_id: subjects[0]?.id || '',
        language: 'en',
        // SPEC §26. Sequential is the default the resolver applies, so the
        // form opens on the behaviour a course would have had anyway.
        unlock_mode: 'sequential',
    });

    return (
        <AppShell title="Course catalog">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/courses/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/catalog/courses', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-5"
            >
                <input className="form-input" placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <select className="form-input" value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                    {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name_en}</option>)}
                </select>
                <select className="form-input" value={form.data.language} onChange={(e) => form.setData('language', e.target.value)}>
                    <option value="en">EN</option>
                    <option value="dv">DV</option>
                    <option value="ar">AR</option>
                    <option value="mixed">Mixed</option>
                </select>
                {/* SPEC §26 "Admin must be able to configure unlock rules."
                    Unlock was hardcoded sequential for every course, so "All
                    lessons open" — the first rule §26 lists — could not be
                    chosen at all. */}
                <select className="form-input" value={form.data.unlock_mode} onChange={(e) => form.setData('unlock_mode', e.target.value)}>
                    {unlockModes.map((mode) => <option key={mode.value} value={mode.value}>{mode.label}</option>)}
                </select>
                <button type="submit" className="btn-primary" disabled={form.processing}>Save draft</button>
                {form.errors.title && <span className="text-xs text-red-600">{form.errors.title}</span>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Subject</th>
                            <th className="px-3 py-2">Workflow</th>
                            <th className="px-3 py-2">Unlock</th>
                            <th className="px-3 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No engine courses yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <Fragment key={row.id}>
                            <tr className="border-t">
                                <td className="px-3 py-2">
                                    <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${row.id}/outline`}>{row.title}</a>
                                    <a className="ms-3 text-xs text-[#7C2D37] hover:underline" href={`/catalog/courses/${row.id}/activities`}>Activities</a>
                                </td>
                                <td className="px-3 py-2">{row.subject_name || '—'}</td>
                                <td className="px-3 py-2">{row.workflow_status}</td>
                                <td className="px-3 py-2">
                                    {row.workflow_status === 'draft' ? (
                                        <select
                                            className="form-input"
                                            value={row.unlock_mode}
                                            onChange={(e) => router.post(`/catalog/courses/${row.id}`, {
                                                _method: 'put',
                                                title: row.title,
                                                title_dv: row.title_dv || '',
                                                title_ar: row.title_ar || '',
                                                subject_id: row.subject_id || '',
                                                language: row.language || 'en',
                                                unlock_mode: e.target.value,
                                            }, { preserveScroll: true })}
                                        >
                                            {unlockModes.map((mode) => <option key={mode.value} value={mode.value}>{mode.label}</option>)}
                                        </select>
                                    ) : (
                                        // Only draft courses are editable
                                        // (SaveEngineCourseAction refuses the
                                        // rest), so show the mode rather than a
                                        // control that would always fail.
                                        <span>{(unlockModes.find((m) => m.value === row.unlock_mode) || {}).label || row.unlock_mode}</span>
                                    )}
                                </td>
                                <td className="px-3 py-2">
                                    {row.workflow_status === 'draft' && (
                                        <button type="button" className="btn-secondary" onClick={() => router.post(`/catalog/courses/${row.id}/transition`, { workflow_status: 'in_review' })}>Submit review</button>
                                    )}
                                    {/* §8.4 gives reviewing to Dean/Supervisor; §8.3's
                                        Course Creator does not review at all, and
                                        `courses.publish` is what separates them. The
                                        server checks the same thing — this only avoids
                                        offering a control that would 403. */}
                                    {row.workflow_status === 'in_review' && canPublish && (
                                        <ReviewDecision row={row} decisions={decisions} canPublish={canPublish} />
                                    )}
                                    {row.workflow_status === 'in_review' && !canPublish && (
                                        <span className="text-xs text-gray-500">Waiting for review</span>
                                    )}
                                    {row.workflow_status === 'published' && (
                                        <button type="button" className="btn-secondary" onClick={() => router.post(`/catalog/courses/${row.id}/transition`, { workflow_status: 'archived' })}>Archive</button>
                                    )}
                                </td>
                            </tr>
                            {(row.review_decisions || []).length > 0 && (
                                /* §34 "View supervisor comments". Every round, newest
                                   first — a creator who has been through two of them
                                   needs both, not only the latest verdict. */
                                <tr className="bg-[#F9F4EE]">
                                    <td className="px-3 pb-3 text-sm" colSpan={5}>
                                        <ul className="space-y-1">
                                            {row.review_decisions.map((decision) => (
                                                <li key={decision.id}>
                                                    <span className="font-medium">{decision.decision_label}</span>
                                                    {decision.created_at ? ` · ${decision.created_at.slice(0, 10)}` : ''}
                                                    {decision.comment ? ` — ${decision.comment}` : ''}
                                                </li>
                                            ))}
                                        </ul>
                                    </td>
                                </tr>
                            )}
                            </Fragment>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
