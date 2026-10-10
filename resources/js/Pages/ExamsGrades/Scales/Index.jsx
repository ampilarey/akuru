import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

const defaultBands = JSON.stringify([
    { min: 85, grade: 'A', point: 4, descriptor_en: 'Excellent' },
    { min: 0, grade: 'E', point: 0, descriptor_en: 'Fail' },
], null, 2);

/**
 * Grade scales. Every word is the `exams` book's, and a scale's kind is named
 * rather than printed as its code (slice EG1, STATUS §5qj). A band's grade
 * and descriptor are the school's, written in the bands.
 */
export default function Index({ scales, types, t = {} }) {
    const form = useForm({
        name: '',
        type: types[0] || 'percentage_bands',
        bands: defaultBands,
        is_default: false,
        active: true,
    });

    return (
        <AppShell title={t.scales_title || 'Grade scales'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/exams/scales/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((data) => ({ ...data, bands: parseJson(data.bands) }));
                    form.post('/exams/scales', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2"
            >
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.name || 'Name'}</span>
                    <input className="form-input w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    {form.errors.name && <span className="text-xs text-red-600">{form.errors.name}</span>}
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.type || 'Type'}</span>
                    <select className="form-input w-full" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((type) => <option key={type} value={type}>{t[`scale_type_${type}`] || type}</option>)}
                    </select>
                </label>
                <label className="text-sm md:col-span-2">
                    <span className="mb-1 block text-gray-600">{t.scales_bands || 'Bands (JSON)'}</span>
                    <textarea className="form-input w-full font-mono text-xs" dir="ltr" rows={6} value={form.data.bands} onChange={(e) => form.setData('bands', e.target.value)} />
                    {form.errors.bands && <span className="text-xs text-red-600">{form.errors.bands}</span>}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.is_default} onChange={(e) => form.setData('is_default', e.target.checked)} />
                    {t.scales_default || 'Default scale'}
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.scales_create || 'Create scale'}</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.name || 'Name'}</th>
                            <th className="px-3 py-2">{t.type || 'Type'}</th>
                            <th className="px-3 py-2">{t.scales_col_default || 'Default'}</th>
                            <th className="px-3 py-2">{t.scales_col_bands || 'Bands'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {scales.map((row) => <ScaleRow key={row.id} scale={row} types={types} t={t} />)}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function ScaleRow({ scale, types, t }) {
    const form = useForm({
        name: scale.name,
        type: scale.type,
        bands: JSON.stringify(scale.bands, null, 2),
        is_default: scale.is_default,
        active: scale.active,
    });
    const field = (phrase) => phrase.replace(':name', scale.name);
    const refused = form.errors.name || form.errors.bands || form.errors.type;

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-2">
                <input className="form-input w-full" aria-label={field(t.scales_name_of || 'Name: :name')} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                {refused && <span className="mt-1 block text-xs text-red-600">{refused}</span>}
            </td>
            <td className="px-3 py-2">
                <select className="form-input w-full" aria-label={field(t.scales_type_of || 'Type: :name')} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                    {types.map((type) => <option key={type} value={type}>{t[`scale_type_${type}`] || type}</option>)}
                </select>
            </td>
            <td className="px-3 py-2">
                <input type="checkbox" aria-label={field(t.scales_default_of || 'Default: :name')} checked={form.data.is_default} onChange={(e) => form.setData('is_default', e.target.checked)} />
            </td>
            <td className="px-3 py-2">
                <textarea className="form-input w-full font-mono text-xs" dir="ltr" rows={4} aria-label={field(t.scales_bands_of || 'Bands: :name')} value={form.data.bands} onChange={(e) => form.setData('bands', e.target.value)} />
            </td>
            <td className="px-3 py-2">
                <button
                    type="button"
                    className="btn-secondary"
                    disabled={form.processing}
                    onClick={() => {
                        form.transform((data) => ({ ...data, bands: parseJson(data.bands) }));
                        form.put(`/exams/scales/${scale.id}`, { preserveScroll: true });
                    }}
                >
                    {t.save || 'Save'}
                </button>
            </td>
        </tr>
    );
}

function parseJson(value) {
    try {
        return JSON.parse(value);
    } catch {
        return value;
    }
}
