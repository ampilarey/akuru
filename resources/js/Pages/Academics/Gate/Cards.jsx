import { Link, router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * E18 gate cards, per class (owner decision 11): who has a card, issue the
 * missing ones, replace a lost one, print the sheet.
 */
export default function Cards({ classes = [], class_id: classId = 0, pupils = [] }) {
    const withCard = pupils.filter((p) => p.card).length;

    return (
        <AppShell title="Gate cards">
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <label className="text-sm">
                    <span className="me-2">Class</span>
                    <select
                        className="form-input inline-block w-auto"
                        value={classId}
                        onChange={(e) => router.get('/academics/gate/cards', { class_id: e.target.value }, { preserveState: true, replace: true })}
                    >
                        {classes.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </label>
                <span className="text-sm text-gray-600">{withCard} of {pupils.length} have a card</span>
                <button
                    type="button"
                    className="btn-primary"
                    disabled={pupils.length === 0 || withCard === pupils.length}
                    onClick={() => router.post('/academics/gate/cards/issue', { class_id: classId }, { preserveScroll: true })}
                >
                    Issue missing cards
                </button>
                {withCard > 0 && (
                    <a className="btn-secondary" href={`/academics/gate/cards/print?class_id=${classId}`} target="_blank" rel="noreferrer">
                        Print cards
                    </a>
                )}
                {pupils.length > 0 && (
                    <a className="text-sm text-[#7C2D37] underline" href={`/academics/gate/cards/export?class_id=${classId}`}>Export CSV</a>
                )}
                <Link href="/academics/gate" className="text-sm text-[#7C2D37] underline">Back to the gate</Link>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className="px-3 py-2 text-start">Pupil</th>
                            <th className="px-3 py-2 text-start">Card code</th>
                            <th className="px-3 py-2 text-start">Issued</th>
                            <th className="px-3 py-2 text-start" />
                        </tr>
                    </thead>
                    <tbody>
                        {pupils.map((p) => (
                            <tr key={p.student_id} className="border-t">
                                <td className="px-3 py-2">
                                    {p.name}
                                    {p.student_number && <span className="ms-2 text-xs text-gray-500">{p.student_number}</span>}
                                </td>
                                <td className="px-3 py-2 font-mono text-xs" dir="ltr">{p.card ? p.card.readable : <span className="text-gray-400">no card</span>}</td>
                                <td className="px-3 py-2 text-xs text-gray-600">{p.card?.issued_at ?? '—'}</td>
                                <td className="px-3 py-2">
                                    {p.card && (
                                        <button
                                            type="button"
                                            className="text-xs text-[#7C2D37] underline"
                                            onClick={() => {
                                                if (window.confirm(`Replace ${p.name}'s card? The old one stops working at once.`)) {
                                                    router.post(`/academics/gate/cards/${p.student_id}/reissue`, {}, { preserveScroll: true });
                                                }
                                            }}
                                        >
                                            Card lost — replace
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {pupils.length === 0 && <p className="px-4 py-6 text-center text-sm text-gray-500">No pupils on this class's roll.</p>}
            </div>

            <p className="mt-4 text-xs text-gray-500">
                A card carries a random code, not the pupil's number, so nobody can make one for someone else.
                Replacing a lost card stops the old one immediately; the gate says so if it is used.
            </p>
        </AppShell>
    );
}
