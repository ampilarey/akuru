import AppShell from '../../Layouts/AppShell';

export default function Loans({ loans = [], t = {} }) {
    // A count is said whole: one day overdue, or :count days (BACKLOG C21,
    // slice PT3).
    const overdue = (days) => (days === 1
        ? (t.loans_overdue_one || '1 day overdue')
        : (t.loans_overdue_many || ':count days overdue').replace(':count', days));

    return (
        <AppShell title={t.loans_title || 'Library books'}>
            {loans.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    {t.loans_none || 'Nothing is out at the moment.'}
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
                                <p className="text-[#7C2D37]">{overdue(loan.days_overdue)}</p>
                            ) : (
                                <p className="text-gray-700">{(t.loans_due || 'due :date').replace(':date', loan.due_on)}</p>
                            )}
                        </div>
                    </li>
                ))}
            </ul>
        </AppShell>
    );
}
