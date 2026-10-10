import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';

/**
 * A pupil's health and welfare notes, and who has read them. Every word is
 * the `people` book's (slice PE2, STATUS §5qq); a note's category is named
 * rather than read from the server's English, and a refused Archive is said
 * under its note — it was said nowhere — as is every refusal of the form.
 */
export default function Index({ q = '', student_id = null, matches = [], categories = [], notes = [], views = [], t = {} }) {
    const [query, setQuery] = useState(q);
    // The note form's own refusals are said under it; the row refusals are
    // what an Archive brought back.
    const noteForm = useForm({ student_id: student_id, category: 'medical', summary: '', body: '', review_on: '' });
    const refusals = useRowRefusals(noteForm);

    const search = (value) => {
        setQuery(value);
        router.get('/people/sensitive', { q: value }, { preserveState: true, replace: true });
    };

    const chosen = matches.find((m) => String(m.id) === String(student_id));
    const open = notes.filter((n) => !n.archived).length;

    return (
        <AppShell title={t.sensitive_title || 'Sensitive information'}>
            <div className="mb-4 rounded-lg border border-[#E0C9A6] bg-[#FBF3E7] p-4 text-sm">
                <p className="font-semibold">{t.sensitive_lead || 'Health and welfare information about a child.'}</p>
                <p className="mt-1 text-gray-700">
                    {t.sensitive_logged || 'Every time these notes are opened it is recorded, including who opened them. The log is shown below the notes, not hidden in an audit page. Nothing here is exported and nothing is deleted.'}
                </p>
            </div>

            <div className="mb-6 rounded-lg border bg-white p-4">
                <label className="block text-sm">
                    <span className="mb-1 block font-semibold">{t.sensitive_find || 'Find a pupil'}</span>
                    <input className="form-input w-full" placeholder={t.sensitive_find_hint || 'Name or student number'}
                        value={query} onChange={(e) => search(e.target.value)} />
                </label>
                {matches.length > 0 && (
                    <ul className="mt-3 grid gap-1">
                        {matches.map((child) => (
                            <li key={child.id}>
                                <button
                                    type="button"
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
                                        {t.sensitive_same_name || 'same name as another pupil — check the number'}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                {query.length >= 2 && matches.length === 0 && (
                    <p className="mt-2 text-sm text-gray-600">{(t.sensitive_nobody || 'Nobody matches “:query”.').replace(':query', query)}</p>
                )}
            </div>

            {!student_id && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    {t.sensitive_choose || 'Choose a pupil. Nothing is read — and nothing is logged — until you do.'}
                </p>
            )}

            {student_id && (
                <>
                    <h2 className="mb-2 text-sm font-semibold">
                        {chosen
                            ? (t.sensitive_notes_for || 'Notes — :name (:count)').replace(':name', chosen.name).replace(':count', open)
                            : (t.sensitive_notes || 'Notes (:count)').replace(':count', open)}
                    </h2>
                    <FormErrors errors={refusals.unplaced} className="mb-2" />

                    <ul className="mb-6 grid gap-2">
                        {notes.map((note) => (
                            <li key={note.id} className={`rounded-lg border bg-white p-3 text-sm ${note.archived ? 'opacity-60' : ''}`}>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded bg-[#F3EBE0] px-2 py-0.5 text-xs">{t[`sensitive_category_${note.category}`] || note.category_label}</span>
                                    {note.needs_review && !note.archived && (
                                        <span className="rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-800">
                                            {t.sensitive_due || 'due to be looked at again'}
                                        </span>
                                    )}
                                    {note.archived && <span className="text-xs text-gray-500">{t.sensitive_archived || 'archived'}</span>}
                                </div>
                                <p className="mt-1 font-medium">{note.summary}</p>
                                {note.body && <p className="mt-1 whitespace-pre-line text-gray-700">{note.body}</p>}
                                <p className="mt-1 text-xs text-gray-500">
                                    {note.author} · {note.recorded_on}
                                    {note.review_on && ` · ${(t.sensitive_review || 'review :date').replace(':date', note.review_on)}`}
                                </p>
                                {!note.archived && (
                                    <button
                                        type="button"
                                        className="mt-2 text-xs text-[#7C2D37] underline"
                                        onClick={() => refusals.actOn(`note:${note.id}`, () => router.post(`/people/sensitive/${note.id}/archive`, {}, { preserveScroll: true }))}
                                    >
                                        {t.sensitive_archive || 'Archive'}
                                    </button>
                                )}
                                <FormErrors errors={refusals.errorsFor(`note:${note.id}`)} className="mt-1" />
                            </li>
                        ))}
                    </ul>
                    {notes.length === 0 && (
                        <p className="mb-6 rounded-lg border bg-white p-4 text-sm text-gray-600">
                            {t.sensitive_none || 'Nothing recorded for this pupil.'}
                        </p>
                    )}

                    <form
                        className="mb-6 grid gap-3 rounded-lg border bg-white p-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            noteForm.transform((data) => ({ ...data, student_id: student_id }));
                            noteForm.post('/people/sensitive', { preserveScroll: true, onSuccess: () => noteForm.reset() });
                        }}
                    >
                        <p className="text-sm font-semibold">{t.sensitive_add || 'Add a note'}</p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="block text-sm">
                                <span className="mb-1 block text-gray-600">{t.sensitive_category || 'Category'}</span>
                                <select className="form-input w-full" value={noteForm.data.category}
                                    onChange={(e) => noteForm.setData('category', e.target.value)}>
                                    {categories.map((c) => <option key={c.value} value={c.value}>{t[`sensitive_category_${c.value}`] || c.label}</option>)}
                                </select>
                            </label>
                            <label className="block text-sm">
                                <span className="mb-1 block text-gray-600">{t.sensitive_review_on || 'Look at this again on (optional)'}</span>
                                <input type="date" className="form-input w-full" value={noteForm.data.review_on}
                                    onChange={(e) => noteForm.setData('review_on', e.target.value)} />
                            </label>
                        </div>
                        <label className="block text-sm">
                            <span className="mb-1 block text-gray-600">
                                {t.sensitive_summary || 'In one line — enough for somebody to act on without reading everything'}
                            </span>
                            <input className="form-input w-full" aria-label={t.sensitive_summary || 'In one line — enough for somebody to act on without reading everything'} value={noteForm.data.summary}
                                onChange={(e) => noteForm.setData('summary', e.target.value)} />
                            {noteForm.errors.summary && <span className="mt-1 block text-xs text-red-600">{noteForm.errors.summary}</span>}
                        </label>
                        <label className="block text-sm">
                            <span className="mb-1 block text-gray-600">{t.sensitive_body || 'Detail (optional)'}</span>
                            <textarea className="form-input w-full" rows={4} value={noteForm.data.body}
                                onChange={(e) => noteForm.setData('body', e.target.value)} />
                        </label>
                        <FormErrors errors={noteForm.errors} except={['summary']} />
                        <button type="submit" className="btn-primary justify-self-start" disabled={noteForm.processing}>
                            {t.sensitive_record || 'Record'}
                        </button>
                    </form>

                    <h2 className="mb-2 text-sm font-semibold">{(t.sensitive_views || 'Who has looked (:count)').replace(':count', views.length)}</h2>
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
