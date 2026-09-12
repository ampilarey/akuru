import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ rows, courses, modes }) {
    const t = usePage().props.i18n?.learn || {};
    const form = useForm({
        course_id: courses[0]?.id || '',
        title: '',
        delivery_mode: modes[0] || 'self_learning',
        status: 'draft',
        pin_mode: 'latest',
        seat_limit: '',
        price_override: '',
    });

    return (
        <AppShell title={t.offerings || 'Offerings'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/offerings/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/catalog/offerings', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-7"
            >
                <select className="form-input" value={form.data.course_id} onChange={(e) => form.setData('course_id', e.target.value)}>
                    {courses.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}
                </select>
                <input className="form-input" placeholder="Offering title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <select className="form-input" value={form.data.delivery_mode} onChange={(e) => form.setData('delivery_mode', e.target.value)}>
                    {modes.map((mode) => <option key={mode} value={mode}>{mode}</option>)}
                </select>
                <select className="form-input" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    <option value="draft">draft</option>
                    <option value="open">open</option>
                    <option value="closed">closed</option>
                    <option value="archived">archived</option>
                </select>
                <input className="form-input" placeholder="Seat limit" value={form.data.seat_limit} onChange={(e) => form.setData('seat_limit', e.target.value)} />
                <input className="form-input" placeholder="Price override (MVR)" value={form.data.price_override} onChange={(e) => form.setData('price_override', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing || courses.length === 0}>Save offering</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Course</th>
                            <th className="px-3 py-2">Mode</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Price</th>
                            <th className="px-3 py-2">Pin</th>
                            <th className="px-3 py-2">Sessions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={7}>No offerings yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.title}</td>
                                <td className="px-3 py-2">{row.course_title}</td>
                                <td className="px-3 py-2">{row.delivery_mode}</td>
                                <td className="px-3 py-2">{row.status}</td>
                                <td className="px-3 py-2">{row.price_override !== null && row.price_override !== undefined ? `MVR ${row.price_override}` : '—'}</td>
                                <td className="px-3 py-2">
                                    <span className="me-2">{row.pin_mode}</span>
                                    {/* SPEC §28.4: re-pinning changes what enrolled
                                        students see mid-offering, so it must be
                                        deliberate and it must record why. The reason
                                        is nullable in the spec, so an empty answer
                                        still pins — but it is asked for, and it was
                                        not captured at all before. */}
                                    <button
                                        type="button"
                                        className="btn-secondary"
                                        onClick={() => {
                                            const reason = window.prompt(
                                                `Re-pin "${row.title}" to the current published revisions?\n\nEnrolled students will see the new content. Reason (optional):`,
                                                '',
                                            );
                                            if (reason === null) {
                                                return;
                                            }
                                            router.post(`/catalog/offerings/${row.id}/pin`, { reason }, { preserveScroll: true });
                                        }}
                                    >
                                        Pin now
                                    </button>
                                    {(row.repin_events || []).length > 0 && (
                                        <details className="mt-1 text-xs text-gray-600">
                                            <summary className="cursor-pointer">
                                                {row.repin_events.length} re-pin{row.repin_events.length === 1 ? '' : 's'}
                                            </summary>
                                            <ul className="mt-1 space-y-1">
                                                {row.repin_events.map((event) => (
                                                    <li key={event.id}>
                                                        <span className="font-medium">{(event.changed_at || '').slice(0, 10)}</span>
                                                        {event.changed_by ? ` · ${event.changed_by}` : ' · unknown admin'}
                                                        {` · ${event.old_pin_mode || 'unset'} → ${event.new_pin_mode}`}
                                                        {` · ${event.changed_lessons.length} lesson${event.changed_lessons.length === 1 ? '' : 's'} changed`}
                                                        {event.reason ? ` · ${event.reason}` : ''}
                                                    </li>
                                                ))}
                                            </ul>
                                        </details>
                                    )}
                                </td>
                                <td className="px-3 py-2">
                                    <a className="text-[#7C2D37] hover:underline" href={`/catalog/offerings/${row.id}/sessions`}>{t.sessions || 'Sessions'}</a>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
