import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ date, academicYearId, staff, rows, statuses }) {
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

    return (
        <AppShell title="Staff attendance">
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
                        value={form.data.date}
                        onChange={(e) => form.setData('date', e.target.value)}
                    />
                    <button type="submit" className="btn-secondary">Load date</button>
                </form>
                <div className="flex flex-wrap gap-3">
                    <a className="btn-secondary" href={`/hr/attendance/export?date=${date}`}>Export CSV</a>
                    <a className="btn-secondary" href="/hr/attendance/reports">Reports</a>
                </div>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/hr/attendance', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-5"
            >
                <select className="form-input" value={form.data.staff_profile_id} onChange={(e) => form.setData('staff_profile_id', e.target.value)}>
                    {staff.map((row) => (
                        <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>
                    ))}
                </select>
                <select className="form-input" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                </select>
                <input className="form-input" placeholder="Late minutes" value={form.data.minutes_late} onChange={(e) => form.setData('minutes_late', e.target.value)} />
                <input className="form-input" placeholder="Remarks" value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Mark attendance</button>
                {form.errors.staff_profile_id && <span className="text-xs text-red-600">{form.errors.staff_profile_id}</span>}
                {form.errors.status && <span className="text-xs text-red-600">{form.errors.status}</span>}
            </form>

            <div className="mb-4 grid gap-3 md:grid-cols-2">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        holidayForm.post('/hr/attendance/holidays', { preserveScroll: true });
                    }}
                    className="rounded-lg border bg-white p-4"
                >
                    <p className="mb-2 text-sm font-medium">Fill holidays from calendar</p>
                    <button type="submit" className="btn-secondary" disabled={!academicYearId || holidayForm.processing}>Fill holidays</button>
                </form>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        importForm.post('/hr/attendance/import', { forceFormData: true, preserveScroll: true });
                    }}
                    className="rounded-lg border bg-white p-4"
                >
                    <p className="mb-2 text-sm font-medium">Import CSV</p>
                    <input type="file" accept=".csv,text/csv" className="form-input mb-2" onChange={(e) => importForm.setData('file', e.target.files?.[0] || null)} />
                    <button type="submit" className="btn-secondary" disabled={importForm.processing}>Import</button>
                    {importForm.errors.file && <p className="mt-2 text-xs text-red-600">{importForm.errors.file}</p>}
                </form>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Staff</th>
                            <th className="px-3 py-2">Department</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Source</th>
                            <th className="px-3 py-2">Late</th>
                            <th className="px-3 py-2">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>No attendance for this date.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.staff_name}</td>
                                <td className="px-3 py-2">{row.department || '—'}</td>
                                <td className="px-3 py-2 uppercase">{row.status}</td>
                                <td className="px-3 py-2">{row.source}</td>
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
