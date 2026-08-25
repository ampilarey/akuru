import { router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Reports({ filters, years, departments, late, absence }) {
    return (
        <AppShell title="Staff attendance reports">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    const data = new FormData(e.currentTarget);
                    router.get('/hr/attendance/reports', {
                        academic_year_id: data.get('academic_year_id'),
                        from: data.get('from'),
                        to: data.get('to'),
                        department: data.get('department'),
                    });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-5"
            >
                <select name="academic_year_id" className="form-input" defaultValue={filters.academic_year_id || ''}>
                    <option value="">All years</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <input type="date" name="from" className="form-input" defaultValue={filters.from || ''} />
                <input type="date" name="to" className="form-input" defaultValue={filters.to || ''} />
                <select name="department" className="form-input" defaultValue={filters.department || ''}>
                    <option value="">All departments</option>
                    {departments.map((department) => <option key={department} value={department}>{department}</option>)}
                </select>
                <button type="submit" className="btn-secondary">Filter</button>
            </form>

            <div className="mb-6">
                <div className="mb-2 flex items-center justify-between">
                    <h2 className="font-medium">Late patterns</h2>
                    <a className="btn-secondary" href={`/hr/attendance/reports/export?kind=late&from=${filters.from || ''}&to=${filters.to || ''}&department=${filters.department || ''}&academic_year_id=${filters.academic_year_id || ''}`}>Export late CSV</a>
                </div>
                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-left">
                            <tr>
                                <th className="px-3 py-2">Staff</th>
                                <th className="px-3 py-2">Department</th>
                                <th className="px-3 py-2">Late days</th>
                                <th className="px-3 py-2">Minutes</th>
                            </tr>
                        </thead>
                        <tbody>
                            {late.length === 0 && (
                                <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No late records.</td></tr>
                            )}
                            {late.map((row) => (
                                <tr key={row.staff_profile_id} className="border-t">
                                    <td className="px-3 py-2">{row.staff_name}</td>
                                    <td className="px-3 py-2">{row.department || '—'}</td>
                                    <td className="px-3 py-2">{row.late_count}</td>
                                    <td className="px-3 py-2">{row.minutes_late}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <div className="mb-2 flex items-center justify-between">
                    <h2 className="font-medium">Absence summary</h2>
                    <a className="btn-secondary" href={`/hr/attendance/reports/export?kind=absence&from=${filters.from || ''}&to=${filters.to || ''}&department=${filters.department || ''}&academic_year_id=${filters.academic_year_id || ''}`}>Export absence CSV</a>
                </div>
                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-left">
                            <tr>
                                <th className="px-3 py-2">Staff</th>
                                <th className="px-3 py-2">Department</th>
                                <th className="px-3 py-2">Absent</th>
                                <th className="px-3 py-2">Half day</th>
                            </tr>
                        </thead>
                        <tbody>
                            {absence.length === 0 && (
                                <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No absences.</td></tr>
                            )}
                            {absence.map((row) => (
                                <tr key={row.staff_profile_id} className="border-t">
                                    <td className="px-3 py-2">{row.staff_name}</td>
                                    <td className="px-3 py-2">{row.department || '—'}</td>
                                    <td className="px-3 py-2">{row.absent_count}</td>
                                    <td className="px-3 py-2">{row.half_day_count}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppShell>
    );
}
