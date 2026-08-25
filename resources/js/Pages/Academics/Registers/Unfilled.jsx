import { Link, router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Unfilled({ yearId, years, unfilled, fillRates, planAdherence }) {
    const generate = useForm({
        academic_year_id: yearId || '',
        from: '',
        to: '',
    });

    return (
        <AppShell title="Unfilled registers">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <select
                    className="form-input"
                    value={yearId || ''}
                    onChange={(e) => router.get(`/academics/registers?academic_year_id=${e.target.value}`)}
                >
                    <option value="">Year</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <a className="btn-secondary" href={`/academics/registers/export?academic_year_id=${yearId || ''}`}>Export CSV</a>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    generate.post('/academics/registers/generate', { preserveScroll: true });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">From</span>
                    <input className="form-input w-full" type="date" value={generate.data.from} onChange={(e) => generate.setData('from', e.target.value)} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">To</span>
                    <input className="form-input w-full" type="date" value={generate.data.to} onChange={(e) => generate.setData('to', e.target.value)} />
                </label>
                <div className="flex items-end">
                    <button type="submit" className="btn-primary">Generate expected</button>
                </div>
            </form>

            <section className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Class</th>
                            <th className="px-3 py-2">Subject</th>
                            <th className="px-3 py-2">Period</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {unfilled.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.date}</td>
                                <td className="px-3 py-2">{row.class_name}</td>
                                <td className="px-3 py-2">{row.subject_name}</td>
                                <td className="px-3 py-2">{row.period_name}</td>
                                <td className="px-3 py-2 uppercase">{row.status}</td>
                                <td className="px-3 py-2">
                                    <Link href={`/academics/registers/${row.id}`} className="text-[#7C2D37] underline">Open</Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {unfilled.length === 0 && <p className="p-4 text-sm text-gray-600">No unfilled registers past their time.</p>}
            </section>

            <div className="grid gap-4 md:grid-cols-2">
                <section className="rounded-lg border bg-white p-4 text-sm">
                    <h2 className="mb-2 font-semibold">Fill rate</h2>
                    <ul className="space-y-1">
                        {fillRates.map((row) => (
                            <li key={row.teacher_id}>{row.teacher_name || `Teacher #${row.teacher_id}`}: {row.filled}/{row.total} ({row.rate}%)</li>
                        ))}
                    </ul>
                </section>
                <section className="rounded-lg border bg-white p-4 text-sm">
                    <h2 className="mb-2 font-semibold">Plan adherence</h2>
                    <ul className="space-y-1">
                        {planAdherence.map((row) => (
                            <li key={row.id}>{row.title}: {row.completed}/{row.total} ({row.rate}%)</li>
                        ))}
                    </ul>
                </section>
            </div>
        </AppShell>
    );
}
