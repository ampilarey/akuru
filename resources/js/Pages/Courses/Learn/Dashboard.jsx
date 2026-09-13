import { usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * SPEC §24's student dashboard, which named twelve things and showed five.
 *
 * The old card read `40% · 3 · active` — the middle number was
 * `completed_lessons` interpolated with no label at all, so the one figure a
 * student most wants ("how much is left?") was both unlabelled and, since
 * nothing sent a total, unanswerable.
 */
const STATUS_LABELS = {
    not_started: 'Not started',
    in_progress: 'In progress',
    submitted: 'Awaiting marking',
    scored: 'Marked',
};

function Stat({ label, value }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-gray-500">{label}</dt>
            <dd className="text-sm font-medium">{value}</dd>
        </div>
    );
}

function AssessmentRow({ row, t }) {
    const status = STATUS_LABELS[row.status] || row.status;
    const mark = row.score !== null && row.score !== undefined
        ? `${row.score}${row.max_score !== null && row.max_score !== undefined ? ` / ${row.max_score}` : ''}`
        : null;

    return (
        <li className="flex flex-wrap items-center justify-between gap-2 border-t py-2 text-sm">
            <span>
                <a className="text-[#7C2D37] hover:underline" href={`/learn/assessments/${row.id}`}>{row.title}</a>
                <span className="ms-2 text-gray-600">{status}</span>
            </span>
            <span className="text-gray-700">
                {mark}
                {/* §19's `show_results`: a teacher who has not published marks
                    is a reason, not a blank. Saying nothing here is how the
                    missing score reads as a bug. */}
                {!mark && row.show_results === false && (
                    <em className="text-gray-500">{t.marks_not_published || 'Marks not published'}</em>
                )}
            </span>
        </li>
    );
}

export default function Dashboard({ student, enrollments, upcoming_sessions = [], certificates = [] }) {
    const t = usePage().props.i18n?.learn || {};

    return (
        <AppShell title={t.dashboard_title || 'My learning'}>
            {!student && <p className="text-sm text-gray-600">{t.no_profile || 'No student profile is linked to this account.'}</p>}
            {student && enrollments.length === 0 && <p className="text-sm text-gray-600">{t.not_enrolled || 'You are not enrolled yet. Browse the learn catalog.'}</p>}
            <div className="mb-4 flex flex-wrap gap-3">
                <a className="text-sm text-[#7C2D37] hover:underline" href="/learn/catalog">{t.browse || 'Browse courses'}</a>
                <a className="text-sm text-[#7C2D37] hover:underline" href="/learn/schedule">{t.schedule || 'Schedule'}</a>
                <a className="text-sm text-[#7C2D37] hover:underline" href="/learn/arabic-report">{t.arabic_report || 'Arabic skills'}</a>
            </div>
            {upcoming_sessions.length > 0 && (
                <section className="mb-4 rounded-lg border bg-white p-4">
                    <h2 className="mb-2 font-medium">{t.upcoming_sessions || 'Upcoming sessions'}</h2>
                    <ul className="space-y-1 text-sm">
                        {upcoming_sessions.map((row) => (
                            <li key={row.id}>{row.title} · {row.starts_at}{row.location_name ? ` · ${row.location_name}` : ''}</li>
                        ))}
                    </ul>
                </section>
            )}
            {/* §24 "Certificates". §39 has issued these all along and every
                route to one was staff-only, so this is the first place the
                person named on the certificate can open it. */}
            {certificates.length > 0 && (
                <section className="mb-4 rounded-lg border bg-white p-4">
                    <h2 className="mb-2 font-medium">{t.certificates || 'Certificates'}</h2>
                    <ul className="space-y-2 text-sm">
                        {certificates.map((row) => (
                            <li key={row.id} className="flex flex-wrap items-center gap-3">
                                <span>
                                    {row.template}
                                    {row.course_name ? ` · ${row.course_name}` : ''}
                                    {row.offering_name ? ` · ${row.offering_name}` : ''}
                                    {row.grade ? ` · ${row.grade}` : ''}
                                    {row.completion_date ? ` · ${row.completion_date}` : ''}
                                </span>
                                {row.downloadable && (
                                    <a className="text-[#7C2D37] hover:underline" href={`/learn/certificates/${row.id}`}>
                                        {t.open_certificate || 'Open'}
                                    </a>
                                )}
                                <a className="text-[#7C2D37] hover:underline" href={row.verify_url}>
                                    {t.verify || 'Verify'} {row.certificate_number}
                                </a>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
            <div className="space-y-3">
                {enrollments.map((row) => (
                    <article key={row.id} className="rounded-lg border bg-white p-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="font-medium">{row.title}</h2>
                                {/* §24: "Offering title/mode if enrolled through
                                    an offering" — a student in one of several
                                    batches could not tell which this was. */}
                                {row.offering && (
                                    <p className="text-sm text-gray-600">
                                        {row.offering.title}
                                        {row.offering.delivery_mode_label ? ` · ${row.offering.delivery_mode_label}` : ''}
                                    </p>
                                )}
                                <p className="text-sm text-gray-600">{row.status}</p>
                            </div>
                            <div className="flex gap-3 text-sm">
                                <a className="text-[#7C2D37] hover:underline" href={`/learn/courses/${row.course_id}`}>{t.course || 'Course'}</a>
                                {row.continue_lesson_id && (
                                    <a className="text-[#7C2D37] hover:underline" href={`/learn/lessons/${row.continue_lesson_id}`}>{t.continue || 'Continue'} {row.continue_title}</a>
                                )}
                            </div>
                        </div>
                        <dl className="mt-3 grid grid-cols-2 gap-3 md:grid-cols-5">
                            <Stat label={t.progress || 'Progress'} value={`${row.progress_percentage}%`} />
                            <Stat
                                label={t.completed_lessons || 'Lessons done'}
                                value={`${row.completed_lessons} / ${row.total_lessons}`}
                            />
                            <Stat label={t.pending_lessons || 'Lessons left'} value={row.pending_lessons} />
                            <Stat label={t.pending_assessments || 'Assessments due'} value={row.pending_assessments} />
                            {/* §24 says attendance "where applicable"; the
                                server answers null when the offering schedules
                                no sessions, and a dash is the honest reading of
                                that — not 0%. */}
                            <Stat
                                label={t.attendance || 'Attendance'}
                                value={row.attendance_percent === null || row.attendance_percent === undefined
                                    ? '—'
                                    : `${row.attendance_percent}%`}
                            />
                        </dl>
                        {row.assessments.length > 0 && (
                            <ul className="mt-3">
                                {row.assessments.map((assessment) => (
                                    <AssessmentRow key={assessment.id} row={assessment} t={t} />
                                ))}
                            </ul>
                        )}
                        {/* §24 "Teacher feedback". `ReviewAttemptAction` has
                            been storing it since the review slice, and only the
                            teacher's own screen ever read it back. */}
                        {row.feedback.length > 0 && (
                            <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
                                <h3 className="mb-1 text-xs font-medium uppercase tracking-wide text-amber-900">
                                    {t.teacher_feedback || 'Teacher feedback'}
                                </h3>
                                <ul className="space-y-1 text-sm text-amber-900">
                                    {row.feedback.map((note, index) => (
                                        <li key={index}><strong>{note.title}:</strong> {note.feedback}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </article>
                ))}
            </div>
        </AppShell>
    );
}
