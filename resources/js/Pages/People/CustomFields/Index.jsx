import { useForm, usePage, router } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';

/**
 * The custom fields the school adds to its records. Every word is the
 * `people` book's (slice PE2, STATUS §5qq); whose record a field belongs to,
 * its type and its settings are named rather than printed as codes, a field
 * reads by its label in the page's language, and every refusal of the form
 * is said — a refused Dhivehi or Arabic label, or its options, was said
 * nowhere.
 */
export default function Index({ entityType, entityTypes, fieldTypes, definitions, t = {} }) {
    const { errors } = usePage().props;
    const entityName = (type) => t[`entity_${type}`] || type;
    const fieldTypeName = (type) => t[`field_type_${type}`] || type;
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
    const refusals = useRowRefusals(form);

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            options: (data.options_text || '')
                .split('\n')
                .map((line) => line.trim())
                .filter(Boolean),
        }));
        form.post('/people/custom-fields', { preserveScroll: true });
    };

    return (
        <AppShell title={t.fields_title || 'Custom field definitions'}>
            <div className="mb-4 flex flex-wrap gap-2">
                {entityTypes.map((type) => (
                    <button
                        key={type}
                        type="button"
                        onClick={() => router.get('/people/custom-fields', { entity_type: type })}
                        className={`rounded px-3 py-1 text-sm ${type === entityType ? 'bg-[#7C2D37] text-white' : 'bg-white border'}`}
                    >
                        {entityName(type)}
                    </button>
                ))}
                <a href="/people/custom-fields/admission-preview" className="rounded border bg-white px-3 py-1 text-sm">
                    {t.fields_preview || 'Admission form preview'}
                </a>
            </div>

            <form onSubmit={submit} className="mb-8 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2">
                <Field label={t.fields_key || 'Key'} error={errors.key}>
                    <input className="form-input" aria-label={t.fields_key || 'Key'} value={form.data.key} onChange={(e) => form.setData('key', e.target.value)} />
                </Field>
                <Field label={t.col_type || 'Type'} error={errors.field_type}>
                    <select className="form-input" aria-label={t.col_type || 'Type'} value={form.data.field_type} onChange={(e) => form.setData('field_type', e.target.value)}>
                        {fieldTypes.map((type) => (
                            <option key={type} value={type}>{fieldTypeName(type)}</option>
                        ))}
                    </select>
                </Field>
                <Field label={t.fields_label_en || 'Label (EN)'} error={errors.label_en}>
                    <input className="form-input" aria-label={t.fields_label_en || 'Label (EN)'} value={form.data.label_en} onChange={(e) => form.setData('label_en', e.target.value)} />
                </Field>
                <Field label={t.fields_label_dv || 'Label (DV)'} error={errors.label_dv}>
                    <input className="form-input" aria-label={t.fields_label_dv || 'Label (DV)'} value={form.data.label_dv} onChange={(e) => form.setData('label_dv', e.target.value)} />
                </Field>
                <Field label={t.fields_label_ar || 'Label (AR)'} error={errors.label_ar}>
                    <input className="form-input" aria-label={t.fields_label_ar || 'Label (AR)'} value={form.data.label_ar} onChange={(e) => form.setData('label_ar', e.target.value)} />
                </Field>
                <Field label={t.fields_options || 'Options (one per line)'} error={errors.options}>
                    <textarea className="form-input" aria-label={t.fields_options || 'Options (one per line)'} rows={3} value={form.data.options_text} onChange={(e) => form.setData('options_text', e.target.value)} />
                </Field>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.required} onChange={(e) => form.setData('required', e.target.checked)} />
                    {t.fields_required || 'Required'}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.show_in_profile} onChange={(e) => form.setData('show_in_profile', e.target.checked)} />
                    {t.fields_in_profile || 'Show in profile'}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.show_in_admission_form} onChange={(e) => form.setData('show_in_admission_form', e.target.checked)} />
                    {t.fields_in_admission || 'Show in admission form'}
                </label>
                <div>
                    <button type="submit" className="btn-primary" disabled={form.processing}>{t.fields_create || 'Create field'}</button>
                </div>
                <FormErrors errors={form.errors} except={['key', 'field_type', 'label_en', 'label_dv', 'label_ar', 'options']} className="md:col-span-2" />
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.fields_key || 'Key'}</th>
                            <th className="px-3 py-2">{t.fields_label || 'Label'}</th>
                            <th className="px-3 py-2">{t.col_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.fields_settings || 'Settings'}</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {definitions.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.fields_none || 'No custom field for these records yet.'}</td></tr>
                        )}
                        {definitions.map((definition) => (
                            <tr key={definition.id} className="border-t align-top">
                                <td className="px-3 py-2 font-mono">{definition.key}</td>
                                <td className="px-3 py-2">{definition.label}</td>
                                <td className="px-3 py-2">{fieldTypeName(definition.field_type)}</td>
                                <td className="px-3 py-2 text-xs text-gray-600">
                                    {[
                                        definition.required && (t.fields_flag_required || 'required'),
                                        definition.show_in_admission_form && (t.fields_flag_admission || 'on the admission form'),
                                        definition.active ? (t.fields_flag_active || 'active') : (t.fields_flag_inactive || 'inactive'),
                                    ].filter(Boolean).join(t.list_separator || ', ')}
                                </td>
                                <td className="px-3 py-2 text-end">
                                    <button
                                        type="button"
                                        className="text-red-700 hover:underline"
                                        onClick={() => refusals.actOn(`field:${definition.id}`, () => router.delete(`/people/custom-fields/${definition.id}`, { preserveScroll: true }))}
                                    >
                                        {t.fields_archive || 'Archive'}
                                    </button>
                                    <FormErrors errors={refusals.errorsFor(`field:${definition.id}`)} className="mt-1 text-start" />
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
