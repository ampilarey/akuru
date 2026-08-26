import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

function exportHref(filters) {
    const params = new URLSearchParams();
    Object.entries(filters || {}).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, String(value));
        }
    });
    const qs = params.toString();
    return qs ? `/catalog/reviews/export?${qs}` : '/catalog/reviews/export';
}

function ReviewRow({ row }) {
    const form = useForm({
        kind: row.kind,
        attempt_id: row.id,
        score: row.score || 0,
        max_score: row.max_score || 1,
        feedback: row.feedback || '',
    });
    const waiting = row.waiting_hours == null
        ? ''
        : (row.waiting_hours >= 24 ? `${Math.floor(row.waiting_hours / 24)}d` : `${row.waiting_hours}h`);

    return (
        <article className="rounded-lg border bg-white p-4">
            <h2 className="mb-1 font-medium">{row.title} <span className="text-xs uppercase text-gray-500">{row.kind}</span></h2>
            <p className="mb-1 text-sm text-gray-700">{row.student_name || 'Student'} · {row.course_title || 'Course'}{waiting ? ` · waiting ${waiting}` : ''}</p>
            <p className="mb-3 text-sm text-gray-600">{row.prompt || 'Teacher-marked submission'}</p>
            <details className="mb-3">
                <summary className="cursor-pointer text-sm text-[#7C2D37]">Submission</summary>
                <pre className="mt-2 overflow-x-auto rounded bg-[#F9F4EE] p-3 text-xs">{JSON.stringify(row.answers, null, 2)}</pre>
            </details>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/catalog/reviews', { preserveScroll: true });
                }}
                className="grid gap-3 md:grid-cols-4"
            >
                <input className="form-input" type="number" min="0" value={form.data.score} onChange={(e) => form.setData('score', e.target.value)} aria-label="Score" />
                <input className="form-input" type="number" min="1" value={form.data.max_score} onChange={(e) => form.setData('max_score', e.target.value)} aria-label="Max score" />
                <input className="form-input md:col-span-2" placeholder="Feedback" value={form.data.feedback} onChange={(e) => form.setData('feedback', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Score and release</button>
            </form>
        </article>
    );
}

function ReportTable({ rows, empty }) {
    return (
        <div className="overflow-x-auto rounded-lg border bg-white">
            <table className="min-w-full text-sm">
                <thead className="bg-[#F3EBE0] text-start">
                    <tr>
                        <th className="px-3 py-2">Student</th>
                        <th className="px-3 py-2">Course</th>
                        <th className="px-3 py-2">Item</th>
                        <th className="px-3 py-2">Score</th>
                        <th className="px-3 py-2">Reason</th>
                        <th className="px-3 py-2">Recommendation</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && (
                        <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{empty}</td></tr>
                    )}
                    {rows.map((row) => (
                        <tr key={`${row.kind}-${row.attempt_id}`} className="border-t">
                            <td className="px-3 py-2">{row.student_name}</td>
                            <td className="px-3 py-2">{row.course_title || '—'}</td>
                            <td className="px-3 py-2">{row.title} <span className="text-xs uppercase text-gray-500">{row.kind}</span></td>
                            <td className="px-3 py-2">{row.score}/{row.max_score} ({row.percent}%)</td>
                            <td className="px-3 py-2">{row.reason}</td>
                            <td className="px-3 py-2">{row.recommendation}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Reviews({
    rows = [],
    weaknesses = [],
    revisions = [],
    years = [],
    courses = [],
    filters = {},
    pending_count = 0,
    weak_item_count = 0,
    weak_student_count = 0,
}) {
    return (
        <AppShell title="Teacher review">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">
                    {pending_count} pending · {weak_student_count} weak students · {weak_item_count} weak items
                </p>
                <a className="btn-secondary" href={exportHref(filters)}>Export CSV</a>
            </div>
            <form method="get" action="/catalog/reviews" className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4">
                <select className="form-input" name="academic_year_id" defaultValue={filters.academic_year_id || ''}>
                    <option value="">All years</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <select className="form-input" name="course_id" defaultValue={filters.course_id || ''}>
                    <option value="">All courses</option>
                    {courses.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}
                </select>
                <input className="form-input" type="number" min="1" max="100" name="threshold" defaultValue={filters.threshold || 50} aria-label="Weakness threshold percent" />
                <button type="submit" className="btn-secondary">Filter</button>
            </form>

            <h2 className="mb-2 text-sm font-medium">Pending review</h2>
            {rows.length === 0 && <p className="mb-6 text-sm text-gray-500">No submitted work waiting for review.</p>}
            <div className="mb-8 space-y-3">
                {rows.map((row) => <ReviewRow key={`${row.kind}-${row.id}`} row={row} />)}
            </div>

            <h2 className="mb-2 text-sm font-medium">Weakness</h2>
            <p className="mb-2 text-xs text-gray-500">Latest scored attempt below the passing score, or below the percent threshold when no passing score is set.</p>
            <div className="mb-8">
                <ReportTable rows={weaknesses} empty="No weak scored attempts." />
            </div>

            <h2 className="mb-2 text-sm font-medium">Revision</h2>
            <p className="mb-2 text-xs text-gray-500">Retry the weak item when retakes remain; otherwise review with a teacher.</p>
            <ReportTable rows={revisions} empty="No revision recommendations." />
        </AppShell>
    );
}
