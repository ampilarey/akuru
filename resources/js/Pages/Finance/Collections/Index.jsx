import { router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ years, yearId, rows }) {
    return (
        <AppShell title="Collections">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-2">
                    {years.map((year) => (
                        <button
                            key={year.id}
                            type="button"
                            className={`rounded px-3 py-1 text-sm ${String(year.id) === String(yearId) ? 'bg-[#7C2D37] text-white' : 'border bg-white'}`}
                            onClick={() => router.get('/finance/collections', { academic_year_id: year.id })}
                        >
                            {year.name}
                        </button>
                    ))}
                </div>
                <a className="btn-secondary" href={`/finance/collections/export?academic_year_id=${yearId || ''}`}>Export CSV</a>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Class</th>
                            <th className="px-3 py-2">Month</th>
                            <th className="px-3 py-2">Billed</th>
                            <th className="px-3 py-2">Collected</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No collections yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={`${row.class_id}-${row.month}`} className="border-t">
                                <td className="px-3 py-2">{row.class_id ?? '—'}</td>
                                <td className="px-3 py-2">{row.month}</td>
                                <td className="px-3 py-2">{row.billed}</td>
                                <td className="px-3 py-2">{row.collected}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
