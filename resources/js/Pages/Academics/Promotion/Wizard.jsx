import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

export default function Wizard({ years, sourceYearId, targetYearId, sourceClasses, targetClasses, report, t = {} }) {
    const form = useForm({
        source_year_id: sourceYearId || '',
        target_year_id: targetYearId || '',
        class_map: {},
        overrides: {},
    });

    // In the page's language (BACKLOG C21, slice OA2). An outcome is a code,
    // named here. The report names each pupil and class; it printed their
    // ids, which nobody in an office can read.
    return (
        <AppShell title={t.promotion_title || 'Promotion wizard'}>
            <form className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2">
                <label className="grid gap-1 text-sm">
                    <span>{t.promotion_source || 'Source year'}</span>
                    <select className="form-input" value={form.data.source_year_id} onChange={(e) => form.setData('source_year_id', e.target.value)}>
                        <option value="">—</option>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                </label>
                <label className="grid gap-1 text-sm">
                    <span>{t.promotion_target || 'Target year'}</span>
                    <select className="form-input" value={form.data.target_year_id} onChange={(e) => form.setData('target_year_id', e.target.value)}>
                        <option value="">—</option>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                </label>
                {sourceClasses.map((source) => (
                    <label key={source.id} className="grid gap-1 text-sm">
                        <span>{(t.promotion_map || 'Map :class').replace(':class', `${source.name} ${source.section || ''}`.trim())}</span>
                        <select
                            className="form-input"
                            value={form.data.class_map[source.id] || ''}
                            onChange={(e) => form.setData('class_map', { ...form.data.class_map, [source.id]: e.target.value })}
                        >
                            <option value="">—</option>
                            {targetClasses.map((target) => <option key={target.id} value={target.id}>{`${target.name} ${target.section || ''}`.trim()}</option>)}
                        </select>
                    </label>
                ))}
                <div className="md:col-span-2 flex gap-3">
                    <button type="button" className="btn-secondary" onClick={() => form.post('/academics/promotion/dry-run')}>{t.promotion_dry_run || 'Dry-run'}</button>
                    <button type="button" className="btn-primary" onClick={() => form.post('/academics/promotion')}>{t.promotion_confirm || 'Confirm promotion'}</button>
                </div>
                <FormErrors errors={form.errors} />
            </form>

            {report && (
                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">{t.col_student || 'Student'}</th>
                                <th className="px-3 py-2">{t.promotion_col_outcome || 'Outcome'}</th>
                                <th className="px-3 py-2">{t.promotion_col_target || 'Target class'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(report.outcomes || []).map((row) => (
                                <tr key={row.student_id} className="border-t">
                                    <td className="px-3 py-2">{row.student_name || row.student_id}</td>
                                    <td className="px-3 py-2">{t[`promotion_outcome_${row.outcome}`] || row.outcome}</td>
                                    <td className="px-3 py-2">{row.target_class_name || row.target_class_id || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AppShell>
    );
}
