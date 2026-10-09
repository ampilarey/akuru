import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * B8 (LIBRARY_PLAN §10): a child's library as their parent sees it — what
 * they are reading and what was bought. Bookmarks and notes are the
 * child's own and are not here.
 */
export default function ChildLibrary({ child, continue: reading = [], purchases = [], t = {} }) {
    // The column names, also the captions a phone shows beside each value; a
    // purchase's state is a code, named here (BACKLOG C21, slice PT3).
    const col = {
        title: t.col_title || 'Title',
        page: t.library_col_page || 'Page',
        progress: t.library_col_progress || 'Progress',
        last_read: t.library_col_last_read || 'Last read',
        time_read: t.library_col_time_read || 'Time read',
        amount: t.col_amount || 'Amount',
        status: t.col_status || 'Status',
        when: t.col_when || 'When',
    };

    return (
        <AppShell title={(t.library_title || ':name’s library').replace(':name', child.name)}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <Link href="/portal/children" className="underline">{t.library_my_children || 'My children'}</Link>
                <a href={`/portal/children/${child.id}/library/export`} className="underline" data-testid="export-csv">{t.export_csv || 'Export CSV'}</a>
            </div>

            {!child.has_account && (
                <p className="mb-4 rounded-lg border bg-white p-4 text-sm text-gray-600" data-testid="no-account">
                    {(t.library_no_account || ':name has no login of their own yet, so there is no reading to show. The office can create one from the student’s profile.').replace(':name', child.name)}
                </p>
            )}

            <h2 className="mb-2 text-base font-semibold">{t.library_reading || 'Reading'}</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm" data-testid="reading">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.title}</th>
                            <th className="px-3 py-2">{col.page}</th>
                            <th className="px-3 py-2">{col.progress}</th>
                            <th className="px-3 py-2">{col.last_read}</th>
                            <th className="px-3 py-2">{col.time_read}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {reading.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.library_nothing_opened || 'Nothing opened yet.'}</td></tr>
                        )}
                        {reading.map((row) => (
                            <tr key={row.item_id} className="border-t">
                                <td className="px-3 py-2" data-label={col.title}><a href={`/library/${row.slug}`} className="underline">{row.title}</a></td>
                                <td className="px-3 py-2" data-label={col.page}>{row.current_page}</td>
                                <td className="px-3 py-2" data-label={col.progress}>{row.completed ? (t.library_completed || 'Completed') : `${row.progress_percent}%`}</td>
                                <td className="px-3 py-2" data-label={col.last_read}>{row.last_read_at ?? '—'}</td>
                                {/* §9.1 reading time (STATUS §5ju): progress, not private words, so a parent may see it. */}
                                <td className="px-3 py-2" data-label={col.time_read}>{row.reading_minutes ? (t.library_minutes || ':count min').replace(':count', row.reading_minutes) : '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <h2 className="mb-2 text-base font-semibold">{t.library_purchases || 'Purchases'}</h2>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm" data-testid="purchases">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.title}</th>
                            <th className="px-3 py-2">{col.amount}</th>
                            <th className="px-3 py-2">{col.status}</th>
                            <th className="px-3 py-2">{col.when}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {purchases.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>{t.library_no_purchases || 'No purchases.'}</td></tr>
                        )}
                        {purchases.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2" data-label={col.title}>{row.slug ? <a href={`/library/${row.slug}`} className="underline">{row.title}</a> : row.title}</td>
                                <td className="px-3 py-2" data-label={col.amount}>{row.currency} {row.amount}</td>
                                <td className="px-3 py-2" data-label={col.status}>{t[`library_purchase_status_${row.status}`] || row.status}</td>
                                <td className="px-3 py-2" data-label={col.when}>{row.purchased_at ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
