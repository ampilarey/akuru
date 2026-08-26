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
};

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
        });
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
                    if (editingId) {
                        form.put(`/catalog/glossary/${editingId}`, { preserveScroll: true, onSuccess: cancelEdit });
                    } else {
                        form.post('/catalog/glossary', { preserveScroll: true });
                    }
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
                    <thead className="bg-[#F3EBE0] text-left">
                        <tr>
                            <th className="px-3 py-2">Term</th>
                            <th className="px-3 py-2">DV / AR</th>
                            <th className="px-3 py-2">Meaning</th>
                            <th className="px-3 py-2">Tags</th>
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
