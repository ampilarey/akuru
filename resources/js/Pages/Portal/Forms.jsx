import { router, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

function Field({ field, value, onChange, disabled }) {
    const common = 'form-input w-full';

    if (field.type === 'textarea') {
        return <textarea className={common} rows={3} value={value || ''} disabled={disabled}
            onChange={(e) => onChange(e.target.value)} />;
    }
    if (field.type === 'date') {
        return <input className={common} type="date" value={value || ''} disabled={disabled}
            onChange={(e) => onChange(e.target.value)} />;
    }
    if (field.type === 'yes_no') {
        return (
            <span className="flex gap-4 text-sm">
                {['yes', 'no'].map((v) => (
                    <label key={v} className="flex items-center gap-1">
                        <input type="radio" checked={value === v} disabled={disabled}
                            onChange={() => onChange(v)} />
                        {v === 'yes' ? 'Yes' : 'No'}
                    </label>
                ))}
            </span>
        );
    }
    if (field.type === 'select') {
        return (
            <select className={common} value={value || ''} disabled={disabled}
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
    return <input className={common} type="text" value={value || ''} disabled={disabled}
        onChange={(e) => onChange(e.target.value)} />;
}

function FormCard({ item }) {
    const form = useForm({ answers: {} });
    const closed = !item.is_open;

    return (
        <li className="rounded-lg border bg-white p-4">
            <div className="mb-1 flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="text-sm font-semibold">{item.title}</h2>
                    {item.answered_at && !item.awaiting_confirmation && (
                    <span className="text-xs text-green-700">Answered</span>
                )}
                {/* A pupil whose answer is still waiting must be told, or the
                    form looks finished and nobody chases the parent. */}
                {item.awaiting_confirmation && (
                    <span className="text-xs font-semibold text-[#7C2D37]">Waiting for a parent to confirm</span>
                )}
                {closed && <span className="text-xs text-gray-500">Closed</span>}
            </div>
            {item.description && <p className="mb-3 text-sm text-gray-700">{item.description}</p>}
            {item.is_anonymous && (
                <p className="mb-3 text-xs text-gray-500">
                    Anonymous — your name is not recorded, so this cannot show whether you have answered.
                </p>
            )}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/portal/forms/${item.id}/submit`, { preserveScroll: true });
                }}
                className="grid gap-3"
            >
                {item.fields.map((field) => (
                    <label key={field.key} className="block text-sm">
                        <span className="mb-1 block text-gray-600">
                            {field.label}{field.required ? ' *' : ''}
                        </span>
                        <Field
                            field={field}
                            value={form.data.answers[field.key]}
                            disabled={closed}
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
                        {item.answered_at ? 'Update answer' : 'Submit'}
                    </button>
                )}
            </form>
        </li>
    );
}

function Pending({ rows }) {
    return (
        <section className="mb-6 rounded-lg border border-[#7C2D37] bg-[#FDFBF8] p-4">
            <h2 className="mb-2 text-sm font-semibold">Waiting for you to confirm</h2>
            <ul className="grid gap-3">
                {rows.map((row) => (
                    <li key={row.response_id} className="rounded border bg-white p-3 text-sm">
                        <p className="font-medium">{row.form_title}</p>
                        <p className="text-xs text-gray-600">{row.child_name} answered {row.submitted_at}</p>
                        <ul className="mt-2 grid gap-0.5 text-xs text-gray-700">
                            {row.fields.map((f) => {
                                const v = row.answers[f.key];
                                return <li key={f.key}>{f.label}: {Array.isArray(v) ? v.join(', ') : (v ?? '—')}</li>;
                            })}
                        </ul>
                        <button
                            type="button"
                            className="btn-primary mt-3"
                            onClick={() => router.post(`/portal/forms/responses/${row.response_id}/confirm`, {}, { preserveScroll: true })}
                        >
                            Confirm
                        </button>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function Forms({ forms = [], pending = [] }) {
    return (
        <AppShell title="Sign-ups and surveys">
            <p className="mb-4 text-sm text-gray-600">Forms the school has sent to you.</p>

            {pending.length > 0 && <Pending rows={pending} />}
            {forms.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">Nothing to fill in right now.</p>
            )}
            <ul className="grid gap-3">
                {forms.map((item) => <FormCard key={item.id} item={item} />)}
            </ul>
        </AppShell>
    );
}
