import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ years, yearId, structures, structureId, invoices }) {
    const form = useForm({
        academic_year_id: yearId || '',
        fee_structure_id: structureId || structures[0]?.id || '',
        period_start: '2026-01-01',
        period_end: '2026-03-31',
        monthly_mode: 'per_month',
        include_optional: false,
    });

    const issueAll = () => {
        router.post('/finance/invoices/issue', {
            invoice_ids: invoices.map((row) => row.id),
            academic_year_id: yearId,
            fee_structure_id: structureId || form.data.fee_structure_id,
        });
    };

    return (
        <AppShell title="Invoices">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-2">
                    {years.map((year) => (
                        <button
                            key={year.id}
                            type="button"
                            className={`rounded px-3 py-1 text-sm ${String(year.id) === String(yearId) ? 'bg-[#7C2D37] text-white' : 'border bg-white'}`}
                            onClick={() => router.get('/finance/invoices', { academic_year_id: year.id })}
                        >
                            {year.name}
                        </button>
                    ))}
                </div>
                <div className="flex gap-2">
                    <button type="button" className="btn-secondary" onClick={issueAll} disabled={invoices.length === 0}>Issue drafts</button>
                    <a className="btn-secondary" href={`/finance/invoices/export?academic_year_id=${yearId || ''}&fee_structure_id=${structureId || ''}`}>Export CSV</a>
                </div>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/finance/invoices/generate', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <select className="form-input" value={form.data.fee_structure_id} onChange={(e) => form.setData('fee_structure_id', e.target.value)}>
                    <option value="">Structure</option>
                    {structures.map((row) => <option key={row.id} value={row.id}>{row.name} ({row.status})</option>)}
                </select>
                <input className="form-input" type="date" value={form.data.period_start} onChange={(e) => form.setData('period_start', e.target.value)} />
                <input className="form-input" type="date" value={form.data.period_end} onChange={(e) => form.setData('period_end', e.target.value)} />
                <select className="form-input" value={form.data.monthly_mode} onChange={(e) => form.setData('monthly_mode', e.target.value)}>
                    <option value="per_month">One invoice per month</option>
                    <option value="consolidated">Consolidate monthly items</option>
                </select>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={!!form.data.include_optional} onChange={(e) => form.setData('include_optional', e.target.checked)} />
                    Include optional items
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>Generate drafts</button>
                {form.errors.fee_structure_id && <span className="text-xs text-red-600">{form.errors.fee_structure_id}</span>}
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Number</th>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Period</th>
                            <th className="px-3 py-2">Due</th>
                            <th className="px-3 py-2">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No draft invoices.</td></tr>
                        )}
                        {invoices.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.invoice_number}</td>
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.period_key}</td>
                                <td className="px-3 py-2">{row.due_date}</td>
                                <td className="px-3 py-2">{row.total_amount}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
