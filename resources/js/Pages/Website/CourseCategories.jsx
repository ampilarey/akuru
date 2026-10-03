import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * BACKLOG C16 slice N2: the website's course categories — name, address and
 * order; delete when no course uses one. The owner, on the live site: "there
 * is no cat" — the seeder had never run there and no screen existed.
 */
export default function CourseCategories({ categories = [], t = {} }) {
    const { errors = {} } = usePage().props;
    const form = useForm({ name: '', order: '' });

    return (
        <AppShell title={t.courses_categories_title || 'Course categories'}>
            <p className="mb-4 text-sm"><Link href="/admin/public-site/courses" className="text-gray-500 underline" data-testid="categories-back">{t.courses_back || '← Back to Courses'}</Link></p>
            <p className="mb-4 max-w-2xl text-sm text-gray-600">{t.courses_categories_intro || 'The groups the public courses page is sorted into. A category can be deleted only when no course uses it.'}</p>
            <FormErrors errors={errors} className="mb-4" />
            <form onSubmit={(e) => { e.preventDefault(); form.post('/admin/public-site/courses/categories', { preserveScroll: true, onSuccess: () => form.reset() }); }} className="mb-4 flex flex-wrap gap-2" data-testid="course-category-add">
                <input className="form-input" placeholder={t.courses_category_name || 'Category name'} aria-label={t.courses_category_name || 'Category name'} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} data-testid="course-category-name" />
                <input className="form-input w-24" type="number" min="0" placeholder={t.courses_category_order || 'Order'} aria-label={t.courses_category_order || 'Order'} value={form.data.order} onChange={(e) => form.setData('order', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={form.processing} data-testid="course-category-submit">{t.courses_category_add || 'Add category'}</button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.courses_category_name || 'Category name'}</th>
                            <th className="px-3 py-2">{t.pages_col_slug || 'Slug'}</th>
                            <th className="px-3 py-2">{t.courses_category_order || 'Order'}</th>
                            <th className="px-3 py-2">{t.courses_category_courses || 'Courses'}</th>
                            <th className="px-3 py-2"><span className="sr-only">{t.pages_col_actions || 'Actions'}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {categories.length === 0 && <tr><td colSpan="5" className="px-3 py-4 text-gray-500">{t.courses_category_none || 'No categories yet.'}</td></tr>}
                        {categories.map((category) => <Row key={category.id} category={category} t={t} />)}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}

function Row({ category, t }) {
    const [editing, setEditing] = useState(false);
    const [name, setName] = useState(category.name);
    const [order, setOrder] = useState(String(category.order));
    const save = () => router.put(`/admin/public-site/courses/categories/${category.id}`, { name, order }, { preserveScroll: true, onSuccess: () => setEditing(false) });
    const remove = () => {
        if (!window.confirm((t.courses_category_delete_confirm || 'Delete the category ":name"?').replace(':name', category.name))) return;
        router.delete(`/admin/public-site/courses/categories/${category.id}`, { preserveScroll: true });
    };

    return (
        <tr className="border-t" data-testid="course-category-row">
            <td data-label={t.courses_category_name || 'Category name'} className="px-3 py-2">
                {editing
                    ? <input className="form-input" value={name} onChange={(e) => setName(e.target.value)} aria-label={t.courses_category_name || 'Category name'} />
                    : category.name}
            </td>
            <td data-label={t.pages_col_slug || 'Slug'} className="px-3 py-2"><code className="text-xs" dir="ltr">{category.slug}</code></td>
            <td data-label={t.courses_category_order || 'Order'} className="px-3 py-2">
                {editing
                    ? <input className="form-input w-20" type="number" min="0" value={order} onChange={(e) => setOrder(e.target.value)} aria-label={t.courses_category_order || 'Order'} />
                    : category.order}
            </td>
            <td data-label={t.courses_category_courses || 'Courses'} className="px-3 py-2">{category.courses}</td>
            <td className="table-actions whitespace-nowrap px-3 py-2 text-end">
                {editing ? (
                    <>
                        <button type="button" className="me-3 text-xs font-semibold text-[#1D4E89] underline" onClick={save} data-testid="course-category-save">{t.courses_category_save || 'Save'}</button>
                        <button type="button" className="text-xs underline" onClick={() => { setEditing(false); setName(category.name); setOrder(String(category.order)); }}>{t.instructors_cancel || 'Cancel'}</button>
                    </>
                ) : (
                    <>
                        <button type="button" className="me-3 text-xs font-semibold text-[#1D4E89] underline" onClick={() => setEditing(true)} data-testid="course-category-edit">{t.research_edit || 'Edit'}</button>
                        {category.courses === 0 && (
                            <button type="button" className="text-xs font-semibold text-red-700 underline" onClick={remove} data-testid="course-category-delete">{t.courses_category_delete || 'Delete'}</button>
                        )}
                    </>
                )}
            </td>
        </tr>
    );
}
