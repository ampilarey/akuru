import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * Assessment weight schemes: how much each exam type counts toward a term.
 * Every word is the `exams` book's (slice EG1, STATUS §5qj). A scheme names
 * its year, class and subject, and its weights by exam type — it printed ids
 * (*year 3 / class — / subject —*) and the stored JSON.
 */
export default function Index({ years, classes, subjects, examTypes, schemes, resolve, t = {} }) {
    const locale = usePage().props.locale || 'en';
    const named = (row) => ({ dv: row?.name_dhivehi, ar: row?.name_arabic }[locale]) || row?.name;
    const yearName = (id) => years.find((row) => `${row.id}` === `${id}`)?.name ?? id;
    const className = (id) => {
        const row = classes.find((entry) => `${entry.id}` === `${id}`);
        return row ? `${row.name} ${row.section ?? ''}`.trim() : id;
    };
    const subjectName = (id) => named(subjects.find((row) => `${row.id}` === `${id}`)) ?? id;
    const scope = (scheme) => [
        yearName(scheme.academic_year_id),
        scheme.class_id ? className(scheme.class_id) : (t.weights_year_default || 'Year default'),
        scheme.subject_id ? subjectName(scheme.subject_id) : (t.weights_class_year_default || 'Class / year default'),
    ].join(' / ');
    const weightsOf = (scheme) => Object.entries(scheme.weights || {})
        .map(([typeId, percent]) => `${named(examTypes.find((type) => `${type.id}` === `${typeId}`)) ?? typeId} ${percent}%`)
        .join(' · ');

    const seededWeights = Object.fromEntries(
        examTypes.map((type) => [`${type.id}`, Number(type.default_weight) || 0]),
    );
    const form = useForm({
        academic_year_id: resolve.academic_year_id || years[0]?.id || '',
        class_id: '',
        subject_id: '',
        weights: seededWeights,
    });
    const sum = Object.values(form.data.weights || {}).reduce((total, value) => total + Number(value || 0), 0);
    const refused = form.errors.weights || form.errors.academic_year_id || form.errors.class_id || form.errors.subject_id;

    return (
        <AppShell title={t.weights_title || 'Assessment weights'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/exams/weights/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    const data = new FormData(e.currentTarget);
                    const params = new URLSearchParams({
                        academic_year_id: `${data.get('academic_year_id') || ''}`,
                        class_id: `${data.get('class_id') || ''}`,
                        subject_id: `${data.get('subject_id') || ''}`,
                    });
                    router.get(`/exams/weights?${params.toString()}`, {}, { preserveState: true });
                }}
            >
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.weights_resolve_year || 'Resolve year'}</span>
                    <select className="form-input w-full" name="academic_year_id" defaultValue={resolve.academic_year_id || ''}>
                        <option value="">—</option>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.class || 'Class'}</span>
                    <select className="form-input w-full" name="class_id" defaultValue={resolve.class_id || ''}>
                        <option value="">{t.weights_year_default || 'Year default'}</option>
                        {classes.map((row) => <option key={row.id} value={row.id}>{row.name} {row.section}</option>)}
                    </select>
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.subject || 'Subject'}</span>
                    <select className="form-input w-full" name="subject_id" defaultValue={resolve.subject_id || ''}>
                        <option value="">{t.weights_class_year_default || 'Class / year default'}</option>
                        {subjects.map((row) => <option key={row.id} value={row.id}>{named(row)}</option>)}
                    </select>
                </label>
                <p className="md:col-span-3 text-sm text-gray-600">
                    {resolve.scheme
                        ? (t.weights_resolved || 'Resolved scheme: :scope').replace(':scope', scope(resolve.scheme))
                        : (t.weights_resolved_none || 'Resolved scheme: none')}
                </p>
                <button type="submit" className="btn-secondary">{t.weights_show_resolved || 'Show resolved'}</button>
            </form>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/exams/weights', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <h2 className="md:col-span-3 font-semibold">{t.weights_create || 'Create year scheme'}</h2>
                <p className="md:col-span-3 text-sm text-gray-600">
                    {t.weights_hint || 'Type percents must add to 100. Fields start from each exam type’s default weight.'}
                </p>
                {refused && <p className="md:col-span-3 text-sm text-red-600">{refused}</p>}
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.year || 'Year'}</span>
                    <select className="form-input w-full" value={form.data.academic_year_id} onChange={(e) => form.setData('academic_year_id', e.target.value)}>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.weights_class_optional || 'Class (optional)'}</span>
                    <select className="form-input w-full" value={form.data.class_id} onChange={(e) => form.setData('class_id', e.target.value)}>
                        <option value="">{t.weights_year_default || 'Year default'}</option>
                        {classes.map((row) => <option key={row.id} value={row.id}>{row.name} {row.section}</option>)}
                    </select>
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.weights_subject_optional || 'Subject (optional)'}</span>
                    <select className="form-input w-full" value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                        <option value="">{t.weights_class_year_default || 'Class / year default'}</option>
                        {subjects.map((row) => <option key={row.id} value={row.id}>{named(row)}</option>)}
                    </select>
                </label>
                {examTypes.map((type) => (
                    <label key={type.id} className="text-sm">
                        <span className="mb-1 block text-gray-600">{named(type)} %</span>
                        <input
                            type="number"
                            min="0"
                            max="100"
                            className="form-input w-full"
                            value={form.data.weights[`${type.id}`] ?? 0}
                            onChange={(e) => form.setData('weights', {
                                ...form.data.weights,
                                [`${type.id}`]: e.target.value === '' ? 0 : Number(e.target.value),
                            })}
                        />
                    </label>
                ))}
                <p className={`md:col-span-3 text-sm ${sum === 100 ? 'text-green-800' : 'text-red-700'}`}>
                    {(t.weights_sum || 'Sum: :sum / 100').replace(':sum', sum)}
                </p>
                <button type="submit" className="btn-primary" disabled={form.processing || sum !== 100}>{t.weights_save || 'Save scheme'}</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.weights_col_scope || 'Scope'}</th>
                            <th className="px-3 py-2">{t.weights_col_weights || 'Weights'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {schemes.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{scope(row)}</td>
                                <td className="px-3 py-2 text-xs">{weightsOf(row)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
