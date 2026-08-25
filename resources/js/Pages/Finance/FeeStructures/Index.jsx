import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ years, yearId, classes, feeItems, structures, appliesTo, statuses, frequencies }) {
    const firstItem = feeItems[0];
    const form = useForm({
        academic_year_id: yearId || '',
        name: '',
        applies_to: appliesTo[0] || 'class',
        class_ids: [],
        status: 'draft',
        items: firstItem ? [{
            fee_item_id: firstItem.id,
            amount: firstItem.default_amount,
            frequency: firstItem.frequency,
            due_day: 5,
            is_mandatory: true,
        }] : [],
    });

    const toggleClass = (id) => {
        const current = form.data.class_ids.map(String);
        const next = current.includes(String(id))
            ? form.data.class_ids.filter((value) => String(value) !== String(id))
            : [...form.data.class_ids, id];
        form.setData('class_ids', next);
    };

    const setItem = (index, key, value) => {
        const items = form.data.items.map((item, i) => (i === index ? { ...item, [key]: value } : item));
        form.setData('items', items);
    };

    return (
        <AppShell title="Fee structures">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-2">
                    {years.map((year) => (
                        <button
                            key={year.id}
                            type="button"
                            className={`rounded px-3 py-1 text-sm ${String(year.id) === String(yearId) ? 'bg-[#7C2D37] text-white' : 'border bg-white'}`}
                            onClick={() => router.get('/finance/fee-structures', { academic_year_id: year.id })}
                        >
                            {year.name}
                        </button>
                    ))}
                </div>
                <div className="flex gap-2">
                    <button
                        type="button"
                        className="btn-secondary"
                        onClick={() => router.post('/finance/fee-structures/copy-last-year', { academic_year_id: yearId })}
                    >
                        Copy from last year
                    </button>
                    <a className="btn-secondary" href={`/finance/fee-structures/export?academic_year_id=${yearId || ''}`}>Export CSV</a>
                </div>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/finance/fee-structures', { preserveScroll: true });
                }}
                className="mb-4 space-y-3 rounded-lg border bg-white p-4"
            >
                <div className="grid gap-3 md:grid-cols-4">
                    <input className="form-input" placeholder="Structure name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    <select className="form-input" value={form.data.applies_to} onChange={(e) => form.setData('applies_to', e.target.value)}>
                        {appliesTo.map((value) => <option key={value} value={value}>{value}</option>)}
                    </select>
                    <select className="form-input" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                        {statuses.map((value) => <option key={value} value={value}>{value}</option>)}
                    </select>
                    <button type="submit" className="btn-primary" disabled={form.processing}>Create structure</button>
                </div>
                {form.data.applies_to === 'class' && (
                    <div className="flex flex-wrap gap-3 text-sm">
                        {classes.map((row) => (
                            <label key={row.id} className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={form.data.class_ids.map(String).includes(String(row.id))}
                                    onChange={() => toggleClass(row.id)}
                                />
                                {row.label}
                            </label>
                        ))}
                    </div>
                )}
                {form.data.items.map((item, index) => (
                    <div key={index} className="grid gap-3 md:grid-cols-5">
                        <select className="form-input" value={item.fee_item_id} onChange={(e) => setItem(index, 'fee_item_id', e.target.value)}>
                            {feeItems.map((feeItem) => <option key={feeItem.id} value={feeItem.id}>{feeItem.name}</option>)}
                        </select>
                        <input className="form-input" placeholder="Amount" value={item.amount} onChange={(e) => setItem(index, 'amount', e.target.value)} />
                        <select className="form-input" value={item.frequency} onChange={(e) => setItem(index, 'frequency', e.target.value)}>
                            {frequencies.map((frequency) => <option key={frequency} value={frequency}>{frequency}</option>)}
                        </select>
                        <input className="form-input" placeholder="Due day" value={item.due_day ?? ''} onChange={(e) => setItem(index, 'due_day', e.target.value)} />
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={!!item.is_mandatory} onChange={(e) => setItem(index, 'is_mandatory', e.target.checked)} />
                            Mandatory
                        </label>
                    </div>
                ))}
                {form.errors.name && <span className="text-xs text-red-600">{form.errors.name}</span>}
                {form.errors.status && <span className="text-xs text-red-600">{form.errors.status}</span>}
                {form.errors.items && <span className="text-xs text-red-600">{form.errors.items}</span>}
            </form>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Applies</th>
                            <th className="px-3 py-2">Classes</th>
                            <th className="px-3 py-2">Items</th>
                            <th className="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {structures.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No fee structures yet.</td></tr>
                        )}
                        {structures.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                <td className="px-3 py-2">{row.applies_to}</td>
                                <td className="px-3 py-2">{(row.class_ids || []).join(', ') || 'all'}</td>
                                <td className="px-3 py-2">{row.items.length}</td>
                                <td className="px-3 py-2">{row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
