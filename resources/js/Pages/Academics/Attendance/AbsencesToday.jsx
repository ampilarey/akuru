import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

function query({ date, classId, onlyUnexplained }) {
    const params = new URLSearchParams();
    if (date) params.set('date', date);
    if (classId) params.set('class_id', classId);
    if (onlyUnexplained) params.set('only_unexplained', '1');
    const q = params.toString();

    return q ? `?${q}` : '';
}

export default function AbsencesToday({
    date,
    students = [],
    counts = { total: 0, unexplained: 0 },
    classId = null,
    onlyUnexplained = false,
    classes = [],
    t = {},
}) {
    const [filters, setFilters] = useState({
        date: date || '',
        classId: classId || '',
        onlyUnexplained: !!onlyUnexplained,
    });

    const apply = (next) => {
        setFilters(next);
        router.get(`/academics/attendance/absences${query(next)}`, {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // In the page's language (BACKLOG C21, slice OA1). The count stays bold
    // wherever the language puts it in the sentence; a note's state is a
    // code, named here. A contact's relationship is what the office typed.
    const [beforeCount, afterCount] = (t.absences_not_in || ':count not in on :date').replace(':date', date).split(':count');

    return (
        <AppShell title={t.absences_title || 'Absences'}>
            <div className="mb-4 flex flex-wrap items-end gap-3 rounded-lg border bg-white p-4">
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.date || 'Date'}</span>
                    <input
                        type="date"
                        className="form-input"
                        value={filters.date}
                        onChange={(e) => apply({ ...filters, date: e.target.value })}
                    />
                </label>
                <label className="block text-sm">
                    <span className="mb-1 block text-gray-600">{t.class || 'Class'}</span>
                    <select
                        className="form-input"
                        value={filters.classId}
                        onChange={(e) => apply({ ...filters, classId: e.target.value })}
                    >
                        <option value="">{t.absences_all_classes || 'All classes'}</option>
                        {classes.map((item) => (
                            <option key={item.id} value={item.id}>{item.label}</option>
                        ))}
                    </select>
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={filters.onlyUnexplained}
                        onChange={(e) => apply({ ...filters, onlyUnexplained: e.target.checked })}
                    />
                    {t.absences_only_unexplained || 'Only unexplained'}
                </label>
                <a className="btn-secondary" href={`/academics/attendance/absences/export${query(filters)}`}>{t.export_csv || 'Export CSV'}</a>
            </div>

            <p className="mb-4 text-sm text-gray-600">
                {beforeCount}<span className="font-semibold">{counts.total}</span>{afterCount}
                {counts.unexplained > 0 && (
                    <>
                        {' · '}
                        <span className="font-semibold text-[#7C2D37]">{(t.absences_no_note_count || ':count with no note').replace(':count', counts.unexplained)}</span>
                    </>
                )}
            </p>

            {students.length === 0 ? (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    {t.absences_nobody || 'Nobody is marked absent for this day.'}
                </p>
            ) : (
                <div className="overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">{t.col_student || 'Student'}</th>
                                <th className="px-3 py-2">{t.col_class || 'Class'}</th>
                                <th className="px-3 py-2">{t.absences_col_periods || 'Periods'}</th>
                                <th className="px-3 py-2">{t.col_note || 'Note'}</th>
                                <th className="px-3 py-2">{t.absences_col_ring || 'Ring'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {students.map((row) => (
                                <tr
                                    key={row.student_id}
                                    className={`border-t ${row.is_unexplained ? 'bg-[#FBF3F4]' : ''}`}
                                >
                                    <td className="px-3 py-2">
                                        {row.student_name}
                                        {row.student_number && (
                                            <span className="block text-xs text-gray-500">{row.student_number}</span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">{row.class_name}</td>
                                    <td className="px-3 py-2">
                                        {row.periods_missed}
                                        <span className="block text-xs text-gray-500">{row.periods.join(', ')}</span>
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.is_unexplained ? (
                                            <span className="font-semibold text-[#7C2D37]">{t.absences_call_home || 'No note — call home'}</span>
                                        ) : (
                                            <>
                                                <span className="rounded bg-[#F3EBE0] px-2 py-0.5 text-xs">
                                                    {t[`note_status_${row.note_status}`] || row.note_status}
                                                </span>
                                                {row.note_reason && (
                                                    <span className="block text-xs text-gray-600">{row.note_reason}</span>
                                                )}
                                            </>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.emergency_contact ? (
                                            <>
                                                <a
                                                    className="text-[#7C2D37] underline"
                                                    href={`tel:${row.emergency_contact.phone}`}
                                                >
                                                    {row.emergency_contact.phone}
                                                </a>
                                                <span className="block text-xs text-gray-500">
                                                    {row.emergency_contact.name}
                                                    {row.emergency_contact.relationship
                                                        ? ` · ${row.emergency_contact.relationship}`
                                                        : ''}
                                                    {row.emergency_contact.others > 0
                                                        ? ` · ${(t.absences_more || '+:count more').replace(':count', row.emergency_contact.others)}`
                                                        : ''}
                                                </span>
                                            </>
                                        ) : (
                                            <span className="text-xs text-gray-500">{t.absences_no_contact || 'No contact on file'}</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <p className="mt-4 text-sm text-gray-600">
                <Link href="/academics/absence-notes" className="text-[#7C2D37] underline">{t.absences_review_notes || 'Review absence notes'}</Link>
            </p>
        </AppShell>
    );
}
