import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

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

export default function Activities({ course, activities, patterns, skills = [], letters = [], harakas = [], surahs = [], t = {} }) {
    // Every string below is a key in the `teach` book (slice CT2, STATUS
    // §5ol); the English is the fallback. The JSON samples above keep their
    // English values: the keys are the format, the values are what the author
    // types over.
    const locale = usePage().props.locale || 'en';
    const patternName = (pattern) => t[`pattern_${pattern}`] || pattern;
    const surahName = (row) => (locale === 'en' ? row.english_name : row.arabic_name || row.english_name);
    const form = useForm({
        title: '',
        pattern: 'selection',
        activity_type: 'multiple_choice',
        skill: '',
        letter_id: '',
        harakah_id: '',
        surah_id: '',
        ayah_start: '',
        ayah_end: '',
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
        <AppShell title={(t.activities_title || 'Activities — :course').replace(':course', course.title)}>
            <div className="mb-4 flex flex-wrap gap-3 text-sm">
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${course.id}/outline`}>{t.nav_outline || 'Outline'}</a>
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${course.id}/activities/export`}>{t.catalog_export || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/catalog/courses/${course.id}/activities`, { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2"
            >
                <input className="form-input" placeholder={t.catalog_new_title || 'Title'} aria-label={t.catalog_new_title || 'Title'} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                <select
                    className="form-input"
                    aria-label={t.activities_pattern || 'Pattern'}
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
                    {patterns.map((pattern) => <option key={pattern} value={pattern}>{patternName(pattern)}</option>)}
                </select>
                <input className="form-input" placeholder={t.activities_activity_type || 'Activity type label'} aria-label={t.activities_activity_type || 'Activity type label'} value={form.data.activity_type} onChange={(e) => form.setData('activity_type', e.target.value)} />
                <select className="form-input" aria-label={t.activities_skill || 'Skill'} value={form.data.skill} onChange={(e) => form.setData('skill', e.target.value)}>
                    <option value="">{t.activities_no_skill || 'No skill tag'}</option>
                    {skills.map((skill) => <option key={skill} value={skill}>{t[`skill_${skill}`] || skill}</option>)}
                </select>
                <select className="form-input" aria-label={t.activities_letter || 'Letter'} value={form.data.letter_id} onChange={(e) => form.setData('letter_id', e.target.value)}>
                    <option value="">{t.activities_any_letter || 'Any letter'}</option>
                    {letters.map((letter) => <option key={letter.id} value={letter.id}>{letter.arabic_character} {letter.display_name}</option>)}
                </select>
                <select className="form-input" aria-label={t.activities_harakah || 'Harakah'} value={form.data.harakah_id} onChange={(e) => form.setData('harakah_id', e.target.value)}>
                    <option value="">{t.activities_any_harakah || 'Any harakah'}</option>
                    {harakas.map((row) => <option key={row.id} value={row.id}>{row.symbol} {row.display_name}</option>)}
                </select>
                <select className="form-input" aria-label={t.activities_surah || 'Surah'} value={form.data.surah_id} onChange={(e) => form.setData('surah_id', e.target.value)}>
                    <option value="">{t.activities_no_range || 'No recitation range'}</option>
                    {surahs.map((row) => <option key={row.id} value={row.id}>{row.index}. {surahName(row)}</option>)}
                </select>
                <input className="form-input" type="number" min="1" placeholder={t.activities_ayah_start || 'Ayah start'} aria-label={t.activities_ayah_start || 'Ayah start'} value={form.data.ayah_start} onChange={(e) => form.setData('ayah_start', e.target.value)} />
                <input className="form-input" type="number" min="1" placeholder={t.activities_ayah_end || 'Ayah end'} aria-label={t.activities_ayah_end || 'Ayah end'} value={form.data.ayah_end} onChange={(e) => form.setData('ayah_end', e.target.value)} />
                <input className="form-input" type="number" min="1" placeholder={t.activities_max_score || 'Max score'} aria-label={t.activities_max_score || 'Max score'} value={form.data.max_score} onChange={(e) => form.setData('max_score', e.target.value)} />
                <textarea className="form-input md:col-span-2 min-h-32 font-mono text-xs" dir="ltr" aria-label={t.activities_content || 'Activity content (JSON)'} value={form.data.data} onChange={(e) => form.setData('data', e.target.value)} />
                {form.data.pattern === 'teacher_marked' && (
                    /* §36 "Play audio/voice submissions · View uploaded files". The kind
                       was already storable; until this slice nothing read it, so saying
                       what the choices are was pointless. Now it decides what the
                       student is shown and what the reviewer can play. */
                    <p className="md:col-span-2 text-xs text-gray-600">
                        <code>submission_kind</code>: <code>written</code> ({t.activities_kind_written || 'text box'}),
                        {' '}<code>file</code> ({t.activities_kind_file || 'image, PDF, document or audio upload'}),
                        {' '}<code>audio</code> ({t.activities_kind_audio || 'recording only — the reviewer plays it'}),
                        {' '}<code>canvas</code> ({t.activities_kind_canvas || 'handwriting — the student draws, the reviewer sees the image'}).
                    </p>
                )}
                <textarea className="form-input md:col-span-2 min-h-24 font-mono text-xs" dir="ltr" aria-label={t.activities_settings || 'Settings (JSON)'} value={form.data.settings} onChange={(e) => form.setData('settings', e.target.value)} />
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.is_required} onChange={(e) => form.setData('is_required', e.target.checked)} />
                    {t.outline_required || 'Required'}
                </label>
                <button type="submit" className="btn-primary" disabled={form.processing}>{t.activities_save || 'Save activity'}</button>
                {/* Every refusal, not a hand-picked three. The server also refuses
                    under `settings` (a recitation range outside its surah, an
                    unknown surah or letter) and `activity_type`; until the Qur'an A
                    walk those came back to a silent form with the typed values
                    still in the boxes — indistinguishable from a save (STATUS §5fm). */}
                <FormErrors errors={form.errors} className="md:col-span-2" />
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.catalog_col_title || 'Title'}</th>
                            <th className="px-3 py-2">{t.activities_pattern || 'Pattern'}</th>
                            <th className="px-3 py-2">{t.activities_col_type || 'Type'}</th>
                            <th className="px-3 py-2">{t.activities_col_score || 'Score'}</th>
                            <th className="px-3 py-2">{t.catalog_col_actions || 'Actions'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {activities.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={5}>{t.activities_none || 'No activities yet.'}</td></tr>
                        )}
                        {activities.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.title}</td>
                                <td className="px-3 py-2">{patternName(row.pattern)}</td>
                                <td className="px-3 py-2">{row.activity_type}</td>
                                <td className="px-3 py-2">{row.max_score}</td>
                                <td className="px-3 py-2">
                                    <button
                                        type="button"
                                        className="btn-secondary"
                                        aria-label={(t.activities_delete_aria || 'Delete :title').replace(':title', row.title)}
                                        onClick={() => router.delete(`/catalog/courses/${course.id}/activities/${row.id}`)}
                                    >
                                        {t.rubric_delete || 'Delete'}
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
