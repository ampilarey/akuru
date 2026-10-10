import { router } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

/**
 * What came back from one form. Every word is the `academics` book's (slice
 * SE1, STATUS §5qr), and a refused Close is said under the button — it was
 * said nowhere.
 */
export default function Results({ form, rows = [], t = {} }) {
    const refusals = useRowRefusals();
    // Closing is an update that sets `closes_at` to now and changes nothing
    // else: the frozen fields, flags and price go back exactly as they are.
    // Nothing on the screen could close a sheet before this (STATUS §5fq).
    const closeNow = () => refusals.actOn('close', () => router.put(`/forms/${form.id}`, {
        title: form.title,
        description: form.description,
        fields: form.fields,
        target_audience: form.target_audience,
        target_classes: form.target_classes,
        is_anonymous: form.is_anonymous,
        requires_parent_confirmation: form.requires_parent_confirmation,
        fee_amount: form.fee_amount,
        is_published: form.is_published,
        opens_at: form.opens_at,
        closes_at: new Date().toISOString(),
    }));

    return (
        <AppShell title={form.title}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">
                    {[
                        form.responses === 1 ? (t.forms_responses_one || '1 response') : (t.forms_responses || ':count responses').replace(':count', form.responses),
                        form.requires_parent_confirmation && (t.results_confirmed_count || ':count confirmed by a parent').replace(':count', form.confirmed),
                        form.is_anonymous && (t.forms_flag_anonymous || 'anonymous'),
                        !form.is_open && (t.forms_state_closed || 'closed'),
                    ].filter(Boolean).join(' · ')}
                </p>
                <span className="flex flex-wrap gap-2">
                    {form.is_open && (
                        <button type="button" className="btn-secondary" onClick={closeNow}>{t.results_close_now || 'Close sign-up now'}</button>
                    )}
                    <a className="btn-secondary" href={`/forms/${form.id}/export`}>{t.export_csv || 'Export CSV'}</a>
                </span>
            </div>
            <FormErrors errors={refusals.errorsFor('close')} className="mb-4" />
            <FormErrors errors={refusals.unplaced} className="mb-4" />

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="w-full min-w-[40rem] text-sm">
                    <thead className="bg-[#F9F4EE] text-start">
                        <tr>
                            {/* No respondent column at all on an anonymous form —
                                a column of dashes invites someone to go looking. */}
                            {!form.is_anonymous && <th className="p-2">{t.results_who || 'Who'}</th>}
                            <th className="p-2">{t.results_submitted || 'Submitted'}</th>
                            {form.requires_parent_confirmation && <th className="p-2">{t.results_confirmed || 'Confirmed'}</th>}
                            {form.fields.map((f) => <th key={f.key} className="p-2">{f.label}</th>)}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t align-top">
                                {!form.is_anonymous && <td className="p-2">{row.respondent}</td>}
                                <td className="p-2 text-xs text-gray-500">{when(row.submitted_at)}</td>
                                {form.requires_parent_confirmation && (
                                    <td className={`p-2 text-xs ${row.confirmed_at ? 'text-green-700' : 'font-semibold text-[#7C2D37]'}`}>
                                        {when(row.confirmed_at) || (t.results_not_confirmed || 'Not confirmed')}
                                    </td>
                                )}
                                {form.fields.map((f) => {
                                    const v = row.answers[f.key];
                                    return <td key={f.key} className="p-2">{answer(f, v, t)}</td>;
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
                {rows.length === 0 && <p className="p-4 text-sm text-gray-600">{t.results_none || 'No responses yet.'}</p>}
            </div>
        </AppShell>
    );
}

// A time as the school reads it — its date and its hour — rather than the
// ISO stamp the server sends (*2026-10-10T13:05:00+05:00*).
function when(value) {
    return value ? String(value).replace('T', ' ').slice(0, 16) : '';
}

// A yes or no is named in the page's language; every other answer is the
// family's own words, as they wrote it.
function answer(field, value, t) {
    if (field.type === 'yes_no' && (value === 'yes' || value === 'no' || value === true || value === false)) {
        return (value === 'yes' || value === true) ? (t.yes || 'Yes') : (t.no || 'No');
    }

    return Array.isArray(value) ? value.join(t.list_separator || ', ') : (value ?? '');
}
