import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

const LANGUAGES = ['en', 'dv', 'ar'];
const COMMENT_TYPES = ['class_teacher', 'head'];

/**
 * A class's report cards: generated, published, commented on, and a pupil's
 * transcript. Every word is the `exams` book's (slice EG2, STATUS §5qk); a
 * card's state and a comment's author are named rather than printed as
 * codes, and a card's language is named, not abbreviated. A refused
 * publish or comment is said under the form that asked — they were said
 * nowhere — and a teacher's or head's comment can be written in Dhivehi and
 * Arabic for a card in those languages.
 */
export default function Index({ classes, terms, cards, unpublished, classId, termId, pdf_available = false, t = {} }) {
    // The term being viewed, else the one actually running, else the first.
    // Defaulting straight to `terms[0]` opened the publish control on Term 2
    // while the table below listed Term 1 — the control and the evidence for
    // using it disagreed.
    const startTerm = termId
        || terms.find((term) => term.status === 'active')?.id
        || terms[0]?.id
        || '';
    const startClass = classId || classes[0]?.id || '';
    const language = (code) => t[`language_${code}`] || code;
    const statusName = (status) => t[`report_card_status_${status}`] || status;

    const generate = useForm({
        class_id: startClass,
        term_id: startTerm,
        locale: 'en',
        regenerate_published: false,
        reason: '',
    });
    const publish = useForm({
        class_id: startClass,
        term_id: startTerm,
    });
    const comment = useForm({
        report_card_id: cards[0]?.id || '',
        comment_type: 'class_teacher',
        comment: '',
        comment_dhivehi: '',
        comment_arabic: '',
    });
    const transcript = useForm({
        student_id: cards[0]?.student_id || '',
        locale: 'en',
    });
    const generateRefused = generate.errors.reason || generate.errors.status || generate.errors.term_id || generate.errors.class_id || generate.errors.template_id;
    const publishRefused = publish.errors.status || publish.errors.class_id || publish.errors.term_id;
    const commentRefused = comment.errors.comment || comment.errors.report_card_id || comment.errors.comment_type;

    return (
        <AppShell title={t.reportcards_title || 'Report cards'}>
            <div className="mb-4 flex flex-wrap justify-between gap-2">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        const data = new FormData(e.currentTarget);
                        router.get('/exams/report-cards', {
                            class_id: data.get('class_id'),
                            term_id: data.get('term_id'),
                        });
                    }}
                    className="flex gap-2"
                >
                    <select className="form-input" name="class_id" aria-label={t.class || 'Class'} defaultValue={classId || ''}>
                        <option value="">{t.all_classes || 'All classes'}</option>
                        {classes.map((row) => <option key={row.id} value={row.id}>{row.name} {row.section}</option>)}
                    </select>
                    <select className="form-input" name="term_id" aria-label={t.term || 'Term'} defaultValue={termId || ''}>
                        <option value="">{t.all_terms || 'All terms'}</option>
                        {terms.map((term) => <option key={term.id} value={term.id}>{term.name}</option>)}
                    </select>
                    <button type="submit" className="btn-secondary">{t.filter || 'Filter'}</button>
                </form>
                <a className="btn-secondary" href={`/exams/report-cards/export?class_id=${classId || ''}&term_id=${termId || ''}`}>{t.export_csv || 'Export CSV'}</a>
            </div>

            <p className="mb-4 rounded border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700">
                {pdf_available
                    ? (t.reportcards_pdf_note || 'Report cards are kept as web pages. A PDF is printed from the page when you download one.')
                    : (t.reportcards_html_note || 'Report cards are web pages, not PDFs. Open one to print it.')}
            </p>

            {unpublished.length > 0 && (
                <div className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
                    {(t.reportcards_unpublished || 'Unpublished report cards: :count').replace(':count', unpublished.length)}
                </div>
            )}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    generate.post('/exams/report-cards/generate', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" aria-label={t.class || 'Class'} value={generate.data.class_id} onChange={(e) => generate.setData('class_id', e.target.value)}>
                    {classes.map((row) => <option key={row.id} value={row.id}>{row.name} {row.section}</option>)}
                </select>
                <select className="form-input" aria-label={t.term || 'Term'} value={generate.data.term_id} onChange={(e) => generate.setData('term_id', e.target.value)}>
                    {terms.map((term) => <option key={term.id} value={term.id}>{term.name}</option>)}
                </select>
                <select className="form-input" aria-label={t.reportcards_language || 'Language of the card'} value={generate.data.locale} onChange={(e) => generate.setData('locale', e.target.value)}>
                    {LANGUAGES.map((code) => <option key={code} value={code}>{language(code)}</option>)}
                </select>
                <button type="submit" className="btn-primary" disabled={generate.processing}>{t.reportcards_generate || 'Generate'}</button>
                <label className="flex items-center gap-2 text-sm md:col-span-2">
                    <input
                        type="checkbox"
                        checked={generate.data.regenerate_published}
                        onChange={(e) => generate.setData('regenerate_published', e.target.checked)}
                    />
                    {t.reportcards_regenerate || 'Also regenerate published cards (a reason is recorded against each)'}
                </label>
                {generate.data.regenerate_published && (
                    <input
                        className="form-input md:col-span-2"
                        aria-label={t.reportcards_reason || 'Reason, e.g. corrected Arabic mark'}
                        placeholder={t.reportcards_reason || 'Reason, e.g. corrected Arabic mark'}
                        value={generate.data.reason}
                        onChange={(e) => generate.setData('reason', e.target.value)}
                    />
                )}
                {generateRefused && <p className="text-sm text-red-600 md:col-span-4">{generateRefused}</p>}
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    publish.post('/exams/report-cards/publish', { preserveScroll: true });
                }}
                className="mb-4 flex flex-wrap gap-3 rounded-lg border bg-white p-4"
            >
                <select className="form-input" aria-label={t.class || 'Class'} value={publish.data.class_id} onChange={(e) => publish.setData('class_id', e.target.value)}>
                    {classes.map((row) => <option key={row.id} value={row.id}>{row.name} {row.section}</option>)}
                </select>
                <select className="form-input" aria-label={t.term || 'Term'} value={publish.data.term_id} onChange={(e) => publish.setData('term_id', e.target.value)}>
                    {terms.map((term) => <option key={term.id} value={term.id}>{term.name}</option>)}
                </select>
                <button type="submit" className="btn-secondary" disabled={publish.processing}>{t.reportcards_publish || 'Publish ready cards'}</button>
                {publishRefused && <p className="w-full text-sm text-red-600">{publishRefused}</p>}
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    comment.post('/exams/report-cards/comment', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <select className="form-input" aria-label={t.reportcards_card || 'Report card'} value={comment.data.report_card_id} onChange={(e) => comment.setData('report_card_id', e.target.value)}>
                    {cards.map((card) => <option key={card.id} value={card.id}>{card.student_name} — {card.term_name}</option>)}
                </select>
                <select className="form-input" aria-label={t.reportcards_comment_by || 'Comment by'} value={comment.data.comment_type} onChange={(e) => comment.setData('comment_type', e.target.value)}>
                    {COMMENT_TYPES.map((type) => <option key={type} value={type}>{t[`comment_type_${type}`] || type}</option>)}
                </select>
                <input className="form-input" aria-label={t.reportcards_comment_en || 'Comment (EN)'} placeholder={t.reportcards_comment_en || 'Comment (EN)'} value={comment.data.comment} onChange={(e) => comment.setData('comment', e.target.value)} />
                <input className="form-input" dir="rtl" aria-label={t.reportcards_comment_dv || 'Comment (DV)'} placeholder={t.reportcards_comment_dv || 'Comment (DV)'} value={comment.data.comment_dhivehi} onChange={(e) => comment.setData('comment_dhivehi', e.target.value)} />
                <input className="form-input" dir="rtl" aria-label={t.reportcards_comment_ar || 'Comment (AR)'} placeholder={t.reportcards_comment_ar || 'Comment (AR)'} value={comment.data.comment_arabic} onChange={(e) => comment.setData('comment_arabic', e.target.value)} />
                <button type="submit" className="btn-secondary" disabled={comment.processing}>{t.reportcards_save_comment || 'Save comment'}</button>
                {commentRefused && <p className="text-sm text-red-600 md:col-span-3">{commentRefused}</p>}
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    window.location.href = `/exams/transcript?student_id=${transcript.data.student_id}&locale=${transcript.data.locale}`;
                }}
                className="mb-4 flex flex-wrap gap-3 rounded-lg border bg-white p-4"
            >
                <select className="form-input" aria-label={t.student || 'Student'} value={transcript.data.student_id} onChange={(e) => transcript.setData('student_id', e.target.value)}>
                    {cards.map((card) => <option key={card.student_id} value={card.student_id}>{card.student_name}</option>)}
                </select>
                <select className="form-input" aria-label={t.reportcards_transcript_language || 'Language of the transcript'} value={transcript.data.locale} onChange={(e) => transcript.setData('locale', e.target.value)}>
                    {LANGUAGES.map((code) => <option key={code} value={code}>{language(code)}</option>)}
                </select>
                <button type="submit" className="btn-secondary">{t.reportcards_open_transcript || 'Open transcript'}</button>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.student || 'Student'}</th>
                            <th className="px-3 py-2">{t.class || 'Class'}</th>
                            <th className="px-3 py-2">{t.term || 'Term'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                            <th className="px-3 py-2">{t.reportcards_col_revisions || 'Revisions'}</th>
                            <th className="px-3 py-2">{t.reportcards_col_document || 'Document'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {cards.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.reportcards_none || 'No report cards yet.'}</td></tr>
                        )}
                        {cards.map((card) => (
                            <tr key={card.id} className="border-t">
                                <td className="px-3 py-2">{card.student_name}</td>
                                <td className="px-3 py-2">{card.class_name}</td>
                                <td className="px-3 py-2">{card.term_name}</td>
                                <td className="px-3 py-2">{statusName(card.status)}</td>
                                <td className="px-3 py-2" title={card.last_revision_reason || ''}>
                                    {card.revisions ? `${card.revisions} — ${card.last_revision_reason}` : '—'}
                                </td>
                                <td className="px-3 py-2">
                                    {card.document_id ? <span className="flex flex-wrap gap-3">
                                        {pdf_available && <a className="text-[#7C2D37] underline" href={`/exams/report-cards/${card.id}/download?format=pdf`} data-testid={`report-card-pdf-${card.id}`}>{t.reportcards_pdf || 'PDF'}</a>}
                                        <a className="text-[#7C2D37] underline" href={`/exams/report-cards/${card.id}/download`}>{pdf_available ? (t.reportcards_web_page || 'Web page') : (t.reportcards_open_print || 'Open to print')}</a>
                                    </span> : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
