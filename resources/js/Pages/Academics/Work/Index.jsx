import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

function UploadForm({ matches, q, onSearch }) {
    const form = useForm({ student_id: '', photo: null, title: '', note: '', done_on: '' });
    const chosen = matches.find((m) => String(m.id) === String(form.data.student_id));

    return (
        <form
            className="mb-6 grid gap-3 rounded-lg border bg-white p-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/academics/work', { forceFormData: true, onSuccess: () => form.reset() });
            }}
        >
            <p className="text-sm font-semibold">Photograph a piece of work</p>

            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">Whose work is it?</span>
                <input
                    className="form-input w-full"
                    placeholder="Search by name or student number"
                    value={q}
                    onChange={(e) => onSearch(e.target.value)}
                />
            </label>

            {matches.length > 0 && (
                <ul className="grid gap-1">
                    {matches.map((child) => (
                        <li key={child.id}>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="radio"
                                    name="student_id"
                                    value={child.id}
                                    checked={String(form.data.student_id) === String(child.id)}
                                    onChange={() => form.setData('student_id', child.id)}
                                />
                                <span>
                                    {child.name}
                                    <span className="ms-2 text-xs text-gray-500">
                                        {[child.student_number, child.current_class].filter(Boolean).join(' · ')}
                                    </span>
                                    {child.indistinguishable && (
                                        <span className="ms-2 rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-800">
                                            same name as another pupil — check the number
                                        </span>
                                    )}
                                </span>
                            </label>
                        </li>
                    ))}
                </ul>
            )}

            {form.errors.student_id && <p className="text-sm text-red-600">{form.errors.student_id}</p>}

            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">Photo</span>
                <input type="file" accept="image/*" capture="environment"
                    onChange={(e) => form.setData('photo', e.target.files[0])} />
                {form.errors.photo && <span className="mt-1 block text-xs text-red-600">{form.errors.photo}</span>}
            </label>

            <div className="grid gap-3 sm:grid-cols-3">
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Title (optional)</span>
                    <input className="form-input w-full" value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Note (optional)</span>
                    <input className="form-input w-full" value={form.data.note}
                        onChange={(e) => form.setData('note', e.target.value)} />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Done on</span>
                    <input type="date" className="form-input w-full" value={form.data.done_on}
                        onChange={(e) => form.setData('done_on', e.target.value)} />
                </label>
            </div>

            <p className="text-xs text-gray-600">
                {chosen
                    ? `This will go to ${chosen.name}’s family.`
                    : 'Nothing is sent to a family until you choose a pupil.'}
            </p>

            <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                Save
            </button>
        </form>
    );
}

function ReassignRow({ work, matches, q, onSearch }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <button className="text-xs text-[#7C2D37] underline" onClick={() => setOpen(!open)}>
                Wrong pupil?
            </button>
            {open && (
                <div className="mt-2 rounded border bg-[#FBF7F2] p-2">
                    <input
                        className="form-input w-full text-xs"
                        placeholder="Search for the right pupil"
                        value={q}
                        onChange={(e) => onSearch(e.target.value)}
                    />
                    <ul className="mt-2 grid gap-1">
                        {matches.map((child) => (
                            <li key={child.id}>
                                <button
                                    className="text-xs text-[#7C2D37] underline"
                                    onClick={() =>
                                        router.post(`/academics/work/${work.id}/reassign`, { student_id: child.id }, { preserveScroll: true })
                                    }
                                >
                                    Move to {child.name} {child.student_number ? `(${child.student_number})` : ''}
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </>
    );
}

export default function Index({ q = '', matches = [], work = [] }) {
    const [query, setQuery] = useState(q);

    const search = (value) => {
        setQuery(value);
        router.get('/academics/work', { q: value }, { preserveState: true, replace: true });
    };

    return (
        <AppShell title="Student work">
            <UploadForm matches={matches} q={query} onSearch={search} />

            <h2 className="mb-2 text-sm font-semibold">Photographed ({work.length})</h2>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {work.map((item) => (
                    <div key={item.id} className={`rounded-lg border bg-white p-3 ${item.hidden ? 'opacity-60' : ''}`}>
                        <img
                            src={`/academics/work/${item.id}/photo`}
                            alt={item.title || `Work by ${item.student}`}
                            className="mb-2 w-full rounded border object-cover"
                            style={{ aspectRatio: '4 / 3', maxWidth: '100%' }}
                        />
                        <p className="text-sm font-medium">{item.student}</p>
                        {item.student_number && <p className="text-xs text-gray-500">{item.student_number}</p>}
                        {item.title && <p className="text-sm">{item.title}</p>}
                        {item.note && <p className="text-xs text-gray-600">{item.note}</p>}
                        <p className="mt-1 text-xs text-gray-500">
                            {item.done_on} · {item.uploaded_by}
                        </p>
                        {item.times_moved > 0 && (
                            <p className="mt-1 rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-800">
                                moved {item.times_moved} time{item.times_moved === 1 ? '' : 's'}
                            </p>
                        )}
                        <div className="mt-2 flex flex-wrap items-center gap-3">
                            {item.hidden ? (
                                <button
                                    className="text-xs text-[#7C2D37] underline"
                                    onClick={() => router.post(`/academics/work/${item.id}/restore`, {}, { preserveScroll: true })}
                                >
                                    Show families again
                                </button>
                            ) : (
                                <button
                                    className="text-xs text-[#7C2D37] underline"
                                    onClick={() => router.post(`/academics/work/${item.id}/hide`, {}, { preserveScroll: true })}
                                >
                                    Hide from families
                                </button>
                            )}
                            <ReassignRow work={item} matches={matches} q={query} onSearch={search} />
                        </div>
                    </div>
                ))}
            </div>
            {work.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">Nothing photographed yet.</p>
            )}

            <p className="mt-4 text-xs text-gray-500">
                Work reaching the wrong parent is the failure this screen is built around. Moving a
                photo takes effect at once — the first family stops seeing it immediately — and every
                move is recorded, so the school can say which family saw what and for how long.
            </p>
        </AppShell>
    );
}
