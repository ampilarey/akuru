import { router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * Staff attendance reports: who is late and how often, who is absent. Every
 * word is the `hr` book's (slice HR1, STATUS §5ql). A department's name is
 * the school's.
 */
export default function Reports({ filters, years, departments, late, absence, t = {} }) {
    const query = `from=${filters.from || ''}&to=${filters.to || ''}&department=${filters.department || ''}&academic_year_id=${filters.academic_year_id || ''}`;

    return (
        <AppShell title={t.reports_title || 'Staff attendance reports'}>
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
                <select name="academic_year_id" className="form-input" aria-label={t.year || 'Year'} defaultValue={filters.academic_year_id || ''}>
                    <option value="">{t.all_years || 'All years'}</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <input type="date" name="from" className="form-input" aria-label={t.from || 'From'} defaultValue={filters.from || ''} />
                <input type="date" name="to" className="form-input" aria-label={t.to || 'To'} defaultValue={filters.to || ''} />
                <select name="department" className="form-input" aria-label={t.department || 'Department'} defaultValue={filters.department || ''}>
                    <option value="">{t.all_departments || 'All departments'}</option>
                    {departments.map((department) => <option key={department} value={department}>{department}</option>)}
                </select>
                <button type="submit" className="btn-secondary">{t.filter || 'Filter'}</button>
            </form>

            <div className="mb-6">
                <div className="mb-2 flex items-center justify-between">
                    <h2 className="font-medium">{t.reports_late_patterns || 'Late patterns'}</h2>
                    <a className="btn-secondary" href={`/hr/attendance/reports/export?kind=late&${query}`}>{t.reports_export_late || 'Export late CSV'}</a>
                </div>
                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">{t.staff || 'Staff'}</th>
                                <th className="px-3 py-2">{t.department || 'Department'}</th>
                                <th className="px-3 py-2">{t.reports_late_days || 'Late days'}</th>
                                <th className="px-3 py-2">{t.reports_minutes || 'Minutes'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {late.length === 0 && (
                                <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.reports_no_late || 'No late records.'}</td></tr>
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
                    <h2 className="font-medium">{t.reports_absence_summary || 'Absence summary'}</h2>
                    <a className="btn-secondary" href={`/hr/attendance/reports/export?kind=absence&${query}`}>{t.reports_export_absence || 'Export absence CSV'}</a>
                </div>
                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">{t.staff || 'Staff'}</th>
                                <th className="px-3 py-2">{t.department || 'Department'}</th>
                                <th className="px-3 py-2">{t.attendance_status_absent || 'Absent'}</th>
                                <th className="px-3 py-2">{t.attendance_status_half_day || 'Half day'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {absence.length === 0 && (
                                <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.reports_no_absences || 'No absences.'}</td></tr>
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
