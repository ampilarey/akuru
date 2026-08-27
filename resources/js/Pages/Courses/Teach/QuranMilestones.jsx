import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

function RecommendForm({ targets, options }) {
    const form = useForm({
        hifz_program_id: '',
        student_id: '',
        type: 'juz_completed',
        surah_number: '',
        juz_number: '',
        page_number: '',
        title: '',
        note: '',
    });

    const pickTarget = (value) => {
        const [programId, studentId] = value.split(':');
        form.setData((data) => ({
            ...data,
            hifz_program_id: programId ?? '',
            student_id: studentId ?? '',
        }));
    };

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/teach/milestones', { preserveScroll: true, onSuccess: () => form.reset() });
            }}
            className="mb-6 grid gap-2 rounded-lg border bg-white p-4 md:grid-cols-4"
        >
            <select className="form-input" onChange={(e) => pickTarget(e.target.value)} defaultValue="">
                <option value="">Student (mapped halaqas)…</option>
                {targets.map((target) => (
                    <option key={`${target.hifz_program_id}:${target.student_id}`} value={`${target.hifz_program_id}:${target.student_id}`}>
                        {target.student_name} — {target.program_name}
                    </option>
                ))}
            </select>
            <select className="form-input" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                {options.types.map((type) => <option key={type} value={type}>{type.replaceAll('_', ' ')}</option>)}
            </select>
            <input className="form-input" placeholder="Title (optional)" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
            <button type="submit" className="btn-primary" disabled={form.processing || !form.data.student_id}>Recommend</button>

            <input className="form-input" type="number" min="1" max="114" placeholder="Surah #" value={form.data.surah_number} onChange={(e) => form.setData('surah_number', e.target.value)} />
            <input className="form-input" type="number" min="1" max="30" placeholder="Juz #" value={form.data.juz_number} onChange={(e) => form.setData('juz_number', e.target.value)} />
            <input className="form-input" type="number" min="1" placeholder="Page #" value={form.data.page_number} onChange={(e) => form.setData('page_number', e.target.value)} />
            <input className="form-input" placeholder="Note" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
        </form>
    );
}

export default function QuranMilestones({ rows, targets, options, status, can_decide }) {
    return (
        <AppShell title="Memorization milestones">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-1">
                    {options.statuses.map((option) => (
                        <button
                            key={option}
                            type="button"
                            onClick={() => router.get('/teach/milestones', option === 'all' ? {} : { status: option }, { preserveState: false })}
                            className={`rounded px-3 py-1 text-sm ${option === status ? 'bg-[#0F6D5F] text-white' : 'bg-gray-100'}`}
                        >
                            {option.replaceAll('_', ' ')}
                        </button>
                    ))}
                </div>
                <a className="btn-secondary" href={`/teach/milestones?status=${status}&format=csv`}>Export CSV</a>
            </div>

            <RecommendForm targets={targets} options={options} />

            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Program</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Detail</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Note</th>
                            {can_decide && <th className="px-3 py-2" />}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={can_decide ? 7 : 6}>No milestones.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.student_name}</td>
                                <td className="px-3 py-2">{row.program_name}</td>
                                <td className="px-3 py-2">{row.type?.replaceAll('_', ' ')}</td>
                                <td className="px-3 py-2">
                                    {row.title || [row.surah_number && `Surah ${row.surah_number}`, row.juz_number && `Juz ${row.juz_number}`, row.page_number && `Page ${row.page_number}`].filter(Boolean).join(', ') || '—'}
                                </td>
                                <td className="px-3 py-2">{row.status?.replaceAll('_', ' ')}</td>
                                <td className="px-3 py-2">{row.note ?? '—'}</td>
                                {can_decide && (
                                    <td className="px-3 py-2 text-end">
                                        {row.status === 'pending' && (
                                            <button type="button" className="btn-secondary me-1" onClick={() => router.post(`/teach/milestones/${row.id}/review`, {}, { preserveScroll: true })}>
                                                Review
                                            </button>
                                        )}
                                        {(row.status === 'pending' || row.status === 'supervisor_reviewed') && (
                                            <>
                                                <button type="button" className="btn-primary me-1" onClick={() => router.post(`/teach/milestones/${row.id}/decide`, { approved: true }, { preserveScroll: true })}>
                                                    Approve
                                                </button>
                                                <button type="button" className="text-sm text-red-600" onClick={() => router.post(`/teach/milestones/${row.id}/decide`, { approved: false }, { preserveScroll: true })}>
                                                    Reject
                                                </button>
                                            </>
                                        )}
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
