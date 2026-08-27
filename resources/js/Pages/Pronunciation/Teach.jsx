import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

function ReviewRow({ attempt, letters, harakas }) {
    const [letterId, setLetterId] = useState(attempt.expected_letter_id);
    const [harakaId, setHarakaId] = useState(attempt.expected_haraka_id);
    const [notes, setNotes] = useState('');

    const decide = (reject) =>
        router.post(`/teach/pronunciation/${attempt.id}/review`, reject
            ? { reject: 1, rejection_reason: notes || 'Unclear audio' }
            : { verified_letter_id: letterId, verified_haraka_id: harakaId, notes: notes || undefined },
        { preserveScroll: true });

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-2">
                <p className="font-medium">{attempt.expected_letter} + {attempt.expected_haraka}</p>
                <p className="text-xs text-gray-500">{attempt.submitted_at} · {attempt.status?.replaceAll('_', ' ')}</p>
                {attempt.has_audio && (
                    <p className="text-xs text-gray-400">audio #{attempt.audio_media_file_id}</p>
                )}
            </td>
            <td className="px-3 py-2 text-xs text-gray-600">
                {attempt.ai ? (
                    <>
                        <p>{attempt.ai.letter} + {attempt.ai.haraka}</p>
                        <p>conf {attempt.ai.letter_confidence} / {attempt.ai.haraka_confidence}</p>
                        <p>{attempt.ai.final_status?.replaceAll('_', ' ')}</p>
                    </>
                ) : '—'}
            </td>
            <td className="px-3 py-2">
                <div className="mb-2 flex gap-2">
                    <select className="form-input" value={letterId} onChange={(e) => setLetterId(e.target.value)}>
                        {letters.map((row) => <option key={row.id} value={row.id}>{row.char} {row.key_name}</option>)}
                    </select>
                    <select className="form-input" value={harakaId} onChange={(e) => setHarakaId(e.target.value)}>
                        {harakas.map((row) => <option key={row.id} value={row.id}>{row.symbol} {row.key_name}</option>)}
                    </select>
                </div>
                <input className="form-input mb-2 w-full" placeholder="Notes / rejection reason" value={notes} onChange={(e) => setNotes(e.target.value)} />
                <div className="flex gap-2">
                    <button type="button" className="btn-primary" onClick={() => decide(false)}>Confirm as selected</button>
                    <button type="button" className="text-sm text-red-600" onClick={() => decide(true)}>Reject audio</button>
                </div>
            </td>
        </tr>
    );
}

export default function Teach({ review_queue: reviewQueue, letters, harakas, ai_enabled: aiEnabled }) {
    const flash = usePage().props.flash || {};

    return (
        <AppShell title="Pronunciation review">
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700">{flash.success}</p>}
            {!aiEnabled && <p className="mb-4 rounded bg-amber-50 p-3 text-sm text-amber-800">AI checking is off — every attempt lands here for a human ear.</p>}

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Attempt (expected)</th>
                            <th className="px-3 py-2">AI opinion</th>
                            <th className="px-3 py-2">Your verdict (verified letter + haraka)</th>
                        </tr>
                    </thead>
                    <tbody>
                        {reviewQueue.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={3}>Nothing waiting for review.</td></tr>
                        )}
                        {reviewQueue.map((attempt) => (
                            <ReviewRow key={attempt.id} attempt={attempt} letters={letters} harakas={harakas} />
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
