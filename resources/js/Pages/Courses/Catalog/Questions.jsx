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
    // §17 Pattern 3: a matching answer key is a MAP of left id => right value,
    // not a selection. The old sample was ['1'] — a single id — which encoded
    // the mis-modelling that routed matching through Pattern 1.
    matching: JSON.stringify({ 1: 'Alif', 2: 'Baa' }, null, 2),
    arrange: JSON.stringify(['1', '2']),
};

/**
 * SPEC §18 groups the switches into general and Arabic-specific, and is
 * explicit that "Arabic normalization must not be global. It should apply only
 * when the activity configuration requires it." Keeping the two groups visibly
 * apart in the builder is how an author is told that.
 */
const FLAG_LABELS = {
    trim: 'Trim whitespace',
    collapse_space: 'Normalize repeated spaces',
    strip_punctuation: 'Remove punctuation',
    case_insensitive: 'Case-insensitive comparison',
    strip_tashkeel: 'Strip tashkeel / diacritics',
    normalize_alef: 'Normalize alef variants',
    normalize_hamza: 'Normalize hamza variants',
    taa_marbuta: 'Tolerate taa marbuta',
};
const ARABIC_FLAGS = ['strip_tashkeel', 'normalize_alef', 'normalize_hamza', 'taa_marbuta'];

export default function Questions({
    rows,
    subjects,
    standards,
    types,
    textInputTypes = [],
    normalizationFlags = [],
    normalizationModes = [],
}) {
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
        // §18's mode and switches. `{}` means "leave unset", which keeps the
        // question on the defaults a question saved before this existed uses.
        normalization_settings: {},
        standard_ids: [],
        file: null,
    });

    const isTextInput = textInputTypes.includes(form.data.question_type);
    const settings = form.data.normalization_settings || {};
    const setSetting = (key, value) => {
        const next = { ...settings };
        if (value === '' || value === null) {
            delete next[key];
        } else {
            next[key] = value;
        }
        form.setData('normalization_settings', next);
    };
    const generalFlags = normalizationFlags.filter((flag) => !ARABIC_FLAGS.includes(flag));
    const arabicFlags = normalizationFlags.filter((flag) => ARABIC_FLAGS.includes(flag));

    const flagCheckbox = (flag) => (
        <label key={flag} className="flex items-center gap-2 text-sm">
            <input
                type="checkbox"
                // Indeterminate is not worth the complexity here: an unticked
                // box means "not set", and the mode (or the default) decides.
                checked={settings[flag] === true}
                onChange={(e) => setSetting(flag, e.target.checked ? true : '')}
            />
            {FLAG_LABELS[flag] || flag}
        </label>
    );

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
                {/* §18: "For auto-marked text input, comparison must be
                    configurable per activity." The column and the scorer both
                    existed; there was no control anywhere to set them, so
                    every text question was marked on the lenient defaults. */}
                {isTextInput && (
                    <fieldset className="md:col-span-2 rounded-lg border bg-[#F9F4EE] p-3">
                        <legend className="px-1 text-xs font-medium uppercase tracking-wide text-gray-600">
                            Answer comparison
                        </legend>
                        <label className="mb-2 flex flex-wrap items-center gap-2 text-sm">
                            <span>Mode</span>
                            <select
                                className="form-input"
                                value={settings.mode || ''}
                                onChange={(e) => setSetting('mode', e.target.value)}
                            >
                                <option value="">Default</option>
                                {normalizationModes.map((mode) => <option key={mode} value={mode}>{mode}</option>)}
                            </select>
                            <span className="text-xs text-gray-600">
                                A mode sets the switches below; ticking one overrides the mode for that switch.
                            </span>
                        </label>
                        <div className="grid gap-1 md:grid-cols-2">
                            <div>
                                <p className="mb-1 text-xs font-medium text-gray-600">General</p>
                                {generalFlags.map(flagCheckbox)}
                            </div>
                            <div>
                                <p className="mb-1 text-xs font-medium text-gray-600">
                                    Arabic — applied only when ticked
                                </p>
                                {arabicFlags.map(flagCheckbox)}
                            </div>
                        </div>
                        <label className="mt-2 block text-sm">
                            <span className="text-xs text-gray-600">
                                Other accepted answers, one per line
                            </span>
                            <textarea
                                className="form-input min-h-16"
                                value={form.data.acceptable_answers === '[]' ? '' : form.data.acceptable_answers}
                                onChange={(e) => form.setData('acceptable_answers', e.target.value)}
                            />
                        </label>
                        {form.errors.normalization_settings && (
                            <p className="mt-1 text-xs text-red-600">{form.errors.normalization_settings}</p>
                        )}
                    </fieldset>
                )}
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
