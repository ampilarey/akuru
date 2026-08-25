import AppShell from '../../../Layouts/AppShell';

export default function Dashboard({ student, enrollments }) {
    return (
        <AppShell title="My learning">
            {!student && <p className="text-sm text-gray-600">No student profile is linked to this account.</p>}
            {student && enrollments.length === 0 && <p className="text-sm text-gray-600">You are not enrolled yet. Browse the learn catalog.</p>}
            <div className="mb-4">
                <a className="text-sm text-[#7C2D37] hover:underline" href="/learn/catalog">Browse courses</a>
            </div>
            <div className="space-y-3">
                {enrollments.map((row) => (
                    <article key={row.id} className="rounded-lg border bg-white p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="font-medium">{row.title}</h2>
                                <p className="text-sm text-gray-600">{row.progress_percentage}% · {row.completed_lessons} completed · {row.status}</p>
                            </div>
                            <div className="flex gap-3 text-sm">
                                <a className="text-[#7C2D37] hover:underline" href={`/learn/courses/${row.course_id}`}>Course</a>
                                {row.continue_lesson_id && (
                                    <a className="text-[#7C2D37] hover:underline" href={`/learn/lessons/${row.continue_lesson_id}`}>Continue {row.continue_title}</a>
                                )}
                            </div>
                        </div>
                    </article>
                ))}
            </div>
        </AppShell>
    );
}
