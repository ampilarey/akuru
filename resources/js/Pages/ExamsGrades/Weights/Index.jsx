import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ years, classes, subjects, examTypes, schemes, resolve }) {
    const emptyWeights = Object.fromEntries(examTypes.map((type) => [String(type.id), 0]));
    const form = useForm({
        academic_year_id: years[0]?.id || '',
        class_id: '',
        subject_id: '',
        weights: JSON.stringify(emptyWeights, null, 2),
    });

    return (
        <AppShell title="Assessment weights">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/exams/weights/export">Export CSV</a>
            </div>
            <form
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    const params = new URLSearchParams({
                        academic_year_id: String(resolve.academic_year_id || form.data.academic_year_id || ''),
                        class_id: String(resolve.class_id || ''),
                        subject_id: String(resolve.subject_id || ''),
                    });
                    router.get(`/exams/weights?${params.toString()}`, {}, { preserveState: true });
                }}
            >
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Resolve year</span>
                    <select className="form-input w-full" defaultValue={resolve.academic_year_id || ''} onChange={(e) => resolve.academic_year_id = e.target.value} name="academic_year_id">
                        <option value="">—</option>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
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
                    form.transform((data) => ({ ...data, weights: parseJson(data.weights) }));
                    form.post('/exams/weights', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Year</span>
                    <select className="form-input w-full" value={form.data.academic_year_id} onChange={(e) => form.setData('academic_year_id', e.target.value)}>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                    {form.errors.academic_year_id && <span className="text-xs text-red-600">{form.errors.academic_year_id}</span>}
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
                <label className="text-sm md:col-span-3">
                    <span className="mb-1 block text-gray-600">Weights JSON (exam_type_id → percent, sum 100)</span>
                    <textarea className="form-input w-full font-mono text-xs" rows={6} value={form.data.weights} onChange={(e) => form.setData('weights', e.target.value)} />
                    {form.errors.weights && <span className="text-xs text-red-600">{form.errors.weights}</span>}
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>Create scheme</button>
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

function parseJson(value) {
    try {
        return JSON.parse(value);
    } catch {
        return value;
    }
}
