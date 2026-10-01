import { useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';
import { IdentityChecks } from '../../Components/IdentityCard';

/**
 * LENDING_AND_USED_BOOKS_PLAN L1: the office's view of book lending — the
 * lenders' ID cards to check (a lender's books reach the shelf only once
 * their card is verified, D5), every lender with their counts, and the
 * loans. Run by the Bookstore team (D6). No money passes here (D4).
 */
/** L2: pause a lender with a note the person reads, or resume them. */
function LenderAction({ lender, t }) {
    const form = useForm({ note: '' });
    const action = lender.office_paused ? 'resume' : 'pause';
    const submit = (e) => { e.preventDefault(); form.post(`/admin/lending/lenders/${lender.id}/${action}`, { preserveScroll: true, onSuccess: () => form.reset() }); };

    return (
        <form className="flex flex-wrap items-center gap-1" onSubmit={submit}>
            {!lender.office_paused && <input className="form-input w-40 text-xs" placeholder={t.office_note_placeholder} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} maxLength={500} data-testid={`lender-note-${lender.id}`} />}
            <button type="submit" className={`rounded px-2 py-1 text-xs font-semibold ${lender.office_paused ? 'bg-green-700 text-white' : 'border border-red-300 text-red-800'}`} disabled={form.processing} data-testid={`lender-${action}-${lender.id}`}>{lender.office_paused ? t.office_resume_lender : t.office_pause_lender}</button>
            {form.errors.note && <span className="w-full text-xs text-red-700">{form.errors.note}</span>}
        </form>
    );
}

/** L2: take a book down, with a note. */
function RemoveBook({ book, t }) {
    const form = useForm({ note: '' });
    const submit = (e) => { e.preventDefault(); form.post(`/admin/lending/books/${book.id}/remove`, { preserveScroll: true }); };

    return (
        <form className="flex flex-wrap items-center gap-1" onSubmit={submit}>
            <input className="form-input w-40 text-xs" placeholder={t.office_note_placeholder} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} maxLength={500} required data-testid={`book-note-${book.id}`} />
            <button type="submit" className="rounded border border-red-300 px-2 py-1 text-xs text-red-800" disabled={form.processing} data-testid={`book-remove-${book.id}`}>{t.office_remove_book}</button>
            {form.errors.note && <span className="w-full text-xs text-red-700">{form.errors.note}</span>}
        </form>
    );
}

function Stat({ label, value, testid }) {
    return (
        <div className="rounded-lg border bg-white p-3" data-testid={testid}>
            <p className="text-xs text-gray-500">{label}</p>
            <p className="text-2xl font-semibold">{value}</p>
        </div>
    );
}

export default function Admin({ t = {}, id_l = {}, admin = {} }) {
    const { errors } = usePage().props;
    const { counts = {}, lenders = [], loans = [], books = [], identity = [] } = admin;
    const stars = (r) => (r && r.count > 0 ? `★ ${r.avg} (${r.count})` : '—');
    const tone = { requested: 'bg-amber-100 text-amber-800', accepted: 'bg-blue-100 text-blue-800', declined: 'bg-red-100 text-red-800', cancelled: 'bg-gray-100 text-gray-700', out: 'bg-indigo-100 text-indigo-800', returned: 'bg-green-100 text-green-800', given: 'bg-green-100 text-green-800' };

    return (
        <AppShell title={t.admin_title}>
            <FormErrors errors={errors} className="mb-4" />
            <p className="mb-4 text-sm text-gray-600">{t.admin_intro}</p>

            <div className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4" data-testid="lending-stats">
                <Stat label={t.stat_lenders} value={counts.lenders ?? 0} testid="stat-lenders" />
                <Stat label={t.stat_books} value={counts.books ?? 0} testid="stat-books" />
                <Stat label={t.stat_open_loans} value={counts.open_loans ?? 0} testid="stat-open-loans" />
                <Stat label={t.stat_overdue} value={counts.overdue ?? 0} testid="stat-overdue" />
            </div>

            <div className="mb-6">
                <IdentityChecks rows={identity} l={id_l} title={t.id_checks_title} />
            </div>

            <section className="mb-6 rounded-lg border bg-white p-4" data-testid="lenders">
                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 className="font-semibold">{t.lenders_section}</h2>
                    <a href="/admin/lending/lenders/export" className="btn-secondary text-sm" data-testid="export-lenders">{t.export_lenders_csv}</a>
                </div>
                {lenders.length === 0 ? <p className="text-sm text-gray-500" data-testid="lenders-empty">{t.lenders_empty}</p> : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm table-stack">
                            <thead><tr className="text-gray-500"><th className="p-2 text-start">{t.col_lender}</th><th className="p-2 text-start">{t.col_person}</th><th className="p-2 text-start">{t.col_island}</th><th className="p-2 text-end">{t.col_books}</th><th className="p-2 text-end">{t.col_loans}</th><th className="p-2 text-start">{t.col_id}</th><th className="p-2 text-start">{t.col_rating}</th><th className="p-2 text-start">{t.col_since}</th><th className="p-2" /></tr></thead>
                            <tbody>
                                {lenders.map((l) => (
                                    <tr key={l.id} className="border-t align-top" data-testid={`lender-${l.id}`} data-id-verified={l.id_verified ? '1' : '0'}>
                                        <td className="p-2"><span className="font-medium" dir="auto">{l.display_name}</span>{l.id_required && <span className="ms-1 rounded bg-brandMaroon-50 px-1 text-xs text-brandMaroon-800">{t.id_required_badge}</span>}<div className="text-xs text-gray-500">{t[`lender_status_${l.status}`] || l.status}{l.office_paused && <span className="ms-1 rounded bg-red-100 px-1 text-red-800" data-testid={`lender-office-paused-${l.id}`}>{t.paused_by_office}</span>}</div>{l.office_note && <div className="text-xs text-red-700" dir="auto">{l.office_note}</div>}</td>
                                        <td className="p-2 text-xs text-gray-600" dir="auto">{l.person?.name}<div dir="ltr">{l.person?.phone || l.person?.email}</div></td>
                                        <td className="p-2" dir="auto">{l.island || '—'}</td>
                                        <td className="p-2 text-end">{l.books}</td>
                                        <td className="p-2 text-end">{l.loans} ({l.open_loans})</td>
                                        <td className="p-2"><span className={`rounded px-2 py-0.5 text-xs font-semibold ${l.id_verified ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'}`}>{l.id_verified ? id_l.id_status_verified : id_l.id_status_pending}</span></td>
                                        <td className="p-2 text-xs text-amber-700">{stars(l.rating)}</td>
                                        <td className="p-2 text-xs text-gray-500">{l.since}</td>
                                        <td className="p-2"><LenderAction lender={l} t={t} /></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            <section className="mb-6 rounded-lg border bg-white p-4" data-testid="books">
                <h2 className="mb-2 font-semibold">{t.office_books_section}</h2>
                {books.length === 0 ? <p className="text-sm text-gray-500" data-testid="books-empty">{t.office_books_empty}</p> : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm table-stack">
                            <thead><tr className="text-gray-500"><th className="p-2 text-start">{t.col_book}</th><th className="p-2 text-start">{t.col_lender}</th><th className="p-2 text-start">{t.col_status}</th><th className="p-2 text-start">{t.col_since}</th><th className="p-2" /></tr></thead>
                            <tbody>
                                {books.map((b) => (
                                    <tr key={b.id} className="border-t align-top" data-testid={`book-${b.id}`} data-status={b.status}>
                                        <td className="p-2" dir="auto"><a href={`/lending/${b.slug}`} className="font-medium hover:underline">{b.title}</a>{b.author && <div className="text-xs text-gray-500">{b.author}</div>}<div className="text-xs text-gray-500">{b.condition_label}</div></td>
                                        <td className="p-2" dir="auto">{b.lender}</td>
                                        <td className="p-2 text-xs">{b.status_label}</td>
                                        <td className="p-2 text-xs text-gray-500">{b.since}</td>
                                        <td className="p-2">{b.status !== 'on_loan' && <RemoveBook book={b} t={t} />}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            <section className="rounded-lg border bg-white p-4" data-testid="loans">
                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 className="font-semibold">{t.loans_section}</h2>
                    <a href="/admin/lending/export" className="btn-secondary text-sm" data-testid="export-loans">{t.export_csv}</a>
                </div>
                {loans.length === 0 ? <p className="text-sm text-gray-500" data-testid="loans-empty">{t.loans_empty}</p> : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm table-stack">
                            <thead><tr className="text-gray-500"><th className="p-2 text-start">{t.col_book}</th><th className="p-2 text-start">{t.col_lender}</th><th className="p-2 text-start">{t.col_borrower}</th><th className="p-2 text-start">{t.col_status}</th><th className="p-2 text-start">{t.col_requested}</th><th className="p-2 text-start">{t.col_due}</th><th className="p-2 text-start">{t.col_returned}</th></tr></thead>
                            <tbody>
                                {loans.map((l) => (
                                    <tr key={l.id} className="border-t align-top" data-testid={`loan-${l.id}`} data-status={l.status}>
                                        <td className="p-2 font-medium" dir="auto"><a href={`/lending/${l.book_slug}`} className="hover:underline">{l.book}</a></td>
                                        <td className="p-2" dir="auto">{l.lender}</td>
                                        <td className="p-2" dir="auto">{l.borrower}<div className="text-xs text-gray-500" dir="ltr">{l.borrower_phone}</div></td>
                                        <td className="p-2"><span className={`rounded px-2 py-0.5 text-xs font-semibold ${tone[l.status] || ''}`}>{l.status_label}</span>{l.overdue && <span className="ms-1 rounded bg-red-100 px-1 text-xs text-red-800">{t.overdue}</span>}</td>
                                        <td className="p-2 text-xs text-gray-600">{l.requested_at}</td>
                                        <td className="p-2 text-xs text-gray-600">{l.due_on}</td>
                                        <td className="p-2 text-xs text-gray-600">{l.returned_at}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </AppShell>
    );
}
