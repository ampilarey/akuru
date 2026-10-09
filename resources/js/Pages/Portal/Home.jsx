import { usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import WorkspaceTiles from '../../Components/WorkspaceTiles';

// `Date#getDay()`'s order, for the next school day's name (BACKLOG C21,
// slice PT1a): a browser has no Dhivehi weekdays to give.
const WEEKDAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

const fill = (phrase, values) => Object.entries(values).reduce((text, [name, value]) => text.replace(`:${name}`, value ?? ''), phrase);

function Section({ title, href, children, empty, t }) {
    return (
        <section className="rounded-lg border bg-white p-4">
            <div className="mb-2 flex items-center justify-between gap-3">
                <h3 className="text-sm font-medium">{title}</h3>
                {href && <a className="chip-link" href={href}>{t.open || 'Open'}</a>}
            </div>
            {empty ? <p className="text-sm text-gray-500">{empty}</p> : children}
        </section>
    );
}

function StudentCard({ student, labels, t, learn }) {
    const summary = student.attendance_summary || {};
    return (
        <article className="space-y-4 rounded-xl border border-[#E6D9C8] bg-[#FDFBF8] p-4">
            <header>
                <h2 className="text-lg font-semibold">{student.name}</h2>
                <p className="text-xs uppercase text-gray-500">{learn[`relationship_${student.relationship}`] || student.relationship}</p>
            </header>

            <Section t={t} title={labels.attendance} href="/portal/attendance" empty={summary.total ? null : (t.home_no_attendance || 'No attendance yet.')}>
                <p className="mb-2 text-sm">
                    {fill(t.home_attendance_line || ':percent% present · :absent absent · :excused excused', {
                        percent: summary.percent ?? 0,
                        absent: summary.absent ?? 0,
                        excused: summary.excused ?? 0,
                    })}
                </p>
                <ul className="space-y-1 text-sm">
                    {(student.attendance || []).map((row) => (
                        <li key={row.id}>{row.date} · {row.class_name || t.col_class || 'Class'} · {t[`attendance_status_${row.status}`] || row.status}</li>
                    ))}
                </ul>
            </Section>

            <Section t={t} title={labels.exams} href="/portal/exams" empty={(student.exams || []).length ? null : (t.home_no_exams || 'No published exam results.')}>
                <ul className="space-y-1 text-sm">
                    {(student.exams || []).map((exam) => (
                        <li key={`${exam.id}-${student.id}`}>
                            {exam.name} {exam.subject ? `· ${exam.subject}` : ''} · {exam.marks == null ? '—' : `${exam.marks}/${exam.max_marks}`}
                        </li>
                    ))}
                </ul>
            </Section>

            <Section t={t} title={labels.invoices} href="/portal/invoices" empty={(student.invoices || []).length ? null : (t.home_no_invoices || 'No invoices.')}>
                <p className="mb-2 text-sm">{fill(t.home_balance || 'Balance :amount', { amount: student.invoice_balance })}</p>
                <ul className="space-y-1 text-sm">
                    {(student.invoices || []).map((invoice) => (
                        <li key={invoice.id}>{invoice.invoice_number} · {t[`invoice_status_${invoice.status}`] || invoice.status} · {invoice.balance}</li>
                    ))}
                </ul>
            </Section>

            <Section t={t} title={labels.courses} href="/portal/performance" empty={(student.courses || []).length ? null : (t.home_no_courses || 'No course enrollments.')}>
                <ul className="space-y-1 text-sm">
                    {(student.courses || []).map((course) => (
                        <li key={course.enrollment_id}>{course.course_title} · {course.progress_percentage}% · {learn[`enrol_status_${course.status}`] || course.status}</li>
                    ))}
                </ul>
            </Section>

            <Section t={t} title={labels.hifz} empty={(student.hifz || []).length ? null : (t.home_no_hifz || 'No Hifz records.')}>
                <ul className="space-y-1 text-sm">
                    {(student.hifz || []).map((row, index) => (
                        <li key={`${row.program}-${index}`}>
                            {row.program || labels.hifz} {row.current_surah ? `· ${row.current_surah}` : ''} · {t[`hifz_status_${row.status}`] || row.status}
                            {row.accuracy_percent != null ? ` · ${row.accuracy_percent}%` : ''}
                        </li>
                    ))}
                </ul>
            </Section>
        </article>
    );
}

// The next school day, named honestly: "Tomorrow" only when it really is.
function NextDayStrip({ day, t }) {
    if (!day) return null;

    const weekday = WEEKDAYS[new Date(day.date + 'T00:00:00').getDay()];
    const heading = day.is_tomorrow ? (t.home_tomorrow || 'Tomorrow') : (t[`weekday_${weekday}`] || weekday);

    return (
        <section className="mb-6 rounded-xl border border-[#E6D9C8] bg-[#F9F4EE] p-4" aria-label={t.home_next_day || 'Next school day'}>
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
                                    ? fill(t.home_cover || 'Cover: :name', { name: period.substitute_teacher })
                                    : (t.home_cover_unassigned || 'Cover not yet assigned'))
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

    const className = 'block rounded-xl border border-gray-200 bg-white p-4 text-start';

    return tile.href
        ? <a className={`${className} hover:border-[#7C2D37]`} href={tile.href}>{body}</a>
        : <div className={className}>{body}</div>;
}

export default function Home({ title, students = [], csvUrl = '/portal/home/export', sections = [], tiles = [], nextSchoolDay = null, t = {} }) {
    // A relationship and a course enrolment's status are the `learn` book's,
    // which the shell shares with every page; the title, the tiles and the
    // sections arrive already in the page's language.
    const learn = usePage().props.i18n?.learn || {};
    const labels = Object.fromEntries(sections.map((section) => [section.key, section.label]));
    const extras = sections.filter((section) => !['attendance', 'exams', 'invoices', 'courses', 'hifz'].includes(section.key) && section.href);

    return (
        <AppShell title={title || t.home_title_student || 'Student Dashboard'}>
            <NextDayStrip day={nextSchoolDay} t={t} />

            {tiles.length > 0 && (
                <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {tiles.map((tile) => <Tile key={tile.key} tile={tile} />)}
                </div>
            )}

            {/* The rest of the workspace's menu as tiles (SIGN_IN_PLAN ID5). */}
            <WorkspaceTiles skip={tiles.map((tile) => tile.href)} className="mb-6" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">{t.home_intro || 'Attendance, exams, invoices, course progress and Hifz at a glance.'}</p>
                {/* `flex-wrap` on the row outside this one is not enough: this
                    inner group is a single unwrappable unit, so six links and a
                    CSV button ran to 492px inside a 393px phone and pushed the
                    whole portal sideways under the thumb (STATUS §5em). */}
                <div className="flex flex-wrap items-center gap-2 text-sm">
                    {extras.map((section) => (
                        <a key={section.key} className="chip-link" href={section.href}>{section.label}</a>
                    ))}
                    <a className="btn-secondary" href={csvUrl}>{t.export_csv || 'Export CSV'}</a>
                </div>
            </div>
            {students.length === 0 && (
                <p className="text-sm text-gray-600">{t.none_students || 'No student or linked children.'}</p>
            )}
            <div className="space-y-6">
                {students.map((student) => (
                    <StudentCard
                        key={student.id}
                        student={student}
                        t={t}
                        learn={learn}
                        labels={labels}
                    />
                ))}
            </div>
        </AppShell>
    );
}
