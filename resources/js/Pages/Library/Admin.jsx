import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import BodyEditor from '../../Components/BodyEditor';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';
import { IdentityChecks } from '../../Components/IdentityCard';
import { DeliveryChoice, TeacherAuthors, defaultDelivery } from '../../Components/LibraryAuthoring';
import ReviewStateChip from '../../Components/ReviewStateChip';

// The office's words are the `admin` book's (`t`); the words it shares with
// the writer's portal — a type, an access, a status, a field — are the
// `common` book's (`common`), as the shell sends them (slice LT3).
const fill = (text, values) => Object.entries(values).reduce((out, [key, value]) => out.split(`:${key}`).join(String(value ?? '')), String(text));
const money = (common, amount) => fill(common.library_money || 'MVR :amount', { amount });

// The editor's toolbar, from the common tranche (EN/DV/AR).
const editorLabels = (common) => ({
    toolbar: common.library_editor_toolbar,
    bold: common.library_editor_bold,
    italic: common.library_editor_italic,
    heading: common.library_editor_heading,
    subheading: common.library_editor_subheading,
    bullets: common.library_editor_bullets,
    numbers: common.library_editor_numbers,
    quote: common.library_editor_quote,
    link: common.library_editor_link,
    link_prompt: common.library_editor_link_prompt,
    page_break: common.library_editor_page_break,
    source: common.library_editor_source,
});

function ApplicationsQueue({ applications, t, refusals }) {
    const [notes, setNotes] = useState({});
    if (applications.length === 0) return null;

    const decide = (id, approve) => refusals.actOn(`application:${id}`, () => router.post(`/admin/library/applications/${id}/decide`, { approve, note: notes[id] || undefined }, { preserveScroll: true }));

    return (
        <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
            <table className="table-stack min-w-full text-sm">
                <thead className="bg-[#F3EBE0] text-start">
                    <tr>
                        <th className="px-3 py-2">{t.library_office_th_application || 'Writer application'}</th>
                        <th className="px-3 py-2">{t.library_office_th_background || 'Background'}</th>
                        <th className="px-3 py-2">{t.library_office_th_decision || 'Decision'}</th>
                    </tr>
                </thead>
                <tbody>
                    {applications.map((app) => (
                        <tr key={app.id} className="border-t align-top">
                            <td data-label={t.library_office_th_application || 'Writer application'} className="px-3 py-2">
                                <div className="flex items-start gap-2">
                                    {app.photo_url && <img src={app.photo_url} alt="" className="h-10 w-10 rounded-full object-cover" data-testid="application-portrait" />}
                                    <div>
                                        <p className="font-medium">{app.display_name}</p>
                                        <p className="text-xs text-gray-500">{fill(t.library_office_applied || 'Applied :date', { date: app.applied_at })}</p>
                                        {app.motivation && <p className="mt-1 text-xs text-gray-600">{app.motivation}</p>}
                                    </div>
                                </div>
                            </td>
                            <td data-label={t.library_office_th_background || 'Background'} className="px-3 py-2 text-xs text-gray-600">
                                {app.expertise && <p>{app.expertise}</p>}
                                {app.qualifications && <p>{app.qualifications}</p>}
                                {/* B9 (§11.1): what they have published, and the identity
                                    document — private, served to the office only. */}
                                {app.previous_publications && <p className="mt-1 whitespace-pre-line">{app.previous_publications}</p>}
                                {app.has_id_document && (
                                    <a className="mt-1 inline-block text-[#7C2D37] underline" href={`/admin/library/applications/${app.id}/document`} target="_blank" rel="noopener" data-testid="application-id-document">{t.library_office_id_document || 'ID document'}</a>
                                )}
                                {/* COMMERCE_PARITY_PLAN P2: both sides; approving the application verifies them. */}
                                {app.identity && (
                                    <span className="mt-1 flex gap-2">
                                        <a className="text-[#7C2D37] underline" href={app.identity.front_url} target="_blank" rel="noopener" data-testid="application-id-front">{t.library_office_id_front || 'ID front'}</a>
                                        {app.identity.back_url && <a className="text-[#7C2D37] underline" href={app.identity.back_url} target="_blank" rel="noopener" data-testid="application-id-back">{t.library_office_id_back || 'ID back'}</a>}
                                    </span>
                                )}
                            </td>
                            <td className="table-actions px-3 py-2">
                                <input
                                    className="form-input mb-2 w-48"
                                    placeholder={t.library_office_note_optional || 'Note (optional)'} aria-label={t.library_office_note_optional || 'Note (optional)'}
                                    value={notes[app.id] || ''}
                                    onChange={(e) => setNotes({ ...notes, [app.id]: e.target.value })}
                                />
                                <div className="flex gap-2">
                                    <button type="button" className="btn-primary" onClick={() => decide(app.id, true)}>{t.library_office_approve || 'Approve'}</button>
                                    <button type="button" className="text-sm text-red-600" onClick={() => decide(app.id, false)}>{t.library_office_reject || 'Reject'}</button>
                                </div>
                                <FormErrors errors={refusals.errorsFor(`application:${app.id}`)} className="mt-1" />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function PayoutsQueue({ payouts, t, common, refusals }) {
    const [notes, setNotes] = useState({});
    if (payouts.requests.length === 0 && payouts.writers.length === 0) return null;

    const decide = (id, paid) => refusals.actOn(`payout:${id}`, () => router.post(`/admin/library/payouts/${id}/decide`, { paid, note: notes[id] || undefined }, { preserveScroll: true }));

    return (
        <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
            {payouts.requests.length > 0 && (
                <table className="table-stack min-w-full border-b text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.library_office_th_payout || 'Payout request'}</th>
                            <th className="px-3 py-2">{t.library_office_th_amount || 'Amount'}</th>
                            <th className="px-3 py-2">{t.library_office_th_decision || 'Decision'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {payouts.requests.map((req) => (
                            <tr key={req.id} className="border-t">
                                <td data-label={t.library_office_th_payout || 'Payout request'} className="px-3 py-2">{req.writer} · {req.requested_at}</td>
                                <td data-label={t.library_office_th_amount || 'Amount'} className="px-3 py-2 font-medium">{money(common, req.amount)}</td>
                                <td className="table-actions px-3 py-2">
                                    <input
                                        className="form-input mb-1 w-40"
                                        placeholder={t.library_office_note || 'Note'} aria-label={t.library_office_note || 'Note'}
                                        value={notes[req.id] || ''}
                                        onChange={(e) => setNotes({ ...notes, [req.id]: e.target.value })}
                                    />
                                    <span className="flex gap-2">
                                        <button type="button" className="btn-primary" onClick={() => decide(req.id, true)}>{t.library_office_mark_paid || 'Mark paid'}</button>
                                        <button type="button" className="text-sm text-red-600" onClick={() => decide(req.id, false)}>{t.library_office_reject || 'Reject'}</button>
                                    </span>
                                    <FormErrors errors={refusals.errorsFor(`payout:${req.id}`)} className="mt-1" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
            {payouts.writers.length > 0 && (
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.library_office_th_writer_earnings || 'Writer earnings'}</th>
                            <th className="px-3 py-2">{t.library_office_th_pending || 'Pending'}</th>
                            <th className="px-3 py-2">{t.library_office_th_available || 'Available'}</th>
                            <th className="px-3 py-2">{t.library_office_th_paid || 'Paid'}</th>
                            <th className="px-3 py-2">{t.library_office_th_refunded || 'Refunded'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {payouts.writers.map((row) => (
                            <tr key={row.writer} className="border-t">
                                <td data-label={t.library_office_th_writer_earnings || 'Writer earnings'} className="px-3 py-2">{row.writer}</td>
                                <td data-label={t.library_office_th_pending || 'Pending'} className="px-3 py-2">{row.pending}</td>
                                <td data-label={t.library_office_th_available || 'Available'} className="px-3 py-2">{row.available}</td>
                                <td data-label={t.library_office_th_paid || 'Paid'} className="px-3 py-2">{row.paid}</td>
                                <td data-label={t.library_office_th_refunded || 'Refunded'} className="px-3 py-2">{row.refunded}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}

// R3: research waits for its peer-review accepts before it can be published.
const blockedByReview = (item) => item.content_type === 'research' && item.status !== 'published' && item.review_state?.state !== 'accepted_awaiting_publish';

function SubmissionsQueue({ submissions, reviewers = [], t, common, refusals }) {
    const [comments, setComments] = useState({});
    const [reviewerEmails, setReviewerEmails] = useState({});
    // R3b: when each report is due; 14 days when left empty.
    const [dueDates, setDueDates] = useState({});
    if (submissions.length === 0) return null;

    // A submission's review and its reviewer's assignment are both said on
    // its row: the gate's refusal, an unknown email, a date in the past.
    const review = (id, decision) => refusals.actOn(`submission:${id}`, () => router.post(`/admin/library/items/${id}/review`, { decision, comment: comments[id] || undefined }, { preserveScroll: true }));
    const assign = (id) => refusals.actOn(`submission:${id}`, () => router.post(`/admin/library/items/${id}/assign-reviewer`, { reviewer_email: reviewerEmails[id] || '', due_on: dueDates[id] || undefined }, { preserveScroll: true }));

    return (
        <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
            <datalist id="reviewer-pool">
                {reviewers.map((reviewer) => <option key={reviewer.email} value={reviewer.email}>{fill(t.library_office_reviewer_option || ':name (:open open)', { name: reviewer.name, open: reviewer.open })}</option>)}
            </datalist>
            <table className="table-stack min-w-full text-sm">
                <thead className="bg-[#F3EBE0] text-start">
                    <tr>
                        <th className="px-3 py-2">{t.library_office_th_submitted || 'Submitted item'}</th>
                        <th className="px-3 py-2">{t.library_office_th_history || 'History'}</th>
                        <th className="px-3 py-2">{t.library_office_th_review || 'Review'}</th>
                    </tr>
                </thead>
                <tbody>
                    {submissions.map((sub) => (
                        <tr key={sub.id} className="border-t align-top">
                            <td data-label={t.library_office_th_submitted || 'Submitted item'} className="px-3 py-2">
                                <p className="font-medium">{sub.title}</p>
                                <p className="text-xs text-gray-500">
                                    {sub.writer} · {common[`library_type_${sub.content_type}`] || sub.content_type} · {common[`library_access_${sub.access_type}`] || sub.access_type}{sub.price ? ` · ${money(common, sub.price)}` : ''} · {sub.submitted_at}
                                </p>
                            </td>
                            <td data-label={t.library_office_th_history || 'History'} className="px-3 py-2 text-xs text-gray-600">
                                {sub.history.map((entry, index) => (
                                    <p key={index}>{t[`library_office_decision_${entry.decision}`] || entry.decision}{entry.comment ? ` — ${entry.comment}` : ''}</p>
                                ))}
                                {sub.content_type === 'research' && (
                                    <div className="mt-2 border-t pt-2">
                                        <ReviewStateChip state={sub.review_state} t={common} />
                                        {(sub.reviews || []).map((rev, index) => (
                                            <p key={index}>
                                                {fill(t.library_office_peer_review || 'peer review (round :round): :status', { round: rev.round, status: t[`library_office_assignment_${rev.status}`] || rev.status })}
                                                {rev.recommendation ? ` — ${common[`review_rec_${rev.recommendation}`] || rev.recommendation}` : ''}
                                            </p>
                                        ))}
                                        {/* R3b: pick from the reviewer pool, or type a new email (which adds them). */}
                                        <span className="mt-1 flex flex-wrap gap-1">
                                            <input
                                                className="form-input w-44"
                                                placeholder={t.library_office_reviewer_email || 'Reviewer email'} aria-label={t.library_office_reviewer_email || 'Reviewer email'}
                                                list="reviewer-pool"
                                                value={reviewerEmails[sub.id] || ''}
                                                onChange={(e) => setReviewerEmails({ ...reviewerEmails, [sub.id]: e.target.value })}
                                                data-testid={`assign-email-${sub.id}`}
                                            />
                                            <input
                                                type="date"
                                                className="form-input w-36"
                                                aria-label={common.review_due_label || 'Report due on'}
                                                value={dueDates[sub.id] || ''}
                                                onChange={(e) => setDueDates({ ...dueDates, [sub.id]: e.target.value })}
                                                data-testid={`assign-due-${sub.id}`}
                                            />
                                            <button type="button" className="btn-secondary" onClick={() => assign(sub.id)}>{t.library_office_assign || 'Assign'}</button>
                                        </span>
                                    </div>
                                )}
                            </td>
                            <td data-label={t.library_office_th_review || 'Review'} className="px-3 py-2">
                                <input
                                    className="form-input mb-2 w-56"
                                    placeholder={t.library_office_editor_comment || 'Editor comment'} aria-label={t.library_office_editor_comment || 'Editor comment'}
                                    value={comments[sub.id] || ''}
                                    onChange={(e) => setComments({ ...comments, [sub.id]: e.target.value })}
                                />
                                <div className="flex flex-wrap gap-2">
                                    <button type="button" className="btn-primary disabled:cursor-not-allowed disabled:opacity-50" disabled={blockedByReview(sub)} title={blockedByReview(sub) ? (common.review_publish_blocked || 'Waiting for the peer-review accepts') : undefined} onClick={() => review(sub.id, 'approved')} data-testid="approve-publish">{t.library_office_approve_publish || 'Approve & publish'}</button>
                                    <button type="button" className="btn-secondary" onClick={() => review(sub.id, 'changes_requested')}>{t.library_office_request_changes || 'Request changes'}</button>
                                    <button type="button" className="text-sm text-red-600" onClick={() => review(sub.id, 'rejected')}>{t.library_office_reject || 'Reject'}</button>
                                </div>
                                <FormErrors errors={refusals.errorsFor(`submission:${sub.id}`)} className="mt-1" />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function ItemForm({ categories, options, t, common, actOn }) {
    const form = useForm({
        title: '',
        subtitle: '',
        content_type: 'article',
        access_type: 'free_public',
        price: '',
        library_category_id: '',
        abstract: '',
        body: '',
        cover_image: '',
        reading_time: '',
        difficulty: '',
        tags_text: '',
        authors_text: '',
        // R1: teacher authors, and how readers get it (D1).
        teacher_ids: [],
        delivery: '',
        pdf: null,
        cover: null,
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.transform(({ teacher_ids, delivery, ...data }) => ({
                    ...data,
                    tags: data.tags_text ? data.tags_text.split(',').map((tag) => tag.trim()).filter(Boolean) : [],
                    // The institute's teachers first, then the other names.
                    authors: [
                        ...teacher_ids.map((id) => ({ instructor_profile_id: id })),
                        ...(data.authors_text
                            ? data.authors_text.split(',').map((name) => ({ name: name.trim() })).filter((author) => author.name)
                            : []),
                    ],
                    delivery: delivery || defaultDelivery(data.content_type, data.access_type),
                }));
                actOn('item-form', () => form.post('/admin/library/items', {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => form.reset(),
                }));
            }}
            className="mb-6 grid gap-2 rounded-lg border bg-white p-4 md:grid-cols-4"
        >
            <input className="form-input md:col-span-2" placeholder={common.library_f_title || 'Title'} aria-label={common.library_f_title || 'Title'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
            <select className="form-input" value={form.data.content_type} onChange={(e) => form.setData('content_type', e.target.value)} aria-label={common.library_f_type || 'Type'}>
                {options.content_types.map((type) => <option key={type} value={type}>{common[`library_type_${type}`] || type.replaceAll('_', ' ')}</option>)}
            </select>
            <select className="form-input" value={form.data.access_type} onChange={(e) => form.setData('access_type', e.target.value)} aria-label={common.library_f_access || 'Access'}>
                {options.access_types.map((type) => <option key={type} value={type}>{common[`library_access_${type}`] || type.replaceAll('_', ' ')}</option>)}
            </select>

            <input className="form-input" placeholder={t.library_office_price || 'Price (MVR)'} aria-label={t.library_office_price || 'Price (MVR)'} value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} />
            <input className="form-input" placeholder={common.library_f_subtitle || 'Subtitle'} aria-label={common.library_f_subtitle || 'Subtitle'} value={form.data.subtitle} onChange={(e) => form.setData('subtitle', e.target.value)} />
            <select className="form-input" value={form.data.library_category_id} onChange={(e) => form.setData('library_category_id', e.target.value)} aria-label={common.library_f_category || 'Category'}>
                <option value="">{common.library_f_category_pick || 'Category…'}</option>
                {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
            </select>
            <input className="form-input" placeholder={t.library_office_authors || 'Authors (comma-separated)'} aria-label={t.library_office_authors || 'Authors (comma-separated)'} value={form.data.authors_text} onChange={(e) => form.setData('authors_text', e.target.value)} />
            <input className="form-input" placeholder={t.library_office_tags || 'Tags (comma-separated)'} aria-label={t.library_office_tags || 'Tags (comma-separated)'} value={form.data.tags_text} onChange={(e) => form.setData('tags_text', e.target.value)} />

            <TeacherAuthors className="md:col-span-4" teachers={options.teachers || []} value={form.data.teacher_ids} onChange={(ids) => form.setData('teacher_ids', ids)} t={common} />
            <DeliveryChoice
                className="md:col-span-4"
                contentType={form.data.content_type}
                value={form.data.delivery || defaultDelivery(form.data.content_type, form.data.access_type)}
                onChange={(value) => form.setData('delivery', value)}
                hasPdf={Boolean(form.data.pdf)}
                t={common}
            />

            <textarea className="form-input md:col-span-2" rows="2" placeholder={common.library_f_abstract || 'Abstract'} aria-label={common.library_f_abstract || 'Abstract'} value={form.data.abstract} onChange={(e) => form.setData('abstract', e.target.value)} />
            <label className="text-sm">
                {t.library_office_cover || 'Cover image (JPEG, PNG or WebP)'}
                <input className="form-input" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => form.setData('cover', e.target.files[0] ?? null)} />
            </label>
            <input className="form-input" placeholder={t.library_office_cover_url || '…or a cover image URL'} aria-label={t.library_office_cover_url || '…or a cover image URL'} value={form.data.cover_image} onChange={(e) => form.setData('cover_image', e.target.value)} />
            <input className="form-input" type="number" min="1" placeholder={common.library_f_reading_time || 'Reading time (min)'} aria-label={common.library_f_reading_time || 'Reading time (min)'} value={form.data.reading_time} onChange={(e) => form.setData('reading_time', e.target.value)} />
            <select className="form-input" value={form.data.difficulty} onChange={(e) => form.setData('difficulty', e.target.value)} aria-label={common.library_f_difficulty || 'Difficulty'}>
                <option value="">{common.library_difficulty_none || 'Difficulty: not set'}</option>
                <option value="beginner">{common.library_difficulty_beginner || 'Beginner'}</option>
                <option value="intermediate">{common.library_difficulty_intermediate || 'Intermediate'}</option>
                <option value="advanced">{common.library_difficulty_advanced || 'Advanced'}</option>
            </select>

            {/* B3: the same editor the writer has; the `</>` toggle takes pasted HTML. */}
            <BodyEditor className="md:col-span-4" value={form.data.body} onChange={(html) => form.setData('body', html)} placeholder={t.library_office_body || 'Body — the free-reading content; insert a page break between pages'} labels={editorLabels(common)} testId="admin-body-editor" />

            <label className="text-sm md:col-span-3">
                {t.library_office_pdf || 'Original PDF (stored privately — never exposed)'}
                <input className="form-input" type="file" accept="application/pdf" onChange={(e) => form.setData('pdf', e.target.files[0] ?? null)} />
                <span className="mt-1 block text-xs text-gray-500">
                    {t.library_office_pdf_help || 'Its pages become the reader’s pages, watermarked per reader. A scanned PDF has no text to show — paste the text into the body. When both exist, the body wins.'}
                </span>
            </label>
            <button type="submit" className="btn-primary self-end" disabled={form.processing}>{t.library_office_save_item || 'Save item'}</button>
            <FormErrors errors={form.errors} />
        </form>
    );
}

/**
 * §5po: a category in English, Dhivehi and Arabic. The shelf's filter and an
 * item's page say the name for the page's language (LT6), and the English
 * where none was given.
 */
function CategoryForm({ t, actOn }) {
    const form = useForm({ name: '', name_dv: '', name_ar: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                actOn('category', () => form.post('/admin/library/categories', { preserveScroll: true, onSuccess: () => form.reset() }));
            }}
            className="flex flex-wrap gap-2"
            data-testid="category-form"
        >
            <input className="form-input" placeholder={t.library_office_new_category || 'New category'} aria-label={t.library_office_new_category || 'New category'} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} data-testid="new-category-name" />
            <input className="form-input" dir="rtl" lang="dv" placeholder={t.library_office_category_name_dv || 'Name in Dhivehi'} aria-label={t.library_office_category_name_dv || 'Name in Dhivehi'} value={form.data.name_dv} onChange={(e) => form.setData('name_dv', e.target.value)} data-testid="new-category-name-dv" />
            <input className="form-input" dir="rtl" lang="ar" placeholder={t.library_office_category_name_ar || 'Name in Arabic'} aria-label={t.library_office_category_name_ar || 'Name in Arabic'} value={form.data.name_ar} onChange={(e) => form.setData('name_ar', e.target.value)} data-testid="new-category-name-ar" />
            <button type="submit" className="btn-secondary" disabled={form.processing}>{t.library_office_add || 'Add'}</button>
            <FormErrors errors={form.errors} />
        </form>
    );
}

/** §5po: one category, renamed in place. Its address (the slug) stays. */
function CategoryRow({ category, t }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ name: category.name, name_dv: category.name_dv || '', name_ar: category.name_ar || '' });
    const stop = () => {
        form.reset();
        form.clearErrors();
        setEditing(false);
    };

    return (
        <li className="border-t p-3 text-sm" data-testid={`category-row-${category.slug}`}>
            {editing ? (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(`/admin/library/categories/${category.id}`, { preserveScroll: true, onSuccess: () => setEditing(false) });
                    }}
                    className="flex flex-wrap items-center gap-2"
                >
                    <input className="form-input" aria-label={t.library_office_category_name_en || 'Name in English'} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} data-testid="category-name" />
                    <input className="form-input" dir="rtl" lang="dv" aria-label={t.library_office_category_name_dv || 'Name in Dhivehi'} value={form.data.name_dv} onChange={(e) => form.setData('name_dv', e.target.value)} data-testid="category-name-dv" />
                    <input className="form-input" dir="rtl" lang="ar" aria-label={t.library_office_category_name_ar || 'Name in Arabic'} value={form.data.name_ar} onChange={(e) => form.setData('name_ar', e.target.value)} data-testid="category-name-ar" />
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="category-save">{t.library_office_category_save || 'Save names'}</button>
                    <button type="button" className="btn-secondary" onClick={stop}>{t.library_office_category_cancel || 'Cancel'}</button>
                    <FormErrors errors={form.errors} className="w-full" />
                </form>
            ) : (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="min-w-0">
                        <span className="font-medium" data-office-words data-testid="category-en">{category.name}</span>
                        <span className="ms-3 text-gray-700" dir="rtl" lang="dv" data-office-words data-testid="category-dv">{category.name_dv || '—'}</span>
                        <span className="ms-3 text-gray-700" dir="rtl" lang="ar" data-office-words data-testid="category-ar">{category.name_ar || '—'}</span>
                        <span className="ms-3 text-xs text-gray-500">{fill(t.library_office_category_count || ':count published', { count: category.published_count ?? 0 })}</span>
                    </span>
                    <button type="button" className="btn-secondary" onClick={() => setEditing(true)} data-testid="category-rename">{t.library_office_category_rename || 'Rename'}</button>
                </div>
            )}
        </li>
    );
}

function CategoryList({ categories, t }) {
    if (categories.length === 0) return null;

    return (
        <details className="mb-6 rounded-lg border bg-white" data-testid="library-categories">
            <summary className="cursor-pointer p-3 font-semibold">{fill(t.library_office_categories || 'Categories (:count)', { count: categories.length })}</summary>
            <ul>
                {/* Keyed by the names too, so a saved rename redraws the row from the server's values. */}
                {categories.map((category) => <CategoryRow key={`${category.id}:${category.name}:${category.name_dv}:${category.name_ar}`} category={category} t={t} />)}
            </ul>
        </details>
    );
}

export default function Admin({ items, categories, options, sales = [], queues = { applications: [], submissions: [] }, payouts = { requests: [], writers: [] }, identity_checks = [], id_l = {}, t = {} }) {
    const common = usePage().props.i18n?.common || {};
    // The queues' and the shelf's buttons post through `router.post` with no
    // form object, so a refusal — the peer-review gate declining research
    // with no accept on file, an unknown reviewer, a decided application —
    // is said on the row whose button was pressed; each form says its own.
    const refusals = useRowRefusals();

    return (
        <AppShell title={t.library_office_title || 'Digital Library admin'}>
            <FormErrors errors={refusals.unplaced} className="mb-4" />
            {/* COMMERCE_PARITY_PLAN P2: writers' identity cards. */}
            {identity_checks.some((r) => r.status === 'pending') && <div id="identity" className="mb-6"><IdentityChecks rows={identity_checks} l={id_l} /></div>}
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <CategoryForm t={t} actOn={refusals.actOn} />
                {/* Wraps on a phone: six buttons in one row were 649 px wide and made Safari zoom the page out (STATUS §5jq). */}
                <span className="flex flex-wrap gap-2">
                    <a className="btn-secondary" href="/admin/library/reading-alerts">{t.library_office_reading_alerts || 'Reading alerts'}</a>
                    <a className="btn-secondary" href="/admin/library/reviewers" data-testid="library-reviewers-link">{common.library_reviewers_link || 'Reviewers'}</a>
                    <a className="btn-secondary" href="/admin/library/settings" data-testid="library-settings-link">{t.library_office_settings || 'Settings'}</a>
                    <a className="btn-secondary" href="/admin/library/insights" data-testid="library-insights-link">{t.library_office_insights || 'Insights'}</a>
                    <a className="btn-secondary" href="/admin/library/promotions" data-testid="library-promotions-link">{t.library_office_promotions || 'Promotions'}</a>
                    <a className="btn-secondary" href="/admin/library/earnings/export">{t.library_office_earnings_csv || 'Earnings CSV'}</a>
                    <a className="btn-secondary" href="/admin/library?format=csv">{t.library_office_export_csv || 'Export CSV'}</a>
                </span>
            </div>
            <CategoryList categories={categories} t={t} />

            <ApplicationsQueue applications={queues.applications} t={t} refusals={refusals} />
            <SubmissionsQueue submissions={queues.submissions} reviewers={options.reviewers || []} t={t} common={common} refusals={refusals} />
            <PayoutsQueue payouts={payouts} t={t} common={common} refusals={refusals} />

            <ItemForm categories={categories} options={options} t={t} common={common} actOn={refusals.actOn} />

            {sales.length > 0 && (
                <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                    <table className="table-stack min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">{t.library_office_th_sales || 'Sales'}</th>
                                <th className="px-3 py-2">{t.library_office_th_count || 'Count'}</th>
                                <th className="px-3 py-2">{t.library_office_th_revenue || 'Revenue (MVR)'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sales.map((row) => (
                                <tr key={row.library_item_id} className="border-t">
                                    <td data-label={t.library_office_th_sales || 'Sales'} className="px-3 py-2">{row.title}</td>
                                    <td data-label={t.library_office_th_count || 'Count'} className="px-3 py-2">{row.sales}</td>
                                    <td data-label={t.library_office_th_revenue || 'Revenue (MVR)'} className="px-3 py-2">{row.revenue}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.library_office_th_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.library_office_th_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.library_office_th_access || 'Access'}</th>
                            <th className="px-3 py-2">{t.library_office_th_category || 'Category'}</th>
                            <th className="px-3 py-2">{t.library_office_th_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.library_office_th_published || 'Published'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={7}>{t.library_office_no_items || 'No library items yet.'}</td></tr>
                        )}
                        {items.map((item) => (
                            <tr key={item.id} className="border-t">
                                <td data-label={t.library_office_th_title || 'Title'} className="px-3 py-2">
                                    <div className="font-medium">{item.title}</div>
                                    <div className="text-xs text-gray-500">/{item.slug}{item.has_pdf ? ' · PDF' : ''}</div>
                                </td>
                                <td data-label={t.library_office_th_type || 'Type'} className="px-3 py-2">{common[`library_type_${item.content_type}`] || item.content_type?.replaceAll('_', ' ')}</td>
                                <td data-label={t.library_office_th_access || 'Access'} className="px-3 py-2">{common[`library_access_${item.access_type}`] || item.access_type?.replaceAll('_', ' ')}</td>
                                <td data-label={t.library_office_th_category || 'Category'} className="px-3 py-2">{item.category?.name ?? '—'}</td>
                                <td data-label={t.library_office_th_status || 'Status'} className="px-3 py-2">{common[`library_status_${item.status}`] || item.status} {item.review_state && <ReviewStateChip state={item.review_state} t={common} />}</td>
                                <td data-label={t.library_office_th_published || 'Published'} className="px-3 py-2">{item.published_at ?? '—'}</td>
                                <td className="table-actions px-3 py-2 text-end whitespace-nowrap">
                                    {item.status === 'published' && (
                                        <button
                                            type="button"
                                            className="me-3 text-sm text-[#7C2D37] hover:underline"
                                            data-testid={`feature-${item.slug}`}
                                            onClick={() => refusals.actOn(`item:${item.id}`, () => router.post(`/admin/library/items/${item.id}/feature`, { featured: !item.featured }, { preserveScroll: true }))}
                                        >
                                            {item.featured ? t.library_office_unfeature || '★ Unfeature' : t.library_office_feature || '☆ Feature'}
                                        </button>
                                    )}
                                    <button
                                        type="button"
                                        className={item.status === 'published' ? 'text-sm text-red-600' : 'btn-primary disabled:cursor-not-allowed disabled:opacity-50'}
                                        disabled={blockedByReview(item)}
                                        data-testid={`publish-${item.slug}`}
                                        onClick={() => refusals.actOn(`item:${item.id}`, () => router.post(`/admin/library/items/${item.id}/publish`, { publish: item.status !== 'published' }, { preserveScroll: true }))}
                                    >
                                        {item.status === 'published' ? t.library_office_unpublish || 'Unpublish' : t.library_office_publish || 'Publish'}
                                    </button>
                                    <FormErrors errors={refusals.errorsFor(`item:${item.id}`)} className="mt-1 text-start" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
                    {!identity_checks.some((r) => r.status === 'pending') && <div id="identity" className="mt-8"><IdentityChecks rows={identity_checks} l={id_l} /></div>}
        </AppShell>
    );
}
