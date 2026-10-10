import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * Staff attendance for a day. Every word is the `hr` book's (slice HR1,
 * STATUS §5ql); a day's state and how it was recorded are named rather than
 * printed as codes (*ON_LEAVE*, *manual*). A refused day, fill or import is
 * said under the form that asked — filling the holidays said nothing.
 */
export default function Index({ date, academicYearId, staff, rows, statuses, t = {} }) {
    const statusName = (status) => t[`attendance_status_${status}`] || status;
    const sourceName = (source) => t[`attendance_source_${source}`] || source;
    const form = useForm({
        staff_profile_id: staff[0]?.id || '',
        date,
        status: 'present',
        minutes_late: '',
        remarks: '',
    });

    const holidayForm = useForm({
        academic_year_id: academicYearId || '',
        date,
    });

    const importForm = useForm({ file: null });
    const refused = form.errors.staff_profile_id || form.errors.status || form.errors.date || form.errors.minutes_late;
    const holidayRefused = Object.values(holidayForm.errors)[0];
    // A row refused for its month's locked payroll comes back on `date`, not
    // `file`, so the import says whichever it was given.
    const importRefused = importForm.errors.file || Object.values(importForm.errors)[0];

    return (
        <AppShell title={t.attendance_title || 'Staff attendance'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/hr/attendance', { date: form.data.date });
                    }}
                    className="flex flex-wrap items-center gap-3"
                >
                    <input
                        type="date"
                        className="form-input"
                        aria-label={t.date || 'Date'}
                        value={form.data.date}
                        onChange={(e) => form.setData('date', e.target.value)}
                    />
                    <button type="submit" className="btn-secondary">{t.attendance_load || 'Load date'}</button>
                </form>
                <div className="flex flex-wrap gap-3">
                    <a className="btn-secondary" href={`/hr/attendance/export?date=${date}`}>{t.export_csv || 'Export CSV'}</a>
                    <a className="btn-secondary" href="/hr/attendance/reports">{t.attendance_reports || 'Reports'}</a>
                </div>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/hr/attendance', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-5"
            >
                <select className="form-input" aria-label={t.staff_member || 'Staff member'} value={form.data.staff_profile_id} onChange={(e) => form.setData('staff_profile_id', e.target.value)}>
                    {staff.map((row) => (
                        <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>
                    ))}
                </select>
                <select className="form-input" aria-label={t.status || 'Status'} value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    {statuses.map((status) => <option key={status} value={status}>{statusName(status)}</option>)}
                </select>
                <input className="form-input" aria-label={t.attendance_late_minutes || 'Late minutes'} placeholder={t.attendance_late_minutes || 'Late minutes'} value={form.data.minutes_late} onChange={(e) => form.setData('minutes_late', e.target.value)} />
                <input className="form-input" aria-label={t.remarks || 'Remarks'} placeholder={t.remarks || 'Remarks'} value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.attendance_mark || 'Mark attendance'}</button>
                {refused && <span className="text-xs text-red-600 md:col-span-5">{refused}</span>}
            </form>

            <div className="mb-4 grid gap-3 md:grid-cols-2">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        holidayForm.post('/hr/attendance/holidays', { preserveScroll: true });
                    }}
                    className="rounded-lg border bg-white p-4"
                >
                    <p className="mb-2 text-sm font-medium">{t.attendance_fill_holidays_title || 'Fill holidays from calendar'}</p>
                    <button type="submit" className="btn-secondary" disabled={!academicYearId || holidayForm.processing}>{t.attendance_fill_holidays || 'Fill holidays'}</button>
                    {holidayRefused && <p className="mt-2 text-xs text-red-600">{holidayRefused}</p>}
                </form>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        importForm.post('/hr/attendance/import', { forceFormData: true, preserveScroll: true });
                    }}
                    className="rounded-lg border bg-white p-4"
                >
                    <p className="mb-2 text-sm font-medium">{t.attendance_import_title || 'Import CSV'}</p>
                    <input type="file" accept=".csv,text/csv" className="form-input mb-2" aria-label={t.attendance_file || 'CSV file'} onChange={(e) => importForm.setData('file', e.target.files?.[0] || null)} />
                    <button type="submit" className="btn-secondary" disabled={importForm.processing}>{t.attendance_import || 'Import'}</button>
                    {importRefused && <p className="mt-2 text-xs text-red-600">{importRefused}</p>}
                </form>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.staff || 'Staff'}</th>
                            <th className="px-3 py-2">{t.department || 'Department'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                            <th className="px-3 py-2">{t.attendance_col_source || 'Source'}</th>
                            <th className="px-3 py-2">{t.attendance_col_late || 'Late'}</th>
                            <th className="px-3 py-2">{t.remarks || 'Remarks'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.attendance_none || 'No attendance for this date.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.staff_name}</td>
                                <td className="px-3 py-2">{row.department || '—'}</td>
                                <td className="px-3 py-2">{statusName(row.status)}</td>
                                <td className="px-3 py-2">{sourceName(row.source)}</td>
                                <td className="px-3 py-2">{row.minutes_late ?? '—'}</td>
                                <td className="px-3 py-2">{row.remarks || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
