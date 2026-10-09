import { useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function StaffCheckIn({ enabled, staff, rows, t = {} }) {
    const form = useForm({});

    // In the page's language (BACKLOG C21, slice PT4); a day's state and how
    // it was recorded are codes, named here.
    return (
        <AppShell title={t.checkin_title || 'Staff check-in'}>
            {!staff && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">{t.staff_no_profile || 'No staff profile is linked to this account.'}</p>
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
                            <button type="submit" className="btn-primary" disabled={form.processing}>{t.checkin_today || 'Check in today'}</button>
                            {form.errors.check_in && <p className="mt-2 text-xs text-red-600">{form.errors.check_in}</p>}
                        </form>
                    ) : (
                        <p className="mt-2 text-sm text-gray-600">{t.checkin_off || 'Self check-in is turned off.'}</p>
                    )}
                </section>
            )}
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_date || 'Date'}</th>
                            <th className="px-3 py-2">{t.col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.col_source || 'Source'}</th>
                            <th className="px-3 py-2">{t.checkin_col_time || 'Check in'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.checkin_none || 'No attendance recorded yet.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.date}</td>
                                <td className="px-3 py-2">{t[`staff_attendance_${row.status}`] || row.status}</td>
                                <td className="px-3 py-2">{t[`staff_attendance_source_${row.source}`] || row.source}</td>
                                <td className="px-3 py-2">{row.check_in || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
