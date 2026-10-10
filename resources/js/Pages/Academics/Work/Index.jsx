import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

// In the page's language (BACKLOG C21, slice OA3).
function UploadForm({ matches, q, onSearch, t }) {
    const form = useForm({ student_id: '', photo: null, title: '', note: '', done_on: '' });
    const chosen = matches.find((m) => `${m.id}` === `${form.data.student_id}`);

    return (
        <form
            className="mb-6 grid gap-3 rounded-lg border bg-white p-4"
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/academics/work', { forceFormData: true, onSuccess: () => form.reset() });
            }}
        >
            <p className="text-sm font-semibold">{t.work_photograph || 'Photograph a piece of work'}</p>

            <label className="block text-sm">
                <span className="mb-1 block text-gray-600">{t.work_whose || 'Whose work is it?'}</span>
                <input
                    className="form-input w-full"
                    placeholder={t.work_search || 'Search by name or student number'}
                    aria-label={t.work_whose || 'Whose work is it?'}
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
                                    checked={`${form.data.student_id}` === `${child.id}`}
                                    onChange={() => form.setData('student_id', child.id)}
                                />
                                <span>
                                    {child.name}
                                    <span className="ms-2 text-xs text-gray-500">
                                        {[child.student_number, child.current_class].filter(Boolean).join(' · ')}
                                    </span>
                                    {child.indistinguishable && (
                                        <span className="ms-2 rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-800">
                                            {t.same_name_check || 'same name as another pupil — check the number'}
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
                <span className="mb-1 block text-gray-600">{t.work_photo || 'Photo'}</span>
                <input type="file" accept="image/*" capture="environment" aria-label={t.work_photo || 'Photo'}
                    onChange={(e) => form.setData('photo', e.target.files[0])} />
                {form.errors.photo && <span className="mt-1 block text-xs text-red-600">{form.errors.photo}</span>}
            </label>

            <div className="grid gap-3 sm:grid-cols-3">
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.work_title_optional || 'Title (optional)'}</span>
                    <input className="form-input w-full" aria-label={t.work_title_optional || 'Title (optional)'} value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)} />
                    {form.errors.title && <span className="mt-1 block text-xs text-red-600">{form.errors.title}</span>}
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.work_note_optional || 'Note (optional)'}</span>
                    <input className="form-input w-full" aria-label={t.work_note_optional || 'Note (optional)'} value={form.data.note}
                        onChange={(e) => form.setData('note', e.target.value)} />
                    {form.errors.note && <span className="mt-1 block text-xs text-red-600">{form.errors.note}</span>}
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.work_done_on || 'Done on'}</span>
                    <input type="date" className="form-input w-full" aria-label={t.work_done_on || 'Done on'} value={form.data.done_on}
                        onChange={(e) => form.setData('done_on', e.target.value)} />
                    {form.errors.done_on && <span className="mt-1 block text-xs text-red-600">{form.errors.done_on}</span>}
                </label>
            </div>

            <p className="text-xs text-gray-600">
                {chosen
                    ? (t.work_goes_to || 'This will go to the family of :name.').replace(':name', chosen.name)
                    : (t.work_choose_first || 'Nothing is sent to a family until you choose a pupil.')}
            </p>

            <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                {t.save || 'Save'}
            </button>
        </form>
    );
}

function ReassignRow({ work, matches, q, onSearch, t }) {
    const [open, setOpen] = useState(false);
    // A move the server refuses (the same pupil, or none chosen) is said here.
    const { errors = {} } = usePage().props;

    return (
        <>
            <button className="text-xs text-[#7C2D37] underline" onClick={() => setOpen(!open)}>
                {t.work_wrong_pupil || 'Wrong pupil?'}
            </button>
            {open && (
                <div className="mt-2 rounded border bg-[#FBF7F2] p-2">
                    <input
                        className="form-input w-full text-xs"
                        placeholder={t.work_search_right || 'Search for the right pupil'}
                        aria-label={`${t.work_search_right || 'Search for the right pupil'}: ${work.student}`}
                        value={q}
                        onChange={(e) => onSearch(e.target.value)}
                    />
                    {errors.student_id && <p className="mt-1 text-xs text-red-600">{errors.student_id}</p>}
                    <ul className="mt-2 grid gap-1">
                        {matches.map((child) => (
                            <li key={child.id}>
                                <button
                                    className="text-xs text-[#7C2D37] underline"
                                    onClick={() =>
                                        router.post(`/academics/work/${work.id}/reassign`, { student_id: child.id }, { preserveScroll: true })
                                    }
                                >
                                    {(t.work_move_to || 'Move to :name').replace(':name', child.name)} {child.student_number ? `(${child.student_number})` : ''}
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </>
    );
}

export default function Index({ q = '', matches = [], work = [], t = {} }) {
    const [query, setQuery] = useState(q);
    // A move or a hide the server refuses comes back as the page's errors;
    // it was said nowhere.
    const { errors = {} } = usePage().props;
    const refused = errors.work;

    const search = (value) => {
        setQuery(value);
        router.get('/academics/work', { q: value }, { preserveState: true, replace: true });
    };

    return (
        <AppShell title={t.work_title || 'Student work'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/academics/work/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            {refused && <p className="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">{refused}</p>}
            <UploadForm matches={matches} q={query} onSearch={search} t={t} />

            <h2 className="mb-2 text-sm font-semibold">{(t.work_photographed || 'Photographed (:count)').replace(':count', work.length)}</h2>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {work.map((item) => (
                    <div key={item.id} className={`rounded-lg border bg-white p-3 ${item.hidden ? 'opacity-60' : ''}`}>
                        <img
                            src={`/academics/work/${item.id}/photo`}
                            alt={item.title || (t.work_by || 'Work by :name').replace(':name', item.student)}
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
                                {item.times_moved === 1
                                    ? (t.work_moved_once || 'Moved once')
                                    : (t.work_moved || 'Moved :count times').replace(':count', item.times_moved)}
                            </p>
                        )}
                        <div className="mt-2 flex flex-wrap items-center gap-3">
                            {item.hidden ? (
                                <button
                                    className="text-xs text-[#7C2D37] underline"
                                    onClick={() => router.post(`/academics/work/${item.id}/restore`, {}, { preserveScroll: true })}
                                >
                                    {t.work_show_again || 'Show families again'}
                                </button>
                            ) : (
                                <button
                                    className="text-xs text-[#7C2D37] underline"
                                    onClick={() => router.post(`/academics/work/${item.id}/hide`, {}, { preserveScroll: true })}
                                >
                                    {t.work_hide || 'Hide from families'}
                                </button>
                            )}
                            <ReassignRow work={item} matches={matches} q={query} onSearch={search} t={t} />
                        </div>
                    </div>
                ))}
            </div>
            {work.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">{t.work_none || 'Nothing photographed yet.'}</p>
            )}

            <p className="mt-4 text-xs text-gray-500">
                {t.work_footer || 'Work reaching the wrong parent is the failure this screen is built around. Moving a photo takes effect at once — the first family stops seeing it immediately — and every move is recorded, so the school can say which family saw what and for how long.'}
            </p>
        </AppShell>
    );
}
