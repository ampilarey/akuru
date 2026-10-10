import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

/**
 * A title on the library desk: its copies, adding more, issuing it to a class
 * and collecting it back. Every word is the `circulation` book's (slice LD1,
 * STATUS §5qt), and a copy's state is named. The class issue's result — who
 * got a book, who did not, and why — is shown by the pupils' names: the
 * server kept it in the session and the page never received it. A refused
 * issue or collection is said under its buttons.
 */
export default function Title({ title, copies = [], q = '', matches = [], bulk_result: bulkResult = null, t = {} }) {
    const [query, setQuery] = useState(q);
    const [picked, setPicked] = useState([]);
    const copiesForm = useForm({ how_many: 5, shelf: '' });
    const refusals = useRowRefusals(copiesForm);

    const toggle = (id) =>
        setPicked((current) => (current.includes(id) ? current.filter((i) => i !== id) : [...current, id]));

    const bulk = (what) =>
        refusals.actOn('bulk', () => router.post(`/circulation/titles/${title.id}/${what}`, { student_ids: picked }, { preserveScroll: true }));

    return (
        <AppShell title={title.title}>
            <p className="mb-4 text-sm text-gray-600">
                {[title.author, title.isbn, title.classification, (t.loan_period || 'loan period :days days').replace(':days', title.loan_days)].filter(Boolean).join(' · ')}
            </p>
            <FormErrors errors={refusals.unplaced} className="mb-4" />

            <div className="mb-6 flex flex-wrap gap-3">
                <Link href={`/circulation/titles/${title.id}/labels`} className="btn-secondary text-sm">
                    {t.print_labels || 'Print labels'}
                </Link>
                <Link href="/circulation" className="text-sm text-[#7C2D37] underline self-center">
                    {t.back_to_desk || 'Back to circulation'}
                </Link>
            </div>

            <form
                className="mb-6 flex flex-wrap items-end gap-3 rounded-lg border bg-white p-4"
                onSubmit={(e) => { e.preventDefault(); copiesForm.post(`/circulation/titles/${title.id}/copies`, { preserveScroll: true }); }}
            >
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.add_copies || 'Add copies'}</span>
                    <input type="number" className="form-input w-24" value={copiesForm.data.how_many}
                        onChange={(e) => copiesForm.setData('how_many', e.target.value)} />
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.shelf || 'Shelf'}</span>
                    <input className="form-input w-40" value={copiesForm.data.shelf}
                        onChange={(e) => copiesForm.setData('shelf', e.target.value)} />
                </label>
                <button className="btn-primary text-sm" disabled={copiesForm.processing}>{t.add || 'Add'}</button>
                <FormErrors errors={copiesForm.errors} className="basis-full" />
            </form>

            <h2 className="mb-2 text-sm font-semibold">{(t.copies_heading || 'Copies (:count)').replace(':count', copies.length)}</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className="px-3 py-2 text-start">{t.col_accession || 'Accession'}</th>
                            <th className="px-3 py-2 text-start">{t.col_status || 'Status'}</th>
                            <th className="px-3 py-2 text-start">{t.col_with || 'With'}</th>
                            <th className="px-3 py-2 text-start">{t.col_due || 'Due'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {copies.map((copy) => (
                            <tr key={copy.id} className="border-t">
                                <td className="px-3 py-2 font-mono text-xs">{copy.accession_number}</td>
                                <td className="px-3 py-2">{t[`copy_status_${copy.status}`] || copy.status_label}</td>
                                <td className="px-3 py-2 text-gray-600">{copy.borrower ?? '—'}</td>
                                <td className={`px-3 py-2 text-xs ${copy.overdue ? 'text-[#7C2D37]' : 'text-gray-500'}`}>
                                    {copy.due_on
                                        ? (copy.overdue ? (t.due_overdue || ':date (overdue)').replace(':date', copy.due_on) : copy.due_on)
                                        : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {copies.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">{t.copies_none || 'No copies yet — add some above.'}</p>
                )}
            </div>

            <h2 className="mb-2 text-sm font-semibold">{t.issue_heading || 'Issue to a class'}</h2>
            <div className="rounded-lg border bg-white p-4">
                <p className="mb-2 text-xs text-gray-600">
                    {t.issue_hint || 'For textbook issue at the start of term. A pupil who already has a copy is skipped rather than stopping the rest of the class.'}
                </p>
                <input
                    className="form-input mb-3 w-full text-sm"
                    placeholder={t.search_pupils || 'Search pupils by name or number'}
                    aria-label={t.search_pupils || 'Search pupils by name or number'}
                    value={query}
                    onChange={(e) => { setQuery(e.target.value); router.get(`/circulation/titles/${title.id}`, { q: e.target.value }, { preserveState: true, replace: true }); }}
                />
                <ul className="mb-3 grid gap-1">
                    {matches.map((child) => (
                        <li key={child.id}>
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={picked.includes(child.id)} onChange={() => toggle(child.id)} />
                                {child.name}
                                <span className="text-xs text-gray-500">
                                    {[child.student_number, child.current_class].filter(Boolean).join(' · ')}
                                </span>
                            </label>
                        </li>
                    ))}
                </ul>
                <div className="flex flex-wrap gap-3">
                    <button type="button" className="btn-primary text-sm" disabled={picked.length === 0} onClick={() => bulk('issue')}>
                        {picked.length === 1 ? (t.issue_one || 'Issue to 1 pupil') : (t.issue_many || 'Issue to :count pupils').replace(':count', picked.length)}
                    </button>
                    <button type="button" className="btn-secondary text-sm" disabled={picked.length === 0} onClick={() => bulk('collect')}>
                        {t.collect || 'Collect back in'}
                    </button>
                </div>
                <FormErrors errors={refusals.errorsFor('bulk')} className="mt-2" />

                {bulkResult && (
                    <div className="mt-4 rounded border bg-[#FBF7F2] p-3 text-xs" data-testid="bulk-result">
                        {bulkResult.issued != null && <p>{(t.result_issued || ':count issued.').replace(':count', bulkResult.issued)}</p>}
                        {bulkResult.returned != null && <p>{(t.result_returned || ':count taken back.').replace(':count', bulkResult.returned)}</p>}
                        {bulkResult.skipped?.length > 0 && (
                            <>
                                <p className="mt-1 font-semibold">{t.result_not_issued || 'Not issued:'}</p>
                                <ul>
                                    {bulkResult.skipped.map((skip) => (
                                        <li key={skip.student_id}>{skip.name} — {skip.reason}</li>
                                    ))}
                                </ul>
                            </>
                        )}
                        {bulkResult.outstanding?.length > 0 && (
                            <p className="mt-1">{(t.result_outstanding || 'Still outstanding: :names').replace(':names', bulkResult.outstanding.join(t.list_separator || ', '))}</p>
                        )}
                    </div>
                )}
            </div>
        </AppShell>
    );
}
