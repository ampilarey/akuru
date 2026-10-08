import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

const fill = (text, values) => Object.entries(values).reduce((out, [key, value]) => out.replace(`:${key}`, value), String(text));

// accept / revise / reject, as the reviewer's buttons name them (slice LT2).
const recommendationLabel = (t, recommendation) => t[`review_rec_${recommendation}`] || recommendation;

function AssignmentCard({ assignment, t, refusals }) {
    const [comment, setComment] = useState('');
    const item = assignment.item;
    // The buttons post without a form; a refusal — a round that closed, a
    // paper back with its writer — is said on the card it came from.
    const row = `assignment:${assignment.id}`;

    const submit = (recommendation) => refusals.actOn(row, () => router.post(`/review/${assignment.id}`, { recommendation, comment: comment || undefined }, { preserveScroll: true }));
    const declare = () => refusals.actOn(row, () => router.post(`/review/${assignment.id}/declare`, {}, { preserveScroll: true }));

    return (
        <div className="mb-4 rounded-lg border bg-white p-4" data-testid="review-assignment">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 className="text-lg font-semibold">{item?.title}</h2>
                    <p className="text-xs text-gray-500" data-testid="review-meta">
                        {fill(t.review_assigned || 'Assigned :date', { date: assignment.assigned_at })}
                        {' · '}
                        {fill(t.review_round || 'Round :round', { round: assignment.round })}
                        {' · '}
                        {fill(t.review_item_status || 'item :status', { status: t[`library_status_${item?.status}`] || item?.status?.replaceAll('_', ' ') })}
                        {assignment.recommendation ? ` · ${fill(t.review_your_recommendation || 'your recommendation: :recommendation', { recommendation: recommendationLabel(t, assignment.recommendation) })}` : ''}
                    </p>
                    {/* R3b: when the report is due, and whether it is late. */}
                    {assignment.due_on && assignment.status === 'assigned' && (
                        <p className={`text-xs ${assignment.overdue ? 'font-semibold text-red-700' : 'text-gray-600'}`} data-testid="review-due">
                            {fill(assignment.overdue ? (t.review_overdue || 'Overdue — it was due on :date') : (t.review_due || 'Due on :date'), { date: assignment.due_on })}
                        </p>
                    )}
                </div>
            </div>

            {/* R3b: the conflict-of-interest step. The paper stays closed until it is declared. */}
            {!assignment.coi_declared ? (
                <div className="rounded border border-amber-200 bg-amber-50 p-3 text-sm" data-testid="review-coi">
                    <p className="mb-2">{t.review_coi_text || 'Before you read it: do you have a conflict of interest with this paper — a personal, professional or financial tie to it or its authors? If you do, tell the office and do not review it.'}</p>
                    <button type="button" className="btn-primary" onClick={declare} data-testid="review-coi-declare">{t.review_coi_declare || 'I have no conflict of interest'}</button>
                </div>
            ) : (
                <>
                    {assignment.revision_note && (
                        <div className="mb-2 rounded border-s-4 border-blue-300 bg-blue-50 p-2 text-sm" data-testid="review-revision-note">
                            <p className="text-xs font-semibold text-blue-900">{t.review_revision_note || 'The writer says what changed'}</p>
                            <p>{assignment.revision_note}</p>
                        </div>
                    )}
                    {(assignment.my_reports || []).length > 0 && (
                        <div className="mb-2 text-xs text-gray-600" data-testid="review-my-reports">
                            <p className="font-semibold">{t.review_my_reports || 'Your earlier reports on this paper'}</p>
                            {assignment.my_reports.map((report, index) => (
                                <p key={index}>{report.at} · {recommendationLabel(t, report.recommendation)}{report.comment ? ` — ${report.comment}` : ''}</p>
                            ))}
                        </div>
                    )}
                    {item?.abstract && <p className="mb-2 text-sm text-gray-700">{item.abstract}</p>}
                    {item?.body && (
                        <div className="mb-2 max-h-96 overflow-y-auto rounded border bg-gray-50 p-3 text-sm" dangerouslySetInnerHTML={{ __html: item.body }} />
                    )}
                    {item?.citations && (
                        <div className="mb-2 text-xs text-gray-600">
                            <p className="font-semibold">{t.review_citations || 'Citations'}</p>
                            <pre className="whitespace-pre-wrap font-sans">{item.citations}</pre>
                        </div>
                    )}
                    <textarea
                        className="form-input mb-2 w-full"
                        rows="3"
                        placeholder={t.review_comment_placeholder || 'Review comments for the writer'} aria-label={t.review_comment_placeholder || 'Review comments for the writer'}
                        value={comment}
                        onChange={(e) => setComment(e.target.value)}
                    />
                    <div className="flex flex-wrap gap-2">
                        <button type="button" className="btn-primary" onClick={() => submit('accept')}>{t.review_accept || 'Recommend accept'}</button>
                        <button type="button" className="btn-secondary" onClick={() => submit('revise')}>{t.review_revise || 'Needs revision'}</button>
                        <button type="button" className="text-sm text-red-600" onClick={() => submit('reject')}>{t.review_reject || 'Recommend reject'}</button>
                    </div>
                </>
            )}
            <FormErrors errors={refusals.errorsFor(row)} className="mt-2" />
        </div>
    );
}

export default function Review({ assignments }) {
    const { i18n } = usePage().props;
    const t = i18n?.common || {};
    const refusals = useRowRefusals();

    return (
        <AppShell title={t.review_title || 'Peer review'}>
            {/* What came back before any card was acted on. */}
            <FormErrors errors={refusals.unplaced} className="mb-4" />
            {assignments.length === 0 && <p className="text-gray-500">{t.review_empty || 'No review assignments.'}</p>}
            {assignments.map((assignment) => <AssignmentCard key={assignment.id} assignment={assignment} t={t} refusals={refusals} />)}
        </AppShell>
    );
}
