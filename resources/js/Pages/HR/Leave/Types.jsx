import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Types({ types, codes }) {
    const form = useForm({
        code: codes[0] || 'annual',
        name: '',
        name_arabic: '',
        name_dhivehi: '',
        days_per_year: '0',
        carry_over_max: '0',
        paid: true,
        active: true,
    });

    return (
        <AppShell title="Leave types">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/hr/leave-types/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/hr/leave-types', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <select className="form-input" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)}>
                    {codes.map((code) => <option key={code} value={code}>{code}</option>)}
                </select>
                <input className="form-input" placeholder="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input className="form-input" placeholder="Days / year" value={form.data.days_per_year} onChange={(e) => form.setData('days_per_year', e.target.value)} />
                <input className="form-input" placeholder="Carry-over max" value={form.data.carry_over_max} onChange={(e) => form.setData('carry_over_max', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>Save leave type</button>
                {form.errors.name && <span className="text-xs text-red-600">{form.errors.name}</span>}
                {form.errors.code && <span className="text-xs text-red-600">{form.errors.code}</span>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Code</th>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Days</th>
                            <th className="px-3 py-2">Carry</th>
                            <th className="px-3 py-2">Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        {types.map((type) => (
                            <tr key={type.id} className="border-t">
                                <td className="px-3 py-2">{type.code}</td>
                                <td className="px-3 py-2">{type.name}</td>
                                <td className="px-3 py-2">{type.days_per_year}</td>
                                <td className="px-3 py-2">{type.carry_over_max}</td>
                                <td className="px-3 py-2">{type.paid ? 'yes' : 'no'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
