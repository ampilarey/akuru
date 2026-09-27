import { Link, useForm, usePage } from '@inertiajs/react';
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
 */
export default function CourseForm(props) {
    return <CourseFormBody key={props.course?.id ?? 'new'} {...props} />;
}

function CourseFormBody({ course = null, categories = [], t = {} }) {
    const { flash = {} } = usePage().props;
    const editing = course !== null;
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
        cover_image: course?.cover_image || '',
        status: course?.status || 'open',
        fee: course?.fee ?? '',
        seats: course?.seats ?? '',
    });
    const submit = (e) => {
        e.preventDefault();
        if (editing) form.put(`/admin/public-site/courses/${course.slug}`, { preserveScroll: true });
        else form.post('/admin/public-site/courses', { preserveScroll: true });
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
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="courses-flash">✓ {flash.success}</p>}
            {firstError && <p className="mb-4 rounded bg-red-50 p-3 text-sm text-red-700" data-testid="courses-error">✗ {firstError}</p>}

            <form onSubmit={submit} action={editing ? `/admin/public-site/courses/${course.slug}` : '/admin/public-site/courses'} method="post" className="max-w-3xl space-y-5 rounded-lg border bg-white p-6" data-testid="course-form">
                <div>
                    {label('course_category_id', t.courses_category || 'Category', true)}
                    <select id="course-course_category_id" name="course_category_id" required className={`form-input w-full ${form.errors.course_category_id ? 'border-red-500' : ''}`} value={form.data.course_category_id} onChange={set('course_category_id')}>
                        <option value="">{t.courses_select_category || 'Select Category'}</option>
                        {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                    </select>
                    {error('course_category_id')}
                </div>
                {input('title', t.pages_col_title || 'Title', { required: true, type: 'text' })}
                {input('slug', t.pages_col_slug || 'Slug', { required: true, type: 'text', placeholder: 'url-friendly-slug', dir: 'ltr' })}
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
                    {input('cover_image', t.pages_cover || 'Cover Image URL', { required: true, type: 'text', placeholder: 'e.g. /images/course.jpg', dir: 'ltr' })}
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
