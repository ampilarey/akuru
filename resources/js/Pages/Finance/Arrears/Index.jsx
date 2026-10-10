import { router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * A year's unpaid invoices, oldest due first. Every word is the `finance`
 * book's (slice FN2, STATUS §5qo); how long an invoice has been overdue is
 * named rather than printed as its code (*current*, *30*) and counted in
 * days rather than abbreviated (*12d*).
 */
export default function Index({ years, yearId, rows, t = {} }) {
    const bucketName = (bucket) => t[`aging_${bucket}`] || bucket;
    const overdue = (days) => {
        if (!days) return t.arrears_not_overdue || 'not yet overdue';
        return days === 1
            ? (t.arrears_day_overdue || '1 day overdue')
            : (t.arrears_days_overdue || ':days days overdue').replace(':days', days);
    };

    return (
        <AppShell title={t.arrears_title || 'Arrears'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-2">
                    {years.map((year) => (
                        <button
                            key={year.id}
                            type="button"
                            className={`rounded px-3 py-1 text-sm ${String(year.id) === String(yearId) ? 'bg-[#7C2D37] text-white' : 'border bg-white'}`}
                            onClick={() => router.get('/finance/arrears', { academic_year_id: year.id })}
                        >
                            {year.name}
                        </button>
                    ))}
                </div>
                <a className="btn-secondary" href={`/finance/arrears/export?academic_year_id=${yearId || ''}`}>{t.export_csv || 'Export CSV'}</a>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.invoices_col_number || 'Number'}</th>
                            <th className="px-3 py-2">{t.student || 'Student'}</th>
                            <th className="px-3 py-2">{t.arrears_guardian || 'Guardian'}</th>
                            <th className="px-3 py-2">{t.invoices_col_due || 'Due'}</th>
                            <th className="px-3 py-2">{t.arrears_balance || 'Balance'}</th>
                            <th className="px-3 py-2">{t.arrears_aging || 'Overdue'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.arrears_none || 'No arrears.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.invoice_number}</td>
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.guardian_name || '—'}</td>
                                <td className="px-3 py-2">{row.due_date}</td>
                                <td className="px-3 py-2">{row.balance}</td>
                                <td className="px-3 py-2">{bucketName(row.aging_bucket)} ({overdue(row.days_overdue)})</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
