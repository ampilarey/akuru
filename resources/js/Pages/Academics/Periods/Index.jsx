import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ periods }) {
    const form = useForm({
        name: '',
        start_time: '08:00',
        end_time: '08:45',
        order: (periods[periods.length - 1]?.order || 0) + 1,
        is_break: false,
        is_active: true,
    });

    return (
        <AppShell title="Periods">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/academics/periods/export">
                    Export CSV
                </a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/academics/periods', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-6"
            >
                <input className="form-input" placeholder="Name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input className="form-input" type="time" value={form.data.start_time} onChange={(e) => form.setData('start_time', e.target.value)} />
                <input className="form-input" type="time" value={form.data.end_time} onChange={(e) => form.setData('end_time', e.target.value)} />
                <input className="form-input" type="number" min="1" value={form.data.order} onChange={(e) => form.setData('order', e.target.value)} />
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.is_break} onChange={(e) => form.setData('is_break', e.target.checked)} />
                    Break
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>Create period</button>
            </form>
            {form.errors.name && <p className="mb-2 text-sm text-red-600">{form.errors.name}</p>}
            {form.errors.order && <p className="mb-2 text-sm text-red-600">{form.errors.order}</p>}
            {form.errors.end_time && <p className="mb-2 text-sm text-red-600">{form.errors.end_time}</p>}
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Order</th>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Start</th>
                            <th className="px-3 py-2">End</th>
                            <th className="px-3 py-2">Break</th>
                            <th className="px-3 py-2">Active</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {periods.length === 0 && (
                            <tr>
                                <td className="px-3 py-4 text-gray-500" colSpan={7}>No periods yet. Create one here or seed PeriodSeeder.</td>
                            </tr>
                        )}
                        {periods.map((row) => (
                            <PeriodRow key={row.id} period={row} />
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function PeriodRow({ period }) {
    const form = useForm({
        name: period.name,
        start_time: period.start_time,
        end_time: period.end_time,
        order: period.order,
        is_break: period.is_break,
        is_active: period.is_active,
    });

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-2">
                <input className="form-input w-20" type="number" min="1" value={form.data.order} onChange={(e) => form.setData('order', e.target.value)} />
            </td>
            <td className="px-3 py-2">
                <input className="form-input w-full" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
            </td>
            <td className="px-3 py-2">
                <input className="form-input" type="time" value={form.data.start_time} onChange={(e) => form.setData('start_time', e.target.value)} />
            </td>
            <td className="px-3 py-2">
                <input className="form-input" type="time" value={form.data.end_time} onChange={(e) => form.setData('end_time', e.target.value)} />
            </td>
            <td className="px-3 py-2">
                <input type="checkbox" checked={form.data.is_break} onChange={(e) => form.setData('is_break', e.target.checked)} />
            </td>
            <td className="px-3 py-2">
                <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
            </td>
            <td className="px-3 py-2">
                <button
                    type="button"
                    className="btn-secondary"
                    disabled={form.processing}
                    onClick={() => form.put(`/academics/periods/${period.id}`, { preserveScroll: true })}
                >
                    Save
                </button>
            </td>
        </tr>
    );
}
