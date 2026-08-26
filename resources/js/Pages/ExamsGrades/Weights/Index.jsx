import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ years, classes, subjects, examTypes, schemes, resolve }) {
    const seededWeights = Object.fromEntries(
        examTypes.map((type) => [String(type.id), Number(type.default_weight) || 0]),
    );
    const form = useForm({
        academic_year_id: resolve.academic_year_id || years[0]?.id || '',
        class_id: '',
        subject_id: '',
        weights: seededWeights,
    });
    const sum = Object.values(form.data.weights || {}).reduce((total, value) => total + Number(value || 0), 0);

    return (
        <AppShell title="Assessment weights">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/exams/weights/export">Export CSV</a>
            </div>
            <form
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    const data = new FormData(e.currentTarget);
                    const params = new URLSearchParams({
                        academic_year_id: String(data.get('academic_year_id') || ''),
                        class_id: String(data.get('class_id') || ''),
                        subject_id: String(data.get('subject_id') || ''),
                    });
                    router.get(`/exams/weights?${params.toString()}`, {}, { preserveState: true });
                }}
            >
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Resolve year</span>
                    <select className="form-input w-full" name="academic_year_id" defaultValue={resolve.academic_year_id || ''}>
                        <option value="">—</option>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Class</span>
                    <select className="form-input w-full" name="class_id" defaultValue={resolve.class_id || ''}>
                        <option value="">Year default</option>
                        {classes.map((row) => <option key={row.id} value={row.id}>{row.name} {row.section}</option>)}
                    </select>
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Subject</span>
                    <select className="form-input w-full" name="subject_id" defaultValue={resolve.subject_id || ''}>
                        <option value="">Class / year default</option>
                        {subjects.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                    </select>
                </label>
                <p className="md:col-span-3 text-sm text-gray-600">
                    Resolved scheme: {resolve.scheme
                        ? `id ${resolve.scheme.id} (year ${resolve.scheme.academic_year_id}, class ${resolve.scheme.class_id ?? 'default'}, subject ${resolve.scheme.subject_id ?? 'default'})`
                        : 'none'}
                </p>
                <button type="submit" className="btn-secondary">Show resolved</button>
            </form>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/exams/weights', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <h2 className="md:col-span-3 font-semibold">Create year scheme</h2>
                <p className="md:col-span-3 text-sm text-gray-600">
                    Type percents must add to 100. Fields start from each exam type’s default weight (Midterm 30, Final 40, Quiz 10, Assignment 10, Practical 5, Oral 5).
                </p>
                {form.errors.weights && <p className="md:col-span-3 text-sm text-red-600">{form.errors.weights}</p>}
                {form.errors.academic_year_id && <p className="md:col-span-3 text-sm text-red-600">{form.errors.academic_year_id}</p>}
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Year</span>
                    <select className="form-input w-full" value={form.data.academic_year_id} onChange={(e) => form.setData('academic_year_id', e.target.value)}>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Class (optional)</span>
                    <select className="form-input w-full" value={form.data.class_id} onChange={(e) => form.setData('class_id', e.target.value)}>
                        <option value="">Year default</option>
                        {classes.map((row) => <option key={row.id} value={row.id}>{row.name} {row.section}</option>)}
                    </select>
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Subject (optional)</span>
                    <select className="form-input w-full" value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                        <option value="">Class / year default</option>
                        {subjects.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                    </select>
                </label>
                {examTypes.map((type) => (
                    <label key={type.id} className="text-sm">
                        <span className="mb-1 block text-gray-600">{type.name} %</span>
                        <input
                            type="number"
                            min="0"
                            max="100"
                            className="form-input w-full"
                            value={form.data.weights[String(type.id)] ?? 0}
                            onChange={(e) => form.setData('weights', {
                                ...form.data.weights,
                                [String(type.id)]: e.target.value === '' ? 0 : Number(e.target.value),
                            })}
                        />
                    </label>
                ))}
                <p className={`md:col-span-3 text-sm ${sum === 100 ? 'text-green-800' : 'text-red-700'}`}>
                    Sum: {sum} / 100
                </p>
                <button type="submit" className="btn-primary" disabled={form.processing || sum !== 100}>Save scheme</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Scope</th>
                            <th className="px-3 py-2">Weights</th>
                        </tr>
                    </thead>
                    <tbody>
                        {schemes.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">year {row.academic_year_id} / class {row.class_id ?? '—'} / subject {row.subject_id ?? '—'}</td>
                                <td className="px-3 py-2 font-mono text-xs">{JSON.stringify(row.weights)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
