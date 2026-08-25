import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

const SAMPLE_DATA = {
    selection: JSON.stringify({
        prompt: 'Choose the correct option',
        options: [{ id: 'a', label: 'Option A' }, { id: 'b', label: 'Option B' }],
        correct_ids: ['a'],
        multiple: false,
    }, null, 2),
    text_input: JSON.stringify({
        prompt: 'Type the answer',
        acceptable: ['salam'],
    }, null, 2),
    arrange: JSON.stringify({
        prompt: 'Put these in order',
        items: [{ id: '1', label: 'First' }, { id: '2', label: 'Second' }],
        correct_order: ['1', '2'],
    }, null, 2),
    teacher_marked: JSON.stringify({
        prompt: 'Write a short response',
        submission_kind: 'written',
    }, null, 2),
};

export default function Activities({ course, activities, patterns }) {
    const form = useForm({
        title: '',
        pattern: 'selection',
        activity_type: 'multiple_choice',
        max_score: 1,
        passing_score: '',
        is_required: false,
        data: SAMPLE_DATA.selection,
        settings: JSON.stringify({
            retakes_allowed: true,
            retake_limit: 3,
            show_correct_answer: true,
            normalize: { case_insensitive: true, trim: true },
        }, null, 2),
    });

    return (
        <AppShell title={`Activities — ${course.title}`}>
            <div className="mb-4 flex flex-wrap gap-3 text-sm">
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${course.id}/outline`}>Outline</a>
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${course.id}/activities/export`}>Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/catalog/courses/${course.id}/activities`, { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2"
            >
                <input className="form-input" placeholder="Title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <select
                    className="form-input"
                    value={form.data.pattern}
                    onChange={(e) => {
                        const pattern = e.target.value;
                        form.setData({
                            ...form.data,
                            pattern,
                            data: SAMPLE_DATA[pattern] || form.data.data,
                        });
                    }}
                >
                    {patterns.map((pattern) => <option key={pattern} value={pattern}>{pattern}</option>)}
                </select>
                <input className="form-input" placeholder="Activity type label" value={form.data.activity_type} onChange={(e) => form.setData('activity_type', e.target.value)} />
                <input className="form-input" type="number" min="1" placeholder="Max score" value={form.data.max_score} onChange={(e) => form.setData('max_score', e.target.value)} />
                <textarea className="form-input md:col-span-2 min-h-32 font-mono text-xs" value={form.data.data} onChange={(e) => form.setData('data', e.target.value)} />
                <textarea className="form-input md:col-span-2 min-h-24 font-mono text-xs" value={form.data.settings} onChange={(e) => form.setData('settings', e.target.value)} />
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.is_required} onChange={(e) => form.setData('is_required', e.target.checked)} />
                    Required
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>Save activity</button>
                {form.errors.pattern && <span className="text-xs text-red-600">{form.errors.pattern}</span>}
                {form.errors.data && <span className="text-xs text-red-600">{form.errors.data}</span>}
                {form.errors.title && <span className="text-xs text-red-600">{form.errors.title}</span>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Title</th>
                            <th className="px-3 py-2">Pattern</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Score</th>
                            <th className="px-3 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {activities.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>No activities yet.</td></tr>
                        )}
                        {activities.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.title}</td>
                                <td className="px-3 py-2">{row.pattern}</td>
                                <td className="px-3 py-2">{row.activity_type}</td>
                                <td className="px-3 py-2">{row.max_score}</td>
                                <td className="px-3 py-2">
                                    <button
                                        type="button"
                                        className="btn-secondary"
                                        onClick={() => router.delete(`/catalog/courses/${course.id}/activities/${row.id}`)}
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
