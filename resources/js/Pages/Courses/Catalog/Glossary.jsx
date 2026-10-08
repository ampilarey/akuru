import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

const EMPTY = {
    term: '',
    term_dv: '',
    term_ar: '',
    transliteration: '',
    meaning_primary: '',
    meaning_secondary: '',
    meaning_dv: '',
    meaning_ar: '',
    description: '',
    description_dv: '',
    description_ar: '',
    example_text: '',
    example_translation: '',
    example_text_dv: '',
    example_text_ar: '',
    tags: '',
    subject_id: '',
    level_id: '',
    // SPEC §22 "Glossary Media". The four columns were fillable, validated
    // against `media_files` and sent to the player — and the form had no file
    // input at all, so there was no way to obtain an id to put in them.
    audio_file: null,
    example_audio_file: null,
    image_file: null,
    diagram_file: null,
    clear_media: [],
};

// `key` names the slot in the `teach` book; `label` is the English.
const MEDIA_SLOTS = [
    { slot: 'audio_media_id', field: 'audio_file', key: 'glossary_media_audio', label: 'Pronunciation audio', accept: 'audio/*' },
    { slot: 'example_audio_media_id', field: 'example_audio_file', key: 'glossary_media_example_audio', label: 'Example audio', accept: 'audio/*' },
    { slot: 'image_media_id', field: 'image_file', key: 'glossary_media_image', label: 'Image', accept: 'image/*' },
    { slot: 'diagram_media_id', field: 'diagram_file', key: 'glossary_media_diagram', label: 'Diagram', accept: 'image/*' },
];

export default function Glossary({ rows, subjects = [], levels = [], t = {} }) {
    // Every string below is a key in the `teach` book (slice CT3, STATUS
    // §5on); the English is the fallback.
    const locale = usePage().props.locale || 'en';
    const named = (row) => row[`name_${locale}`] || row.name_en;
    const slotName = ({ key, label }) => t[key] || label;
    const [editingId, setEditingId] = useState(null);
    const form = useForm({ ...EMPTY });

    const startEdit = (row) => {
        setEditingId(row.id);
        form.setData({
            term: row.term || '',
            term_dv: row.term_dv || '',
            term_ar: row.term_ar || '',
            transliteration: row.transliteration || '',
            meaning_primary: row.meaning_primary || '',
            meaning_secondary: row.meaning_secondary || '',
            meaning_dv: row.meaning_dv || '',
            meaning_ar: row.meaning_ar || '',
            description: row.description || '',
            description_dv: row.description_dv || '',
            description_ar: row.description_ar || '',
            example_text: row.example_text || '',
            example_translation: row.example_translation || '',
            example_text_dv: row.example_text_dv || '',
            example_text_ar: row.example_text_ar || '',
            tags: (row.tags || []).join(', '),
            subject_id: row.subject_id || '',
            level_id: row.level_id || '',
            audio_file: null,
            example_audio_file: null,
            image_file: null,
            diagram_file: null,
            clear_media: [],
        });
    };

    const editingRow = rows.find((row) => row.id === editingId) || null;
    const toggleClear = (slot, on) => {
        const next = new Set(form.data.clear_media || []);
        if (on) {
            next.add(slot);
        } else {
            next.delete(slot);
        }
        form.setData('clear_media', Array.from(next));
    };

    const cancelEdit = () => {
        setEditingId(null);
        form.setData({ ...EMPTY });
    };

    return (
        <AppShell title={t.outline_glossary || 'Glossary'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/glossary/export">{t.catalog_export || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    // A multipart PUT is not parsed by PHP, so an edit that
                    // carries a file has to be a POST with `_method` spoofing —
                    // the same shape the question bank needed once it grew an
                    // upload. `form.transform(...)` returns undefined in
                    // @inertiajs/react v3, so the two statements stay apart.
                    form.transform((data) => (editingId ? { ...data, _method: 'put' } : data));
                    form.post(editingId ? `/catalog/glossary/${editingId}` : '/catalog/glossary', {
                        preserveScroll: true,
                        forceFormData: true,
                        onSuccess: () => { if (editingId) cancelEdit(); },
                    });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-3"
            >
                <p className="md:col-span-3 text-sm font-medium">{editingId ? (t.glossary_edit || 'Edit term') : (t.glossary_add || 'Add term')}</p>
                <input className="form-input" placeholder={t.glossary_term_en || 'Term (EN)'} aria-label={t.glossary_term_en || 'Term (EN)'} dir="ltr" value={form.data.term} onChange={(e) => form.setData('term', e.target.value)} />
                <input className="form-input" placeholder={t.glossary_term_dv || 'Term (DV)'} aria-label={t.glossary_term_dv || 'Term (DV)'} dir="rtl" value={form.data.term_dv} onChange={(e) => form.setData('term_dv', e.target.value)} />
                <input className="form-input" placeholder={t.glossary_term_ar || 'Term (AR)'} aria-label={t.glossary_term_ar || 'Term (AR)'} dir="rtl" value={form.data.term_ar} onChange={(e) => form.setData('term_ar', e.target.value)} />
                <input className="form-input" placeholder={t.glossary_transliteration || 'Transliteration'} aria-label={t.glossary_transliteration || 'Transliteration'} dir="ltr" value={form.data.transliteration} onChange={(e) => form.setData('transliteration', e.target.value)} />
                <textarea className="form-input md:col-span-2 min-h-16" placeholder={t.glossary_meaning_primary || 'Meaning (primary / EN)'} aria-label={t.glossary_meaning_primary || 'Meaning (primary / EN)'} dir="ltr" value={form.data.meaning_primary} onChange={(e) => form.setData('meaning_primary', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder={t.glossary_meaning_secondary || 'Meaning (secondary)'} aria-label={t.glossary_meaning_secondary || 'Meaning (secondary)'} dir="ltr" value={form.data.meaning_secondary} onChange={(e) => form.setData('meaning_secondary', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder={t.glossary_meaning_dv || 'Meaning (DV)'} aria-label={t.glossary_meaning_dv || 'Meaning (DV)'} dir="rtl" value={form.data.meaning_dv} onChange={(e) => form.setData('meaning_dv', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder={t.glossary_meaning_ar || 'Meaning (AR)'} aria-label={t.glossary_meaning_ar || 'Meaning (AR)'} dir="rtl" value={form.data.meaning_ar} onChange={(e) => form.setData('meaning_ar', e.target.value)} />
                <textarea className="form-input md:col-span-3 min-h-16" placeholder={t.glossary_description_en || 'Description (EN)'} aria-label={t.glossary_description_en || 'Description (EN)'} dir="ltr" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder={t.glossary_description_dv || 'Description (DV)'} aria-label={t.glossary_description_dv || 'Description (DV)'} dir="rtl" value={form.data.description_dv} onChange={(e) => form.setData('description_dv', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder={t.glossary_description_ar || 'Description (AR)'} aria-label={t.glossary_description_ar || 'Description (AR)'} dir="rtl" value={form.data.description_ar} onChange={(e) => form.setData('description_ar', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder={t.glossary_example_en || 'Example (EN)'} aria-label={t.glossary_example_en || 'Example (EN)'} dir="ltr" value={form.data.example_text} onChange={(e) => form.setData('example_text', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder={t.glossary_example_translation || 'Example translation'} aria-label={t.glossary_example_translation || 'Example translation'} dir="ltr" value={form.data.example_translation} onChange={(e) => form.setData('example_translation', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder={t.glossary_example_dv || 'Example (DV)'} aria-label={t.glossary_example_dv || 'Example (DV)'} dir="rtl" value={form.data.example_text_dv} onChange={(e) => form.setData('example_text_dv', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder={t.glossary_example_ar || 'Example (AR)'} aria-label={t.glossary_example_ar || 'Example (AR)'} dir="rtl" value={form.data.example_text_ar} onChange={(e) => form.setData('example_text_ar', e.target.value)} />
                <input className="form-input" placeholder={t.glossary_tags || 'Tags (comma separated)'} aria-label={t.glossary_tags || 'Tags (comma separated)'} value={form.data.tags} onChange={(e) => form.setData('tags', e.target.value)} />
                <select className="form-input" aria-label={t.catalog_col_subject || 'Subject'} value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                    <option value="">{t.questions_any_subject || 'Any subject'}</option>
                    {subjects.map((subject) => <option key={subject.id} value={subject.id}>{named(subject)}</option>)}
                </select>
                <select className="form-input" aria-label={t.glossary_level || 'Level'} value={form.data.level_id} onChange={(e) => form.setData('level_id', e.target.value)}>
                    <option value="">{t.glossary_any_level || 'Any level'}</option>
                    {levels.map((level) => <option key={level.id} value={level.id}>{named(level)}</option>)}
                </select>
                {/* §22 "Glossary Media": audio, image, example audio, diagram,
                    all through the centralized media system. Each slot is typed,
                    so §30's mime list and size cap for that kind apply. */}
                <fieldset className="md:col-span-3 rounded-lg border bg-[#F9F4EE] p-3">
                    <legend className="px-1 text-xs font-medium uppercase tracking-wide text-gray-600">{t.glossary_media || 'Media'}</legend>
                    <div className="grid gap-3 md:grid-cols-4">
                        {MEDIA_SLOTS.map(({ slot, field, key, label, accept }) => (
                            <label key={slot} className="text-sm">
                                <span className="block text-xs text-gray-600">{slotName({ key, label })}</span>
                                <input
                                    className="form-input"
                                    type="file"
                                    accept={accept}
                                    onChange={(e) => form.setData(field, e.target.files?.[0] || null)}
                                />
                                {editingRow?.[slot] && (
                                    <span className="mt-1 flex items-center gap-2 text-xs text-gray-600">
                                        <span>{t.glossary_attached || 'Attached'}</span>
                                        <label className="flex items-center gap-1">
                                            <input
                                                type="checkbox"
                                                checked={(form.data.clear_media || []).includes(slot)}
                                                onChange={(e) => toggleClear(slot, e.target.checked)}
                                            />
                                            {t.outline_remove || 'Remove'}
                                        </label>
                                    </span>
                                )}
                                {form.errors[field] && <span className="text-xs text-red-600">{form.errors[field]}</span>}
                            </label>
                        ))}
                    </div>
                </fieldset>
                <div className="md:col-span-3 flex flex-wrap gap-2">
                    <button type="submit" className="btn-primary" disabled={form.processing}>{editingId ? (t.glossary_update || 'Update term') : (t.glossary_save || 'Save term')}</button>
                    {editingId && (
                        <button type="button" className="btn-secondary" onClick={cancelEdit}>{t.rubric_cancel || 'Cancel'}</button>
                    )}
                </div>
                {form.errors.term && <p className="md:col-span-3 text-sm text-red-600">{form.errors.term}</p>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.outline_term || 'Term'}</th>
                            <th className="px-3 py-2">{t.glossary_col_translations || 'DV / AR'}</th>
                            <th className="px-3 py-2">{t.glossary_col_meaning || 'Meaning'}</th>
                            <th className="px-3 py-2">{t.glossary_col_tags || 'Tags'}</th>
                            <th className="px-3 py-2">{t.glossary_media || 'Media'}</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td className="px-3 py-4 text-gray-500" colSpan={6}>{t.glossary_none || 'No terms yet.'}</td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">
                                    <span dir="ltr">{row.term}</span>
                                    {row.transliteration && <span className="ms-2 text-xs text-gray-500">{row.transliteration}</span>}
                                </td>
                                <td className="px-3 py-2">
                                    <span dir="rtl">{row.term_dv || '—'}</span>
                                    {' / '}
                                    <span dir="rtl">{row.term_ar || '—'}</span>
                                </td>
                                <td className="px-3 py-2">{row.meaning_primary || '—'}</td>
                                <td className="px-3 py-2">{(row.tags || []).join(', ') || '—'}</td>
                                <td className="px-3 py-2 text-xs text-gray-600">
                                    {MEDIA_SLOTS.filter(({ slot }) => row[slot]).map(slotName).join(', ') || '—'}
                                </td>
                                <td className="px-3 py-2 text-end">
                                    <button type="button" className="text-sm text-[#7C2D37] hover:underline" aria-label={(t.glossary_edit_aria || 'Edit :term').replace(':term', row.term)} onClick={() => startEdit(row)}>{t.rubric_edit || 'Edit'}</button>
                                    {' · '}
                                    <button type="button" className="text-sm text-red-700" aria-label={(t.glossary_delete_aria || 'Delete :term').replace(':term', row.term)} onClick={() => router.delete(`/catalog/glossary/${row.id}`)}>{t.rubric_delete || 'Delete'}</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
