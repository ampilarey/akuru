import AppShell from '../../../Layouts/AppShell';

export default function CompletionReports({
    offering_summaries = [],
    course_summaries = [],
    rows = [],
    years = [],
    courses = [],
    offerings = [],
    filters = {},
}) {
    return (
        <AppShell title="Completion reports">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/reports/completions/export">Export CSV</a>
            </div>
            <form method="get" action="/catalog/reports/completions" className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4">
                <select className="form-input" name="academic_year_id" defaultValue={filters.academic_year_id || ''}>
                    <option value="">All years</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <select className="form-input" name="course_id" defaultValue={filters.course_id || ''}>
                    <option value="">All courses</option>
                    {courses.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}
                </select>
                <select className="form-input" name="offering_id" defaultValue={filters.offering_id || ''}>
                    <option value="">All offerings</option>
                    {offerings.map((row) => <option key={row.id} value={row.id}>{row.title}</option>)}
                </select>
                <button type="submit" className="btn-secondary">Filter</button>
            </form>

            <h2 className="mb-2 text-sm font-medium">Offering completion</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Offering</th>
                            <th className="px-3 py-2">Enrolled</th>
                            <th className="px-3 py-2">Completed</th>
                            <th className="px-3 py-2">Avg progress</th>
                            <th className="px-3 py-2">Avg attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        {offering_summaries.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No offering enrollments.</td></tr>
                        )}
                        {offering_summaries.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.title}</td>
                                <td className="px-3 py-2">{row.enrolled}</td>
                                <td className="px-3 py-2">{row.completed}</td>
                                <td className="px-3 py-2">{row.average_progress}%</td>
                                <td className="px-3 py-2">{row.average_attendance == null ? '—' : `${row.average_attendance}%`}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <h2 className="mb-2 text-sm font-medium">Course completion</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Course</th>
                            <th className="px-3 py-2">Enrolled</th>
                            <th className="px-3 py-2">Completed</th>
                            <th className="px-3 py-2">Avg progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        {course_summaries.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No course enrollments.</td></tr>
                        )}
                        {course_summaries.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.title}</td>
                                <td className="px-3 py-2">{row.enrolled}</td>
                                <td className="px-3 py-2">{row.completed}</td>
                                <td className="px-3 py-2">{row.average_progress}%</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <h2 className="mb-2 text-sm font-medium">Roster</h2>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Course</th>
                            <th className="px-3 py-2">Offering</th>
                            <th className="px-3 py-2">Progress</th>
                            <th className="px-3 py-2">Attendance</th>
                            <th className="px-3 py-2">Lessons</th>
                            <th className="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={7}>No rows.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.enrollment_id} className="border-t">
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.course_title}</td>
                                <td className="px-3 py-2">{row.offering_title || '—'}</td>
                                <td className="px-3 py-2">{row.progress_percentage}%</td>
                                <td className="px-3 py-2">{row.attendance_percent == null ? '—' : `${row.attendance_percent}%`}</td>
                                <td className="px-3 py-2">{row.lessons_completed}/{row.lessons_required}</td>
                                <td className="px-3 py-2">{row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
