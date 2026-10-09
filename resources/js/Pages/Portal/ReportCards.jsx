import { router, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function ReportCards({ children, studentId, cards, pdf_available = false, t = {} }) {
    // A transcript in the page's language: the controller has always taken
    // `en`, `dv` or `ar`, and the button always sent `en` (BACKLOG C21, slice
    // PT2).
    const locale = usePage().props.locale || 'en';
    const col = {
        student: t.col_student || 'Student',
        term: t.col_term || 'Term',
        published: t.col_published || 'Published',
        download: t.col_download || 'Download',
    };

    return (
        <AppShell title={t.report_cards_title || 'Report cards'}>
            <p className="mb-4 rounded border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700">
                {pdf_available
                    ? (t.report_cards_pdf_hint || 'Download a report card as a PDF, or open it to read and print.')
                    : (t.report_cards_html_hint || 'These files are HTML, not PDF: open one to read and print it.')}
            </p>
            <div className="mb-4 flex flex-wrap gap-3">
                <select
                    className="form-input"
                    aria-label={t.pick_child || 'Child'}
                    value={studentId || ''}
                    onChange={(e) => router.get(`/portal/report-cards?student_id=${e.target.value}`)}
                >
                    {children.map((child) => <option key={child.id} value={child.id}>{child.name}</option>)}
                </select>
                {studentId && (
                    <a className="btn-secondary" href={`/portal/transcript?student_id=${studentId}&locale=${locale}`} data-testid="transcript">
                        {t.report_cards_transcript || 'Request transcript'}
                    </a>
                )}
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.student}</th>
                            <th className="px-3 py-2">{col.term}</th>
                            <th className="px-3 py-2">{col.published}</th>
                            <th className="px-3 py-2">{col.download}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {cards.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.report_cards_none || 'No published report cards yet.'}</td></tr>
                        )}
                        {cards.map((card) => (
                            <tr key={card.id} className="border-t">
                                <td className="px-3 py-2" data-label={col.student}>{card.student_name}</td>
                                <td className="px-3 py-2" data-label={col.term}>{card.term_name}</td>
                                <td className="px-3 py-2" data-label={col.published}>{card.published_at || '—'}</td>
                                <td className="table-actions px-3 py-2">
                                    {pdf_available && <a className="chip-link" href={`/portal/report-cards/${card.id}/download?format=pdf`} data-testid={`report-card-pdf-${card.id}`}>{t.report_cards_pdf || 'Download PDF'}</a>}
                                    <a className="chip-link" href={`/portal/report-cards/${card.id}/download`} data-testid={`report-card-open-${card.id}`}>{pdf_available ? (t.open || 'Open') : (t.report_cards_open_print || 'Open to print')}</a>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
