import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

function ReviewRow({ attempt, letters, harakas, refusals, t }) {
    const [letterId, setLetterId] = useState(attempt.expected_letter_id);
    const [harakaId, setHarakaId] = useState(attempt.expected_haraka_id);
    const [notes, setNotes] = useState('');
    const row = `attempt:${attempt.id}`;

    const decide = (reject) => refusals.actOn(row, () => router.post(`/teach/pronunciation/${attempt.id}/review`, reject
        ? { reject: 1, rejection_reason: notes || t.pron_unclear_audio || 'Unclear audio' }
        : { verified_letter_id: letterId, verified_haraka_id: harakaId, notes: notes || undefined },
    { preserveScroll: true }));
    const confidence = attempt.ai ? (t.pron_confidence || 'confidence :letter / :haraka')
        .replace(':letter', attempt.ai.letter_confidence ?? '—')
        .replace(':haraka', attempt.ai.haraka_confidence ?? '—') : null;

    return (
        <tr className="border-t align-top" data-testid={`pron-attempt-${attempt.id}`}>
            <td className="px-3 py-2">
                <p className="font-medium">{attempt.expected_letter} + {attempt.expected_haraka}</p>
                <p className="text-xs text-gray-500">{attempt.submitted_at} · {t[`pron_attempt_status_${attempt.status}`] || attempt.status?.replaceAll('_', ' ')}</p>
                {attempt.has_audio && (
                    <p className="text-xs text-gray-400">{(t.pron_audio || 'audio #:id').replace(':id', attempt.audio_media_file_id)}</p>
                )}
            </td>
            <td className="px-3 py-2 text-xs text-gray-600">
                {attempt.ai ? (
                    <>
                        <p>{attempt.ai.letter} + {attempt.ai.haraka}</p>
                        <p>{confidence}</p>
                        <p>{t[`pron_ai_status_${attempt.ai.final_status}`] || attempt.ai.final_status?.replaceAll('_', ' ')}</p>
                    </>
                ) : '—'}
            </td>
            <td className="px-3 py-2">
                <div className="mb-2 flex gap-2">
                    <select className="form-input" aria-label={t.pron_verified_letter || 'Verified letter'} value={letterId} onChange={(e) => setLetterId(e.target.value)}>
                        {letters.map((option) => <option key={option.id} value={option.id}>{option.char} {option.key_name}</option>)}
                    </select>
                    <select className="form-input" aria-label={t.pron_verified_haraka || 'Verified haraka'} value={harakaId} onChange={(e) => setHarakaId(e.target.value)}>
                        {harakas.map((option) => <option key={option.id} value={option.id}>{option.symbol} {option.key_name}</option>)}
                    </select>
                </div>
                <input className="form-input mb-2 w-full" placeholder={t.pron_notes || 'Notes / rejection reason'} aria-label={t.pron_notes || 'Notes / rejection reason'} value={notes} onChange={(e) => setNotes(e.target.value)} />
                <div className="flex gap-2">
                    <button type="button" className="btn-primary" onClick={() => decide(false)}>{t.pron_confirm || 'Confirm as selected'}</button>
                    <button type="button" className="text-sm text-red-600" onClick={() => decide(true)}>{t.pron_reject_audio || 'Reject audio'}</button>
                </div>
                <FormErrors errors={refusals.errorsFor(row)} className="mt-1" />
            </td>
        </tr>
    );
}

export default function Teach({ review_queue: reviewQueue, letters, harakas, ai_enabled: aiEnabled, t = {} }) {
    // A verdict posts with `router`, so its refusal comes back as the page's
    // errors with no form to own it; the attempt it was given on says it
    // (slice CT6b-2b).
    const refusals = useRowRefusals();

    return (
        <AppShell title={t.pron_review_title || 'Pronunciation review'}>
            {!aiEnabled && <p className="mb-4 rounded bg-amber-50 p-3 text-sm text-amber-800">{t.pron_review_ai_off || 'AI checking is off — every attempt lands here for a human ear.'}</p>}
            <FormErrors errors={refusals.unplaced} className="mb-4" />

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.pron_attempt_expected || 'Attempt (expected)'}</th>
                            <th className="px-3 py-2">{t.pron_ai_opinion || 'AI opinion'}</th>
                            <th className="px-3 py-2">{t.pron_your_verdict || 'Your verdict (verified letter + haraka)'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {reviewQueue.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={3}>{t.pron_review_empty || 'Nothing waiting for review.'}</td></tr>
                        )}
                        {reviewQueue.map((attempt) => (
                            <ReviewRow key={attempt.id} attempt={attempt} letters={letters} harakas={harakas} refusals={refusals} t={t} />
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
