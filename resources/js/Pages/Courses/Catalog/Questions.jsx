import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

const SAMPLE_OPTIONS = {
    mcq_single: JSON.stringify([{ id: 'a', label: 'Yes' }, { id: 'b', label: 'No' }], null, 2),
    mcq_multiple: JSON.stringify([{ id: 'a', label: 'One' }, { id: 'b', label: 'Two' }], null, 2),
    true_false: JSON.stringify([{ id: 'true', label: 'True' }, { id: 'false', label: 'False' }], null, 2),
    matching: JSON.stringify([{ id: '1', label: 'A' }, { id: '2', label: 'B' }], null, 2),
    arrange: JSON.stringify([{ id: '1', label: 'First' }, { id: '2', label: 'Second' }], null, 2),
};

const SAMPLE_CORRECT = {
    mcq_single: JSON.stringify(['a']),
    mcq_multiple: JSON.stringify(['a', 'b']),
    true_false: JSON.stringify(['true']),
    matching: JSON.stringify(['1']),
    arrange: JSON.stringify(['1', '2']),
};

export default function Questions({ rows, subjects, standards, types }) {
    const form = useForm({
        title: '',
        question_text: '',
        question_type: 'mcq_single',
        subject_id: '',
        difficulty: 'medium',
        skill_tag: '',
        options: SAMPLE_OPTIONS.mcq_single,
        correct_answer: SAMPLE_CORRECT.mcq_single,
        acceptable_answers: '[]',
        standard_ids: [],
        file: null,
    });

    return (
        <AppShell title="Question bank">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/questions/export">Export CSV</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/catalog/questions', { preserveScroll: true, forceFormData: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2"
            >
                <input className="form-input" placeholder="Title (optional)" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <select
                    className="form-input"
                    value={form.data.question_type}
                    onChange={(e) => {
                        const question_type = e.target.value;
                        form.setData({
                            ...form.data,
                            question_type,
                            options: SAMPLE_OPTIONS[question_type] || '[]',
                            correct_answer: SAMPLE_CORRECT[question_type] || '[]',
                        });
                    }}
                >
                    {types.map((type) => <option key={type} value={type}>{type}</option>)}
                </select>
                <select className="form-input" value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                    <option value="">Any subject</option>
                    {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name_en}</option>)}
                </select>
                <select className="form-input" value={form.data.difficulty} onChange={(e) => form.setData('difficulty', e.target.value)}>
                    <option value="easy">easy</option>
                    <option value="medium">medium</option>
                    <option value="hard">hard</option>
                </select>
                <textarea className="form-input md:col-span-2 min-h-20" placeholder="Question text" value={form.data.question_text} onChange={(e) => form.setData('question_text', e.target.value)} />
                <textarea className="form-input min-h-24 font-mono text-xs" value={form.data.options} onChange={(e) => form.setData('options', e.target.value)} />
                <textarea className="form-input min-h-24 font-mono text-xs" value={form.data.correct_answer} onChange={(e) => form.setData('correct_answer', e.target.value)} />
                <input className="form-input" placeholder="Skill tag" value={form.data.skill_tag} onChange={(e) => form.setData('skill_tag', e.target.value)} />
                <input className="form-input" type="file" onChange={(e) => form.setData('file', e.target.files?.[0] || null)} />
                {standards.length > 0 && (
                    <select
                        className="form-input md:col-span-2"
                        multiple
                        value={form.data.standard_ids}
                        onChange={(e) => form.setData('standard_ids', Array.from(e.target.selectedOptions).map((option) => option.value))}
                    >
                        {standards.map((standard) => (
                            <option key={standard.id} value={standard.id}>{standard.code} {standard.title}</option>
                        ))}
                    </select>
                )}
                <button type="submit" className="btn-primary" disabled={form.processing}>Save question</button>
                {form.errors.question_text && <span className="text-xs text-red-600">{form.errors.question_text}</span>}
                {form.errors.question_type && <span className="text-xs text-red-600">{form.errors.question_type}</span>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Text</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2">Pattern</th>
                            <th className="px-3 py-2">Difficulty</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={4}>No questions yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.title || row.question_text}</td>
                                <td className="px-3 py-2">{row.question_type}</td>
                                <td className="px-3 py-2">{row.pattern}</td>
                                <td className="px-3 py-2">{row.difficulty}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
