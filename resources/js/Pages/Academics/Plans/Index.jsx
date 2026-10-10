import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

function Field({ label, error, children }) {
    return (
        <label className="block text-sm">
            <span className="mb-1 block text-gray-600">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}

export default function Index({ plans, teacherId, canManage, years, classes, subjects, statuses, t = {} }) {
    const form = useForm({
        title: '',
        teacher_id: teacherId || '',
        subject_id: subjects[0]?.id || '',
        classroom_id: classes[0]?.id || '',
        academic_year_id: years[0]?.id || '',
        status: 'active',
    });
    // In the page's language (BACKLOG C21, slice OA3); a plan's state is a
    // code, named here.
    const statusName = (status) => t[`plan_status_${status}`] || status;

    return (
        <AppShell title={t.plans_title || 'Teaching plans'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/academics/plans/export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/academics/plans', { preserveScroll: true });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <Field label={t.title || 'Title'} error={form.errors.title}>
                    <input className="form-input w-full" aria-label={t.title || 'Title'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </Field>
                <Field label={t.subject || 'Subject'} error={form.errors.subject_id}>
                    <select className="form-input w-full" aria-label={t.subject || 'Subject'} value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                        {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                    </select>
                </Field>
                <Field label={t.class || 'Class'} error={form.errors.classroom_id}>
                    <select className="form-input w-full" aria-label={t.class || 'Class'} value={form.data.classroom_id} onChange={(e) => form.setData('classroom_id', e.target.value)}>
                        {classes.map((item) => <option key={item.id} value={item.id}>{item.name} {item.section}</option>)}
                    </select>
                </Field>
                <Field label={t.year || 'Year'} error={form.errors.academic_year_id}>
                    <select className="form-input w-full" aria-label={t.year || 'Year'} value={form.data.academic_year_id} onChange={(e) => form.setData('academic_year_id', e.target.value)}>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                </Field>
                {canManage && (
                    <Field label={t.plans_teacher_id || 'Teacher id'} error={form.errors.teacher_id}>
                        <input className="form-input w-full" aria-label={t.plans_teacher_id || 'Teacher id'} value={form.data.teacher_id} onChange={(e) => form.setData('teacher_id', e.target.value)} />
                    </Field>
                )}
                <Field label={t.status || 'Status'} error={form.errors.status}>
                    <select className="form-input w-full" aria-label={t.status || 'Status'} value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                        {statuses.map((status) => <option key={status} value={status}>{statusName(status)}</option>)}
                    </select>
                </Field>
                <div className="flex items-end">
                    <button type="submit" className="btn-primary">{t.plans_create || 'Create plan'}</button>
                </div>
            </form>

            {plans.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">{t.plans_none || 'No teaching plans yet.'}</p>
            )}
            <div className="grid gap-4">
                {plans.map((plan) => (
                    <PlanCard key={plan.id} plan={plan} classes={classes} years={years} statusName={statusName} t={t} />
                ))}
            </div>
        </AppShell>
    );
}

function PlanCard({ plan, classes, years, statusName, t }) {
    const topic = useForm({ title: '' });
    const copy = useForm({
        classroom_id: plan.classroom_id,
        academic_year_id: plan.academic_year_id || '',
    });
    // Each box says which plan it belongs to.
    const label = (column) => `${column}: ${plan.title}`;

    return (
        <section className="rounded-lg border bg-white p-4">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="font-semibold">{plan.title}</h2>
                <span className="text-xs uppercase text-gray-500">{statusName(plan.status)} · {plan.academic_year}</span>
            </div>
            <ul className="mb-3 list-disc ps-5 text-sm">
                {plan.topics.map((item) => (
                    <li key={item.id}>{item.title}{item.is_completed ? ` — ${t.plans_taught || 'taught'}` : ''}</li>
                ))}
            </ul>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    topic.post(`/academics/plans/${plan.id}/topics`, { preserveScroll: true });
                }}
                className="mb-3 flex flex-wrap gap-2"
            >
                <input className="form-input" placeholder={t.plans_new_topic || 'New topic'} aria-label={label(t.plans_new_topic || 'New topic')} value={topic.data.title} onChange={(e) => topic.setData('title', e.target.value)} />
                <button type="submit" className="btn-secondary">{t.plans_add_topic || 'Add topic'}</button>
                {topic.errors.title && <span className="w-full text-xs text-red-600">{topic.errors.title}</span>}
            </form>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    copy.post(`/academics/plans/${plan.id}/copy`, { preserveScroll: true });
                }}
                className="flex flex-wrap gap-2"
            >
                <select className="form-input" aria-label={label(t.plans_copy_class || 'Copy to class')} value={copy.data.classroom_id} onChange={(e) => copy.setData('classroom_id', e.target.value)}>
                    {classes.map((item) => <option key={item.id} value={item.id}>{item.name} {item.section}</option>)}
                </select>
                <select className="form-input" aria-label={label(t.plans_copy_year || 'Copy to year')} value={copy.data.academic_year_id} onChange={(e) => copy.setData('academic_year_id', e.target.value)}>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <button type="submit" className="btn-secondary">{t.plans_copy || 'Copy plan'}</button>
                {(copy.errors.classroom_id || copy.errors.academic_year_id) && (
                    <span className="w-full text-xs text-red-600">{copy.errors.classroom_id || copy.errors.academic_year_id}</span>
                )}
            </form>
        </section>
    );
}
