import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

function Row({ notice }) {
    return (
        <tr className="border-t align-top">
            <td className="px-3 py-2">
                <p className="font-medium">{notice.student}</p>
                {notice.student_number && <p className="text-xs text-gray-500">{notice.student_number}</p>}
            </td>
            <td className="px-3 py-2">
                {notice.guardian}
                {notice.note && <p className="text-xs text-gray-600">“{notice.note}”</p>}
            </td>
            <td className="px-3 py-2 text-xs text-gray-600">
                {notice.requested_at?.slice(11, 16)}
                {notice.sent_at && <span className="block">sent {notice.sent_at.slice(11, 16)}</span>}
                {notice.collected_at && <span className="block">gone {notice.collected_at.slice(11, 16)}</span>}
            </td>
            <td className="px-3 py-2">
                {notice.status === 'requested' && (
                    <div className="flex flex-wrap gap-2">
                        <button
                            className="btn-primary text-xs"
                            onClick={() => router.post(`/academics/pickup/${notice.id}/send`, {}, { preserveScroll: true })}
                        >
                            Send to reception
                        </button>
                        <button
                            className="text-xs text-[#7C2D37] underline"
                            onClick={() => router.post(`/academics/pickup/${notice.id}/cancel`, {}, { preserveScroll: true })}
                        >
                            Cancel
                        </button>
                    </div>
                )}
                {notice.status === 'sent' && (
                    <span className="rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-800">
                        At reception — waiting for the adult to confirm
                    </span>
                )}
            </td>
        </tr>
    );
}

export default function Console({ date, is_open, waiting = [], left = [] }) {
    const form = useForm({ date });

    return (
        <AppShell title="Student pick-up">
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <input
                    type="date"
                    className="form-input text-sm"
                    value={form.data.date}
                    onChange={(e) => {
                        form.setData('date', e.target.value);
                        router.get('/academics/pickup', { date: e.target.value }, { preserveState: true, replace: true });
                    }}
                />
                {is_open ? (
                    <>
                        <span className="rounded bg-emerald-50 px-2 py-0.5 text-sm text-emerald-800">Pick-up is open</span>
                        <button className="btn-secondary text-sm"
                            onClick={() => router.post('/academics/pickup/close', { date: form.data.date }, { preserveScroll: true })}>
                            Close pick-up
                        </button>
                    </>
                ) : (
                    <>
                        <span className="rounded bg-gray-100 px-2 py-0.5 text-sm text-gray-700">Pick-up is closed</span>
                        <button className="btn-primary text-sm"
                            onClick={() => router.post('/academics/pickup/open', { date: form.data.date }, { preserveScroll: true })}>
                            Open pick-up
                        </button>
                    </>
                )}
            </div>

            <p className="mb-4 text-sm text-gray-600">
                Families can only ask while pick-up is open, and only for a child they are listed
                as allowed to collect. Their PIN is checked before you see the request.
            </p>

            <h2 className="mb-2 text-sm font-semibold">Waiting for departure ({waiting.length})</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className="px-3 py-2 text-start">Child</th>
                            <th className="px-3 py-2 text-start">Adult</th>
                            <th className="px-3 py-2 text-start">Times</th>
                            <th className="px-3 py-2 text-start" />
                        </tr>
                    </thead>
                    <tbody>
                        {waiting.map((n) => <Row key={n.id} notice={n} />)}
                    </tbody>
                </table>
                {waiting.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">Nobody is waiting.</p>
                )}
            </div>

            <h2 className="mb-2 text-sm font-semibold">Left ({left.length})</h2>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <tbody>
                        {left.map((n) => (
                            <tr key={n.id} className="border-t">
                                <td className="px-3 py-2">{n.student}</td>
                                <td className="px-3 py-2 text-gray-600">with {n.guardian}</td>
                                <td className="px-3 py-2 text-xs text-gray-500">{n.collected_at?.slice(11, 16)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {left.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">Nobody has left yet.</p>
                )}
            </div>
        </AppShell>
    );
}
