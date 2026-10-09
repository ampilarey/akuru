import { router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ filters, years, classes, statuses, rows, chronic, unexcused, tardies = [], tardiesPerAbsence = 0, t = {} }) {
    const query = new URLSearchParams(
        Object.fromEntries(Object.entries(filters).filter(([, value]) => value)),
    ).toString();
    // In the page's language (BACKLOG C21, slice OA1); a mark's state and how
    // it was recorded are codes, named here.
    const status = (code) => t[`attendance_status_${code}`] || code;

    return (
        <AppShell title={t.reports_title || 'Attendance reports'}>
            <div className="mb-4 flex flex-wrap items-end gap-2">
                <select className="form-input" aria-label={t.year || 'Year'} value={filters.academic_year_id || ''} onChange={(e) => router.get(`/academics/attendance?academic_year_id=${e.target.value}`)}>
                    <option value="">{t.year || 'Year'}</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <select className="form-input" aria-label={t.class || 'Class'} value={filters.class_id || ''} onChange={(e) => router.get(`/academics/attendance?academic_year_id=${filters.academic_year_id || ''}&class_id=${e.target.value}`)}>
                    <option value="">{t.class || 'Class'}</option>
                    {classes.map((item) => <option key={item.id} value={item.id}>{item.name} {item.section}</option>)}
                </select>
                <select className="form-input" aria-label={t.status || 'Status'} value={filters.status || ''} onChange={(e) => router.get(`/academics/attendance?academic_year_id=${filters.academic_year_id || ''}&class_id=${filters.class_id || ''}&status=${e.target.value}`)}>
                    <option value="">{t.status || 'Status'}</option>
                    {statuses.map((code) => <option key={code} value={code}>{status(code)}</option>)}
                </select>
                <a className="btn-secondary" href={`/academics/attendance/export?${query}`}>{t.reports_export_sheet || 'Export sheet'}</a>
                <a className="btn-secondary" href={`/academics/attendance/export?kind=chronic&${query}`}>{t.reports_chronic_csv || 'Chronic absence CSV'}</a>
                <a className="btn-secondary" href={`/academics/attendance/export?kind=unexcused&${query}`}>{t.reports_unexcused_csv || 'Unexcused absence CSV'}</a>
                <a className="btn-secondary" href={`/academics/attendance/export?kind=tardies&${query}`}>{t.reports_lateness_csv || 'Lateness CSV'}</a>
            </div>

            <section className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_date || 'Date'}</th>
                            <th className="px-3 py-2">{t.col_student || 'Student'}</th>
                            <th className="px-3 py-2">{t.col_class || 'Class'}</th>
                            <th className="px-3 py-2">{t.col_status || 'Status'}</th>
                            <th className="px-3 py-2">{t.col_source || 'Source'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.date}</td>
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.class_name}</td>
                                <td className="px-3 py-2 uppercase">{status(row.status)}</td>
                                <td className="px-3 py-2">{t[`attendance_source_${row.source}`] || row.source}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {rows.length === 0 && <p className="p-4 text-sm text-gray-600">{t.reports_none || 'No attendance rows for these filters.'}</p>}
            </section>

            <div className="grid gap-4 md:grid-cols-2">
                <section className="rounded-lg border bg-white p-4 text-sm">
                    <h2 className="mb-2 font-semibold">{t.reports_chronic || 'Chronic absence'}</h2>
                    <ul className="space-y-1">
                        {chronic.map((row) => (
                            <li key={row.student_id}>{row.student_name}: {(t.reports_days || ':count days').replace(':count', row.absent_days)}</li>
                        ))}
                        {chronic.length === 0 && <li className="text-gray-500">{t.reports_chronic_none || 'None above the threshold.'}</li>}
                    </ul>
                </section>
                <section className="rounded-lg border bg-white p-4 text-sm">
                    <h2 className="mb-2 font-semibold">{t.reports_unexcused || 'Unexcused'}</h2>
                    <ul className="space-y-1">
                        {unexcused.map((row) => (
                            <li key={row.id}>{row.date} · {row.student_name}</li>
                        ))}
                        {unexcused.length === 0 && <li className="text-gray-500">{t.reports_unexcused_none || 'No unexcused absences.'}</li>}
                    </ul>
                </section>
            </div>

            <section className="mt-4 overflow-x-auto rounded-lg border bg-white">
                <div className="flex flex-wrap items-baseline justify-between gap-2 p-4 pb-2">
                    <h2 className="text-sm font-semibold">{t.reports_lateness || 'Lateness and early departures'}</h2>
                    <p className="text-xs text-gray-500">
                        {tardiesPerAbsence > 0
                            ? (t.reports_tardy_rule || ':count late marks count as 1 absence in the last column. This is shown, not applied — absence figures above are unchanged.').replace(':count', tardiesPerAbsence)
                            : (t.reports_no_tardy_rule || 'No tardy-to-absence rule is set, so no lateness is counted as absence.')}
                    </p>
                </div>
                <table className="w-full min-w-[46rem] text-sm">
                    <thead className="bg-[#F9F4EE] text-start">
                        <tr>
                            <th className="p-2">{t.col_student || 'Student'}</th>
                            <th className="p-2">{t.reports_col_late || 'Late'}</th>
                            <th className="p-2">{t.reports_col_minutes || 'Minutes'}</th>
                            <th className="p-2">{t.reports_col_left_early || 'Left early'}</th>
                            <th className="p-2">{t.reports_col_absent_days || 'Absent days'}</th>
                            {tardiesPerAbsence > 0 && <th className="p-2">{t.reports_col_from_lateness || 'From lateness'}</th>}
                            {tardiesPerAbsence > 0 && <th className="p-2">{t.reports_col_effective || 'Effective'}</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {tardies.map((row) => (
                            <tr key={row.student_id} className="border-t">
                                <td className="p-2">{row.student_name}</td>
                                <td className="p-2">{row.tardies}</td>
                                <td className="p-2">{row.minutes_late}</td>
                                <td className="p-2">{row.early_departures}</td>
                                <td className="p-2">{row.absent_days}</td>
                                {tardiesPerAbsence > 0 && <td className="p-2">{row.absences_from_tardies}</td>}
                                {tardiesPerAbsence > 0 && <td className="p-2 font-semibold">{row.effective_absences}</td>}
                            </tr>
                        ))}
                    </tbody>
                </table>
                {tardies.length === 0 && <p className="p-4 text-sm text-gray-600">{t.reports_lateness_none || 'No lateness recorded for these filters.'}</p>}
            </section>
        </AppShell>
    );
}
