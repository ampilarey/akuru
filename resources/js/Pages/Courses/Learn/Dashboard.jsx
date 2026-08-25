import { usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Dashboard({ student, enrollments, upcoming_sessions = [] }) {
    const t = usePage().props.i18n?.learn || {};

    return (
        <AppShell title={t.dashboard_title || 'My learning'}>
            {!student && <p className="text-sm text-gray-600">{t.no_profile || 'No student profile is linked to this account.'}</p>}
            {student && enrollments.length === 0 && <p className="text-sm text-gray-600">{t.not_enrolled || 'You are not enrolled yet. Browse the learn catalog.'}</p>}
            <div className="mb-4">
                <a className="text-sm text-[#7C2D37] hover:underline" href="/learn/catalog">{t.browse || 'Browse courses'}</a>
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
            <div className="space-y-3">
                {enrollments.map((row) => (
                    <article key={row.id} className="rounded-lg border bg-white p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="font-medium">{row.title}</h2>
                                <p className="text-sm text-gray-600">{row.progress_percentage}% · {row.completed_lessons} · {row.status}</p>
                            </div>
                            <div className="flex gap-3 text-sm">
                                <a className="text-[#7C2D37] hover:underline" href={`/learn/courses/${row.course_id}`}>{t.course || 'Course'}</a>
                                {row.continue_lesson_id && (
                                    <a className="text-[#7C2D37] hover:underline" href={`/learn/lessons/${row.continue_lesson_id}`}>{t.continue || 'Continue'} {row.continue_title}</a>
                                )}
                            </div>
                        </div>
                    </article>
                ))}
            </div>
        </AppShell>
    );
}
