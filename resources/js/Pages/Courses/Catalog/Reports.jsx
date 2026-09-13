import AppShell from '../../../Layouts/AppShell';

/**
 * SPEC §33 "Admin Dashboard → Reports" names ten reports. Six were already
 * computed, correctly, and scattered across three unrelated screens; three —
 * total students, active enrollments and assessment scores — had no reader at
 * all, though every one of them was a count or an existing Action away.
 *
 * This page computes nothing. It is the single place that answers §33's
 * question, which is the thing an administrator actually asked for.
 */
function Stat({ label, value, detail }) {
    return (
        <div className="rounded-lg border bg-white p-4">
            <dt className="text-xs uppercase tracking-wide text-gray-500">{label}</dt>
            <dd className="mt-1 text-2xl font-semibold">{value}</dd>
            {detail && <p className="mt-1 text-xs text-gray-600">{detail}</p>}
        </div>
    );
}

function SummaryTable({ title, rows, attendanceColumn = false }) {
    return (
        <section className="rounded-lg border bg-white p-4">
            <h2 className="mb-2 font-medium">{title}</h2>
            {rows.length === 0 && <p className="text-sm text-gray-500">Nothing in this view.</p>}
            {rows.length > 0 && (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2 text-start">Name</th>
                                <th className="px-3 py-2">Enrolled</th>
                                <th className="px-3 py-2">Completed</th>
                                <th className="px-3 py-2">Avg progress</th>
                                {attendanceColumn && <th className="px-3 py-2">Avg attendance</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="px-3 py-2">{row.title}</td>
                                    <td className="px-3 py-2 text-center">{row.enrolled}</td>
                                    <td className="px-3 py-2 text-center">{row.completed}</td>
                                    <td className="px-3 py-2 text-center">{row.average_progress}%</td>
                                    {attendanceColumn && (
                                        <td className="px-3 py-2 text-center">
                                            {/* §24's "where applicable": null means the
                                                offering schedules no sessions, and 0%
                                                would read as "nobody turned up". */}
                                            {row.average_attendance === null || row.average_attendance === undefined
                                                ? '—'
                                                : `${row.average_attendance}%`}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

export default function Reports({
    filters = {},
    courses = [],
    totals = {},
    byCourse = [],
    byOffering = [],
    scores = {},
    pendingReviews = {},
    certificates = {},
}) {
    const pct = (value) => (value === null || value === undefined ? '—' : `${value}%`);

    return (
        <AppShell title="Reports">
            <form method="get" className="mb-4 flex flex-wrap items-end gap-3">
                <label className="text-sm">
                    <span className="block text-xs text-gray-600">Course</span>
                    <select className="form-input" name="course_id" defaultValue={filters.course_id || ''}>
                        <option value="">All courses</option>
                        {courses.map((course) => (
                            <option key={course.id} value={course.id}>{course.title}</option>
                        ))}
                    </select>
                </label>
                <button type="submit" className="btn-secondary">Filter</button>
                <a className="btn-secondary" href="/catalog/reports">Clear</a>
                <span className="ms-auto" />
                <a className="btn-secondary" href="/catalog/reports/export">Export CSV</a>
            </form>

            <dl className="mb-4 grid gap-3 md:grid-cols-4">
                {/* §33's first two, which nothing read before. */}
                <Stat label="Total students" value={totals.students ?? 0} detail="Across the institute" />
                <Stat label="Active enrolments" value={totals.active_enrollments ?? 0} detail="Active or approved" />
                <Stat
                    label="Lesson completion"
                    value={`${totals.lessons_completed ?? 0} / ${totals.lessons_required ?? 0}`}
                    detail="Required lessons in this view"
                />
                <Stat label="Average attendance" value={pct(totals.average_attendance)} detail="Where sessions are scheduled" />
                <Stat
                    label="Assessment scores"
                    value={pct(scores.average_percent)}
                    detail={`${scores.count ?? 0} marked attempt${(scores.count ?? 0) === 1 ? '' : 's'}`}
                />
                <Stat
                    label="Pending reviews"
                    value={pendingReviews.count ?? 0}
                    detail={pendingReviews.oldest ? `Oldest waiting since ${pendingReviews.oldest}` : 'Nothing waiting'}
                />
                <Stat
                    label="Certificates issued"
                    value={certificates.issued ?? 0}
                    detail={certificates.revoked ? `${certificates.revoked} revoked` : 'None revoked'}
                />
                <Stat
                    label="Completed in view"
                    value={`${totals.completed_in_view ?? 0} / ${totals.enrolled_in_view ?? 0}`}
                    detail="Enrolments matching the filter"
                />
            </dl>

            <div className="grid gap-4 md:grid-cols-2">
                <SummaryTable title="Course completion" rows={byCourse} />
                <SummaryTable title="Offering completion" rows={byOffering} attendanceColumn />
            </div>

            <div className="mt-4 flex flex-wrap gap-3 text-sm">
                <a className="text-[#7C2D37] hover:underline" href="/catalog/reports/completions">Per-student completions</a>
                <a className="text-[#7C2D37] hover:underline" href="/catalog/reviews">Review queue</a>
                <a className="text-[#7C2D37] hover:underline" href="/catalog/certificates">Certificates</a>
            </div>

            {/* §33's tenth report is its own deferral. Saying so beats an
                administrator wondering whether it is missing or broken. */}
            <p className="mt-4 text-xs text-gray-500">
                Payment reports are deferred by SPEC §33 (&ldquo;Payment reports later&rdquo;).
            </p>
        </AppShell>
    );
}
