import { useForm, usePage, router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ entityType, entityTypes, fieldTypes, definitions }) {
    const { errors } = usePage().props;
    const form = useForm({
        entity_type: entityType,
        key: '',
        label_en: '',
        label_dv: '',
        label_ar: '',
        field_type: 'text',
        options_text: '',
        required: false,
        show_in_profile: true,
        show_in_admission_form: false,
        sort_order: 0,
        active: true,
    });

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            options: (data.options_text || '')
                .split('\n')
                .map((line) => line.trim())
                .filter(Boolean),
        })).post('/people/custom-fields', { preserveScroll: true });
    };

    return (
        <AppShell title="Custom field definitions">
            <div className="mb-4 flex flex-wrap gap-2">
                {entityTypes.map((type) => (
                    <button
                        key={type}
                        type="button"
                        onClick={() => router.get('/people/custom-fields', { entity_type: type })}
                        className={`rounded px-3 py-1 text-sm ${type === entityType ? 'bg-[#7C2D37] text-white' : 'bg-white border'}`}
                    >
                        {type}
                    </button>
                ))}
                <a href="/people/custom-fields/admission-preview" className="rounded border bg-white px-3 py-1 text-sm">
                    Admission form preview
                </a>
            </div>

            <form onSubmit={submit} className="mb-8 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2">
                <Field label="Key" error={errors.key}>
                    <input className="form-input" value={form.data.key} onChange={(e) => form.setData('key', e.target.value)} />
                </Field>
                <Field label="Type" error={errors.field_type}>
                    <select className="form-input" value={form.data.field_type} onChange={(e) => form.setData('field_type', e.target.value)}>
                        {fieldTypes.map((type) => (
                            <option key={type} value={type}>{type}</option>
                        ))}
                    </select>
                </Field>
                <Field label="Label (EN)" error={errors.label_en}>
                    <input className="form-input" value={form.data.label_en} onChange={(e) => form.setData('label_en', e.target.value)} />
                </Field>
                <Field label="Label (DV)">
                    <input className="form-input" value={form.data.label_dv} onChange={(e) => form.setData('label_dv', e.target.value)} />
                </Field>
                <Field label="Label (AR)">
                    <input className="form-input" value={form.data.label_ar} onChange={(e) => form.setData('label_ar', e.target.value)} />
                </Field>
                <Field label="Options (one per line)">
                    <textarea className="form-input" rows={3} value={form.data.options_text} onChange={(e) => form.setData('options_text', e.target.value)} />
                </Field>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.required} onChange={(e) => form.setData('required', e.target.checked)} />
                    Required
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.show_in_profile} onChange={(e) => form.setData('show_in_profile', e.target.checked)} />
                    Show in profile
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.show_in_admission_form} onChange={(e) => form.setData('show_in_admission_form', e.target.checked)} />
                    Show in admission form
                </label>
                <div>
                    <button type="submit" className="btn-primary" disabled={form.processing}>Create field</button>
                </div>
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Key</th>
                            <th className="px-3 py-2">Label</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Flags</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {definitions.map((definition) => (
                            <tr key={definition.id} className="border-t">
                                <td className="px-3 py-2 font-mono">{definition.key}</td>
                                <td className="px-3 py-2">{definition.label}</td>
                                <td className="px-3 py-2">{definition.field_type}</td>
                                <td className="px-3 py-2 text-xs text-gray-600">
                                    {definition.required ? 'required ' : ''}
                                    {definition.show_in_admission_form ? 'admission ' : ''}
                                    {definition.active ? 'active' : 'inactive'}
                                </td>
                                <td className="px-3 py-2 text-right">
                                    <button
                                        type="button"
                                        className="text-red-700 hover:underline"
                                        onClick={() => router.delete(`/people/custom-fields/${definition.id}`)}
                                    >
                                        Archive
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function Field({ label, error, children }) {
    return (
        <label className="grid gap-1 text-sm">
            <span className="font-medium">{label}</span>
            {children}
            {error && <span className="text-xs text-red-600">{error}</span>}
        </label>
    );
}
