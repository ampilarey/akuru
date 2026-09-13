import { useForm } from '@inertiajs/react';
import { useState } from 'react';
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
    const blank = {
        title: '',
        question_text: '',
        secondary_text: '',
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
        // §20's "Video reference" — a link, not an upload.
        video_url: '',
        video_title: '',
    };
    const form = useForm(blank);

    // The `PUT catalog/questions/{question}` route and its controller method
    // both existed and **nothing on this page called them**: the bank was
    // write-once, so a typo was permanent and an attachment could never be
    // taken off. §21's snapshot rule exists precisely so that editing a bank
    // question is safe for attempts already under way — the guarantee was in
    // place and the door was locked.
    const [editing, setEditing] = useState(null);
    const editRow = (row) => {
        setEditing(row.id);
        form.setData({
            ...blank,
            title: row.title || '',
            question_text: row.question_text || '',
            secondary_text: row.secondary_text || '',
            question_type: row.question_type,
            subject_id: row.subject_id || '',
            difficulty: row.difficulty || 'medium',
            skill_tag: row.skill_tag || '',
            options: JSON.stringify(row.options || [], null, 2),
            correct_answer: JSON.stringify(row.correct_answer || [], null, 2),
            acceptable_answers: (row.acceptable_answers || []).join('\n'),
            normalization_settings: row.normalization_settings || {},
            standard_ids: (row.standard_ids || []).map(String),
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };
    const editingRow = rows.find((row) => row.id === editing) || null;

    const submit = (extra = {}) => {
        const url = editing ? `/catalog/questions/${editing}` : '/catalog/questions';
        // `form.transform(...)` returns undefined in @inertiajs/react v3, so the
        // two statements must stay apart — see InertiaFormTransformTest.
        form.transform((data) => (editing ? { ...data, ...extra, _method: 'put' } : { ...data, ...extra }));
        form.post(url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                if (!editing) {
                    form.reset();
                }
            },
        });
    };

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
                    submit();
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2"
            >
                <p className="md:col-span-2 text-sm font-medium">
                    {editing ? `Editing question #${editing}` : 'New question'}
                    {editing && (
                        <button
                            type="button"
                            className="ms-3 text-xs text-[#7C2D37] hover:underline"
                            onClick={() => { setEditing(null); form.reset(); }}
                        >
                            Cancel and start a new one
                        </button>
                    )}
                </p>
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
                {/* §20 names "Secondary text" as a field of its own — the
                    passage, transliteration or stem a question hangs off. The
                    column, the model, the payload and §21's snapshot all
                    carried it; there was no control to type it into. */}
                <textarea
                    className="form-input md:col-span-2 min-h-16"
                    placeholder="Secondary text — passage, transliteration or context shown with the question (optional)"
                    value={form.data.secondary_text}
                    onChange={(e) => form.setData('secondary_text', e.target.value)}
                />
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
                {/* §20 "Question Attachments": audio, image, PDF, video
                    reference. The upload existed; what was attached was never
                    shown back, so an author could not tell whether a file had
                    landed, which one it was, or take a wrong one off again. */}
                <fieldset className="md:col-span-2 rounded-lg border bg-[#F9F4EE] p-3">
                    <legend className="px-1 text-xs font-medium uppercase tracking-wide text-gray-600">
                        Attachments
                    </legend>
                    {editingRow && (editingRow.attachments || []).length > 0 && (
                        <ul className="mb-2 space-y-1 text-sm">
                            {(editingRow.attachments || []).map((attachment, index) => (
                                <li key={index} className="flex flex-wrap items-center gap-2">
                                    <span>
                                        {attachment.kind || attachment.mime || 'attachment'}
                                        {attachment.original_name ? ` · ${attachment.original_name}` : ''}
                                        {attachment.embed_url ? ` · ${attachment.embed_url}` : ''}
                                    </span>
                                    <button
                                        type="button"
                                        className="btn-secondary"
                                        onClick={() => submit({ remove_attachment: index })}
                                    >
                                        Remove
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="grid gap-2 md:grid-cols-3">
                        <label className="text-sm">
                            <span className="text-xs text-gray-600">Upload audio, image, PDF or video</span>
                            <input className="form-input" type="file" onChange={(e) => form.setData('file', e.target.files?.[0] || null)} />
                        </label>
                        <label className="text-sm">
                            <span className="text-xs text-gray-600">Or reference a video (YouTube / Vimeo)</span>
                            <input className="form-input" placeholder="https://…" value={form.data.video_url} onChange={(e) => form.setData('video_url', e.target.value)} />
                        </label>
                        <label className="text-sm">
                            <span className="text-xs text-gray-600">Video label (optional)</span>
                            <input className="form-input" value={form.data.video_title} onChange={(e) => form.setData('video_title', e.target.value)} />
                        </label>
                    </div>
                    {form.errors.file && <p className="mt-1 text-xs text-red-600">{form.errors.file}</p>}
                    {form.errors.video_url && <p className="mt-1 text-xs text-red-600">{form.errors.video_url}</p>}
                </fieldset>
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
                <button type="submit" className="btn-primary" disabled={form.processing}>
                    {editing ? 'Save changes' : 'Save question'}
                </button>
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
                            <th className="px-3 py-2">Attachments</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>No questions yet.</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.title || row.question_text}</td>
                                <td className="px-3 py-2">{row.question_type}</td>
                                <td className="px-3 py-2">{row.pattern}</td>
                                <td className="px-3 py-2">{row.difficulty}</td>
                                <td className="px-3 py-2">
                                    {(row.attachments || []).map((a) => a.kind || a.mime).filter(Boolean).join(', ') || '—'}
                                </td>
                                <td className="px-3 py-2">
                                    <button type="button" className="btn-secondary" onClick={() => editRow(row)}>Edit</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
