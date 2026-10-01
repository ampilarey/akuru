import { Link, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * Create or edit a website page (C9 slice 10, STATUS §5jl). The body is
 * authored HTML, sanitised on write by the controller; the form is keyed on
 * the page so new and edit never share state (STATUS §5jj).
 */
export default function PageForm(props) {
    return <PageFormBody key={props.page?.id ?? 'new'} {...props} />;
}

function PageFormBody({ page = null, t = {} }) {
        const editing = page !== null;
    const form = useForm({
        title: page?.title || '',
        slug: page?.slug || '',
        excerpt: page?.excerpt || '',
        body: page?.body || '',
        cover_image: page?.cover_image || '',
        is_published: editing ? !!page.is_published : false,
    });
    const submit = (e) => {
        e.preventDefault();
        // The controller reads presence, as the Blade checkbox sent it: only send the flag when on.
        form.transform(({ is_published, ...data }) => (is_published ? { ...data, is_published: 1 } : data));
        if (editing) form.put(`/admin/public-site/pages/${page.id}`, { preserveScroll: true });
        else form.post('/admin/public-site/pages', { preserveScroll: true });
    };
    const firstError = Object.values(form.errors)[0];
    const field = (name, label, props = {}) => (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor={`page-${name}`}>{label}{props.required && <span className="text-red-500"> *</span>}</label>
            <input id={`page-${name}`} name={name} className={`form-input w-full ${form.errors[name] ? 'border-red-500' : ''}`} value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} {...props} />
            {form.errors[name] && <p className="mt-1 text-xs text-red-700">{form.errors[name]}</p>}
        </div>
    );

    return (
        <AppShell title={editing ? (t.pages_edit_title || 'Edit Page: :title').replace(':title', page.title) : (t.pages_new_title || 'Create New Page')}>
            <p className="mb-4 text-sm"><Link href="/admin/public-site/pages" className="text-gray-500 underline" data-testid="pages-back">{t.pages_back || '← Back to Pages'}</Link></p>
            {firstError && <p className="mb-4 rounded bg-red-50 p-3 text-sm text-red-700" data-testid="pages-error">✗ {firstError}</p>}

            <form onSubmit={submit} className="max-w-3xl space-y-5 rounded-lg border bg-white p-6" data-testid="page-form">
                {field('title', t.pages_col_title || 'Title', { required: true, type: 'text' })}
                {field('slug', t.pages_col_slug || 'Slug', { required: true, type: 'text', placeholder: 'url-friendly-slug', dir: 'ltr' })}
                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="page-excerpt">{t.pages_excerpt || 'Excerpt'}</label>
                    <textarea id="page-excerpt" name="excerpt" rows="2" className="form-input w-full" value={form.data.excerpt} onChange={(e) => form.setData('excerpt', e.target.value)} />
                    {form.errors.excerpt && <p className="mt-1 text-xs text-red-700">{form.errors.excerpt}</p>}
                </div>
                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="page-body">{t.pages_content || 'Content'}<span className="text-red-500"> *</span></label>
                    <textarea id="page-body" name="body" rows="12" required className={`form-input w-full font-mono text-xs ${form.errors.body ? 'border-red-500' : ''}`} dir="ltr" value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
                    <p className="mt-1 text-xs text-gray-500">{t.pages_content_hint || 'HTML. Scripts and unsafe markup are removed on save.'}</p>
                    {form.errors.body && <p className="mt-1 text-xs text-red-700">{form.errors.body}</p>}
                </div>
                {field('cover_image', t.pages_cover || 'Cover Image URL', { type: 'text', placeholder: 'https://…', dir: 'ltr' })}
                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_published" checked={form.data.is_published} onChange={(e) => form.setData('is_published', e.target.checked)} className="rounded border-gray-300" data-testid="page-published" />
                    {t.pages_published || 'Published'}
                </label>
                <div className="flex justify-end gap-3">
                    <Link href="/admin/public-site/pages" className="btn-secondary">{t.instructors_cancel || 'Cancel'}</Link>
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="page-save">{editing ? (t.pages_update || 'Update Page') : (t.pages_create || 'Create Page')}</button>
                </div>
            </form>
        </AppShell>
    );
}
