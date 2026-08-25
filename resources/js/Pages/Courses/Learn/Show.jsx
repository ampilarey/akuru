import { router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Show({ course, enrollment, modules }) {
    return (
        <AppShell title={course.title}>
            <p className="mb-4 text-sm text-gray-600">
                {enrollment ? `${enrollment.progress_percentage}% complete` : 'Preview only — enroll to track progress.'}
            </p>
            {!enrollment && (
                <div className="mb-4">
                    <button type="button" className="btn-primary" onClick={() => router.post(`/learn/courses/${course.id}/enroll`)}>Enroll</button>
                </div>
            )}
            <div className="space-y-4">
                {modules.map((module) => (
                    <section key={module.id} className="rounded-lg border bg-white p-4">
                        <h2 className="mb-2 font-medium">{module.title}</h2>
                        {module.lessons.length === 0 && <p className="text-sm text-gray-500">No published lessons yet.</p>}
                        <ul className="space-y-2 text-sm">
                            {module.lessons.map((lesson) => (
                                <li key={lesson.id} className="flex flex-wrap items-center justify-between gap-2 border-t pt-2">
                                    <span>
                                        {lesson.title}
                                        {lesson.is_preview && <span className="ms-2 text-xs uppercase text-gray-500">preview</span>}
                                        <span className="ms-2 text-xs uppercase text-gray-500">{lesson.status}</span>
                                    </span>
                                    {lesson.unlocked ? (
                                        <a className="text-[#7C2D37] hover:underline" href={`/learn/lessons/${lesson.id}`}>Open</a>
                                    ) : (
                                        <span className="text-xs text-gray-400">Locked</span>
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
