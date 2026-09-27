import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * B8 (LIBRARY_PLAN §10): a child's library as their parent sees it — what
 * they are reading and what was bought. Bookmarks and notes are the
 * child's own and are not here.
 */
export default function ChildLibrary({ child, continue: reading = [], purchases = [] }) {
    return (
        <AppShell title={`${child.name}'s library`}>
            <div className="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <Link href="/portal/children" className="underline">My children</Link>
                <a href={`/portal/children/${child.id}/library/export`} className="underline" data-testid="export-csv">Export CSV</a>
            </div>

            {!child.has_account && (
                <p className="mb-4 rounded-lg border bg-white p-4 text-sm text-gray-600" data-testid="no-account">
                    {child.name} has no login of their own yet, so there is no reading to show. The office can create one from the student&rsquo;s profile.
                </p>
            )}

            <h2 className="mb-2 text-base font-semibold">Reading</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm" data-testid="reading">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Page</th>
                            <th className="px-3 py-2">Progress</th>
                            <th className="px-3 py-2">Last read</th>
                        </tr>
                    </thead>
                    <tbody>
                        {reading.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>Nothing opened yet.</td></tr>
                        )}
                        {reading.map((row) => (
                            <tr key={row.item_id} className="border-t">
                                <td className="px-3 py-2"><a href={`/library/${row.slug}`} className="underline">{row.title}</a></td>
                                <td className="px-3 py-2">{row.current_page}</td>
                                <td className="px-3 py-2">{row.completed ? 'Completed' : `${row.progress_percent}%`}</td>
                                <td className="px-3 py-2">{row.last_read_at ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <h2 className="mb-2 text-base font-semibold">Purchases</h2>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm" data-testid="purchases">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Amount</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        {purchases.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No purchases.</td></tr>
                        )}
                        {purchases.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.slug ? <a href={`/library/${row.slug}`} className="underline">{row.title}</a> : row.title}</td>
                                <td className="px-3 py-2">{row.currency} {row.amount}</td>
                                <td className="px-3 py-2">{row.status}</td>
                                <td className="px-3 py-2">{row.purchased_at ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
