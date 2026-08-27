import { router, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

function ItemForm({ categories, options }) {
    const form = useForm({
        title: '',
        subtitle: '',
        content_type: 'article',
        access_type: 'free_public',
        library_category_id: '',
        abstract: '',
        body: '',
        cover_image: '',
        reading_time: '',
        tags_text: '',
        authors_text: '',
        pdf: null,
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((data) => ({
                    ...data,
                    tags: data.tags_text ? data.tags_text.split(',').map((tag) => tag.trim()).filter(Boolean) : [],
                    authors: data.authors_text
                        ? data.authors_text.split(',').map((name) => ({ name: name.trim() })).filter((author) => author.name)
                        : [],
                })).post('/admin/library/items', {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => form.reset(),
                });
            }}
            className="mb-6 grid gap-2 rounded-lg border bg-white p-4 md:grid-cols-4"
        >
            <input className="form-input md:col-span-2" placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
            <select className="form-input" value={form.data.content_type} onChange={(e) => form.setData('content_type', e.target.value)}>
                {options.content_types.map((type) => <option key={type} value={type}>{type.replaceAll('_', ' ')}</option>)}
            </select>
            <select className="form-input" value={form.data.access_type} onChange={(e) => form.setData('access_type', e.target.value)}>
                {options.access_types.map((type) => <option key={type} value={type}>{type.replaceAll('_', ' ')}</option>)}
            </select>

            <input className="form-input" placeholder="Subtitle" value={form.data.subtitle} onChange={(e) => form.setData('subtitle', e.target.value)} />
            <select className="form-input" value={form.data.library_category_id} onChange={(e) => form.setData('library_category_id', e.target.value)}>
                <option value="">Category…</option>
                {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
            </select>
            <input className="form-input" placeholder="Authors (comma-separated)" value={form.data.authors_text} onChange={(e) => form.setData('authors_text', e.target.value)} />
            <input className="form-input" placeholder="Tags (comma-separated)" value={form.data.tags_text} onChange={(e) => form.setData('tags_text', e.target.value)} />

            <textarea className="form-input md:col-span-2" rows="2" placeholder="Abstract" value={form.data.abstract} onChange={(e) => form.setData('abstract', e.target.value)} />
            <input className="form-input" placeholder="Cover image URL" value={form.data.cover_image} onChange={(e) => form.setData('cover_image', e.target.value)} />
            <input className="form-input" type="number" min="1" placeholder="Reading time (min)" value={form.data.reading_time} onChange={(e) => form.setData('reading_time', e.target.value)} />

            <textarea className="form-input md:col-span-4" rows="6" placeholder="Body (HTML — the free-reading content)" value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />

            <label className="text-sm md:col-span-3">
                Original PDF (stored privately — never exposed)
                <input className="form-input" type="file" accept="application/pdf" onChange={(e) => form.setData('pdf', e.target.files[0] ?? null)} />
            </label>
            <button type="submit" className="btn-primary self-end" disabled={form.processing}>Save item</button>
        </form>
    );
}

function CategoryForm() {
    const form = useForm({ name: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/admin/library/categories', { preserveScroll: true, onSuccess: () => form.reset() });
            }}
            className="flex gap-2"
        >
            <input className="form-input" placeholder="New category" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
            <button type="submit" className="btn-secondary" disabled={form.processing}>Add</button>
        </form>
    );
}

export default function Admin({ items, categories, options }) {
    return (
        <AppShell title="Library admin">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <CategoryForm />
                <a className="btn-secondary" href="/admin/library?format=csv">Export CSV</a>
            </div>

            <ItemForm categories={categories} options={options} />

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Access</th>
                            <th className="px-3 py-2">Category</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Published</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {items.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={7}>No library items yet.</td></tr>
                        )}
                        {items.map((item) => (
                            <tr key={item.id} className="border-t">
                                <td className="px-3 py-2">
                                    <div className="font-medium">{item.title}</div>
                                    <div className="text-xs text-gray-500">/{item.slug}{item.has_pdf ? ' · PDF' : ''}</div>
                                </td>
                                <td className="px-3 py-2">{item.content_type?.replaceAll('_', ' ')}</td>
                                <td className="px-3 py-2">{item.access_type?.replaceAll('_', ' ')}</td>
                                <td className="px-3 py-2">{item.category?.name ?? '—'}</td>
                                <td className="px-3 py-2">{item.status}</td>
                                <td className="px-3 py-2">{item.published_at ?? '—'}</td>
                                <td className="px-3 py-2 text-end">
                                    <button
                                        type="button"
                                        className={item.status === 'published' ? 'text-sm text-red-600' : 'btn-primary'}
                                        onClick={() => router.post(`/admin/library/items/${item.id}/publish`, { publish: item.status !== 'published' }, { preserveScroll: true })}
                                    >
                                        {item.status === 'published' ? 'Unpublish' : 'Publish'}
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
