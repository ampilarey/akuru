import { Link, router, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

function averageRate(rows) {
    if (!rows.length) {
        return null;
    }
    const total = rows.reduce((sum, row) => sum + Number(row.rate || 0), 0);
    return Math.round((total / rows.length) * 10) / 10;
}

function SummaryCard({ label, value, href, openLabel }) {
    return (
        <article className="rounded-lg border border-[#E6D9C8] bg-white p-4">
            <p className="text-xs uppercase tracking-wide text-gray-500">{label}</p>
            <p className="mt-1 text-2xl font-semibold text-[#7C2D37]">{value}</p>
            {href && (
                <Link href={href} className="mt-2 inline-block text-sm text-[#7C2D37] underline">
                    {openLabel}
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
    unpublishedReportCards = [],
    csvUrl = '/portal/overview/export',
    sections = [],
    t = {},
}) {
    // The section names come from the server in the page's language; the
    // rest is the `portal` book's (BACKLOG C21, slice PT4). A register's and
    // an exam's state are codes, named here.
    const labels = Object.fromEntries(sections.map((section) => [section.key, section.label]));
    const hrefs = Object.fromEntries(sections.map((section) => [section.key, section.href]));
    const fillAverage = averageRate(fillRates);
    const planAverage = averageRate(planAdherence);
    const open = t.open || 'Open';
    const col = {
        date: t.col_date || 'Date',
        class: t.col_class || 'Class',
        subject: t.col_subject || 'Subject',
        period: t.overview_col_period || 'Period',
        status: t.col_status || 'Status',
        exam: t.col_exam || 'Exam',
    };
    // This screen is the school day in numbers; the person's workspace home
    // (the School office, or the Institute) is where things are managed.
    // Offered to whoever has one (STATUS §5ia, §5id).
    const { auth = {}, i18n = {} } = usePage().props;
    const home = (auth.workspaces || []).find((workspace) => workspace.key === auth.workspace);
    const hint = i18n.nav || {};

    return (
        <AppShell title={title}>
            {home && (
                <p className="mb-4 flex flex-wrap items-center gap-3 text-xs text-gray-500" data-testid="dashboard-hint">
                    <Link href={home.href} data-testid="open-admin-panel" className="rounded bg-[#7C2D37] px-3 py-1.5 text-sm font-semibold text-white hover:bg-[#5E1F28]">
                        🏠 {home.label} →
                    </Link>
                    <span>{hint.dashboard_hint || 'This overview is today’s numbers. Your home has everything else.'}</span>
                </p>
            )}
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <select
                    className="form-input"
                    aria-label={t.col_year || 'Year'}
                    value={yearId || ''}
                    onChange={(e) => router.get(`/portal/overview?academic_year_id=${e.target.value}`)}
                >
                    {years.map((year) => (
                        <option key={year.id} value={year.id}>{year.name}</option>
                    ))}
                </select>
                <a className="btn-secondary" href={csvUrl}>{t.export_csv || 'Export CSV'}</a>
            </div>

            <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <SummaryCard label={labels.unfilled || t.overview_unfilled || 'Unfilled registers'} value={unfilled.length} href={hrefs.unfilled} openLabel={open} />
                <SummaryCard label={labels.ungraded || t.overview_ungraded || 'Ungraded exams'} value={ungraded.length} href={hrefs.ungraded} openLabel={open} />
                <SummaryCard label={labels.unpublished_report_cards || t.overview_unpublished || 'Unpublished report cards'} value={unpublishedReportCards.length} href={hrefs.unpublished_report_cards} openLabel={open} />
                <SummaryCard label={labels.fill_rates || t.overview_fill_rate || 'Fill rate'} value={fillAverage == null ? '—' : `${fillAverage}%`} href={hrefs.fill_rates} openLabel={open} />
                <SummaryCard label={labels.plan_adherence || t.overview_plan_adherence || 'Plan adherence'} value={planAverage == null ? '—' : `${planAverage}%`} href={hrefs.plan_adherence} openLabel={open} />
            </div>

            <section className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <div className="flex items-center justify-between px-3 py-2">
                    <h2 className="text-sm font-medium">{labels.unfilled || t.overview_unfilled || 'Unfilled registers'}</h2>
                    {hrefs.unfilled && <Link href={hrefs.unfilled} className="text-sm text-[#7C2D37] underline">{t.overview_registers || 'Registers'}</Link>}
                </div>
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.date}</th>
                            <th className="px-3 py-2">{col.class}</th>
                            <th className="px-3 py-2">{col.subject}</th>
                            <th className="px-3 py-2">{col.period}</th>
                            <th className="px-3 py-2">{col.status}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {unfilled.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.overview_none_unfilled || 'No unfilled registers past their time.'}</td></tr>
                        )}
                        {unfilled.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.date}</td>
                                <td className="px-3 py-2">{row.class_name}</td>
                                <td className="px-3 py-2">{row.subject_name}</td>
                                <td className="px-3 py-2">{row.period_name || '—'}</td>
                                <td className="px-3 py-2">{t[`register_status_${row.status}`] || row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            <section className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <div className="flex items-center justify-between px-3 py-2">
                    <h2 className="text-sm font-medium">{labels.ungraded || t.overview_ungraded || 'Ungraded exams'}</h2>
                    {hrefs.ungraded && <Link href={hrefs.ungraded} className="text-sm text-[#7C2D37] underline">{t.overview_exams || 'Exams'}</Link>}
                </div>
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.exam}</th>
                            <th className="px-3 py-2">{col.class}</th>
                            <th className="px-3 py-2">{col.subject}</th>
                            <th className="px-3 py-2">{col.date}</th>
                            <th className="px-3 py-2">{col.status}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {ungraded.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.overview_none_ungraded || 'No exams still in marks entry after the exam date.'}</td></tr>
                        )}
                        {ungraded.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                <td className="px-3 py-2">{row.class_name}</td>
                                <td className="px-3 py-2">{row.subject_name}</td>
                                <td className="px-3 py-2">{row.exam_date}</td>
                                <td className="px-3 py-2">{t[`exam_status_${row.status}`] || row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            <div className="grid gap-4 md:grid-cols-2">
                <section className="rounded-lg border bg-white p-4 text-sm">
                    <h2 className="mb-2 font-semibold">{labels.fill_rates || t.overview_fill_rate || 'Fill rate'}</h2>
                    {fillRates.length === 0 && <p className="text-gray-500">{t.overview_no_fill || 'No register fill data.'}</p>}
                    <ul className="space-y-1">
                        {fillRates.map((row) => (
                            <li key={row.teacher_id}>{row.teacher_name || (t.overview_teacher || 'Teacher #:id').replace(':id', row.teacher_id)}: {row.filled}/{row.total} ({row.rate}%)</li>
                        ))}
                    </ul>
                </section>
                <section className="rounded-lg border bg-white p-4 text-sm">
                    <h2 className="mb-2 font-semibold">{labels.plan_adherence || t.overview_plan_adherence || 'Plan adherence'}</h2>
                    {planAdherence.length === 0 && <p className="text-gray-500">{t.overview_no_plans || 'No course plans.'}</p>}
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
