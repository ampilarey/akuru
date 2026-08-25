import AppShell from '../../Layouts/AppShell';

export default function LeaveBalances({ staff, rows }) {
    return (
        <AppShell title="My leave">
            {!staff && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">No staff profile is linked to this account.</p>
            )}
            {staff && <p className="mb-4 text-sm text-gray-600">{staff.name}</p>}
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Entitled</th>
                            <th className="px-3 py-2">Carried</th>
                            <th className="px-3 py-2">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No leave balances yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.leave_type}</td>
                                <td className="px-3 py-2">{row.entitled_days}</td>
                                <td className="px-3 py-2">{row.carried_over_days}</td>
                                <td className="px-3 py-2">{row.balance}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
