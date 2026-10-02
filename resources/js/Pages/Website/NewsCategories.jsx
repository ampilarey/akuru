import { Link, router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * RESEARCH_ARTICLES_PLAN R4: the news categories — name, address, order and
 * whether they are offered on the news form.
 */
export default function NewsCategories({ categories = [], t = {} }) {
    const { errors = {} } = usePage().props;
    const form = useForm({ name: '', sort_order: '' });
    const toggle = (category) => router.put(`/admin/public-site/news/categories/${category.id}`, { name: category.name, slug: category.slug, sort_order: category.sort_order, is_active: category.is_active ? 0 : 1 }, { preserveScroll: true });

    return (
        <AppShell title={t.news_categories || 'Categories'}>
            <p className="mb-4 text-sm"><Link href="/admin/public-site/news" className="text-gray-500 underline">{t.news_back || '← Back to News'}</Link></p>
            <FormErrors errors={errors} className="mb-4" />
            <form onSubmit={(e) => { e.preventDefault(); form.post('/admin/public-site/news/categories', { preserveScroll: true, onSuccess: () => form.reset() }); }} className="mb-4 flex flex-wrap gap-2" data-testid="news-category-add">
                <input className="form-input" placeholder={t.news_category_name || 'Category name'} aria-label={t.news_category_name || 'Category name'} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <input className="form-input w-24" type="number" min="0" placeholder={t.news_category_order || 'Order'} aria-label={t.news_category_order || 'Order'} value={form.data.sort_order} onChange={(e) => form.setData('sort_order', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.news_category_add || 'Add category'}</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.news_category_name || 'Category name'}</th>
                            <th className="px-3 py-2">{t.pages_col_slug || 'Slug'}</th>
                            <th className="px-3 py-2">{t.news_category_order || 'Order'}</th>
                            <th className="px-3 py-2">{t.news_category_posts || 'News items'}</th>
                            <th className="px-3 py-2"><span className="sr-only">{t.pages_col_actions || 'Actions'}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {categories.length === 0 && <tr><td colSpan="5" className="px-3 py-4 text-gray-500">{t.news_category_none || 'No categories yet.'}</td></tr>}
                        {categories.map((category) => (
                            <tr key={category.id} className="border-t" data-testid="news-category-row">
                                <td className="px-3 py-2">{category.name}{!category.is_active && <span className="ms-2 text-xs text-gray-500">({t.news_category_hidden || 'hidden'})</span>}</td>
                                <td className="px-3 py-2"><code className="text-xs" dir="ltr">{category.slug}</code></td>
                                <td className="px-3 py-2">{category.sort_order}</td>
                                <td className="px-3 py-2">{category.posts}</td>
                                <td className="px-3 py-2 text-end"><button type="button" className="text-xs underline" onClick={() => toggle(category)}>{category.is_active ? (t.news_category_hide || 'Hide') : (t.news_category_show || 'Offer again')}</button></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
