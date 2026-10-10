import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

function Row({ notice, t }) {
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
                {notice.sent_at && <span className="block">{(t.pickup_sent_at || 'sent :time').replace(':time', notice.sent_at.slice(11, 16))}</span>}
                {notice.collected_at && <span className="block">{(t.pickup_gone_at || 'gone :time').replace(':time', notice.collected_at.slice(11, 16))}</span>}
            </td>
            <td className="px-3 py-2">
                {notice.status === 'requested' && (
                    <div className="flex flex-wrap gap-2">
                        <button
                            className="btn-primary text-xs"
                            onClick={() => router.post(`/academics/pickup/${notice.id}/send`, {}, { preserveScroll: true })}
                        >
                            {t.pickup_send || 'Send to reception'}
                        </button>
                        <button
                            className="text-xs text-[#7C2D37] underline"
                            onClick={() => router.post(`/academics/pickup/${notice.id}/cancel`, {}, { preserveScroll: true })}
                        >
                            {t.cancel || 'Cancel'}
                        </button>
                    </div>
                )}
                {notice.status === 'sent' && (
                    <span className="rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-800">
                        {t.pickup_at_reception || 'At reception — waiting for the adult to confirm'}
                    </span>
                )}
            </td>
        </tr>
    );
}

/**
 * The office's pick-up console. Every word is the `academics` book's (slice
 * OA4, STATUS §5qi), and a send or cancel the server refuses — another member
 * of staff got there first — is said here; it used to vanish.
 */
export default function Console({ date, is_open, waiting = [], left = [], t = {} }) {
    const form = useForm({ date });
    const { errors = {} } = usePage().props;

    return (
        <AppShell title={t.pickup_title || 'Student pick-up'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href={`/academics/pickup/export?date=${form.data.date}`}>{t.export_csv || 'Export CSV'}</a>
            </div>
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <input
                    type="date"
                    className="form-input text-sm"
                    aria-label={t.date || 'Date'}
                    value={form.data.date}
                    onChange={(e) => {
                        form.setData('date', e.target.value);
                        router.get('/academics/pickup', { date: e.target.value }, { preserveState: true, replace: true });
                    }}
                />
                {is_open ? (
                    <>
                        <span className="rounded bg-emerald-50 px-2 py-0.5 text-sm text-emerald-800">{t.pickup_is_open || 'Pick-up is open'}</span>
                        <button className="btn-secondary text-sm"
                            onClick={() => router.post('/academics/pickup/close', { date: form.data.date }, { preserveScroll: true })}>
                            {t.pickup_close || 'Close pick-up'}
                        </button>
                    </>
                ) : (
                    <>
                        <span className="rounded bg-gray-100 px-2 py-0.5 text-sm text-gray-700">{t.pickup_is_closed || 'Pick-up is closed'}</span>
                        <button className="btn-primary text-sm"
                            onClick={() => router.post('/academics/pickup/open', { date: form.data.date }, { preserveScroll: true })}>
                            {t.pickup_open || 'Open pick-up'}
                        </button>
                    </>
                )}
            </div>

            {errors.pickup && (
                <p className="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">{errors.pickup}</p>
            )}

            <p className="mb-4 text-sm text-gray-600">
                {t.pickup_intro || 'Families can only ask while pick-up is open, and only for a child they are listed as allowed to collect. Their PIN is checked before you see the request.'}
            </p>

            <h2 className="mb-2 text-sm font-semibold">{(t.pickup_waiting || 'Waiting for departure (:count)').replace(':count', waiting.length)}</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className="px-3 py-2 text-start">{t.pickup_col_child || 'Child'}</th>
                            <th className="px-3 py-2 text-start">{t.pickup_col_adult || 'Adult'}</th>
                            <th className="px-3 py-2 text-start">{t.pickup_col_times || 'Times'}</th>
                            <th className="px-3 py-2 text-start" />
                        </tr>
                    </thead>
                    <tbody>
                        {waiting.map((n) => <Row key={n.id} notice={n} t={t} />)}
                    </tbody>
                </table>
                {waiting.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">{t.pickup_nobody_waiting || 'Nobody is waiting.'}</p>
                )}
            </div>

            <h2 className="mb-2 text-sm font-semibold">{(t.pickup_left || 'Left (:count)').replace(':count', left.length)}</h2>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <tbody>
                        {left.map((n) => (
                            <tr key={n.id} className="border-t">
                                <td className="px-3 py-2">{n.student}</td>
                                <td className="px-3 py-2 text-gray-600">{(t.pickup_with || 'with :name').replace(':name', n.guardian)}</td>
                                <td className="px-3 py-2 text-xs text-gray-500">{n.collected_at?.slice(11, 16)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {left.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">{t.pickup_nobody_left || 'Nobody has left yet.'}</p>
                )}
            </div>
        </AppShell>
    );
}
