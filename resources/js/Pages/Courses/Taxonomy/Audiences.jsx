import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Audiences({ rows }) {
    const form = useForm({ name_en: '', name_dv: '', name_ar: '' });

    return (
        <AppShell title="Audiences">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/audiences/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/catalog/audiences', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <input className="form-input" placeholder="Name (EN)" value={form.data.name_en} onChange={(e) => form.setData('name_en', e.target.value)} />
                <input className="form-input" placeholder="Name (DV)" value={form.data.name_dv} onChange={(e) => form.setData('name_dv', e.target.value)} />
                <input className="form-input" placeholder="Name (AR)" value={form.data.name_ar} onChange={(e) => form.setData('name_ar', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Save audience</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Slug</th>
                            <th className="px-3 py-2">Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.name_en}</td>
                                <td className="px-3 py-2">{row.slug}</td>
                                <td className="px-3 py-2">{row.active ? 'yes' : 'no'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
