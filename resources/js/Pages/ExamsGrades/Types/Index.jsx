import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

/**
 * Exam types. Every word is the `exams` book's, and a type's code is named
 * rather than printed (slice EG1, STATUS §5qj). The type's own name is the
 * school's, in the three languages it is written in here.
 */
export default function Index({ types, codes, t = {} }) {
    const form = useForm({
        name: '',
        name_arabic: '',
        name_dhivehi: '',
        code: codes[0] || 'quiz',
        default_weight: 0,
        counts_toward_final: true,
        active: true,
    });
    const codeName = (code) => t[`exam_type_code_${code}`] || code;

    return (
        <AppShell title={t.types_title || 'Exam types'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/exams/types/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/exams/types', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.name_en || 'Name (EN)'}</span>
                    <input className="form-input w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    {form.errors.name && <span className="text-xs text-red-600">{form.errors.name}</span>}
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.name_ar || 'Name (AR)'}</span>
                    <input className="form-input w-full" dir="rtl" value={form.data.name_arabic} onChange={(e) => form.setData('name_arabic', e.target.value)} />
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.name_dv || 'Name (DV)'}</span>
                    <input className="form-input w-full" dir="rtl" value={form.data.name_dhivehi} onChange={(e) => form.setData('name_dhivehi', e.target.value)} />
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.types_code || 'Code'}</span>
                    <select className="form-input w-full" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)}>
                        {codes.map((code) => <option key={code} value={code}>{codeName(code)}</option>)}
                    </select>
                    {form.errors.code && <span className="text-xs text-red-600">{form.errors.code}</span>}
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">{t.types_default_weight || 'Default weight'}</span>
                    <input className="form-input w-full" type="number" min="0" max="100" value={form.data.default_weight} onChange={(e) => form.setData('default_weight', e.target.value)} />
                    {form.errors.default_weight && <span className="text-xs text-red-600">{form.errors.default_weight}</span>}
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.counts_toward_final} onChange={(e) => form.setData('counts_toward_final', e.target.checked)} />
                    {t.types_counts || 'Counts toward final'}
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.types_create || 'Create type'}</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.name || 'Name'}</th>
                            <th className="px-3 py-2">{t.types_code || 'Code'}</th>
                            <th className="px-3 py-2">{t.types_col_weight || 'Weight'}</th>
                            <th className="px-3 py-2">{t.types_col_final || 'Final'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {types.map((row) => <TypeRow key={row.id} type={row} codes={codes} codeName={codeName} t={t} />)}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function TypeRow({ type, codes, codeName, t }) {
    const form = useForm({
        name: type.name,
        name_arabic: type.name_arabic || '',
        name_dhivehi: type.name_dhivehi || '',
        code: type.code,
        default_weight: type.default_weight,
        counts_toward_final: type.counts_toward_final,
        active: type.active,
    });
    const field = (phrase) => phrase.replace(':name', type.name);
    const refused = form.errors.name || form.errors.code || form.errors.default_weight;

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-2">
                <input className="form-input w-full" aria-label={field(t.types_name_en_of || 'Name (EN): :name')} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input className="form-input mt-1 w-full" dir="rtl" aria-label={field(t.types_name_ar_of || 'Name (AR): :name')} placeholder={t.name_ar || 'Name (AR)'} value={form.data.name_arabic} onChange={(e) => form.setData('name_arabic', e.target.value)} />
                <input className="form-input mt-1 w-full" dir="rtl" aria-label={field(t.types_name_dv_of || 'Name (DV): :name')} placeholder={t.name_dv || 'Name (DV)'} value={form.data.name_dhivehi} onChange={(e) => form.setData('name_dhivehi', e.target.value)} />
                {refused && <span className="mt-1 block text-xs text-red-600">{refused}</span>}
            </td>
            <td className="px-3 py-2">
                <select className="form-input w-full" aria-label={field(t.types_code_of || 'Code: :name')} value={form.data.code} onChange={(e) => form.setData('code', e.target.value)}>
                    {codes.map((code) => <option key={code} value={code}>{codeName(code)}</option>)}
                </select>
            </td>
            <td className="px-3 py-2">
                <input className="form-input w-full" type="number" min="0" max="100" aria-label={field(t.types_weight_of || 'Weight: :name')} value={form.data.default_weight} onChange={(e) => form.setData('default_weight', e.target.value)} />
            </td>
            <td className="px-3 py-2">
                <input type="checkbox" aria-label={field(t.types_final_of || 'Counts toward final: :name')} checked={form.data.counts_toward_final} onChange={(e) => form.setData('counts_toward_final', e.target.checked)} />
            </td>
            <td className="px-3 py-2">
                <button type="button" className="btn-secondary" disabled={form.processing} onClick={() => form.put(`/exams/types/${type.id}`, { preserveScroll: true })}>
                    {t.save || 'Save'}
                </button>
            </td>
        </tr>
    );
}
