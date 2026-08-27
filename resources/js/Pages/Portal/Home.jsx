import AppShell from '../../Layouts/AppShell';

function Section({ title, href, children, empty }) {
    return (
        <section className="rounded-lg border bg-white p-4">
            <div className="mb-2 flex items-center justify-between gap-3">
                <h3 className="text-sm font-medium">{title}</h3>
                {href && <a className="text-sm text-[#7C2D37] hover:underline" href={href}>Open</a>}
            </div>
            {empty ? <p className="text-sm text-gray-500">{empty}</p> : children}
        </section>
    );
}

function StudentCard({ student, labels }) {
    const summary = student.attendance_summary || {};
    return (
        <article className="space-y-4 rounded-xl border border-[#E6D9C8] bg-[#FDFBF8] p-4">
            <header>
                <h2 className="text-lg font-semibold">{student.name}</h2>
                <p className="text-xs uppercase text-gray-500">{student.relationship}</p>
            </header>

            <Section title={labels.attendance} href="/portal/attendance" empty={summary.total ? null : 'No attendance yet.'}>
                <p className="mb-2 text-sm">{summary.percent ?? 0}% present · {summary.absent ?? 0} absent · {summary.excused ?? 0} excused</p>
                <ul className="space-y-1 text-sm">
                    {(student.attendance || []).map((row) => (
                        <li key={row.id}>{row.date} · {row.class_name || 'Class'} · {row.status}</li>
                    ))}
                </ul>
            </Section>

            <Section title={labels.exams} href="/portal/exams" empty={(student.exams || []).length ? null : 'No published exam results.'}>
                <ul className="space-y-1 text-sm">
                    {(student.exams || []).map((exam) => (
                        <li key={`${exam.id}-${student.id}`}>
                            {exam.name} {exam.subject ? `· ${exam.subject}` : ''} · {exam.marks == null ? '—' : `${exam.marks}/${exam.max_marks}`}
                        </li>
                    ))}
                </ul>
            </Section>

            <Section title={labels.invoices} href="/portal/invoices" empty={(student.invoices || []).length ? null : 'No invoices.'}>
                <p className="mb-2 text-sm">Balance {student.invoice_balance}</p>
                <ul className="space-y-1 text-sm">
                    {(student.invoices || []).map((invoice) => (
                        <li key={invoice.id}>{invoice.invoice_number} · {invoice.status} · {invoice.balance}</li>
                    ))}
                </ul>
            </Section>

            <Section title={labels.courses} href="/portal/performance" empty={(student.courses || []).length ? null : 'No course enrollments.'}>
                <ul className="space-y-1 text-sm">
                    {(student.courses || []).map((course) => (
                        <li key={course.enrollment_id}>{course.course_title} · {course.progress_percentage}% · {course.status}</li>
                    ))}
                </ul>
            </Section>

            <Section title={labels.hifz} empty={(student.hifz || []).length ? null : 'No Hifz records.'}>
                <ul className="space-y-1 text-sm">
                    {(student.hifz || []).map((row, index) => (
                        <li key={`${row.program}-${index}`}>
                            {row.program || 'Hifz'} {row.current_surah ? `· ${row.current_surah}` : ''} · {row.status}
                            {row.accuracy_percent != null ? ` · ${row.accuracy_percent}%` : ''}
                        </li>
                    ))}
                </ul>
            </Section>
        </article>
    );
}

export default function Home({ title = 'Dashboard', students = [], csvUrl = '/portal/home/export', sections = [] }) {
    const labels = Object.fromEntries(sections.map((section) => [section.key, section.label]));
    const absence = sections.find((section) => section.key === 'absence_notes');

    return (
        <AppShell title={title}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">Attendance, exams, invoices, course progress, and Hifz — read from each domain's public contract.</p>
                <div className="flex gap-3 text-sm">
                    {absence && <a className="text-[#7C2D37] hover:underline" href={absence.href}>{absence.label}</a>}
                    <a className="btn-secondary" href={csvUrl}>Export CSV</a>
                </div>
            </div>
            {students.length === 0 && (
                <p className="text-sm text-gray-600">No student or linked children.</p>
            )}
            <div className="space-y-6">
                {students.map((student) => (
                    <StudentCard
                        key={student.id}
                        student={student}
                        labels={{
                            attendance: labels.attendance || 'Attendance',
                            exams: labels.exams || 'Exams / grades',
                            invoices: labels.invoices || 'Invoices',
                            courses: labels.courses || 'Course progress',
                            hifz: labels.hifz || 'Hifz',
                        }}
                    />
                ))}
            </div>
        </AppShell>
    );
}
