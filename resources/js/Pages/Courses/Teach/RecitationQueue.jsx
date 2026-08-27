import { router, useForm } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

const MISTAKE_TYPES = [
    'wrong_letter', 'wrong_haraka', 'missed_word', 'added_word', 'repeated_word',
    'wrong_word', 'pronunciation_issue', 'waqf_issue', 'madd_issue', 'ghunnah_issue',
    'tajweed_issue', 'other',
];
const SEVERITIES = ['minor', 'medium', 'major'];
const OUTCOMES = ['passed', 'needs_repeat', 'failed', 'teacher_reviewed'];

const range = (row) =>
    row.start_ayah_number ? `${row.start_ayah_number}–${row.end_ayah_number ?? row.start_ayah_number}` : '—';

function ReviewForm({ submission, onDone }) {
    const form = useForm({ status: 'passed', note: '', mistakes: [] });

    const addMistake = () =>
        form.setData('mistakes', [...form.data.mistakes, { mistake_type: 'wrong_haraka', severity: 'minor', ayah_number: '', comment: '' }]);
    const setMistake = (index, key, value) =>
        form.setData('mistakes', form.data.mistakes.map((row, i) => (i === index ? { ...row, [key]: value } : row)));
    const removeMistake = (index) =>
        form.setData('mistakes', form.data.mistakes.filter((_, i) => i !== index));

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(`/teach/recitations/${submission.id}/review`, { preserveScroll: true, onSuccess: onDone });
            }}
            className="grid gap-2 border-t bg-[#FBF7F0] p-3"
        >
            <div className="flex flex-wrap items-center gap-2">
                <select className="form-input" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                    {OUTCOMES.map((status) => <option key={status} value={status}>{status.replaceAll('_', ' ')}</option>)}
                </select>
                <input
                    className="form-input flex-1"
                    placeholder="Teacher note"
                    value={form.data.note}
                    onChange={(e) => form.setData('note', e.target.value)}
                />
                <button type="button" className="btn-secondary" onClick={addMistake}>+ Mistake</button>
                <button type="submit" className="btn-primary" disabled={form.processing}>Save review</button>
            </div>
            {form.data.mistakes.map((mistake, index) => (
                <div key={index} className="flex flex-wrap items-center gap-2">
                    <select className="form-input" value={mistake.mistake_type} onChange={(e) => setMistake(index, 'mistake_type', e.target.value)}>
                        {MISTAKE_TYPES.map((type) => <option key={type} value={type}>{type.replaceAll('_', ' ')}</option>)}
                    </select>
                    <select className="form-input" value={mistake.severity} onChange={(e) => setMistake(index, 'severity', e.target.value)}>
                        {SEVERITIES.map((severity) => <option key={severity} value={severity}>{severity}</option>)}
                    </select>
                    <input className="form-input w-24" type="number" min="1" placeholder="Ayah" value={mistake.ayah_number} onChange={(e) => setMistake(index, 'ayah_number', e.target.value)} />
                    <input className="form-input flex-1" placeholder="Comment" value={mistake.comment} onChange={(e) => setMistake(index, 'comment', e.target.value)} />
                    <button type="button" className="text-sm text-red-600" onClick={() => removeMistake(index)}>Remove</button>
                </div>
            ))}
        </form>
    );
}

export default function RecitationQueue({ rows, statuses, status }) {
    const [openId, setOpenId] = useState(null);

    return (
        <AppShell title="Recitation review queue">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap gap-1">
                    {statuses.map((option) => (
                        <button
                            key={option}
                            type="button"
                            onClick={() => router.get('/teach/recitations', option === 'all' ? {} : { status: option }, { preserveState: false })}
                            className={`rounded px-3 py-1 text-sm ${option === status ? 'bg-[#0F6D5F] text-white' : 'bg-gray-100'}`}
                        >
                            {option.replaceAll('_', ' ')}
                        </button>
                    ))}
                </div>
                <a className="btn-secondary" href={`/teach/recitations?status=${status}&format=csv`}>Export CSV</a>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Student</th>
                            <th className="px-3 py-2">Surah</th>
                            <th className="px-3 py-2">Ayahs</th>
                            <th className="px-3 py-2">Submitted</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Mistakes</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={7}>Queue is empty.</td></tr>
                        )}
                        {rows.map((row) => (
                            <Fragment key={row.id}>
                                <tr className="border-t">
                                    <td className="px-3 py-2">{row.student?.name ?? '—'}</td>
                                    <td className="px-3 py-2">{row.surah ?? '—'}</td>
                                    <td className="px-3 py-2">{range(row)}</td>
                                    <td className="px-3 py-2">{row.submitted_at}</td>
                                    <td className="px-3 py-2">{row.status?.replaceAll('_', ' ')}</td>
                                    <td className="px-3 py-2">{row.mistake_count}</td>
                                    <td className="px-3 py-2 text-end">
                                        <button type="button" className="btn-secondary" onClick={() => setOpenId(openId === row.id ? null : row.id)}>
                                            {openId === row.id ? 'Close' : 'Review'}
                                        </button>
                                    </td>
                                </tr>
                                {openId === row.id && (
                                    <tr>
                                        <td colSpan={7} className="p-0">
                                            <ReviewForm submission={row} onDone={() => setOpenId(null)} />
                                        </td>
                                    </tr>
                                )}
                            </Fragment>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
