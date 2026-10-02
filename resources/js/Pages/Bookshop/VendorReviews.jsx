import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';
import VendorNav from '../../Components/Bookshop/VendorNav';

/**
 * BOOKSHOP_PLAN slice B7 — the shop's reviews: every review of its products
 * from customers who received them, the office's moderation state, and one
 * public reply each (editable). CSV of the list.
 */

function Stars({ rating }) {
    return <span className="text-amber-700" aria-label={`${rating}/5`}>{'★'.repeat(rating)}{'☆'.repeat(5 - rating)}</span>;
}

function ReviewRow({ review: r, t }) {
    const [text, setText] = useState(r.reply || '');
    const [open, setOpen] = useState(!r.reply && r.status === 'published');

    return (
        <li className="rounded border bg-white p-3" data-testid={`vendor-review-${r.id}`} data-review-status={r.status}>
            <p className="text-sm">
                <Stars rating={r.rating} /> · <a href={`/shop/products/${r.product_slug}#reviews`} target="_blank" rel="noreferrer" className="font-semibold text-blue-700 underline">{r.product}</a>
                <span className="text-gray-500"> · {r.order_number} · {r.created_at}</span>
                {r.status !== 'published' && <span className="ms-2 rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-900">{t[`review_status_${r.status}`] || r.status}</span>}
            </p>
            {r.body && <p className="mt-1 whitespace-pre-line text-sm" dir="auto">{r.body}</p>}
            {r.moderation_note && <p className="mt-1 text-xs text-red-700">{t.office_note}: {r.moderation_note}</p>}
            {r.reply && !open && (
                <div className="mt-2 ms-4 rounded bg-gray-50 p-2 text-sm">
                    <p className="whitespace-pre-line" dir="auto">{r.reply}</p>
                    <button type="button" className="text-xs text-blue-700 underline" onClick={() => setOpen(true)}>{t.edit}</button>
                </div>
            )}
            {open && (
                <form className="mt-2 flex flex-wrap items-start gap-2" onSubmit={(e) => { e.preventDefault(); router.post(`/vendor/reviews/${r.id}/reply`, { reply: text }, { preserveScroll: true, onSuccess: () => setOpen(false) }); }}>
                    <textarea className="form-input w-full min-w-0 flex-1" rows={2} value={text} onChange={(e) => setText(e.target.value)} placeholder={t.reply_placeholder} maxLength={2000} data-testid={`reply-text-${r.id}`} />
                    <button type="submit" className="btn-primary" data-testid={`reply-save-${r.id}`}>{t.reply_publicly}</button>
                </form>
            )}
        </li>
    );
}

/** STATUS §5le: a customer's question about a product, and the shop's public answer. */
function QuestionRow({ question: q, t }) {
    const [text, setText] = useState(q.answer || '');
    const [open, setOpen] = useState(!q.answer && q.status === 'published');

    return (
        <li className="rounded border bg-white p-3" data-testid={`vendor-question-${q.id}`} data-question-status={q.status}>
            <p className="text-sm">
                <a href={`/shop/products/${q.product_slug}#questions`} target="_blank" rel="noreferrer" className="font-semibold text-blue-700 underline">{q.product}</a>
                <span className="text-gray-500"> · {q.created_at}</span>
                {q.status !== 'published' && <span className="ms-2 rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-900">{t[`review_status_${q.status}`] || q.status}</span>}
                {!q.answer && q.status === 'published' && <span className="ms-2 rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-900">{t.question_unanswered}</span>}
            </p>
            <p className="mt-1 whitespace-pre-line text-sm" dir="auto">{t.question_q} {q.question}</p>
            {q.moderation_note && <p className="mt-1 text-xs text-red-700">{t.office_note}: {q.moderation_note}</p>}
            {q.answer && !open && (
                <div className="mt-2 ms-4 rounded bg-gray-50 p-2 text-sm">
                    <p className="whitespace-pre-line" dir="auto">{q.answer}</p>
                    <button type="button" className="text-xs text-blue-700 underline" onClick={() => setOpen(true)}>{t.edit}</button>
                </div>
            )}
            {open && (
                <form className="mt-2 flex flex-wrap items-start gap-2" onSubmit={(e) => { e.preventDefault(); router.post(`/vendor/questions/${q.id}/answer`, { answer: text }, { preserveScroll: true, onSuccess: () => setOpen(false) }); }}>
                    <textarea className="form-input w-full min-w-0 flex-1" rows={2} dir="auto" value={text} onChange={(e) => setText(e.target.value)} placeholder={t.answer_placeholder} maxLength={2000} data-testid={`answer-text-${q.id}`} />
                    <button type="submit" className="btn-primary" data-testid={`answer-save-${q.id}`}>{t.answer_publicly}</button>
                </form>
            )}
        </li>
    );
}

export default function VendorReviews({ t, vendor, reviews, questions = { waiting: 0, questions: [] } }) {
    const { errors } = usePage().props;
    const s = reviews.summary;

    return (
        <AppShell title={t.reviews_heading}>
            <FormErrors errors={errors} className="mb-4" />
            <header className="mb-4">
                <div className="mb-3 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold" data-testid="reviews-heading">{t.reviews_heading} · {vendor.name}</h1>
                        <p className="text-sm text-gray-600">
                            {s.count > 0 ? t.rating_summary.replace(':avg', s.avg).replace(':count', s.count) : t.no_reviews}
                            {s.unanswered > 0 && <span className="ms-2 text-amber-800" data-testid="unanswered">{t.unanswered_count.replace(':count', s.unanswered)}</span>}
                        </p>
                    </div>
                    <a href="/vendor/reviews/export" className="btn-secondary">{t.export_csv}</a>
                </div>
                <VendorNav t={t} current="reviews" />
            </header>
            {reviews.reviews.length === 0 ? (
                <p className="rounded border bg-white p-4 text-gray-600">{t.no_reviews}</p>
            ) : (
                <ul className="space-y-3" data-testid="vendor-reviews">
                    {reviews.reviews.map((r) => <ReviewRow key={r.id} review={r} t={t} />)}
                </ul>
            )}

            <section id="questions" className="mt-8" data-testid="vendor-questions-section">
                <header className="mb-3 flex flex-wrap items-end justify-between gap-3">
                    <h2 className="text-xl font-bold">
                        {t.questions_heading}
                        {questions.waiting > 0 && <span className="ms-2 rounded bg-amber-100 px-2 text-sm font-normal text-amber-800" data-testid="questions-waiting">{t.questions_waiting_count.replace(':count', questions.waiting)}</span>}
                    </h2>
                    <a href="/vendor/questions/export" className="btn-secondary">{t.export_csv}</a>
                </header>
                {questions.questions.length === 0 ? (
                    <p className="rounded border bg-white p-4 text-gray-600">{t.no_questions}</p>
                ) : (
                    <ul className="space-y-3" data-testid="vendor-questions">
                        {questions.questions.map((q) => <QuestionRow key={q.id} question={q} t={t} />)}
                    </ul>
                )}
            </section>
        </AppShell>
    );
}
