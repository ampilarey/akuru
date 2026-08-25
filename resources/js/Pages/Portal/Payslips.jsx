import AppShell from '../../Layouts/AppShell';

export default function Payslips({ enabled, staff, rows }) {
    return (
        <AppShell title="My payslips">
            {!enabled && <p className="mb-4 text-sm text-gray-600">Payroll is not enabled yet.</p>}
            {!staff && <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">No staff profile is linked to this account.</p>}
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Period</th>
                            <th className="px-3 py-2">Net</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Document</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No payslips yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.year}-{String(row.month).padStart(2, '0')}</td>
                                <td className="px-3 py-2">{row.net_pay}</td>
                                <td className="px-3 py-2">{row.status}</td>
                                <td className="px-3 py-2">
                                    {row.document_id ? <a className="text-[#7C2D37] hover:underline" href={`/hr/payslips/${row.id}/document`}>Open</a> : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
