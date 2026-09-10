import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

const TYPE_LABELS = {
    text: 'Short text', textarea: 'Long text', select: 'Choose one',
    multi_select: 'Choose several', yes_no: 'Yes / No', date: 'Date',
};

export default function Index({ forms = [], fieldTypes = [] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        title: '', description: '',
        fields: [{ label: '', type: 'text', options: [], required: false }],
        target_audience: [], is_anonymous: false, requires_parent_confirmation: false,
        fee_amount: '', is_published: true,
    });

    const setField = (i, patch) => form.setData('fields',
        form.data.fields.map((f, n) => (n === i ? { ...f, ...patch } : f)));

    return (
        <AppShell title="Sign-ups and surveys">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">Forms you have sent, and what came back.</p>
                <button type="button" className="btn-primary" onClick={() => setOpen(!open)}>
                    {open ? 'Cancel' : 'New form'}
                </button>
            </div>

            {open && (
                <form
                    onSubmit={(e) => { e.preventDefault(); form.post('/forms', { onSuccess: () => setOpen(false) }); }}
                    className="mb-6 grid gap-3 rounded-lg border bg-white p-4"
                >
                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">Title</span>
                        <input className="form-input w-full" value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)} />
                        {form.errors.title && <span className="text-xs text-red-600">{form.errors.title}</span>}
                    </label>
                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">Description</span>
                        <textarea className="form-input w-full" rows={2} value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)} />
                    </label>

                    <fieldset className="rounded border border-[#E6D9C8] p-3">
                        <legend className="px-1 text-xs uppercase tracking-wide text-gray-500">Questions</legend>
                        {form.data.fields.map((field, i) => (
                            <div key={i} className="mb-3 grid gap-2 border-b pb-3 last:border-0 sm:grid-cols-[2fr,1fr,auto]">
                                <input className="form-input" placeholder="Question" value={field.label}
                                    onChange={(e) => setField(i, { label: e.target.value })} />
                                <select className="form-input" value={field.type}
                                    onChange={(e) => setField(i, { type: e.target.value })}>
                                    {fieldTypes.map((t) => <option key={t} value={t}>{TYPE_LABELS[t] || t}</option>)}
                                </select>
                                <label className="flex items-center gap-1 text-xs">
                                    <input type="checkbox" checked={field.required}
                                        onChange={(e) => setField(i, { required: e.target.checked })} />
                                    Required
                                </label>
                                {['select', 'multi_select'].includes(field.type) && (
                                    <input className="form-input sm:col-span-3" placeholder="Options, comma separated"
                                        value={(field.options || []).join(', ')}
                                        onChange={(e) => setField(i, { options: e.target.value.split(',').map((o) => o.trim()) })} />
                                )}
                                {form.errors[`fields.${i}.options`] && (
                                    <span className="text-xs text-red-600 sm:col-span-3">{form.errors[`fields.${i}.options`]}</span>
                                )}
                            </div>
                        ))}
                        <button type="button" className="text-xs text-[#7C2D37] hover:underline"
                            onClick={() => form.setData('fields', [...form.data.fields, { label: '', type: 'text', options: [], required: false }])}>
                            Add question
                        </button>
                        {form.errors.fields && <span className="block text-xs text-red-600">{form.errors.fields}</span>}
                    </fieldset>

                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={form.data.is_anonymous}
                            onChange={(e) => form.setData('is_anonymous', e.target.checked)} />
                        Anonymous — no name is recorded, and answers cannot be traced back
                    </label>

                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={form.data.requires_parent_confirmation}
                            disabled={form.data.is_anonymous}
                            onChange={(e) => form.setData('requires_parent_confirmation', e.target.checked)} />
                        A pupil&apos;s answer needs a parent to confirm it
                    </label>
                    {form.errors.requires_parent_confirmation && (
                        <span className="text-xs text-red-600">{form.errors.requires_parent_confirmation}</span>
                    )}

                    <label className="block text-sm">
                        <span className="mb-1 block text-gray-600">Fee (MVR, leave blank for free)</span>
                        <input className="form-input w-full sm:w-40" type="number" min="0" step="0.01"
                            value={form.data.fee_amount} disabled={form.data.is_anonymous}
                            onChange={(e) => form.setData('fee_amount', e.target.value)} />
                        <span className="mt-1 block text-xs text-gray-500">
                            Raises an invoice per pupil when they sign up. Paid through the normal invoice
                            screen — the amount cannot be changed once families have been invoiced.
                        </span>
                        {form.errors.fee_amount && <span className="text-xs text-red-600">{form.errors.fee_amount}</span>}
                    </label>

                    <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                        Save form
                    </button>
                </form>
            )}

            <ul className="grid gap-2">
                {forms.map((f) => (
                    <li key={f.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-white p-3 text-sm">
                        <span>
                            <a className="font-medium text-[#7C2D37] hover:underline" href={`/forms/${f.id}/results`}>{f.title}</a>
                            <span className="ms-2 text-xs uppercase text-gray-500">
                                {f.is_published ? (f.is_open ? 'open' : 'closed') : 'draft'}
                                {f.is_anonymous ? ' · anonymous' : ''}
                            </span>
                        </span>
                        <span className="text-xs text-gray-600">{f.responses} response{f.responses === 1 ? '' : 's'}</span>
                    </li>
                ))}
                {forms.length === 0 && <li className="rounded-lg border bg-white p-4 text-sm text-gray-600">No forms yet.</li>}
            </ul>
        </AppShell>
    );
}
