import { useForm } from '@inertiajs/react';

/**
 * COMMERCE_PARITY_PLAN P2/P3: identity cards — the front, and an optional
 * back (C17 slice R3, STATUS §5of: everything needed is on the front).
 *
 *  - <IdentityCardFields>: the two file inputs, inside a form that already
 *    exists (an application); the form must post with forceFormData.
 *  - <IdentityCardUpload>: a card of its own for a portal — where the person
 *    stands, and the upload when they have none verified.
 *  - <IdentityChecks>: the office's list — both sides, verify or reject with
 *    a note.
 *
 * Labels come from resources/lang/{en,dv,ar}/account.php (`id_*`), passed in
 * as `l`.
 */
export function IdentityCardFields({ form, l, required = true }) {
    return (
        <fieldset className="rounded border border-gray-200 p-3 md:col-span-2" data-testid="id-card-fields">
            <legend className="px-1 text-sm font-semibold">{l.id_title}</legend>
            <p className="mb-2 text-xs text-gray-500">{l.id_hint}</p>
            <div className="grid gap-3 sm:grid-cols-2">
                <label className="text-sm">{l.id_front}
                    <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required={required} className="mt-1 block w-full text-sm"
                        onChange={(e) => form.setData('id_front', e.target.files[0] ?? null)} data-testid="id-front" />
                    {form.errors.id_front && <span className="text-xs text-red-700">{form.errors.id_front}</span>}
                </label>
                <label className="text-sm">{l.id_back}
                    <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" className="mt-1 block w-full text-sm"
                        onChange={(e) => form.setData('id_back', e.target.files[0] ?? null)} data-testid="id-back" />
                    {form.errors.id_back && <span className="text-xs text-red-700">{form.errors.id_back}</span>}
                </label>
            </div>
        </fieldset>
    );
}

export function IdentityStatus({ status, l }) {
    const tone = { verified: 'bg-green-100 text-green-800', pending: 'bg-amber-100 text-amber-800', rejected: 'bg-red-100 text-red-800', none: 'bg-gray-100 text-gray-700' }[status] ?? 'bg-gray-100 text-gray-700';

    return <span className={`rounded px-2 py-0.5 text-xs font-semibold ${tone}`} data-id-status={status}>{l[`id_status_${status}`] ?? status}</span>;
}

export function IdentityCardUpload({ identity, href, l, blurb }) {
    const form = useForm({ id_front: null, id_back: null });
    if (!identity || identity.status === 'verified') return null;
    const canUpload = identity.status !== 'pending';

    return (
        <section className="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4" data-testid="identity-card">
            <div className="mb-1 flex flex-wrap items-center gap-2">
                <h2 className="font-semibold">{l.id_verify_title}</h2>
                <IdentityStatus status={identity.status} l={l} />
            </div>
            <p className="mb-2 text-sm text-gray-700">{identity.status === 'pending' ? l.id_pending_body : blurb}</p>
            {identity.status === 'rejected' && identity.note && <p className="mb-2 text-sm text-red-800" data-testid="identity-note">{l.id_rejected_note}: {identity.note}</p>}
            {canUpload && (
                <form className="grid gap-3" onSubmit={(e) => { e.preventDefault(); form.post(href, { forceFormData: true, preserveScroll: true }); }} data-testid="identity-form">
                    <IdentityCardFields form={form} l={l} />
                    <div><button type="submit" className="btn-primary" disabled={form.processing} data-testid="identity-submit">{l.id_submit}</button></div>
                </form>
            )}
        </section>
    );
}

function DecideRow({ row, l }) {
    const form = useForm({ decision: 'verify', note: '' });
    const decide = (decision) => (e) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, decision }));
        form.post(row.decide_url, { preserveScroll: true });
    };

    return (
        <form className="flex flex-wrap items-center gap-2" onSubmit={decide('verify')}>
            <input className="form-input w-48 text-sm" placeholder={l.id_note_placeholder} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} maxLength={500} data-testid={`identity-note-${row.id}`} />
            <button type="submit" className="rounded bg-green-700 px-3 py-1 text-sm font-semibold text-white" disabled={form.processing} data-testid={`identity-verify-${row.id}`}>{l.id_verify}</button>
            <button type="button" onClick={decide('reject')} className="rounded border border-red-300 px-3 py-1 text-sm text-red-800" disabled={form.processing} data-testid={`identity-reject-${row.id}`}>{l.id_reject}</button>
            {form.errors.note && <span className="text-xs text-red-700">{form.errors.note}</span>}
        </form>
    );
}

export function IdentityChecks({ rows = [], l, title }) {
    return (
        <section className="rounded-lg border bg-white p-4" data-testid="identity-checks">
            <h2 className="mb-2 font-semibold">{title ?? l.id_checks_title}</h2>
            {rows.length === 0 ? <p className="text-sm text-gray-500">{l.id_checks_none}</p> : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead><tr className="text-start text-gray-500"><th className="p-2 text-start">{l.id_person}</th><th className="p-2 text-start">{l.id_card}</th><th className="p-2 text-start">{l.id_state}</th><th className="p-2" /></tr></thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.id} className="border-t align-top" data-identity-row={row.id} data-identity-status={row.status}>
                                    <td className="p-2"><div className="font-medium" dir="auto">{row.name}</div><div className="text-xs text-gray-500" dir="ltr">{row.phone || row.email}</div><div className="text-xs text-gray-500">{row.submitted_at}</div></td>
                                    <td className="p-2">
                                        <a href={row.front_url} target="_blank" rel="noreferrer" className="me-2 text-blue-700 underline" data-testid={`identity-front-${row.id}`}>{l.id_front}</a>
                                        {row.back_url && <a href={row.back_url} target="_blank" rel="noreferrer" className="text-blue-700 underline" data-testid={`identity-back-${row.id}`}>{l.id_back}</a>}
                                    </td>
                                    <td className="p-2"><IdentityStatus status={row.status} l={l} />{row.note && <div className="text-xs text-gray-600">{row.note}</div>}</td>
                                    <td className="p-2">{row.status === 'pending' ? <DecideRow row={row} l={l} /> : <span className="text-xs text-gray-500">{row.decided_at}</span>}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}
