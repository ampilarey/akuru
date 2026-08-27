import { Link, router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

function averageRate(rows) {
    if (!rows.length) {
        return null;
    }
    const total = rows.reduce((sum, row) => sum + Number(row.rate || 0), 0);
    return Math.round((total / rows.length) * 10) / 10;
}

function SummaryCard({ label, value, href }) {
    return (
        <article className="rounded-lg border border-[#E6D9C8] bg-white p-4">
            <p className="text-xs uppercase tracking-wide text-gray-500">{label}</p>
            <p className="mt-1 text-2xl font-semibold text-[#7C2D37]">{value}</p>
            {href && (
                <Link href={href} className="mt-2 inline-block text-sm text-[#7C2D37] underline">
                    Open
                </Link>
            )}
        </article>
    );
}

export default function StaffOverview({
    title = 'Staff overview',
    yearId = null,
    years = [],
    unfilled = [],
    fillRates = [],
    planAdherence = [],
    ungraded = [],
    csvUrl = '/portal/overview/export',
    sections = [],
}) {
    const labels = Object.fromEntries(sections.map((section) => [section.key, section.label]));
    const hrefs = Object.fromEntries(sections.map((section) => [section.key, section.href]));
    const fillAverage = averageRate(fillRates);
    const planAverage = averageRate(planAdherence);

    return (
        <AppShell title={title}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <select
                    className="form-input"
                    value={yearId || ''}
                    onChange={(e) => router.get(`/portal/overview?academic_year_id=${e.target.value}`)}
                >
                    {years.map((year) => (
                        <option key={year.id} value={year.id}>{year.name}</option>
                    ))}
                </select>
                <a className="btn-secondary" href={csvUrl}>Export CSV</a>
            </div>

            <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <SummaryCard label={labels.unfilled || 'Unfilled registers'} value={unfilled.length} href={hrefs.unfilled} />
                <SummaryCard label={labels.ungraded || 'Ungraded exams'} value={ungraded.length} href={hrefs.ungraded} />
                <SummaryCard label={labels.fill_rates || 'Fill rate'} value={fillAverage == null ? '—' : `${fillAverage}%`} href={hrefs.fill_rates} />
                <SummaryCard label={labels.plan_adherence || 'Plan adherence'} value={planAverage == null ? '—' : `${planAverage}%`} href={hrefs.plan_adherence} />
            </div>

            <section className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <div className="flex items-center justify-between px-3 py-2">
                    <h2 className="text-sm font-medium">{labels.unfilled || 'Unfilled registers'}</h2>
                    {hrefs.unfilled && <Link href={hrefs.unfilled} className="text-sm text-[#7C2D37] underline">Registers</Link>}
                </div>
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Class</th>
                            <th className="px-3 py-2">Subject</th>
                            <th className="px-3 py-2">Period</th>
                            <th className="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {unfilled.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No unfilled registers past their time.</td></tr>
                        )}
                        {unfilled.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.date}</td>
                                <td className="px-3 py-2">{row.class_name}</td>
                                <td className="px-3 py-2">{row.subject_name}</td>
                                <td className="px-3 py-2">{row.period_name || '—'}</td>
                                <td className="px-3 py-2 uppercase">{row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            <section className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <div className="flex items-center justify-between px-3 py-2">
                    <h2 className="text-sm font-medium">{labels.ungraded || 'Ungraded exams'}</h2>
                    {hrefs.ungraded && <Link href={hrefs.ungraded} className="text-sm text-[#7C2D37] underline">Exams</Link>}
                </div>
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Exam</th>
                            <th className="px-3 py-2">Class</th>
                            <th className="px-3 py-2">Subject</th>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {ungraded.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No exams still in marks entry after the exam date.</td></tr>
                        )}
                        {ungraded.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                <td className="px-3 py-2">{row.class_name}</td>
                                <td className="px-3 py-2">{row.subject_name}</td>
                                <td className="px-3 py-2">{row.exam_date}</td>
                                <td className="px-3 py-2">{row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            <div className="grid gap-4 md:grid-cols-2">
                <section className="rounded-lg border bg-white p-4 text-sm">
                    <h2 className="mb-2 font-semibold">{labels.fill_rates || 'Fill rate'}</h2>
                    {fillRates.length === 0 && <p className="text-gray-500">No register fill data.</p>}
                    <ul className="space-y-1">
                        {fillRates.map((row) => (
                            <li key={row.teacher_id}>{row.teacher_name || `Teacher #${row.teacher_id}`}: {row.filled}/{row.total} ({row.rate}%)</li>
                        ))}
                    </ul>
                </section>
                <section className="rounded-lg border bg-white p-4 text-sm">
                    <h2 className="mb-2 font-semibold">{labels.plan_adherence || 'Plan adherence'}</h2>
                    {planAdherence.length === 0 && <p className="text-gray-500">No course plans.</p>}
                    <ul className="space-y-1">
                        {planAdherence.map((row) => (
                            <li key={row.id}>{row.title}: {row.completed}/{row.total} ({row.rate}%)</li>
                        ))}
                    </ul>
                </section>
            </div>
        </AppShell>
    );
}
