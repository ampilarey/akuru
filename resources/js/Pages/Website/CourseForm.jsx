import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';

const LANGUAGES = ['en', 'ar', 'dv', 'mixed'];
const LEVELS = ['kids', 'youth', 'adult', 'all'];
const STATUSES = ['open', 'closed', 'upcoming'];

/**
 * Create or edit a website course (C9 slice 11, STATUS §5jm): the catalogue
 * fields, the trilingual learning outcomes one per line, the WhatsApp and
 * syllabus CTA. The body is authored HTML, sanitised on write by the
 * controller; the form is keyed on the course so new and edit never share
 * state (STATUS §5jj).
 *
 * BACKLOG C16 slice N2, from the owner's walk of the live form ("Slug *
 * what is this?", "Cover Image URL *"): the address is filled from the
 * title as it is typed and can be left alone; the cover is an upload with
 * the current one shown; the category field says when there are none and
 * links to the screen that adds them.
 */
const slugify = (text) => text.toString().normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);

export default function CourseForm(props) {
    return <CourseFormBody key={props.course?.id ?? 'new'} {...props} />;
}

function CourseFormBody({ course = null, categories = [], t = {} }) {
    const editing = course !== null;
    // The address follows the title until the office types one of its own.
    const [slugTouched, setSlugTouched] = useState(editing);
    const form = useForm({
        course_category_id: course?.course_category_id ?? '',
        title: course?.title || '',
        slug: course?.slug || '',
        short_desc: course?.short_desc || '',
        body: course?.body || '',
        learning_outcomes_en: course?.learning_outcomes_en || '',
        learning_outcomes_dv: course?.learning_outcomes_dv || '',
        learning_outcomes_ar: course?.learning_outcomes_ar || '',
        whatsapp_number: course?.whatsapp_number || '',
        syllabus_media_file_id: course?.syllabus_media_file_id ?? '',
        language: course?.language || 'en',
        level: course?.level || 'kids',
        cover: null,
        status: course?.status || 'open',
        fee: course?.fee ?? '',
        seats: course?.seats ?? '',
    });
    const submit = (e) => {
        e.preventDefault();
        // A file upload cannot travel in a PUT: the update is a POST with the method spoofed (the news editor's way).
        form.transform((data) => ({ ...data, ...(editing ? { _method: 'put' } : {}) }));
        form.post(editing ? `/admin/public-site/courses/${course.slug}` : '/admin/public-site/courses', { preserveScroll: true, forceFormData: true });
    };
    const setTitle = (e) => {
        const title = e.target.value;
        form.setData((data) => ({ ...data, title, ...(slugTouched ? {} : { slug: slugify(title) }) }));
    };
    const firstError = Object.values(form.errors)[0];
    const set = (name) => (e) => form.setData(name, e.target.value);
    const label = (name, text, required = false) => (
        <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor={`course-${name}`}>{text}{required && <span className="text-red-500"> *</span>}</label>
    );
    const error = (name) => form.errors[name] && <p className="mt-1 text-xs text-red-700">{form.errors[name]}</p>;
    const input = (name, text, props = {}) => (
        <div>
            {label(name, text, props.required)}
            <input id={`course-${name}`} name={name} className={`form-input w-full ${form.errors[name] ? 'border-red-500' : ''}`} value={form.data[name]} onChange={set(name)} {...props} />
            {error(name)}
        </div>
    );
    const select = (name, text, options, labels) => (
        <div>
            {label(name, text, true)}
            <select id={`course-${name}`} name={name} required className={`form-input w-full ${form.errors[name] ? 'border-red-500' : ''}`} value={form.data[name]} onChange={set(name)}>
                {options.map((value) => <option key={value} value={value}>{labels(value)}</option>)}
            </select>
            {error(name)}
        </div>
    );
    const textarea = (name, text, props = {}) => (
        <div>
            {label(name, text, props.required)}
            <textarea id={`course-${name}`} name={name} className={`form-input w-full ${form.errors[name] ? 'border-red-500' : ''}`} value={form.data[name]} onChange={set(name)} {...props} />
            {error(name)}
        </div>
    );

    return (
        <AppShell title={editing ? (t.courses_edit_title || 'Edit Course: :title').replace(':title', course.title) : (t.courses_new_title || 'Create New Course')}>
            <p className="mb-4 text-sm"><Link href="/admin/public-site/courses" className="text-gray-500 underline" data-testid="courses-back">{t.courses_back || '← Back to Courses'}</Link></p>
            {firstError && <p className="mb-4 rounded bg-red-50 p-3 text-sm text-red-700" data-testid="courses-error">✗ {firstError}</p>}

            <form onSubmit={submit} action={editing ? `/admin/public-site/courses/${course.slug}` : '/admin/public-site/courses'} method="post" className="max-w-3xl space-y-5 rounded-lg border bg-white p-6" data-testid="course-form">
                <div>
                    <div className="mb-1 flex items-baseline justify-between gap-3">
                        {label('course_category_id', t.courses_category || 'Category', true)}
                        <Link href="/admin/public-site/courses/categories" className="text-xs text-[#1D4E89] underline" data-testid="course-manage-categories">{t.courses_manage_categories || 'Manage categories →'}</Link>
                    </div>
                    <select id="course-course_category_id" name="course_category_id" required className={`form-input w-full ${form.errors.course_category_id ? 'border-red-500' : ''}`} value={form.data.course_category_id} onChange={set('course_category_id')}>
                        <option value="">{t.courses_select_category || 'Select Category'}</option>
                        {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                    </select>
                    {categories.length === 0 && <p className="mt-1 text-xs text-amber-800" data-testid="course-no-categories">{t.courses_no_categories || 'No categories yet — add one first.'}</p>}
                    {error('course_category_id')}
                </div>
                <div>
                    {label('title', t.pages_col_title || 'Title', true)}
                    <input id="course-title" name="title" type="text" required className={`form-input w-full ${form.errors.title ? 'border-red-500' : ''}`} value={form.data.title} onChange={setTitle} />
                    {error('title')}
                </div>
                <div>
                    {label('slug', t.courses_slug_label || 'Web address')}
                    <input id="course-slug" name="slug" type="text" dir="ltr" className={`form-input w-full ${form.errors.slug ? 'border-red-500' : ''}`} value={form.data.slug} onChange={(e) => { setSlugTouched(true); form.setData('slug', e.target.value); }} data-testid="course-slug" />
                    <p className="mt-1 text-xs text-gray-500">{(t.courses_slug_hint || 'Filled from the title. The course page will be akuru.edu.mv/courses/:slug').replace(':slug', form.data.slug || '…')}</p>
                    {error('slug')}
                </div>
                {textarea('short_desc', t.courses_short_desc || 'Short Description', { required: true, rows: 2 })}
                <div>
                    {textarea('body', t.pages_content || 'Content', { required: true, rows: 8, dir: 'ltr' })}
                    <p className="mt-1 text-xs text-gray-500">{t.pages_content_hint || 'HTML. Scripts and unsafe markup are removed on save.'}</p>
                </div>

                <fieldset className="space-y-3 rounded-xl border border-gray-200 p-4" data-testid="course-outcomes">
                    <legend className="px-1 text-sm font-semibold text-gray-900">{t.courses_outcomes || 'Learning outcomes'}</legend>
                    <p className="text-xs text-gray-500">{t.courses_outcomes_hint || 'One outcome per line. Shown as “What you\'ll be able to do” on the public course page. Empty locales fall back to English.'}</p>
                    {textarea('learning_outcomes_en', t.courses_lang_en || 'English', { rows: 4 })}
                    {textarea('learning_outcomes_dv', t.courses_lang_dv || 'Dhivehi', { rows: 4, dir: 'rtl' })}
                    {textarea('learning_outcomes_ar', t.courses_lang_ar || 'Arabic', { rows: 4, dir: 'rtl' })}
                </fieldset>

                <div className="grid grid-cols-1 gap-5 md:grid-cols-2" data-testid="course-cta">
                    <div>
                        {input('whatsapp_number', t.courses_whatsapp || 'WhatsApp number', { type: 'text', maxLength: 32, dir: 'ltr', placeholder: t.courses_whatsapp_placeholder || 'Leave blank to use the Settings default' })}
                        <p className="mt-1 text-xs text-gray-500">{t.courses_whatsapp_hint || 'Digits with country code, e.g. 9607972434. Blank uses conversion.whatsapp_number, then the Viber contact number.'}</p>
                    </div>
                    <div>
                        {input('syllabus_media_file_id', t.courses_syllabus || 'Syllabus media file id', { type: 'number', min: 1, dir: 'ltr' })}
                        <p className="mt-1 text-xs text-gray-500">{t.courses_syllabus_hint || 'Public media_files id. Empty hides “Get full syllabus”.'}</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                    {select('language', t.courses_language || 'Language', LANGUAGES, (value) => t[`courses_lang_${value}`] || value)}
                    {select('level', t.courses_level || 'Level', LEVELS, (value) => t[`courses_level_${value}`] || value)}
                </div>
                <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        {label('cover', t.courses_cover || 'Cover image (JPEG, PNG or WebP, up to 5 MB)', !editing && !course?.cover_url)}
                        {course?.cover_url && <img src={course.cover_url} alt="" className="mb-2 h-24 rounded object-cover" data-testid="course-cover-current" />}
                        <input id="course-cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => form.setData('cover', e.target.files[0] ?? null)} data-testid="course-cover" />
                        <p className="mt-1 text-xs text-gray-500">{t.courses_cover_hint || 'Shown on the course page, the courses list and the home page.'}</p>
                        {error('cover')}
                    </div>
                    {select('status', t.pages_col_status || 'Status', STATUSES, (value) => t[`courses_status_${value}`] || value)}
                </div>
                <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                    {input('fee', t.courses_fee || 'Fee (MVR)', { type: 'number', min: 0, step: '0.01', dir: 'ltr' })}
                    {input('seats', t.courses_seats || 'Seats', { type: 'number', min: 1, dir: 'ltr' })}
                </div>

                <div className="flex justify-end gap-3">
                    <Link href="/admin/public-site/courses" className="btn-secondary">{t.instructors_cancel || 'Cancel'}</Link>
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="course-save">{editing ? (t.courses_update || 'Update Course') : (t.courses_create || 'Create Course')}</button>
                </div>
            </form>
        </AppShell>
    );
}
