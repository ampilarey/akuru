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
    homeworkDueDate = null,
    nextLessonDate = null,
    materials,
    attachedMaterials = [],
    homeworkMaterials = [],
    materialLibrary = [],
    notes,
    canSubmit,
    attendanceMode = 'per_lesson',
    // No 'excused': that comes from approving a guardian's note, and the
    // writer rejects one that does not (KNOWN_ISSUES #15).
    attendanceStatuses = ['present', 'absent', 'late', 'left_early'],
    roster = [],
    marks = [],
    t = {},
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
        // An unset due date defaults to the next time this class meets this
        // subject — never "tomorrow", which lands on days with no lesson.
        homework_due_date: homeworkDueDate || nextLessonDate || '',
        materials: materials || '',
        // The structured library (E13a). The free-text field above is the
        // legacy one and still shown, so old registers keep reading correctly.
        material_ids: attachedMaterials.map((id) => String(id)),
        // Which of them the family sees on the homework (E13b).
        homework_material_ids: homeworkMaterials.map((id) => String(id)),
        notes: notes || '',
    });
    const unlock = useForm({ reason: '' });
    // What the register will say was taught. When a topic is picked the title
    // is the answer, so the box below stops being the place to write it
    // (KNOWN_ISSUES #16) — it asks for what the title leaves out instead.
    const chosenTopicId = String(form.data.plan_topic_id);
    const chosenTopic = topics.find((topic) => `${topic.id}` === chosenTopicId) || null;
    const taughtLabel = chosenTopic
        ? (t.register_left_out || 'Anything the topic title leaves out (optional)')
        : (t.register_what_taught || 'What was taught');
    // In the page's language (BACKLOG C21, slice OA1); a register's and a
    // mark's state are codes, named here.
    const col = {
        student: t.col_student || 'Student',
        number: t.col_number || 'Number',
        dob: t.col_date_of_birth || 'Date of birth',
        status: t.col_status || 'Status',
        minutesLate: t.col_minutes_late || 'Minutes late',
    };

    return (
        <AppShell title={t.register_title || 'Class register'}>
            <p className="mb-4 text-sm text-gray-600">
                <Link href="/academics/registers/today" className="text-[#7C2D37] underline">{t.register_today || 'Today'}</Link>
                {' · '}
                {register.subject_name} · {register.class_name} · {register.date}
            </p>
            {errors?.status && <p className="mb-3 text-sm text-red-600">{errors.status}</p>}
            {errors?.teacher_id && <p className="mb-3 text-sm text-red-600">{errors.teacher_id}</p>}
            <p className="mb-4">
                <span className="rounded bg-[#F3EBE0] px-2 py-0.5 text-xs uppercase">{t[`register_status_${register.status}`] || register.status}</span>
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
                <Field label={t.register_plan_topic || 'Plan topic'} error={form.errors.plan_topic_id}>
                    <select
                        className="form-input w-full"
                        aria-label={t.register_plan_topic || 'Plan topic'}
                        value={form.data.plan_topic_id}
                        onChange={(e) => form.setData('plan_topic_id', e.target.value)}
                        disabled={!canSubmit}
                    >
                        <option value="">{t.register_free_text || 'Free text / none'}</option>
                        {topics.map((topic) => (
                            <option key={topic.id} value={topic.id}>
                                {topic.order}. {topic.is_completed ? (t.register_topic_taught || ':title (taught)').replace(':title', topic.title) : topic.title}
                            </option>
                        ))}
                    </select>
                    {chosenTopic && (
                        <span className="mt-1 block text-xs text-gray-500">
                            {(t.register_will_read || 'This register will read “:title”, and submitting marks that topic taught on the plan.').replace(':title', chosenTopic.title)}
                        </span>
                    )}
                </Field>
                <Field label={taughtLabel} error={form.errors.taught_summary}>
                    <textarea
                        className="form-input w-full"
                        aria-label={taughtLabel}
                        rows={3}
                        value={form.data.taught_summary}
                        onChange={(e) => form.setData('taught_summary', e.target.value)}
                        placeholder={chosenTopic
                            ? (t.register_left_out_hint || 'Leave blank unless the lesson went somewhere the title does not cover.')
                            : ''}
                        disabled={!canSubmit}
                    />
                    {chosenTopic && (
                        <span className="mt-1 block text-xs text-gray-500">
                            {(t.register_no_repeat || 'No need to type “:title” again — a note that only repeats the title is dropped.').replace(':title', chosenTopic.title)}
                        </span>
                    )}
                </Field>
                <Field label={t.register_homework || 'Homework'}>
                    <textarea
                        className="form-input w-full"
                        aria-label={t.register_homework || 'Homework'}
                        rows={2}
                        value={form.data.homework}
                        onChange={(e) => form.setData('homework', e.target.value)}
                        disabled={!canSubmit}
                    />
                </Field>
                <Field label={t.register_homework_due || 'Homework due'} error={errors.homework_due_date}>
                    <input
                        className="form-input w-full"
                        type="date"
                        aria-label={t.register_homework_due || 'Homework due'}
                        value={form.data.homework_due_date}
                        onChange={(e) => form.setData('homework_due_date', e.target.value)}
                        disabled={!canSubmit || !form.data.homework}
                    />
                    <span className="mt-1 block text-xs text-gray-500">
                        {nextLessonDate
                            ? (t.register_due_default || 'Defaults to the next lesson for this class (:date).').replace(':date', nextLessonDate)
                            : (t.register_no_next || 'No further lesson found for this class in the next three weeks.')}
                    </span>
                </Field>
                <Field label={t.register_materials_text || 'Materials (comma separated)'}>
                    <input
                        className="form-input w-full"
                        aria-label={t.register_materials_text || 'Materials (comma separated)'}
                        value={form.data.materials}
                        onChange={(e) => form.setData('materials', e.target.value)}
                        disabled={!canSubmit}
                    />
                </Field>
                <div>
                    <p className="mb-2 text-sm text-gray-600">
                        {t.register_from_library || 'From the library'}
                        {' · '}
                        <Link href="/academics/materials" className="text-[#7C2D37] underline">{t.register_manage_materials || 'Manage materials'}</Link>
                    </p>
                    {materialLibrary.length === 0 ? (
                        <p className="text-xs text-gray-500">
                            {t.register_library_empty || 'Nothing saved for this subject yet. Write a material once and it is reusable in every lesson.'}
                        </p>
                    ) : (
                        <div className="grid gap-1 md:grid-cols-2">
                            {materialLibrary.map((material) => {
                                const id = String(material.id);
                                const checked = form.data.material_ids.includes(id);
                                const sentHome = form.data.homework_material_ids.includes(id);

                                return (
                                    <div key={material.id} className="text-sm">
                                        <label className="flex items-start gap-2">
                                            <input
                                                type="checkbox"
                                                className="mt-1"
                                                checked={checked}
                                                disabled={!canSubmit}
                                                onChange={(e) => {
                                                    form.setData(
                                                        'material_ids',
                                                        e.target.checked
                                                            ? [...form.data.material_ids, id]
                                                            : form.data.material_ids.filter((value) => value !== id),
                                                    );
                                                    // Un-attaching also stops it going home; a
                                                    // family should never be sent a material the
                                                    // lesson no longer uses.
                                                    if (!e.target.checked) {
                                                        form.setData(
                                                            'homework_material_ids',
                                                            form.data.homework_material_ids.filter((value) => value !== id),
                                                        );
                                                    }
                                                }}
                                            />
                                            <span>
                                                {material.title}
                                                {material.tags.length > 0 && (
                                                    <span className="text-xs text-gray-500"> · {material.tags.join(', ')}</span>
                                                )}
                                            </span>
                                        </label>
                                        {checked && (
                                            <label className="ms-6 flex items-center gap-2 text-xs text-gray-600">
                                                <input
                                                    type="checkbox"
                                                    checked={sentHome}
                                                    disabled={!canSubmit}
                                                    onChange={(e) => form.setData(
                                                        'homework_material_ids',
                                                        e.target.checked
                                                            ? [...form.data.homework_material_ids, id]
                                                            : form.data.homework_material_ids.filter((value) => value !== id),
                                                    )}
                                                />
                                                {t.register_send_home || 'Send home with the homework'}
                                            </label>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
                <Field label={t.register_notes || 'Notes'}>
                    <input
                        className="form-input w-full"
                        aria-label={t.register_notes || 'Notes'}
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        disabled={!canSubmit}
                    />
                </Field>
                {attendanceMode === 'per_lesson' && roster.length > 0 && (
                    <div>
                        <p className="mb-2 text-sm font-semibold">{t.register_attendance || 'Attendance'}</p>
                        {errors?.attendance && <p className="mb-2 text-xs text-red-600">{errors.attendance}</p>}
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-[#F3EBE0] text-start">
                                    <tr>
                                        <th className="px-2 py-1">{col.student}</th>
                                        <th className="px-2 py-1">{col.number}</th>
                                        <th className="px-2 py-1">{col.dob}</th>
                                        <th className="px-2 py-1">{col.status}</th>
                                        <th className="px-2 py-1">{col.minutesLate}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {roster.map((student) => {
                                        const key = String(student.student_id);
                                        const row = grid[key] || { student_id: student.student_id, status: 'present', minutes_late: '' };
                                        return (
                                            <tr key={student.student_id} className="border-t">
                                                <td className="px-2 py-1">{student.name}</td>
                                                <td className="px-2 py-1">{student.student_number || '—'}</td>
                                                <td className="px-2 py-1">{student.date_of_birth || '—'}</td>
                                                <td className="px-2 py-1">
                                                    <select
                                                        className="form-input w-full"
                                                        aria-label={`${col.status}: ${student.name}`}
                                                        value={row.status}
                                                        disabled={!canSubmit}
                                                        onChange={(e) => setGrid((current) => ({
                                                            ...current,
                                                            [key]: { ...row, status: e.target.value },
                                                        }))}
                                                    >
                                                        {attendanceStatuses.map((status) => <option key={status} value={status}>{t[`attendance_status_${status}`] || status}</option>)}
                                                    </select>
                                                </td>
                                                <td className="px-2 py-1">
                                                    <input
                                                        className="form-input w-20"
                                                        type="number"
                                                        min="0"
                                                        aria-label={`${col.minutesLate}: ${student.name}`}
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
                        {t.register_daily_mode || 'This school marks attendance once per day.'}
                        {' '}
                        <Link href="/academics/attendance/daily" className="text-[#7C2D37] underline">{t.register_open_daily || 'Open daily attendance'}</Link>
                    </p>
                )}
                {canSubmit && (
                    <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                        {t.register_submit || 'Submit register'}
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
                    <p className="text-sm text-gray-600">{t.register_unlock_intro || 'Admin unlock (audited). The teacher then has 24 hours to edit.'}</p>
                    <Field label={t.register_reason || 'Reason'} error={unlock.errors.reason}>
                        <input className="form-input w-full" aria-label={t.register_reason || 'Reason'} value={unlock.data.reason} onChange={(e) => unlock.setData('reason', e.target.value)} />
                    </Field>
                    <button type="submit" className="btn-secondary justify-self-start">{t.register_unlock || 'Unlock'}</button>
                </form>
            )}
        </AppShell>
    );
}
