import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

export default function Show({ classRoom, roster, q = '', candidates = [], teachers = [] }) {
    const searchForm = useForm({ q });
    const assignForm = useForm({ student_id: '' });
    const teacherForm = useForm({
        class_teacher_id: classRoom.class_teacher_id || '',
    });
    const [selectedId, setSelectedId] = useState('');

    const selected = candidates.find((row) => String(row.id) === String(selectedId));
    const hasAmbiguous = candidates.some((row) => row.indistinguishable);

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
                    Class teacher
                    <select
                        className="form-input mt-1 block min-w-56"
                        value={teacherForm.data.class_teacher_id}
                        onChange={(e) => teacherForm.setData('class_teacher_id', e.target.value)}
                    >
                        <option value="">None</option>
                        {teachers.map((teacher) => (
                            <option key={teacher.id} value={teacher.id}>{teacher.name}</option>
                        ))}
                    </select>
                </label>
                <button type="submit" className="btn-primary" disabled={teacherForm.processing}>
                    Save class teacher
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
                    placeholder="Search name, number, national ID"
                    value={searchForm.data.q}
                    onChange={(e) => searchForm.setData('q', e.target.value)}
                />
                <button type="submit" className="btn-primary">Search</button>
            </form>

            {q !== '' && candidates.length === 0 && (
                <p className="mb-4 text-sm text-gray-600">No students match that search.</p>
            )}

            {hasAmbiguous && (
                <p className="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
                    Two or more records look the same on name, date of birth, and national ID.
                    Class and student number (including a blank number) do not distinguish them.
                    Choose one explicitly — the roster will not guess.
                </p>
            )}

            {candidates.length > 0 && (
                <div className="mb-4 overflow-x-auto rounded-lg border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-left">
                            <tr>
                                <th className="px-3 py-2">Choose</th>
                                <th className="px-3 py-2">Name</th>
                                <th className="px-3 py-2">Number</th>
                                <th className="px-3 py-2">Date of birth</th>
                                <th className="px-3 py-2">National ID</th>
                                <th className="px-3 py-2">Current class</th>
                            </tr>
                        </thead>
                        <tbody>
                            {candidates.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <input
                                            type="radio"
                                            name="roster_candidate"
                                            value={row.id}
                                            checked={String(selectedId) === String(row.id)}
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
                            Add to roster
                        </button>
                        {!selected && (
                            <span className="text-sm text-gray-600">Select a student first.</span>
                        )}
                    </form>
                </div>
            )}

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Name</th>
                            <th className="px-3 py-2">Number</th>
                            <th className="px-3 py-2">Date of birth</th>
                            <th className="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {roster.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.name}</td>
                                <td className="px-3 py-2">{row.student_number}</td>
                                <td className="px-3 py-2">{row.date_of_birth || '—'}</td>
                                <td className="px-3 py-2">{row.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
