import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ awards, students, issued, years, terms }) {
    const award = useForm({
        title: '',
        level: 'school',
        description: '',
        active: true,
    });
    const issue = useForm({
        award_id: awards[0]?.id || '',
        student_ids: students[0] ? [students[0].id] : [],
        academic_year_id: years[0]?.id || '',
        term_id: '',
        awarded_date: new Date().toISOString().slice(0, 10),
        notes: '',
    });

    return (
        <AppShell title="Awards">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/exams/awards/export">Export CSV</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    award.post('/exams/awards', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <input className="form-input" placeholder="Title" value={award.data.title} onChange={(e) => award.setData('title', e.target.value)} />
                <select className="form-input" value={award.data.level} onChange={(e) => award.setData('level', e.target.value)}>
                    <option value="school">School</option>
                    <option value="class">Class</option>
                </select>
                <input className="form-input" placeholder="Description" value={award.data.description} onChange={(e) => award.setData('description', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={award.processing}>Create award</button>
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    issue.transform((data) => ({
                        ...data,
                        student_ids: Array.isArray(data.student_ids) ? data.student_ids : [data.student_ids],
                    }));
                    issue.post('/exams/awards/issue', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <select className="form-input" value={issue.data.award_id} onChange={(e) => issue.setData('award_id', e.target.value)}>
                    {awards.map((row) => <option key={row.id} value={row.id}>{row.title}</option>)}
                </select>
                <select
                    className="form-input"
                    multiple
                    value={issue.data.student_ids}
                    onChange={(e) => issue.setData('student_ids', Array.from(e.target.selectedOptions).map((option) => option.value))}
                >
                    {students.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                </select>
                <select className="form-input" value={issue.data.academic_year_id} onChange={(e) => issue.setData('academic_year_id', e.target.value)}>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <input className="form-input" type="date" value={issue.data.awarded_date} onChange={(e) => issue.setData('awarded_date', e.target.value)} />
                <button type="submit" className="btn-secondary" disabled={issue.processing}>Issue certificates</button>
            </form>

            <form className="mb-4 flex flex-wrap gap-2 rounded-lg border bg-white p-4" method="get" action="/exams/awards/id-card">
                <select className="form-input" name="student_id" defaultValue={students[0]?.id || ''}>
                    {students.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                </select>
                <button type="submit" className="btn-secondary">ID card</button>
                <button type="submit" className="btn-secondary" formAction="/exams/awards/transfer">Transfer cert</button>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Award</th>
                            <th className="px-3 py-2">Level</th>
                            <th className="px-3 py-2">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        {issued.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No awards issued yet.</td></tr>
                        )}
                        {issued.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.award}</td>
                                <td className="px-3 py-2">{row.level}</td>
                                <td className="px-3 py-2">{row.awarded_date}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
