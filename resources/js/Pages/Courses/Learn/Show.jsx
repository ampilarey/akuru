import { router, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Show({
    course,
    enrollment,
    modules,
    upcoming_sessions = [],
    activities = [],
    assessments = [],
    offering = null,
    certificate = null,
}) {
    const t = usePage().props.i18n?.learn || {};

    return (
        <AppShell title={course.title}>
            <p className="mb-4 text-sm text-gray-600">
                {enrollment ? `${enrollment.progress_percentage}%` : (t.preview_only || 'Preview only — enroll to track progress.')}
                {/* §24: which offering. A student in one of several batches
                    could not tell from this screen which one this was. */}
                {offering && (
                    <span className="ms-2">
                        · {offering.title}
                        <span className="ms-1 text-gray-500">({(offering.delivery_mode || '').replaceAll('_', ' ')})</span>
                    </span>
                )}
            </p>

            {/* §24 "Certificate eligibility status". The rules engine existed
                and ran only when an admin issued the certificate, so a student
                could not see whether they were on track — or what was missing,
                which is the part they can act on. */}
            {certificate && (
                <section
                    className={`mb-4 rounded-lg border p-4 text-sm ${
                        certificate.issued
                            ? 'border-green-200 bg-green-50 text-green-900'
                            : certificate.eligible
                                ? 'border-green-200 bg-green-50 text-green-900'
                                : 'border-gray-200 bg-white text-gray-700'
                    }`}
                >
                    <h2 className="mb-1 font-medium">{certificate.template}</h2>
                    {certificate.issued ? (
                        <p>
                            {t.certificate_issued || 'Issued.'}{' '}
                            {certificate.certificate_number && (
                                <span className="font-mono text-xs">{certificate.certificate_number}</span>
                            )}
                        </p>
                    ) : certificate.eligible ? (
                        <p>{t.certificate_eligible || 'You have met the requirements for this certificate.'}</p>
                    ) : (
                        <>
                            <p className="mb-1">{t.certificate_outstanding || 'Still needed for this certificate:'}</p>
                            <ul className="list-inside list-disc">
                                {(certificate.reasons || []).map((reason) => (
                                    <li key={reason}>{reason}</li>
                                ))}
                            </ul>
                        </>
                    )}
                </section>
            )}
            {!enrollment && (
                <div className="mb-4">
                    <button type="button" className="btn-primary" onClick={() => router.post(`/learn/courses/${course.id}/enroll`)}>{t.enroll || 'Enroll'}</button>
                </div>
            )}
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
            {assessments.length > 0 && (
                <section className="mb-4 rounded-lg border bg-white p-4">
                    <h2 className="mb-2 font-medium">{t.assessments || 'Assessments'}</h2>
                    <ul className="space-y-2 text-sm">
                        {assessments.map((row) => (
                            <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 border-t pt-2 first:border-t-0 first:pt-0">
                                <span>{row.title} <span className="text-xs uppercase text-gray-500">{row.assessment_type}</span></span>
                                {enrollment ? (
                                    <a className="text-[#7C2D37] hover:underline" href={`/learn/assessments/${row.id}`}>{t.open || 'Open'}</a>
                                ) : (
                                    <span className="text-xs text-gray-400">{t.enroll || 'Enroll'}</span>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}
            {activities.length > 0 && (
                <section className="mb-4 rounded-lg border bg-white p-4">
                    <h2 className="mb-2 font-medium">{t.activities || 'Activities'}</h2>
                    <ul className="space-y-2 text-sm">
                        {activities.map((row) => (
                            <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 border-t pt-2 first:border-t-0 first:pt-0">
                                <span>{row.title} <span className="text-xs uppercase text-gray-500">{row.pattern}</span></span>
                                {enrollment ? (
                                    <a className="text-[#7C2D37] hover:underline" href={`/learn/activities/${row.id}`}>{t.open || 'Open'}</a>
                                ) : (
                                    <span className="text-xs text-gray-400">{t.enroll || 'Enroll'}</span>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}
            <div className="space-y-4">
                {modules.map((module) => (
                    <section key={module.id} className="rounded-lg border bg-white p-4">
                        <h2 className="mb-2 font-medium">{module.title}</h2>
                        {module.lessons.length === 0 && <p className="text-sm text-gray-500">{t.no_lessons || 'No published lessons yet.'}</p>}
                        <ul className="space-y-2 text-sm">
                            {module.lessons.map((lesson) => (
                                <li key={lesson.id} className="flex flex-wrap items-center justify-between gap-2 border-t pt-2">
                                    <span>
                                        {lesson.title}
                                        {lesson.is_preview && <span className="ms-2 text-xs uppercase text-gray-500">{t.preview || 'preview'}</span>}
                                        <span className="ms-2 text-xs uppercase text-gray-500">{lesson.status}</span>
                                    </span>
                                    {lesson.unlocked ? (
                                        <a className="text-[#7C2D37] hover:underline" href={`/learn/lessons/${lesson.id}`}>{t.open || 'Open'}</a>
                                    ) : (
                                        <span className="text-xs text-gray-400">{t.locked || 'Locked'}</span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>
        </AppShell>
    );
}
