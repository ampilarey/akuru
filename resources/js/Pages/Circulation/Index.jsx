import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

// The desk's two forms: a copy lent to the card scanned after it, and a copy
// taken back by its label alone.
function Desk({ t }) {
    const lend = useForm({ accession_number: '', student_number: '' });
    const back = useForm({ accession_number: '', lost: false });

    return (
        <div className="mb-6 grid gap-4 sm:grid-cols-2">
            <form
                className="grid gap-2 rounded-lg border bg-white p-4"
                onSubmit={(e) => { e.preventDefault(); lend.post('/circulation/lend', { preserveScroll: true, onSuccess: () => lend.reset() }); }}
            >
                <p className="text-sm font-semibold">{t.lend_heading || 'Lend'}</p>
                <p className="text-xs text-gray-600">{t.lend_hint || 'Scan the book, then the borrower card.'}</p>
                <input className="form-input w-full text-sm" placeholder={t.accession_number || 'Accession number'} aria-label={t.accession_number || 'Accession number'}
                    value={lend.data.accession_number} onChange={(e) => lend.setData('accession_number', e.target.value)} />
                <input className="form-input w-full text-sm" placeholder={t.borrower_card || 'Borrower card or student number'} aria-label={t.borrower_card || 'Borrower card or student number'}
                    value={lend.data.student_number} onChange={(e) => lend.setData('student_number', e.target.value)} />
                <FormErrors errors={lend.errors} />
                <button className="btn-primary justify-self-start text-sm" disabled={lend.processing}>{t.lend || 'Lend'}</button>
            </form>

            <form
                className="grid gap-2 rounded-lg border bg-white p-4"
                onSubmit={(e) => { e.preventDefault(); back.post('/circulation/return', { preserveScroll: true, onSuccess: () => back.reset() }); }}
            >
                <p className="text-sm font-semibold">{t.take_back_heading || 'Take back'}</p>
                <p className="text-xs text-gray-600">
                    {t.take_back_hint || 'Scan the label. Nobody at a return desk knows which loan row it is.'}
                </p>
                <input className="form-input w-full text-sm" placeholder={t.accession_number || 'Accession number'} aria-label={t.accession_number || 'Accession number'}
                    value={back.data.accession_number} onChange={(e) => back.setData('accession_number', e.target.value)} />
                <label className="flex items-center gap-2 text-xs text-gray-700">
                    <input type="checkbox" checked={back.data.lost} onChange={(e) => back.setData('lost', e.target.checked)} />
                    {t.reported_lost || 'Reported lost'}
                </label>
                <FormErrors errors={back.errors} />
                <button className="btn-secondary justify-self-start text-sm" disabled={back.processing}>{t.take_back || 'Take back'}</button>
            </form>
        </div>
    );
}

function NewTitle({ t }) {
    const form = useForm({ title: '', author: '', isbn: '', classification: '', loan_days: 14 });

    return (
        <form
            className="mb-6 grid gap-2 rounded-lg border bg-white p-4"
            onSubmit={(e) => { e.preventDefault(); form.post('/circulation/titles', { preserveScroll: true, onSuccess: () => form.reset() }); }}
        >
            <p className="text-sm font-semibold">{t.add_title_heading || 'Add a title'}</p>
            <div className="grid gap-2 sm:grid-cols-2">
                <input className="form-input w-full text-sm" placeholder={t.field_title || 'Title'} aria-label={t.field_title || 'Title'}
                    value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <input className="form-input w-full text-sm" placeholder={t.field_author || 'Author'} aria-label={t.field_author || 'Author'}
                    value={form.data.author} onChange={(e) => form.setData('author', e.target.value)} />
                <input className="form-input w-full text-sm" placeholder={t.field_isbn || 'ISBN'} aria-label={t.field_isbn || 'ISBN'}
                    value={form.data.isbn} onChange={(e) => form.setData('isbn', e.target.value)} />
                <input className="form-input w-full text-sm" placeholder={t.field_shelf_mark || 'Shelf mark'} aria-label={t.field_shelf_mark || 'Shelf mark'}
                    value={form.data.classification} onChange={(e) => form.setData('classification', e.target.value)} />
                <label className="text-xs text-gray-600">
                    {t.field_loan_days || 'Loan period (days)'}
                    <input type="number" className="form-input w-full text-sm" value={form.data.loan_days}
                        onChange={(e) => form.setData('loan_days', e.target.value)} />
                </label>
            </div>
            <FormErrors errors={form.errors} />
            <button className="btn-primary justify-self-start text-sm" disabled={form.processing}>{t.add || 'Add'}</button>
        </form>
    );
}

/**
 * The library desk: lending and taking back paper books, the titles on the
 * shelves and what is overdue. Every word is the `circulation` book's (slice
 * LD1, STATUS §5qt). The borrower box takes what a borrower card's barcode
 * carries — the pupil's student number — where it took the pupil's row id,
 * and every refusal of the three forms is said under its form.
 */
export default function Index({ q = '', titles = [], overdue = [], t = {} }) {
    const [query, setQuery] = useState(q);

    return (
        <AppShell title={t.desk_title || 'Circulation'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href={`/circulation/export?q=${encodeURIComponent(q ?? '')}`}>{t.export_csv || 'Export CSV'}</a>
            </div>
            <p className="mb-4 text-sm text-gray-600">
                {t.desk_intro || 'Physical books and textbooks on shelves. The digital Library is a different thing entirely — this is the one with labels on it.'}
            </p>

            <Desk t={t} />

            <input
                className="form-input mb-4 w-full"
                placeholder={t.search_titles || 'Search title, author, ISBN or shelf mark'}
                aria-label={t.search_titles || 'Search title, author, ISBN or shelf mark'}
                value={query}
                onChange={(e) => { setQuery(e.target.value); router.get('/circulation', { q: e.target.value }, { preserveState: true, replace: true }); }}
            />

            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className="px-3 py-2 text-start">{t.col_title || 'Title'}</th>
                            <th className="px-3 py-2 text-start">{t.col_on_shelf || 'On the shelf'}</th>
                            <th className="px-3 py-2 text-start">{t.col_out || 'Out'}</th>
                            <th className="px-3 py-2 text-start" />
                        </tr>
                    </thead>
                    <tbody>
                        {titles.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">
                                    <Link href={`/circulation/titles/${row.id}`} className="text-[#7C2D37] hover:underline">
                                        {row.title}
                                    </Link>
                                    {row.author && <span className="block text-xs text-gray-500">{row.author}</span>}
                                </td>
                                <td className="px-3 py-2">
                                    {row.available > 0
                                        ? <span className="text-emerald-700">{(t.on_shelf_count || ':available of :total').replace(':available', row.available).replace(':total', row.total)}</span>
                                        : <span className="text-gray-500">{t.on_shelf_none || 'none'}</span>}
                                </td>
                                <td className="px-3 py-2 text-gray-600">
                                    {row.on_loan}
                                    {row.available === 0 && row.soonest_back && (
                                        <span className="block text-xs">{(t.back_on || 'back :date').replace(':date', row.soonest_back)}</span>
                                    )}
                                </td>
                                <td className="px-3 py-2">
                                    <Link href={`/circulation/titles/${row.id}/labels`} className="text-xs text-[#7C2D37] underline">
                                        {t.labels || 'Labels'}
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {titles.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">{t.titles_none || 'No titles yet.'}</p>
                )}
            </div>

            <NewTitle t={t} />

            <h2 className="mb-2 text-sm font-semibold">{(t.overdue_heading || 'Overdue (:count)').replace(':count', overdue.length)}</h2>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <tbody>
                        {overdue.map((loan) => (
                            <tr key={loan.id} className="border-t">
                                <td className="px-3 py-2">{loan.title}</td>
                                <td className="px-3 py-2 text-gray-600">{loan.borrower}</td>
                                <td className="px-3 py-2 text-xs text-[#7C2D37]">
                                    {(loan.days_overdue === 1 ? (t.overdue_one || 'due :date · 1 day over') : (t.overdue_many || 'due :date · :days days over'))
                                        .replace(':date', loan.due_on).replace(':days', loan.days_overdue)}
                                </td>
                                <td className="px-3 py-2 text-xs text-gray-500">{loan.accession_number}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {overdue.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">{t.overdue_none || 'Nothing is overdue.'}</p>
                )}
            </div>
        </AppShell>
    );
}
