import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

export default function Show({ classRoom, roster, q = '', candidates = [], teachers = [], assessments = [], t = {} }) {
    const searchForm = useForm({ q });
    const assignForm = useForm({ student_id: '' });
    const teacherForm = useForm({
        class_teacher_id: classRoom.class_teacher_id || '',
    });
    const [selectedId, setSelectedId] = useState('');

    const selected = candidates.find((row) => `${row.id}` === `${selectedId}`);
    const hasAmbiguous = candidates.some((row) => row.indistinguishable);
    // In the page's language (BACKLOG C21, slice OA2); a pupil's state and an
    // assessment's state and kind are codes, named here.
    const col = {
        choose: t.classes_col_choose || 'Choose',
        name: t.col_name || 'Name',
        number: t.col_number || 'Number',
        dob: t.col_date_of_birth || 'Date of birth',
    };

    return (
        <AppShell title={`${classRoom.name} ${classRoom.section || ''}`}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    teacherForm.put(`/academics/classes/${classRoom.id}`, { preserveScroll: true });
                }}
                className="mb-4 flex flex-wrap items-end gap-3 rounded-lg border bg-white p-4"
            >
                <label className="text-sm text-gray-700">
                    {t.classes_teacher || 'Class teacher'}
                    <select
                        className="form-input mt-1 block min-w-56"
                        value={teacherForm.data.class_teacher_id}
                        onChange={(e) => teacherForm.setData('class_teacher_id', e.target.value)}
                    >
                        <option value="">{t.none || 'None'}</option>
                        {teachers.map((teacher) => (
                            <option key={teacher.id} value={teacher.id}>{teacher.name}</option>
                        ))}
                    </select>
                </label>
                <button type="submit" className="btn-primary" disabled={teacherForm.processing}>
                    {t.classes_save_teacher || 'Save class teacher'}
                </button>
                {teacherForm.errors.class_teacher_id && (
                    <p className="text-sm text-red-600">{teacherForm.errors.class_teacher_id}</p>
                )}
            </form>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    searchForm.get(`/academics/classes/${classRoom.id}`, { preserveScroll: true, preserveState: true });
                    setSelectedId('');
                    assignForm.setData('student_id', '');
                }}
                className="mb-4 flex flex-wrap gap-3 rounded-lg border bg-white p-4"
            >
                <input
                    className="form-input min-w-56"
                    placeholder={t.classes_search_placeholder || 'Search name, number, national ID'}
                    aria-label={t.classes_search_placeholder || 'Search name, number, national ID'}
                    value={searchForm.data.q}
                    onChange={(e) => searchForm.setData('q', e.target.value)}
                />
                <button type="submit" className="btn-primary">{t.classes_search || 'Search'}</button>
            </form>

            {q !== '' && candidates.length === 0 && (
                <p className="mb-4 text-sm text-gray-600">{t.classes_no_match || 'No students match that search.'}</p>
            )}

            {hasAmbiguous && (
                <p className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
                    {t.classes_ambiguous || 'Two or more records look the same on name, date of birth, and national ID. Class and student number (including a blank number) do not distinguish them. Choose one explicitly — the roster will not guess.'}
                </p>
            )}

            {candidates.length > 0 && (
                <div className="mb-4 overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">{col.choose}</th>
                                <th className="px-3 py-2">{col.name}</th>
                                <th className="px-3 py-2">{col.number}</th>
                                <th className="px-3 py-2">{col.dob}</th>
                                <th className="px-3 py-2">{t.classes_col_national_id || 'National ID'}</th>
                                <th className="px-3 py-2">{t.classes_col_current_class || 'Current class'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {candidates.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <input
                                            type="radio"
                                            name="roster_candidate"
                                            aria-label={`${col.choose}: ${row.name}`}
                                            value={row.id}
                                            checked={`${selectedId}` === `${row.id}`}
                                            onChange={() => {
                                                setSelectedId(row.id);
                                                assignForm.setData('student_id', row.id);
                                            }}
                                        />
                                    </td>
                                    <td className="px-3 py-2">{row.name}</td>
                                    <td className="px-3 py-2">{row.student_number || '—'}</td>
                                    <td className="px-3 py-2">{row.date_of_birth || '—'}</td>
                                    <td className="px-3 py-2">{row.national_id || '—'}</td>
                                    <td className="px-3 py-2">{row.current_class || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            if (!selected) {
                                return;
                            }
                            assignForm.post(`/academics/classes/${classRoom.id}/assign`, {
                                onSuccess: () => {
                                    setSelectedId('');
                                    assignForm.reset();
                                },
                            });
                        }}
                        className="flex items-center gap-3 border-t p-4"
                    >
                        <button type="submit" className="btn-primary" disabled={!selected}>
                            {t.classes_add_to_roster || 'Add to roster'}
                        </button>
                        {!selected && (
                            <span className="text-sm text-gray-600">{t.classes_select_first || 'Select a student first.'}</span>
                        )}
                        {assignForm.errors.student_id && <span className="text-sm text-red-600">{assignForm.errors.student_id}</span>}
                    </form>
                </div>
            )}

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{col.name}</th>
                            <th className="px-3 py-2">{col.number}</th>
                            <th className="px-3 py-2">{col.dob}</th>
                            <th className="px-3 py-2">{t.col_status || 'Status'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {roster.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                <td className="px-3 py-2">{row.student_number}</td>
                                <td className="px-3 py-2">{row.date_of_birth || '—'}</td>
                                <td className="px-3 py-2">{t[`student_status_${row.status}`] || row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <section className="mt-6 rounded-lg border bg-white p-4">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <h2 className="font-medium">{t.classes_assessments || 'Course assessments'}</h2>
                    <a
                        className="btn-secondary"
                        href={`/academics/classes/${classRoom.id}/assessments/export`}
                    >
                        {t.export_csv || 'Export CSV'}
                    </a>
                </div>
                {assessments.length === 0 && (
                    <p className="text-sm text-gray-500">{t.classes_no_assessments || 'No course assessments are attached to this class.'}</p>
                )}
                <ul className="space-y-3">
                    {assessments.map((row) => (
                        <li key={row.id} className="border-t pt-3 text-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="font-medium">{row.title}</span>
                                <span className="text-gray-500">
                                    {t[`assessment_status_${row.status}`] || row.status}
                                    {' · '}
                                    {t[`assessment_type_${row.assessment_type}`] || row.assessment_type}
                                    {' · '}
                                    {(t.classes_max_score || 'max :score').replace(':score', row.max_score)}
                                </span>
                            </div>
                            {row.legacy_quiz_id && (
                                <p className="text-xs text-gray-500">{(t.classes_migrated_quiz || 'Migrated quiz #:id').replace(':id', row.legacy_quiz_id)}</p>
                            )}
                            {row.legacy_assignment_id && (
                                <p className="text-xs text-gray-500">{(t.classes_migrated_assignment || 'Migrated assignment #:id').replace(':id', row.legacy_assignment_id)}</p>
                            )}
                            <ul className="mt-2 list-disc ps-5">
                                {(row.questions || []).map((item) => (
                                    <li key={item.question_id}>
                                        {item.question.question_text} · {(t.classes_points || ':points pts').replace(':points', item.points_override || 1)}
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ul>
            </section>
        </AppShell>
    );
}
