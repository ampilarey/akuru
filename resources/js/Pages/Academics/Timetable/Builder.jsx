import { router, useForm, usePage } from '@inertiajs/react';
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
    t = {},
}) {
    const { errors: pageErrors = {}, locale = 'en' } = usePage().props;
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

    // In the page's language (BACKLOG C21, slice OA2). A clash's kind is a
    // code, named here; a room is named in the page's language where the
    // office gave it one.
    const teacherName = (id) => {
        const row = teachers.find((item) => `${item.id}` === `${id}`);
        return row ? `${row.first_name} ${row.last_name}` : (t.teacher_number || 'Teacher #:id').replace(':id', id);
    };
    const subjectName = (id) => subjects.find((item) => `${item.id}` === `${id}`)?.name || (t.subject_number || 'Subject #:id').replace(':id', id);
    // The teacher and room views list one person's or one room's week across
    // every class, and until the timetable walk (STATUS §5fs) a cell there read
    // "Arabic Language · Ustadh Mohamed" twice with no word of *which* classes —
    // which is the one thing those views exist to say.
    const className = (id) => {
        const row = classes.find((item) => `${item.id}` === `${id}`);
        return row ? `${row.name} ${row.section}`.trim() : (t.class_number || 'Class #:id').replace(':id', id);
    };
    const roomLabel = (room) => ({ dv: room.name_dhivehi, ar: room.name_arabic }[locale]) || room.name;
    const roomName = (id) => {
        const room = rooms.find((item) => `${item.id}` === `${id}`);
        return room ? roomLabel(room) : '';
    };
    const subFor = (id) => substitutions.find((item) => `${item.timetable_id}` === `${id}`);
    const clashes = (items) => items.map((item) => t[`conflict_${item.type}`] || item.type).join(', ');

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
        <AppShell title={t.timetable_title || 'Timetable'}>
            <style>{`@media print { header, .no-print { display: none !important; } table { width: 100%; } }`}</style>
            <div className="no-print mb-4 flex flex-wrap items-center gap-2">
                {['class', 'teacher', 'room'].map((name) => (
                    <button
                        key={name}
                        type="button"
                        className={`rounded px-3 py-1 text-sm ${view === name ? 'bg-[#7C2D37] text-white' : 'border bg-white'}`}
                        onClick={() => reload({ view: name })}
                    >
                        {t[`timetable_view_${name}`] || name}
                    </button>
                ))}
                <select className="form-input" aria-label={t.year || 'Year'} value={yearId || ''} onChange={(e) => reload({ academic_year_id: e.target.value })}>
                    <option value="">{t.year || 'Year'}</option>
                    {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                </select>
                {view === 'class' && (
                    <select className="form-input" aria-label={t.class || 'Class'} value={classId || ''} onChange={(e) => reload({ class_id: e.target.value })}>
                        <option value="">{t.class || 'Class'}</option>
                        {classes.map((row) => <option key={row.id} value={row.id}>{row.name} {row.section}</option>)}
                    </select>
                )}
                {view === 'teacher' && (
                    <select className="form-input" aria-label={t.teacher || 'Teacher'} value={teacherId || ''} onChange={(e) => reload({ teacher_id: e.target.value })}>
                        <option value="">{t.teacher || 'Teacher'}</option>
                        {teachers.map((row) => <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>)}
                    </select>
                )}
                {view === 'room' && (
                    <select className="form-input" aria-label={t.room || 'Room'} value={roomId || ''} onChange={(e) => reload({ room_id: e.target.value })}>
                        <option value="">{t.room || 'Room'}</option>
                        {rooms.map((row) => <option key={row.id} value={row.id}>{roomLabel(row)}</option>)}
                    </select>
                )}
                <a className="btn-secondary" href={`/academics/timetable/export?academic_year_id=${yearId || ''}`}>{t.export_csv || 'Export CSV'}</a>
                <button type="button" className="btn-secondary" onClick={() => window.print()}>{t.print || 'Print'}</button>
            </div>

            <div className="no-print mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-4">
                <label className="text-sm">{t.teacher || 'Teacher'}
                    <select className="form-input w-full" value={form.data.teacher_id} onChange={(e) => form.setData('teacher_id', e.target.value)}>
                        {teachers.map((row) => <option key={row.id} value={row.id}>{row.first_name} {row.last_name}</option>)}
                    </select>
                </label>
                <label className="text-sm">{t.room || 'Room'}
                    <select className="form-input w-full" value={form.data.room_id} onChange={(e) => form.setData('room_id', e.target.value)}>
                        <option value="">{t.none || 'None'}</option>
                        {rooms.map((row) => <option key={row.id} value={row.id}>{roomLabel(row)}</option>)}
                    </select>
                </label>
                {canOverride && (
                    <>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={form.data.allow_conflict} onChange={(e) => form.setData('allow_conflict', e.target.checked)} />
                            {t.timetable_allow_conflict || 'Allow conflict'}
                        </label>
                        <input
                            className="form-input"
                            placeholder={t.timetable_override_reason || 'Override reason'}
                            aria-label={t.timetable_override_reason || 'Override reason'}
                            value={form.data.conflict_reason}
                            onChange={(e) => form.setData('conflict_reason', e.target.value)}
                        />
                    </>
                )}
                {['conflicts', 'period_id', 'allow_conflict', 'conflict_reason'].map((key) => (form.errors[key] || pageErrors[key]) && (
                    <p key={key} className="md:col-span-4 text-sm text-red-600">{form.errors[key] || pageErrors[key]}</p>
                ))}
            </div>

            <div className="no-print mb-4">
                <p className="mb-2 text-sm text-gray-600">{t.timetable_drag || 'Drag a subject onto a period cell.'}</p>
                <div className="flex flex-wrap gap-2">
                    {subjects.map((subject) => (
                        <button
                            key={subject.id}
                            type="button"
                            draggable
                            className={`rounded border px-3 py-1 text-sm ${`${form.data.subject_id}` === `${subject.id}` ? 'border-[#7C2D37] bg-[#F3EBE0]' : 'bg-white'}`}
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
                            <th className="px-2 py-2 text-start">{t.col_period || 'Period'}</th>
                            {DAYS.map((day) => <th key={day} className="px-2 py-2">{t[`weekday_${day}`] || day}</th>)}
                        </tr>
                    </thead>
                    <tbody>
                        {periods.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={8}>{t.timetable_no_periods || 'No periods yet. Add periods before building a timetable.'}</td></tr>
                        )}
                        {periods.map((period) => (
                            <tr key={period.id} className="border-t align-top">
                                <td className="px-2 py-2 font-medium">{period.name}<div className="text-gray-500">{period.start_time}–{period.end_time}</div></td>
                                {DAYS.map((day) => {
                                    const cell = entries.filter((entry) => entry.day_of_week === day && `${entry.period_id}` === `${period.id}`);
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
                                                        {view !== 'class' && <div>{className(entry.class_id)}</div>}
                                                        <div>{teacherName(entry.teacher_id)}</div>
                                                        {entry.room_id && <div>{roomName(entry.room_id)}</div>}
                                                        {(entry.valid_from || entry.valid_until) && (
                                                            <div className="text-gray-500">{entry.valid_from || '…'}–{entry.valid_until || '…'}</div>
                                                        )}
                                                        {entry.conflicts?.length > 0 && (
                                                            <div className="text-red-700">{(t.timetable_conflict || 'Conflict: :list').replace(':list', clashes(entry.conflicts))}</div>
                                                        )}
                                                        {sub && <div className="text-[#7C2D37]">{(t.timetable_cover || 'Cover: :name').replace(':name', teacherName(sub.substitute_teacher_id))}</div>}
                                                        <button
                                                            type="button"
                                                            className="no-print text-[11px] text-red-700 underline"
                                                            onClick={() => router.delete(`/academics/timetable/${entry.id}?academic_year_id=${yearId}&class_id=${classId}&view=${view}`)}
                                                        >
                                                            {t.remove || 'Remove'}
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
                        <p className="mb-2 text-sm font-medium">{t.timetable_copy_from_class || 'Copy from class'}</p>
                        <select className="form-input mb-2 w-full" aria-label={t.timetable_source_class || 'Source class'} value={copyForm.data.source_class_id} onChange={(e) => copyForm.setData('source_class_id', e.target.value)}>
                            <option value="">{t.timetable_source_class || 'Source class'}</option>
                            {classes.filter((row) => `${row.id}` !== `${classId}`).map((row) => (
                                <option key={row.id} value={row.id}>{row.name} {row.section}</option>
                            ))}
                        </select>
                        <button type="submit" className="btn-primary" disabled={copyForm.processing}>{t.timetable_copy_from_class || 'Copy from class'}</button>
                        {copyForm.errors.source_class_id && <p className="mt-1 text-sm text-red-600">{copyForm.errors.source_class_id}</p>}
                    </form>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            weekForm.post('/academics/timetable/copy-week');
                        }}
                    >
                        <p className="mb-2 text-sm font-medium">{t.timetable_copy_week_title || 'Copy week (+7 days validity)'}</p>
                        <button type="submit" className="btn-secondary" disabled={weekForm.processing || !classId}>{t.timetable_copy_week || 'Copy week'}</button>
                    </form>
                </div>
            )}
        </AppShell>
    );
}
