import AppShell from '../../Layouts/AppShell';

export default function Loans({ loans = [] }) {
    return (
        <AppShell title="Library books">
            {loans.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    Nothing is out at the moment.
                </p>
            )}
            <ul className="grid gap-2">
                {loans.map((loan) => (
                    <li key={loan.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-white p-3 text-sm">
                        <div>
                            <p className="font-medium">{loan.title}</p>
                            <p className="text-xs text-gray-500">{loan.borrower} · {loan.accession_number}</p>
                        </div>
                        <div className="text-end">
                            {loan.overdue ? (
                                <p className="text-[#7C2D37]">
                                    {loan.days_overdue} day{loan.days_overdue === 1 ? '' : 's'} overdue
                                </p>
                            ) : (
                                <p className="text-gray-700">due {loan.due_on}</p>
                            )}
                        </div>
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}
