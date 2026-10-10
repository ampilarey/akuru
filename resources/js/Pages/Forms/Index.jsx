import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * The sign-up forms and surveys the office sends to families. Every word is
 * the `academics` book's (slice SE1, STATUS §5qr); a question's type and a
 * form's state are named rather than printed as codes, every field is named
 * for a screen reader, and every refusal is said — a refused description, a
 * class or a single option was said nowhere.
 */
export default function Index({ forms = [], fieldTypes = [], classes = [], t = {} }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        title: '', description: '',
        fields: [{ label: '', type: 'text', options: [], required: false }],
        target_audience: [], target_classes: [], closes_at: '',
        is_anonymous: false, requires_parent_confirmation: false,
        fee_amount: '', is_published: true,
    });

    const toggleClass = (id) => form.setData('target_classes',
        form.data.target_classes.includes(id)
            ? form.data.target_classes.filter((v) => v !== id)
            : [...form.data.target_classes, id]);

    const setField = (i, patch) => form.setData('fields',
        form.data.fields.map((f, n) => (n === i ? { ...f, ...patch } : f)));

    // What the form says beside its fields; the rest is said under it.
    const saidBeside = ['title', 'closes_at', 'requires_parent_confirmation', 'fee_amount', 'fields',
        ...form.data.fields.flatMap((_, i) => [`fields.${i}.label`, `fields.${i}.type`, `fields.${i}.options`])];
    const stateName = (f) => (f.is_published ? (f.is_open ? (t.forms_state_open || 'open') : (t.forms_state_closed || 'closed')) : (t.forms_state_draft || 'draft'));

    return (
        <AppShell title={t.forms_title || 'Sign-ups and surveys'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">{t.forms_intro || 'Forms you have sent, and what came back.'}</p>
                <button type="button" className="btn-primary" onClick={() => setOpen(!open)}>
                    {open ? (t.cancel || 'Cancel') : (t.forms_new || 'New form')}
                </button>
            </div>

            {open && (
                <form
                    onSubmit={(e) => { e.preventDefault(); form.post('/forms', { onSuccess: () => setOpen(false) }); }}
                    className="mb-6 grid gap-3 rounded-lg border bg-white p-4"
                >
                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">{t.col_title || 'Title'}</span>
                        <input className="form-input w-full" value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)} />
                        {form.errors.title && <span className="text-xs text-red-600">{form.errors.title}</span>}
                    </label>
                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">{t.description || 'Description'}</span>
                        <textarea className="form-input w-full" rows={2} value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)} />
                    </label>

                    <fieldset className="rounded border border-[#E6D9C8] p-3">
                        <legend className="px-1 text-xs uppercase tracking-wide text-gray-500">{t.forms_questions || 'Questions'}</legend>
                        {form.data.fields.map((field, i) => (
                            <div key={i} className="mb-3 grid gap-2 border-b pb-3 last:border-0 sm:grid-cols-[2fr,1fr,auto]">
                                <input className="form-input" aria-label={t.forms_question || 'Question'} placeholder={t.forms_question || 'Question'} value={field.label}
                                    onChange={(e) => setField(i, { label: e.target.value })} />
                                <select className="form-input" aria-label={t.forms_question_type || 'Question type'} value={field.type}
                                    onChange={(e) => setField(i, { type: e.target.value })}>
                                    {fieldTypes.map((type) => <option key={type} value={type}>{t[`form_type_${type}`] || type}</option>)}
                                </select>
                                <label className="flex items-center gap-1 text-xs">
                                    <input type="checkbox" checked={field.required}
                                        onChange={(e) => setField(i, { required: e.target.checked })} />
                                    {t.forms_required || 'Required'}
                                </label>
                                {['select', 'multi_select'].includes(field.type) && (
                                    <input className="form-input sm:col-span-3" aria-label={t.forms_options || 'Options, comma separated'} placeholder={t.forms_options || 'Options, comma separated'}
                                        value={(field.options || []).join(t.forms_options_separator || ', ')}
                                        onChange={(e) => setField(i, { options: e.target.value.split(/[,،]/).map((o) => o.trim()) })} />
                                )}
                                {/* `fields.*.label` is `required` server-side, and nothing
                                    rendered its error. Leaving a question blank made Save
                                    do nothing at all: no save, no message, composer still
                                    open. Every other rule on this form was already
                                    surfaced — this was the one gap, and it sat on the
                                    field a person is most likely to leave empty, because
                                    "Add question" creates it blank. */}
                                {form.errors[`fields.${i}.label`] && (
                                    <span className="text-xs text-red-600 sm:col-span-3">{form.errors[`fields.${i}.label`]}</span>
                                )}
                                {form.errors[`fields.${i}.type`] && (
                                    <span className="text-xs text-red-600 sm:col-span-3">{form.errors[`fields.${i}.type`]}</span>
                                )}
                                {form.errors[`fields.${i}.options`] && (
                                    <span className="text-xs text-red-600 sm:col-span-3">{form.errors[`fields.${i}.options`]}</span>
                                )}
                            </div>
                        ))}
                        <button type="button" className="text-xs text-[#7C2D37] hover:underline"
                            onClick={() => form.setData('fields', [...form.data.fields, { label: '', type: 'text', options: [], required: false }])}>
                            {t.forms_add_question || 'Add question'}
                        </button>
                        {form.errors.fields && <span className="block text-xs text-red-600">{form.errors.fields}</span>}
                    </fieldset>

                    {/* E6's acceptance is a sign-up "targeted at one class" that
                        "closed forms reject": the action honoured both and the
                        screen offered neither, so the office could only send a
                        sheet to the whole school and never close it (STATUS §5fq). */}
                    <div className="text-sm">
                        <span className="mb-1 block text-gray-600">{t.forms_classes || 'Only these classes (optional — blank means everyone)'}</span>
                        <div className="flex max-h-28 flex-wrap gap-2 overflow-y-auto">
                            {classes.map((c) => (
                                <label key={c.id} className="inline-flex items-center gap-1">
                                    <input type="checkbox" checked={form.data.target_classes.includes(c.id)} onChange={() => toggleClass(c.id)} />
                                    {c.label}
                                </label>
                            ))}
                        </div>
                    </div>

                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">{t.forms_closes_at || 'Closes at (optional)'}</span>
                        <input className="form-input w-full sm:w-64" type="datetime-local" value={form.data.closes_at}
                            onChange={(e) => form.setData('closes_at', e.target.value)} />
                        {form.errors.closes_at && <span className="text-xs text-red-600">{form.errors.closes_at}</span>}
                    </label>

                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={form.data.is_anonymous}
                            onChange={(e) => form.setData('is_anonymous', e.target.checked)} />
                        {t.forms_anonymous || 'Anonymous — no name is recorded, and answers cannot be traced back'}
                    </label>

                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={form.data.requires_parent_confirmation}
                            disabled={form.data.is_anonymous}
                            onChange={(e) => form.setData('requires_parent_confirmation', e.target.checked)} />
                        {t.forms_parent_confirmation || 'A pupil’s answer needs a parent to confirm it'}
                    </label>
                    {form.errors.requires_parent_confirmation && (
                        <span className="text-xs text-red-600">{form.errors.requires_parent_confirmation}</span>
                    )}

                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">{t.forms_fee || 'Fee (MVR, leave blank for free)'}</span>
                        <input className="form-input w-full sm:w-40" type="number" min="0" step="0.01"
                            value={form.data.fee_amount} disabled={form.data.is_anonymous}
                            onChange={(e) => form.setData('fee_amount', e.target.value)} />
                        <span className="mt-1 block text-xs text-gray-500">
                            {t.forms_fee_hint || 'Raises an invoice per pupil when they sign up. Paid through the normal invoice screen — the amount cannot be changed once families have been invoiced.'}
                        </span>
                        {form.errors.fee_amount && <span className="text-xs text-red-600">{form.errors.fee_amount}</span>}
                    </label>

                    <FormErrors errors={form.errors} except={saidBeside} />
                    <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                        {t.forms_save || 'Save form'}
                    </button>
                </form>
            )}

            <ul className="grid gap-2">
                {forms.map((f) => (
                    <li key={f.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-white p-3 text-sm">
                        <span>
                            <a className="font-medium text-[#7C2D37] hover:underline" href={`/forms/${f.id}/results`}>{f.title}</a>
                            <span className="ms-2 text-xs uppercase text-gray-500">
                                {[
                                    stateName(f),
                                    f.is_anonymous && (t.forms_flag_anonymous || 'anonymous'),
                                    f.target_classes?.length === 1 && (t.forms_classes_one || '1 class'),
                                    f.target_classes?.length > 1 && (t.forms_classes_count || ':count classes').replace(':count', f.target_classes.length),
                                ].filter(Boolean).join(' · ')}
                            </span>
                        </span>
                        <span className="text-xs text-gray-600">{f.responses === 1 ? (t.forms_responses_one || '1 response') : (t.forms_responses || ':count responses').replace(':count', f.responses)}</span>
                    </li>
                ))}
                {forms.length === 0 && <li className="rounded-lg border bg-white p-4 text-sm text-gray-600">{t.forms_none || 'No forms yet.'}</li>}
            </ul>
        </AppShell>
    );
}
