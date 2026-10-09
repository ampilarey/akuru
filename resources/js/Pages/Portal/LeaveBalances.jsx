import AppShell from '../../Layouts/AppShell';

export default function LeaveBalances({ staff, rows, t = {} }) {
    // In the page's language (BACKLOG C21, slice PT4); a leave type is
    // named as the office named it.
    return (
        <AppShell title={t.leave_title || 'My leave'}>
            {!staff && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">{t.staff_no_profile || 'No staff profile is linked to this account.'}</p>
            )}
            {staff && <p className="mb-4 text-sm text-gray-600">{staff.name}</p>}
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.leave_col_entitled || 'Entitled'}</th>
                            <th className="px-3 py-2">{t.leave_col_carried || 'Carried'}</th>
                            <th className="px-3 py-2">{t.col_balance || 'Balance'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.leave_none || 'No leave balances yet.'}</td></tr>
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
