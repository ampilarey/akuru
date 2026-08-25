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

export default function Index({ plans, teacherId, canManage, years, classes, subjects, statuses }) {
    const form = useForm({
        title: '',
        teacher_id: teacherId || '',
        subject_id: subjects[0]?.id || '',
        classroom_id: classes[0]?.id || '',
        academic_year_id: years[0]?.id || '',
        status: 'active',
    });

    return (
        <AppShell title="Teaching plans">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/academics/plans', { preserveScroll: true });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <Field label="Title" error={form.errors.title}>
                    <input className="form-input w-full" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </Field>
                <Field label="Subject">
                    <select className="form-input w-full" value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                        {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                    </select>
                </Field>
                <Field label="Class">
                    <select className="form-input w-full" value={form.data.classroom_id} onChange={(e) => form.setData('classroom_id', e.target.value)}>
                        {classes.map((item) => <option key={item.id} value={item.id}>{item.name} {item.section}</option>)}
                    </select>
                </Field>
                <Field label="Year">
                    <select className="form-input w-full" value={form.data.academic_year_id} onChange={(e) => form.setData('academic_year_id', e.target.value)}>
                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                    </select>
                </Field>
                {canManage && (
                    <Field label="Teacher id">
                        <input className="form-input w-full" value={form.data.teacher_id} onChange={(e) => form.setData('teacher_id', e.target.value)} />
                    </Field>
                )}
                <Field label="Status">
                    <select className="form-input w-full" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                        {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                    </select>
                </Field>
                <div className="flex items-end">
                    <button type="submit" className="btn-primary">Create plan</button>
                </div>
            </form>

            <div className="grid gap-4">
                {plans.map((plan) => (
                    <PlanCard key={plan.id} plan={plan} classes={classes} years={years} />
                ))}
            </div>
        </AppShell>
    );
}

function PlanCard({ plan, classes, years }) {
    const topic = useForm({ title: '' });
    const copy = useForm({
        classroom_id: plan.classroom_id,
        academic_year_id: plan.academic_year_id || '',
    });

    return (
        <section className="rounded-lg border bg-white p-4">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="font-semibold">{plan.title}</h2>
                <span className="text-xs uppercase text-gray-500">{plan.status} · {plan.academic_year}</span>
            </div>
            <ul className="mb-3 list-disc pl-5 text-sm">
                {plan.topics.map((item) => (
                    <li key={item.id}>{item.title}{item.is_completed ? ' — taught' : ''}</li>
                ))}
            </ul>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    topic.post(`/academics/plans/${plan.id}/topics`, { preserveScroll: true });
                }}
                className="mb-3 flex flex-wrap gap-2"
            >
                <input className="form-input" placeholder="New topic" value={topic.data.title} onChange={(e) => topic.setData('title', e.target.value)} />
                <button type="submit" className="btn-secondary">Add topic</button>
            </form>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    copy.post(`/academics/plans/${plan.id}/copy`, { preserveScroll: true });
                }}
                className="flex flex-wrap gap-2"
            >
                <select className="form-input" value={copy.data.classroom_id} onChange={(e) => copy.setData('classroom_id', e.target.value)}>
                    {classes.map((item) => <option key={item.id} value={item.id}>{item.name} {item.section}</option>)}
                </select>
                <select className="form-input" value={copy.data.academic_year_id} onChange={(e) => copy.setData('academic_year_id', e.target.value)}>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                <button type="submit" className="btn-secondary">Copy plan</button>
            </form>
        </section>
    );
}
