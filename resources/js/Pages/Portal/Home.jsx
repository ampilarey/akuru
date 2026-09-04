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

// The next school day, named honestly: "Tomorrow" only when it really is.
function NextDayStrip({ day }) {
    if (!day) return null;

    const heading = day.is_tomorrow
        ? 'Tomorrow'
        : new Date(day.date + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long' });

    return (
        <section className="mb-6 rounded-xl border border-[#E6D9C8] bg-[#F9F4EE] p-4" aria-label="Next school day">
            <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="text-sm font-bold text-[#7C2D37]">
                    {heading}
                    {day.class ? <span className="font-normal text-gray-600"> · {day.class.name}</span> : null}
                </h2>
                <span className="text-xs text-gray-500">{day.date}</span>
            </div>
            <ol className="flex flex-wrap gap-2">
                {day.periods.map((period) => (
                    <li key={period.timetable_entry_id} className="rounded-lg border border-[#E6D9C8] bg-white px-3 py-2">
                        <p className="text-xs font-semibold text-gray-500">
                            {period.period_name}
                            {period.starts_at ? ` · ${period.starts_at}` : ''}
                        </p>
                        <p className="text-sm font-semibold text-gray-900">{period.subject}</p>
                        <p className="text-xs text-gray-600">
                            {/* An open cover request means nobody is assigned yet; saying so
                                beats naming a teacher who will not be there. */}
                            {period.is_substituted
                                ? (period.substitute_teacher
                                    ? `Cover: ${period.substitute_teacher}`
                                    : 'Cover not yet assigned')
                                : (period.teacher || '')}
                            {period.room ? ` · ${period.room}` : ''}
                        </p>
                    </li>
                ))}
            </ol>
        </section>
    );
}

function Tile({ tile }) {
    const body = (
        <>
            <div className="flex items-start justify-between gap-2">
                <p className="text-sm font-semibold text-gray-900">{tile.label}</p>
                {tile.badge ? (
                    <span className="rounded-full bg-[#7C2D37] px-2 py-0.5 text-xs font-bold text-white">{tile.badge}</span>
                ) : null}
            </div>
            <p className="mt-1 text-xs text-gray-600">{tile.status}</p>
        </>
    );

    const className = 'block rounded-xl border border-gray-200 bg-white p-4 text-left';

    return tile.href
        ? <a className={`${className} hover:border-[#7C2D37]`} href={tile.href}>{body}</a>
        : <div className={className}>{body}</div>;
}

export default function Home({ title = 'Dashboard', students = [], csvUrl = '/portal/home/export', sections = [], tiles = [], nextSchoolDay = null }) {
    const labels = Object.fromEntries(sections.map((section) => [section.key, section.label]));
    const extras = sections.filter((section) => !['attendance', 'exams', 'invoices', 'courses', 'hifz'].includes(section.key) && section.href);

    return (
        <AppShell title={title}>
            <NextDayStrip day={nextSchoolDay} />

            {tiles.length > 0 && (
                <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {tiles.map((tile) => <Tile key={tile.key} tile={tile} />)}
                </div>
            )}

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">Attendance, exams, invoices, course progress, and Hifz — read from each domain's public contract.</p>
                <div className="flex gap-3 text-sm">
                    {extras.map((section) => (
                        <a key={section.key} className="text-[#7C2D37] hover:underline" href={section.href}>{section.label}</a>
                    ))}
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
