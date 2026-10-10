import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

function LogForm({ item = null, onDone, t }) {
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
                <span className="mb-1 block text-gray-600">{t.found_what || 'What is it?'}</span>
                <input className="form-input w-full" value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)} />
                {form.errors.title && <span className="mt-1 block text-xs text-red-600">{form.errors.title}</span>}
            </label>
            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">{t.found_description || 'Description'}</span>
                <textarea className="form-input w-full" rows={2} value={form.data.description}
                    onChange={(e) => form.setData('description', e.target.value)} />
                {form.errors.description && <span className="mt-1 block text-xs text-red-600">{form.errors.description}</span>}
            </label>
            <div className="grid gap-3 sm:grid-cols-3">
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.found_where || 'Found where'}</span>
                    <input className="form-input w-full" value={form.data.location}
                        onChange={(e) => form.setData('location', e.target.value)} />
                    {form.errors.location && <span className="mt-1 block text-xs text-red-600">{form.errors.location}</span>}
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.found_held_at || 'Held at'}</span>
                    <input className="form-input w-full" placeholder={t.found_held_placeholder || 'Front office'} value={form.data.held_at}
                        onChange={(e) => form.setData('held_at', e.target.value)} />
                    {form.errors.held_at && <span className="mt-1 block text-xs text-red-600">{form.errors.held_at}</span>}
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.found_date || 'Date found'}</span>
                    <input type="date" className="form-input w-full" value={form.data.found_at}
                        onChange={(e) => form.setData('found_at', e.target.value)} />
                    <span className="mt-1 block text-xs text-gray-500">{t.found_defaults_today || 'Defaults to today.'}</span>
                    {form.errors.found_at && <span className="mt-1 block text-xs text-red-600">{form.errors.found_at}</span>}
                </label>
            </div>
            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">{t.found_photo || 'Photo'}</span>
                <input type="file" accept="image/*" className="w-full text-sm"
                    onChange={(e) => form.setData('photo', e.target.files?.[0] ?? null)} />
                {form.errors.photo && <span className="mt-1 block text-xs text-red-600">{form.errors.photo}</span>}
            </label>
            <div className="flex gap-2">
                <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                    {item ? (t.found_save_changes || 'Save changes') : (t.found_log || 'Log item')}
                </button>
                {item && <button type="button" className="btn-secondary" onClick={onDone}>{t.cancel || 'Cancel'}</button>}
            </div>
        </form>
    );
}

function ReturnForm({ item, t }) {
    const form = useForm({ returned_to: '' });
    // Returned already — by someone else, on another screen — is refused
    // with the date it went; it used to be refused in silence.
    const refused = form.errors.status || form.errors.returned_to;

    return (
        <form
            className="mt-2 flex flex-wrap items-center gap-2"
            onSubmit={(e) => { e.preventDefault(); form.post(`/academics/found-items/${item.id}/return`, { preserveScroll: true }); }}
        >
            <input className="form-input w-48 text-sm" aria-label={t.found_returned_to || 'Returned to (optional)'} placeholder={t.found_returned_to || 'Returned to (optional)'}
                value={form.data.returned_to} onChange={(e) => form.setData('returned_to', e.target.value)} />
            <button type="submit" className="btn-secondary text-xs" disabled={form.processing}>{t.found_mark_returned || 'Mark returned'}</button>
            {refused && <span className="block w-full text-xs text-red-600">{refused}</span>}
        </form>
    );
}

/**
 * Lost and found, the staff's side. Every word is the `academics` book's
 * (slice OA4, STATUS §5qi).
 */
export default function Index({ items = [], filters = {}, t = {} }) {
    const [q, setQ] = useState(filters.q || '');
    const [status, setStatus] = useState(filters.status || '');
    const [editing, setEditing] = useState(null);

    const apply = (next) => router.get('/academics/found-items', next, { preserveState: true, replace: true });

    return (
        <AppShell title={t.found_title || 'Lost and found'}>
            {/* No flash banner here — AppShell already renders one, and a
                second copy showed the message twice. Caught by walking it. */}
            <p className="mb-4 text-sm text-gray-600">
                {t.found_intro || 'Log what is handed in. Families see everything still on the shelf, so a good description saves a phone call.'}
            </p>

            {editing ? (
                <LogForm item={editing} onDone={() => setEditing(null)} t={t} />
            ) : (
                <LogForm onDone={() => setEditing(null)} t={t} />
            )}

            <div className="mb-3 flex flex-wrap items-center gap-2">
                <input type="search" className="form-input w-64 text-sm" aria-label={t.found_search || 'Search item, description or place'} placeholder={t.found_search || 'Search item, description or place'}
                    value={q} onChange={(e) => setQ(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && apply({ q, status })} />
                <select className="form-input text-sm" aria-label={t.status || 'Status'} value={status}
                    onChange={(e) => { setStatus(e.target.value); apply({ q, status: e.target.value }); }}>
                    <option value="">{t.all || 'All'}</option>
                    <option value="listed">{t.found_status_listed || 'Still here'}</option>
                    <option value="returned">{t.found_status_returned || 'Returned'}</option>
                </select>
                <a href={`/academics/found-items/export?q=${encodeURIComponent(q)}&status=${status}`}
                    className="rounded border px-3 py-1 text-sm hover:bg-gray-50">{t.export_csv || 'Export CSV'}</a>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className="px-3 py-2 text-start">{t.found_col_item || 'Item'}</th>
                            <th className="px-3 py-2 text-start">{t.found_col_found || 'Found'}</th>
                            <th className="px-3 py-2 text-start">{t.found_held_at || 'Held at'}</th>
                            <th className="px-3 py-2 text-start">{t.status || 'Status'}</th>
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
                                            className="text-xs text-[#7C2D37] underline">{t.found_photo || 'Photo'}</a>
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
                                            {item.returned_to
                                                ? (t.found_returned_on_to || 'Returned :date to :name').replace(':date', item.returned_at?.slice(0, 10) ?? '').replace(':name', item.returned_to)
                                                : (t.found_returned_on || 'Returned :date').replace(':date', item.returned_at?.slice(0, 10) ?? '')}
                                        </span>
                                    ) : (
                                        <>
                                            <button className="text-xs text-[#7C2D37] underline" onClick={() => setEditing(item)}>{t.edit || 'Edit'}</button>
                                            <ReturnForm item={item} t={t} />
                                        </>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {items.length === 0 && (
                    <p className="px-4 py-6 text-center text-sm text-gray-500">{t.found_none || 'Nothing logged for this school year.'}</p>
                )}
            </div>
        </AppShell>
    );
}
