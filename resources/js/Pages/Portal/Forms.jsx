import { router, useForm } from '@inertiajs/react';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';
import AppShell from '../../Layouts/AppShell';

function Field({ field, value, onChange, disabled, t }) {
    const common = 'form-input w-full';

    if (field.type === 'textarea') {
        return <textarea className={common} rows={3} aria-label={field.label} value={value || ''} disabled={disabled}
            onChange={(e) => onChange(e.target.value)} />;
    }
    if (field.type === 'date') {
        return <input className={common} type="date" aria-label={field.label} value={value || ''} disabled={disabled}
            onChange={(e) => onChange(e.target.value)} />;
    }
    if (field.type === 'yes_no') {
        return (
            <span className="flex gap-4 text-sm">
                {['yes', 'no'].map((v) => (
                    <label key={v} className="flex items-center gap-1">
                        <input type="radio" checked={value === v} disabled={disabled}
                            onChange={() => onChange(v)} />
                        {v === 'yes' ? (t.forms_yes || 'Yes') : (t.forms_no || 'No')}
                    </label>
                ))}
            </span>
        );
    }
    if (field.type === 'select') {
        return (
            <select className={common} aria-label={field.label} value={value || ''} disabled={disabled}
                onChange={(e) => onChange(e.target.value)}>
                <option value="">—</option>
                {field.options.map((o) => <option key={o} value={o}>{o}</option>)}
            </select>
        );
    }
    if (field.type === 'multi_select') {
        const selected = Array.isArray(value) ? value : [];
        return (
            <span className="grid gap-1 text-sm">
                {field.options.map((o) => (
                    <label key={o} className="flex items-center gap-2">
                        <input type="checkbox" checked={selected.includes(o)} disabled={disabled}
                            onChange={(e) => onChange(e.target.checked
                                ? [...selected, o]
                                : selected.filter((s) => s !== o))} />
                        {o}
                    </label>
                ))}
            </span>
        );
    }
    return <input className={common} type="text" aria-label={field.label} value={value || ''} disabled={disabled}
        onChange={(e) => onChange(e.target.value)} />;
}

function FormCard({ item, children = [], t }) {
    const form = useForm({ answers: {}, student_id: '' });
    const closed = !item.is_open;

    return (
        <li className="rounded-lg border bg-white p-4">
            <div className="mb-1 flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="text-sm font-semibold">{item.title}</h2>
                    {item.answered_at && !item.awaiting_confirmation && (
                    <span className="text-xs text-green-700">{t.forms_answered || 'Answered'}</span>
                )}
                {/* A pupil whose answer is still waiting must be told, or the
                    form looks finished and nobody chases the parent. */}
                {item.awaiting_confirmation && (
                    <span className="text-xs font-semibold text-[#7C2D37]">{t.forms_awaiting_parent || 'Waiting for a parent to confirm'}</span>
                )}
                {closed && <span className="text-xs text-gray-500">{t.forms_closed || 'Closed'}</span>}
            </div>
            {item.description && <p className="mb-3 text-sm text-gray-700">{item.description}</p>}
            {item.fee_amount !== null && item.fee_amount !== undefined && (
                <p className="mb-3 rounded border border-[#E6D9C8] bg-[#F9F4EE] p-2 text-xs text-gray-700">
                    {(t.forms_fee || 'Fee: MVR :amount.').replace(':amount', item.fee_amount.toFixed(2))}
                    {' '}
                    {item.invoice_id
                        ? (t.forms_invoice_raised || 'An invoice has been raised (:status) — pay it on the Invoices page.')
                            .replace(':status', t[`invoice_status_${item.invoice_status}`] || item.invoice_status || '—')
                        : (t.forms_invoice_on_signup || 'An invoice is raised when you sign up.')}
                </p>
            )}
            {item.is_anonymous && (
                <p className="mb-3 text-xs text-gray-500">
                    {t.forms_anonymous || 'Anonymous — your name is not recorded, so this cannot show whether you have answered.'}
                </p>
            )}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/portal/forms/${item.id}/submit`, { preserveScroll: true });
                }}
                className="grid gap-3"
            >
                {/* Only asked when the answer is genuinely ambiguous. */}
                {children.length > 1 && item.fee_amount != null && (
                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">{t.forms_which_child || 'Which child is this for?'}</span>
                        <select className="form-input w-full" value={form.data.student_id}
                            onChange={(e) => form.setData('student_id', e.target.value)}>
                            <option value="">—</option>
                            {children.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                        {form.errors.student_id && <span className="text-xs text-red-600">{form.errors.student_id}</span>}
                    </label>
                )}
                {item.fields.map((field) => (
                    <label key={field.key} className="block text-sm">
                        <span className="mb-1 block text-gray-600">
                            {field.label}{field.required ? ' *' : ''}
                        </span>
                        <Field
                            field={field}
                            value={form.data.answers[field.key]}
                            disabled={closed}
                            t={t}
                            onChange={(v) => form.setData('answers', { ...form.data.answers, [field.key]: v })}
                        />
                        {form.errors[`answers.${field.key}`] && (
                            <span className="text-xs text-red-600">{form.errors[`answers.${field.key}`]}</span>
                        )}
                    </label>
                ))}
                {form.errors.form && <p className="text-xs text-red-600">{form.errors.form}</p>}
                {!closed && (
                    <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                        {item.answered_at ? (t.forms_update || 'Update answer') : (t.forms_submit || 'Submit')}
                    </button>
                )}
            </form>
        </li>
    );
}

function Pending({ rows, t }) {
    // Confirm posts with `router`, so a refusal had no form to show it: it is
    // said under the answer now (BACKLOG C21, slice PT3).
    const refusals = useRowRefusals();

    return (
        <section className="mb-6 rounded-lg border border-[#7C2D37] bg-[#FDFBF8] p-4">
            <h2 className="mb-2 text-sm font-semibold">{t.forms_waiting_you || 'Waiting for you to confirm'}</h2>
            <FormErrors errors={refusals.unplaced} className="mb-2" />
            <ul className="grid gap-3">
                {rows.map((row) => (
                    <li key={row.response_id} className="rounded border bg-white p-3 text-sm">
                        <p className="font-medium">{row.form_title}</p>
                        <p className="text-xs text-gray-600">{(t.forms_child_answered || ':name answered :date').replace(':name', row.child_name).replace(':date', row.submitted_at)}</p>
                        <ul className="mt-2 grid gap-0.5 text-xs text-gray-700">
                            {row.fields.map((f) => {
                                const v = row.answers[f.key];
                                return <li key={f.key}>{f.label}: {Array.isArray(v) ? v.join(', ') : (v ?? '—')}</li>;
                            })}
                        </ul>
                        <button
                            type="button"
                            className="btn-primary mt-3"
                            onClick={() => refusals.actOn(`response:${row.response_id}`, () => router.post(`/portal/forms/responses/${row.response_id}/confirm`, {}, { preserveScroll: true, preserveState: 'errors' }))}
                        >
                            {t.forms_confirm || 'Confirm'}
                        </button>
                        <FormErrors errors={refusals.errorsFor(`response:${row.response_id}`)} className="mt-1" />
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function Forms({ forms = [], pending = [], children: myChildren = [], t = {} }) {
    return (
        <AppShell title={t.forms_title || 'Sign-ups and surveys'}>
            <p className="mb-4 text-sm text-gray-600">{t.forms_intro || 'Forms the school has sent to you.'}</p>

            {pending.length > 0 && <Pending rows={pending} t={t} />}
            {forms.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">{t.forms_none || 'Nothing to fill in right now.'}</p>
            )}
            <ul className="grid gap-3">
                {forms.map((item) => <FormCard key={item.id} item={item} children={myChildren} t={t} />)}
            </ul>
        </AppShell>
    );
}
