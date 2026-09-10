import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
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

function queryString(filters) {
    const params = new URLSearchParams();
    if (filters.q) params.set('q', filters.q);
    if (filters.subject_id) params.set('subject_id', filters.subject_id);
    if (filters.tag) params.set('tag', filters.tag);
    if (filters.mine) params.set('mine', '1');
    const query = params.toString();

    return query ? `?${query}` : '';
}

export default function Index({ materials = [], subjects = [], filters = {}, userId }) {
    // Kept in local state so typing does not fire a request per keystroke; the
    // search runs on submit.
    const [search, setSearch] = useState({
        q: filters.q || '',
        subject_id: filters.subject_id || '',
        tag: filters.tag || '',
        mine: !!filters.mine,
    });
    const create = useForm({ title: '', body: '', subject_id: '', tags: '' });
    const [editing, setEditing] = useState(null);

    const applySearch = (next) => {
        setSearch(next);
        router.get(`/academics/materials${queryString(next)}`, {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <AppShell title="Teaching materials">
            <p className="mb-4 text-sm text-gray-600">
                Write a material once and attach it to any lesson. Everyone on the
                staff can see and reuse these; only the author can edit one.
            </p>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    applySearch(search);
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4"
            >
                <Field label="Search title or text">
                    <input
                        className="form-input w-full"
                        value={search.q}
                        onChange={(e) => setSearch({ ...search, q: e.target.value })}
                    />
                </Field>
                <Field label="Subject">
                    <select
                        className="form-input w-full"
                        value={search.subject_id}
                        onChange={(e) => setSearch({ ...search, subject_id: e.target.value })}
                    >
                        <option value="">All subjects</option>
                        {subjects.map((subject) => (
                            <option key={subject.id} value={subject.id}>{subject.name}</option>
                        ))}
                    </select>
                </Field>
                <Field label="Tag">
                    <input
                        className="form-input w-full"
                        value={search.tag}
                        onChange={(e) => setSearch({ ...search, tag: e.target.value })}
                    />
                </Field>
                <div className="flex flex-wrap items-end gap-3">
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={search.mine}
                            onChange={(e) => applySearch({ ...search, mine: e.target.checked })}
                        />
                        Mine only
                    </label>
                    <button type="submit" className="btn-primary">Search</button>
                    <a className="btn-secondary" href={`/academics/materials/export${queryString(search)}`}>CSV</a>
                </div>
            </form>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    create.post('/academics/materials', {
                        preserveScroll: true,
                        onSuccess: () => create.reset(),
                    });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2"
            >
                <p className="text-sm font-semibold md:col-span-2">New material</p>
                <Field label="Title" error={create.errors.title}>
                    <input
                        className="form-input w-full"
                        value={create.data.title}
                        onChange={(e) => create.setData('title', e.target.value)}
                    />
                </Field>
                <Field label="Subject" error={create.errors.subject_id}>
                    <select
                        className="form-input w-full"
                        value={create.data.subject_id}
                        onChange={(e) => create.setData('subject_id', e.target.value)}
                    >
                        <option value="">No subject</option>
                        {subjects.map((subject) => (
                            <option key={subject.id} value={subject.id}>{subject.name}</option>
                        ))}
                    </select>
                </Field>
                <div className="md:col-span-2">
                    <Field label="Details" error={create.errors.body}>
                        <textarea
                            className="form-input w-full"
                            rows={3}
                            value={create.data.body}
                            onChange={(e) => create.setData('body', e.target.value)}
                        />
                    </Field>
                </div>
                <Field label="Tags (comma separated)" error={create.errors.tags}>
                    <input
                        className="form-input w-full"
                        value={create.data.tags}
                        onChange={(e) => create.setData('tags', e.target.value)}
                    />
                </Field>
                <div className="flex items-end">
                    <button type="submit" className="btn-primary" disabled={create.processing}>Save material</button>
                </div>
            </form>

            {materials.length === 0 ? (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    No materials match. Add one above and it is available to every lesson.
                </p>
            ) : (
                <div className="grid gap-3">
                    {materials.map((material) => (
                        editing === material.id ? (
                            <EditCard
                                key={material.id}
                                material={material}
                                subjects={subjects}
                                onDone={() => setEditing(null)}
                            />
                        ) : (
                            <article key={material.id} className="rounded-lg border bg-white p-4">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <h2 className="font-semibold">{material.title}</h2>
                                    {material.created_by === userId && (
                                        <button
                                            type="button"
                                            className="text-sm text-[#7C2D37] underline"
                                            onClick={() => setEditing(material.id)}
                                        >
                                            Edit
                                        </button>
                                    )}
                                </div>
                                <p className="mt-1 text-xs text-gray-500">
                                    {material.subject || 'No subject'} · {material.author}
                                </p>
                                {material.body && (
                                    <p className="mt-2 whitespace-pre-line text-sm text-gray-700">{material.body}</p>
                                )}
                                {material.tags.length > 0 && (
                                    <p className="mt-2 flex flex-wrap gap-1">
                                        {material.tags.map((tag) => (
                                            <button
                                                key={tag}
                                                type="button"
                                                className="rounded bg-[#F3EBE0] px-2 py-0.5 text-xs"
                                                onClick={() => applySearch({ ...search, tag })}
                                            >
                                                {tag}
                                            </button>
                                        ))}
                                    </p>
                                )}
                                <Files
                                    material={material}
                                    canEdit={material.created_by === userId}
                                />
                            </article>
                        )
                    ))}
                </div>
            )}
        </AppShell>
    );
}

function humanSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function Files({ material, canEdit }) {
    const upload = useForm({ file: null });
    const remove = useForm({});
    const files = material.files || [];

    return (
        <div className="mt-3 border-t pt-3">
            {files.length === 0 ? (
                <p className="text-xs text-gray-500">No files attached.</p>
            ) : (
                <ul className="space-y-1">
                    {files.map((file) => (
                        <li key={file.id} className="flex flex-wrap items-center gap-2 text-sm">
                            <a
                                className="text-[#7C2D37] underline"
                                href={`/academics/materials/files/${file.id}`}
                            >
                                {file.name}
                            </a>
                            <span className="text-xs text-gray-500">{humanSize(file.size)}</span>
                            {canEdit && (
                                <button
                                    type="button"
                                    className="text-xs text-gray-500 underline"
                                    onClick={() => remove.delete(`/academics/materials/files/${file.id}`, { preserveScroll: true })}
                                >
                                    Remove
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {canEdit && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        upload.post(`/academics/materials/${material.id}/files`, {
                            preserveScroll: true,
                            forceFormData: true,
                            onSuccess: () => upload.reset(),
                        });
                    }}
                    className="mt-2 flex flex-wrap items-center gap-2"
                >
                    <input
                        type="file"
                        className="text-xs"
                        onChange={(e) => upload.setData('file', e.target.files[0])}
                    />
                    <button type="submit" className="btn-secondary text-xs" disabled={!upload.data.file || upload.processing}>
                        Add file
                    </button>
                    {upload.errors.file && <span className="text-xs text-red-600">{upload.errors.file}</span>}
                </form>
            )}
        </div>
    );
}

function EditCard({ material, subjects, onDone }) {
    const form = useForm({
        title: material.title,
        body: material.body || '',
        subject_id: material.subject_id || '',
        tags: material.tags.join(', '),
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put(`/academics/materials/${material.id}`, {
                    preserveScroll: true,
                    onSuccess: onDone,
                });
            }}
            className="grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2"
        >
            <Field label="Title" error={form.errors.title}>
                <input className="form-input w-full" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
            </Field>
            <Field label="Subject">
                <select className="form-input w-full" value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                    <option value="">No subject</option>
                    {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                </select>
            </Field>
            <div className="md:col-span-2">
                <Field label="Details">
                    <textarea className="form-input w-full" rows={3} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
                </Field>
            </div>
            <Field label="Tags (comma separated)">
                <input className="form-input w-full" value={form.data.tags} onChange={(e) => form.setData('tags', e.target.value)} />
            </Field>
            <div className="flex items-end gap-3">
                <button type="submit" className="btn-primary" disabled={form.processing}>Save</button>
                <button type="button" className="btn-secondary" onClick={onDone}>Cancel</button>
            </div>
        </form>
    );
}
