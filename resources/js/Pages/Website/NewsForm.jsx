import { Link, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import BodyEditor from '../../Components/BodyEditor';

/**
 * RESEARCH_ARTICLES_PLAN R4: write or edit a news item. The body is the rich
 * text editor; the server sanitises it (PROFILE_CMS) before it reaches the
 * public page. Keyed on the post so new and edit never share state.
 */
export default function NewsForm(props) {
    return <NewsFormBody key={props.post?.id ?? 'new'} {...props} />;
}

function NewsFormBody({ post = null, categories = [], t = {} }) {
        const editing = post !== null;
    const form = useForm({
        title: post?.title || '',
        slug: post?.slug || '',
        summary: post?.summary || '',
        body: post?.body || '',
        post_category_id: post?.post_category_id || '',
        tags: (post?.tags || []).join(', '),
        is_featured: !!post?.is_featured,
        is_pinned: !!post?.is_pinned,
        is_published: !!post?.is_published,
        published_at: post?.published_at_local || '',
        meta_description: post?.meta_description || '',
        cover: null,
    });
    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            is_featured: data.is_featured ? 1 : 0,
            is_pinned: data.is_pinned ? 1 : 0,
            is_published: data.is_published ? 1 : 0,
            ...(editing ? { _method: 'put' } : {}),
        }));
        form.post(editing ? `/admin/public-site/news/${post.id}` : '/admin/public-site/news', { preserveScroll: true, forceFormData: true });
    };
    const firstError = Object.values(form.errors)[0];
    const input = (name, label, props = {}) => (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor={`news-${name}`}>{label}</label>
            <input id={`news-${name}`} name={name} className={`form-input w-full ${form.errors[name] ? 'border-red-500' : ''}`} value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} {...props} />
            {form.errors[name] && <p className="mt-1 text-xs text-red-700">{form.errors[name]}</p>}
        </div>
    );
    const check = (name, label) => (
        <label className="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name={name} checked={form.data[name]} onChange={(e) => form.setData(name, e.target.checked)} className="rounded border-gray-300" data-testid={`news-${name}`} />
            {label}
        </label>
    );

    return (
        <AppShell title={editing ? (t.news_edit_title || 'Edit news: :title').replace(':title', post.title) : (t.news_new_title || 'Write news')}>
            <p className="mb-4 text-sm"><Link href="/admin/public-site/news" className="text-gray-500 underline">{t.news_back || '← Back to News'}</Link></p>
            {firstError && <p className="mb-4 rounded bg-red-50 p-3 text-sm text-red-700" data-testid="news-error">✗ {firstError}</p>}

            <form onSubmit={submit} className="max-w-3xl space-y-5 rounded-lg border bg-white p-6" data-testid="news-form">
                {input('title', t.pages_col_title || 'Title', { required: true, type: 'text' })}
                {input('slug', t.news_slug || 'Address (optional; made from the title)', { type: 'text', dir: 'ltr', placeholder: 'news-address' })}
                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="news-summary">{t.news_summary || 'Summary (shown on the list and the home page)'}</label>
                    <textarea id="news-summary" name="summary" rows="2" className="form-input w-full" value={form.data.summary} onChange={(e) => form.setData('summary', e.target.value)} />
                </div>
                <div>
                    <p className="mb-1 block text-sm font-medium text-gray-700">{t.pages_content || 'Content'}<span className="text-red-500"> *</span></p>
                    <BodyEditor value={form.data.body} onChange={(html) => form.setData('body', html)} placeholder={t.news_body_placeholder || 'Write the news'} testId="news-body" />
                    {form.errors.body && <p className="mt-1 text-xs text-red-700">{form.errors.body}</p>}
                </div>
                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="news-cover">{t.news_cover || 'Cover image (JPEG, PNG or WebP, up to 5 MB)'}</label>
                    {post?.cover_url && <img src={post.cover_url} alt="" className="mb-2 h-24 rounded object-cover" data-testid="news-cover-current" />}
                    <input id="news-cover" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => form.setData('cover', e.target.files[0] ?? null)} data-testid="news-cover" />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="news-category">{t.news_col_category || 'Category'}</label>
                        <select id="news-category" className="form-input w-full" value={form.data.post_category_id} onChange={(e) => form.setData('post_category_id', e.target.value)}>
                            <option value="">—</option>
                            {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                        </select>
                    </div>
                    {input('tags', t.news_tags || 'Tags (comma-separated)', { type: 'text' })}
                    {input('published_at', t.news_publish_at || 'Publish at (empty: now)', { type: 'datetime-local' })}
                    {input('meta_description', t.news_meta || 'Search description (optional)', { type: 'text', maxLength: 300 })}
                </div>
                <div className="flex flex-wrap gap-4">
                    {check('is_published', t.pages_published || 'Published')}
                    {check('is_featured', t.news_featured || 'Featured')}
                    {check('is_pinned', t.news_pinned || 'Pinned')}
                </div>
                <div className="flex justify-end gap-3">
                    <Link href="/admin/public-site/news" className="btn-secondary">{t.instructors_cancel || 'Cancel'}</Link>
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="news-save">{t.news_save || 'Save news'}</button>
                </div>
            </form>
        </AppShell>
    );
}
