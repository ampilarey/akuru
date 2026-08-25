import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ enabled, periods, periodId, rows, canApprove }) {
    const run = useForm({
        year: new Date().getFullYear(),
        month: new Date().getMonth() + 1,
    });

    return (
        <AppShell title="Payroll">
            {!enabled && <p className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm">Payroll is disabled until two parallel cycles match.</p>}
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    run.post('/hr/payroll/run');
                }}
                className="mb-4 flex flex-wrap gap-3 rounded-lg border bg-white p-4"
            >
                <input className="form-input w-28" value={run.data.year} onChange={(e) => run.setData('year', e.target.value)} />
                <input className="form-input w-20" value={run.data.month} onChange={(e) => run.setData('month', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={run.processing || !enabled}>Run payroll</button>
            </form>
            {periodId && (
                <div className="mb-4 flex flex-wrap gap-3">
                    {canApprove && (
                        <>
                            <button type="button" className="btn-secondary" onClick={() => router.post(`/hr/payroll/${periodId}/approve`)}>Approve</button>
                            <button type="button" className="btn-secondary" onClick={() => router.post(`/hr/payroll/${periodId}/pay`)}>Mark paid</button>
                            <button type="button" className="btn-secondary" onClick={() => router.post(`/hr/payroll/${periodId}/lock`)}>Lock</button>
                        </>
                    )}
                    <a className="btn-secondary" href={`/hr/payroll/${periodId}/export`}>Bank CSV</a>
                </div>
            )}
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Staff</th>
                            <th className="px-3 py-2">Gross</th>
                            <th className="px-3 py-2">Net</th>
                            <th className="px-3 py-2">Previous</th>
                            <th className="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.staff_name}</td>
                                <td className="px-3 py-2">{row.gross}</td>
                                <td className="px-3 py-2">{row.net_pay}</td>
                                <td className="px-3 py-2">{row.previous_net || '—'}</td>
                                <td className="px-3 py-2">{row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <p className="mt-3 text-xs text-gray-500">{periods.length} period(s) on file.</p>
        </AppShell>
    );
}
