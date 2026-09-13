import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
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

const MEDIA_SLOTS = [
    { slot: 'audio_media_id', field: 'audio_file', label: 'Pronunciation audio', accept: 'audio/*' },
    { slot: 'example_audio_media_id', field: 'example_audio_file', label: 'Example audio', accept: 'audio/*' },
    { slot: 'image_media_id', field: 'image_file', label: 'Image', accept: 'image/*' },
    { slot: 'diagram_media_id', field: 'diagram_file', label: 'Diagram', accept: 'image/*' },
];

export default function Glossary({ rows, subjects = [], levels = [] }) {
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
        <AppShell title="Glossary">
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/glossary/export">Export CSV</a>
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
                <p className="md:col-span-3 text-sm font-medium">{editingId ? 'Edit term' : 'Add term'}</p>
                <input className="form-input" placeholder="Term (EN)" dir="ltr" value={form.data.term} onChange={(e) => form.setData('term', e.target.value)} />
                <input className="form-input" placeholder="Term (DV)" dir="rtl" value={form.data.term_dv} onChange={(e) => form.setData('term_dv', e.target.value)} />
                <input className="form-input" placeholder="Term (AR)" dir="rtl" value={form.data.term_ar} onChange={(e) => form.setData('term_ar', e.target.value)} />
                <input className="form-input" placeholder="Transliteration" dir="ltr" value={form.data.transliteration} onChange={(e) => form.setData('transliteration', e.target.value)} />
                <textarea className="form-input md:col-span-2 min-h-16" placeholder="Meaning (primary / EN)" dir="ltr" value={form.data.meaning_primary} onChange={(e) => form.setData('meaning_primary', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder="Meaning (secondary)" dir="ltr" value={form.data.meaning_secondary} onChange={(e) => form.setData('meaning_secondary', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder="Meaning (DV)" dir="rtl" value={form.data.meaning_dv} onChange={(e) => form.setData('meaning_dv', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder="Meaning (AR)" dir="rtl" value={form.data.meaning_ar} onChange={(e) => form.setData('meaning_ar', e.target.value)} />
                <textarea className="form-input md:col-span-3 min-h-16" placeholder="Description (EN)" dir="ltr" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder="Description (DV)" dir="rtl" value={form.data.description_dv} onChange={(e) => form.setData('description_dv', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder="Description (AR)" dir="rtl" value={form.data.description_ar} onChange={(e) => form.setData('description_ar', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder="Example (EN)" dir="ltr" value={form.data.example_text} onChange={(e) => form.setData('example_text', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder="Example translation" dir="ltr" value={form.data.example_translation} onChange={(e) => form.setData('example_translation', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder="Example (DV)" dir="rtl" value={form.data.example_text_dv} onChange={(e) => form.setData('example_text_dv', e.target.value)} />
                <textarea className="form-input min-h-16" placeholder="Example (AR)" dir="rtl" value={form.data.example_text_ar} onChange={(e) => form.setData('example_text_ar', e.target.value)} />
                <input className="form-input" placeholder="Tags (comma separated)" value={form.data.tags} onChange={(e) => form.setData('tags', e.target.value)} />
                <select className="form-input" value={form.data.subject_id} onChange={(e) => form.setData('subject_id', e.target.value)}>
                    <option value="">Any subject</option>
                    {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name_en}</option>)}
                </select>
                <select className="form-input" value={form.data.level_id} onChange={(e) => form.setData('level_id', e.target.value)}>
                    <option value="">Any level</option>
                    {levels.map((level) => <option key={level.id} value={level.id}>{level.name_en}</option>)}
                </select>
                {/* §22 "Glossary Media": audio, image, example audio, diagram,
                    all through the centralized media system. Each slot is typed,
                    so §30's mime list and size cap for that kind apply. */}
                <fieldset className="md:col-span-3 rounded-lg border bg-[#F9F4EE] p-3">
                    <legend className="px-1 text-xs font-medium uppercase tracking-wide text-gray-600">Media</legend>
                    <div className="grid gap-3 md:grid-cols-4">
                        {MEDIA_SLOTS.map(({ slot, field, label, accept }) => (
                            <label key={slot} className="text-sm">
                                <span className="block text-xs text-gray-600">{label}</span>
                                <input
                                    className="form-input"
                                    type="file"
                                    accept={accept}
                                    onChange={(e) => form.setData(field, e.target.files?.[0] || null)}
                                />
                                {editingRow?.[slot] && (
                                    <span className="mt-1 flex items-center gap-2 text-xs text-gray-600">
                                        <span>Attached</span>
                                        <label className="flex items-center gap-1">
                                            <input
                                                type="checkbox"
                                                checked={(form.data.clear_media || []).includes(slot)}
                                                onChange={(e) => toggleClear(slot, e.target.checked)}
                                            />
                                            Remove
                                        </label>
                                    </span>
                                )}
                                {form.errors[field] && <span className="text-xs text-red-600">{form.errors[field]}</span>}
                            </label>
                        ))}
                    </div>
                </fieldset>
                <div className="md:col-span-3 flex flex-wrap gap-2">
                    <button type="submit" className="btn-primary" disabled={form.processing}>{editingId ? 'Update term' : 'Save term'}</button>
                    {editingId && (
                        <button type="button" className="btn-secondary" onClick={cancelEdit}>Cancel</button>
                    )}
                </div>
                {form.errors.term && <p className="md:col-span-3 text-sm text-red-600">{form.errors.term}</p>}
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">Term</th>
                            <th className="px-3 py-2">DV / AR</th>
                            <th className="px-3 py-2">Meaning</th>
                            <th className="px-3 py-2">Tags</th>
                            <th className="px-3 py-2">Media</th>
                            <th className="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
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
                                    {MEDIA_SLOTS.filter(({ slot }) => row[slot]).map(({ label }) => label).join(', ') || '—'}
                                </td>
                                <td className="px-3 py-2 text-end">
                                    <button type="button" className="text-sm text-[#7C2D37] hover:underline" onClick={() => startEdit(row)}>Edit</button>
                                    {' · '}
                                    <button type="button" className="text-sm text-red-700" onClick={() => router.delete(`/catalog/glossary/${row.id}`)}>Delete</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
