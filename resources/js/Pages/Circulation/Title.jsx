import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

export default function Title({ title, copies = [], q = '', matches = [] }) {
    const [query, setQuery] = useState(q);
    const [picked, setPicked] = useState([]);
    const bulkResult = usePage().props.bulk_result;
    const copiesForm = useForm({ how_many: 5, shelf: '' });

    const toggle = (id) =>
        setPicked((current) => (current.includes(id) ? current.filter((i) => i !== id) : [...current, id]));

    const bulk = (what) =>
        router.post(`/circulation/titles/${title.id}/${what}`, { student_ids: picked }, { preserveScroll: true });

    return (
        <AppShell title={title.title}>
            <p className="mb-4 text-sm text-gray-600">
                {[title.author, title.isbn, title.classification].filter(Boolean).join(' · ')}
                {' · '}loan period {title.loan_days} days
            </p>

            <div className="mb-6 flex flex-wrap gap-3">
                <Link href={`/circulation/titles/${title.id}/labels`} className="btn-secondary text-sm">
                    Print labels
                </Link>
                <Link href="/circulation" className="text-sm text-[#7C2D37] underline self-center">
                    Back to circulation
                </Link>
            </div>

            <form
                className="mb-6 flex flex-wrap items-end gap-3 rounded-lg border bg-white p-4"
                onSubmit={(e) => { e.preventDefault(); copiesForm.post(`/circulation/titles/${title.id}/copies`, { preserveScroll: true }); }}
            >
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Add copies</span>
                    <input type="number" className="form-input w-24" value={copiesForm.data.how_many}
                        onChange={(e) => copiesForm.setData('how_many', e.target.value)} />
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Shelf</span>
                    <input className="form-input w-40" value={copiesForm.data.shelf}
                        onChange={(e) => copiesForm.setData('shelf', e.target.value)} />
                </label>
                <button className="btn-primary text-sm" disabled={copiesForm.processing}>Add</button>
                {copiesForm.errors.how_many && <p className="text-xs text-red-600">{copiesForm.errors.how_many}</p>}
            </form>

            <h2 className="mb-2 text-sm font-semibold">Copies ({copies.length})</h2>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className="px-3 py-2 text-start">Accession</th>
                            <th className="px-3 py-2 text-start">Status</th>
                            <th className="px-3 py-2 text-start">With</th>
                            <th className="px-3 py-2 text-start">Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        {copies.map((copy) => (
                            <tr key={copy.id} className="border-t">
                                <td className="px-3 py-2 font-mono text-xs">{copy.accession_number}</td>
                                <td className="px-3 py-2">{copy.status_label}</td>
                                <td className="px-3 py-2 text-gray-600">{copy.borrower ?? '—'}</td>
                                <td className={`px-3 py-2 text-xs ${copy.overdue ? 'text-[#7C2D37]' : 'text-gray-500'}`}>
                                    {copy.due_on ?? '—'}{copy.overdue ? ' (overdue)' : ''}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {copies.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">No copies yet — add some above.</p>
                )}
            </div>

            <h2 className="mb-2 text-sm font-semibold">Issue to a class</h2>
            <div className="rounded-lg border bg-white p-4">
                <p className="mb-2 text-xs text-gray-600">
                    For textbook issue at the start of term. A pupil who already has a copy is
                    skipped rather than stopping the rest of the class.
                </p>
                <input
                    className="form-input mb-3 w-full text-sm"
                    placeholder="Search pupils by name or number"
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
                    <button className="btn-primary text-sm" disabled={picked.length === 0} onClick={() => bulk('issue')}>
                        Issue to {picked.length} pupil{picked.length === 1 ? '' : 's'}
                    </button>
                    <button className="btn-secondary text-sm" disabled={picked.length === 0} onClick={() => bulk('collect')}>
                        Collect back in
                    </button>
                </div>

                {bulkResult && (
                    <div className="mt-4 rounded border bg-[#FBF7F2] p-3 text-xs">
                        {bulkResult.issued && <p>{bulkResult.issued.length} issued.</p>}
                        {bulkResult.returned && <p>{bulkResult.returned.length} taken back.</p>}
                        {bulkResult.skipped?.length > 0 && (
                            <>
                                <p className="mt-1 font-semibold">Not issued:</p>
                                <ul>
                                    {bulkResult.skipped.map((s) => (
                                        <li key={s.student_id}>pupil #{s.student_id} — {s.reason}</li>
                                    ))}
                                </ul>
                            </>
                        )}
                        {bulkResult.outstanding?.length > 0 && (
                            <p className="mt-1">Still outstanding: {bulkResult.outstanding.join(', ')}</p>
                        )}
                    </div>
                )}
            </div>
        </AppShell>
    );
}
