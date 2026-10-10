import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

function Field({ label, error, children }) {
    return (
        <label className="block text-sm">
            <span className="mb-1 block text-gray-600">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}

export default function Index({ rooms, types, t = {} }) {
    const form = useForm({
        name: '',
        name_arabic: '',
        name_dhivehi: '',
        building: '',
        capacity: '',
        type: types[0] || 'classroom',
        bookable: true,
        active: true,
    });
    // In the page's language (BACKLOG C21, slice OA2); a room's type is a
    // code, named here.
    const typeName = (type) => t[`room_type_${type}`] || type;

    return (
        <AppShell title={t.rooms_title || 'Rooms'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/academics/rooms/export">
                    {t.export_csv || 'Export CSV'}
                </a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/academics/rooms', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <Field label={t.name_en || 'Name (EN)'} error={form.errors.name}>
                    <input className="form-input w-full" aria-label={t.name_en || 'Name (EN)'} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </Field>
                <Field label={t.name_ar || 'Name (AR)'}>
                    <input className="form-input w-full" dir="rtl" aria-label={t.name_ar || 'Name (AR)'} value={form.data.name_arabic} onChange={(e) => form.setData('name_arabic', e.target.value)} />
                </Field>
                <Field label={t.name_dv || 'Name (DV)'}>
                    <input className="form-input w-full" dir="rtl" aria-label={t.name_dv || 'Name (DV)'} value={form.data.name_dhivehi} onChange={(e) => form.setData('name_dhivehi', e.target.value)} />
                </Field>
                <Field label={t.rooms_building || 'Building'}>
                    <input className="form-input w-full" aria-label={t.rooms_building || 'Building'} value={form.data.building} onChange={(e) => form.setData('building', e.target.value)} />
                </Field>
                <Field label={t.capacity || 'Capacity'} error={form.errors.capacity}>
                    <input className="form-input w-full" type="number" min="1" aria-label={t.capacity || 'Capacity'} value={form.data.capacity} onChange={(e) => form.setData('capacity', e.target.value)} />
                </Field>
                <Field label={t.type || 'Type'} error={form.errors.type}>
                    <select className="form-input w-full" aria-label={t.type || 'Type'} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                        {types.map((type) => (
                            <option key={type} value={type}>{typeName(type)}</option>
                        ))}
                    </select>
                </Field>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.bookable} onChange={(e) => form.setData('bookable', e.target.checked)} />
                    {t.rooms_bookable || 'Bookable'}
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.rooms_create || 'Create room'}</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_name || 'Name'}</th>
                            <th className="px-3 py-2">{t.rooms_col_names || 'Arabic and Dhivehi names'}</th>
                            <th className="px-3 py-2">{t.rooms_building || 'Building'}</th>
                            <th className="px-3 py-2">{t.col_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.col_capacity || 'Capacity'}</th>
                            <th className="px-3 py-2">{t.rooms_col_use || 'Use'}</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {rooms.length === 0 && (
                            <tr>
                                <td className="px-3 py-4 text-gray-500" colSpan={7}>{t.rooms_none || 'No rooms yet.'}</td>
                            </tr>
                        )}
                        {rooms.map((row) => (
                            <RoomRow key={row.id} room={row} types={types} typeName={typeName} t={t} />
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function RoomRow({ room, types, typeName, t }) {
    const form = useForm({
        name: room.name,
        name_arabic: room.name_arabic || '',
        name_dhivehi: room.name_dhivehi || '',
        building: room.building || '',
        capacity: room.capacity ?? '',
        type: room.type,
        bookable: room.bookable,
        active: room.active,
    });
    // Each box says which room it belongs to.
    const label = (column) => `${column}: ${room.name}`;

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-2">
                <input className="form-input w-full" aria-label={label(t.name_en || 'Name (EN)')} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                {form.errors.name && <span className="text-xs text-red-600">{form.errors.name}</span>}
            </td>
            <td className="px-3 py-2">
                <input className="form-input mb-1 w-full" dir="rtl" placeholder={t.name_ar || 'Name (AR)'} aria-label={label(t.name_ar || 'Name (AR)')} value={form.data.name_arabic} onChange={(e) => form.setData('name_arabic', e.target.value)} />
                <input className="form-input w-full" dir="rtl" placeholder={t.name_dv || 'Name (DV)'} aria-label={label(t.name_dv || 'Name (DV)')} value={form.data.name_dhivehi} onChange={(e) => form.setData('name_dhivehi', e.target.value)} />
            </td>
            <td className="px-3 py-2">
                <input className="form-input w-full" aria-label={label(t.rooms_building || 'Building')} value={form.data.building} onChange={(e) => form.setData('building', e.target.value)} />
            </td>
            <td className="px-3 py-2">
                <select className="form-input w-full" aria-label={label(t.col_type || 'Type')} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                    {types.map((type) => (
                        <option key={type} value={type}>{typeName(type)}</option>
                    ))}
                </select>
            </td>
            <td className="px-3 py-2">
                <input className="form-input w-full" type="number" min="1" aria-label={label(t.col_capacity || 'Capacity')} value={form.data.capacity} onChange={(e) => form.setData('capacity', e.target.value)} />
                {form.errors.capacity && <span className="text-xs text-red-600">{form.errors.capacity}</span>}
            </td>
            <td className="px-3 py-2 text-xs">
                <label className="flex items-center gap-1">
                    <input type="checkbox" checked={form.data.bookable} onChange={(e) => form.setData('bookable', e.target.checked)} />
                    {t.rooms_bookable_flag || 'bookable'}
                </label>
                <label className="mt-1 flex items-center gap-1">
                    <input type="checkbox" checked={form.data.active} onChange={(e) => form.setData('active', e.target.checked)} />
                    {t.rooms_active_flag || 'active'}
                </label>
            </td>
            <td className="px-3 py-2">
                <button
                    type="button"
                    className="btn-secondary"
                    disabled={form.processing}
                    onClick={() => form.put(`/academics/rooms/${room.id}`, { preserveScroll: true })}
                >
                    {t.save || 'Save'}
                </button>
            </td>
        </tr>
    );
}
