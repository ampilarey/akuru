import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Index({ periods, t = {} }) {
    const form = useForm({
        name: '',
        start_time: '08:00',
        end_time: '08:45',
        order: (periods[periods.length - 1]?.order || 0) + 1,
        is_break: false,
        is_active: true,
    });

    // In the page's language (BACKLOG C21, slice OA2).
    return (
        <AppShell title={t.periods_title || 'Periods'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/academics/periods/export">
                    {t.export_csv || 'Export CSV'}
                </a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/academics/periods', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-6"
            >
                <input className="form-input" placeholder={t.name || 'Name'} aria-label={t.name || 'Name'} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input className="form-input" type="time" aria-label={t.start || 'Start'} value={form.data.start_time} onChange={(e) => form.setData('start_time', e.target.value)} />
                <input className="form-input" type="time" aria-label={t.end || 'End'} value={form.data.end_time} onChange={(e) => form.setData('end_time', e.target.value)} />
                <input className="form-input" type="number" min="1" aria-label={t.col_order || 'Order'} value={form.data.order} onChange={(e) => form.setData('order', e.target.value)} />
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.is_break} onChange={(e) => form.setData('is_break', e.target.checked)} />
                    {t.periods_break || 'Break'}
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.periods_create || 'Create period'}</button>
            </form>
            {form.errors.name && <p className="mb-2 text-sm text-red-600">{form.errors.name}</p>}
            {form.errors.start_time && <p className="mb-2 text-sm text-red-600">{form.errors.start_time}</p>}
            {form.errors.order && <p className="mb-2 text-sm text-red-600">{form.errors.order}</p>}
            {form.errors.end_time && <p className="mb-2 text-sm text-red-600">{form.errors.end_time}</p>}
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_order || 'Order'}</th>
                            <th className="px-3 py-2">{t.col_name || 'Name'}</th>
                            <th className="px-3 py-2">{t.col_start || 'Start'}</th>
                            <th className="px-3 py-2">{t.col_end || 'End'}</th>
                            <th className="px-3 py-2">{t.periods_break || 'Break'}</th>
                            <th className="px-3 py-2">{t.col_active || 'Active'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {periods.length === 0 && (
                            <tr>
                                <td className="px-3 py-4 text-gray-500" colSpan={7}>{t.periods_none || 'No periods yet. Create the first one above.'}</td>
                            </tr>
                        )}
                        {periods.map((row) => (
                            <PeriodRow key={row.id} period={row} t={t} />
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function PeriodRow({ period, t }) {
    const form = useForm({
        name: period.name,
        start_time: period.start_time,
        end_time: period.end_time,
        order: period.order,
        is_break: period.is_break,
        is_active: period.is_active,
    });
    // Each box says which period it belongs to.
    const label = (column) => `${column}: ${period.name}`;

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-2">
                <input className="form-input w-20" type="number" min="1" aria-label={label(t.col_order || 'Order')} value={form.data.order} onChange={(e) => form.setData('order', e.target.value)} />
            </td>
            <td className="px-3 py-2">
                <input className="form-input w-full" aria-label={label(t.col_name || 'Name')} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                {form.errors.name && <span className="text-xs text-red-600">{form.errors.name}</span>}
            </td>
            <td className="px-3 py-2">
                <input className="form-input" type="time" aria-label={label(t.col_start || 'Start')} value={form.data.start_time} onChange={(e) => form.setData('start_time', e.target.value)} />
            </td>
            <td className="px-3 py-2">
                <input className="form-input" type="time" aria-label={label(t.col_end || 'End')} value={form.data.end_time} onChange={(e) => form.setData('end_time', e.target.value)} />
                {form.errors.end_time && <span className="block text-xs text-red-600">{form.errors.end_time}</span>}
            </td>
            <td className="px-3 py-2">
                <input type="checkbox" aria-label={label(t.periods_break || 'Break')} checked={form.data.is_break} onChange={(e) => form.setData('is_break', e.target.checked)} />
            </td>
            <td className="px-3 py-2">
                <input type="checkbox" aria-label={label(t.col_active || 'Active')} checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
            </td>
            <td className="px-3 py-2">
                <button
                    type="button"
                    className="btn-secondary"
                    disabled={form.processing}
                    onClick={() => form.put(`/academics/periods/${period.id}`, { preserveScroll: true })}
                >
                    {t.save || 'Save'}
                </button>
                {form.errors.order && <span className="block text-xs text-red-600">{form.errors.order}</span>}
            </td>
        </tr>
    );
}
