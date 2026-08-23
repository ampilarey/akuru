import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Wizard({ years, sourceYearId, targetYearId, sourceClasses, targetClasses, report }) {
    const form = useForm({
        source_year_id: sourceYearId || '',
        target_year_id: targetYearId || '',
        class_map: {},
        overrides: {},
    });

    return (
        <AppShell title="Promotion wizard">
            <form className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2">
                <label className="grid gap-1 text-sm">
                    <span>Source year</span>
                    <select className="form-input" value={form.data.source_year_id} onChange={(e) => form.setData('source_year_id', e.target.value)}>
                        <option value="">—</option>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                </label>
                <label className="grid gap-1 text-sm">
                    <span>Target year</span>
                    <select className="form-input" value={form.data.target_year_id} onChange={(e) => form.setData('target_year_id', e.target.value)}>
                        <option value="">—</option>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                </label>
                {sourceClasses.map((source) => (
                    <label key={source.id} className="grid gap-1 text-sm">
                        <span>Map {source.name}</span>
                        <select
                            className="form-input"
                            value={form.data.class_map[source.id] || ''}
                            onChange={(e) => form.setData('class_map', { ...form.data.class_map, [source.id]: e.target.value })}
                        >
                            <option value="">—</option>
                            {targetClasses.map((target) => <option key={target.id} value={target.id}>{target.name}</option>)}
                        </select>
                    </label>
                ))}
                <div className="md:col-span-2 flex gap-3">
                    <button type="button" className="btn-secondary" onClick={() => form.post('/academics/promotion/dry-run')}>Dry-run</button>
                    <button type="button" className="btn-primary" onClick={() => form.post('/academics/promotion')}>Confirm promotion</button>
                </div>
            </form>

            {report && (
                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-left">
                            <tr>
                                <th className="px-3 py-2">Student</th>
                                <th className="px-3 py-2">Outcome</th>
                                <th className="px-3 py-2">Target class</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(report.outcomes || []).map((row) => (
                                <tr key={row.student_id} className="border-t">
                                    <td className="px-3 py-2">{row.student_id}</td>
                                    <td className="px-3 py-2">{row.outcome}</td>
                                    <td className="px-3 py-2">{row.target_class_id || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AppShell>
    );
}
