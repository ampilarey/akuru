import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';

const STATUSES = ['unmatched', 'suggested', 'confirmed', 'ignored'];

const STATUS_CLASS = {
    unmatched: 'bg-gray-100 text-gray-700',
    suggested: 'bg-amber-100 text-amber-800',
    confirmed: 'bg-green-100 text-green-800',
    ignored: 'bg-gray-200 text-gray-500',
};

/**
 * A bank's statement, imported, and its credits matched to invoices. Every
 * word is the `finance` book's (slice FN2, STATUS §5qo); a line's state is
 * named from the book, and an open invoice says whose it is. A refused
 * Confirm or Not a payment is said under its line — a debit confirmed, a
 * line with no invoice chosen, an invoice already paid and a receipt
 * ignored were refused with nothing on the page.
 */
export default function BankStatements({
    imports = [],
    selected_import_id = null,
    lines = [],
    status = null,
    open_invoices = [],
    can_confirm = false,
    expected_columns = [],
    t = {},
}) {
    const statusName = (value) => t[`match_status_${value}`] || value;
    const upload = useForm({ file: null, account_label: '' });
    const refusals = useRowRefusals(upload);
    // Which invoice each line should be confirmed against. Seeded from the
    // suggestion, but the person can override it before confirming — that
    // override is the entire point of suggesting rather than auto-applying.
    const [chosen, setChosen] = useState({});

    const submitUpload = (event) => {
        event.preventDefault();
        upload.post('/finance/bank-statements', { forceFormData: true });
    };

    const go = (params) => {
        const query = new URLSearchParams();
        if (selected_import_id) query.set('import', selected_import_id);
        Object.entries(params).forEach(([k, v]) => (v ? query.set(k, v) : query.delete(k)));
        router.get(`/finance/bank-statements?${query.toString()}`, {}, { preserveScroll: true });
    };

    const confirm = (line) => {
        const invoiceId = chosen[line.id] ?? line.matched_invoice_id ?? '';
        refusals.actOn(`line:${line.id}`, () => router.post(`/finance/bank-statements/lines/${line.id}/confirm`, { invoice_id: invoiceId || null }, { preserveScroll: true }));
    };

    const ignore = (line) => {
        refusals.actOn(`line:${line.id}`, () => router.post(`/finance/bank-statements/lines/${line.id}/ignore`, {}, { preserveScroll: true }));
    };

    const exportHref = `/finance/bank-statements/export?${new URLSearchParams({
        ...(selected_import_id ? { import: selected_import_id } : {}),
        ...(status ? { status } : {}),
    }).toString()}`;
    const importLabel = (row) => (t.bank_import_option || ':file · :start → :end · :count lines')
        .replace(':file', row.original_filename)
        .replace(':start', row.period_start ?? '?')
        .replace(':end', row.period_end ?? '?')
        .replace(':count', row.line_count);

    return (
        <AppShell title={t.bank_title || 'Bank statements'}>
            <p className="mb-4 text-sm text-gray-600">
                {t.bank_intro || 'Import a bank export and match credits to invoices. Nothing here is a payment until you confirm it, and confirming records an ordinary transfer receipt, which does not grant access to any paid course: course access still waits on the payment gateway.'}
            </p>

            <form onSubmit={submitUpload} className="mb-6 rounded-lg border bg-white p-4">
                <h2 className="mb-3 font-semibold">{t.bank_import_title || 'Import a statement'}</h2>
                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <label className="mb-1 block text-sm font-medium" htmlFor="file">{t.bank_file || 'CSV file'}</label>
                        <input
                            id="file"
                            type="file"
                            accept=".csv,text/csv,text/plain"
                            className="form-input"
                            onChange={(e) => upload.setData('file', e.target.files[0] ?? null)}
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium" htmlFor="account_label">{t.bank_account || 'Account (optional)'}</label>
                        <input
                            id="account_label"
                            className="form-input"
                            placeholder={t.bank_account_hint || 'e.g. BML current'}
                            value={upload.data.account_label}
                            onChange={(e) => upload.setData('account_label', e.target.value)}
                        />
                    </div>
                    <button type="submit" className="btn-primary" disabled={upload.processing || !upload.data.file}>
                        {t.bank_import || 'Import'}
                    </button>
                </div>
                {expected_columns.length > 0 && (
                    <p className="mt-3 text-xs text-gray-500">
                        {(t.bank_expected || 'Expected column headings: :columns. They can be mapped to your bank’s own names without a code change.').replace(':columns', expected_columns.join(t.list_separator || ', '))}
                    </p>
                )}
                <FormErrors errors={upload.errors} className="mt-2" />
            </form>
            <FormErrors errors={refusals.unplaced} className="mb-4" />

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <select
                    className="form-input"
                    aria-label={t.bank_statement || 'Statement'}
                    value={selected_import_id ?? ''}
                    onChange={(e) => router.get(`/finance/bank-statements?import=${e.target.value}`)}
                >
                    {imports.length === 0 && <option value="">{t.bank_no_imports || 'No statements imported yet'}</option>}
                    {imports.map((row) => <option key={row.id} value={row.id}>{importLabel(row)}</option>)}
                </select>
                <select
                    className="form-input"
                    aria-label={t.bank_status_filter || 'Status filter'}
                    value={status ?? ''}
                    onChange={(e) => go({ status: e.target.value })}
                >
                    <option value="">{t.bank_all_statuses || 'All statuses'}</option>
                    {STATUSES.map((value) => <option key={value} value={value}>{statusName(value)}</option>)}
                </select>
                <a className="btn-secondary" href={exportHref}>{t.export_csv || 'Export CSV'}</a>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.reconciliation_date || 'Date'}</th>
                            <th className="px-3 py-2">{t.bank_description || 'Description'}</th>
                            <th className="px-3 py-2">{t.bank_reference || 'Reference'}</th>
                            <th className="px-3 py-2">{t.amount || 'Amount'}</th>
                            <th className="px-3 py-2">{t.status || 'Status'}</th>
                            <th className="px-3 py-2">{t.plans_col_invoice || 'Invoice'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {lines.length === 0 && (
                            <tr>
                                <td className="px-3 py-6 text-center text-gray-500" colSpan={7}>
                                    {t.bank_no_lines || 'No lines to show.'}
                                </td>
                            </tr>
                        )}
                        {lines.map((line) => (
                            <tr key={line.id} className="border-t align-top">
                                <td className="px-3 py-2 whitespace-nowrap">{line.posted_on}</td>
                                <td className="px-3 py-2">
                                    {line.description || '—'}
                                    {line.match_note && (
                                        <p className="mt-1 text-xs text-gray-500">{line.match_note}</p>
                                    )}
                                </td>
                                <td className="px-3 py-2">{line.reference || '—'}</td>
                                <td className={`px-3 py-2 whitespace-nowrap ${line.is_credit ? '' : 'text-gray-500'}`}>
                                    {line.amount}
                                </td>
                                <td className="px-3 py-2">
                                    <span className={`rounded px-2 py-0.5 text-xs ${STATUS_CLASS[line.match_status] ?? ''}`}>
                                        {statusName(line.match_status)}
                                    </span>
                                </td>
                                <td className="px-3 py-2">
                                    {line.match_status === 'confirmed' || !line.is_credit ? (
                                        line.invoice_number || '—'
                                    ) : (
                                        <select
                                            className="form-input text-xs"
                                            aria-label={(t.bank_invoice_for || 'Invoice for line :id').replace(':id', line.id)}
                                            value={chosen[line.id] ?? line.matched_invoice_id ?? ''}
                                            onChange={(e) => setChosen({ ...chosen, [line.id]: e.target.value })}
                                        >
                                            <option value="">{t.bank_choose_invoice || 'Choose an invoice…'}</option>
                                            {open_invoices.map((invoice) => (
                                                <option key={invoice.id} value={invoice.id}>
                                                    {(t.plans_invoice_option || ':number — :student — :balance due')
                                                        .replace(':number', invoice.invoice_number).replace(':student', invoice.student_name || '—').replace(':balance', invoice.balance)}
                                                </option>
                                            ))}
                                        </select>
                                    )}
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap">
                                    {line.match_status !== 'confirmed' && line.is_credit && can_confirm && (
                                        <button type="button" className="btn-primary text-xs" onClick={() => confirm(line)}>
                                            {t.bank_confirm || 'Confirm'}
                                        </button>
                                    )}
                                    {line.match_status !== 'confirmed' && line.match_status !== 'ignored' && (
                                        <button type="button" className="btn-secondary ms-1 text-xs" onClick={() => ignore(line)}>
                                            {t.bank_ignore || 'Not a payment'}
                                        </button>
                                    )}
                                    <FormErrors errors={refusals.errorsFor(`line:${line.id}`)} className="mt-1 whitespace-normal" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
