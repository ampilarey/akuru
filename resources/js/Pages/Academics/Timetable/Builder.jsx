import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

function query(params) {
    const next = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            next.set(key, String(value));
        }
    });
    return `/academics/timetable?${next.toString()}`;
}

export default function Builder({
    yearId,
    view,
    classId,
    teacherId,
    roomId,
    years,
    classes,
    periods,
    subjects,
    rooms,
    teachers,
    entries,
    substitutions,
    canOverride,
}) {
    const form = useForm({
        academic_year_id: yearId || '',
        class_id: classId || '',
        subject_id: '',
        teacher_id: teacherId || teachers[0]?.id || '',
        room_id: roomId || rooms[0]?.id || '',
        day_of_week: 'monday',
        period_id: periods.find((period) => !period.is_break)?.id || '',
        allow_conflict: false,
        conflict_reason: '',
    });

    const copyForm = useForm({
        academic_year_id: yearId || '',
        source_class_id: '',
        target_class_id: classId || '',
    });

    const weekForm = useForm({
        academic_year_id: yearId || '',
        class_id: classId || '',
    });

    const teacherName = (id) => {
        const row = teachers.find((item) => String(item.id) === String(id));
        return row ? `${row.first_name} ${row.last_name}` : `Teacher ${id}`;
    };
    const subjectName = (id) => subjects.find((item) => String(item.id) === String(id))?.name || `Subject ${id}`;
    const roomName = (id) => rooms.find((item) => String(item.id) === String(id))?.name || '';
    const subFor = (id) => substitutions.find((item) => String(item.timetable_id) === String(id));

    const reload = (overrides = {}) => {
        router.get(query({
            academic_year_id: yearId,
            view,
            class_id: classId,
            teacher_id: teacherId,
            room_id: roomId,
            ...overrides,
        }), {}, { preserveState: true });
    };

    const placeSlot = (day, period, subjectId) => {
        if (!subjectId || !classId || !yearId || !form.data.teacher_id) {
            return;
        }
        router.post('/academics/timetable', {
            academic_year_id: yearId,
            class_id: classId,
            subject_id: subjectId,
            teacher_id: form.data.teacher_id,
            room_id: form.data.room_id || null,
            day_of_week: day,
            period_id: period.id,
            allow_conflict: form.data.allow_conflict,
            conflict_reason: form.data.conflict_reason,
        }, { preserveScroll: true });
    };

    return (
        <AppShell title="Timetable">
            <style>{`@media print { header, .no-print { display: none !important; } table { width: 100%; } }`}</style>
            <div className="no-print mb-4 flex flex-wrap items-center gap-2">
                {['class', 'teacher', 'room'].map((name) => (
                    <button
                        key={name}
                        type="button"
                        className={`rounded px-3 py-1 text-sm capitalize ${view === name ? 'bg-[#7C2D37] text-white' : 'border bg-white'}`}
                        onClick={() => reload({ view: name })}
                    >
                        {name} view
                    </button>
                ))}
                <select className="form-input" value={yearId || ''} onChange={(e) => reload({ academic_year_id: e.target.value })}>
                    <option value="">Year</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                {view === 'class' && (
                    <select className="form-input" value={classId || ''} onChange={(e) => reload({ class_id: e.target.value })}>
                        <option value="">Class</option>
                        {classes.map((row) => <option key={row.id} value={row.id}>{row.name} {row.section}</option>)}
                    </select>
                )}
                {view === 'teacher' && (
                    <select className="form-input" value={teacherId || ''} onChange={(e) => reload({ teacher_id: e.target.value })}>
                        <option value="">Teacher</option>
                        {teachers.map((row) => <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>)}
                    </select>
                )}
                {view === 'room' && (
                    <select className="form-input" value={roomId || ''} onChange={(e) => reload({ room_id: e.target.value })}>
                        <option value="">Room</option>
                        {rooms.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                    </select>
                )}
                <a className="btn-secondary" href={`/academics/timetable/export?academic_year_id=${yearId || ''}`}>Export CSV</a>
                <button type="button" className="btn-secondary" onClick={() => window.print()}>Print</button>
            </div>

            <div className="no-print mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4">
                <label className="text-sm">Teacher
                    <select className="form-input w-full" value={form.data.teacher_id} onChange={(e) => form.setData('teacher_id', e.target.value)}>
                        {teachers.map((row) => <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>)}
                    </select>
                </label>
                <label className="text-sm">Room
                    <select className="form-input w-full" value={form.data.room_id} onChange={(e) => form.setData('room_id', e.target.value)}>
                        <option value="">None</option>
                        {rooms.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                    </select>
                </label>
                {canOverride && (
                    <>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={form.data.allow_conflict} onChange={(e) => form.setData('allow_conflict', e.target.checked)} />
                            Allow conflict
                        </label>
                        <input className="form-input" placeholder="Override reason" value={form.data.conflict_reason} onChange={(e) => form.setData('conflict_reason', e.target.value)} />
                    </>
                )}
                {form.errors.conflicts && <p className="md:col-span-4 text-sm text-red-600">{form.errors.conflicts}</p>}
                {form.errors.period_id && <p className="md:col-span-4 text-sm text-red-600">{form.errors.period_id}</p>}
            </div>

            <div className="no-print mb-4">
                <p className="mb-2 text-sm text-gray-600">Drag a subject onto a period cell.</p>
                <div className="flex flex-wrap gap-2">
                    {subjects.map((subject) => (
                        <button
                            key={subject.id}
                            type="button"
                            draggable
                            className={`rounded border px-3 py-1 text-sm ${String(form.data.subject_id) === String(subject.id) ? 'border-[#7C2D37] bg-[#F3EBE0]' : 'bg-white'}`}
                            onDragStart={(event) => {
                                event.dataTransfer.setData('text/plain', String(subject.id));
                                form.setData('subject_id', subject.id);
                            }}
                            onClick={() => form.setData('subject_id', subject.id)}
                        >
                            {subject.name}
                        </button>
                    ))}
                </div>
            </div>

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-xs">
                    <thead className="bg-[#F3EBE0]">
                        <tr>
                            <th className="px-2 py-2 text-left">Period</th>
                            {DAYS.map((day) => <th key={day} className="px-2 py-2 capitalize">{day.slice(0, 3)}</th>)}
                        </tr>
                    </thead>
                    <tbody>
                        {periods.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={8}>No periods yet. Add periods before building a timetable.</td></tr>
                        )}
                        {periods.map((period) => (
                            <tr key={period.id} className="border-t align-top">
                                <td className="px-2 py-2 font-medium">{period.name}<div className="text-gray-500">{period.start_time}–{period.end_time}</div></td>
                                {DAYS.map((day) => {
                                    const cell = entries.filter((entry) => entry.day_of_week === day && String(entry.period_id) === String(period.id));
                                    return (
                                        <td
                                            key={day}
                                            className="h-20 border-l px-1 py-1"
                                            onDragOver={(event) => event.preventDefault()}
                                            onClick={() => placeSlot(day, period, form.data.subject_id)}
                                            onDrop={(event) => {
                                                event.preventDefault();
                                                const subjectId = event.dataTransfer.getData('text/plain') || form.data.subject_id;
                                                placeSlot(day, period, subjectId);
                                            }}
                                        >
                                            {cell.map((entry) => {
                                                const sub = subFor(entry.id);
                                                return (
                                                    <div key={entry.id} className="mb-1 rounded bg-[#F9F4EE] p-1" onClick={(event) => event.stopPropagation()}>
                                                        <div className="font-medium">{subjectName(entry.subject_id)}</div>
                                                        <div>{teacherName(entry.teacher_id)}</div>
                                                        {entry.room_id && <div>{roomName(entry.room_id)}</div>}
                                                        {entry.conflicts?.length > 0 && (
                                                            <div className="text-red-700">Conflict: {entry.conflicts.map((item) => item.type).join(', ')}</div>
                                                        )}
                                                        {sub && <div className="text-[#7C2D37]">Sub: {teacherName(sub.substitute_teacher_id)}</div>}
                                                        <button
                                                            type="button"
                                                            className="no-print text-[11px] text-red-700 underline"
                                                            onClick={() => router.delete(`/academics/timetable/${entry.id}?academic_year_id=${yearId}&class_id=${classId}&view=${view}`)}
                                                        >
                                                            Remove
                                                        </button>
                                                    </div>
                                                );
                                            })}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {view === 'class' && (
                <div className="no-print mt-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            copyForm.post('/academics/timetable/copy-from-class');
                        }}
                    >
                        <p className="mb-2 text-sm font-medium">Copy from class</p>
                        <select className="form-input mb-2 w-full" value={copyForm.data.source_class_id} onChange={(e) => copyForm.setData('source_class_id', e.target.value)}>
                            <option value="">Source class</option>
                            {classes.filter((row) => String(row.id) !== String(classId)).map((row) => (
                                <option key={row.id} value={row.id}>{row.name} {row.section}</option>
                            ))}
                        </select>
                        <button type="submit" className="btn-primary" disabled={copyForm.processing}>Copy from class</button>
                    </form>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            weekForm.post('/academics/timetable/copy-week');
                        }}
                    >
                        <p className="mb-2 text-sm font-medium">Copy week (+7 days validity)</p>
                        <button type="submit" className="btn-secondary" disabled={weekForm.processing || !classId}>Copy week</button>
                    </form>
                </div>
            )}
        </AppShell>
    );
}
