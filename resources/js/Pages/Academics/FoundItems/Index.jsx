import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

function LogForm({ item = null, onDone }) {
    const form = useForm({
        title: item?.title || '',
        description: item?.description || '',
        location: item?.location || '',
        held_at: item?.held_at || '',
        found_at: item?.found_at || '',
        photo: null,
    });

    const submit = (e) => {
        e.preventDefault();
        const url = item ? `/academics/found-items/${item.id}` : '/academics/found-items';
        form.post(url, { forceFormData: true, preserveScroll: true, onSuccess: () => { form.reset(); onDone?.(); } });
    };

    return (
        <form onSubmit={submit} className="mb-6 grid gap-3 rounded-lg border bg-white p-4">
            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">What is it?</span>
                <input className="form-input w-full" value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)} />
                {form.errors.title && <span className="mt-1 block text-xs text-red-600">{form.errors.title}</span>}
            </label>
            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">Description</span>
                <textarea className="form-input w-full" rows={2} value={form.data.description}
                    onChange={(e) => form.setData('description', e.target.value)} />
                {form.errors.description && <span className="mt-1 block text-xs text-red-600">{form.errors.description}</span>}
            </label>
            <div className="grid gap-3 sm:grid-cols-3">
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Found where</span>
                    <input className="form-input w-full" value={form.data.location}
                        onChange={(e) => form.setData('location', e.target.value)} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Held at</span>
                    <input className="form-input w-full" placeholder="Front office" value={form.data.held_at}
                        onChange={(e) => form.setData('held_at', e.target.value)} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Date found</span>
                    <input type="date" className="form-input w-full" value={form.data.found_at}
                        onChange={(e) => form.setData('found_at', e.target.value)} />
                    <span className="mt-1 block text-xs text-gray-500">Defaults to today.</span>
                </label>
            </div>
            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">Photo</span>
                <input type="file" accept="image/*" className="w-full text-sm"
                    onChange={(e) => form.setData('photo', e.target.files?.[0] ?? null)} />
                {form.errors.photo && <span className="mt-1 block text-xs text-red-600">{form.errors.photo}</span>}
            </label>
            <div className="flex gap-2">
                <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                    {item ? 'Save changes' : 'Log item'}
                </button>
                {item && <button type="button" className="btn-secondary" onClick={onDone}>Cancel</button>}
            </div>
        </form>
    );
}

function ReturnForm({ item }) {
    const form = useForm({ returned_to: '' });

    return (
        <form
            className="mt-2 flex flex-wrap items-center gap-2"
            onSubmit={(e) => { e.preventDefault(); form.post(`/academics/found-items/${item.id}/return`, { preserveScroll: true }); }}
        >
            <input className="form-input w-48 text-sm" placeholder="Returned to (optional)"
                value={form.data.returned_to} onChange={(e) => form.setData('returned_to', e.target.value)} />
            <button type="submit" className="btn-secondary text-xs" disabled={form.processing}>Mark returned</button>
        </form>
    );
}

export default function Index({ items = [], filters = {} }) {
    const [q, setQ] = useState(filters.q || '');
    const [status, setStatus] = useState(filters.status || '');
    const [editing, setEditing] = useState(null);

    const apply = (next) => router.get('/academics/found-items', next, { preserveState: true, replace: true });

    return (
        <AppShell title="Lost and found">
            {/* No flash banner here — AppShell already renders one, and a
                second copy showed the message twice. Caught by walking it. */}
            <p className="mb-4 text-sm text-gray-600">
                Log what is handed in. Families see everything still on the shelf, so a good
                description saves a phone call.
            </p>

            {editing ? (
                <LogForm item={editing} onDone={() => setEditing(null)} />
            ) : (
                <LogForm onDone={() => setEditing(null)} />
            )}

            <div className="mb-3 flex flex-wrap items-center gap-2">
                <input type="search" className="form-input w-64 text-sm" placeholder="Search item, description or place"
                    value={q} onChange={(e) => setQ(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && apply({ q, status })} />
                <select className="form-input text-sm" value={status}
                    onChange={(e) => { setStatus(e.target.value); apply({ q, status: e.target.value }); }}>
                    <option value="">All</option>
                    <option value="listed">Still here</option>
                    <option value="returned">Returned</option>
                </select>
                <a href={`/academics/found-items/export?q=${encodeURIComponent(q)}&status=${status}`}
                    className="rounded border px-3 py-1 text-sm hover:bg-gray-50">CSV</a>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className="px-3 py-2 text-start">Item</th>
                            <th className="px-3 py-2 text-start">Found</th>
                            <th className="px-3 py-2 text-start">Held at</th>
                            <th className="px-3 py-2 text-start">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr key={item.id} className="border-t align-top">
                                <td className="px-3 py-2">
                                    <p className="font-medium">{item.title}</p>
                                    {item.description && <p className="text-xs text-gray-600">{item.description}</p>}
                                    {item.has_photo && (
                                        <a href={`/academics/found-items/${item.id}/photo`} target="_blank" rel="noreferrer"
                                            className="text-xs text-[#7C2D37] underline">Photo</a>
                                    )}
                                </td>
                                <td className="px-3 py-2">
                                    {item.found_at}
                                    {item.location && <span className="block text-xs text-gray-500">{item.location}</span>}
                                </td>
                                <td className="px-3 py-2">{item.held_at || '—'}</td>
                                <td className="px-3 py-2">
                                    {item.status === 'returned' ? (
                                        <span className="text-xs text-gray-600">
                                            Returned {item.returned_at?.slice(0, 10)}
                                            {item.returned_to ? ` to ${item.returned_to}` : ''}
                                        </span>
                                    ) : (
                                        <>
                                            <button className="text-xs text-[#7C2D37] underline" onClick={() => setEditing(item)}>Edit</button>
                                            <ReturnForm item={item} />
                                        </>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {items.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">Nothing logged for this school year.</p>
                )}
            </div>
        </AppShell>
    );
}
