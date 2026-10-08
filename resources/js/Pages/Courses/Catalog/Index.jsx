import { router, useForm, usePage } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';
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
function ReviewDecision({ row, decisions, canPublish, t, actOn }) {
    const [decision, setDecision] = useState('changes_requested');
    const [comment, setComment] = useState('');
    const chosen = decisions.find((option) => option.value === decision);

    // Approving is the one decision that says nothing is wrong, so it is the
    // one that may be silent.
    const blocked = (chosen?.requires_comment ?? true) && comment.trim() === '';

    return (
        <div className="space-y-2">
            <select className="form-input" value={decision} onChange={(e) => setDecision(e.target.value)} aria-label={t.catalog_review_decision || 'Review decision'}>
                {decisions
                    .filter((option) => option.value !== 'approved' || canPublish)
                    .map((option) => <option key={option.value} value={option.value}>{t[`decision_${option.value}`] || option.label}</option>)}
            </select>
            <input
                className="form-input"
                placeholder={chosen?.requires_comment ? (t.catalog_review_why || 'Why? (required)') : (t.catalog_review_comment || 'Comment (optional)')}
                value={comment}
                onChange={(e) => setComment(e.target.value)}
                aria-label={t.catalog_review_comment_label || 'Review comment'}
            />
            <button
                type="button"
                className="btn-primary"
                disabled={blocked}
                onClick={() => actOn(`course:${row.id}`, () => router.post(
                    `/catalog/courses/${row.id}/review-decision`,
                    { decision, comment },
                    { preserveScroll: true },
                ))}
            >
                {t.catalog_record_review || 'Record review'}
            </button>
        </div>
    );
}

/**
 * Moodle parity slice M1: copy the whole course as a new draft. The copy's
 * outline opens next, so the teacher carries on in the copy.
 */
function CopyCourse({ row, t, actOn }) {
    const [open, setOpen] = useState(false);
    const [title, setTitle] = useState(`${row.title} (copy)`);
    const [busy, setBusy] = useState(false);

    if (!open) {
        return (
            <button type="button" className="btn-secondary" data-testid={`copy-course-${row.id}`} onClick={() => setOpen(true)}>
                {t.copy || 'Copy'}
            </button>
        );
    }

    return (
        <form
            className="mt-2 space-y-2"
            data-testid={`copy-course-form-${row.id}`}
            onSubmit={(e) => {
                e.preventDefault();
                setBusy(true);
                actOn(`course:${row.id}`, () => router.post(`/catalog/courses/${row.id}/copy`, { title }, { preserveScroll: 'errors', onFinish: () => setBusy(false) }));
            }}
        >
            <label className="block text-xs text-gray-600" htmlFor={`copy-title-${row.id}`}>{t.copy_title || 'Title of the copy'}</label>
            <input id={`copy-title-${row.id}`} className="form-input" value={title} onChange={(e) => setTitle(e.target.value)} maxLength={255} />
            <p className="text-xs text-gray-500">{t.copy_hint}</p>
            <div className="flex gap-2">
                <button type="submit" className="btn-primary" disabled={busy || title.trim() === ''}>{t.copy_make || 'Make the copy'}</button>
                <button type="button" className="btn-secondary" onClick={() => setOpen(false)}>{t.copy_cancel || 'Cancel'}</button>
            </div>
        </form>
    );
}

export default function Index({ rows, subjects, canPublish, unlockModes = [], decisions = [], t = {} }) {
    // Every string below is a key in the `teach` book (STATUS §5ok), so the
    // catalog reads in Dhivehi and Arabic; the English is the fallback.
    const locale = usePage().props.locale || 'en';
    const subjectName = (subject) => subject[`name_${locale}`] || subject.name_en;
    const rowSubject = (row) => {
        const subject = subjects.find((item) => item.id === row.subject_id);
        return subject ? subjectName(subject) : (row.subject_name || '—');
    };
    const unlockLabel = (mode) => t[`unlock_${mode.value}`] || mode.label;
    const workflowLabel = (status) => t[`workflow_${status}`] || status;
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
    // A row's buttons post with `router`: Submit review, Archive, the review
    // decision, the unlock rule, Copy. Their refusals — a move the workflow
    // does not allow, a decision with no reason, a course no longer a draft —
    // were shown nowhere (slice CT6b-2b). Each now says it under its row.
    const refusals = useRowRefusals(form);

    return (
        <AppShell title={t.catalog_title || 'Course catalog'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/courses/export">{t.catalog_export || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/catalog/courses', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-5"
            >
                <input className="form-input" placeholder={t.catalog_new_title || 'Title'} aria-label={t.catalog_new_title || 'Title'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <select className="form-input" aria-label={t.catalog_col_subject || 'Subject'} value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                    {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subjectName(subject)}</option>)}
                </select>
                <select className="form-input" aria-label={t.catalog_language || 'Language'} value={form.data.language} onChange={(e) => form.setData('language', e.target.value)}>
                    <option value="en">{t.outline_lang_en || 'English'}</option>
                    <option value="dv">{t.outline_lang_dv || 'Dhivehi'}</option>
                    <option value="ar">{t.outline_lang_ar || 'Arabic'}</option>
                    <option value="mixed">{t.catalog_language_mixed || 'Mixed'}</option>
                </select>
                {/* SPEC §26 "Admin must be able to configure unlock rules."
                    Unlock was hardcoded sequential for every course, so "All
                    lessons open" — the first rule §26 lists — could not be
                    chosen at all. */}
                <select className="form-input" aria-label={t.catalog_unlock_rule || 'Unlock rule'} value={form.data.unlock_mode} onChange={(e) => form.setData('unlock_mode', e.target.value)}>
                    {unlockModes.map((mode) => <option key={mode.value} value={mode.value}>{unlockLabel(mode)}</option>)}
                </select>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.catalog_save_draft || 'Save draft'}</button>
                {form.errors.title && <span className="text-xs text-red-600">{form.errors.title}</span>}
                <FormErrors errors={form.errors} except={['title']} className="md:col-span-5" />
            </form>
            <FormErrors errors={refusals.unplaced} className="mb-4 rounded border border-red-200 bg-red-50 py-2 pe-3" />
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.catalog_col_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.catalog_col_subject || 'Subject'}</th>
                            <th className="px-3 py-2">{t.catalog_col_workflow || 'Workflow'}</th>
                            <th className="px-3 py-2">{t.catalog_col_unlock || 'Unlock'}</th>
                            <th className="px-3 py-2">{t.catalog_col_actions || 'Actions'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.catalog_none || 'No engine courses yet.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <Fragment key={row.id}>
                            <tr className="border-t">
                                <td className="px-3 py-2">
                                    <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${row.id}/outline`}>{row.title}</a>
                                    <a className="ms-3 text-xs text-[#7C2D37] hover:underline" href={`/catalog/courses/${row.id}/activities`}>{t.catalog_activities || 'Activities'}</a>
                                    <a className="ms-3 text-xs text-[#7C2D37] hover:underline" href={`/catalog/courses/${row.id}/rubrics`}>{t.rubrics || 'Rubrics'}</a>
                                    <a className="ms-3 text-xs text-[#7C2D37] hover:underline" href={`/learn/courses/${row.id}/forum`}>{t.forum || 'Forum'}</a>
                                </td>
                                <td className="px-3 py-2">{rowSubject(row)}</td>
                                <td className="px-3 py-2">{workflowLabel(row.workflow_status)}</td>
                                <td className="px-3 py-2">
                                    {row.workflow_status === 'draft' ? (
                                        <select
                                            className="form-input"
                                            aria-label={(t.outline_unlock_aria || 'Unlock rule for :title').replace(':title', row.title)}
                                            value={row.unlock_mode}
                                            onChange={(e) => {
                                                const value = e.target.value;
                                                refusals.actOn(`course:${row.id}`, () => router.post(`/catalog/courses/${row.id}`, {
                                                    _method: 'put',
                                                    title: row.title,
                                                    title_dv: row.title_dv || '',
                                                    title_ar: row.title_ar || '',
                                                    subject_id: row.subject_id || '',
                                                    language: row.language || 'en',
                                                    unlock_mode: value,
                                                }, { preserveScroll: true }));
                                            }}
                                        >
                                            {unlockModes.map((mode) => <option key={mode.value} value={mode.value}>{unlockLabel(mode)}</option>)}
                                        </select>
                                    ) : (
                                        // Only draft courses are editable
                                        // (SaveEngineCourseAction refuses the
                                        // rest), so show the mode rather than a
                                        // control that would always fail.
                                        <span>{unlockModes.some((m) => m.value === row.unlock_mode) ? unlockLabel(unlockModes.find((m) => m.value === row.unlock_mode)) : row.unlock_mode}</span>
                                    )}
                                </td>
                                <td className="px-3 py-2">
                                    {row.workflow_status === 'draft' && (
                                        <button type="button" className="btn-secondary" onClick={() => refusals.actOn(`course:${row.id}`, () => router.post(`/catalog/courses/${row.id}/transition`, { workflow_status: 'in_review' }, { preserveScroll: true }))}>{t.catalog_submit_review || 'Submit review'}</button>
                                    )}
                                    {/* §8.4 gives reviewing to Dean/Supervisor; §8.3's
                                        Course Creator does not review at all, and
                                        `courses.publish` is what separates them. The
                                        server checks the same thing — this only avoids
                                        offering a control that would 403. */}
                                    {row.workflow_status === 'in_review' && canPublish && (
                                        <ReviewDecision row={row} decisions={decisions} canPublish={canPublish} t={t} actOn={refusals.actOn} />
                                    )}
                                    {row.workflow_status === 'in_review' && !canPublish && (
                                        <span className="text-xs text-gray-500">{t.catalog_waiting_review || 'Waiting for review'}</span>
                                    )}
                                    {row.workflow_status === 'published' && (
                                        <button type="button" className="btn-secondary" onClick={() => refusals.actOn(`course:${row.id}`, () => router.post(`/catalog/courses/${row.id}/transition`, { workflow_status: 'archived' }, { preserveScroll: true }))}>{t.catalog_archive || 'Archive'}</button>
                                    )}
                                    <div className="mt-2"><CopyCourse row={row} t={t} actOn={refusals.actOn} /></div>
                                    <FormErrors errors={refusals.errorsFor(`course:${row.id}`)} className="mt-2" />
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
                                                    <span className="font-medium">{t[`decision_${decision.decision}`] || decision.decision_label}</span>
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
