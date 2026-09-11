import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

function Desk() {
    const lend = useForm({ accession_number: '', student_id: '', note: '' });
    const back = useForm({ accession_number: '', lost: false });

    return (
        <div className="mb-6 grid gap-4 sm:grid-cols-2">
            <form
                className="grid gap-2 rounded-lg border bg-white p-4"
                onSubmit={(e) => { e.preventDefault(); lend.post('/circulation/lend', { preserveScroll: true, onSuccess: () => lend.reset() }); }}
            >
                <p className="text-sm font-semibold">Lend</p>
                <p className="text-xs text-gray-600">Scan the book, then the borrower card.</p>
                <input className="form-input w-full text-sm" placeholder="Accession number"
                    value={lend.data.accession_number} onChange={(e) => lend.setData('accession_number', e.target.value)} />
                <input className="form-input w-full text-sm" placeholder="Pupil id"
                    value={lend.data.student_id} onChange={(e) => lend.setData('student_id', e.target.value)} />
                {lend.errors.copy && <p className="text-xs text-red-600">{lend.errors.copy}</p>}
                {lend.errors.borrower && <p className="text-xs text-red-600">{lend.errors.borrower}</p>}
                <button className="btn-primary justify-self-start text-sm" disabled={lend.processing}>Lend</button>
            </form>

            <form
                className="grid gap-2 rounded-lg border bg-white p-4"
                onSubmit={(e) => { e.preventDefault(); back.post('/circulation/return', { preserveScroll: true, onSuccess: () => back.reset() }); }}
            >
                <p className="text-sm font-semibold">Take back</p>
                <p className="text-xs text-gray-600">
                    Scan the label. Nobody at a return desk knows which loan row it is.
                </p>
                <input className="form-input w-full text-sm" placeholder="Accession number"
                    value={back.data.accession_number} onChange={(e) => back.setData('accession_number', e.target.value)} />
                <label className="flex items-center gap-2 text-xs text-gray-700">
                    <input type="checkbox" checked={back.data.lost} onChange={(e) => back.setData('lost', e.target.checked)} />
                    Reported lost
                </label>
                {back.errors.copy && <p className="text-xs text-red-600">{back.errors.copy}</p>}
                <button className="btn-secondary justify-self-start text-sm" disabled={back.processing}>Take back</button>
            </form>
        </div>
    );
}

function NewTitle() {
    const form = useForm({ title: '', author: '', isbn: '', classification: '', loan_days: 14 });

    return (
        <form
            className="mb-6 grid gap-2 rounded-lg border bg-white p-4"
            onSubmit={(e) => { e.preventDefault(); form.post('/circulation/titles', { preserveScroll: true, onSuccess: () => form.reset() }); }}
        >
            <p className="text-sm font-semibold">Add a title</p>
            <div className="grid gap-2 sm:grid-cols-2">
                <input className="form-input w-full text-sm" placeholder="Title"
                    value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <input className="form-input w-full text-sm" placeholder="Author"
                    value={form.data.author} onChange={(e) => form.setData('author', e.target.value)} />
                <input className="form-input w-full text-sm" placeholder="ISBN"
                    value={form.data.isbn} onChange={(e) => form.setData('isbn', e.target.value)} />
                <input className="form-input w-full text-sm" placeholder="Shelf mark"
                    value={form.data.classification} onChange={(e) => form.setData('classification', e.target.value)} />
                <label className="text-xs text-gray-600">
                    Loan period (days)
                    <input type="number" className="form-input w-full text-sm" value={form.data.loan_days}
                        onChange={(e) => form.setData('loan_days', e.target.value)} />
                </label>
            </div>
            {form.errors.title && <p className="text-xs text-red-600">{form.errors.title}</p>}
            <button className="btn-primary justify-self-start text-sm" disabled={form.processing}>Add</button>
        </form>
    );
}

export default function Index({ q = '', titles = [], overdue = [] }) {
    const [query, setQuery] = useState(q);

    return (
        <AppShell title="Circulation">
            <p className="mb-4 text-sm text-gray-600">
                Physical books and textbooks on shelves. The digital Library is a different
                thing entirely — this is the one with labels on it.
            </p>

            <Desk />

            <input
                className="form-input mb-4 w-full"
                placeholder="Search title, author, ISBN or shelf mark"
                value={query}
                onChange={(e) => { setQuery(e.target.value); router.get('/circulation', { q: e.target.value }, { preserveState: true, replace: true }); }}
            />

            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className="px-3 py-2 text-start">Title</th>
                            <th className="px-3 py-2 text-start">On the shelf</th>
                            <th className="px-3 py-2 text-start">Out</th>
                            <th className="px-3 py-2 text-start" />
                        </tr>
                    </thead>
                    <tbody>
                        {titles.map((t) => (
                            <tr key={t.id} className="border-t">
                                <td className="px-3 py-2">
                                    <Link href={`/circulation/titles/${t.id}`} className="text-[#7C2D37] hover:underline">
                                        {t.title}
                                    </Link>
                                    {t.author && <span className="block text-xs text-gray-500">{t.author}</span>}
                                </td>
                                <td className="px-3 py-2">
                                    {t.available > 0
                                        ? <span className="text-emerald-700">{t.available} of {t.total}</span>
                                        : <span className="text-gray-500">none</span>}
                                </td>
                                <td className="px-3 py-2 text-gray-600">
                                    {t.on_loan}
                                    {t.available === 0 && t.soonest_back && (
                                        <span className="block text-xs">back {t.soonest_back}</span>
                                    )}
                                </td>
                                <td className="px-3 py-2">
                                    <Link href={`/circulation/titles/${t.id}/labels`} className="text-xs text-[#7C2D37] underline">
                                        Labels
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {titles.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">No titles yet.</p>
                )}
            </div>

            <NewTitle />

            <h2 className="mb-2 text-sm font-semibold">Overdue ({overdue.length})</h2>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <tbody>
                        {overdue.map((loan) => (
                            <tr key={loan.id} className="border-t">
                                <td className="px-3 py-2">{loan.title}</td>
                                <td className="px-3 py-2 text-gray-600">{loan.borrower}</td>
                                <td className="px-3 py-2 text-xs text-[#7C2D37]">
                                    due {loan.due_on} · {loan.days_overdue} day{loan.days_overdue === 1 ? '' : 's'} over
                                </td>
                                <td className="px-3 py-2 text-xs text-gray-500">{loan.accession_number}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {overdue.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">Nothing is overdue.</p>
                )}
            </div>
        </AppShell>
    );
}
