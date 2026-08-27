import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

function CreateForm({ targets, options, reference, surahs }) {
    const form = useForm({
        student_id: '',
        course_id: '',
        course_offering_id: '',
        assignment_type: 'new_memorization',
        surah_id: '',
        start_ayah_number: '',
        end_ayah_number: '',
        expected_letter_id: '',
        expected_haraka_id: '',
        due_date: '',
        notes: '',
    });

    const pickTarget = (value) => {
        const target = targets.find((t) => String(t.enrollment_id) === value);
        form.setData((data) => ({
            ...data,
            student_id: target?.student_id ?? '',
            course_id: target?.course_id ?? '',
            course_offering_id: target?.course_offering_id ?? '',
        }));
    };

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/teach/assignments', { preserveScroll: true, onSuccess: () => form.reset() });
            }}
            className="mb-6 grid gap-2 rounded-lg border bg-white p-4 md:grid-cols-4"
        >
            <select className="form-input" onChange={(e) => pickTarget(e.target.value)} defaultValue="">
                <option value="">Student (hifz enrollments)…</option>
                {targets.map((target) => (
                    <option key={target.enrollment_id} value={target.enrollment_id}>{target.student_name}</option>
                ))}
            </select>
            <select className="form-input" value={form.data.assignment_type} onChange={(e) => form.setData('assignment_type', e.target.value)}>
                {options.types.map((type) => <option key={type} value={type}>{type.replaceAll('_', ' ')}</option>)}
            </select>
            <input className="form-input" type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
            <button type="submit" className="btn-primary" disabled={form.processing || !form.data.student_id}>Assign</button>

            <select className="form-input" value={form.data.surah_id} onChange={(e) => form.setData('surah_id', e.target.value)}>
                <option value="">Surah (optional)…</option>
                {surahs.map((surah) => (
                    <option key={surah.id} value={surah.id}>{surah.index}. {surah.english_name}</option>
                ))}
            </select>
            <input className="form-input" type="number" min="1" placeholder="From ayah" value={form.data.start_ayah_number} onChange={(e) => form.setData('start_ayah_number', e.target.value)} />
            <input className="form-input" type="number" min="1" placeholder="To ayah" value={form.data.end_ayah_number} onChange={(e) => form.setData('end_ayah_number', e.target.value)} />
            <input className="form-input" placeholder="Notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />

            <select className="form-input" value={form.data.expected_letter_id} onChange={(e) => form.setData('expected_letter_id', e.target.value)}>
                <option value="">Letter (practice)…</option>
                {reference.letters.map((letter) => (
                    <option key={letter.id} value={letter.id}>{letter.arabic_character} {letter.display_name}</option>
                ))}
            </select>
            <select className="form-input" value={form.data.expected_haraka_id} onChange={(e) => form.setData('expected_haraka_id', e.target.value)}>
                <option value="">Haraka (practice)…</option>
                {reference.harakas.map((haraka) => (
                    <option key={haraka.id} value={haraka.id}>{haraka.symbol} {haraka.display_name}</option>
                ))}
            </select>
        </form>
    );
}

export default function QuranAssignments({ rows, targets, options, status, reference, surahs }) {
    return (
        <AppShell title="Qur'an assignments">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-1">
                    {['all', ...options.statuses].map((option) => (
                        <button
                            key={option}
                            type="button"
                            onClick={() => router.get('/teach/assignments', option === 'all' ? {} : { status: option }, { preserveState: false })}
                            className={`rounded px-3 py-1 text-sm ${option === status ? 'bg-[#0F6D5F] text-white' : 'bg-gray-100'}`}
                        >
                            {option.replaceAll('_', ' ')}
                        </button>
                    ))}
                </div>
                <a className="btn-secondary" href={`/teach/assignments?status=${status}&format=csv`}>Export CSV</a>
            </div>

            <CreateForm targets={targets} options={options} reference={reference} surahs={surahs} />

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Surah</th>
                            <th className="px-3 py-2">Ayahs</th>
                            <th className="px-3 py-2">Due</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={7}>No assignments.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.student?.name ?? '—'}</td>
                                <td className="px-3 py-2">{row.assignment_type?.replaceAll('_', ' ')}</td>
                                <td className="px-3 py-2">{row.surah ?? '—'}</td>
                                <td className="px-3 py-2">{row.start_ayah_number ? `${row.start_ayah_number}–${row.end_ayah_number ?? row.start_ayah_number}` : '—'}</td>
                                <td className="px-3 py-2">{row.due_date ?? '—'}</td>
                                <td className="px-3 py-2">{row.status?.replaceAll('_', ' ')}</td>
                                <td className="px-3 py-2 text-end">
                                    {row.status !== 'cancelled' && (
                                        <button
                                            type="button"
                                            className="text-sm text-red-600"
                                            onClick={() => router.put(`/teach/assignments/${row.id}`, { status: 'cancelled' }, { preserveScroll: true })}
                                        >
                                            Cancel
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
