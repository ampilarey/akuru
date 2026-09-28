import { Link, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * Enrol a pupil in a Hifz programme (the Hifz port, slice 1, STATUS §5jv):
 * the pupil, a teacher (or the programme's default), the start date and
 * the page they are on. A save lands on the programme with its flash.
 */
export default function EnrollmentForm({ program, students = [], teachers = [], today = '', t = {} }) {
    const form = useForm({
        student_id: students[0] ? String(students[0].id) : '',
        teacher_id: '',
        start_date: today,
        current_page: '',
    });
    const submit = (e) => {
        e.preventDefault();
        const clean = (data) => Object.fromEntries(Object.entries(data).map(([key, value]) => [key, value === '' ? null : value]));
        form.transform(clean);
        form.post(`/hifz/programs/${program.id}/enrollments`, { preserveScroll: true });
    };
    const error = (name) => form.errors[name] && <p className="mt-1 text-xs text-red-700">{form.errors[name]}</p>;

    return (
        <AppShell title={`${t.hifz_enroll_title || 'Enroll Student'} — ${program.name}`}>
            <p className="mb-4 text-sm"><Link href={`/hifz/programs/${program.id}`} className="text-gray-500 underline" data-testid="program-back">{t.hifz_back_program || '← Program'}</Link></p>

            <form onSubmit={submit} action={`/hifz/programs/${program.id}/enrollments`} method="post" className="max-w-2xl space-y-4 rounded-lg border bg-white p-6" data-testid="enrollment-form">
                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="enrollment-student">{t.hifz_student || 'Student'}</label>
                    <select id="enrollment-student" name="student_id" className="form-input w-full" required value={form.data.student_id} onChange={(e) => form.setData('student_id', e.target.value)}>
                        {students.map((s) => <option key={s.id} value={String(s.id)}>{s.name}</option>)}
                    </select>
                    {error('student_id')}
                </div>
                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="enrollment-teacher">{t.hifz_teacher || 'Teacher'}</label>
                    <select id="enrollment-teacher" name="teacher_id" className="form-input w-full" value={form.data.teacher_id} onChange={(e) => form.setData('teacher_id', e.target.value)}>
                        <option value="">{t.hifz_teacher_default || 'Default'}</option>
                        {teachers.map((s) => <option key={s.id} value={String(s.id)}>{s.name}</option>)}
                    </select>
                    {error('teacher_id')}
                </div>
                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="enrollment-start">{t.hifz_start_date || 'Start Date'}</label>
                    <input id="enrollment-start" type="date" name="start_date" className="form-input w-full" required value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} />
                    {error('start_date')}
                </div>
                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700" htmlFor="enrollment-page">{t.hifz_current_page || 'Current Page'}</label>
                    <input id="enrollment-page" type="number" name="current_page" min="1" max="604" className="form-input w-full" value={form.data.current_page} onChange={(e) => form.setData('current_page', e.target.value)} />
                    {error('current_page')}
                </div>
                <div className="flex items-center gap-3">
                    <button type="submit" className="btn-primary" disabled={form.processing} data-testid="enrollment-save">{t.hifz_enroll || 'Enroll'}</button>
                </div>
            </form>
        </AppShell>
    );
}
