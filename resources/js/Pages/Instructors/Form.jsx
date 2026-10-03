import { Link, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * Add or edit an instructor shown on the public website (C9 slice 3, STATUS
 * §5je). One form for both: the edit carries the current portrait and posts
 * with a spoofed PUT, because a browser cannot send files on a real one.
 */
export default function Form({ instructor = null, staff = [], t = {} }) {
    const editing = instructor !== null;
    const form = useForm({
        user_id: instructor?.user_id ?? '',
        name: instructor?.name || '',
        qualification: instructor?.qualification || '',
        specialization: instructor?.specialization || '',
        email: instructor?.email || '',
        phone: instructor?.phone || '',
        bio: instructor?.bio || '',
        sort_order: instructor?.sort_order ?? 0,
        is_active: editing ? !!instructor.is_active : true,
        photo: null,
        ...(editing ? { _method: 'put' } : {}),
    });
    const submit = (e) => {
        e.preventDefault();
        form.post(editing ? `/admin/instructors/${instructor.id}` : '/admin/instructors', { forceFormData: true });
    };
    const field = (name, label, props = {}) => (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor={`instructor-${name}`}>{label}{props.required && <span className="text-red-500"> *</span>}</label>
            <input id={`instructor-${name}`} name={name} className="form-input w-full" value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} {...props} />
            {form.errors[name] && <p className="mt-1 text-xs text-red-700">{form.errors[name]}</p>}
        </div>
    );
    const firstError = Object.values(form.errors)[0];

    return (
        <AppShell title={editing ? (t.instructors_edit_title || 'Edit instructor') : (t.instructors_new_title || 'Add instructor')}>
            <p className="mb-4 text-sm"><Link href="/admin/instructors" className="text-gray-500 underline" data-testid="instructors-back">{t.instructors_back || '← Instructors'}</Link></p>
            {firstError && <p className="mb-4 rounded bg-red-50 p-3 text-sm text-red-700" data-testid="instructor-error">✗ {firstError}</p>}

            <form onSubmit={submit} className="max-w-2xl rounded-lg border bg-white p-6" data-testid="instructor-form" encType="multipart/form-data">
                <div className="mb-4 grid gap-4 sm:grid-cols-2">
                    <div className="sm:col-span-2">{field('name', t.instructors_field_name || 'Full name', { required: true, type: 'text' })}</div>
                    {field('qualification', t.instructors_field_qualification || 'Qualification', { type: 'text', placeholder: t.instructors_qualification_placeholder || 'e.g. Bachelor of Islamic Studies' })}
                    {field('specialization', t.instructors_field_specialization || 'Specialization', { type: 'text', placeholder: t.instructors_specialization_placeholder || 'e.g. Quran Memorization' })}
                    {field('email', t.instructors_field_email || 'Email', { type: 'email' })}
                    {field('phone', t.instructors_field_phone || 'Phone', { type: 'text' })}
                </div>

                <div className="mb-4">
                    <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="instructor-bio">{t.instructors_field_bio || 'Bio'}</label>
                    <textarea id="instructor-bio" name="bio" rows="4" className="form-input w-full" value={form.data.bio} onChange={(e) => form.setData('bio', e.target.value)} />
                </div>

                <div className="mb-4">
                    <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="instructor-photo">{t.instructors_field_photo || 'Photo'}</label>
                    {editing && instructor.photo_url && <img src={instructor.photo_url} alt="" className="mb-2 h-20 w-20 rounded-full object-cover" data-testid="instructor-current-photo" />}
                    <input id="instructor-photo" type="file" name="photo" accept="image/*" className="block text-sm text-gray-600" onChange={(e) => form.setData('photo', e.target.files[0] || null)} data-testid="instructor-photo-input" />
                    <p className="mt-1 text-xs text-gray-500">{t.instructors_photo_hint || 'Max 2MB. JPEG or PNG.'}</p>
                    {form.errors.photo && <p className="mt-1 text-xs text-red-700">{form.errors.photo}</p>}
                </div>

                {/* The staff login this profile belongs to (C16 slice N6): with the courses assigned on the course form, it is what opens a teacher's review queue to their own courses. */}
                <div className="mb-4">
                    <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="instructor-user_id">{t.instructors_field_user || 'Staff login'}</label>
                    <select id="instructor-user_id" name="user_id" className="form-input w-full" value={form.data.user_id} onChange={(e) => form.setData('user_id', e.target.value)} data-testid="instructor-user">
                        <option value="">{t.instructors_user_none || '— Not linked —'}</option>
                        {staff.map((person) => <option key={person.id} value={person.id}>{person.name}{person.email ? ` · ${person.email}` : ''}</option>)}
                    </select>
                    <p className="mt-1 text-xs text-gray-500">{t.instructors_user_hint || 'Link this profile to the person’s sign-in. A teacher marks the submissions of the courses this profile is assigned to on the course form.'}</p>
                    {form.errors.user_id && <p className="mt-1 text-xs text-red-700">{form.errors.user_id}</p>}
                </div>

                <div className="mb-6 grid gap-4 sm:grid-cols-2">
                    {field('sort_order', t.instructors_field_sort_order || 'Sort order', { type: 'number', min: 0 })}
                    <label className="flex items-center gap-2 pt-6 text-sm font-medium text-gray-700">
                        <input type="checkbox" name="is_active" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} className="rounded border-gray-300" data-testid="instructor-active" />
                        {t.instructors_field_active || 'Active (show publicly)'}
                    </label>
                </div>

                <div className="flex gap-3">
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="instructor-save">{editing ? (t.instructors_save || 'Save changes') : (t.instructors_create || 'Create instructor')}</button>
                    <Link href="/admin/instructors" className="btn-secondary">{t.instructors_cancel || 'Cancel'}</Link>
                </div>
            </form>
        </AppShell>
    );
}
