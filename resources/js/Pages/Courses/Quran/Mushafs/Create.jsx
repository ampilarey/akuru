import { useForm } from '@inertiajs/react';
import AppShell from '../../../../Layouts/AppShell';

export default function MushafCreate() {
    const form = useForm({ name: '', description: '', source_file: null, page_count: '' });

    // `transform()` returns undefined in @inertiajs/react v3, so it can never be
    // chained onto the submit call — a mistake this repo has shipped before.
    const submit = (event) => {
        event.preventDefault();
        form.post('/quran/mushafs', { forceFormData: true });
    };

    return (
        <AppShell title="Upload mushaf">
            <form onSubmit={submit} className="max-w-2xl space-y-4 rounded-lg border bg-white p-6">
                <div>
                    <label className="mb-1 block text-sm font-medium" htmlFor="name">Name</label>
                    <input
                        id="name"
                        className="form-input w-full"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        required
                    />
                    {form.errors.name && <p className="mt-1 text-sm text-red-600">{form.errors.name}</p>}
                </div>
                <div>
                    <label className="mb-1 block text-sm font-medium" htmlFor="description">Description</label>
                    <textarea
                        id="description"
                        rows={2}
                        className="form-input w-full"
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                    />
                </div>
                <div>
                    <label className="mb-1 block text-sm font-medium" htmlFor="source_file">PDF or Word file</label>
                    <input
                        id="source_file"
                        type="file"
                        accept=".pdf,.doc,.docx"
                        className="form-input w-full"
                        onChange={(e) => form.setData('source_file', e.target.files[0] ?? null)}
                    />
                    {form.errors.source_file && <p className="mt-1 text-sm text-red-600">{form.errors.source_file}</p>}
                </div>
                <div>
                    <label className="mb-1 block text-sm font-medium" htmlFor="page_count">
                        Page count (creates that many page placeholders)
                    </label>
                    <input
                        id="page_count"
                        type="number"
                        min="1"
                        max="604"
                        className="form-input w-full"
                        value={form.data.page_count}
                        onChange={(e) => form.setData('page_count', e.target.value)}
                    />
                    {form.errors.page_count && <p className="mt-1 text-sm text-red-600">{form.errors.page_count}</p>}
                </div>
                <button type="submit" className="btn-primary" disabled={form.processing}>
                    Create mushaf
                </button>
            </form>
        </AppShell>
    );
}
