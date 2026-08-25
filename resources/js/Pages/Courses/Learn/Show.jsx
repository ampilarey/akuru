import { router, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Show({ course, enrollment, modules, upcoming_sessions = [], activities = [], assessments = [] }) {
    const t = usePage().props.i18n?.learn || {};

    return (
        <AppShell title={course.title}>
            <p className="mb-4 text-sm text-gray-600">
                {enrollment ? `${enrollment.progress_percentage}%` : (t.preview_only || 'Preview only — enroll to track progress.')}
            </p>
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
