import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

function NoteForm({ studentId, categories }) {
    const form = useForm({ student_id: studentId, category: 'medical', summary: '', body: '', review_on: '' });

    return (
        <form
            className="mb-6 grid gap-3 rounded-lg border bg-white p-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((data) => ({ ...data, student_id: studentId }));
                form.post('/people/sensitive', { preserveScroll: true, onSuccess: () => form.reset() });
            }}
        >
            <p className="text-sm font-semibold">Add a note</p>
            <div className="grid gap-3 sm:grid-cols-2">
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Category</span>
                    <select className="form-input w-full" value={form.data.category}
                        onChange={(e) => form.setData('category', e.target.value)}>
                        {categories.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                    </select>
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">Look at this again on (optional)</span>
                    <input type="date" className="form-input w-full" value={form.data.review_on}
                        onChange={(e) => form.setData('review_on', e.target.value)} />
                </label>
            </div>
            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">
                    In one line — enough for somebody to act on without reading everything
                </span>
                <input className="form-input w-full" value={form.data.summary}
                    onChange={(e) => form.setData('summary', e.target.value)} />
                {form.errors.summary && <span className="mt-1 block text-xs text-red-600">{form.errors.summary}</span>}
            </label>
            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">Detail (optional)</span>
                <textarea className="form-input w-full" rows={4} value={form.data.body}
                    onChange={(e) => form.setData('body', e.target.value)} />
            </label>
            <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                Record
            </button>
        </form>
    );
}

export default function Index({ q = '', student_id = null, matches = [], categories = [], notes = [], views = [] }) {
    const [query, setQuery] = useState(q);

    const search = (value) => {
        setQuery(value);
        router.get('/people/sensitive', { q: value }, { preserveState: true, replace: true });
    };

    const chosen = matches.find((m) => String(m.id) === String(student_id));

    return (
        <AppShell title="Sensitive information">
            <div className="mb-4 rounded-lg border border-[#E0C9A6] bg-[#FBF3E7] p-4 text-sm">
                <p className="font-semibold">Health and welfare information about a child.</p>
                <p className="mt-1 text-gray-700">
                    Every time these notes are opened it is recorded, including who opened them. The
                    log is shown below the notes, not hidden in an audit page. Nothing here is
                    exported and nothing is deleted.
                </p>
            </div>

            <div className="mb-6 rounded-lg border bg-white p-4">
                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">Find a pupil</span>
                    <input className="form-input w-full" placeholder="Name or student number"
                        value={query} onChange={(e) => search(e.target.value)} />
                </label>
                {matches.length > 0 && (
                    <ul className="mt-3 grid gap-1">
                        {matches.map((child) => (
                            <li key={child.id}>
                                <button
                                    className="text-sm text-[#7C2D37] underline"
                                    onClick={() => router.get('/people/sensitive', { q: query, student_id: child.id }, { preserveState: true })}
                                >
                                    {child.name}
                                    <span className="ms-2 text-xs text-gray-500">
                                        {[child.student_number, child.current_class].filter(Boolean).join(' · ')}
                                    </span>
                                </button>
                                {child.indistinguishable && (
                                    <span className="ms-2 rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-800">
                                        same name as another pupil — check the number
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                {query.length >= 2 && matches.length === 0 && (
                    <p className="mt-2 text-sm text-gray-600">Nobody matches “{query}”.</p>
                )}
            </div>

            {!student_id && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    Choose a pupil. Nothing is read — and nothing is logged — until you do.
                </p>
            )}

            {student_id && (
                <>
                    <h2 className="mb-2 text-sm font-semibold">
                        Notes{chosen ? ` — ${chosen.name}` : ''} ({notes.filter((n) => !n.archived).length})
                    </h2>

                    <ul className="mb-6 grid gap-2">
                        {notes.map((note) => (
                            <li key={note.id} className={`rounded-lg border bg-white p-3 text-sm ${note.archived ? 'opacity-60' : ''}`}>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded bg-[#F3EBE0] px-2 py-0.5 text-xs">{note.category_label}</span>
                                    {note.needs_review && !note.archived && (
                                        <span className="rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-800">
                                            due to be looked at again
                                        </span>
                                    )}
                                    {note.archived && <span className="text-xs text-gray-500">archived</span>}
                                </div>
                                <p className="mt-1 font-medium">{note.summary}</p>
                                {note.body && <p className="mt-1 whitespace-pre-line text-gray-700">{note.body}</p>}
                                <p className="mt-1 text-xs text-gray-500">
                                    {note.author} · {note.recorded_on}
                                    {note.review_on && ` · review ${note.review_on}`}
                                </p>
                                {!note.archived && (
                                    <button
                                        className="mt-2 text-xs text-[#7C2D37] underline"
                                        onClick={() => router.post(`/people/sensitive/${note.id}/archive`, {}, { preserveScroll: true })}
                                    >
                                        Archive
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                    {notes.length === 0 && (
                        <p className="mb-6 rounded-lg border bg-white p-4 text-sm text-gray-600">
                            Nothing recorded for this pupil.
                        </p>
                    )}

                    <NoteForm studentId={student_id} categories={categories} />

                    <h2 className="mb-2 text-sm font-semibold">Who has looked ({views.length})</h2>
                    <ul className="grid gap-1 rounded-lg border bg-white p-3 text-sm">
                        {views.map((view) => (
                            <li key={view.id} className="text-gray-700">
                                {view.who} <span className="text-xs text-gray-500">{view.at}</span>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </AppShell>
    );
}
