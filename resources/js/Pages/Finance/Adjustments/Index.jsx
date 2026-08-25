import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ years, yearId, studentId, adjustments, suggestions, types, bases, appliesTo, statuses }) {
    const form = useForm({
        academic_year_id: yearId || '',
        student_id: studentId || '',
        type: types[0] || 'sibling_discount',
        basis: 'percent',
        value: '10',
        applies_to: 'all_items',
        status: 'approved',
        notes: '',
    });

    return (
        <AppShell title="Fee adjustments">
            <p className="mb-3 text-sm text-gray-600">
                School-fee reductions only. Promotional Commerce codes live in L4.
            </p>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-2">
                    {years.map((year) => (
                        <button
                            key={year.id}
                            type="button"
                            className={`rounded px-3 py-1 text-sm ${String(year.id) === String(yearId) ? 'bg-[#7C2D37] text-white' : 'border bg-white'}`}
                            onClick={() => router.get('/finance/adjustments', { academic_year_id: year.id, student_id: studentId || '' })}
                        >
                            {year.name}
                        </button>
                    ))}
                </div>
                <a className="btn-secondary" href={`/finance/adjustments/export?academic_year_id=${yearId || ''}`}>Export CSV</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/finance/adjustments', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <input className="form-input" placeholder="Student id" value={form.data.student_id} onChange={(e) => form.setData('student_id', e.target.value)} />
                <select className="form-input" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                    {types.map((type) => <option key={type} value={type}>{type}</option>)}
                </select>
                <select className="form-input" value={form.data.basis} onChange={(e) => form.setData('basis', e.target.value)}>
                    {bases.map((basis) => <option key={basis} value={basis}>{basis}</option>)}
                </select>
                <input className="form-input" placeholder="Value" value={form.data.value} onChange={(e) => form.setData('value', e.target.value)} />
                <select className="form-input" value={form.data.applies_to} onChange={(e) => form.setData('applies_to', e.target.value)}>
                    {appliesTo.map((value) => <option key={value} value={value}>{value}</option>)}
                </select>
                <select className="form-input" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                </select>
                <button type="submit" className="btn-primary md:col-span-3" disabled={form.processing}>Save adjustment</button>
                {form.errors.value && <span className="text-xs text-red-600">{form.errors.value}</span>}
            </form>

            {suggestions.length > 0 && (
                <div className="mb-4 rounded-lg border bg-white p-4 text-sm">
                    <div className="mb-2 font-medium">Sibling suggestions</div>
                    {suggestions.map((row) => (
                        <div key={row.student_id}>{row.student_name} — {row.reason}</div>
                    ))}
                </div>
            )}

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Value</th>
                            <th className="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {adjustments.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No adjustments yet.</td></tr>
                        )}
                        {adjustments.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.type}</td>
                                <td className="px-3 py-2">{row.value} {row.basis}</td>
                                <td className="px-3 py-2">{row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
