import { useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

function Item({ item, studentId, canTick }) {
    const form = useForm({ student_id: studentId, done: !item.is_done });

    const toggle = () => {
        form.transform((data) => ({ ...data, done: !item.is_done }))
            .post(`/portal/homework/${item.id}/tick`, { preserveScroll: true });
    };

    return (
        <li className={`rounded-lg border bg-white p-3 ${item.is_overdue ? 'border-red-300' : ''}`}>
            <div className="flex items-start gap-3">
                {canTick && (
                    <input
                        type="checkbox"
                        className="mt-1"
                        checked={item.is_done}
                        onChange={toggle}
                        disabled={form.processing}
                        aria-label={item.is_done ? 'Mark not done' : 'Mark done'}
                    />
                )}
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <p className="text-sm font-semibold">
                            {item.subject || 'Lesson'}
                            {item.teacher ? <span className="font-normal text-gray-500"> · {item.teacher}</span> : null}
                        </p>
                        <span className={`text-xs ${item.is_overdue ? 'font-semibold text-red-600' : 'text-gray-500'}`}>
                            {item.due_date ? `Due ${item.due_date}` : 'No due date'}
                        </span>
                    </div>
                    <p className={`mt-1 whitespace-pre-wrap text-sm ${item.is_done ? 'text-gray-400 line-through' : 'text-gray-800'}`}>
                        {item.homework}
                    </p>
                    <p className="mt-1 text-xs text-gray-500">Set on {item.set_on}</p>
                </div>
            </div>
        </li>
    );
}

export default function Homework({ students = [], canTick = [] }) {
    return (
        <AppShell title="Homework">
            <p className="mb-4 text-sm text-gray-600">
                Homework from the class register, newest and most urgent first. Ticking is your own
                checklist — it is not seen by grading.
            </p>

            {students.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    No student or linked children.
                </p>
            )}

            <div className="space-y-6">
                {students.map((student) => (
                    <section key={student.id}>
                        <h2 className="mb-2 text-sm font-semibold">
                            {student.name}
                            <span className="ms-2 text-xs font-normal uppercase text-gray-500">{student.relationship}</span>
                        </h2>
                        {student.homework.length === 0 ? (
                            <p className="rounded-lg border bg-white p-3 text-sm text-gray-600">
                                No homework set in the last month.
                            </p>
                        ) : (
                            <ul className="grid gap-2">
                                {student.homework.map((item) => (
                                    <Item
                                        key={item.id}
                                        item={item}
                                        studentId={student.id}
                                        canTick={canTick.includes(student.id)}
                                    />
                                ))}
                            </ul>
                        )}
                    </section>
                ))}
            </div>
        </AppShell>
    );
}
