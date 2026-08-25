import { useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function StaffCheckIn({ enabled, staff, rows }) {
    const form = useForm({});

    return (
        <AppShell title="Staff check-in">
            {!staff && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">No staff profile is linked to this account.</p>
            )}
            {staff && (
                <section className="mb-4 rounded-lg border bg-white p-4">
                    <p className="text-sm"><strong>{staff.name}</strong>{staff.department ? ` · ${staff.department}` : ''}</p>
                    {enabled ? (
                        <form
                            className="mt-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/portal/staff-check-in');
                            }}
                        >
                            <button type="submit" className="btn-primary" disabled={form.processing}>Check in today</button>
                            {form.errors.check_in && <p className="mt-2 text-xs text-red-600">{form.errors.check_in}</p>}
                        </form>
                    ) : (
                        <p className="mt-2 text-sm text-gray-600">Self check-in is turned off.</p>
                    )}
                </section>
            )}
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Source</th>
                            <th className="px-3 py-2">Check in</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No attendance recorded yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.date}</td>
                                <td className="px-3 py-2 uppercase">{row.status}</td>
                                <td className="px-3 py-2">{row.source}</td>
                                <td className="px-3 py-2">{row.check_in || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
