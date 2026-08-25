import { router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ filters, years, classes, statuses, rows, chronic, unexcused }) {
    const query = new URLSearchParams(
        Object.fromEntries(Object.entries(filters).filter(([, value]) => value)),
    ).toString();

    return (
        <AppShell title="Attendance reports">
            <div className="mb-4 flex flex-wrap items-end gap-2">
                <select className="form-input" value={filters.academic_year_id || ''} onChange={(e) => router.get(`/academics/attendance?academic_year_id=${e.target.value}`)}>
                    <option value="">Year</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <select className="form-input" value={filters.class_id || ''} onChange={(e) => router.get(`/academics/attendance?academic_year_id=${filters.academic_year_id || ''}&class_id=${e.target.value}`)}>
                    <option value="">Class</option>
                    {classes.map((item) => <option key={item.id} value={item.id}>{item.name} {item.section}</option>)}
                </select>
                <select className="form-input" value={filters.status || ''} onChange={(e) => router.get(`/academics/attendance?academic_year_id=${filters.academic_year_id || ''}&class_id=${filters.class_id || ''}&status=${e.target.value}`)}>
                    <option value="">Status</option>
                    {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                </select>
                <a className="btn-secondary" href={`/academics/attendance/export?${query}`}>Export sheet</a>
                <a className="btn-secondary" href={`/academics/attendance/export?kind=chronic&${query}`}>Chronic CSV</a>
                <a className="btn-secondary" href={`/academics/attendance/export?kind=unexcused&${query}`}>Unexcused CSV</a>
            </div>

            <section className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Class</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.date}</td>
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.class_name}</td>
                                <td className="px-3 py-2 uppercase">{row.status}</td>
                                <td className="px-3 py-2">{row.source}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {rows.length === 0 && <p className="p-4 text-sm text-gray-600">No attendance rows for these filters.</p>}
            </section>

            <div className="grid gap-4 md:grid-cols-2">
                <section className="rounded-lg border bg-white p-4 text-sm">
                    <h2 className="mb-2 font-semibold">Chronic absence</h2>
                    <ul className="space-y-1">
                        {chronic.map((row) => (
                            <li key={row.student_id}>{row.student_name}: {row.absent_days} days</li>
                        ))}
                        {chronic.length === 0 && <li className="text-gray-500">None above the threshold.</li>}
                    </ul>
                </section>
                <section className="rounded-lg border bg-white p-4 text-sm">
                    <h2 className="mb-2 font-semibold">Unexcused</h2>
                    <ul className="space-y-1">
                        {unexcused.map((row) => (
                            <li key={row.id}>{row.date} · {row.student_name}</li>
                        ))}
                        {unexcused.length === 0 && <li className="text-gray-500">No unexcused absences.</li>}
                    </ul>
                </section>
            </div>
        </AppShell>
    );
}
