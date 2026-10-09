import { Link, router, useForm } from '@inertiajs/react';
import AppShell from '../../../../Layouts/AppShell';
import FormErrors from '../../../../Components/FormErrors';

// `:name` placeholders in a phrase, filled in.
const fill = (text, values) => Object.entries(values).reduce((out, [key, value]) => out.replace(`:${key}`, value), text);

export default function MushafShow({ mushaf, can_manage = false, can_approve = false, can_lock = false, t = {} }) {
    const importForm = useForm({
        surah_number: '',
        ayah_number: '',
        page_number: '',
        text_uthmani: '',
        words: ['', ''],
    });
    // A mushaf's pages, given after the upload (STATUS §5pt): the upload form
    // made them only when it was given a count, and nothing else could.
    const pagesForm = useForm({ page_count: '' });
    const editable = can_manage && !mushaf.locked;

    const setWord = (index, value) => {
        const next = [...importForm.data.words];
        next[index] = value;
        importForm.setData('words', next);
    };

    const submitImport = (event) => {
        event.preventDefault();
        importForm.post(`/quran/mushafs/${mushaf.id}/import-ayah`, {
            preserveScroll: true,
            onSuccess: () => importForm.reset('ayah_number', 'text_uthmani', 'words'),
        });
    };

    const submitPages = (event) => {
        event.preventDefault();
        pagesForm.post(`/quran/mushafs/${mushaf.id}/pages`, { preserveScroll: true, onSuccess: () => pagesForm.reset() });
    };

    return (
        <AppShell title={mushaf.name}>
            <p className="mb-4 text-sm text-gray-600">
                {fill(t.mushaf_summary || 'Hash: :hash · Pages: :pages · Ayahs: :ayahs · Words: :words', {
                    hash: mushaf.source_hash ?? '—',
                    pages: mushaf.pages_count,
                    ayahs: mushaf.ayahs_count,
                    words: mushaf.words_count,
                })}
                {mushaf.is_active && <span className="ms-2 rounded bg-green-100 px-2 py-0.5 text-green-800">{t.mushaf_col_active || 'Active'}</span>}
                {mushaf.locked && <span className="ms-2 rounded bg-gray-200 px-2 py-0.5">{t.mushaf_col_locked || 'Locked'}</span>}
            </p>

            <div className="mb-6 flex flex-wrap gap-2">
                {can_approve && (
                    <button
                        type="button"
                        className="btn-primary"
                        onClick={() => router.post(`/quran/mushafs/${mushaf.id}/approve`, {}, { preserveScroll: true })}
                    >
                        {t.mushaf_approve || 'Approve & activate'}
                    </button>
                )}
                {can_lock && (
                    <button
                        type="button"
                        className="btn-secondary"
                        onClick={() => router.post(`/quran/mushafs/${mushaf.id}/lock`, {}, { preserveScroll: true })}
                    >
                        {t.mushaf_lock || 'Lock'}
                    </button>
                )}
                {/* Page 1 is a page placeholder; a mushaf uploaded without a page
                    count has none, and the link led to a 404 (slice CT5b). */}
                {mushaf.pages_count > 0 ? (
                    <Link className="btn-secondary" href={`/quran/mushafs/${mushaf.id}/pages/1`}>{t.mushaf_map_first || 'Map page 1'}</Link>
                ) : (
                    <p className="text-sm text-amber-700">{t.mushaf_no_pages || 'This mushaf has no pages yet. Give it its number of pages below.'}</p>
                )}
            </div>

            {mushaf.locked && (
                <p className="mb-6 max-w-2xl rounded border border-gray-300 bg-gray-50 p-3 text-sm text-gray-700" data-testid="mushaf-locked">
                    {t.mushaf_locked_note || 'This mushaf is locked. Its ayahs, words, pages and page images can no longer be changed.'}
                </p>
            )}

            {editable && (
                <form onSubmit={submitPages} className="mb-6 max-w-2xl space-y-2 rounded-lg border bg-white p-4" data-testid="mushaf-pages">
                    <h2 className="font-semibold">{t.mushaf_pages_title || 'Pages'}</h2>
                    <p className="text-sm text-gray-600">{t.mushaf_pages_hint || 'Pages are added up to this number, and none is ever taken away: a page may already carry word boxes.'}</p>
                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            className="form-input w-32" type="number" min="1" max="604" required
                            placeholder={t.mushaf_pages_count || 'Number of pages'} aria-label={t.mushaf_pages_count || 'Number of pages'}
                            value={pagesForm.data.page_count}
                            onChange={(e) => pagesForm.setData('page_count', e.target.value)}
                        />
                        <button type="submit" className="btn-primary" disabled={pagesForm.processing}>{t.mushaf_pages_add || 'Add pages'}</button>
                    </div>
                    <FormErrors errors={pagesForm.errors} />
                </form>
            )}

            {editable && (
                <form onSubmit={submitImport} className="max-w-2xl space-y-3 rounded-lg border bg-white p-4">
                    <h2 className="font-semibold">{t.mushaf_import_title || 'Import ayah / words'}</h2>
                    <div className="grid grid-cols-3 gap-2">
                        <input
                            className="form-input" type="number" min="1" max="114" required
                            placeholder={t.mushaf_surah || 'Surah'} aria-label={t.mushaf_surah || 'Surah'}
                            value={importForm.data.surah_number}
                            onChange={(e) => importForm.setData('surah_number', e.target.value)}
                        />
                        <input
                            className="form-input" type="number" min="1" required
                            placeholder={t.qt_ayah || 'Ayah'} aria-label={t.qt_ayah || 'Ayah'}
                            value={importForm.data.ayah_number}
                            onChange={(e) => importForm.setData('ayah_number', e.target.value)}
                        />
                        <input
                            className="form-input" type="number" min="1"
                            placeholder={t.mushaf_page || 'Page'} aria-label={t.mushaf_page || 'Page'}
                            value={importForm.data.page_number}
                            onChange={(e) => importForm.setData('page_number', e.target.value)}
                        />
                    </div>
                    <textarea
                        className="form-input w-full" rows={2} dir="rtl" required
                        placeholder={t.mushaf_ayah_text || 'Ayah text (Uthmani)'} aria-label={t.mushaf_ayah_text || 'Ayah text (Uthmani)'}
                        value={importForm.data.text_uthmani}
                        onChange={(e) => importForm.setData('text_uthmani', e.target.value)}
                    />
                    {importForm.data.words.map((word, index) => (
                        <input
                            key={index}
                            className="form-input w-full" dir="rtl"
                            placeholder={fill(t.mushaf_word || 'Word :n', { n: index + 1 })}
                            aria-label={fill(t.mushaf_word || 'Word :n', { n: index + 1 })}
                            value={word}
                            onChange={(e) => setWord(index, e.target.value)}
                        />
                    ))}
                    <div className="flex gap-2">
                        <button
                            type="button" className="btn-secondary"
                            onClick={() => importForm.setData('words', [...importForm.data.words, ''])}
                        >
                            {t.mushaf_add_word || 'Add word'}
                        </button>
                        <button type="submit" className="btn-primary" disabled={importForm.processing}>{t.mushaf_import || 'Import'}</button>
                    </div>
                    {Object.values(importForm.errors).map((message) => (
                        <p key={message} className="text-sm text-red-600">{message}</p>
                    ))}
                </form>
            )}
        </AppShell>
    );
}
