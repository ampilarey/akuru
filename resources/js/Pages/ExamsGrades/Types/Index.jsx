import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ types, codes }) {
    const form = useForm({
        name: '',
        name_arabic: '',
        name_dhivehi: '',
        code: codes[0] || 'quiz',
        default_weight: 0,
        counts_toward_final: true,
        active: true,
    });

    return (
        <AppShell title="Exam types">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/exams/types/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/exams/types', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Name (EN)</span>
                    <input className="form-input w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    {form.errors.name && <span className="text-xs text-red-600">{form.errors.name}</span>}
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">AR</span>
                    <input className="form-input w-full" dir="rtl" value={form.data.name_arabic} onChange={(e) => form.setData('name_arabic', e.target.value)} />
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">DV</span>
                    <input className="form-input w-full" dir="rtl" value={form.data.name_dhivehi} onChange={(e) => form.setData('name_dhivehi', e.target.value)} />
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Code</span>
                    <select className="form-input w-full" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)}>
                        {codes.map((code) => <option key={code} value={code}>{code}</option>)}
                    </select>
                    {form.errors.code && <span className="text-xs text-red-600">{form.errors.code}</span>}
                </label>
                <label className="text-sm">
                    <span className="mb-1 block text-gray-600">Default weight</span>
                    <input className="form-input w-full" type="number" min="0" max="100" value={form.data.default_weight} onChange={(e) => form.setData('default_weight', e.target.value)} />
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.counts_toward_final} onChange={(e) => form.setData('counts_toward_final', e.target.checked)} />
                    Counts toward final
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>Create type</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Code</th>
                            <th className="px-3 py-2">Weight</th>
                            <th className="px-3 py-2">Final</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {types.map((row) => <TypeRow key={row.id} type={row} codes={codes} />)}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function TypeRow({ type, codes }) {
    const form = useForm({
        name: type.name,
        name_arabic: type.name_arabic || '',
        name_dhivehi: type.name_dhivehi || '',
        code: type.code,
        default_weight: type.default_weight,
        counts_toward_final: type.counts_toward_final,
        active: type.active,
    });

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-2">
                <input className="form-input w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input className="form-input mt-1 w-full" dir="rtl" placeholder="AR" value={form.data.name_arabic} onChange={(e) => form.setData('name_arabic', e.target.value)} />
                <input className="form-input mt-1 w-full" dir="rtl" placeholder="DV" value={form.data.name_dhivehi} onChange={(e) => form.setData('name_dhivehi', e.target.value)} />
            </td>
            <td className="px-3 py-2">
                <select className="form-input w-full" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)}>
                    {codes.map((code) => <option key={code} value={code}>{code}</option>)}
                </select>
            </td>
            <td className="px-3 py-2">
                <input className="form-input w-full" type="number" min="0" max="100" value={form.data.default_weight} onChange={(e) => form.setData('default_weight', e.target.value)} />
            </td>
            <td className="px-3 py-2">
                <input type="checkbox" checked={form.data.counts_toward_final} onChange={(e) => form.setData('counts_toward_final', e.target.checked)} />
            </td>
            <td className="px-3 py-2">
                <button type="button" className="btn-secondary" disabled={form.processing} onClick={() => form.put(`/exams/types/${type.id}`, { preserveScroll: true })}>
                    Save
                </button>
            </td>
        </tr>
    );
}
