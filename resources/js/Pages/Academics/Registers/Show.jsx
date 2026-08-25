import { Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
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

export default function Show({
    register,
    topics,
    homework,
    materials,
    notes,
    canSubmit,
    attendanceMode = 'per_lesson',
    attendanceStatuses = ['present', 'absent', 'late', 'excused', 'left_early'],
    roster = [],
    marks = [],
}) {
    const { errors } = usePage().props;
    const existing = Object.fromEntries(marks.map((mark) => [String(mark.student_id), mark]));
    const [grid, setGrid] = useState(() => Object.fromEntries(roster.map((student) => [String(student.student_id), {
        student_id: student.student_id,
        status: existing[String(student.student_id)]?.status || 'present',
        minutes_late: existing[String(student.student_id)]?.minutes_late || '',
    }])));
    const form = useForm({
        plan_topic_id: register.plan_topic_id || '',
        taught_summary: register.taught_summary || '',
        homework: homework || '',
        materials: materials || '',
        notes: notes || '',
    });
    const unlock = useForm({ reason: '' });

    return (
        <AppShell title="Class register">
            <p className="mb-4 text-sm text-gray-600">
                <Link href="/academics/registers/today" className="text-[#7C2D37] underline">Today</Link>
                {' · '}
                {register.subject_name} · {register.class_name} · {register.date}
            </p>
            {errors?.status && <p className="mb-3 text-sm text-red-600">{errors.status}</p>}
            {errors?.teacher_id && <p className="mb-3 text-sm text-red-600">{errors.teacher_id}</p>}
            <p className="mb-4">
                <span className="rounded bg-[#F3EBE0] px-2 py-0.5 text-xs uppercase">{register.status}</span>
            </p>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        plan_topic_id: data.plan_topic_id || null,
                        attendance: attendanceMode === 'per_lesson' ? Object.values(grid) : [],
                    }));
                    form.put(`/academics/registers/${register.id}`, { preserveScroll: true });
                }}
                className="mb-6 grid gap-3 rounded-lg border bg-white p-4"
            >
                <Field label="Plan topic" error={form.errors.plan_topic_id}>
                    <select
                        className="form-input w-full"
                        value={form.data.plan_topic_id}
                        onChange={(e) => form.setData('plan_topic_id', e.target.value)}
                        disabled={!canSubmit}
                    >
                        <option value="">Free text / none</option>
                        {topics.map((topic) => (
                            <option key={topic.id} value={topic.id}>
                                {topic.order}. {topic.title}{topic.is_completed ? ' (taught)' : ''}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="What was taught" error={form.errors.taught_summary}>
                    <textarea
                        className="form-input w-full"
                        rows={3}
                        value={form.data.taught_summary}
                        onChange={(e) => form.setData('taught_summary', e.target.value)}
                        disabled={!canSubmit}
                    />
                </Field>
                <Field label="Homework">
                    <textarea
                        className="form-input w-full"
                        rows={2}
                        value={form.data.homework}
                        onChange={(e) => form.setData('homework', e.target.value)}
                        disabled={!canSubmit}
                    />
                </Field>
                <Field label="Materials (comma separated)">
                    <input
                        className="form-input w-full"
                        value={form.data.materials}
                        onChange={(e) => form.setData('materials', e.target.value)}
                        disabled={!canSubmit}
                    />
                </Field>
                <Field label="Notes">
                    <input
                        className="form-input w-full"
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        disabled={!canSubmit}
                    />
                </Field>
                {attendanceMode === 'per_lesson' && roster.length > 0 && (
                    <div>
                        <p className="mb-2 text-sm font-semibold">Attendance</p>
                        {errors?.attendance && <p className="mb-2 text-xs text-red-600">{errors.attendance}</p>}
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-[#F3EBE0] text-left">
                                    <tr>
                                        <th className="px-2 py-1">Student</th>
                                        <th className="px-2 py-1">Status</th>
                                        <th className="px-2 py-1">Minutes late</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {roster.map((student) => {
                                        const key = String(student.student_id);
                                        const row = grid[key] || { student_id: student.student_id, status: 'present', minutes_late: '' };
                                        return (
                                            <tr key={student.student_id} className="border-t">
                                                <td className="px-2 py-1">{student.name}</td>
                                                <td className="px-2 py-1">
                                                    <select
                                                        className="form-input w-full"
                                                        value={row.status}
                                                        disabled={!canSubmit}
                                                        onChange={(e) => setGrid((current) => ({
                                                            ...current,
                                                            [key]: { ...row, status: e.target.value },
                                                        }))}
                                                    >
                                                        {attendanceStatuses.map((status) => <option key={status} value={status}>{status}</option>)}
                                                    </select>
                                                </td>
                                                <td className="px-2 py-1">
                                                    <input
                                                        className="form-input w-20"
                                                        type="number"
                                                        min="0"
                                                        value={row.minutes_late}
                                                        disabled={!canSubmit}
                                                        onChange={(e) => setGrid((current) => ({
                                                            ...current,
                                                            [key]: { ...row, minutes_late: e.target.value },
                                                        }))}
                                                    />
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
                {attendanceMode === 'daily' && (
                    <p className="text-sm text-gray-600">
                        This school marks attendance once per day.
                        {' '}
                        <Link href="/academics/attendance/daily" className="text-[#7C2D37] underline">Open daily attendance</Link>
                    </p>
                )}
                {canSubmit && (
                    <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                        Submit register
                    </button>
                )}
            </form>

            {register.status === 'locked' && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        unlock.post(`/academics/registers/${register.id}/unlock`, { preserveScroll: true });
                    }}
                    className="grid gap-3 rounded-lg border bg-white p-4"
                >
                    <p className="text-sm text-gray-600">Admin unlock (audited). The teacher then has 24 hours to edit.</p>
                    <Field label="Reason" error={unlock.errors.reason}>
                        <input className="form-input w-full" value={unlock.data.reason} onChange={(e) => unlock.setData('reason', e.target.value)} />
                    </Field>
                    <button type="submit" className="btn-secondary justify-self-start">Unlock</button>
                </form>
            )}
        </AppShell>
    );
}
