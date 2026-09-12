import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

const STATUS_LABEL = {
    unmatched: 'Unmatched',
    suggested: 'Suggested',
    confirmed: 'Confirmed',
    ignored: 'Ignored',
};

const STATUS_CLASS = {
    unmatched: 'bg-gray-100 text-gray-700',
    suggested: 'bg-amber-100 text-amber-800',
    confirmed: 'bg-green-100 text-green-800',
    ignored: 'bg-gray-200 text-gray-500',
};

export default function BankStatements({
    imports = [],
    selected_import_id = null,
    lines = [],
    status = null,
    open_invoices = [],
    can_confirm = false,
    expected_columns = [],
}) {
    const upload = useForm({ file: null, account_label: '' });
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
        if (!invoiceId) return;
        router.post(
            `/finance/bank-statements/lines/${line.id}/confirm`,
            { invoice_id: invoiceId },
            { preserveScroll: true },
        );
    };

    const ignore = (line) => {
        router.post(`/finance/bank-statements/lines/${line.id}/ignore`, {}, { preserveScroll: true });
    };

    const exportHref = `/finance/bank-statements/export?${new URLSearchParams({
        ...(selected_import_id ? { import: selected_import_id } : {}),
        ...(status ? { status } : {}),
    }).toString()}`;

    return (
        <AppShell title="Bank statements">
            <p className="mb-4 text-sm text-gray-600">
                Import a bank export and match credits to invoices. Nothing here is a payment until you
                confirm it — and confirming records an ordinary <strong>transfer</strong> receipt, which
                does not grant access to any paid course. Course access still waits on the payment
                gateway.
            </p>

            <form onSubmit={submitUpload} className="mb-6 rounded-lg border bg-white p-4">
                <h2 className="mb-3 font-semibold">Import a statement</h2>
                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <label className="mb-1 block text-sm font-medium" htmlFor="file">CSV file</label>
                        <input
                            id="file"
                            type="file"
                            accept=".csv,text/csv,text/plain"
                            className="form-input"
                            onChange={(e) => upload.setData('file', e.target.files[0] ?? null)}
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium" htmlFor="account_label">Account (optional)</label>
                        <input
                            id="account_label"
                            className="form-input"
                            placeholder="e.g. BML current"
                            value={upload.data.account_label}
                            onChange={(e) => upload.setData('account_label', e.target.value)}
                        />
                    </div>
                    <button type="submit" className="btn-primary" disabled={upload.processing || !upload.data.file}>
                        Import
                    </button>
                </div>
                {expected_columns.length > 0 && (
                    <p className="mt-3 text-xs text-gray-500">
                        Expected column headings: {expected_columns.join(', ')}. These are configurable —
                        if your bank uses different names, they can be mapped without a code change.
                    </p>
                )}
                {Object.values(upload.errors).map((message) => (
                    <p key={message} className="mt-2 text-sm text-red-600">{message}</p>
                ))}
            </form>

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <select
                    className="form-input"
                    aria-label="Statement"
                    value={selected_import_id ?? ''}
                    onChange={(e) => router.get(`/finance/bank-statements?import=${e.target.value}`)}
                >
                    {imports.length === 0 && <option value="">No statements imported yet</option>}
                    {imports.map((row) => (
                        <option key={row.id} value={row.id}>
                            {row.original_filename} · {row.period_start ?? '?'} → {row.period_end ?? '?'} · {row.line_count} lines
                        </option>
                    ))}
                </select>
                <select
                    className="form-input"
                    aria-label="Status filter"
                    value={status ?? ''}
                    onChange={(e) => go({ status: e.target.value })}
                >
                    <option value="">All statuses</option>
                    {Object.entries(STATUS_LABEL).map(([value, label]) => (
                        <option key={value} value={value}>{label}</option>
                    ))}
                </select>
                <a className="btn-secondary" href={exportHref}>Export CSV</a>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Date</th>
                            <th className="px-3 py-2">Description</th>
                            <th className="px-3 py-2">Reference</th>
                            <th className="px-3 py-2">Amount</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Invoice</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {lines.length === 0 && (
                            <tr>
                                <td className="px-3 py-6 text-center text-gray-500" colSpan={7}>
                                    No lines to show.
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
                                        {STATUS_LABEL[line.match_status] ?? line.match_status}
                                    </span>
                                </td>
                                <td className="px-3 py-2">
                                    {line.match_status === 'confirmed' || !line.is_credit ? (
                                        line.invoice_number || '—'
                                    ) : (
                                        <select
                                            className="form-input text-xs"
                                            aria-label={`Invoice for line ${line.id}`}
                                            value={chosen[line.id] ?? line.matched_invoice_id ?? ''}
                                            onChange={(e) => setChosen({ ...chosen, [line.id]: e.target.value })}
                                        >
                                            <option value="">Choose invoice…</option>
                                            {open_invoices.map((invoice) => (
                                                <option key={invoice.id} value={invoice.id}>
                                                    {invoice.invoice_number} · {invoice.balance} due
                                                </option>
                                            ))}
                                        </select>
                                    )}
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap">
                                    {line.match_status !== 'confirmed' && line.is_credit && can_confirm && (
                                        <button type="button" className="btn-primary text-xs" onClick={() => confirm(line)}>
                                            Confirm
                                        </button>
                                    )}
                                    {line.match_status !== 'confirmed' && line.match_status !== 'ignored' && (
                                        <button type="button" className="btn-secondary ms-1 text-xs" onClick={() => ignore(line)}>
                                            Not a payment
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
