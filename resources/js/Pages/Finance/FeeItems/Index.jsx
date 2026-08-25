import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ items, types, frequencies }) {
    const form = useForm({
        name: '',
        name_arabic: '',
        name_dhivehi: '',
        default_amount: '',
        type: types[0] || 'tuition',
        frequency: frequencies[0] || 'one_time',
        is_mandatory: true,
        is_active: true,
    });

    return (
        <AppShell title="Fee items">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/finance/fee-items/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/finance/fee-items', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <input className="form-input" placeholder="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input className="form-input" placeholder="AR" dir="rtl" value={form.data.name_arabic} onChange={(e) => form.setData('name_arabic', e.target.value)} />
                <input className="form-input" placeholder="DV" dir="rtl" value={form.data.name_dhivehi} onChange={(e) => form.setData('name_dhivehi', e.target.value)} />
                <input className="form-input" placeholder="Amount" value={form.data.default_amount} onChange={(e) => form.setData('default_amount', e.target.value)} />
                <select className="form-input" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                    {types.map((type) => <option key={type} value={type}>{type}</option>)}
                </select>
                <select className="form-input" value={form.data.frequency} onChange={(e) => form.setData('frequency', e.target.value)}>
                    {frequencies.map((frequency) => <option key={frequency} value={frequency}>{frequency}</option>)}
                </select>
                <button type="submit" className="btn-primary" disabled={form.processing}>Create fee item</button>
                {form.errors.name && <span className="text-xs text-red-600">{form.errors.name}</span>}
                {form.errors.default_amount && <span className="text-xs text-red-600">{form.errors.default_amount}</span>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Amount</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Frequency</th>
                            <th className="px-3 py-2">Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No fee items yet.</td></tr>
                        )}
                        {items.map((item) => (
                            <tr key={item.id} className="border-t">
                                <td className="px-3 py-2">{item.name}</td>
                                <td className="px-3 py-2">{item.default_amount} {item.currency}</td>
                                <td className="px-3 py-2">{item.type}</td>
                                <td className="px-3 py-2">{item.frequency}</td>
                                <td className="px-3 py-2">{item.is_active ? 'yes' : 'no'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
